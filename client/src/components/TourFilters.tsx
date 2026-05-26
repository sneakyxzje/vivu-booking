import React from "react";
import { StarIcon } from "@/components/Icons";

export const MOCK_CATEGORIES = [
  { id: "tour-nghi-duong", name: "Tour nghỉ dưỡng", count: 24 },
  { id: "tour-leo-nui", name: "Tour leo núi", count: 12 },
  { id: "tour-van-hoa", name: "Tour văn hóa", count: 18 },
  { id: "tour-bien-dao", name: "Tour biển đảo", count: 35 },
  { id: "tour-kham-pha", name: "Khám phá mạo hiểm", count: 8 },
];

export const MOCK_SERVICES = [
  { id: "khach-san-4-sao", name: "Khách sạn 4-5 sao" },
  { id: "dua-don-san-bay", name: "Đưa đón sân bay" },
  { id: "bao-an-3-bua", name: "Bao ăn 3 bữa" },
  { id: "huong-dan-vien", name: "Hướng dẫn viên Tiếng Anh" },
  { id: "ve-may-bay", name: "Bao gồm vé máy bay" },
];

export const DURATION_OPTIONS = [
  { value: "all", label: "Tất cả" },
  { value: "1", label: "Trong ngày (1 ngày)" },
  { value: "2-3", label: "Ngắn ngày (2 - 3 ngày)" },
  { value: "4+", label: "Dài ngày (Từ 4 ngày trở lên)" },
];

interface TourFiltersProps {
  selectedCategories: string[];
  toggleCategory: (slug: string) => void;
  selectedServices: string[];
  toggleService: (id: string) => void;
  selectedDuration: string;
  setSelectedDuration: (val: string) => void;
  priceRange: [number, number];
  setPriceRange: (range: [number, number]) => void;
  minRating: number;
  setMinRating: (rating: number) => void;
  onReset: () => void;
}

export const TourFilters: React.FC<TourFiltersProps> = ({
  selectedCategories,
  toggleCategory,
  selectedServices,
  toggleService,
  selectedDuration,
  setSelectedDuration,
  priceRange,
  setPriceRange,
  minRating,
  setMinRating,
  onReset,
}) => {
  return (
    <aside className="w-full lg:w-[320px] shrink-0">
      <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sticky top-24">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-bold text-gray-900">Bộ lọc nâng cao</h2>
          <button
            onClick={onReset}
            className="text-xs font-bold text-primary-600 hover:text-primary-700 underline cursor-pointer"
          >
            Xóa lọc
          </button>
        </div>

        {/* 1. Category Filter */}
        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">
            Danh mục Tour
          </h3>
          <div className="space-y-3">
            {MOCK_CATEGORIES.map((cat) => (
              <label
                key={cat.id}
                className="flex items-center justify-between cursor-pointer group"
              >
                <div className="flex items-center gap-3">
                  <div
                    className={`w-5 h-5 rounded border flex items-center justify-center transition-colors ${selectedCategories.includes(cat.id) ? "bg-primary-600 border-primary-600" : "border-gray-300 bg-white group-hover:border-primary-400"}`}
                  >
                    {selectedCategories.includes(cat.id) && (
                      <svg
                        className="w-3.5 h-3.5 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={3}
                          d="M5 13l4 4L19 7"
                        />
                      </svg>
                    )}
                  </div>
                  <span className="text-sm text-gray-600 group-hover:text-gray-900 font-medium">
                    {cat.name}
                  </span>
                </div>
                <span className="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">
                  {cat.count}
                </span>
                <input
                  type="checkbox"
                  className="hidden"
                  checked={selectedCategories.includes(cat.id)}
                  onChange={() => toggleCategory(cat.id)}
                />
              </label>
            ))}
          </div>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        {/* 2. Price Range */}
        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">
            Mức giá
          </h3>
          <div className="px-2">
            <input
              type="range"
              min="0"
              max="20000000"
              step="500000"
              value={priceRange[1]}
              onChange={(e) => setPriceRange([0, parseInt(e.target.value)])}
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

        {/* 3. Duration */}
        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">
            Thời gian
          </h3>
          <div className="space-y-3">
            {DURATION_OPTIONS.map((opt) => (
              <label
                key={opt.value}
                className="flex items-center gap-3 cursor-pointer group"
              >
                <div
                  className={`w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors ${selectedDuration === opt.value ? "border-primary-600" : "border-gray-300 group-hover:border-primary-400"}`}
                >
                  {selectedDuration === opt.value && (
                    <div className="w-2.5 h-2.5 bg-primary-600 rounded-full" />
                  )}
                </div>
                <span className="text-sm text-gray-600 group-hover:text-gray-900 font-medium">
                  {opt.label}
                </span>
                <input
                  type="radio"
                  name="duration"
                  className="hidden"
                  onChange={() => setSelectedDuration(opt.value)}
                  checked={selectedDuration === opt.value}
                />
              </label>
            ))}
          </div>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        {/* 4. Rating */}
        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">
            Đánh giá
          </h3>
          <div className="space-y-2">
            {[5, 4, 3].map((star) => (
              <label
                key={star}
                className="flex items-center gap-3 cursor-pointer group"
              >
                <div
                  className={`w-5 h-5 rounded border flex items-center justify-center transition-colors ${minRating === star ? "bg-primary-600 border-primary-600" : "border-gray-300 bg-white group-hover:border-primary-400"}`}
                >
                  {minRating === star && (
                    <svg
                      className="w-3.5 h-3.5 text-white"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={3}
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  )}
                </div>
                <div className="flex text-amber-400 gap-0.5">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <StarIcon key={i} className="w-4 h-4" filled={i < star} />
                  ))}
                </div>
                <span className="text-sm text-gray-500 ml-1">trở lên</span>
                <input
                  type="radio"
                  name="rating"
                  className="hidden"
                  onChange={() => setMinRating(star)}
                  checked={minRating === star}
                />
              </label>
            ))}
          </div>
        </div>

        <div className="h-px bg-gray-100 my-6" />

        {/* 5. Services / Amenities */}
        <div>
          <h3 className="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">
            Dịch vụ đi kèm
          </h3>
          <div className="space-y-3">
            {MOCK_SERVICES.map((srv) => (
              <label
                key={srv.id}
                className="flex items-center gap-3 cursor-pointer group"
              >
                <div
                  className={`w-5 h-5 rounded border flex items-center justify-center transition-colors ${selectedServices.includes(srv.id) ? "bg-primary-600 border-primary-600" : "border-gray-300 bg-white group-hover:border-primary-400"}`}
                >
                  {selectedServices.includes(srv.id) && (
                    <svg
                      className="w-3.5 h-3.5 text-white"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={3}
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  )}
                </div>
                <span className="text-sm text-gray-600 group-hover:text-gray-900 font-medium">
                  {srv.name}
                </span>
                <input
                  type="checkbox"
                  className="hidden"
                  checked={selectedServices.includes(srv.id)}
                  onChange={() => toggleService(srv.id)}
                />
              </label>
            ))}
          </div>
        </div>
      </div>
    </aside>
  );
};
