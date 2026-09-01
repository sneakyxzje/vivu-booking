<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Một việc ai đó cần biết ngay — điều hành hoặc hướng dẫn viên.
 *
 * ## Vì sao chỉ có MỘT lớp cho mọi loại việc, và cho cả hai vai
 *
 * Bảy loại việc dưới đây khác nhau về nghiệp vụ nhưng với người nhận thì giống hệt: một dòng nói
 * *chuyện gì, ai, ở chuyến nào*, bấm vào thì mở đúng màn hình xử lý. Dựng bảy lớp thông báo để
 * rồi cả bảy trả về cùng một hình dạng là đúng kiểu bộ máy dự án này đã bỏ bớt.
 *
 * Khác biệt duy nhất có ý nghĩa là `kind`, và nó chỉ dùng để chọn màu.
 *
 * ## Hai đường giao, cùng một nội dung
 *
 *   - `database` — luôn ghi. Đây là **nguồn sự thật**: mở màn thông báo lúc nào cũng thấy đủ.
 *   - `broadcast` — đẩy tức thì qua WebSocket, nếu tiến trình Reverb đang chạy.
 *
 * Thứ tự ấy là chủ ý. Không có Reverb thì thông báo **vẫn tới**, chỉ mất tính tức thời — màn hình
 * tự hỏi lại định kỳ. Một tính năng chết hẳn khi quên bật một tiến trình là thứ không nên mang đi
 * trình bày.
 */
class Alert extends Notification
{
    /* --- Việc điều hành cần biết --------------------------------------------------------- */

    public const TU_CHOI_CHUYEN = 'guide_declined';
    public const XIN_BAN_GIAO = 'handover_requested';
    public const SU_CO = 'incident_reported';

    /* --- Việc cả hai vai đều cần biết ---------------------------------------------------- */

    /**
     * Hạn chốt danh sách của một chuyến vừa bị dời.
     *
     * Gửi cho hướng dẫn viên phụ trách chuyến ấy: họ là người cầm danh sách đi gặp nhà cung cấp,
     * không biết mốc đã dịch thì vẫn hứa với khách theo mốc cũ.
     */
    public const HAN_CHOT_DOI = 'deadline_changed';

    /* --- Việc hướng dẫn viên cần biết ---------------------------------------------------- */

    /** Được phân công một chuyến mới. */
    public const PHAN_CONG = 'assigned';

    /** Vừa nhận một đoàn từ người khác — gấp nhất phía hướng dẫn viên, đoàn đang trên đường. */
    public const NHAN_BAN_GIAO = 'handover_received';

    /** Phiếu xin bàn giao của mình đã được xử lý, dù theo hướng nào. */
    public const PHIEU_DA_XU_LY = 'handover_closed';

    /** Điều hành đã quyết phương án cho sự cố mình báo — thứ mình đọc lại cho khách. */
    public const SU_CO_DA_QUYET = 'incident_resolved';

    use Queueable;

    /**
     * @param  string  $kind  Một trong các hằng số ở trên. Chỉ quyết định màu.
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
     *
     * ## Vì sao ép `sync`
     *
     * Mặc định Laravel **xếp hàng** việc đẩy broadcast: `BroadcastManager::queue()` đưa nó vào
     * hàng đợi trừ khi sự kiện tự nhận là gửi ngay. Với `QUEUE_CONNECTION=database` mà không có
     * `queue:work` chạy kèm thì bản ghi vào cơ sở dữ liệu ngay, còn cú đẩy nằm im trong bảng
     * `jobs` — nên phải tải lại trang mới thấy thông báo, đúng cái tình huống WebSocket sinh ra để
     * xoá bỏ.
     *
     * Ép `sync` để việc đẩy chạy ngay trong tiến trình đang xử lý yêu cầu. Cái giá là một lượt gọi
     * HTTP nội bộ tới Reverb, vài mili giây; đổi lại không phải nhớ bật thêm một tiến trình nền
     * nữa, và hỏng thì `Notifier` đã nuốt lỗi rồi — bản ghi trong cơ sở dữ liệu vẫn còn nguyên vì
     * `via()` đặt `database` trước `broadcast`.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable) + [
            'id' => $this->id,
            'created_at' => now()->toDateTimeString(),
            'read_at' => null,
        ]))->onConnection('sync');
    }
}
