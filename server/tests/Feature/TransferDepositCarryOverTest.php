<?php

namespace Tests\Feature;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\ContactPurpose;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CustomerContactLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Services\BookingPaymentService;
use App\Services\BookingTransferService;
use App\Services\ScheduleMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tiền cọc đi theo đơn khi đơn đổi chuyến hoặc đổi tour.
 *
 * Hai câu hỏi mà bộ này trả lời bằng số, không bằng lập luận:
 *
 *   1. Khách cọc 50% của tour 10 triệu rồi chuyển sang tour ĐẮT hơn hoặc RẺ hơn — khoản cọc ấy
 *      được xử lý ra sao, khi 50% của giá mới là một con số khác hẳn?
 *   2. Ghép chuyến vào một chuyến đích mà hạn chốt danh sách của nó đã tới — chuyện gì xảy ra?
 */
class TransferDepositCarryOverTest extends TestCase
{
    use RefreshDatabase;

    private function tour(float $giaNguoiLon): Tour
    {
        return Tour::factory()->create([
            'status' => 'active',
            'adult_price' => $giaNguoiLon,
            'child_price' => $giaNguoiLon * 0.7,
            'infant_price' => 0,
        ]);
    }

    private function chuyen(Tour $tour, int $ngayNua, ?int $hanChotTruoc = null): TourSchedule
    {
        $start = now()->addDays($ngayNua)->setTime(6, 0);

        return TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays($hanChotTruoc ?? 3),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);
    }

    /** Đơn 2 người lớn, đã cọc $tyLe% giá đơn. */
    private function donDaCoc(TourSchedule $chuyen, int $tyLe = 50): Booking
    {
        $tour = $chuyen->tour;
        $tong = 2 * (float) $tour->adult_price;

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Coc',
            'customer_email' => 'coc-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'adult_price' => $tour->adult_price,
            'child_price' => $tour->child_price,
            'infant_price' => $tour->infant_price,
            'total_amount' => $tong,
            'status' => 'confirmed',
            'confirmed_at' => now()->subDay(),
        ]);

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'deposit',
            'amount' => round($tong * $tyLe / 100),
            'paid_at' => now()->subDay(),
        ]);

        $chuyen->increment('booked_people', 2);

        CustomerContactLog::create([
            'booking_id' => $don->id,
            'channel' => ContactChannel::Phone,
            'purpose' => ContactPurpose::Transfer,
            'outcome' => ContactOutcome::Agreed,
            'note' => 'Khách đồng ý đổi chuyến.',
            'contacted_at' => now()->subHours(2),
        ]);

        return $don;
    }

    private function so(): BookingPaymentService
    {
        return app(BookingPaymentService::class);
    }

    /**
     * Căn cứ đã hỏi khách.
     *
     * Không truyền vào là dịch vụ chặn ngay — và chặn đúng: chuyển chuyến đổi ngày đi của người
     * khác, nên phải có bản ghi họ đồng ý. Bài kiểm phải đi qua đúng cửa ấy như đường ghi thật.
     */
    private function canCu(Booking $don): CustomerContactLog
    {
        return CustomerContactLog::query()
            ->where('booking_id', $don->getKey())
            ->latest('contacted_at')
            ->firstOrFail();
    }

    // --- Câu 1: cọc đi theo đơn khi đổi tour --------------------------------------------------

    /**
     * Chuyển sang tour ĐẮT hơn: cọc giữ nguyên bằng SỐ TIỀN, không tính lại theo tỷ lệ.
     *
     * Đơn 10 triệu cọc 5 triệu, chuyển sang tour 20 triệu. Khoản 5 triệu ấy không bị quy đổi thành
     * "25% của giá mới" rồi đòi cọc bù cho đủ 50% — nó vẫn là 5 triệu đã nằm trong sổ, và phần còn
     * lại 15 triệu thành khoản phải trả trước hạn trả nốt của chuyến mới.
     *
     * Tỷ lệ cọc là điều kiện của lúc ĐẶT, không phải một trạng thái phải giữ suốt đời đơn.
     */
    public function test_chuyen_sang_tour_dat_hon_thi_coc_giu_nguyen_so_tien(): void
    {
        $tourA = $this->tour(5_000_000);   // đơn 2 khách = 10 triệu
        $tourB = $this->tour(10_000_000);  // đơn 2 khách = 20 triệu

        $chuyenA = $this->chuyen($tourA, 60);
        $chuyenB = $this->chuyen($tourB, 55);

        $don = $this->donDaCoc($chuyenA);

        $this->assertEquals(10_000_000, round((float) $don->total_amount));
        $this->assertEquals(5_000_000, $this->so()->netPaid($don));

        app(BookingTransferService::class)->transfer(
            booking: $don,
            toSchedule: $chuyenB,
            reason: 'Khách muốn đi tour cao cấp hơn.',
            initiatedBy: 'company',
            canCu: $this->canCu($don),
        );

        $moi = $don->fresh();

        $this->assertEquals(20_000_000, round((float) $moi->total_amount), 'Tổng đơn theo giá tour đích.');
        $this->assertEquals(5_000_000, $this->so()->netPaid($moi), 'Số đã thu KHÔNG đổi.');
        $this->assertEquals(15_000_000, $this->so()->balanceDue($moi), 'Còn thiếu là phần chênh cộng đuôi cũ.');

        // Và hệ thống đòi trọn phần còn thiếu, không đòi "cọc bù cho đủ 50%".
        $this->assertEquals(
            15_000_000,
            $this->so()->nextPaymentAmount($moi),
            'Đã trả một khoản rồi thì lần sau là toàn bộ phần còn thiếu.',
        );
    }

    /** Chuyển sang tour RẺ hơn mà vẫn còn nợ: cọc giữ nguyên, phần còn thiếu co lại. */
    public function test_chuyen_sang_tour_re_hon_thi_con_thieu_co_lai(): void
    {
        $tourA = $this->tour(5_000_000);   // 10 triệu
        $tourB = $this->tour(3_000_000);   // 6 triệu

        $don = $this->donDaCoc($this->chuyen($tourA, 60));

        app(BookingTransferService::class)->transfer(
            booking: $don,
            toSchedule: $this->chuyen($tourB, 55),
            reason: 'Đổi sang tour ngắn hơn.',
            initiatedBy: 'company',
            canCu: $this->canCu($don),
        );

        $moi = $don->fresh();

        $this->assertEquals(6_000_000, round((float) $moi->total_amount));
        $this->assertEquals(5_000_000, $this->so()->netPaid($moi), 'Cọc vẫn là 5 triệu đã đưa.');
        $this->assertEquals(1_000_000, $this->so()->balanceDue($moi));
        $this->assertEquals(0.0, $this->so()->refundOutstanding($moi), 'Chưa thừa tiền thì chưa có gì để hoàn.');
    }

    /**
     * Chuyển sang tour rẻ tới mức cọc VƯỢT giá đơn mới: phần thừa thành nghĩa vụ hoàn.
     *
     * Đây là chỗ dễ mất tiền nhất nếu hệ thống im lặng — khoản thừa chỉ nằm trong sổ, không màn hình
     * nào đọc ra, và chỉ lộ khi chính khách gọi lên đòi.
     */
    public function test_chuyen_sang_tour_qua_re_thi_sinh_nghia_vu_hoan(): void
    {
        $tourA = $this->tour(5_000_000);   // 10 triệu, cọc 5 triệu
        $tourB = $this->tour(2_000_000);   // 4 triệu

        $don = $this->donDaCoc($this->chuyen($tourA, 60));

        app(BookingTransferService::class)->transfer(
            booking: $don,
            toSchedule: $this->chuyen($tourB, 55),
            reason: 'Đổi sang tour rẻ hơn hẳn.',
            initiatedBy: 'company',
            canCu: $this->canCu($don),
        );

        $moi = $don->fresh();

        $this->assertEquals(4_000_000, round((float) $moi->total_amount));
        $this->assertEquals(0.0, $this->so()->balanceDue($moi), 'Không còn nợ gì.');
        $this->assertEquals(
            1_000_000,
            $this->so()->refundOutstanding($moi),
            'Thu 5 triệu cho đơn 4 triệu thì công ty nợ khách 1 triệu.',
        );
    }

    /** Chuyển trong CÙNG tour thì giá giữ nguyên theo đơn giá đã chép lúc đặt, không theo giá hôm nay. */
    public function test_chuyen_cung_tour_khong_ap_gia_moi(): void
    {
        $tour = $this->tour(5_000_000);
        $don = $this->donDaCoc($this->chuyen($tour, 60));

        // Công ty nâng giá tour sau khi khách đã đặt.
        $tour->forceFill(['adult_price' => 8_000_000])->save();

        app(BookingTransferService::class)->transfer(
            booking: $don,
            toSchedule: $this->chuyen($tour->fresh(), 55),
            reason: 'Xe hỏng, dời sang chuyến khác cùng tour.',
            initiatedBy: 'company',
            canCu: $this->canCu($don),
        );

        $this->assertEquals(
            10_000_000,
            round((float) $don->fresh()->total_amount),
            'Giá lúc bán được giữ: dời lịch không phải dịp áp bảng giá mới lên đơn cũ.',
        );
    }

    // --- Câu 2: ghép vào chuyến đã tới hạn chốt -----------------------------------------------

    /**
     * Ghép vào chuyến đích đã TỚI hạn chốt danh sách thì bị chặn, kèm lý do đọc được.
     *
     * Danh sách của chuyến đích đã gửi nhà cung cấp; nhận thêm khách vào đó là vượt số suất đã cam
     * kết. Mốc so là `>=`, nên đúng khoảnh khắc hạn chốt tới là đã chặn — không có khe hở "vừa kịp".
     */
    public function test_ghep_vao_chuyen_da_toi_han_chot_thi_bi_chan(): void
    {
        $tour = $this->tour(5_000_000);

        $nguon = $this->chuyen($tour, 30);
        // Chuyến đích còn ba mươi ngày nữa mới đi, nhưng hạn chốt của nó đã lùi vào hôm qua.
        $dich = $this->chuyen($tour, 30, hanChotTruoc: 31);

        $this->donDaCoc($nguon);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/hạn chốt danh sách/u');

        app(ScheduleMergeService::class)->merge($nguon, $dich, 'Gộp hai chuyến ế.');
    }

    /** Và chặn cả khi chính chuyến NGUỒN đã qua hạn chốt. */
    public function test_ghep_tu_chuyen_da_qua_han_chot_thi_bi_chan(): void
    {
        $tour = $this->tour(5_000_000);

        $nguon = $this->chuyen($tour, 30, hanChotTruoc: 31);
        $dich = $this->chuyen($tour, 29);

        $this->donDaCoc($nguon);

        $this->expectException(BusinessRuleException::class);

        app(ScheduleMergeService::class)->merge($nguon, $dich, 'Gộp hai chuyến ế.');
    }

    /**
     * Ghép vào chuyến đích có hạn trả nốt TRÙNG hôm nay: đơn còn nợ vẫn phải trả nốt.
     *
     * Ghép không xóa khoản nợ và cũng không quy đổi lại tỷ lệ cọc. Đơn giữ nguyên số đã thu, và
     * phần còn thiếu tới hạn ngay — đúng cảnh mà ba tầng bảo vệ của lệnh hủy sinh ra để đỡ.
     */
    public function test_ghep_vao_chuyen_dung_han_tra_not_thi_van_no_phan_con_lai(): void
    {
        $tour = $this->tour(5_000_000);
        $hanTraNot = (int) config('booking.balance_due_days', 10);

        // Chuyến đích đi sớm hơn hạn trả nốt một ngày, nên hạn ấy đã nằm ở quá khứ ngay lúc ghép.
        $nguon = $this->chuyen($tour, $hanTraNot + 1);
        $dich = $this->chuyen($tour, $hanTraNot - 1);

        $don = $this->donDaCoc($nguon);

        app(ScheduleMergeService::class)->merge($nguon, $dich, 'Gộp hai chuyến ế.');

        $moi = $don->fresh();

        $this->assertEquals(5_000_000, $this->so()->netPaid($moi), 'Cọc không bị đụng tới.');
        $this->assertEquals(5_000_000, $this->so()->balanceDue($moi), 'Vẫn nợ đúng phần còn lại.');
        $this->assertTrue(
            now()->gte($moi->balanceDueAt()),
            'Hạn trả nốt của chuyến đích đã tới ngay lúc ghép xong.',
        );
    }
}
