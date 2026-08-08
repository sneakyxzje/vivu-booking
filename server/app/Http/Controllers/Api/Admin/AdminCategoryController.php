<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    /**
     * Danh sách danh mục tour kèm số tour đang thuộc danh mục đó.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('tours')
            ->orderBy('name')
            ->paginate(20);

        return $this->success($categories, 'Lấy danh sách danh mục thành công');
    }

    public function show(int $id): JsonResponse
    {
        $category = Category::withCount('tours')->find($id);

        if (! $category) {
            return $this->error('Không tìm thấy danh mục', 404);
        }

        return $this->success($category, 'Lấy chi tiết danh mục thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Vui lòng nhập tên danh mục.',
            'name.unique' => 'Tên danh mục này đã tồn tại trong hệ thống.',
        ]);

        $category = Category::create([
            ...$validated,
            'slug' => $this->buildUniqueSlug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($category->loadCount('tours'), 'Tạo danh mục thành công', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->error('Không tìm thấy danh mục', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Tên danh mục này đã tồn tại trong hệ thống.',
        ]);

        // Đổi tên thì sinh lại slug cho khớp, vẫn đảm bảo duy nhất
        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = $this->buildUniqueSlug($validated['name'], $category->id);
        }

        $category->update($validated);

        return $this->success($category->fresh()->loadCount('tours'), 'Cập nhật danh mục thành công');
    }

    /**
     * Xóa danh mục. Từ chối nếu vẫn còn tour đang thuộc danh mục này
     * để tránh tour mất phân loại ngoài ý muốn.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::withCount('tours')->find($id);

        if (! $category) {
            return $this->error('Không tìm thấy danh mục', 404);
        }

        if ($category->tours_count > 0) {
            return $this->error(
                "Không thể xóa: còn {$category->tours_count} tour đang thuộc danh mục này. "
                    . 'Hãy gỡ danh mục khỏi các tour đó hoặc tạm tắt danh mục.',
                422
            );
        }

        $category->delete();

        return $this->success(null, 'Xóa danh mục thành công');
    }

    private function buildUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'danh-muc';
        $slug = $base;
        $suffix = 2;

        while (
            Category::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
