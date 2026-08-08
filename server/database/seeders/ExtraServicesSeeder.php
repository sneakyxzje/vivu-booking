<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ExtraServicesSeeder extends Seeder
{
    /**
     * Seed dữ liệu dịch vụ phát sinh mẫu.
     */
    public function run(): void
    {
        $services = [
            [
                'name'        => 'Khách sạn 3 sao',
                'description' => 'Phòng nghỉ khách sạn 3 sao tiêu chuẩn, điều hòa, wifi, buffet sáng.',
                'price'       => 500000,
                'is_active'   => true,
            ],
            [
                'name'        => 'Khách sạn 4 sao',
                'description' => 'Phòng nghỉ khách sạn 4 sao cao cấp, hồ bơi, spa, breakfast.',
                'price'       => 900000,
                'is_active'   => true,
            ],
            [
                'name'        => 'Ăn uống (3 bữa/ngày)',
                'description' => 'Bữa ăn đặc sản địa phương theo tiêu chuẩn tour, đã bao gồm trong giá.',
                'price'       => null, // Bao gồm trong giá tour
                'is_active'   => true,
            ],
            [
                'name'        => 'Bảo hiểm du lịch',
                'description' => 'Bảo hiểm tai nạn và y tế trong suốt hành trình tour.',
                'price'       => 50000,
                'is_active'   => true,
            ],
            [
                'name'        => 'Hướng dẫn viên chuyên nghiệp',
                'description' => 'HDV có kinh nghiệm, am hiểu văn hóa và địa danh du lịch.',
                'price'       => null, // Bao gồm trong giá tour
                'is_active'   => true,
            ],
            [
                'name'        => 'Vé tham quan danh lam',
                'description' => 'Phí vào cổng các điểm tham quan theo lịch trình tour.',
                'price'       => null, // Bao gồm trong giá tour
                'is_active'   => true,
            ],
            [
                'name'        => 'Nước uống & snack trên xe',
                'description' => 'Nước uống đóng chai và bánh snack phục vụ trên xe trong hành trình.',
                'price'       => null,
                'is_active'   => true,
            ],
            [
                'name'        => 'Thuê xe đạp / xe điện',
                'description' => 'Dịch vụ thuê xe đạp hoặc xe điện khám phá tại điểm đến.',
                'price'       => 80000,
                'is_active'   => true,
            ],
        ];

        foreach ($services as $service) {
            // Chỉ tạo mới nếu chưa tồn tại (tránh trùng lặp khi chạy seeder nhiều lần)
            Service::firstOrCreate(
                ['name' => $service['name']],
                $service
            );
        }

        $this->command->info('Đã tạo ' . count($services) . ' dịch vụ phát sinh mẫu.');
    }
}
