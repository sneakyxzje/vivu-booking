import React, { useCallback, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Settings2 } from "lucide-react";

export interface ActionItem {
  label: string;
  onClick: () => void;
  icon?: React.ReactNode;
  variant?: "danger" | "default" | "success" | "warning";
  /** Ghi chú một dòng dưới nhãn, cho hành động cần giải thích trước khi bấm. */
  hint?: string;
  disabled?: boolean;
}

interface TableActionsProps {
  id: string | number;
  actions: ActionItem[];
  /** Nhãn đọc màn hình cho nút mở, mặc định "Thao tác". */
  label?: string;
}

/** Menu neo theo nút, tọa độ tính bằng pixel màn hình vì nó nằm ngoài cây DOM của bảng. */
interface ViTriMenu {
  /** Mở xuống thì đặt mép trên; mở lên thì đặt mép dưới. Luôn có đúng một trong hai. */
  top?: number;
  bottom?: number;
  right: number;
  maxHeight: number;
  maxWidth: number;
}

/** Khoảng hở với mép màn hình, và giữa nút với menu. */
const LE = 8;
const KHE = 6;

/**
 * Chiều cao menu, ước lượng từ chính danh sách hành động.
 *
 * Chỉ dùng để quyết định mở lên hay mở xuống, nên lệch vài pixel không sao: vị trí thật neo theo
 * mép trên hoặc mép dưới của menu, trình duyệt tự tính phần còn lại.
 */
const uocChieuCao = (actions: ActionItem[]): number => {
  const CAO_MOT_DONG = 44;
  const CAO_THEM_KHI_CO_GHI_CHU = 18;
  const DEM_TREN_DUOI = 12;
  const DUONG_KE = 13;

  const coDuongKe = actions.some((a, i) => a.variant === "danger" && i > 0);

  return (
    DEM_TREN_DUOI +
    (coDuongKe ? DUONG_KE : 0) +
    actions.reduce(
      (cao, a) => cao + CAO_MOT_DONG + (a.hint ? CAO_THEM_KHI_CO_GHI_CHU : 0),
      0,
    )
  );
};

const tinhViTri = (o: DOMRect, caoUoc: number): ViTriMenu => {
  const chongDuoi = window.innerHeight - o.bottom - KHE - LE;
  const chongTren = o.top - KHE - LE;

  // Ưu tiên mở xuống. Chỉ lật lên khi dưới không đủ chỗ mà trên thì rộng hơn — lật khi không cần
  // làm menu nhảy chỗ giữa các hàng của cùng một bảng, đọc rất khó chịu.
  const moLen = chongDuoi < caoUoc && chongTren > chongDuoi;

  return {
    top: moLen ? undefined : o.bottom + KHE,
    bottom: moLen ? window.innerHeight - o.top + KHE : undefined,
    // Canh mép phải theo nút. Neo bằng `right` chứ không `left` nên không cần biết menu rộng bao
    // nhiêu, và cột thao tác thì luôn nằm sát phải bảng.
    right: Math.max(LE, window.innerWidth - o.right),
    maxHeight: Math.max(120, moLen ? chongTren : chongDuoi),
    maxWidth: Math.max(200, o.right - LE),
  };
};

/**
 * Cụm thao tác của một hàng bảng, gói sau một nút bánh răng.
 *
 * Trước đây mỗi hàng trải hết nút ra cạnh nhau — có hàng tới tám cái. Hệ quả là cột thao tác
 * rộng hơn cả cột dữ liệu, và mắt phải đọc cả tám nhãn mới tìm ra cái cần bấm. Gói lại sau một
 * biểu tượng thì hàng ngắn đi, còn khi mở ra thì các hành động xếp dọc nên đọc theo nhãn nhanh
 * hơn nhiều so với đọc ngang.
 *
 * Nguy hiểm luôn nằm cuối và có đường kẻ tách: hủy chuyến không được đứng cạnh xem danh sách.
 *
 * ## Vì sao menu vẽ ra ngoài bảng
 *
 * Menu từng là `absolute` ngay trong ô của hàng. Mọi bảng quản trị đều bọc trong `overflow-hidden`
 * cộng `overflow-x-auto`, mà CSS quy định một trục đã cắt thì trục kia không thể là `visible` —
 * nên menu của mấy hàng cuối bị xén, và phần thò ra sinh thêm thanh cuộn dọc. Người dùng bấm vào
 * nút thao tác rồi phải cuộn xuống mới thấy menu, cuộn xong thì hàng mình vừa bấm đã trôi khỏi
 * tầm mắt.
 *
 * Nay menu vẽ thẳng vào `document.body` qua portal và định vị bằng `position: fixed` theo tọa độ
 * của nút. Không ô nào cắt được nó nữa, và nó tự lật lên khi hàng nằm sát đáy màn hình.
 */
export const TableActions: React.FC<TableActionsProps> = ({ actions, label = "Thao tác" }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [viTri, setViTri] = useState<ViTriMenu | null>(null);
  const nutRef = useRef<HTMLButtonElement>(null);
  const caoUocRef = useRef(0);

  const dongMenu = useCallback(() => {
    setIsOpen(false);
    setViTri(null);
  }, []);

  /** Tính lại tọa độ từ vị trí hiện tại của nút. Gọi lúc mở, và mỗi khi trang cuộn hay đổi cỡ. */
  const neoTheoNut = useCallback(() => {
    const nut = nutRef.current;
    if (!nut) return;

    const o = nut.getBoundingClientRect();

    // Nút đã cuộn khuất khỏi màn hình thì menu đứng chơ vơ giữa trang, đóng luôn cho gọn.
    if (o.bottom < 0 || o.top > window.innerHeight) {
      dongMenu();
      return;
    }

    setViTri(tinhViTri(o, caoUocRef.current));
  }, [dongMenu]);

  const moMenu = () => {
    const nut = nutRef.current;
    if (!nut) return;

    caoUocRef.current = uocChieuCao(actions);
    setViTri(tinhViTri(nut.getBoundingClientRect(), caoUocRef.current));
    setIsOpen(true);
  };

  // Phím Esc đóng menu — bàn phím phải thoát ra được, không chỉ chuột.
  useEffect(() => {
    if (!isOpen) return;

    const khiNhanPhim = (e: KeyboardEvent) => {
      if (e.key === "Escape") dongMenu();
    };

    document.addEventListener("keydown", khiNhanPhim);
    return () => document.removeEventListener("keydown", khiNhanPhim);
  }, [isOpen, dongMenu]);

  /*
   * Bám theo nút khi trang cuộn.
   *
   * `capture: true` để bắt cả những lần cuộn bên trong bảng hoặc trong hộp thoại, không riêng cuộn
   * cửa sổ — sự kiện cuộn của phần tử con không nổi bọt lên window.
   */
  useEffect(() => {
    if (!isOpen) return;

    window.addEventListener("scroll", neoTheoNut, true);
    window.addEventListener("resize", neoTheoNut);

    return () => {
      window.removeEventListener("scroll", neoTheoNut, true);
      window.removeEventListener("resize", neoTheoNut);
    };
  }, [isOpen, neoTheoNut]);

  if (actions.length === 0) return null;

  return (
    <div className="inline-block text-left">
      <button
        ref={nutRef}
        type="button"
        onClick={() => (isOpen ? dongMenu() : moMenu())}
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={isOpen}
        title={label}
        className={`w-9 h-9 flex items-center justify-center rounded-full border transition-colors cursor-pointer ${
          isOpen
            ? "border-ink bg-surface-strong text-ink"
            : "border-hairline bg-canvas text-muted hover:text-ink hover:bg-surface-soft"
        }`}
      >
        <Settings2 className="w-4 h-4" />
      </button>

      {isOpen &&
        viTri &&
        createPortal(
          <>
            {/* Bấm ra ngoài thì đóng. */}
            <div className="fixed inset-0 z-90 cursor-default" onClick={dongMenu} />

            <div
              role="menu"
              style={{
                position: "fixed",
                top: viTri.top,
                bottom: viTri.bottom,
                right: viTri.right,
                maxHeight: viTri.maxHeight,
                maxWidth: viTri.maxWidth,
              }}
              className="min-w-[13rem] w-max overflow-y-auto rounded-xl bg-canvas shadow-float border border-hairline-soft py-1.5 z-100 animate-fade-in text-left"
            >
              {actions.map((action, index) => {
                const colorClass = action.disabled
                  ? "text-muted-soft cursor-not-allowed"
                  : action.variant === "danger"
                    ? "text-rose-600 hover:bg-rose-50"
                    : action.variant === "success"
                      ? "text-emerald-700 hover:bg-emerald-50"
                      : action.variant === "warning"
                        ? "text-amber-700 hover:bg-amber-50"
                        : "text-ink hover:bg-surface-soft";

                return (
                  <React.Fragment key={index}>
                    {action.variant === "danger" && index > 0 && (
                      <div className="h-px bg-hairline-soft my-1.5" />
                    )}
                    <button
                      type="button"
                      role="menuitem"
                      disabled={action.disabled}
                      onClick={() => {
                        dongMenu();
                        action.onClick();
                      }}
                      className={`w-full px-4 py-2.5 transition-colors flex items-start gap-2.5 text-left cursor-pointer ${colorClass}`}
                    >
                      {action.icon && (
                        <span className="shrink-0 mt-0.5">{action.icon}</span>
                      )}
                      <span className="min-w-0">
                        <span className="block text-button-sm whitespace-nowrap">
                          {action.label}
                        </span>
                        {action.hint && (
                          <span className="block text-caption-sm text-muted mt-0.5">
                            {action.hint}
                          </span>
                        )}
                      </span>
                    </button>
                  </React.Fragment>
                );
              })}
            </div>
          </>,
          document.body,
        )}
    </div>
  );
};
