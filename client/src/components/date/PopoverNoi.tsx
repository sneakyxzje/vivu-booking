import React, { useEffect, useLayoutEffect, useRef } from "react";
import { createPortal } from "react-dom";

/**
 * Bảng nổi neo vào một nút, vẽ ở `document.body` chứ không nằm trong cây DOM của nút.
 *
 * ## Vì sao phải ra khỏi cây DOM
 *
 * Bảng chọn ngày trước đây là `absolute` ngay cạnh nút. Cách ấy chỉ đúng khi không tổ tiên nào
 * cắt nội dung — mà thẻ chuyến khởi hành ở trang tạo tour lại có `overflow-hidden` để bo góc
 * viền trái. Lịch mở ra bị xén mất nửa dưới: không bấm được ngày cuối tháng, cũng không thấy
 * nút "Chọn". Cùng lý do ấy, một bảng mở ở cuối trang thì tràn xuống dưới mép màn hình.
 *
 * Cổng (portal) đưa bảng thẳng ra `body`, nên không `overflow` nào cắt được nữa. Đổi lại, bảng
 * không còn trôi theo nút khi cuộn, nên tọa độ phải tự tính và tự cập nhật.
 *
 * ## Quy tắc đặt chỗ
 *
 * Ưu tiên mở xuống dưới. Không đủ chỗ dưới mà trên lại đủ thì lật lên. Không đủ cả hai thì dán
 * vào mép và cho cuộn bên trong — thà cuộn còn hơn mất nút bấm.
 *
 * ## Tọa độ ghi thẳng vào phần tử, không đi qua state
 *
 * Đo xong gán luôn `style.top`. Cho tọa độ vào state thì mỗi lần cuộn một pixel là một lượt vẽ
 * lại toàn bộ nội dung bảng, mà nội dung ấy không đổi — chỉ vị trí đổi. Đây đúng là việc effect
 * sinh ra để làm: đồng bộ một hệ thống bên ngoài (bố cục DOM) theo trạng thái của React.
 */

interface Props {
  mo: boolean;
  /** Phần tử neo — thường là nút mở bảng. Bảng bám mép dưới (hoặc trên) của nó. */
  neo: React.RefObject<HTMLElement | null>;
  onDong: () => void;
  children: React.ReactNode;
  nhan?: string;
  /** Canh mép trái của bảng theo mép trái nút (mặc định), hoặc mép phải theo mép phải nút. */
  canhLe?: "trai" | "phai";
  className?: string;
}

/** Khoảng hở giữa bảng và nút, và giữa bảng với mép màn hình. */
const HO = 8;

export const PopoverNoi: React.FC<Props> = ({
  mo,
  neo,
  onDong,
  children,
  nhan,
  canhLe = "trai",
  className = "",
}) => {
  const bang = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    if (!mo) return;

    const datCho = () => {
      const nutEl = neo.current;
      const bangEl = bang.current;
      if (!nutEl || !bangEl) return;

      const nut = nutEl.getBoundingClientRect();
      const rongMan = document.documentElement.clientWidth;
      const caoMan = document.documentElement.clientHeight;

      // Đo chiều cao tự nhiên bằng `scrollHeight`: `offsetHeight` đã bị `maxHeight` của lần đặt
      // trước kẹp lại, dùng nó thì số đo tự nhân bản mãi và bảng cứ thấp dần.
      const cao = Math.min(bangEl.scrollHeight, caoMan - HO * 2);
      const rong = bangEl.offsetWidth;

      const choDuoi = caoMan - nut.bottom - HO;
      const choTren = nut.top - HO;
      const latLen = choDuoi < cao && choTren > choDuoi;

      const top = latLen
        ? Math.max(HO, nut.top - HO - cao)
        : Math.min(nut.bottom + HO, Math.max(HO, caoMan - HO - cao));

      const leGoc = canhLe === "phai" ? nut.right - rong : nut.left;
      const left = Math.min(Math.max(HO, leGoc), Math.max(HO, rongMan - rong - HO));

      bangEl.style.top = `${top}px`;
      bangEl.style.left = `${left}px`;
      bangEl.style.maxHeight = `${caoMan - HO * 2}px`;
      bangEl.style.visibility = "visible";
    };

    datCho();

    // Cuộn ở BẤT KỲ tầng nào cũng làm nút đi chỗ khác, nên bắt ở pha capture để nghe được cả
    // những khung cuộn lồng bên trong, không chỉ cửa sổ.
    window.addEventListener("scroll", datCho, true);
    window.addEventListener("resize", datCho);

    // Nội dung tự đổi kích thước (đổi tháng, bật ô giờ) thì chỗ đã đặt không còn đúng.
    const theoDoi = new ResizeObserver(datCho);
    if (bang.current) theoDoi.observe(bang.current);

    return () => {
      window.removeEventListener("scroll", datCho, true);
      window.removeEventListener("resize", datCho);
      theoDoi.disconnect();
    };
  }, [mo, canhLe, neo]);

  useEffect(() => {
    if (!mo) return;

    const bamNgoai = (e: MouseEvent) => {
      const dich = e.target as Node;
      if (bang.current?.contains(dich)) return;
      if (neo.current?.contains(dich)) return;
      onDong();
    };
    const bamEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onDong();
    };

    document.addEventListener("mousedown", bamNgoai);
    document.addEventListener("keydown", bamEsc);
    return () => {
      document.removeEventListener("mousedown", bamNgoai);
      document.removeEventListener("keydown", bamEsc);
    };
  }, [mo, neo, onDong]);

  if (!mo) return null;

  return createPortal(
    <div
      ref={bang}
      role="dialog"
      aria-label={nhan}
      // Ẩn ở lượt vẽ đầu: lúc ấy chưa đo được nút nên chưa biết đặt vào đâu, hiện ra thì bảng
      // nháy một cái ở góc trên bên trái. `useLayoutEffect` đặt lại trước khi trình duyệt vẽ.
      style={{ position: "fixed", top: 0, left: 0, visibility: "hidden" }}
      className={`z-[100] overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white shadow-xl ${className}`}
    >
      {children}
    </div>,
    document.body,
  );
};

export default PopoverNoi;
