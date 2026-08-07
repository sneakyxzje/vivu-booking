<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $reviewers = collect([
            ['name' => 'Nguyễn Thu Hằng', 'email' => 'thuhang.demo@gmail.com'],
            ['name' => 'Trần Quốc Bảo', 'email' => 'quocbao.demo@gmail.com'],
            ['name' => 'Lê Minh Ngọc', 'email' => 'minhngoc.demo@gmail.com'],
        ])->map(function (array $item) {
            return User::query()->firstOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'password' => Hash::make('customer123'),
                    'role' => 'customer',
                    'status' => 'active',
                ]
            );
        });

        $reviewsByTour = [
            'tour-ha-long-3n2d' => [
                [
                    'rating' => 5,
                    'comment' => 'Chuyến đi rất đáng tiền. Du thuyền sạch đẹp, hải sản tươi, hướng dẫn viên nhiệt tình và chu đáo với người lớn tuổi trong đoàn.',
                ],
                [
                    'rating' => 5,
                    'comment' => 'Lịch trình hợp lý, không bị dồn dập. Điểm đón đúng giờ, xe êm. Sẽ quay lại đặt tour khác của Vivu Booking.',
                ],
                [
                    'rating' => 4,
                    'comment' => 'Tour tổ chức ổn, cảnh vịnh tuyệt vời. Trừ một điểm nhỏ là hôm mình đi trời mưa nhẹ nên lịch chèo kayak bị rút ngắn.',
                ],
            ],
            'tour-da-nang-hoi-an-4n3d' => [
                [
                    'rating' => 5,
                    'comment' => 'Bà Nà Hills và phố cổ Hội An đều rất đẹp. Khách sạn gần biển, ăn sáng ngon. Gia đình mình rất hài lòng.',
                ],
                [
                    'rating' => 4,
                    'comment' => 'Chuyến đi vui, hướng dẫn viên am hiểu địa phương. Giá hợp lý so với chất lượng dịch vụ nhận được.',
                ],
            ],
            'tour-sapa-fansipan-2n1d' => [
                [
                    'rating' => 5,
                    'comment' => 'May mắn săn được mây trên Fansipan! Xe giường nằm sạch, chạy êm, đúng giờ. Rất đáng tiền cho chuyến cuối tuần.',
                ],
                [
                    'rating' => 4,
                    'comment' => 'Bản Cát Cát đẹp, đồ ăn Tây Bắc ngon. Trừ điểm nhỏ vì sáng ngày hai hơi vội.',
                ],
            ],
            'tour-phu-quoc-3n2d' => [
                [
                    'rating' => 5,
                    'comment' => 'Tour 4 đảo tuyệt vời, san hô đẹp, hải sản tươi. Resort sát biển, hoàng hôn Sunset Sanato không thể quên.',
                ],
            ],
        ];

        foreach ($reviewsByTour as $slug => $reviews) {
            $tour = Tour::query()->where('slug', $slug)->first();

            if (! $tour) {
                continue;
            }

            foreach ($reviews as $index => $review) {
                $reviewer = $reviewers[$index % $reviewers->count()];

                Review::query()->firstOrCreate(
                    [
                        'tour_id' => $tour->id,
                        'user_id' => $reviewer->id,
                    ],
                    [
                        'rating' => $review['rating'],
                        'comment' => $review['comment'],
                    ]
                );
            }
        }
    }
}
