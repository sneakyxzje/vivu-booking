import React from "react";
import type { SelectOption } from "./types";

/*
 * KHÔNG còn ô chọn chính sách hủy ở đây.
 *
 * Cả hệ thống dùng chung một bảng phí hủy, sửa ở màn "Chính sách hủy". Tour không chọn riêng
 * nữa: cho chọn thì mọi màn hình chạm tới tiền đều phải trả lời câu "đơn này áp bảng nào", mà
 * không ai được lợi khi phải trả lời câu đó.
 */
interface Props {
  labelClass: string;
  categories: SelectOption[];
  services: SelectOption[];
  selectedCategoryIds: number[];
  selectedServiceIds: number[];
  onToggleCategory: (id: number) => void;
  onToggleService: (id: number) => void;
  optionsLoading: boolean;
}

export const TourFormTaxonomySection: React.FC<Props> = ({
  labelClass,
  categories,
  services,
  selectedCategoryIds,
  selectedServiceIds,
  onToggleCategory,
  onToggleService,
  optionsLoading,
}) => (
  <>
    {optionsLoading && (
      <div className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500">
        Đang tải danh mục và dịch vụ...
      </div>
    )}

    {categories.length > 0 && (
      <div>
        <label className={labelClass}>Danh mục</label>
        <div className="flex flex-wrap gap-2">
          {categories.map((category) => {
            const checked = selectedCategoryIds.includes(category.id);
            return (
              <label
                key={category.id}
                className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                  checked
                    ? "border-primary-500 bg-primary-50 text-primary-700"
                    : "border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:bg-gray-50"
                }`}
              >
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={() => onToggleCategory(category.id)}
                  className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                {category.name}
              </label>
            );
          })}
        </div>
      </div>
    )}
    {services.length > 0 && (
      <div>
        <label className={labelClass}>Dịch vụ đi kèm</label>
        <div className="flex flex-wrap gap-2">
          {services.map((service) => {
            const checked = selectedServiceIds.includes(service.id);
            return (
              <label
                key={service.id}
                className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                  checked
                    ? "border-primary-500 bg-primary-50 text-primary-700"
                    : "border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:bg-gray-50"
                }`}
              >
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={() => onToggleService(service.id)}
                  className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                {service.name}
              </label>
            );
          })}
        </div>
      </div>
    )}
  </>
);
