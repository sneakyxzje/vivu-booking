<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPassengerSupplement;
use App\Services\DemoClock;
use App\Services\PassengerPolicyService;
use App\Services\PassengerSupplementService;
use Illuminate\Http\Request;

class PassengerSupplementController extends Controller
{
    public function __construct(private PassengerSupplementService $service) {}

    public function index(int $id)
    {
        $booking = Booking::with(['schedule', 'passengers'])->findOrFail($id);
        $remaining = [];
        foreach (['adult', 'child', 'infant'] as $type) {
            $remaining[$type] = max(0, (int) $booking->{$type.'_count'} - $booking->passengers->where('type', $type)->count());
        }
        $deadline = $booking->schedule?->booking_deadline ?? $booking->schedule?->defaultBookingDeadline();
        $reason = $this->service->unavailableReason($booking);
        return $this->success([
            'guests' => (int) $booking->guests, 'declared' => $booking->passengers->count(),
            'missing' => max(0, (int) $booking->guests - $booking->passengers->count()),
            'remaining' => $remaining, 'is_group' => $booking->isGroup() || ((int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count === 0),
            'can_supplement' => $reason === null, 'unavailable_reason' => $reason,
            'deadline_passed' => $deadline && DemoClock::schedule($booking->schedule)->gte($deadline),
            'supplements' => BookingPassengerSupplement::where('booking_id', $id)->latest('id')->get(),
        ]);
    }

    public function store(Request $request, int $id)
    {
        $data = $request->validate(PassengerPolicyService::validationRules() + [
            'request_key' => ['required', 'uuid'],
            'reported_by' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'passengers.*.id' => ['prohibited'],
            'passengers.*.booking_id' => ['prohibited'],
        ]);
        // Discard unknown nested keys so this endpoint cannot update IDs or foreign keys.
        $fields = ['name', 'type', 'gender', 'date_of_birth', 'identity_number', 'id_type', 'nationality', 'phone', 'special_request', 'is_contact', 'note'];
        $data['passengers'] = array_map(fn ($p) => array_intersect_key($p, array_flip($fields)), $data['passengers']);
        return $this->success($this->service->append($id, $data, $request->user()), 'Đã bổ sung hành khách. Cần gửi bản cập nhật cho nhà cung cấp.', 201);
    }

    public function sent(Request $request, int $id, int $supplementId)
    {
        $data = $request->validate([
            'sent_to' => ['required', 'string', 'min:3', 'max:500'],
            'sent_note' => ['nullable', 'string', 'max:1000'],
        ]);
        return $this->success($this->service->markSent($id, $supplementId, $data, $request->user()), 'Đã ghi nhận việc gửi bản bổ sung.');
    }

    public function export(int $id, int $supplementId)
    {
        $supplement = BookingPassengerSupplement::where('booking_id', $id)->whereKey($supplementId)->firstOrFail();
        return response()->streamDownload(function () use ($supplement) {
            $file = fopen('php://output', 'wb');
            fwrite($file, "\xEF\xBB\xBF");
            $write = function (array $row) use ($file) {
                $safe = array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', (string) $cell) ? "'".$cell : $cell, $row);
                fputcsv($file, $safe, ';', '"', '');
            };
            $write(['BẢN BỔ SUNG HÀNH KHÁCH', 'BK'.$supplement->booking_id, 'Chuyến #'.$supplement->tour_schedule_id]);
            $write(['Bổ sung lúc', $supplement->recorded_at->format('d/m/Y H:i')]);
            $write(['Họ tên', 'Loại khách', 'Ngày sinh', 'Loại giấy tờ', 'Số giấy tờ', 'Điện thoại', 'Yêu cầu riêng']);
            foreach ($supplement->passengers as $p) {
                $write([$p['name'], ['adult' => 'Người lớn', 'child' => 'Trẻ em', 'infant' => 'Em bé'][$p['type']],
                    substr($p['date_of_birth'] ?? '', 0, 10), $p['id_type'] ?? '', $p['identity_number'] ?? '',
                    $p['phone'] ?? '', $p['special_request'] ?? '']);
            }
            fclose($file);
        }, "bo-sung-BK{$id}-{$supplementId}.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
