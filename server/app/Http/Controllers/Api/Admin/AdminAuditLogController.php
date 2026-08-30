<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleAuditAction;
use App\Http\Controllers\Controller;
use App\Models\BookingAuditLog;
use App\Models\ScheduleAuditLog;
use App\Traits\LocKhoangThoiGian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhật ký hệ thống: một dòng thời gian cho mọi can thiệp vào đơn và vào chuyến.
 *
 * Trước màn hình này, nhật ký đơn chỉ xem được khi mở hộp chi tiết của đúng đơn đó, tức là phải
 * biết trước cần tìm đơn nào. Câu hỏi thật của điều hành lại đi theo chiều ngược lại: "hôm qua ai
 * đụng vào tiền", "tháng này có bao nhiêu lần mở lại chỗ", "ai dời hạn chốt các chuyến". Không
 * trả lời được câu nào nếu phải mở từng đơn một.
 *
 * Nhật ký chuyến thì chưa có chỗ đọc nào cả.
 *
 * Gộp hai bảng thay vì để hai tab, vì một lần hủy đơn và một lần dời hạn chốt của chính chuyến
 * đó là hai mảnh của cùng một câu chuyện; tách ra thì người đọc phải tự ghép theo thời gian.
 */
class AdminAuditLogController extends Controller
{
    use LocKhoangThoiGian;

    private const PER_PAGE_TOI_DA = 100;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:all,booking,schedule'],
            'action' => ['nullable', 'string', 'max:40'],
            'actor_id' => ['nullable', 'integer'],
            'booking_id' => ['nullable', 'integer'],
            'schedule_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'money_only' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::PER_PAGE_TOI_DA],
        ]);

        $scope = $validated['scope'] ?? 'all';
        $trang = (int) ($validated['page'] ?? 1);
        $moiTrang = (int) ($validated['per_page'] ?? 20);
        $chiTien = (bool) ($validated['money_only'] ?? false);

        // money_only là bộ lọc của nhật ký đơn: dời hạn chốt không chạm tiền của ai.
        $layDon = $scope !== 'schedule';
        $layChuyen = $scope !== 'booking' && !$chiTien;

        $dongDon = $layDon
            ? $this->dongCuaDon($validated, $chiTien, $trang * $moiTrang)
            : collect();

        $dongChuyen = $layChuyen
            ? $this->dongCuaChuyen($validated, $trang * $moiTrang)
            : collect();

        /*
         * Gộp ở tầng PHP chứ không dùng UNION.
         *
         * Hai bảng khác khóa ngoại và khác enum thao tác, ép về một truy vấn thì phải bịa cột cho
         * bằng nhau ở cả hai phía. Mỗi bảng chỉ lấy tối đa số dòng cần cho trang đang xem, nên
         * lượng dữ liệu kéo về vẫn có chặn trên chứ không phải quét cả bảng.
         */
        $tatCa = $dongDon
            ->concat($dongChuyen)
            ->sortByDesc(fn (array $dong) => $dong['created_at'])
            ->values();

        $tong = ($layDon ? $this->demDon($validated, $chiTien) : 0)
            + ($layChuyen ? $this->demChuyen($validated) : 0);

        return $this->success([
            'data' => $tatCa->slice(($trang - 1) * $moiTrang, $moiTrang)->values(),
            'meta' => [
                'current_page' => $trang,
                'per_page' => $moiTrang,
                'total' => $tong,
                'last_page' => max(1, (int) ceil($tong / $moiTrang)),
            ],
            'filters' => [
                'booking_actions' => $this->nhanThaoTacDon(),
                'schedule_actions' => $this->nhanThaoTacChuyen(),
            ],
        ], 'Lấy nhật ký hệ thống thành công');
    }

    /** @param array<string, mixed> $loc */
    private function truyVanDon(array $loc, bool $chiTien)
    {
        $query = BookingAuditLog::query()
            ->with(['actor:id,name', 'booking:id,customer_name'])
            ->when(isset($loc['actor_id']), fn ($q) => $q->where('actor_id', $loc['actor_id']))
            ->when(isset($loc['booking_id']), fn ($q) => $q->where('booking_id', $loc['booking_id']))
            ->when(isset($loc['from']), fn ($q) => $q->where('created_at', '>=', $this->mocDau($loc['from'])))
            ->when(isset($loc['to']), fn ($q) => $q->where('created_at', '<=', $this->mocCuoi($loc['to'])));

        // schedule_id không phải cột của bảng này; lọc theo chuyến thì đi qua đơn thuộc chuyến đó.
        if (isset($loc['schedule_id'])) {
            $query->whereHas('booking', fn ($q) => $q->where('tour_schedule_id', $loc['schedule_id']));
        }

        if (isset($loc['action'])) {
            $query->where('action', $loc['action']);
        }

        if ($chiTien) {
            $query->whereIn('action', $this->thaoTacChamTien());
        }

        return $query;
    }

    /** @param array<string, mixed> $loc */
    private function dongCuaDon(array $loc, bool $chiTien, int $gioiHan)
    {
        return $this->truyVanDon($loc, $chiTien)
            ->latest('created_at')
            ->latest('id')
            ->limit($gioiHan)
            ->get()
            ->map(fn (BookingAuditLog $log) => [
                'id' => 'booking-' . $log->id,
                'source' => 'booking',
                'subject_id' => $log->booking_id,
                'subject_label' => 'BK-' . $log->booking_id,
                'subject_note' => $log->booking?->customer_name,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'touches_money' => $log->action->touchesMoney(),
                'actor_name' => $log->actor?->name,
                'actor_role' => $log->actor_role,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);
    }

    /** @param array<string, mixed> $loc */
    private function truyVanChuyen(array $loc)
    {
        return ScheduleAuditLog::query()
            ->with(['actor:id,name', 'schedule:id,start_date,tour_id', 'schedule.tour:id,title'])
            ->when(isset($loc['actor_id']), fn ($q) => $q->where('actor_id', $loc['actor_id']))
            ->when(isset($loc['schedule_id']), fn ($q) => $q->where('tour_schedule_id', $loc['schedule_id']))
            ->when(isset($loc['action']), fn ($q) => $q->where('action', $loc['action']))
            ->when(isset($loc['from']), fn ($q) => $q->where('created_at', '>=', $this->mocDau($loc['from'])))
            ->when(isset($loc['to']), fn ($q) => $q->where('created_at', '<=', $this->mocCuoi($loc['to'])))
            // Lọc theo đơn thì nhật ký chuyến không có gì để trả về.
            ->when(isset($loc['booking_id']), fn ($q) => $q->whereRaw('1 = 0'));
    }

    /** @param array<string, mixed> $loc */
    private function dongCuaChuyen(array $loc, int $gioiHan)
    {
        return $this->truyVanChuyen($loc)
            ->latest('created_at')
            ->latest('id')
            ->limit($gioiHan)
            ->get()
            ->map(fn (ScheduleAuditLog $log) => [
                'id' => 'schedule-' . $log->id,
                'source' => 'schedule',
                'subject_id' => $log->tour_schedule_id,
                'subject_label' => 'Chuyến #' . $log->tour_schedule_id,
                'subject_note' => $log->schedule?->start_date
                    ? 'khởi hành ' . $log->schedule->start_date->format('d/m/Y H:i')
                    : $log->schedule?->tour?->title,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                // Dời hạn chốt không chạm tiền của khách: bậc hoàn tính theo giờ trước khởi hành.
                'touches_money' => false,
                'actor_name' => $log->actor?->name,
                'actor_role' => $log->actor_role,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);
    }

    /** @param array<string, mixed> $loc */
    private function demDon(array $loc, bool $chiTien): int
    {
        return $this->truyVanDon($loc, $chiTien)->count();
    }

    /** @param array<string, mixed> $loc */
    private function demChuyen(array $loc): int
    {
        return $this->truyVanChuyen($loc)->count();
    }

    /** @return array<int, string> */
    private function thaoTacChamTien(): array
    {
        return array_values(array_map(
            static fn (BookingAuditAction $case): string => $case->value,
            array_filter(
                BookingAuditAction::cases(),
                static fn (BookingAuditAction $case): bool => $case->touchesMoney(),
            ),
        ));
    }

    /** @return array<int, array{value: string, label: string, touches_money: bool}> */
    private function nhanThaoTacDon(): array
    {
        return array_map(static fn (BookingAuditAction $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'touches_money' => $case->touchesMoney(),
        ], BookingAuditAction::cases());
    }

    /** @return array<int, array{value: string, label: string, touches_money: bool}> */
    private function nhanThaoTacChuyen(): array
    {
        return array_map(static fn (ScheduleAuditAction $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'touches_money' => false,
        ], ScheduleAuditAction::cases());
    }
}
