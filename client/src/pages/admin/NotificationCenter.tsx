import { Link } from "react-router-dom";
import { useAdminNotifications } from "@/hooks/useAdminNotifications";
import { formatDateTime } from "@/utils/format";

/**
 * Hộp thông báo của điều hành.
 *
 * Ba loại việc, phân biệt bằng màu chứ không bằng nhãn: từ chối chuyến, xin bàn giao, sự cố
 * nghiêm trọng. Nhãn loại không thêm gì — tiêu đề đã nói rõ chuyện gì, và thêm một chữ "Từ chối
 * chuyến" bên cạnh câu "Phạm Hoàng Long từ chối chuyến #11" chỉ là nói hai lần.
 */

const mauTheoLoai: Record<string, string> = {
  guide_declined: "border-l-rose-500",
  handover_requested: "border-l-amber-500",
  incident_reported: "border-l-red-600",
};

export default function NotificationCenter() {
  const { items, unread, loading, live, danhDauDaDoc, danhDauTatCa } = useAdminNotifications();

  return (
    <div className="max-w-3xl space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">Thông báo</h1>
          <p className="mt-1 text-sm text-gray-500">
            Việc cần biết ngay: hướng dẫn viên từ chối chuyến, xin bàn giao, hoặc báo sự cố nghiêm
            trọng.
          </p>
        </div>

        {unread > 0 && (
          <button
            type="button"
            onClick={danhDauTatCa}
            className="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
          >
            Đánh dấu tất cả đã đọc ({unread})
          </button>
        )}
      </div>

      {/*
        Nói thẳng đang chạy ở chế độ nào.

        Khi WebSocket không kết nối được, thông báo vẫn tới nhưng chậm hơn — và nếu màn hình không
        nói ra thì người dùng tưởng hệ thống hỏng, còn người sửa thì không biết bắt đầu từ đâu.
      */}
      <div
        className={`rounded-lg px-3 py-2 text-xs ${
          live ? "bg-emerald-50 text-emerald-800" : "bg-gray-100 text-gray-600"
        }`}
      >
        {live ? (
          <>Đang nhận tức thì qua WebSocket.</>
        ) : (
          <>
            Chưa kết nối được WebSocket nên đang tự làm mới mỗi 30 giây. Thông báo vẫn đầy đủ, chỉ
            chậm hơn. Muốn tức thì thì chạy <code className="font-mono">php artisan reverb:start</code>.
          </>
        )}
      </div>

      {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

      {!loading && items.length === 0 && (
        <p className="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
          Chưa có thông báo nào.
        </p>
      )}

      <div className="space-y-2">
        {items.map((tb) => {
          const noiDung = (
            <>
              <div className="flex flex-wrap items-baseline gap-2">
                <span className={`text-sm ${tb.read_at ? "font-medium text-gray-700" : "font-bold text-gray-950"}`}>
                  {tb.title}
                </span>
                {!tb.read_at && (
                  <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500" />
                )}
                <span className="ml-auto text-xs text-gray-400">
                  {formatDateTime(tb.created_at)}
                </span>
              </div>
              <p className="mt-1 text-xs leading-relaxed text-gray-600">{tb.body}</p>
            </>
          );

          const lop = `block w-full rounded-xl border border-l-4 p-4 text-left transition-colors ${
            mauTheoLoai[tb.kind] ?? "border-l-gray-300"
          } ${tb.read_at ? "border-gray-100 bg-white" : "border-gray-200 bg-primary-50/30"}`;

          // Có màn hình xử lý thì cả thẻ là một liên kết; không có thì chỉ bấm để đánh dấu đã đọc.
          return tb.url ? (
            <Link key={tb.id} to={tb.url} onClick={() => danhDauDaDoc(tb.id)} className={lop}>
              {noiDung}
            </Link>
          ) : (
            <button key={tb.id} type="button" onClick={() => danhDauDaDoc(tb.id)} className={lop}>
              {noiDung}
            </button>
          );
        })}
      </div>
    </div>
  );
}
