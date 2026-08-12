<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * B05 - Quản lý chính sách hủy.
 *
 * Mỗi chính sách là một bảng phí gồm nhiều bậc theo số giờ còn lại tới khởi hành.
 * Tour trỏ tới chính sách, đơn hàng sao chép chính sách tại thời điểm đặt.
 */
class AdminCancellationPolicyController extends Controller
{
    public function index(): JsonResponse
    {
        $policies = CancellationPolicy::query()
            ->with('rules')
            ->withCount('tours')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->success($policies, 'Lấy danh sách chính sách hủy thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        if ($loi = $this->validateRules($validated['rules'])) {
            return $this->error($loi, 422);
        }

        $policy = DB::transaction(function () use ($validated) {
            $policy = CancellationPolicy::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            $this->syncRules($policy, $validated['rules']);
            $this->keepSingleDefault($policy);

            return $policy;
        });

        return $this->success($policy->load('rules'), 'Đã tạo chính sách hủy.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $policy = CancellationPolicy::query()->find($id);

        if (!$policy) {
            return $this->error('Không tìm thấy chính sách hủy.', 404);
        }

        $validated = $this->validatePayload($request);

        if ($loi = $this->validateRules($validated['rules'])) {
            return $this->error($loi, 422);
        }

        DB::transaction(function () use ($policy, $validated) {
            $policy->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            $this->syncRules($policy, $validated['rules']);
            $this->keepSingleDefault($policy);
        });

        return $this->success($policy->fresh('rules'), 'Đã cập nhật chính sách hủy.');
    }

    public function destroy(int $id): JsonResponse
    {
        $policy = CancellationPolicy::query()->withCount('tours')->find($id);

        if (!$policy) {
            return $this->error('Không tìm thấy chính sách hủy.', 404);
        }

        if ($policy->is_default) {
            return $this->error(
                'Không thể xóa chính sách mặc định. Hãy đặt một chính sách khác làm mặc định trước.',
                422,
            );
        }

        if ($policy->tours_count > 0) {
            return $this->error(
                "Chính sách này đang được {$policy->tours_count} tour sử dụng, không thể xóa.",
                422,
            );
        }

        // Đơn hàng đã sao chép chính sách này thì phải giữ lại, nếu không sẽ không giải thích
        // được điều khoản mà khách đã đồng ý lúc đặt.
        $soDon = Booking::query()->where('cancellation_policy_id', $policy->id)->count();

        if ($soDon > 0) {
            return $this->error(
                "Có {$soDon} đơn đặt tour đang tham chiếu chính sách này. Xóa đi sẽ mất căn cứ "
                . 'giải thích điều khoản đã áp cho khách.',
                422,
            );
        }

        $policy->delete();

        return $this->success(null, 'Đã xóa chính sách hủy.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.min_hours_before' => ['required', 'integer', 'min:0'],
            'rules.*.max_hours_before' => ['nullable', 'integer', 'min:1'],
            'rules.*.refund_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'rules.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'rules.required' => 'Chính sách hủy phải có ít nhất một bậc phí.',
            'rules.*.refund_percent.max' => 'Mức hoàn không vượt quá 100 phần trăm.',
        ]);
    }

    /**
     * Hai ràng buộc không diễn đạt được bằng luật validate vì phải so các phần tử với nhau.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function validateRules(array $rules): ?string
    {
        foreach ($rules as $index => $rule) {
            $min = (int) $rule['min_hours_before'];
            $max = $rule['max_hours_before'] ?? null;

            if ($max !== null && (int) $max <= $min) {
                return sprintf('Bậc thứ %d: mốc trên phải lớn hơn mốc dưới.', $index + 1);
            }
        }

        // Phải có bậc phủ mốc 0 giờ, nếu không thì hủy sát ngày đi sẽ không rơi vào bậc nào
        // và hệ thống lặng lẽ hoàn 0 phần trăm mà không có căn cứ nào ghi ra.
        $coBacTuKhong = collect($rules)->contains(
            fn ($rule) => (int) $rule['min_hours_before'] === 0
        );

        if (!$coBacTuKhong) {
            return 'Phải có một bậc bắt đầu từ 0 giờ để phủ trường hợp hủy sát ngày khởi hành.';
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function syncRules(CancellationPolicy $policy, array $rules): void
    {
        $policy->rules()->delete();

        foreach ($rules as $rule) {
            $policy->rules()->create([
                'min_hours_before' => (int) $rule['min_hours_before'],
                'max_hours_before' => isset($rule['max_hours_before']) ? (int) $rule['max_hours_before'] : null,
                'refund_percent' => (int) $rule['refund_percent'],
                'note' => $rule['note'] ?? null,
            ]);
        }
    }

    /** Chỉ được có đúng một chính sách mặc định tại một thời điểm. */
    private function keepSingleDefault(CancellationPolicy $policy): void
    {
        if (!$policy->is_default) {
            return;
        }

        CancellationPolicy::query()
            ->whereKeyNot($policy->getKey())
            ->update(['is_default' => false]);
    }
}
