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
 * Nếu không mở được — chưa bật `reverb:start`, tường lửa chặn, chưa cấu hình khoá — thì hook
 * chuyển sang **hỏi lại mỗi 30 giây**. Chậm hơn, nhưng không mất gì.
 *
 * Điều cố ý KHÔNG làm: chạy cả hai cùng lúc. Vừa nghe kênh vừa hỏi định kỳ thì mỗi thông báo về
 * hai lần và danh sách nhân đôi.
 */

/** Nhịp hỏi lại khi không có WebSocket. Đủ nhanh để không ai chờ, đủ chậm để không quấy máy chủ. */
const NHIP_HOI_LAI = 30_000;

/** Ai có hộp thông báo. Khách chưa có — máy chủ cũng chưa gửi gì cho họ. */
const CO_HOP_THONG_BAO = ["admin", "guide"];

export const useNotifications = () => {
  const { user } = useAuth();

  const [items, setItems] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(true);
  /** true = đang nghe WebSocket. false = đang hỏi định kỳ. Hiện lên giao diện để biết đường gỡ. */
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

    const token = localStorage.getItem("token");
    if (!token) return;

    const dungNghe = onNotification(token, user.id, (payload) => {
      const tb = payload as AppNotification;

      // Máy chủ có thể gửi lại cùng một thông báo khi kết nối chập chờn.
      if (!tb?.id || daNhan.current.has(tb.id)) return;

      daNhan.current.add(tb.id);
      setItems((truoc) => [tb, ...truoc]);
      setUnread((truoc) => truoc + 1);
    });

    if (dungNghe) {
      setLive(true);
      return dungNghe;
    }

    /*
     * Không mở được kênh: hỏi lại định kỳ.
     *
     * Chỉ hỏi số chưa đọc chứ không kéo cả danh sách — nhẹ hơn nhiều, và khi số đó nhích lên thì
     * mới đi lấy danh sách thật.
     */
    setLive(false);

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
  }, [user, coHop, taiLai]);

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
