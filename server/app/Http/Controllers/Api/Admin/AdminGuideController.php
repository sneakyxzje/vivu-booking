<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
            'card_number' => ['nullable', 'string', 'max:50'],
            'card_expiry' => ['nullable', 'date'],
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
        ], [
            'card_expiry.date' => 'Ngày hết hạn thẻ không hợp lệ.',
        ]);

        DB::transaction(function () use ($guide, $validated) {
            $guide->guideProfile()->updateOrCreate(
                ['user_id' => $guide->getKey()],
                [
                    'card_number' => $validated['card_number'] ?? null,
                    'card_expiry' => $validated['card_expiry'] ?? null,
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

    public function destroy(int $id): JsonResponse
    {
        $guide = User::where('role', 'guide')->find($id);

        if (!$guide) {
            return $this->error('Không tìm thấy hướng dẫn viên', 404);
        }

        $guide->delete(); // Xóa mềm (Soft Delete) do đã thêm trait SoftDeletes vào model User

        return $this->success(null, 'Xóa hướng dẫn viên thành công');
    }
}
