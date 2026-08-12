<?php

use App\Enums\PassengerCheckinStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H04 - Chuyển dữ liệu điểm danh cũ sang mô hình mới.
 *
 * Mô hình cũ điểm danh theo ĐƠN HÀNG và theo NGÀY: một đơn bốn người chỉ tick một lần, và chỉ
 * có hai giá trị có mặt hoặc vắng. Mô hình mới điểm danh theo TỪNG HÀNH KHÁCH tại TỪNG ĐIỂM DỪNG.
 *
 * Mỗi chặng cũ không có điểm dừng nào nên phải sinh một điểm dừng mặc định để dữ liệu cũ có chỗ
 * chuyển sang. Mỗi bản ghi cũ tách thành nhiều bản ghi mới, một cho mỗi hành khách của đơn.
 *
 * Bảng booking_checkins được giữ nguyên, không xóa. Đây là dữ liệu dùng để đối chiếu khi có
 * khiếu nại, nên phải còn bản gốc cho tới khi chắc chắn bản chuyển đổi đúng.
 *
 * Tài liệu: docs/nghiep-vu/07-thiet-ke-du-lieu.md mục 1.6
 */
return new class extends Migration
{
    private const DEFAULT_CHECKPOINT_NAME = 'Điểm danh trong ngày';

    public function up(): void
    {
        if (!Schema::hasTable('booking_checkins')) {
            return;
        }

        $legacy = DB::table('booking_checkins')->orderBy('id')->get();

        if ($legacy->isEmpty()) {
            return;
        }

        $checkpointCache = [];
        $now = now();

        foreach ($legacy as $old) {
            $checkpointId = $checkpointCache[$old->tour_itinerary_id]
                ??= $this->resolveDefaultCheckpoint((int) $old->tour_itinerary_id, $now);

            if (!$checkpointId) {
                continue;
            }

            $booking = DB::table('bookings')
                ->where('id', $old->booking_id)
                ->first(['id', 'tour_schedule_id']);

            if (!$booking) {
                continue;
            }

            $passengers = DB::table('booking_passengers')
                ->where('booking_id', $old->booking_id)
                ->pluck('id');

            // Đơn tạo trước khi có bảng hành khách thì không có ai để gắn bản ghi vào.
            // Bỏ qua chứ không dựng hành khách giả: dữ liệu gốc vẫn còn ở booking_checkins.
            if ($passengers->isEmpty()) {
                continue;
            }

            $status = $old->present
                ? PassengerCheckinStatus::Present->value
                : PassengerCheckinStatus::Absent->value;

            // Trạng thái vắng ở mô hình mới bắt buộc có lý do, nhưng dữ liệu cũ không lưu.
            // Ghi rõ nguồn gốc thay vì để trống, để người đọc sau này biết vì sao không có lý do.
            $note = $old->present
                ? null
                : 'Chuyển từ dữ liệu điểm danh cũ, bản ghi gốc không lưu lý do vắng.';

            foreach ($passengers as $passengerId) {
                $daCo = DB::table('passenger_checkins')
                    ->where('booking_passenger_id', $passengerId)
                    ->where('itinerary_checkpoint_id', $checkpointId)
                    ->exists();

                if ($daCo) {
                    continue;
                }

                DB::table('passenger_checkins')->insert([
                    'booking_passenger_id' => $passengerId,
                    'tour_schedule_id' => $booking->tour_schedule_id,
                    'itinerary_checkpoint_id' => $checkpointId,
                    'status' => $status,
                    'note' => $note,
                    'checked_by' => $old->guide_id,
                    'checked_at' => $old->checked_at,
                    // Bản ghi chuyển đổi không phải ghi bù muộn, nó là dữ liệu đã có sẵn.
                    'is_late_entry' => false,
                    'created_at' => $old->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Điểm dừng mặc định của một chặng, tạo mới nếu chặng đó chưa có điểm dừng nào.
     * Dùng lại điểm dừng đầu tiên nếu quản trị đã khai báo, để không sinh trùng.
     */
    private function resolveDefaultCheckpoint(int $itineraryId, $now): ?int
    {
        $itinerary = DB::table('tour_itineraries')->where('id', $itineraryId)->first(['id']);

        if (!$itinerary) {
            return null;
        }

        $existing = DB::table('itinerary_checkpoints')
            ->where('tour_itinerary_id', $itineraryId)
            ->orderBy('sequence')
            ->orderBy('id')
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('itinerary_checkpoints')->insertGetId([
            'tour_itinerary_id' => $itineraryId,
            'name' => self::DEFAULT_CHECKPOINT_NAME,
            'description' => 'Sinh tự động khi chuyển dữ liệu điểm danh cũ sang mô hình theo điểm dừng.',
            'sequence' => 1,
            'is_required_photo' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Chỉ xóa các bản ghi sinh ra từ dữ liệu cũ, nhận diện qua điểm dừng mặc định.
        // Không xóa điểm dừng do quản trị tự khai báo và điểm danh ghi trên đó.
        $checkpointIds = DB::table('itinerary_checkpoints')
            ->where('name', self::DEFAULT_CHECKPOINT_NAME)
            ->pluck('id');

        if ($checkpointIds->isEmpty()) {
            return;
        }

        DB::table('passenger_checkins')->whereIn('itinerary_checkpoint_id', $checkpointIds)->delete();
        DB::table('itinerary_checkpoints')->whereIn('id', $checkpointIds)->delete();
    }
};
