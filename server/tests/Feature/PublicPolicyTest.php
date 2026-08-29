<?php

namespace Tests\Feature;

use App\Models\CancellationPolicy;
use App\Services\BookingTransferService;
use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang chính sách phía khách.
 *
 * **Điều bộ test này giữ: trang chính sách và phép tính tiền đọc chung một nguồn.**
 *
 * Nếu ai đó về sau chép bảng phí thành chữ trong mã giao diện cho nhanh, bài
 * `test_bang_phi_doi_theo_ban_dang_ap_dung` sẽ đỏ - vì nó sửa bảng phí trong cơ sở dữ liệu rồi đòi
 * điểm cuối trả về đúng bảng mới. Đó là lúc trang chính sách bắt đầu hứa một đằng còn lúc hủy đơn
 * trừ tiền một nẻo.
 */
class PublicPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Không đăng nhập vẫn đọc được.
     *
     * Khách phải đọc được điều khoản hoàn tiền TRƯỚC khi đặt, tức trước khi có tài khoản. Bắt đăng
     * nhập mới xem được là giấu điều khoản cho tới khi người ta đã cam kết.
     */
    public function test_khach_chua_dang_nhap_van_doc_duoc_chinh_sach(): void
    {
        $this->getJson('/api/policies')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cancellation' => ['name', 'effective_from', 'rules'],
                    'transfer' => ['notice_days', 'free_transfers', 'fee'],
                    'booking' => ['payment_ttl_minutes', 'deadline_days'],
                ],
            ]);
    }

    /** Bảng phí trả về là bảng đang áp dụng, không phải bản chép cứng ở đâu đó. */
    public function test_bang_phi_doi_theo_ban_dang_ap_dung(): void
    {
        $policy = CancellationPolicy::create([
            'name' => 'Bảng phí riêng của kỳ nghỉ lễ',
            'effective_from' => now()->subDay(),
        ]);

        $policy->rules()->create([
            'min_days_before' => 20, 'max_days_before' => null, 'refund_percent' => 65,
            'note' => 'Ghi chú nhìn thấy được.',
        ]);
        $policy->rules()->create([
            'min_days_before' => 0, 'max_days_before' => 20, 'refund_percent' => 0,
        ]);

        $data = $this->getJson('/api/policies')->assertOk()->json('data.cancellation');

        $this->assertSame('Bảng phí riêng của kỳ nghỉ lễ', $data['name']);
        $this->assertCount(2, $data['rules']);
        $this->assertSame('Từ 20 ngày trở lên', $data['rules'][0]['window']);
        $this->assertSame(65, $data['rules'][0]['refund_percent']);
        $this->assertSame('Ghi chú nhìn thấy được.', $data['rules'][0]['note']);
    }

    /**
     * Bản hẹn cho tương lai chưa được hiện ra.
     *
     * Khách đặt hôm nay chịu bảng phí hôm nay. Hiện bảng của tháng sau là báo cho họ một điều khoản
     * không áp cho đơn họ sắp đặt.
     */
    public function test_ban_hen_cho_tuong_lai_chua_hien_ra(): void
    {
        $dangChay = CancellationPolicy::create([
            'name' => 'Bảng đang chạy',
            'effective_from' => now()->subDay(),
        ]);
        $dangChay->rules()->create([
            'min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 80,
        ]);

        $hen = CancellationPolicy::create([
            'name' => 'Bảng siết lại từ tháng sau',
            'effective_from' => now()->addDays(30),
        ]);
        $hen->rules()->create([
            'min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 10,
        ]);

        $this->getJson('/api/policies')
            ->assertOk()
            ->assertJsonPath('data.cancellation.name', 'Bảng đang chạy');
    }

    /**
     * Chưa có bản nào trong cơ sở dữ liệu thì trả bảng viết trong mã, không trả rỗng.
     *
     * Đó đúng là thứ hệ thống đang tính theo. Trang chính sách trống trơn sẽ khiến khách tưởng
     * công ty không có chính sách hoàn tiền nào.
     */
    public function test_chua_co_ban_nao_thi_tra_bang_mac_dinh(): void
    {
        $this->assertSame(0, CancellationPolicy::query()->count());

        $this->getJson('/api/policies')
            ->assertOk()
            ->assertJsonCount(
                count(CancellationPolicyService::DEFAULT_RULES),
                'data.cancellation.rules',
            );
    }

    /**
     * Mấy con số ở phần hỏi đáp lấy từ hằng số thật.
     *
     * Trang hỏi đáp nói "báo trước 7 ngày, phí đổi lịch 200.000đ". Gõ cứng mấy con số ấy vào giao
     * diện là dựng bản thứ hai của một luật; đổi hằng số ở máy chủ thì trang vẫn nói số cũ.
     */
    public function test_con_so_o_phan_hoi_dap_lay_tu_hang_so_that(): void
    {
        $data = $this->getJson('/api/policies')->assertOk()->json('data');

        $this->assertSame(BookingTransferService::CUSTOMER_NOTICE_DAYS, $data['transfer']['notice_days']);
        $this->assertSame(BookingTransferService::FREE_TRANSFERS, $data['transfer']['free_transfers']);
        // JSON không phân biệt 200000 với 200000.0, nên so theo giá trị chứ không theo kiểu.
        $this->assertSame((float) config('booking.transfer_fee'), (float) $data['transfer']['fee']);
        $this->assertSame((int) config('booking.payment_ttl_minutes'), $data['booking']['payment_ttl_minutes']);
    }

    /**
     * Mốc hiệu lực trả về dạng mộc, không kèm hậu tố múi giờ.
     *
     * `serializeDate` của model chỉ áp khi chính model được serialize; một Carbon nằm trong mảng
     * thường thì Laravel gắn hậu tố Z, và trình duyệt ở GMT+7 cộng thêm 7 tiếng - mốc 0h mùng 1
     * hiện ra thành 7h mùng 1.
     */
    public function test_moc_hieu_luc_khong_kem_hau_to_mui_gio(): void
    {
        // Mốc phải nằm trong quá khứ, nếu không bản này chưa có hiệu lực và không được trả về.
        $moc = now()->subMonth()->startOfDay();

        $policy = CancellationPolicy::create([
            'name' => 'Bảng phí',
            'effective_from' => $moc,
        ]);
        $policy->rules()->create([
            'min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 50,
        ]);

        $this->getJson('/api/policies')
            ->assertOk()
            ->assertJsonPath('data.cancellation.effective_from', $moc->format('Y-m-d H:i:s'));
    }
}
