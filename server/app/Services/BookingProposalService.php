<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ProposalStatus;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Models\TourSchedule;

class BookingProposalService
{
    public function __construct(
        private BookingTransferService $transferService,
        private BookingPaymentService $payments,
        private BookingHoldService $holdService,
        private BookingAuditLogger $auditLogger
    ) {
    }

    /**
     * Thực thi hành động của một đề xuất (refund hoặc transfer:id)
     */
    public function executeSystemAction(BookingChangeProposal $proposal, string $actionName): void
    {
        $booking = $proposal->booking;
        $schedule = $booking->schedule;

        if (str_starts_with($actionName, 'transfer:')) {
            $parts = explode(':', $actionName);
            $toScheduleId = (int) $parts[1];
            
            $toSchedule = TourSchedule::find($toScheduleId);
            if ($toSchedule) {
                // Sử dụng BookingTransferService để chuyển chuyến (Miễn phí vì do hãng khởi xướng)
                $this->transferService->transfer(
                    booking: $booking,
                    toSchedule: $toSchedule,
                    reason: 'Chuyển chuyến tự động theo đề xuất của ban điều hành. Lý do: ' . $proposal->reason,
                    actor: null,
                    initiatedBy: 'company',
                    nguonBiHuy: false,
                );
            }
        } elseif ($actionName === 'refund') {
            // Thực hiện Hủy và Hoàn tiền 100% (Do hãng đề xuất)
            $this->cancelAndRefund($booking, $schedule, $proposal->reason);
        }
    }

    /**
     * Hủy đơn và hoàn 100% tiền (Mô phỏng lại logic của ScheduleCancellationService::hoanDu)
     */
    private function cancelAndRefund(Booking $booking, ?TourSchedule $schedule, string $reason): void
    {
        // Nếu đơn chưa thanh toán, chỉ hủy đơn giản
        if ($booking->status === BookingStatus::Pending) {
            $lyDo = 'Công ty hủy đơn theo đề xuất. ' . $reason;
            $booking->forceFill([
                'status' => BookingStatus::Cancelled->value,
                'cancel_type' => 'by_company',
                'cancel_reason' => $lyDo,
                'cancelled_at' => now(),
                'seats_released' => true,
                'seats_released_at' => now(),
            ])->save();

            $this->holdService->releaseDiscountUsage($booking);

            if ($schedule) {
                $schedule->decrement('booked_people', min($booking->seatsTaken(), (int) $schedule->booked_people));
                $schedule->refresh();
            }

            $this->auditLogger->logStatusChange(
                $booking,
                BookingAuditAction::Cancelled,
                BookingStatus::Pending->value,
                BookingStatus::Cancelled->value,
                $lyDo,
                ['seats_released' => true, 'refund_amount' => 0],
            );
            return;
        }

        // Đơn đã thanh toán: Tính nghĩa vụ hoàn
        $soDaThu = $this->payments->paidForTour($booking);
        $soTien = $this->payments->nghiaVuHoanGop($booking, $soDaThu);
        $trangThaiCu = (string) $booking->status;

        $lyDo = 'Công ty hủy đơn theo đề xuất. ' . $reason . ' Quý khách được hoàn đủ số tiền đã thanh toán.';

        $booking->forceFill([
            'status' => BookingStatus::Cancelled->value,
            'cancel_type' => 'by_company',
            'cancel_reason' => $lyDo,
            'cancelled_at' => now(),
            'refund_amount' => $soTien,
            'seats_released' => true,
            'seats_released_at' => now(),
        ])->save();

        $this->holdService->releaseDiscountUsage($booking);

        if ($schedule) {
            $schedule->decrement('booked_people', min($booking->seatsTaken(), (int) $schedule->booked_people));
            $schedule->refresh();
        }

        $this->auditLogger->logStatusChange(
            $booking,
            BookingAuditAction::Cancelled,
            $trangThaiCu,
            BookingStatus::Cancelled->value,
            $lyDo,
            [
                'refund_amount' => $soTien,
                'refund_percent' => 100, // Hãng hủy nên hoàn 100%
                'seats_released' => true,
                'cancelled_schedule_id' => $schedule?->getKey(),
            ],
        );
    }
}
