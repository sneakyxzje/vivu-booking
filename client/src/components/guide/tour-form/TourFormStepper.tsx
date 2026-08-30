import React from "react";
import { AlertCircle, Check } from "lucide-react";

/**
 * Thanh bước của biểu mẫu tạo tour.
 *
 * ## Bấm bước nào cũng nhảy được
 *
 * Một wizard khóa chặt, bắt đi tuần tự, chỉ hợp khi người dùng lần đầu và không biết mình cần
 * gì. Người điều hành sửa tour thì biết chính xác họ vào để đổi cái gì — thường là mở thêm vài
 * ngày khởi hành — nên bắt bấm "Tiếp theo" ba lần là thù địch không cần thiết.
 *
 * Bước còn thiếu vẫn hiện dấu cảnh báo, và nút lưu vẫn chặn. Chỉ có đường đi là tự do.
 */

export interface BuocForm {
  ten: string;
  moTa: string;
}

interface Props {
  buocs: BuocForm[];
  hienTai: number;
  /** Lỗi của từng bước, cùng thứ tự với `buocs`. Mảng rỗng nghĩa là bước đó đã ổn. */
  loiTheoBuoc: string[][];
  /** Bước người dùng đã ghé qua. Chưa ghé thì không dán nhãn thiếu sót lên đó. */
  daGhe: number[];
  onChon: (buoc: number) => void;
}

export const TourFormStepper: React.FC<Props> = ({
  buocs,
  hienTai,
  loiTheoBuoc,
  daGhe,
  onChon,
}) => (
  <nav aria-label="Các bước tạo tour" className="rounded-xl border border-gray-200 bg-white p-2 shadow-sm">
    <ol className="flex flex-col gap-1 sm:flex-row sm:items-stretch">
      {buocs.map((buoc, i) => {
        const loi = loiTheoBuoc[i] ?? [];
        const daXong = loi.length === 0;
        const canhBao = !daXong && daGhe.includes(i);
        const dangO = i === hienTai;

        return (
          <li key={buoc.ten} className="flex-1">
            <button
              type="button"
              onClick={() => onChon(i)}
              aria-current={dangO ? "step" : undefined}
              className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left transition-colors ${
                dangO ? "bg-primary-50 ring-1 ring-primary-200" : "hover:bg-gray-50"
              }`}
            >
              <span
                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors ${
                  canhBao
                    ? "bg-amber-100 text-amber-700"
                    : daXong
                      ? "bg-emerald-600 text-white"
                      : dangO
                        ? "bg-primary-600 text-white"
                        : "bg-gray-100 text-gray-500"
                }`}
              >
                {canhBao ? (
                  <AlertCircle className="h-4 w-4" />
                ) : daXong ? (
                  <Check className="h-4 w-4" />
                ) : (
                  i + 1
                )}
              </span>

              <span className="min-w-0">
                <span
                  className={`block truncate text-sm font-bold ${
                    dangO ? "text-primary-800" : "text-gray-800"
                  }`}
                >
                  {buoc.ten}
                </span>
                <span className="block truncate text-[11px] text-gray-500">
                  {canhBao ? loi[0] : buoc.moTa}
                </span>
              </span>
            </button>
          </li>
        );
      })}
    </ol>
  </nav>
);

export default TourFormStepper;
