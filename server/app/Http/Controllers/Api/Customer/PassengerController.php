<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PassengerPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * G03 - Khách tự khai và sửa danh sách hành khách, trong khoảng thời gian còn được phép.
 *
 * Trước hạn chốt danh sách thì khách sửa thoải mái. Sau hạn chốt, danh sách đã gửi cho khách sạn
 * và nhà xe nên chỉ điều hành sửa được. Sau khi đoàn lên đường thì không ai sửa.
 *
 * ## Hai lối vào, một bộ luật
 *
 * Cùng một việc nhưng khách tới từ hai đường:
 *
 *   - **Đã đăng nhập**: vào từ `/my-bookings`, tìm đơn theo id và chủ đơn.
 *   - **Khách vãng lai**: vào từ liên kết trong thư xác nhận, tìm đơn theo mã tra cứu.
 *
 * Chỉ khác nhau ở cách tìm ra đơn. Toàn bộ phần sau đó - kiểm quyền sửa, kiểm danh sách, ghi -
 * đi qua đúng một chỗ. Lỗi lặp lại nhiều lần trong dự án luôn cùng khuôn "luật có ở đường này mà
 * thiếu ở đường kia", nên hai lối vào ở đây chỉ là hai hàm mỏng gọi chung một thân.
 *
 * ## Vì sao khách vãng lai phải khai được
 *
 * Đặt tour không cần tài khoản. Trước đây sửa hành khách lại đòi đăng nhập, nên khách vãng lai
 * đặt xong là vĩnh viễn không sửa được danh sách - gõ nhầm một số căn cước thì chịu. Đó là lý do
 * đường theo mã tra cứu tồn tại, không phải để tiện.
 *
 * Xem docs/nghiep-vu/02-luong-dat-tour.md mục 3.1.
 */
class PassengerController extends Controller
{
    public function __construct(
        private PassengerPolicyService $passengerPolicy,
    ) {
    }

    // --- Lối vào của khách đã đăng nhập ---------------------------------------------------

    public function index(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::query()
            ->with('schedule')
            ->whereKey($bookingId)
            ->where('customer_id', $request->user()->id)
            ->first();

        return $booking
            ? $this->success($this->danhSach($booking), 'Lấy danh sách hành khách thành công')
            : $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
    }

    public function update(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate(PassengerPolicyService::validationRules());

        $booking = Booking::query()
            ->with('schedule')
            ->whereKey($bookingId)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        return $this->success($this->ghiDanhSach($booking, $validated['passengers']), 'Đã cập nhật danh sách hành khách.');
    }

    // --- Lối vào bằng mã tra cứu, không cần đăng nhập -------------------------------------

    public function publicIndex(string $publicToken): JsonResponse
    {
        $booking = $this->timTheoMa($publicToken);

        return $booking
            ? $this->success($this->danhSach($booking), 'Lấy danh sách hành khách thành công')
            : $this->error('Không tìm thấy đơn với mã tra cứu này.', 404);
    }

    public function publicUpdate(Request $request, string $publicToken): JsonResponse
    {
        $validated = $request->validate(PassengerPolicyService::validationRules());

        $booking = $this->timTheoMa($publicToken);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn với mã tra cứu này.', 404);
        }

        return $this->success($this->ghiDanhSach($booking, $validated['passengers']), 'Đã lưu danh sách hành khách.');
    }

    // --- Phần dùng chung -------------------------------------------------------------------

    /**
     * Trạng thái hiện tại của danh sách, kèm mọi thứ giao diện cần để hiện đúng.
     *
     * @return array<string, mixed>
     */
    private function danhSach(Booking $booking): array
    {
        $quyen = $this->passengerPolicy->editability($booking);
        $hanChot = $booking->schedule?->booking_deadline
            ?? $booking->schedule?->defaultBookingDeadline();

        return [
            'booking' => [
                'public_token' => $booking->public_token,
                'tour_title' => $booking->tour?->title,
                'departure_date' => $booking->departure_date,
                'contact_name' => $booking->customer_name,
                'contact_phone' => $booking->customer_phone,
                'status' => $booking->status,
            ],
            'passengers' => $booking->passengers()->get(),
            'guests' => (int) $booking->guests,
            'adult_count' => (int) $booking->adult_count,
            'child_count' => (int) $booking->child_count,
            'infant_count' => (int) $booking->infant_count,
            'can_edit' => $quyen['customer'],
            'locked_reason' => $quyen['customer'] ? null : $quyen['reason'],
            // Hạn chốt là mốc khách mất quyền tự sửa, nên phải hiện chứ không để họ đoán.
            'deadline' => $hanChot?->format('Y-m-d H:i:s'),
            'warnings' => $this->passengerPolicy->manifestWarnings($booking),
        ];
    }

    /**
     * Ghi đè cả danh sách.
     *
     * Nhận cả danh sách chứ không sửa từng người: các luật kiểm tra là luật của cả đơn, ví dụ
     * trùng số giấy tờ, nên phải nhìn thấy toàn bộ mới kiểm được.
     *
     * @param  array<int, array<string, mixed>>  $passengers
     * @return array<string, mixed>
     */
    private function ghiDanhSach(Booking $booking, array $passengers): array
    {
        $this->passengerPolicy->assertCustomerCanEdit($booking);
        $this->passengerPolicy->validateList($booking, $passengers);

        DB::transaction(function () use ($booking, $passengers) {
            $this->passengerPolicy->replaceList($booking, $passengers);
        });

        return $this->danhSach($booking->fresh(['schedule', 'tour']));
    }

    /**
     * Đơn tra theo mã tra cứu.
     *
     * Mã là chuỗi ngẫu nhiên, ai giữ mã thì xem và sửa được - cùng hợp đồng với màn tra cứu đơn
     * của khách vãng lai. Đơn đã hủy vẫn tra ra để khách thấy trạng thái, nhưng luật quyền sửa
     * ở tầng dịch vụ mới là thứ quyết định có ghi được hay không.
     */
    private function timTheoMa(string $publicToken): ?Booking
    {
        return Booking::query()
            ->with(['schedule', 'tour:id,title'])
            ->where('public_token', $publicToken)
            ->first();
    }
}
