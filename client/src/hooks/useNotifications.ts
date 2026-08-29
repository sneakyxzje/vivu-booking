import { useCallback, useEffect, useRef, useState } from "react";
import notificationService from "@/services/notificationService";
import type { AppNotification } from "@/services/notificationService";
import { onNotification } from "@/services/realtime";
import { useAuth } from "@/hooks/useAuth";

/**
 * Hộp thông báo: tải danh sách, và giữ cho nó mới.
 *
 * Dùng chung cho điều hành và hướng dẫn viên. Hai vai khác nhau ở nội dung thông báo chứ không
 * khác ở cách nhận, nên tách thành hai hook chỉ là chép đoạn logic kết nối ra làm hai bản rồi
 * chờ ngày chúng lệch nhau.
 *
 * ## Hai đường, và đường thứ hai luôn có
 *
 * WebSocket là đường nhanh. Nếu mở được kênh thì thông báo mới **chèn thẳng vào đầu danh sách**,
 * không cần gọi lại máy chủ — đó là toàn bộ lợi ích của nó.
 *
 * Nếu không nối được — chưa bật `reverb:start`, tường lửa chặn, chưa cấu hình khoá — thì hook
 * chuyển sang **hỏi lại mỗi 30 giây**. Chậm hơn, nhưng không mất gì.
 *
 * Ranh giới giữa hai đường là `live`, và nó phải là **trạng thái kết nối thật** chứ không phải
 * "đã dựng xong đối tượng Echo". Hai thứ đó khác nhau: pusher-js nhận lệnh, thử lại ngầm mãi mãi
 * và không ném lỗi cho ai, nên lấy mốc "dựng xong" thì Reverb tắt vẫn báo xanh và đường dự phòng
 * không bao giờ bật. Nhờ vậy Reverb chết giữa chừng cũng tự rơi xuống hỏi định kỳ.
 *
 * Điều cố ý KHÔNG làm: chạy cả hai cùng lúc. Vừa nghe kênh vừa hỏi định kỳ thì mỗi thông báo về
 * hai lần và danh sách nhân đôi.
 */

/** Nhịp hỏi lại khi không có WebSocket. Đủ nhanh để không ai chờ, đủ chậm để không quấy máy chủ. */
const NHIP_HOI_LAI = 30_000;

/** Ai có hộp thông báo. Khách chưa có — máy chủ cũng chưa gửi gì cho họ. */
const CO_HOP_THONG_BAO = ["admin", "guide"];

export const useNotifications = () => {
  /*
   * Token lấy từ ngữ cảnh xác thực, không tự đọc localStorage.
   *
   * Bản đầu đọc thẳng `localStorage.getItem("token")` — sai khoá, chỗ lưu thật là `access_token`.
   * Không có gì báo lỗi: hàm trả về null, hiệu ứng thoát sớm, và mất luôn cả WebSocket lẫn nhịp
   * hỏi lại. Màn hình chỉ hiện đúng một dòng "chưa kết nối được".
   *
   * Đọc từ `useAuth()` thì không còn tên khoá nào lặp lại ở đây để mà gõ sai.
   */
  const { user, token } = useAuth();

  const [items, setItems] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(true);
  /** true = đường dây đang nối. false = đang hỏi định kỳ. Hiện lên giao diện để biết đường gỡ. */
  const [live, setLive] = useState(false);

  // Giữ trong ref để hàm nghe kênh không phải khai lại mỗi lần danh sách đổi.
  const daNhan = useRef(new Set<string>());

  const coHop = !!user && CO_HOP_THONG_BAO.includes(user.role);

  const taiLai = useCallback(async () => {
    if (!coHop) {
      setLoading(false);
      return;
    }

    try {
      const data = await notificationService.getNotifications();

      if (data) {
        setItems(data.notifications);
        setUnread(data.unread_count);
        daNhan.current = new Set(data.notifications.map((n) => n.id));
      }
    } catch (err) {
      console.error("Lỗi tải thông báo:", err);
    } finally {
      setLoading(false);
    }
  }, [coHop]);

  useEffect(() => {
    taiLai();
  }, [taiLai]);

  useEffect(() => {
    if (!user || !coHop) return;

    /*
     * Thiếu token thì bỏ qua WebSocket nhưng **vẫn hỏi định kỳ**.
     *
     * Bản đầu thoát hẳn ở đây, tức là một trục trặc của đường nhanh kéo đổ luôn đường dự phòng —
     * đúng thứ mà cả tệp này được viết ra để tránh. Mọi lối rẽ hỏng đều phải rơi xuống nhịp hỏi
     * lại, không lối nào được rơi ra ngoài.
     */
    const dungNghe = token
      ? onNotification(
          token,
          user.id,
          (payload) => {
            const tb = payload as AppNotification;

            // Máy chủ có thể gửi lại cùng một thông báo khi kết nối chập chờn.
            if (!tb?.id || daNhan.current.has(tb.id)) return;

            daNhan.current.add(tb.id);
            setItems((truoc) => [tb, ...truoc]);
            setUnread((truoc) => truoc + 1);
          },
          setLive,
        )
      : null;

    if (!dungNghe) setLive(false);

    return dungNghe ?? undefined;
  }, [user, token, coHop, taiLai]);

  /*
   * Nhịp hỏi lại, bật đúng khi đường dây không nối được.
   *
   * Tách khỏi hiệu ứng trên vì hai việc này đổi theo hai nhịp khác nhau. Trước đây quyết định
   * "nghe hay hỏi" chốt đúng một lần lúc dựng đối tượng Echo — mà dựng được đối tượng không có
   * nghĩa là nối được. Chưa chạy `reverb:start` thì pusher-js cứ thử lại ngầm, không thông báo
   * nào tới, mà nhịp hỏi lại cũng không bao giờ bật. Giờ `live` phản ánh trạng thái thật, nên
   * Reverb chết giữa chừng là chỗ này tự chạy, và Reverb sống lại là nó tự tắt.
   *
   * Chỉ hỏi số chưa đọc chứ không kéo cả danh sách — nhẹ hơn nhiều, và khi số đó nhích lên thì
   * mới đi lấy danh sách thật.
   */
  useEffect(() => {
    if (!coHop || live) return;

    const dinhKy = window.setInterval(async () => {
      try {
        const soMoi = await notificationService.getUnreadCount();

        // So với giá trị mới nhất chứ không so với biến đóng gói lúc khai hiệu ứng.
        setUnread((truoc) => {
          if (soMoi !== truoc) taiLai();
          return soMoi;
        });
      } catch {
        // Máy chủ chưa lên thì lần sau hỏi lại, không cần kêu ca.
      }
    }, NHIP_HOI_LAI);

    return () => window.clearInterval(dinhKy);
  }, [coHop, live, taiLai]);

  const danhDauDaDoc = useCallback(async (id: string) => {
    // Đổi giao diện trước rồi mới gọi máy chủ: bấm vào một dòng thì nó phải mờ đi ngay.
    setItems((truoc) =>
      truoc.map((n) => (n.id === id && !n.read_at ? { ...n, read_at: new Date().toISOString() } : n)),
    );
    setUnread((truoc) => Math.max(0, truoc - 1));

    try {
      await notificationService.markRead(id);
    } catch (err) {
      console.error("Lỗi đánh dấu đã đọc:", err);
      taiLai();
    }
  }, [taiLai]);

  const danhDauTatCa = useCallback(async () => {
    setItems((truoc) => truoc.map((n) => n.read_at ? n : { ...n, read_at: new Date().toISOString() }));
    setUnread(0);

    try {
      await notificationService.markAllRead();
    } catch (err) {
      console.error("Lỗi đánh dấu tất cả:", err);
      taiLai();
    }
  }, [taiLai]);

  return { items, unread, loading, live, taiLai, danhDauDaDoc, danhDauTatCa };
};
