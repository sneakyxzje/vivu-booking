<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\ContactPurpose;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerContactLog;
use App\Support\GioVietNam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhật ký liên hệ khách của một đơn.
 *
 * Chỉ có ghi và đọc. **Không sửa, không xóa** - và đó là toàn bộ giá trị của bảng này: nó chỉ dùng
 * được khi nó là thứ đã xảy ra. Mở đường sửa thì nó thành thứ người ta muốn nó là, và lúc có tranh
 * cãi với khách thì chẳng chứng minh được gì.
 */
class AdminContactLogController extends Controller
{
    public function index(int $bookingId): JsonResponse
    {
        if (!Booking::query()->whereKey($bookingId)->exists()) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $ds = CustomerContactLog::query()
            ->where('booking_id', $bookingId)
            ->with(['contactedBy:id,name', 'transfers:id,contact_log_id'])
            ->orderByDesc('contacted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CustomerContactLog $tb) => [
                'id' => $tb->id,
                'channel' => $tb->channel->value,
                'channel_label' => $tb->channel->label(),
                'purpose' => $tb->purpose->value,
                'purpose_label' => $tb->purpose->label(),
                'outcome' => $tb->outcome->value,
                'outcome_label' => $tb->outcome->label(),
                'note' => $tb->note,
                'contacted_at' => $tb->contacted_at,
                'contacted_by' => $tb->contactedBy?->name,
                /*
                 * Bản ghi này đã tiêu vào một lần chuyển chưa.
                 *
                 * Màn hình cần biết để khỏi bày ra một lựa chọn mà máy chủ sẽ từ chối. Cùng lý do
                 * với việc `options()` lọc sẵn các chuyến không chuyển được.
                 */
                'da_dung_lam_can_cu' => $tb->transfers->isNotEmpty(),
                'dung_lam_can_cu_duoc' => $tb->laSuDongYChuyenChuyen() && $tb->transfers->isEmpty(),
            ]);

        return $this->success($ds, 'Lấy nhật ký liên hệ thành công');
    }

    public function store(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:' . implode(',', ContactChannel::values())],
            'purpose' => ['required', 'string', 'in:' . implode(',', ContactPurpose::values())],
            'outcome' => ['required', 'string', 'in:' . implode(',', ContactOutcome::values())],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'contacted_at' => ['nullable', 'date'],
        ], [
            'note.required' => 'Phải ghi nội dung trao đổi.',
            'note.min' => 'Nội dung cần ít nhất 10 ký tự: khách nói gì, đồng ý hay không, vì sao. '
                . 'Một bản ghi trống chỉ chứng minh có người bấm nút.',
        ]);

        if (!Booking::query()->whereKey($bookingId)->exists()) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $tb = CustomerContactLog::query()->create([
            'booking_id' => $bookingId,
            'channel' => $validated['channel'],
            'purpose' => $validated['purpose'],
            'outcome' => $validated['outcome'],
            'note' => trim($validated['note']),
            'contacted_by' => $request->user()?->getKey(),
            // Mặc định là bây giờ. Cho nhập tay vì điều hành hay gọi trước rồi mới mở máy ghi lại.
            'contacted_at' => isset($validated['contacted_at'])
                ? \Illuminate\Support\Carbon::parse($validated['contacted_at'])
                : GioVietNam::bayGio(),
        ]);

        return $this->success($tb->fresh(), 'Đã ghi nhận cuộc liên hệ.');
    }
}
