<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TourImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SampleTourSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();

        if (! $admin) {
            $admin = User::query()->first();
        }

        if (! $admin) {
            return;
        }

        $guide = User::query()->where('role', 'guide')->first();

        $categories = collect([
            ['name' => 'Biển đảo', 'slug' => 'bien-dao'],
            ['name' => 'Nghỉ dưỡng', 'slug' => 'nghi-duong'],
            ['name' => 'Khám phá', 'slug' => 'kham-pha'],
        ])->map(function (array $item) {
            return Category::query()->firstOrCreate(
                ['slug' => $item['slug']],
                ['name' => $item['name'], 'is_active' => true]
            );
        });

        $services = collect([
            ['name' => 'Xe đưa đón'],
            ['name' => 'Khách sạn 4 sao'],
            ['name' => 'Ăn sáng'],
            ['name' => 'Hướng dẫn viên'],
        ])->map(function (array $item) {
            return Service::query()->firstOrCreate(
                ['name' => $item['name']],
                []
            );
        });

        $tours = [
            [
                'title' => 'Tour Hạ Long 3N2Đ',
                'slug' => 'tour-ha-long-3n2d',
                'description' => 'Khám phá vịnh Hạ Long, nghỉ dưỡng và trải nghiệm hải trình ngắn ngày phù hợp gia đình.',
                'price' => 3190000,
                'discount_price' => null,
                'adult_price' => 3190000,
                'child_price' => 2233000,
                'infant_price' => 0,
                'thumbnail' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
                'number_of_days' => 3,
                'number_of_nights' => 2,
                'start_location' => 'Hà Nội',
                'end_location' => 'Hạ Long',
                'vehicle_info' => 'Xe giường nằm 34 chỗ đời mới, có wifi và nước uống',
                'pickup_location' => 'Nhà hát Lớn Hà Nội - Số 1 Tràng Tiền, Hoàn Kiếm (có mặt trước giờ khởi hành 30 phút)',
                'is_featured' => true,
                'status' => 'active',
                'categories' => ['bien-dao', 'nghi-duong'],
                'services' => ['Xe đưa đón', 'Khách sạn 4 sao', 'Ăn sáng'],
                'images' => [
                    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
                    'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66',
                ],
                'itineraries' => [
                    ['day_number' => 1, 'title' => 'Khởi hành', 'content' => 'Di chuyển từ Hà Nội, nhận phòng và tự do tham quan.'],
                    ['day_number' => 2, 'title' => 'Du thuyền vịnh', 'content' => 'Tham quan các điểm nổi bật trên vịnh Hạ Long.'],
                    ['day_number' => 3, 'title' => 'Trả phòng', 'content' => 'Ăn sáng, trả phòng và quay về Hà Nội.'],
                ],
                'schedules' => [
                    ['start_date' => now()->addDays(7)->setTime(5, 30)->format('Y-m-d H:i:s'), 'max_people' => 20],
                    ['start_date' => now()->addDays(14)->setTime(5, 30)->format('Y-m-d H:i:s'), 'max_people' => 20],
                ],
            ],
            [
                'title' => 'Tour Đà Nẵng - Hội An 4N3Đ',
                'slug' => 'tour-da-nang-hoi-an-4n3d',
                'description' => 'Combo biển, phố cổ và ẩm thực miền Trung với lịch trình cân bằng giữa nghỉ dưỡng và khám phá.',
                'price' => 3890000,
                'discount_price' => null,
                'adult_price' => 3890000,
                'child_price' => 2723000,
                'infant_price' => 0,
                'thumbnail' => 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86',
                'number_of_days' => 4,
                'number_of_nights' => 3,
                'start_location' => 'TP. Hồ Chí Minh',
                'end_location' => 'Đà Nẵng',
                'vehicle_info' => 'Vé máy bay khứ hồi + xe du lịch 29 chỗ tại điểm đến',
                'pickup_location' => 'Ga quốc nội, sân bay Tân Sơn Nhất - Cột số 9 (tập trung trước giờ bay 2 tiếng)',
                'is_featured' => false,
                'status' => 'active',
                'categories' => ['nghi-duong', 'kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng'],
                'images' => [
                    'https://images.unsplash.com/photo-1512813195386-6cf811ad3542',
                    'https://images.unsplash.com/photo-1543877087-ebf71fde2be1',
                ],
                'itineraries' => [
                    ['day_number' => 1, 'title' => 'Đến Đà Nẵng', 'content' => 'Nhận phòng, tham quan tự do buổi chiều.'],
                    ['day_number' => 2, 'title' => 'Bà Nà Hills', 'content' => 'Tham quan khu du lịch Bà Nà Hills.'],
                    ['day_number' => 3, 'title' => 'Hội An', 'content' => 'Khám phá phố cổ và trải nghiệm ẩm thực.'],
                    ['day_number' => 4, 'title' => 'Kết thúc', 'content' => 'Ăn sáng, trả phòng và di chuyển về.'],
                ],
                'schedules' => [
                    ['start_date' => now()->addDays(10)->setTime(8, 0)->format('Y-m-d H:i:s'), 'max_people' => 25],
                ],
            ],
            [
                'title' => 'Tour Sapa - Fansipan 2N1Đ',
                'slug' => 'tour-sapa-fansipan-2n1d',
                'description' => 'Săn mây Fansipan, dạo bản Cát Cát và thưởng thức ẩm thực Tây Bắc trong hai ngày cuối tuần.',
                'price' => 1890000,
                'discount_price' => null,
                'adult_price' => 1890000,
                'child_price' => 1323000,
                'infant_price' => 0,
                'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592',
                'number_of_days' => 2,
                'number_of_nights' => 1,
                'start_location' => 'Hà Nội',
                'end_location' => 'Sapa',
                'vehicle_info' => 'Xe giường nằm cao cấp 22 chỗ, khởi hành đêm',
                'pickup_location' => 'Bến xe Mỹ Đình - Cổng chính, Nam Từ Liêm (có mặt trước giờ khởi hành 30 phút)',
                'is_featured' => true,
                'status' => 'active',
                'categories' => ['kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng'],
                'images' => [
                    'https://images.unsplash.com/photo-1528127269322-539801943592',
                ],
                'itineraries' => [
                    ['day_number' => 1, 'title' => 'Hà Nội - Sapa - Bản Cát Cát', 'start_point' => 'Hà Nội', 'end_point' => 'Sapa', 'route_points' => 'Lào Cai', 'content' => 'Di chuyển lên Sapa, nhận phòng, tham quan bản Cát Cát.'],
                    ['day_number' => 2, 'title' => 'Chinh phục Fansipan', 'start_point' => 'Sapa', 'end_point' => 'Hà Nội', 'content' => 'Đi cáp treo Fansipan, ăn trưa và trở về Hà Nội.'],
                ],
                'schedules' => [
                    ['start_date' => now()->addDays(9)->setTime(21, 0)->format('Y-m-d H:i:s'), 'max_people' => 22],
                ],
            ],
            [
                'title' => 'Tour Phú Quốc 3N2Đ',
                'slug' => 'tour-phu-quoc-3n2d',
                'description' => 'Nghỉ dưỡng đảo ngọc: lặn ngắm san hô 4 đảo, chợ đêm Dương Đông và hoàng hôn Sunset Sanato.',
                'price' => 4590000,
                'discount_price' => null,
                'adult_price' => 4590000,
                'child_price' => 3213000,
                'infant_price' => 0,
                'thumbnail' => 'https://images.unsplash.com/photo-1540541338287-41700207dee6',
                'number_of_days' => 3,
                'number_of_nights' => 2,
                'start_location' => 'TP. Hồ Chí Minh',
                'end_location' => 'Phú Quốc',
                'vehicle_info' => 'Vé máy bay khứ hồi + xe đưa đón sân bay tại Phú Quốc',
                'pickup_location' => 'Ga quốc nội, sân bay Tân Sơn Nhất - Cột số 11 (tập trung trước giờ bay 2 tiếng)',
                'is_featured' => false,
                'status' => 'active',
                'categories' => ['bien-dao', 'nghi-duong'],
                'services' => ['Xe đưa đón', 'Khách sạn 4 sao', 'Ăn sáng', 'Hướng dẫn viên'],
                'images' => [
                    'https://images.unsplash.com/photo-1540541338287-41700207dee6',
                ],
                'itineraries' => [
                    ['day_number' => 1, 'title' => 'Bay đến Phú Quốc', 'start_point' => 'TP. Hồ Chí Minh', 'end_point' => 'Phú Quốc', 'content' => 'Bay đến đảo, nhận phòng resort, tự do tắm biển.'],
                    ['day_number' => 2, 'title' => 'Tour 4 đảo', 'content' => 'Lặn ngắm san hô, câu cá, ăn trưa hải sản trên tàu.'],
                    ['day_number' => 3, 'title' => 'Chợ Dương Đông - trở về', 'content' => 'Mua đặc sản, trả phòng và bay về TP.HCM.'],
                ],
                'schedules' => [
                    ['start_date' => now()->addDays(12)->setTime(6, 30)->format('Y-m-d H:i:s'), 'max_people' => 24],
                ],
            ],
        ];

        DB::transaction(function () use ($admin, $guide, $categories, $services, $tours) {
            foreach ($tours as $tourData) {
                $tour = Tour::query()->updateOrCreate(
                    ['slug' => $tourData['slug']],
                    [
                        'admin_id' => $admin->id,
                        'title' => $tourData['title'],
                        'description' => $tourData['description'],
                        'price' => $tourData['adult_price'],
                        'discount_price' => null,
                        'adult_price' => $tourData['adult_price'],
                        'child_price' => $tourData['child_price'],
                        'infant_price' => $tourData['infant_price'],
                        'thumbnail' => $tourData['thumbnail'],
                        'number_of_days' => $tourData['number_of_days'],
                        'number_of_nights' => $tourData['number_of_nights'],
                        'start_location' => $tourData['start_location'],
                        'end_location' => $tourData['end_location'],
                        'vehicle_info' => $tourData['vehicle_info'],
                        'pickup_location' => $tourData['pickup_location'],
                        'is_featured' => $tourData['is_featured'],
                        'status' => $tourData['status'],
                    ]
                );

                $tour->categories()->sync(
                    $categories
                        ->whereIn('slug', $tourData['categories'])
                        ->pluck('id')
                        ->all()
                );

                $tour->services()->sync(
                    $services
                        ->whereIn('name', $tourData['services'])
                        ->pluck('id')
                        ->all()
                );

                $tour->itineraries()->delete();
                foreach ($tourData['itineraries'] as $item) {
                    $tour->itineraries()->create($item);
                }

                $tour->schedules()->delete();
                foreach ($tourData['schedules'] as $item) {
                    $tour->schedules()->create([
                        'start_date' => $item['start_date'],
                        'guide_id' => $guide?->id,
                        'max_people' => $item['max_people'],
                        'booked_people' => 0,
                        'status' => 'active',
                    ]);
                }

                $tour->images()->delete();
                foreach ($tourData['images'] as $imageUrl) {
                    TourImage::query()->create([
                        'tour_id' => $tour->id,
                        'image_path' => $imageUrl,
                    ]);
                }
            }
        });
    }
}
