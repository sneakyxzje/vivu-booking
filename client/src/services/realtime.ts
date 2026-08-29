import Echo from "laravel-echo";
import Pusher from "pusher-js";

/**
 * Kết nối WebSocket tới máy chủ Reverb.
 *
 * ## Nguyên tắc: đây là đường NHANH, không phải đường DUY NHẤT
 *
 * Reverb là một tiến trình chạy riêng (`php artisan reverb:start`). Nó có thể chưa bật, có thể
 * chết, có thể bị tường lửa chặn — và không tình huống nào trong ba tình huống ấy được phép làm
 * hỏng màn thông báo. Nguồn sự thật là bảng `notifications` trên máy chủ; WebSocket chỉ tiết kiệm
 * cho ta độ trễ giữa hai lần hỏi.
 *
 * Nên mọi hàm ở đây **nuốt lỗi và trả về null**. Bên gọi thấy null thì tự chuyển sang hỏi định kỳ.
 * Ném lỗi ra ngoài chỉ khiến một tiến trình nền không chạy làm vỡ cả trang quản trị.
 */

type EchoInstance = InstanceType<typeof Echo>;

let echo: EchoInstance | null = null;

/**
 * Mở kết nối, hoặc trả về kết nối đang có.
 *
 * Token truyền vào chứ không đọc từ localStorage bên trong: chỗ lưu token là việc của tầng xác
 * thực, và đọc lén nó ở đây tạo thêm một nơi phải sửa khi cách lưu đổi.
 */
export const connectRealtime = (token: string): EchoInstance | null => {
  if (echo) return echo;

  const key = import.meta.env.VITE_REVERB_APP_KEY;

  // Chưa cấu hình thì im lặng bỏ qua. Đây là trạng thái hợp lệ, không phải lỗi.
  if (!key) return null;

  try {
    // laravel-echo tìm Pusher ở phạm vi toàn cục thay vì nhận qua tham số.
    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    echo = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: import.meta.env.VITE_REVERB_HOST ?? "127.0.0.1",
      wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "http") === "https",
      enabledTransports: ["ws", "wss"],
      /*
       * Điểm xác thực kênh riêng nằm dưới tiền tố `api` với middleware `auth:sanctum` — xem
       * bootstrap/app.php. Mặc định của Laravel là `/broadcasting/auth` với middleware `web`,
       * mà giao diện này đăng nhập bằng token chứ không bằng phiên.
       */
      authEndpoint: `${import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000/api"}/broadcasting/auth`,
      auth: { headers: { Authorization: `Bearer ${token}` } },
    });

    return echo;
  } catch (err) {
    console.warn("Không mở được kết nối realtime, chuyển sang hỏi định kỳ.", err);
    echo = null;
    return null;
  }
};

export const disconnectRealtime = () => {
  try {
    echo?.disconnect();
  } catch {
    // Ngắt kết nối hỏng thì cũng chẳng còn gì để cứu.
  }

  echo = null;
};

/**
 * Nghe thông báo mới của một người dùng.
 *
 * Trả về hàm dọn dẹp, hoặc `null` khi không kết nối được — đó chính là tín hiệu bên gọi dùng để
 * bật chế độ hỏi định kỳ.
 */
export const onAdminAlert = (
  token: string,
  userId: number,
  handler: (payload: unknown) => void,
): (() => void) | null => {
  const client = connectRealtime(token);

  if (!client) return null;

  try {
    /*
     * Tên kênh và tên sự kiện do Laravel đặt, không phải ta chọn: thông báo dạng broadcast luôn
     * đi vào `App.Models.User.{id}` với tên sự kiện có dấu chấm ở đầu.
     */
    const channel = client.private(`App.Models.User.${userId}`);
    channel.notification(handler);

    return () => {
      try {
        client.leave(`App.Models.User.${userId}`);
      } catch {
        // Rời kênh hỏng thì bỏ qua: trang đang đóng.
      }
    };
  } catch (err) {
    console.warn("Không đăng ký được kênh realtime, chuyển sang hỏi định kỳ.", err);
    return null;
  }
};
