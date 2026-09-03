import { useCallback, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { Bell, CheckCheck } from "lucide-react";
import { PopoverNoi } from "@/components/date/PopoverNoi";
import { useNotifications } from "@/hooks/useNotifications";
import type { AppNotification } from "@/services/notificationService";
import { formatDateTime } from "@/utils/format";

/**
 * Chuông thông báo: bấm vào mở bảng đọc ngay tại chỗ, không rời trang.
 *
 * ## Vì sao đổi khỏi việc chuyển sang màn riêng
 *
 * Chú thích cũ ở `AdminLayout` từng biện hộ cho việc chuông chỉ mang con số: "nhồi thêm một bản
 * sao rút gọn vào thanh trên cùng là hai chỗ cùng hiển thị một dữ liệu, và hai chỗ ấy sớm muộn
 * lệch nhau". Nỗi lo ấy đúng khi hai chỗ tự đi lấy dữ liệu riêng — nhưng cả hai đều đọc
 * `useNotifications()`, tức cùng một danh sách, cùng một số chưa đọc, cùng một hàm đánh dấu đã
 * đọc. Đánh dấu ở bảng này thì màn riêng đổi theo, và ngược lại.
 *
 * Cái giá của việc chuyển trang thì có thật: điều hành đang dở một việc — duyệt một yêu cầu hủy,
 * điền một khoản thu — mà muốn liếc xem có gì mới thì phải rời khỏi trang đang làm rồi tự bấm
 * quay lại. Một bảng nổi trả lời câu "có gì mới không" mà không bắt trả giá ấy.
 *
 * ## Cắt bớt nội dung dài
 *
 * Bảng này để **liếc**, không để đọc kỹ: mỗi dòng một tiêu đề và hai dòng nội dung, quá thì cắt
 * bằng dấu ba chấm. Ai cần đọc đủ thì bấm vào dòng — nó dẫn tới đúng màn hình xử lý việc đó, hoặc
 * tới màn thông báo đầy đủ.
 *
 * Cắt bằng `line-clamp` của CSS chứ không cắt chuỗi trong JavaScript: cắt chuỗi phải đoán bao
 * nhiêu ký tự thì vừa một dòng, mà con số ấy đổi theo bề rộng màn hình và theo cỡ chữ người dùng
 * đặt trong trình duyệt. CSS đo thật rồi mới cắt, và vẫn giữ nguyên chữ đầy đủ cho việc tìm kiếm
 * lẫn trình đọc màn hình.
 */

/** Bảng chỉ mang ngần này dòng. Đủ để biết có gì mới, không biến thành một màn hình thứ hai. */
const SO_DONG_TOI_DA = 6;

/** `kind` chỉ quyết định màu chấm; nội dung thật nằm ở `title` và `body`. */
const MAU_CHAM: Record<string, string> = {
  guide_declined: "bg-rose-500",
  handover_requested: "bg-amber-500",
  incident_reported: "bg-rose-500",
  assigned: "bg-indigo-500",
  handover_received: "bg-amber-500",
  handover_closed: "bg-emerald-500",
  incident_resolved: "bg-emerald-500",
};

type Props = {
  /** Đường tới màn thông báo đầy đủ — khác nhau giữa điều hành và hướng dẫn viên. */
  trangDayDu: string;
  className?: string;
};

export const NotificationBell = ({ trangDayDu, className = "" }: Props) => {
  const { items, unread, loading, danhDauDaDoc, danhDauTatCa } =
    useNotifications();
  const [mo, setMo] = useState(false);
  const nut = useRef<HTMLButtonElement>(null);

  // `useCallback` vì `PopoverNoi` gắn và gỡ trình nghe sự kiện theo tham chiếu hàm này.
  const dong = useCallback(() => setMo(false), []);

  const dsHien = items.slice(0, SO_DONG_TOI_DA);

  /** Bấm một dòng: đánh dấu đã đọc rồi đóng bảng. Điều hướng do thẻ Link tự lo. */
  const bamDong = (tb: AppNotification) => {
    if (!tb.read_at) danhDauDaDoc(tb.id);
    setMo(false);
  };

  return (
    <>
      <button
        ref={nut}
        type="button"
        onClick={() => setMo((v) => !v)}
        aria-haspopup="dialog"
        aria-expanded={mo}
        aria-label={
          unread > 0 ? `Thông báo, ${unread} chưa đọc` : "Thông báo"
        }
        className={`relative rounded-md p-2 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 ${className}`}
      >
        <Bell className="h-5 w-5" />

        {unread > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
            {unread > 99 ? "99+" : unread}
          </span>
        )}
      </button>

      <PopoverNoi
        mo={mo}
        neo={nut}
        onDong={dong}
        nhan="Thông báo"
        canhLe="phai"
        className="w-[22rem] max-w-[calc(100vw-1rem)]"
      >
        <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
          <p className="text-sm font-bold text-gray-900">
            Thông báo
            {unread > 0 && (
              <span className="ml-1.5 text-xs font-semibold text-rose-600">
                {unread} chưa đọc
              </span>
            )}
          </p>

          {unread > 0 && (
            <button
              type="button"
              onClick={danhDauTatCa}
              className="flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-semibold text-gray-500 transition-colors hover:bg-gray-50 hover:text-indigo-600"
            >
              <CheckCheck className="h-3.5 w-3.5" />
              Đọc tất cả
            </button>
          )}
        </div>

        {loading && dsHien.length === 0 ? (
          <p className="px-4 py-8 text-center text-xs text-gray-400">
            Đang tải thông báo...
          </p>
        ) : dsHien.length === 0 ? (
          <p className="px-4 py-8 text-center text-xs text-gray-400">
            Chưa có thông báo nào.
          </p>
        ) : (
          <ul className="divide-y divide-gray-100">
            {dsHien.map((tb) => {
              const noiDung = (
                <>
                  <span
                    className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                      MAU_CHAM[tb.kind] ?? "bg-gray-300"
                    } ${tb.read_at ? "opacity-30" : ""}`}
                  />

                  <span className="min-w-0 flex-1">
                    {/*
                      `line-clamp` cắt theo chiều rộng thật của bảng: tiêu đề một dòng, nội dung
                      hai dòng. Chữ đầy đủ vẫn nằm trong DOM nên Ctrl+F và trình đọc màn hình vẫn
                      thấy, chỉ phần nhìn bị cắt.
                    */}
                    <span
                      className={`line-clamp-1 text-xs ${
                        tb.read_at
                          ? "font-medium text-gray-500"
                          : "font-bold text-gray-900"
                      }`}
                    >
                      {tb.title}
                    </span>
                    <span className="mt-0.5 line-clamp-2 block text-xs leading-relaxed text-gray-500">
                      {tb.body}
                    </span>
                    <span className="mt-1 block text-[10px] text-gray-400">
                      {formatDateTime(tb.created_at)}
                    </span>
                  </span>
                </>
              );

              const lop = `flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors hover:bg-gray-50 ${
                tb.read_at ? "" : "bg-indigo-50/40"
              }`;

              /*
               * Thông báo có `url` thì cả dòng là một liên kết; không có thì là một nút chỉ để
               * đánh dấu đã đọc. Bọc mọi dòng trong thẻ Link rồi trỏ về chính trang đang đứng sẽ
               * làm trình duyệt nạp lại vô cớ.
               */
              return (
                <li key={tb.id}>
                  {tb.url ? (
                    <Link to={tb.url} onClick={() => bamDong(tb)} className={lop}>
                      {noiDung}
                    </Link>
                  ) : (
                    <button
                      type="button"
                      onClick={() => bamDong(tb)}
                      className={lop}
                    >
                      {noiDung}
                    </button>
                  )}
                </li>
              );
            })}
          </ul>
        )}

        <div className="border-t border-gray-100 px-4 py-2.5 text-center">
          <Link
            to={trangDayDu}
            onClick={dong}
            className="text-xs font-bold text-indigo-600 hover:underline"
          >
            {items.length > dsHien.length
              ? `Xem tất cả (${items.length})`
              : "Xem tất cả thông báo"}
          </Link>
        </div>
      </PopoverNoi>
    </>
  );
};

export default NotificationBell;
