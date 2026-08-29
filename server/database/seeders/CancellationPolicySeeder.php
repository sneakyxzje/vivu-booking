<?php

namespace Database\Seeders;

use App\Models\CancellationPolicy;
use App\Services\CancellationPolicyService;
use App\Support\GioVietNam;
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
                // Lùi một ngày để bản seed chắc chắn đã có hiệu lực, kể cả khi seeder chạy xong là
                // có người đặt đơn ngay trong cùng giây đó.
                'effective_from' => GioVietNam::bayGio()->subDay(),
            ],
        );

        $ghiChu = [
            15 => 'Hủy sớm, chi phí gần như chưa phát sinh.',
            8 => 'Khách sạn chưa chốt phòng.',
            4 => 'Đã qua mốc chốt phòng của phần lớn khách sạn.',
            2 => 'Đã chốt xe và đang chốt suất ăn.',
            0 => 'Toàn bộ dịch vụ đã cam kết, không hủy được với nhà cung cấp.',
        ];

        $policy->rules()->delete();

        foreach (CancellationPolicyService::DEFAULT_RULES as $rule) {
            $policy->rules()->create([
                'min_days_before' => $rule['min_days_before'],
                'max_days_before' => $rule['max_days_before'],
                'refund_percent' => $rule['refund_percent'],
                'note' => $ghiChu[$rule['min_days_before']] ?? null,
            ]);
        }
    }
}
