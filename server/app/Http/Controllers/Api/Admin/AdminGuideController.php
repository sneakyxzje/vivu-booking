<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminGuideController extends Controller
{
    public function index(): JsonResponse
    {
        $guides = User::where('role', 'guide')
            ->withCount(['assignedSchedules as assigned_tours_count'])
            ->with(['guideProfile', 'guideCategories:id,name'])
            ->latest()
            ->paginate(10);
        return $this->success($guides, 'Lấy danh sách hướng dẫn viên thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $validated['role'] = 'guide';
        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'active';

        $guide = User::create($validated);

        return $this->success($guide, 'Tạo hướng dẫn viên thành công', 201);
    }

    public function show(int $id): JsonResponse
    {
        $guide = User::where('role', 'guide')
            ->withCount(['assignedSchedules as assigned_tours_count'])
            ->with(['guideProfile', 'guideCategories:id,name'])
            ->find($id);

        if (!$guide) {
            return $this->error('Không tìm thấy hướng dẫn viên', 404);
        }

        return $this->success($guide, 'Lấy chi tiết hướng dẫn viên thành công');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $guide = User::where('role', 'guide')->find($id);

        if (!$guide) {
            return $this->error('Không tìm thấy hướng dẫn viên', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $guide->update($validated);

        return $this->success($guide, 'Cập nhật hướng dẫn viên thành công');
    }

    /**
     * Lưu hồ sơ năng lực.
     *
     * Tách khỏi `update()` vì hai thứ khác bản chất: `update()` sửa tài khoản đăng nhập, cái này
     * sửa thông tin nghề nghiệp. Gộp lại thì một lần đổi trạng thái tài khoản cũng phải gửi kèm
     * cả danh sách ngôn ngữ.
     *
     * Ghi đè cả hồ sơ chứ không vá từng trường: đây là một biểu mẫu người ta điền và bấm lưu,
     * bỏ trống một ô nghĩa là xóa giá trị cũ, không phải giữ nguyên.
     */
    public function updateProfile(Request $request, int $id): JsonResponse
    {
        $guide = User::where('role', 'guide')->find($id);

        if (!$guide) {
            return $this->error('Không tìm thấy hướng dẫn viên', 404);
        }

        $validated = $request->validate([
            /*
             * Phần tử để `nullable`, không phải `string`.
             *
             * Ô nhập tách bằng dấu phẩy để lại phần tử rỗng, mà middleware của Laravel đổi chuỗi
             * rỗng thành null trước khi validate - bắt buộc `string` thì một dấu phẩy thừa thành
             * lỗi 422 khó hiểu. Nhận vào rồi lọc ở dưới.
             */
            'languages' => ['nullable', 'array'],
            'languages.*' => ['nullable', 'string', 'max:50'],
            'regions' => ['nullable', 'array'],
            'regions.*' => ['nullable', 'string', 'max:100'],
            'max_group_size' => ['nullable', 'integer', 'min:1', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($guide, $validated) {
            $guide->guideProfile()->updateOrCreate(
                ['user_id' => $guide->getKey()],
                [
                    // Lọc chuỗi rỗng: ô nhập tách bằng dấu phẩy rất dễ để lại phần tử trống.
                    'languages' => $this->locDanhSach($validated['languages'] ?? []),
                    'regions' => $this->locDanhSach($validated['regions'] ?? []),
                    'max_group_size' => $validated['max_group_size'] ?? null,
                    'note' => $validated['note'] ?? null,
                ],
            );

            $guide->guideCategories()->sync($validated['category_ids']);
        });

        return $this->success(
            $guide->fresh(['guideProfile', 'guideCategories:id,name']),
            'Đã lưu hồ sơ năng lực.',
        );
    }

    /**
     * @param  array<int, string>  $gia
     * @return array<int, string>
     */
    private function locDanhSach(array $gia): array
    {
        return collect($gia)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Xóa hướng dẫn viên — chặn khi họ còn đang phụ trách một chuyến đã chốt hoặc đang đi.
     *
     * ## Vì sao phải chặn
     *
     * `User` dùng xóa mềm, và quan hệ `guides()` của chuyến chịu global scope của nó. Nghĩa là xóa
     * một hướng dẫn viên sẽ **âm thầm gỡ họ khỏi mọi chuyến** họ đang dẫn: bảng phân công vẫn còn
     * hàng nhưng không truy vấn nào nhìn thấy nữa. Một đoàn ba mươi khách đang trên đường bỗng
     * không còn ai phụ trách trên hệ thống — hướng dẫn viên mất luôn quyền điểm danh, báo sự cố và
     * xin bàn giao, giữa lúc họ đang đứng cùng đoàn.
     *
     * Xóa tour đã có hàng rào đúng như vậy từ lâu (`TourDeletionService::blockers()`), kèm cả màn
     * xem trước. Xóa người thì không có gì cả — cùng một hậu quả vận hành, hai mức bảo vệ khác nhau.
     *
     * ## Vì sao không chặn với chuyến chưa chốt
     *
     * Chuyến còn ở giai đoạn bán thì đổi người là chuyện bình thường của xếp lịch, và điều hành
     * còn thời gian cử người khác. Ranh giới là lúc chuyến đã chốt chạy — từ đó có khách đã trả
     * tiền và đang trông vào đúng chuyến ấy.
     *
     * ## Khóa hay xóa
     *
     * Người nghỉ việc thì **khóa tài khoản** (`PUT /admin/users/{id}/status`) đúng hơn: nó thu hồi
     * phiên đăng nhập, chặn đăng nhập mới, mà vẫn giữ tên họ trên các biên bản bàn giao và nhật ký
     * điểm danh cũ. Câu thông báo bên dưới nói thẳng lối đi đó.
     */
    public function destroy(int $id): JsonResponse
    {
        $guide = User::where('role', 'guide')->find($id);

        if (!$guide) {
            return $this->error('Không tìm thấy hướng dẫn viên', 404);
        }

        $dangPhuTrach = TourSchedule::query()
            ->whereHas('guides', fn ($q) => $q->whereKey($guide->id))
            ->whereIn('status', [
                ScheduleStatus::Confirmed->value,
                ScheduleStatus::InProgress->value,
            ])
            ->count();

        if ($dangPhuTrach > 0) {
            return $this->error(sprintf(
                'Không xóa được: %s còn phụ trách %d chuyến đã chốt hoặc đang đi. Xóa bây giờ là gỡ '
                    . 'họ khỏi đoàn giữa chừng, và đoàn mất người chịu trách nhiệm trên hệ thống. '
                    . 'Hãy bàn giao các chuyến đó cho người khác trước, hoặc khóa tài khoản thay vì '
                    . 'xóa nếu họ chỉ nghỉ việc.',
                $guide->name,
                $dangPhuTrach,
            ), 422);
        }

        /*
         * Thu hồi phiên đăng nhập, giống hệt lúc khóa tài khoản.
         *
         * Xóa mềm không làm token hết hiệu lực: `auth:sanctum` vẫn nhận nó, và các tuyến dùng chung
         * như `/api/me` không đi qua phép kiểm vai trò nào. Người vừa bị xóa mà còn giữ token trong
         * trình duyệt thì vẫn gọi được API.
         */
        $guide->tokens()->delete();
        $guide->delete(); // Xóa mềm (Soft Delete) do đã thêm trait SoftDeletes vào model User

        return $this->success(null, sprintf(
            'Đã xóa %s và thu hồi mọi phiên đăng nhập. Biên bản bàn giao và nhật ký điểm danh cũ '
                . 'vẫn giữ nguyên tên họ.',
            $guide->name,
        ));
    }
}
