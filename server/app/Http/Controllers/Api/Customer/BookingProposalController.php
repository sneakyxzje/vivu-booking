<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Services\ScheduleMergeService;
use App\Services\DemoClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingProposalController extends Controller
{
    public function __construct(private ScheduleMergeService $proposalService)
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

        BookingChangeProposal::where('booking_id', $booking->id)->pending()
            ->where('response_deadline', '<=', DemoClock::booking($booking))
            ->get()->each(fn ($proposal) => $this->proposalService->respond($proposal, 'expire'));

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

        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $proposal = $this->proposalService->respond($proposal, $validated['action'], $validated['note'] ?? null);
        if ($proposal->status === ProposalStatus::Expired) {
            return response()->json(['success' => false, 'message' => $proposal->schedule_snapshot
                ? 'Đề xuất đã hết hạn. Đơn đã hủy và được ghi nhận hoàn đủ số tiền đã thu còn lại.'
                : 'Đề xuất đã hết hạn.'], 422);
        }
        $msg = $validated['action'] === 'accept'
            ? 'Đã xác nhận đồng ý đề xuất.'
            : ($proposal->schedule_snapshot
                ? 'Đã hủy đơn và ghi nhận chờ hoàn đủ số tiền đã thu còn lại.'
                : 'Đã từ chối đề xuất.');

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => $proposal,
        ]);
    }
}
