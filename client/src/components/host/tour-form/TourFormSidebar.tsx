import React from "react";
import type { SelectOption } from "./types";

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
}) => (
  <aside className="space-y-4 lg:sticky lg:top-6 lg:self-start">
    <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
      <div className="aspect-[16/10] bg-gray-100">
        {thumbnailPreview || thumbnailUrl ? (
          <img
            src={thumbnailPreview || thumbnailUrl}
            alt=""
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="flex h-full items-center justify-center bg-primary-50 text-primary-600">
            <svg className="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 16l5-5a2 2 0 012.83 0L14 14m-1-1l2-2a2 2 0 012.83 0L21 14M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        )}
      </div>
      <div className="p-5">
        <div className="flex items-start justify-between gap-3">
          <h2 className="line-clamp-2 text-lg font-bold text-gray-950">
            {title || "Tên tour"}
          </h2>
          <span className="shrink-0 rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700">
            {numberOfDays || 1}N{Number(numberOfNights) > 0 ? ` ${numberOfNights}Đ` : ""}
          </span>
        </div>
        <p className="mt-3 text-xl font-bold text-primary-600">{previewPrice}</p>
        <div className="mt-4 space-y-2 text-sm text-gray-600">
          <div className="flex items-center gap-2">
            <svg className="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 11c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9c0 5-7 11-7 11S5 14 5 9a7 7 0 1114 0z" />
            </svg>
            <span className="truncate">
              {startLocation || "Điểm khởi hành"}
              {endLocation ? ` - ${endLocation}` : ""}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div className="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
      <h3 className="text-sm font-bold text-gray-950">Đã chọn</h3>
      <div className="mt-4 space-y-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">Bộ ảnh</p>
          <p className="mt-2 text-sm text-gray-600">
            {imageCount > 0 ? `${imageCount} ảnh sẽ được tải lên` : "Chưa chọn ảnh"}
          </p>
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">Danh mục</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {selectedCategories.length > 0 ? (
              selectedCategories.map((category) => (
                <span key={category.id} className="rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700">
                  {category.name}
                </span>
              ))
            ) : (
              <span className="text-sm text-gray-400">Chưa chọn</span>
            )}
          </div>
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">Dịch vụ</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {selectedServices.length > 0 ? (
              selectedServices.map((service) => (
                <span key={service.id} className="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                  {service.name}
                </span>
              ))
            ) : (
              <span className="text-sm text-gray-400">Chưa chọn</span>
            )}
          </div>
        </div>
      </div>
    </div>
  </aside>
);
