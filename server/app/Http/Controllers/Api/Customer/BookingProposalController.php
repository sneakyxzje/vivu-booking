<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Services\BookingProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingProposalController extends Controller
{
    public function __construct(private BookingProposalService $proposalService)
    {
    }

    /**
     * Lấy các đề xuất thay đổi của đơn hàng
     * Sử dụng publicToken (mã tra cứu) để xác thực (vì khách có thể là khách vãng lai)
     */
    public function index(Request $request, string $publicToken): JsonResponse
    {
        $booking = Booking::where('public_token', $publicToken)->firstOrFail();

        // Xác thực email tương tự như xem chi tiết đơn
        if (!$booking->khopEmail($request->query('email'))) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng, hoặc email không khớp.',
            ], 404);
        }

        $proposals = BookingChangeProposal::where('booking_id', $booking->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $proposals,
        ]);
    }

    /**
     * Khách hàng phản hồi đề xuất
     */
    public function respond(Request $request, string $publicToken, int $proposalId): JsonResponse
    {
        $booking = Booking::where('public_token', $publicToken)->firstOrFail();

        // Xác thực email
        if (!$booking->khopEmail($request->query('email'))) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng, hoặc email không khớp.',
            ], 404);
        }

        $proposal = BookingChangeProposal::where('booking_id', $booking->id)
            ->where('id', $proposalId)
            ->firstOrFail();

        if ($proposal->status !== ProposalStatus::Pending) {
            return response()->json([
                'success' => false,
                'message' => 'Đề xuất này đã được xử lý hoặc đã hết hạn.',
            ], 400);
        }

        if (now()->isAfter($proposal->response_deadline)) {
            $proposal->update(['status' => ProposalStatus::Expired->value]);
            return response()->json([
                'success' => false,
                'message' => 'Đề xuất này đã quá thời hạn phản hồi.',
            ], 400);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['action'] === 'accept') {
            $proposal->update([
                'status' => ProposalStatus::Accepted->value,
                'customer_note' => $validated['note'] ?? null,
                'responded_at' => now(),
            ]);

            $msg = 'Đã ghi nhận phản hồi đồng ý của bạn.';
        } else {
            $proposal->update([
                'status' => ProposalStatus::Rejected->value,
                'customer_note' => $validated['note'] ?? null,
                'responded_at' => now(),
            ]);
            $msg = 'Đã ghi nhận phản hồi từ chối của bạn.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => $proposal,
        ]);
    }
}
