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
            /*
             * Ba tour SÂN THỬ NGHIỆM, chạy cuối.
             *
             * Gom mọi tình huống nghiệp vụ về đây thay vì rải khắp catalogue: tiền vào tiền ra,
             * ghép và chuyển chuyến, vòng đời chuyến. Chúng mang cờ `is_sandbox` nên mở được quyền
             * tua ngày khởi hành — không có quyền ấy thì muốn xem lệnh hủy chạy phải chờ mười ngày.
             *
             * Mốc thời gian tính lùi từ lúc seed nên trôi dần. Seed lại trước mỗi buổi thử:
             *   php artisan db:seed --class=SandboxTourSeeder
             */
            SandboxTourSeeder::class,
        ]);

        /*
         * `BusinessScenarioSeeder` và `DepositFlowSeeder` KHÔNG còn chạy mặc định.
         *
         * Nội dung của chúng đã chuyển vào ba tour sân thử ở trên, nơi mọi mốc bấm nút là tua tới
         * được thay vì phải chờ đúng ngày. Giữ lại hai file vì các bài kiểm gọi thẳng chúng, và vì
         * chúng vẫn dựng được dữ liệu riêng khi cần:
         *
         *   php artisan db:seed --class=DepositFlowSeeder
         *   php artisan db:seed --class=BusinessScenarioSeeder
         */
    }
}
