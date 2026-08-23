<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingContract;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Q - Hợp đồng du lịch của một đơn.
 *
 * Cấp số, trả về liên kết in, ghi nhận đã ký. Bản in nằm ở tuyến web riêng vì nó là HTML để in,
 * không phải JSON — xem ContractPrintController.
 */
class AdminContractController extends Controller
{
    public function __construct(
        private ContractService $contracts,
    ) {
    }

    /**
     * Tình trạng hợp đồng của một đơn.
     *
     * Trả về `null` khi chưa cấp, chứ không phải lỗi 404: "đơn này chưa có hợp đồng" là một câu
     * trả lời bình thường, và màn hình cần nó để biết hiện nút "Cấp hợp đồng" hay nút "Mở bản in".
     */
    public function show(int $bookingId): JsonResponse
    {
        $contract = BookingContract::query()
            ->where('booking_id', $bookingId)
            ->with('issuer:id,name')
            ->first();

        return $this->success(
            $contract ? $this->dong($contract) : null,
            'Lấy tình trạng hợp đồng thành công',
        );
    }

    /** Cấp hợp đồng, hoặc trả lại bản đã cấp. Không sinh số mới cho đơn đã có hợp đồng. */
    public function issue(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::query()->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour', 404);
        }

        $daCoTruoc = BookingContract::query()->where('booking_id', $bookingId)->exists();
        $contract = $this->contracts->issue($booking, $request->user());

        return $this->success(
            $this->dong($contract->load('issuer:id,name')),
            $daCoTruoc
                ? 'Đơn này đã có hợp đồng ' . $contract->contract_number . '.'
                : 'Đã cấp hợp đồng ' . $contract->contract_number . '.',
        );
    }

    /** Khách ký xong thì ghi lại mốc. */
    public function markSigned(Request $request, int $contractId): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $contract = BookingContract::query()->find($contractId);

        if (!$contract) {
            return $this->error('Không tìm thấy hợp đồng', 404);
        }

        $daKy = $this->contracts->markSigned($contract, $validated['note'] ?? null);

        return $this->success($this->dong($daKy), 'Đã ghi nhận hợp đồng được ký.');
    }

    /** @return array<string, mixed> */
    private function dong(BookingContract $contract): array
    {
        return [
            'id' => $contract->id,
            'booking_id' => $contract->booking_id,
            'contract_number' => $contract->contract_number,
            'issued_at' => $contract->issued_at?->toDateTimeString(),
            'issued_by_name' => $contract->issuer?->name,
            'signed_at' => $contract->signed_at?->toDateTimeString(),
            'signed_note' => $contract->signed_note,
            /*
             * Liên kết sinh mới mỗi lần đọc, vì nó có hạn dùng. Lưu lại vào cột thì lần mở sau
             * nhận một liên kết đã chết, và người dùng không hiểu vì sao.
             */
            'print_url' => $this->contracts->printUrl($contract),
        ];
    }
}
