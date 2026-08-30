import React from "react";
import { Layers } from "lucide-react";
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

const NhomChon: React.FC<{
  nhan: string;
  moTa: string;
  danhSach: SelectOption[];
  daChon: number[];
  onToggle: (id: number) => void;
  labelClass: string;
}> = ({ nhan, moTa, danhSach, daChon, onToggle, labelClass }) => (
  <div>
    <label className={`${labelClass} mb-1`}>
      {nhan}
      {daChon.length > 0 && (
        <span className="ml-1.5 text-xs font-semibold text-primary-600">
          đã chọn {daChon.length}
        </span>
      )}
    </label>
    <p className="mb-2.5 text-[11px] text-gray-500">{moTa}</p>
    <div className="flex flex-wrap gap-2">
      {danhSach.map((muc) => {
        const chon = daChon.includes(muc.id);
        return (
          <label
            key={muc.id}
            className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors ${
              chon
                ? "border-primary-500 bg-primary-50 text-primary-700"
                : "border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:bg-gray-50"
            }`}
          >
            <input
              type="checkbox"
              checked={chon}
              onChange={() => onToggle(muc.id)}
              className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            {muc.name}
          </label>
        );
      })}
    </div>
  </div>
);

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
  <section className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div className="mb-4 flex items-start gap-3 border-b border-gray-100 pb-3">
      <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
        <Layers className="h-4 w-4" />
      </span>
      <div>
        <h3 className="text-sm font-bold text-gray-950">Phân loại & dịch vụ</h3>
        <p className="mt-0.5 text-xs text-gray-500">
          Quyết định tour hiện ra ở bộ lọc nào của khách. Không bắt buộc, nhưng bỏ trống thì tour
          khó được tìm thấy.
        </p>
      </div>
    </div>

    {optionsLoading ? (
      <p className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500">
        Đang tải danh mục và dịch vụ...
      </p>
    ) : (
      <div className="space-y-5">
        {categories.length > 0 && (
          <NhomChon
            nhan="Danh mục"
            moTa="Loại hình tour: biển đảo, nghỉ dưỡng, khám phá..."
            danhSach={categories}
            daChon={selectedCategoryIds}
            onToggle={onToggleCategory}
            labelClass={labelClass}
          />
        )}

        {services.length > 0 && (
          <NhomChon
            nhan="Dịch vụ đi kèm"
            moTa="Những gì đã nằm trong giá vé."
            danhSach={services}
            daChon={selectedServiceIds}
            onToggle={onToggleService}
            labelClass={labelClass}
          />
        )}

        {categories.length === 0 && services.length === 0 && (
          <p className="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
            Chưa có danh mục hay dịch vụ nào trong hệ thống.
          </p>
        )}
      </div>
    )}
  </section>
);
