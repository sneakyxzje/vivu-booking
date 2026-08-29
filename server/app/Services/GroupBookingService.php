<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\GroupRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CancellationPolicy;
use App\Models\GroupBookingRequest;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Booking theo đoàn: yêu cầu → báo giá → chốt → thu tiền nhiều đợt.
 *
 * ## Câu chuyện nghiệp vụ, một lần cho rõ
 *
 * Công ty X muốn đưa 40 nhân viên đi Hạ Long. Họ không đặt qua form khách lẻ - không kế toán nào
 * duyệt chuyển 80 triệu qua cổng thanh toán trong mười phút giữ chỗ, và lúc này họ còn chưa biết
 * chính xác những ai đi. Thực tế diễn ra thế này:
 *
 *   1. Đại diện đoàn gửi YÊU CẦU: chuyến nào, khoảng bao nhiêu người, thông tin xuất hóa đơn.
 *   2. Điều hành BÁO GIÁ: giá mỗi người (thường mềm hơn giá lẻ), suất miễn phí cho trưởng đoàn,
 *      hạn hiệu lực. Thương lượng qua điện thoại, báo giá lại bao nhiêu lần cũng được.
 *   3. Hai bên đồng ý thì điều hành CHỐT: lúc này mới sinh một `Booking` thật, chiếm chỗ thật.
 *   4. Tiền về NHIỀU ĐỢT: cọc khi chốt, phần còn lại trước ngày đi - ghi vào sổ giao dịch.
 *   5. Danh sách khách nộp SAU, qua màn quản lý hành khách sẵn có, trước hạn chốt danh sách.
 *
 * ## Hai ranh giới giữ xuyên suốt
 *
 * **Giá là quyết định của con người.** Không có bảng bậc giá tự động: giảm bao nhiêu cho đoàn
 * 40 người phụ thuộc mùa, quan hệ, chỗ trống - hệ thống ghi lại con số điều hành đưa ra và giữ
 * nhất quán chỗ với tiền, không tính hộ.
 *
 * **Chỗ và tiền đi qua đúng luật của khách lẻ.** Bước chốt khóa dòng chuyến, kiểm chỗ trống,
 * kiểm hạn chốt - y như form đặt lẻ. Đoàn to không phải lý do được vượt chỗ; lỗi lặp lại nhiều
 * lần nhất của dự án này là luật có ở một đường ghi mà thiếu ở đường kia.
 */
class GroupBookingService
{
    public function __construct(
        private readonly BookingHoldService $holdService,
        private readonly BookingAuditLogger $auditLogger,
        private readonly BookingPaymentService $paymentService,
    ) {
    }

    /**
     * Khách gửi yêu cầu. Chưa cam kết gì nên chưa chiếm chỗ.
     *
     * Cố ý KHÔNG bắt số người ước tính phải nhỏ hơn số chỗ còn trống: con số này khách đoán,
     * và xử lý một yêu cầu quá to là việc của điều hành (từ chối, hoặc gợi ý tách đoàn) chứ
     * không phải của cái form. Chuyến phải còn nhận đặt thì mới nhận yêu cầu - gửi yêu cầu vào
     * chuyến đã đóng bán hay đã qua hạn chốt là vô nghĩa với cả hai bên.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, ?User $customer = null): GroupBookingRequest
    {
        $schedule = TourSchedule::query()
            ->where('id', $data['tour_schedule_id'])
            ->where('tour_id', $data['tour_id'])
            ->first();

        if (!$schedule) {
            throw new BusinessRuleException('Lịch khởi hành không thuộc tour đã chọn.');
        }

        if (!$schedule->isBookable() || $schedule->tour?->status !== 'active') {
            throw new BusinessRuleException(
                'Chuyến này hiện không còn nhận đặt. Vui lòng chọn ngày khởi hành khác.',
            );
        }

        return GroupBookingRequest::query()->create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->getKey(),
            'customer_id' => $customer?->getKey(),
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'estimated_guests' => (int) $data['estimated_guests'],
            'company_name' => $data['company_name'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
            'invoice_address' => $data['invoice_address'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => GroupRequestStatus::PendingQuote,
        ]);
    }

    /**
     * Điều hành báo giá, hoặc báo giá lại khi thương lượng chưa ngã ngũ.
     *
     * Báo giá lại ĐÈ lên báo giá cũ: yêu cầu chỉ giữ con số đang có hiệu lực. Lịch sử mặc cả
     * nằm ở điện thoại của hai bên, không phải thứ hệ thống cần dựng lại.
     */
    public function quote(
        GroupBookingRequest $request,
        float $perPerson,
        int $freeSlots,
        \DateTimeInterface $expiresAt,
        ?string $note,
        User $actor,
    ): GroupBookingRequest {
        $this->assertTransition($request, GroupRequestStatus::Quoted);

        if ($perPerson <= 0) {
            throw new BusinessRuleException('Giá mỗi người phải lớn hơn 0.');
        }

        if ($freeSlots >= $request->estimated_guests) {
            throw new BusinessRuleException(
                'Số suất miễn phí phải nhỏ hơn số người của đoàn - miễn phí cả đoàn thì không còn là bán tour.',
            );
        }

        $request->forceFill([
            'status' => GroupRequestStatus::Quoted,
            'quoted_price_per_person' => round($perPerson, 2),
            'quoted_free_slots' => $freeSlots,
            'quote_note' => $note,
            'quote_expires_at' => $expiresAt,
            'quoted_at' => now(),
            'quoted_by' => $actor->getKey(),
        ])->save();

        return $request;
    }

    /**
     * Chốt: biến yêu cầu thành đơn hàng thật, chiếm chỗ thật.
     *
     * Đây là bước duy nhất chạm vào kho chỗ, nên toàn bộ luật của form đặt lẻ áp lại ở đây:
     * khóa dòng chuyến, nhả các đơn giữ chỗ quá hạn trước khi đếm, kiểm chỗ trống, kiểm chuyến
     * còn bán được. Số khách chốt do điều hành nhập - con số hai bên vừa thống nhất qua điện
     * thoại - không phải số ước tính lúc gửi yêu cầu.
     */
    public function confirm(GroupBookingRequest $request, int $finalGuests, User $actor): Booking
    {
        // Nhả chỗ quá hạn trước, để đoàn dùng được ngay các slot khách lẻ vừa bỏ.
        $this->holdService->releaseOverdueForSchedule((int) $request->tour_schedule_id);

        return DB::transaction(function () use ($request, $finalGuests, $actor) {
            $schedule = TourSchedule::query()
                ->whereKey($request->tour_schedule_id)
                ->lockForUpdate()
                ->first();

            // Đọc lại sau khi khóa: hai điều hành cùng chốt một yêu cầu thì người sau phải
            // thấy trạng thái đã đổi, không phải tạo đơn thứ hai.
            $fresh = GroupBookingRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertTransition($fresh, GroupRequestStatus::Confirmed);

            if ($fresh->quoted_price_per_person === null) {
                throw new BusinessRuleException('Chưa có báo giá nào để chốt.');
            }

            if ($fresh->quoteExpired()) {
                throw new BusinessRuleException(
                    'Báo giá đã hết hiệu lực. Báo giá lại rồi mới chốt được - giá cũ treo từ '
                    . $fresh->quote_expires_at->format('d/m/Y H:i') . ' không còn giữ chỗ đứng nữa.',
                );
            }

            if (!$schedule || !$schedule->isBookable() || $schedule->tour?->status !== 'active') {
                throw new BusinessRuleException(
                    'Chuyến này không còn nhận đặt (đã đóng bán, đã qua hạn chốt hoặc đã hủy).',
                );
            }

            if ($finalGuests <= $fresh->quoted_free_slots) {
                throw new BusinessRuleException(sprintf(
                    'Số khách chốt (%d) phải lớn hơn số suất miễn phí (%d).',
                    $finalGuests,
                    $fresh->quoted_free_slots,
                ));
            }

            $conTrong = $schedule->max_people - $schedule->booked_people;

            if ($finalGuests > $conTrong) {
                throw new BusinessRuleException(sprintf(
                    'Chuyến chỉ còn %d chỗ trống, không đủ cho đoàn %d người.',
                    $conTrong,
                    $finalGuests,
                ));
            }

            // Suất miễn phí vẫn chiếm ghế - trưởng đoàn không trả tiền nhưng vẫn ngồi trên xe.
            // Miễn phí là chuyện của TIỀN, không phải của CHỖ.
            $totalAmount = round(
                ($finalGuests - $fresh->quoted_free_slots) * (float) $fresh->quoted_price_per_person,
            );

            $booking = Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $fresh->tour_id,
                'customer_id' => $fresh->customer_id,
                'tour_schedule_id' => $schedule->getKey(),
                'customer_name' => $fresh->contact_name,
                'customer_email' => $fresh->contact_email,
                'customer_phone' => $fresh->contact_phone,
                'departure_date' => $schedule->start_date,
                'guests' => $finalGuests,
                // Giá đoàn là một giá thương lượng cho mỗi đầu người, không chia người lớn
                // trẻ em như giá niêm yết. Ghi cả đoàn vào adult_count để tổng các cột khớp
                // với guests - các màn đọc số liệu không phải xử lý riêng đơn đoàn.
                'adult_count' => $finalGuests,
                'child_count' => 0,
                'infant_count' => 0,
                'total_amount' => $totalAmount,
                // Đơn đoàn sống bằng xác nhận của điều hành, không có 10 phút giữ chỗ chờ cổng
                // thanh toán: status confirmed và không đặt expires_at, để tác vụ nền nhả chỗ
                // quá hạn (vốn chỉ nhìn đơn pending có expires_at) không bao giờ quét nhầm.
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'note' => $fresh->note,
                // Cùng quy tắc với đơn lẻ: chép bảng phí hủy tại thời điểm chốt, để sửa bảng phí
                // về sau không hồi tố lên đơn đã bán.
                'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
                'group_booking_request_id' => $fresh->getKey(),
            ]);

            $schedule->increment('booked_people', $finalGuests);

            $fresh->forceFill([
                'status' => GroupRequestStatus::Confirmed,
                'decided_at' => now(),
                'decided_by' => $actor->getKey(),
            ])->save();

            $this->auditLogger->log($booking, BookingAuditAction::Created, null, [
                'group_request_id' => $fresh->getKey(),
                'guests' => $finalGuests,
                'free_slots' => $fresh->quoted_free_slots,
                'price_per_person' => (float) $fresh->quoted_price_per_person,
                'total_amount' => $totalAmount,
            ], 'Chốt booking đoàn từ yêu cầu #' . $fresh->getKey());

            return $booking->load(['tour:id,title', 'schedule']);
        });
    }

    public function reject(GroupBookingRequest $request, string $reason, User $actor): void
    {
        $this->assertTransition($request, GroupRequestStatus::Rejected);

        $request->forceFill([
            'status' => GroupRequestStatus::Rejected,
            'rejected_reason' => trim($reason),
            'decided_at' => now(),
            'decided_by' => $actor->getKey(),
        ])->save();
    }

    /** Khách rút yêu cầu. Sau khi chốt thì không rút được nữa - lúc đó là hủy đơn, có phí. */
    public function withdraw(GroupBookingRequest $request): void
    {
        if (!$request->status->canTransitionTo(GroupRequestStatus::Withdrawn)) {
            throw new BusinessRuleException(
                $request->status === GroupRequestStatus::Confirmed
                    ? 'Yêu cầu đã được chốt thành đơn. Muốn hủy thì hủy đơn theo chính sách hủy, không rút yêu cầu được nữa.'
                    : 'Yêu cầu này đã đóng.',
            );
        }

        $request->forceFill([
            'status' => GroupRequestStatus::Withdrawn,
            'decided_at' => now(),
        ])->save();
    }

    /**
     * Ghi một khoản thu hoặc hoàn vào sổ giao dịch.
     *
     * Toàn bộ luật đã chuyển sang `BookingPaymentService` khi sổ mở cho cả đơn lẻ. Hai hàm này
     * giữ lại làm lối vào cũ để những nơi đang gọi không phải sửa cùng lúc — chúng chỉ chuyển
     * tiếp, không có luật riêng nào ở đây.
     */
    public function recordPayment(
        Booking $booking,
        string $kind,
        float $amount,
        ?string $method,
        ?string $reference,
        ?string $note,
        User $actor,
    ): BookingPayment {
        return $this->paymentService->record($booking, $kind, $amount, $method, $reference, $note, $actor);
    }

    /** Số đã thu thực: tổng các khoản thu trừ tổng các khoản hoàn. */
    public function netPaid(Booking $booking): float
    {
        return $this->paymentService->netPaid($booking);
    }

    /**
     * Đoàn giảm số khách - "3 người bận việc đột xuất" không phải lý do hủy cả đoàn 40 người.
     *
     * Đây là chỗ đơn đoàn CỐ Ý khác đơn lẻ. Khách lẻ không được sửa số khách (quyết định đã chốt
     * ở luồng cập nhật booking: đổi thứ đã mua thì hủy đặt lại) - vì đơn lẻ 2-4 người, hủy đặt
     * lại là một thao tác nhỏ. Đoàn 40 người mà bắt hủy đặt lại vì bớt 3 người thì vô lý: phá
     * toàn bộ giấy tờ, sổ giao dịch, danh sách đã nhập của 37 người còn lại.
     *
     * Chỉ trước hạn chốt danh sách: sau mốc đó phòng và suất ăn đã đặt theo danh sách gửi nhà
     * cung cấp, bớt người không bớt được chi phí - chỗ của họ thành ghế chết, và tiền thừa (nếu
     * có) là chuyện thương lượng giữa người với người, hệ thống chỉ hiển thị chênh lệch.
     */
    public function reduceGuests(Booking $booking, int $newGuests, User $actor, ?string $reason = null): Booking
    {
        if (!$booking->isGroup()) {
            throw new BusinessRuleException(
                'Chỉ đơn đoàn được giảm số khách. Đơn lẻ muốn đổi số người thì hủy và đặt lại theo chính sách hủy.',
            );
        }

        return DB::transaction(function () use ($booking, $newGuests, $actor, $reason) {
            $schedule = TourSchedule::query()
                ->whereKey($booking->tour_schedule_id)
                ->lockForUpdate()
                ->first();

            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if ($fresh->status !== 'confirmed') {
                throw new BusinessRuleException('Chỉ giảm được số khách của đơn đang hiệu lực.');
            }

            $deadline = $schedule?->booking_deadline ?? $schedule?->defaultBookingDeadline();

            if ($deadline && now()->gte($deadline)) {
                throw new BusinessRuleException(
                    'Đã qua hạn chốt danh sách - phòng và suất ăn đã đặt theo danh sách gửi nhà '
                    . 'cung cấp, giảm người lúc này không giảm được chi phí. Ghi nhận vắng mặt '
                    . 'từng người khi điểm danh, phần tiền xử lý riêng với điều hành.',
                );
            }

            $freeSlots = (int) ($fresh->groupRequest?->quoted_free_slots ?? 0);

            if ($newGuests >= $fresh->guests) {
                throw new BusinessRuleException('Số khách mới phải nhỏ hơn số hiện tại. Muốn thêm người thì liên hệ điều hành báo giá phần thêm.');
            }

            if ($newGuests <= $freeSlots) {
                throw new BusinessRuleException(sprintf(
                    'Đoàn phải còn nhiều hơn %d người (số suất miễn phí đã báo giá).',
                    $freeSlots,
                ));
            }

            $giaMotNguoi = (float) ($fresh->groupRequest?->quoted_price_per_person ?? 0);
            $tongMoi = round(($newGuests - $freeSlots) * $giaMotNguoi);
            $giamBaoNhieu = $fresh->guests - $newGuests;

            $cu = ['guests' => $fresh->guests, 'total_amount' => round((float) $fresh->total_amount)];

            $fresh->forceFill([
                'guests' => $newGuests,
                'adult_count' => $newGuests,
                'total_amount' => $tongMoi,
            ])->save();

            // Trước hạn chốt nên chỗ trả về kho bán lại được - cùng lý do luật trả chỗ của
            // đơn lẻ trả chỗ khi hủy trước hạn.
            if ($schedule) {
                $schedule->decrement('booked_people', min($giamBaoNhieu, (int) $schedule->booked_people));
            }

            $this->auditLogger->log($fresh, BookingAuditAction::GuestsReduced, $cu, [
                'guests' => $newGuests,
                'total_amount' => $tongMoi,
                'net_paid' => $this->netPaid($fresh),
            ], $reason);

            return $fresh;
        });
    }

    private function assertTransition(GroupBookingRequest $request, GroupRequestStatus $to): void
    {
        if (!$request->status->canTransitionTo($to)) {
            throw new BusinessRuleException(sprintf(
                'Yêu cầu đang ở trạng thái "%s", không chuyển sang "%s" được.',
                $request->status->label(),
                $to->label(),
            ));
        }
    }
}
