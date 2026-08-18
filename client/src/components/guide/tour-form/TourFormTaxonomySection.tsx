import React from "react";
import type { SelectOption } from "./types";

interface CancellationPolicyOption {
  id: number;
  name: string;
  is_default?: boolean;
}

interface Props {
  labelClass: string;
  categories: SelectOption[];
  services: SelectOption[];
  cancellationPolicies: CancellationPolicyOption[];
  selectedCategoryIds: number[];
  selectedServiceIds: number[];
  cancellationPolicyId: string;
  onToggleCategory: (id: number) => void;
  onToggleService: (id: number) => void;
  onCancellationPolicyChange: (value: string) => void;
  optionsLoading: boolean;
}

export const TourFormTaxonomySection: React.FC<Props> = ({
  labelClass,
  categories,
  services,
  cancellationPolicies,
  selectedCategoryIds,
  selectedServiceIds,
  cancellationPolicyId,
  onToggleCategory,
  onToggleService,
  onCancellationPolicyChange,
  optionsLoading,
}) => (
  <>
    {optionsLoading && (
      <div className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500">
        Đang tải danh mục và dịch vụ...
      </div>
    )}

    {/*
      Chính sách hủy riêng cho tour.

      Cột dữ liệu có từ đầu và đơn đã chép chính sách lúc đặt, nhưng biểu mẫu chưa bao giờ gửi
      lên — nên mọi tour đều rơi về chính sách mặc định và các chính sách khác nằm chết trong
      bảng. Đây là chỗ nối dây còn thiếu.

      Lý do cần chọn riêng là lý do nghiệp vụ: tour bay vé máy bay không thể cùng điều khoản
      hoàn với tour đi xe, vì vé bay mất trắng từ lúc xuất.
    */}
    {cancellationPolicies.length > 0 && (
      <div>
        <label className={labelClass}>Chính sách hủy</label>
        <select
          value={cancellationPolicyId}
          onChange={(e) => onCancellationPolicyChange(e.target.value)}
          className="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 cursor-pointer focus:outline-none focus:border-primary-500"
        >
          <option value="">Dùng chính sách mặc định của hệ thống</option>
          {cancellationPolicies.map((policy) => (
            <option key={policy.id} value={String(policy.id)}>
              {policy.name}
              {policy.is_default ? " (đang là mặc định)" : ""}
            </option>
          ))}
        </select>
        <p className="mt-1.5 text-xs text-gray-500">
          Đơn đặt tour <b>chép lại chính sách tại thời điểm đặt</b>, nên sửa chính sách về sau
          không làm đổi điều khoản của đơn cũ.
        </p>
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
