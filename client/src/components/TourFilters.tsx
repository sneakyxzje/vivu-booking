import React from "react";
import type { Category, Service } from "@/types";

export const DURATION_OPTIONS = [
  { value: "all", label: "Tất cả" },
  { value: "1", label: "Trong ngày (1 ngày)" },
  { value: "2-3", label: "Ngắn ngày (2 - 3 ngày)" },
  { value: "4+", label: "Dài ngày (Từ 4 ngày trở lên)" },
];

interface TourFiltersProps {
  categories: Category[];
  services: Service[];
  selectedCategories: string[];
  toggleCategory: (slug: string) => void;
  selectedServices: string[];
  toggleService: (id: string) => void;
  selectedDuration: string;
  setSelectedDuration: (val: string) => void;
  priceRange: [number, number];
  setPriceRange: (range: [number, number]) => void;
  maxPrice: number;
  /** Khoảng ngày khách rảnh, dạng YYYY-MM-DD. Chuỗi rỗng nghĩa là không giới hạn đầu đó. */
  departureRange: [string, string];
  setDepartureRange: (range: [string, string]) => void;
  onReset: () => void;
}

export const TourFilters: React.FC<TourFiltersProps> = ({
  categories,
  services,
  selectedCategories,
  toggleCategory,
  selectedServices,
  toggleService,
  selectedDuration,
  setSelectedDuration,
  priceRange,
  setPriceRange,
  maxPrice,
  departureRange,
  setDepartureRange,
  onReset,
}) => {
  // Không cho chọn ngày trong quá khứ: chuyến đã khởi hành thì không còn đặt được.
  const homNay = new Date().toISOString().slice(0, 10);

  return (
    <aside className="w-full lg:w-[320px] shrink-0">
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sticky top-24">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-bold text-gray-900">Bộ lọc nâng cao</h2>
          <button onClick={onReset} className="text-xs font-bold text-primary-600 hover:text-primary-700 underline cursor-pointer">
            Xóa lọc
          </button>
        </div>

        {/*
          Ngày khởi hành đứng đầu bộ lọc, trước cả danh mục.
          Phần lớn người tìm tour bắt đầu từ "tôi rảnh những ngày này", chứ không từ loại hình.
        */}
        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">Ngày khởi hành</h3>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-xs font-medium text-gray-500">Từ ngày</span>
              <input
                type="date"
                min={homNay}
                value={departureRange[0]}
                onChange={(e) => setDepartureRange([e.target.value, departureRange[1]])}
                className="mt-1 w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white"
              />
            </label>
            <label className="block">
              <span className="text-xs font-medium text-gray-500">Đến ngày</span>
              <input
                type="date"
                // Chặn ngay ở ô nhập thay vì để máy chủ trả 422: người dùng thấy được giới hạn
                // trước khi chọn thì không bao giờ chạm vào lỗi đó.
                min={departureRange[0] || homNay}
                value={departureRange[1]}
                onChange={(e) => setDepartureRange([departureRange[0], e.target.value])}
                className="mt-1 w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white"
              />
            </label>
          </div>
          {(departureRange[0] || departureRange[1]) && (
            <button
              onClick={() => setDepartureRange(["", ""])}
              className="mt-3 text-xs font-bold text-primary-600 hover:text-primary-700 underline cursor-pointer"
            >
              Bỏ chọn ngày
            </button>
          )}
          <p className="mt-3 text-xs text-gray-400">
            Chỉ hiện tour còn chỗ và chưa qua hạn chốt trong khoảng ngày này.
          </p>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">Danh mục Tour</h3>
          <div className="space-y-3">
            {categories.length === 0 ? (
              <p className="text-sm text-gray-400">Chưa có danh mục.</p>
            ) : categories.map((cat) => (
              <label key={cat.id} className="flex items-center justify-between cursor-pointer group">
                <div className="flex items-center gap-3">
                  <div className={`w-5 h-5 rounded border flex items-center justify-center transition-colors ${selectedCategories.includes(cat.slug) ? "bg-primary-600 border-primary-600" : "border-gray-300 bg-white group-hover:border-primary-400"}`}>
                    {selectedCategories.includes(cat.slug) && (
                      <svg className="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                      </svg>
                    )}
                  </div>
                  <span className="text-sm text-gray-600 group-hover:text-gray-900 font-medium">{cat.name}</span>
                </div>
                <input type="checkbox" className="hidden" checked={selectedCategories.includes(cat.slug)} onChange={() => toggleCategory(cat.slug)} />
              </label>
            ))}
          </div>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">Mức giá</h3>
          <div className="px-2">
            <input
              type="range"
              min="0"
              max={maxPrice}
              step="500000"
              value={priceRange[1]}
              onChange={(e) => setPriceRange([0, parseInt(e.target.value, 10)])}
              className="w-full accent-primary-600 h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            />
            <div className="flex justify-between items-center mt-4">
              <span className="text-xs text-gray-500 font-medium">0đ</span>
              <span className="text-sm font-bold text-primary-600 bg-primary-50 px-3 py-1 rounded-lg">
                Tới {new Intl.NumberFormat("vi-VN").format(priceRange[1])}đ
              </span>
            </div>
          </div>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">Thời gian</h3>
          <div className="space-y-3">
            {DURATION_OPTIONS.map((opt) => (
              <label key={opt.value} className="flex items-center gap-3 cursor-pointer group">
                <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors ${selectedDuration === opt.value ? "border-primary-600" : "border-gray-300 group-hover:border-primary-400"}`}>
                  {selectedDuration === opt.value && <div className="w-2.5 h-2.5 bg-primary-600 rounded-full" />}
                </div>
                <span className="text-sm text-gray-600 group-hover:text-gray-900 font-medium">{opt.label}</span>
                <input type="radio" name="duration" className="hidden" onChange={() => setSelectedDuration(opt.value)} checked={selectedDuration === opt.value} />
              </label>
            ))}
          </div>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        <div>
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">Dịch vụ đi kèm</h3>
          <div className="space-y-3">
            {services.length === 0 ? (
              <p className="text-sm text-gray-400">Chưa có dịch vụ.</p>
            ) : services.map((srv) => {
              const id = String(srv.id);
              return (
                <label key={srv.id} className="flex items-center gap-3 cursor-pointer group">
                  <div className={`w-5 h-5 rounded border flex items-center justify-center transition-colors ${selectedServices.includes(id) ? "bg-primary-600 border-primary-600" : "border-gray-300 bg-white group-hover:border-primary-400"}`}>
                    {selectedServices.includes(id) && (
                      <svg className="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                      </svg>
                    )}
                  </div>
                  <span className="text-sm text-gray-600 group-hover:text-gray-900 font-medium">{srv.name}</span>
                  <input type="checkbox" className="hidden" checked={selectedServices.includes(id)} onChange={() => toggleService(id)} />
                </label>
              );
            })}
          </div>
        </div>
      </div>
    </aside>
  );
};