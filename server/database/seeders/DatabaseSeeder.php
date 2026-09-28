<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('customer123'),
                'phone' => '0901234567',
                'address' => 'TP. Ho Chi Minh',
                'role' => 'customer',
                'status' => 'active',
            ]
        );

        $this->call([
            // Chạy trước SampleTourSeeder để tour gắn được chính sách hủy mặc định.
            CancellationPolicySeeder::class,
            // Danh mục loại hình: cả tour lẫn hồ sơ năng lực hướng dẫn viên đều tham chiếu tới.
            CategorySeeder::class,
            // Chạy trước mọi seeder dựng chuyến, vì chuyến cần người để phân công.
            GuideSeeder::class,
            SampleTourSeeder::class,
            ReviewSeeder::class,
            DemoBookingSeeder::class,
            // Ba yêu cầu đoàn ở ba chặng của đường ống: chờ báo giá / đã báo giá / đã chốt kèm cọc.
            GroupBookingSeeder::class,
        ]);

        /*
         * `BusinessScenarioSeeder` và `DepositFlowSeeder` KHÔNG còn chạy mặc định.
         *
         * Chúng vẫn dựng được dữ liệu riêng cho kiểm thử và thử tay khi cần:
         *
         *   php artisan db:seed --class=DepositFlowSeeder
         *   php artisan db:seed --class=BusinessScenarioSeeder
         */
    }
}
