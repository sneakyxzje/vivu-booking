<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServiceController extends Controller
{
    /**
     * Lấy danh sách tất cả dịch vụ phát sinh (kèm số tour đang dùng).
     */
    public function index(): JsonResponse
    {
        $services = Service::query()
            ->withCount('tours') // Đếm số tour đang dùng dịch vụ này
            ->orderBy('name')
            ->paginate(20);

        return $this->success($services, 'Lấy danh sách dịch vụ phát sinh thành công');
    }

    /**
     * Xem chi tiết 1 dịch vụ.
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::withCount('tours')->find($id);

        if (! $service) {
            return $this->error('Không tìm thấy dịch vụ', 404);
        }

        return $this->success($service, 'Lấy chi tiết dịch vụ thành công');
    }

    /**
     * Admin tạo dịch vụ phát sinh mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('services', 'name')],
            'icon'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Tên dịch vụ này đã tồn tại trong hệ thống.',
        ]);

        $service = Service::create($validated);

        return $this->success($service, 'Tạo dịch vụ phát sinh thành công', 201);
    }

    /**
     * Admin cập nhật dịch vụ phát sinh.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::find($id);

        if (! $service) {
            return $this->error('Không tìm thấy dịch vụ', 404);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('services', 'name')->ignore($id)],
            'icon'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Tên dịch vụ này đã tồn tại trong hệ thống.',
        ]);

        $service->update($validated);

        return $this->success($service->fresh(), 'Cập nhật dịch vụ thành công');
    }

    /**
     * Admin xóa dịch vụ phát sinh.
     * Nếu dịch vụ đang được gắn vào tour, sẽ bị gỡ khỏi các tour đó.
     */
    public function destroy(int $id): JsonResponse
    {
        $service = Service::withCount('tours')->find($id);

        if (! $service) {
            return $this->error('Không tìm thấy dịch vụ', 404);
        }

        // Gỡ khỏi tất cả tour trước khi xóa (pivot table sẽ tự cascade)
        $service->tours()->detach();
        $service->delete();

        return $this->success(null, 'Xóa dịch vụ thành công');
    }
}
