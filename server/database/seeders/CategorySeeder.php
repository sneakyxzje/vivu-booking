<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Danh mục loại hình tour.
 *
 * Tách ra khỏi `SampleTourSeeder` vì nay có hai bên cùng cần: tour gắn loại hình, và hồ sơ năng
 * lực hướng dẫn viên khai loại hình mình chuyên. Hai bên phải nói đúng cùng một bộ danh mục thì
 * phép so khớp mới có nghĩa - chép danh sách sang chỗ thứ hai là sớm muộn cũng lệch.
 *
 * Chạy đầu tiên, và idempotent, nên seeder nào cần cũng gọi lại được mà không sinh trùng.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $danhMuc = [
            ['name' => 'Biển đảo', 'slug' => 'bien-dao'],
            ['name' => 'Nghỉ dưỡng', 'slug' => 'nghi-duong'],
            ['name' => 'Khám phá', 'slug' => 'kham-pha'],
        ];

        foreach ($danhMuc as $item) {
            Category::query()->firstOrCreate(
                ['slug' => $item['slug']],
                ['name' => $item['name'], 'is_active' => true],
            );
        }
    }
}
