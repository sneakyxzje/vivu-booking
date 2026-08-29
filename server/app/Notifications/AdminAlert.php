<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Một việc điều hành cần biết ngay.
 *
 * ## Vì sao chỉ có MỘT lớp cho mọi loại việc
 *
 * Hướng dẫn viên từ chối chuyến, xin bàn giao, báo sự cố nghiêm trọng — ba chuyện khác nhau,
 * nhưng với người nhận thì chúng giống hệt: một dòng nói *chuyện gì, ai, ở chuyến nào*, bấm vào
 * thì mở đúng màn hình xử lý. Dựng ba lớp thông báo cho ba việc chỉ để rồi cả ba trả về cùng một
 * hình dạng là đúng kiểu bộ máy dự án này vừa bỏ bớt.
 *
 * Khác biệt duy nhất có ý nghĩa là `kind`, và nó chỉ dùng để chọn màu và biểu tượng.
 *
 * ## Hai đường giao, cùng một nội dung
 *
 *   - `database` — luôn ghi. Đây là **nguồn sự thật**: mở màn thông báo lúc nào cũng thấy đủ.
 *   - `broadcast` — đẩy tức thì qua WebSocket, nếu tiến trình Reverb đang chạy.
 *
 * Thứ tự ấy là chủ ý. Không có Reverb thì thông báo **vẫn tới**, chỉ mất tính tức thời — màn
 * hình tự hỏi lại định kỳ. Một tính năng chết hẳn khi quên bật một tiến trình là thứ không nên
 * mang đi trình bày.
 */
class AdminAlert extends Notification
{
    use Queueable;

    public const TU_CHOI_CHUYEN = 'guide_declined';
    public const XIN_BAN_GIAO = 'handover_requested';
    public const SU_CO = 'incident_reported';

    /**
     * @param  string  $kind  Một trong ba hằng số ở trên. Chỉ quyết định màu và biểu tượng.
     * @param  string  $title  Một dòng, đọc là hiểu chuyện gì. Không cần mở ra mới biết.
     * @param  string  $body  Chi tiết: ai, chuyến nào, lý do họ viết.
     * @param  string|null  $url  Đường dẫn tới màn hình xử lý việc này.
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    /**
     * Bản đẩy qua WebSocket mang đúng nội dung bản ghi vào cơ sở dữ liệu, cộng `id` và `created_at`.
     *
     * Cộng thêm hai trường ấy để màn hình chèn thẳng dòng mới vào danh sách mà không phải gọi lại
     * máy chủ. Thiếu chúng thì mỗi lần có thông báo lại là một lượt tải toàn bộ danh sách, tức
     * WebSocket chỉ tiết kiệm được đúng độ trễ chứ không tiết kiệm truy vấn nào.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + [
            'id' => $this->id,
            'created_at' => now()->toDateTimeString(),
            'read_at' => null,
        ]);
    }

    /** Tên sự kiện phía client lắng nghe. Đặt tên gọn để bên kia khỏi phải viết tên lớp đầy đủ. */
    public function broadcastType(): string
    {
        return 'admin.alert';
    }
}
