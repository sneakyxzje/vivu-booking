<?php

namespace App\Notifications;

use App\Models\BookingPassenger;
use App\Models\ItineraryCheckpoint;
use App\Models\TourSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PassengerAbsentAtBoundaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TourSchedule $schedule,
        public BookingPassenger $passenger,
        public ItineraryCheckpoint $checkpoint,
        public bool $isFirstCheckpoint,
    ) {
    }

    /**
     * Kênh lưu notification.
     *
     * Hiện tại lưu vào database để admin có thể xem
     * cảnh báo trong hệ thống.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Dữ liệu được lưu vào bảng notifications.
     */
    public function toDatabase(object $notifiable): array
    {
        $boundary = $this->isFirstCheckpoint
            ? 'điểm đón đầu tiên'
            : 'điểm cuối';

        return [
            'type' => 'passenger_absent_boundary',
            'schedule_id' => $this->schedule->id,
            'passenger_id' => $this->passenger->id,
            'passenger_name' => $this->passenger->name,
            'checkpoint_id' => $this->checkpoint->id,
            'checkpoint_name' => $this->checkpoint->name,
            'boundary' => $boundary,
            'message' => sprintf(
                'Khách %s vắng mặt tại %s của chuyến khởi hành #%d.',
                $this->passenger->name,
                $boundary,
                $this->schedule->id,
            ),
        ];
    }
}