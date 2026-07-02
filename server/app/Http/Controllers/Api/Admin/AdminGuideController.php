<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminGuideController extends Controller
{
    public function index(): JsonResponse
    {
        $guides = User::where('role', 'guide')->latest()->paginate(10);
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
        $guide = User::where('role', 'guide')->find($id);

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
