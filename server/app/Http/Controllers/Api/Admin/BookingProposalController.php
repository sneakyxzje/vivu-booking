<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class BookingProposalController extends Controller
{
    /**
     * Danh sách các đề xuất thay đổi của đơn hàng
     */
    public function index(int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);

        $proposals = BookingChangeProposal::where('booking_id', $booking->id)
            ->with('admin:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $proposals,
        ]);
    }

    /**
     * Tạo đề xuất thay đổi mới
     */
    public function store(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],

            'response_deadline' => ['required', 'date', 'after:now'],
        ]);

        // Hủy các đề xuất đang chờ cũ (nếu có) để tránh xung đột
        BookingChangeProposal::where('booking_id', $booking->id)
            ->where('status', ProposalStatus::Pending->value)
            ->update(['status' => ProposalStatus::Expired->value]);

        $proposal = DB::transaction(function () use ($booking, $validated) {
            $proposal = BookingChangeProposal::create([
                'booking_id' => $booking->id,
                'admin_id' => auth()->id() ?? 1, // fallback for testing if no auth
                'reason' => $validated['reason'],

                'response_deadline' => $validated['response_deadline'],
                'status' => ProposalStatus::Pending->value,
            ]);

            // Trigger email to customer
            Mail::to($booking->customer_email)->send(new \App\Mail\BookingProposalMail($booking, $proposal));

            return $proposal;
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi đề xuất thay đổi cho khách hàng.',
            'data' => $proposal,
        ], 201);
    }

    /**
     * Hủy đề xuất thay đổi (nếu khách chưa phản hồi)
     */
    public function destroy(int $bookingId, int $proposalId): JsonResponse
    {
        $proposal = BookingChangeProposal::where('booking_id', $bookingId)
            ->where('id', $proposalId)
            ->firstOrFail();

        if ($proposal->status !== ProposalStatus::Pending) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đề xuất đang chờ phản hồi.',
            ], 400);
        }

        $proposal->update(['status' => ProposalStatus::Expired->value]);

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy đề xuất thay đổi.',
        ]);
    }
}
