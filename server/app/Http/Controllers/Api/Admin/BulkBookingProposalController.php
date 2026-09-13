<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Mail\BookingProposalMail;
use App\Models\TourSchedule;
use App\Models\BookingChangeProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BulkBookingProposalController extends Controller
{
    /**
     * Tạo Đề xuất Hàng loạt cho 1 Chuyến đi
     */
    public function store(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'response_deadline' => ['required', 'date', 'after:now'],
            'fallback_action' => ['required', 'string'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.id' => ['required', 'string'],
            'options.*.title' => ['required', 'string'],
            'options.*.description' => ['nullable', 'string'],
            'options.*.system_action' => ['required', 'string'], // e.g. 'refund' or 'transfer:10'
        ]);

        $schedule = TourSchedule::with('bookings')->find($scheduleId);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy chuyến đi.',
            ], 404);
        }

        // Chỉ lấy các đơn đã thanh toán hoặc giữ chỗ, và không phải trạng thái cuối
        $validStatuses = BookingStatus::paidValues();
        $terminalStatuses = BookingStatus::terminalValues();

        $targetBookings = $schedule->bookings->filter(function ($booking) use ($validStatuses, $terminalStatuses) {
            return in_array($booking->status, $validStatuses) && !in_array($booking->status, $terminalStatuses);
        });

        if ($targetBookings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Không có đơn hàng nào hợp lệ để gửi đề xuất trong chuyến này.',
            ], 400);
        }

        $proposalsCreated = 0;
        $adminId = auth()->id() ?? 1;

        DB::transaction(function () use ($targetBookings, $validated, $adminId, &$proposalsCreated) {
            foreach ($targetBookings as $booking) {
                // Hủy các đề xuất pending cũ của booking này
                BookingChangeProposal::where('booking_id', $booking->id)
                    ->where('status', ProposalStatus::Pending->value)
                    ->update(['status' => ProposalStatus::Expired->value]);

                // Tạo đề xuất mới
                $proposal = BookingChangeProposal::create([
                    'booking_id' => $booking->id,
                    'admin_id' => $adminId,
                    'reason' => $validated['reason'],
                    'options' => $validated['options'],
                    'response_deadline' => $validated['response_deadline'],
                    'fallback_action' => $validated['fallback_action'],
                    'status' => ProposalStatus::Pending->value,
                ]);

                // Đẩy vào queue gửi mail
                Mail::to($booking->customer_email)->send(new BookingProposalMail($booking, $proposal));
                $proposalsCreated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Đã tạo và gửi thành công {$proposalsCreated} đề xuất thay đổi.",
        ], 201);
    }

    /**
     * Lấy thống kê trạng thái Đề xuất của Chuyến đi
     */
    public function stats(int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::with('bookings.proposals')->find($scheduleId);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy chuyến đi.',
            ], 404);
        }

        $bookingIds = $schedule->bookings->pluck('id');

        // Lấy tất cả proposals mới nhất của mỗi booking
        $latestProposals = BookingChangeProposal::whereIn('booking_id', $bookingIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('booking_id');

        $stats = [
            'total' => $latestProposals->count(),
            'pending' => $latestProposals->where('status', ProposalStatus::Pending->value)->count(),
            'accepted' => $latestProposals->where('status', ProposalStatus::Accepted->value)->count(),
            'rejected' => $latestProposals->where('status', ProposalStatus::Rejected->value)->count(),
            'expired' => $latestProposals->where('status', ProposalStatus::Expired->value)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
