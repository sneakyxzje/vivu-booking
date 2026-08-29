<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Tour $tour;

    private function dungDuLieu(bool $daXacNhanBooking): void
    {
        $admin = User::create([
            'name' => 'Admin Review',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Khach Review',
            'email' => 'khach-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Review Test',
            'slug' => 'tour-review-test-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 1,
            'number_of_nights' => 0,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        if ($daXacNhanBooking) {
            $schedule = TourSchedule::create([
                'tour_id' => $this->tour->id,
                'start_date' => now()->addDays(3),
                'max_people' => 10,
                'booked_people' => 1,
                'status' => 'open',
            ]);

            Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $this->tour->id,
                'customer_id' => $this->customer->id,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $this->customer->name,
                'customer_email' => $this->customer->email,
                'departure_date' => $schedule->start_date,
                'guests' => 1,
                'adult_count' => 1,
                'child_count' => 0,
                'infant_count' => 0,
                'total_amount' => 1000000,
                'status' => 'confirmed',
            ]);
        }
    }

    public function test_khach_chua_dat_tour_khong_duoc_danh_gia(): void
    {
        $this->dungDuLieu(daXacNhanBooking: false);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Tour tuyet voi, rat dang tien!',
        ])->assertStatus(403);

        $this->assertSame(0, Review::query()->count());
    }

    /**
     * Mới đặt chỗ thì CHƯA đánh giá được — luật này đã siết lại.
     *
     * Trước đây `confirmed` là đủ, nghĩa là người đặt chỗ hôm nay chấm được năm sao cho một chuyến
     * khởi hành tháng sau. Điểm ấy không nói gì về chuyến đi mà vẫn đứng chung bảng với điểm của
     * người đã đi thật, và đó cũng là đường dễ nhất để tự nâng điểm tour của chính mình.
     */
    public function test_khach_moi_dat_cho_chua_di_thi_chua_danh_gia_duoc(): void
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Chua di nhung cham nam sao truoc.',
        ])->assertStatus(403);

        $this->assertSame(0, Review::query()->count());
    }

    /**
     * Từ D03, đơn của chuyến đã đi xong tự chuyển sang 'completed'. Đó là mốc đáng tin cho câu
     * hỏi "người này đã đi chuyến đó chưa".
     */
    public function test_khach_da_di_xong_chuyen_thi_danh_gia_duoc_va_gui_lai_thi_cap_nhat(): void
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Booking::query()->update(['status' => BookingStatus::Completed->value]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 4,
            'comment' => 'Chuyen di on, huong dan vien nhiet tinh.',
        ])->assertStatus(201);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Nghi lai thay rat tuyet, se quay lai.',
        ])->assertStatus(201);

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(5, (int) Review::query()->first()->rating);
    }

    /** Khách không có mặt thì không đi chuyến này, không có căn cứ để đánh giá. */
    public function test_khach_khong_co_mat_thi_khong_danh_gia_duoc(): void
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Booking::query()->update(['status' => BookingStatus::NoShow->value]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 1,
            'comment' => 'Khong di duoc nhung van cham diem.',
        ])->assertStatus(403);

        $this->assertSame(0, Review::query()->count());
    }

    // --- Kiểm duyệt và trả lời -----------------------------------------------------------------

    private function taoDanhGiaDaGui(): Review
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Booking::query()->update(['status' => BookingStatus::Completed->value]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Chuyen di dang nho, xe moi va sach.',
        ])->assertStatus(201);

        return Review::query()->firstOrFail();
    }

    private function admin(): User
    {
        return User::query()->where('role', 'admin')->firstOrFail();
    }

    public function test_danh_gia_moi_o_trang_thai_cho_duyet(): void
    {
        $review = $this->taoDanhGiaDaGui();

        $this->assertSame(ReviewStatus::Pending, $review->status);
    }

    public function test_nguoi_ngoai_khong_thay_danh_gia_chua_duyet(): void
    {
        $review = $this->taoDanhGiaDaGui();

        // Đăng xuất: người đọc bất kỳ chỉ được thấy bài đã duyệt.
        $this->app['auth']->forgetGuards();

        $this->assertCount(0, $this->getJson('/api/reviews/' . $review->tour_id)->assertOk()->json('data'));
    }

    public function test_nguoi_viet_van_thay_bai_cua_chinh_minh_kem_trang_thai(): void
    {
        $review = $this->taoDanhGiaDaGui();

        // Ẩn cả với người viết thì họ tưởng bấm gửi không ăn và gửi lại.
        $data = $this->getJson('/api/reviews/' . $review->tour_id)->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_mine']);
        $this->assertSame('pending', $data[0]['status']);
    }

    public function test_danh_gia_chua_duyet_khong_tinh_vao_diem_cua_tour(): void
    {
        $review = $this->taoDanhGiaDaGui();
        $this->app['auth']->forgetGuards();

        $truocDuyet = $this->getJson('/api/tours/' . $review->tour_id)->assertOk();
        $this->assertSame(0, $truocDuyet->json('data.review_count'));

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/approve')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $sauDuyet = $this->getJson('/api/tours/' . $review->tour_id)->assertOk();

        $this->assertSame(1, $sauDuyet->json('data.review_count'));
        $this->assertSame(5.0, (float) $sauDuyet->json('data.rating'));
    }

    public function test_bai_bi_tu_choi_khong_hien_va_khong_keo_diem_xuong(): void
    {
        $review = $this->taoDanhGiaDaGui();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/reject', [
                'reason' => 'Nội dung có số điện thoại quảng cáo.',
            ])
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->assertCount(0, $this->getJson('/api/reviews/' . $review->tour_id)->json('data'));
        $this->assertSame(0, $this->getJson('/api/tours/' . $review->tour_id)->json('data.review_count'));
    }

    public function test_tu_choi_phai_kem_ly_do(): void
    {
        $review = $this->taoDanhGiaDaGui();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/reject', ['reason' => ''])
            ->assertStatus(422);
    }

    public function test_khong_tra_loi_duoc_bai_chua_duyet(): void
    {
        $review = $this->taoDanhGiaDaGui();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/reply', [
                'reply' => 'Cảm ơn bạn đã đi tour cùng chúng tôi.',
            ])
            ->assertStatus(422);
    }

    public function test_tra_loi_bai_da_duyet_va_cau_tra_loi_hien_cong_khai(): void
    {
        $review = $this->taoDanhGiaDaGui();
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/approve')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/reply', [
                'reply' => 'Cảm ơn bạn, hẹn gặp lại ở chuyến sau.',
            ])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $data = $this->getJson('/api/reviews/' . $review->tour_id)->assertOk()->json('data');

        $this->assertSame('Cảm ơn bạn, hẹn gặp lại ở chuyến sau.', $data[0]['reply']);
        $this->assertSame($admin->name, $data[0]['replied_by']);
    }

    public function test_sua_bai_da_duyet_thi_quay_lai_cho_duyet(): void
    {
        $review = $this->taoDanhGiaDaGui();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/reviews/' . $review->id . '/approve')
            ->assertOk();

        // Nội dung đã đổi thì lần duyệt trước không còn nói về nội dung đang có.
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/reviews', [
            'tour_id' => $review->tour_id,
            'rating' => 1,
            'comment' => 'Doi y roi, chuyen di rat te.',
        ])->assertStatus(201);

        $this->assertSame(ReviewStatus::Pending, $review->fresh()->status);
    }
}

