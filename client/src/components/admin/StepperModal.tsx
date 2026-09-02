import React from "react";

export interface BuocModal {
  /** Tên ngắn, hiện trên thanh bước. */
  ten: string;
  /** Một câu nói bước này để làm gì. Hiện dưới tiêu đề bước. */
  moTa?: string;
  noiDung: React.ReactNode;
  /**
   * Vì sao chưa đi tiếp được, hoặc null khi xong.
   *
   * Là câu chữ chứ không phải cờ boolean: nút mờ đi mà không nói vì sao là chỗ người dùng đứng
   * lại lâu nhất, và họ thường kết luận màn hình bị treo.
   */
  chuaXong?: string | null;
}

interface Props {
  isOpen: boolean;
  onClose: () => void;
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  buoc: BuocModal[];
  /** Bước đang mở. Do nơi gọi giữ, để mở lại là quay về bước đầu. */
  hienTai: number;
  onDoiBuoc: (chiSo: number) => void;
  nhanHoanTat: string;
  onHoanTat: () => void;
  dangChay?: boolean;
  /** Việc phá đi (hủy đơn) khác việc dựng tiếp (chuyển chuyến), nên nút cuối khác màu. */
  sacThai?: "chinh" | "nguy-hiem";
  size?: "lg" | "xl" | "2xl";
}

const NEN_NUT = {
  chinh: "bg-primary-600 hover:bg-primary-700",
  "nguy-hiem": "bg-rose-600 hover:bg-rose-700",
};

const NEN_BUOC = {
  chinh: "bg-primary-600",
  "nguy-hiem": "bg-rose-600",
};

const CO_CHU = {
  lg: "max-w-lg",
  xl: "max-w-xl",
  "2xl": "max-w-2xl",
};

/**
 * Hộp thoại dẫn qua từng bước, dùng cho những thao tác không nên làm vội.
 *
 * ## Vì sao không bày hết một lượt
 *
 * Hủy đơn và chuyển chuyến đều là thao tác chạm tới tiền của khách, và cả hai đều cần bốn thứ:
 * xem hậu quả, chọn phương án, ghi lý do, rồi mới bấm. Đổ hết xuống một khung cuộn dài thì người
 * bấm đọc lướt phần trên để tới được cái nút ở dưới cùng — đúng phần họ cần đọc kỹ nhất lại là
 * phần bị lướt qua.
 *
 * Mỗi lần một bước, và bước sau chỉ mở khi bước trước đã đủ. Cái giá là thêm vài cú bấm; đổi lại
 * không ai bấm "xác nhận hủy" trước khi nhìn con số hoàn tiền.
 *
 * ## Cố ý KHÔNG khóa cuộn của trang
 *
 * Hộp thoại này luôn mở đè lên hộp chi tiết đơn, và hộp kia đã khóa cuộn rồi. Khóa thêm lần nữa
 * thì lúc đóng bước, phần dọn dẹp trả trang về trạng thái cuộn được trong khi hộp dưới vẫn mở.
 */
export const StepperModal: React.FC<Props> = ({
  isOpen,
  onClose,
  title,
  subtitle,
  buoc,
  hienTai,
  onDoiBuoc,
  nhanHoanTat,
  onHoanTat,
  dangChay = false,
  sacThai = "chinh",
  size = "xl",
}) => {
  if (!isOpen || buoc.length === 0) return null;

  const chiSo = Math.min(Math.max(hienTai, 0), buoc.length - 1);
  const dangO = buoc[chiSo];
  const laBuocCuoi = chiSo === buoc.length - 1;
  const chuaXong = dangO.chuaXong ?? null;

  return (
    <div className="fixed inset-0 z-60 overflow-y-auto flex items-center justify-center p-4 bg-black/50 animate-fade-in">
      <div className={`relative bg-canvas w-full ${CO_CHU[size]} rounded-xl shadow-2xl overflow-hidden`}>
        <div className="px-6 pt-6 pb-4 border-b border-hairline-soft">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <h3 className="text-display-sm text-ink">{title}</h3>
              {subtitle && <p className="text-body-sm text-muted mt-1">{subtitle}</p>}
            </div>
            <button
              type="button"
              onClick={onClose}
              aria-label="Đóng"
              className="shrink-0 w-8 h-8 flex items-center justify-center bg-surface-strong hover:bg-hairline-soft text-ink rounded-full transition-colors cursor-pointer"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/*
            Thanh bước. Bước đã qua bấm lại được — người ta hay muốn xem lại con số ở bước trước
            trước khi bấm nút cuối. Bước chưa tới thì không, vì chưa đủ dữ liệu để dựng.
          */}
          <ol className="mt-4 flex flex-wrap items-center gap-x-2 gap-y-2">
            {buoc.map((b, i) => {
              const daQua = i < chiSo;
              const dangMo = i === chiSo;

              return (
                <li key={b.ten} className="flex items-center gap-2">
                  <button
                    type="button"
                    disabled={!daQua || dangChay}
                    onClick={() => onDoiBuoc(i)}
                    className={`flex items-center gap-1.5 rounded-full py-1 pl-1 pr-2.5 text-caption-sm font-semibold transition-colors ${
                      dangMo
                        ? "bg-surface-strong text-ink"
                        : daQua
                          ? "text-muted hover:bg-surface-soft cursor-pointer"
                          : "text-muted-soft"
                    }`}
                  >
                    <span
                      className={`flex h-5 w-5 items-center justify-center rounded-full text-[11px] ${
                        dangMo || daQua
                          ? `${NEN_BUOC[sacThai]} text-white`
                          : "bg-surface-strong text-muted"
                      }`}
                    >
                      {daQua ? "✓" : i + 1}
                    </span>
                    {b.ten}
                  </button>

                  {i < buoc.length - 1 && (
                    <span className="text-muted-soft" aria-hidden="true">·</span>
                  )}
                </li>
              );
            })}
          </ol>
        </div>

        <div className="px-6 py-5 space-y-4 max-h-[62vh] overflow-y-auto text-body-md text-body">
          {dangO.moTa && <p className="text-body-sm text-muted">{dangO.moTa}</p>}
          {dangO.noiDung}
        </div>

        <div className="bg-surface-soft px-6 py-4 border-t border-hairline-soft space-y-2">
          {chuaXong && (
            <p className="text-caption-sm text-muted text-right">{chuaXong}</p>
          )}

          <div className="flex justify-end gap-3">
            <button
              type="button"
              onClick={() => (chiSo === 0 ? onClose() : onDoiBuoc(chiSo - 1))}
              disabled={dangChay}
              className="px-4 py-2 text-button-sm border border-hairline bg-canvas text-body rounded-lg hover:bg-surface-soft disabled:opacity-50 cursor-pointer"
            >
              {chiSo === 0 ? "Thoát" : "Quay lại"}
            </button>

            <button
              type="button"
              onClick={() => (laBuocCuoi ? onHoanTat() : onDoiBuoc(chiSo + 1))}
              disabled={dangChay || chuaXong !== null}
              className={`px-4 py-2 text-button-sm font-semibold text-white rounded-lg disabled:opacity-50 cursor-pointer ${NEN_NUT[sacThai]}`}
            >
              {dangChay ? "Đang xử lý..." : laBuocCuoi ? nhanHoanTat : "Tiếp tục"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default StepperModal;
