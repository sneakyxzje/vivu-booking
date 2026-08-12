<?php

namespace Database\Seeders;

use App\Models\CancellationPolicy;
use App\Services\CancellationPolicyService;
use Illuminate\Database\Seeder;

/**
 * B02 - Chính sách hủy mặc định.
 *
 * Lấy đúng bảng phí trong CancellationPolicyService::DEFAULT_RULES để mã và dữ liệu không
 * nói hai chuyện khác nhau. Lớp dịch vụ chỉ dùng hằng số đó khi chưa seed chính sách nào.
 */
class CancellationPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policy = CancellationPolicy::query()->updateOrCreate(
            ['name' => 'Chính sách hủy tiêu chuẩn'],
            [
                'description' => 'Áp dụng cho tour nội địa. Phí hủy tăng dần khi càng sát ngày '
                    . 'khởi hành, vì chi phí đã cam kết với nhà cung cấp càng khó hủy: khách sạn '
                    . 'chốt phòng khoảng 7 ngày trước, nhà xe 3 ngày, suất ăn 1 đến 2 ngày.',
                'is_default' => true,
            ],
        );

        $ghiChu = [
            360 => 'Hủy sớm, chi phí gần như chưa phát sinh.',
            192 => 'Khách sạn chưa chốt phòng.',
            96 => 'Đã qua mốc chốt phòng của phần lớn khách sạn.',
            48 => 'Đã chốt xe và đang chốt suất ăn.',
            0 => 'Toàn bộ dịch vụ đã cam kết, không hủy được với nhà cung cấp.',
        ];

        $policy->rules()->delete();

        foreach (CancellationPolicyService::DEFAULT_RULES as $rule) {
            $policy->rules()->create([
                'min_hours_before' => $rule['min_hours_before'],
                'max_hours_before' => $rule['max_hours_before'],
                'refund_percent' => $rule['refund_percent'],
                'note' => $ghiChu[$rule['min_hours_before']] ?? null,
            ]);
        }
    }
}
