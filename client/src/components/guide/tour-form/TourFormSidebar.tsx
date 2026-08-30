import React from "react";
import { AlertCircle, CalendarDays, CheckCircle2, ImageIcon, MapPin } from "lucide-react";
import type { SelectOption } from "./types";

/**
 * Cột phải: tour đang thành hình ra sao, và còn thiếu gì để lưu được.
 *
 * Thẻ xem trước dựng đúng theo thẻ tour ở trang khách, nên người tạo thấy ngay tiêu đề mình gõ
 * sẽ bị cắt ở đâu và ảnh bìa cắt cúp thế nào. Bên dưới là danh sách còn thiếu — thay cho việc
 * bấm Lưu rồi mới biết máy chủ từ chối vì cái gì.
 */

interface Props {
  title: string;
  previewPrice: string;
  startLocation: string;
  endLocation: string;
  thumbnailPreview: string;
  thumbnailUrl: string;
  selectedCategories: SelectOption[];
  selectedServices: SelectOption[];
  imageCount: number;
  numberOfDays: string;
  numberOfNights: string;
  soChuyen: number;
  /** Những việc còn thiếu, gộp từ mọi bước. Rỗng nghĩa là lưu được. */
  thieu: string[];
}

export const TourFormSidebar: React.FC<Props> = ({
  title,
  previewPrice,
  startLocation,
  endLocation,
  thumbnailPreview,
  thumbnailUrl,
  selectedCategories,
  selectedServices,
  imageCount,
  numberOfDays,
  numberOfNights,
  soChuyen,
  thieu,
}) => (
  <aside className="space-y-4 lg:sticky lg:top-6 lg:self-start">
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <div className="aspect-[16/10] bg-gray-100">
        {thumbnailPreview || thumbnailUrl ? (
          <img
            src={thumbnailPreview || thumbnailUrl}
            alt=""
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="flex h-full flex-col items-center justify-center gap-1 bg-primary-50 text-primary-400">
            <ImageIcon className="h-8 w-8" />
            <span className="text-[11px] font-semibold">Chưa có ảnh bìa</span>
          </div>
        )}
      </div>

      <div className="p-4">
        <div className="flex items-start justify-between gap-3">
          <h2 className="line-clamp-2 text-base font-bold text-gray-950">
            {title || "Tên tour sẽ hiện ở đây"}
          </h2>
          <span className="shrink-0 rounded-lg bg-primary-50 px-2 py-1 text-[11px] font-bold text-primary-700">
            {numberOfDays || 1}N{Number(numberOfNights) > 0 ? `${numberOfNights}Đ` : ""}
          </span>
        </div>

        <p className="mt-2 text-lg font-bold text-primary-600">{previewPrice}</p>

        <div className="mt-3 space-y-1.5 text-xs text-gray-600">
          <div className="flex items-center gap-2">
            <MapPin className="h-3.5 w-3.5 shrink-0 text-gray-400" />
            <span className="truncate">
              {startLocation || "Điểm khởi hành"}
              {endLocation ? ` → ${endLocation}` : ""}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <CalendarDays className="h-3.5 w-3.5 shrink-0 text-gray-400" />
            <span>
              {soChuyen > 0 ? `${soChuyen} ngày khởi hành đang mở` : "Chưa mở ngày khởi hành"}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <h3 className="text-xs font-bold uppercase tracking-wide text-gray-500">
        Còn thiếu để lưu tour
      </h3>

      {thieu.length === 0 ? (
        <p className="mt-3 flex items-start gap-2 text-sm font-semibold text-emerald-700">
          <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
          Đã đủ thông tin, lưu được rồi.
        </p>
      ) : (
        <ul className="mt-3 space-y-2">
          {thieu.map((viec) => (
            <li key={viec} className="flex items-start gap-2 text-xs text-gray-600">
              <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500" />
              {viec}
            </li>
          ))}
        </ul>
      )}
    </div>

    <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <h3 className="text-xs font-bold uppercase tracking-wide text-gray-500">Đã chọn</h3>
      <div className="mt-3 space-y-3">
        <div>
          <p className="text-[11px] font-semibold text-gray-400">Bộ ảnh</p>
          <p className="mt-1 text-xs text-gray-600">
            {imageCount > 0 ? `${imageCount} ảnh sẽ được tải lên` : "Chưa chọn ảnh"}
          </p>
        </div>
        <div>
          <p className="text-[11px] font-semibold text-gray-400">Danh mục</p>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {selectedCategories.length > 0 ? (
              selectedCategories.map((category) => (
                <span
                  key={category.id}
                  className="rounded-lg bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700"
                >
                  {category.name}
                </span>
              ))
            ) : (
              <span className="text-xs text-gray-400">Chưa chọn</span>
            )}
          </div>
        </div>
        <div>
          <p className="text-[11px] font-semibold text-gray-400">Dịch vụ</p>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {selectedServices.length > 0 ? (
              selectedServices.map((service) => (
                <span
                  key={service.id}
                  className="rounded-lg bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700"
                >
                  {service.name}
                </span>
              ))
            ) : (
              <span className="text-xs text-gray-400">Chưa chọn</span>
            )}
          </div>
        </div>
      </div>
    </div>
  </aside>
);
