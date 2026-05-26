import React, { useState, useEffect, useMemo } from "react";
import tourService from "@/services/tourService";
import type { Tour } from "@/types";
import { TourCard } from "@/components/TourCard";
import {
  MagnifyingGlassIcon,
  InformationCircleIcon,
  ExclamationTriangleIcon,
  InboxIcon,
} from "@/components/Icons";

import { TourFilters } from "@/components/TourFilters";

export const Tours: React.FC = () => {
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [isMock, setIsMock] = useState<boolean>(false);

  const [searchKeyword, setSearchKeyword] = useState("");
  const [selectedCategories, setSelectedCategories] = useState<string[]>([]);
  const [selectedServices, setSelectedServices] = useState<string[]>([]);
  const [selectedDuration, setSelectedDuration] = useState<string>("all");
  const [priceRange, setPriceRange] = useState<[number, number]>([0, 20000000]); // 0 - 20tr
  const [minRating, setMinRating] = useState<number>(0);

  const [sortBy, setSortBy] = useState<
    "featured" | "price_asc" | "price_desc" | "rating"
  >("featured");

  const fetchTours = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await tourService.getAll();
      if (res.success) {
        setTours(res.data);
        setIsMock(!!res.isMock);
      } else {
        setError("Không thể tải danh sách tour.");
      }
    } catch (err) {
      setError("Đã xảy ra lỗi kết nối.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTours();
  }, []);

  const filteredTours = useMemo(() => {
    let result = [...tours];

    if (searchKeyword.trim()) {
      const q = searchKeyword.toLowerCase();
      result = result.filter(
        (t) =>
          t.title.toLowerCase().includes(q) ||
          t.start_location.toLowerCase().includes(q) ||
          (t.end_location && t.end_location.toLowerCase().includes(q)),
      );
    }

    if (selectedCategories.length > 0) {
      result = result.filter((t) =>
        t.categories?.some((c) => selectedCategories.includes(c.slug)),
      );
    }

    if (selectedServices.length > 0) {
      result = result.filter((t) => t.services && t.services.length > 0);
    }

    if (selectedDuration !== "all") {
      if (selectedDuration === "1")
        result = result.filter((t) => t.number_of_days === 1);
      if (selectedDuration === "2-3")
        result = result.filter(
          (t) => t.number_of_days >= 2 && t.number_of_days <= 3,
        );
      if (selectedDuration === "4+")
        result = result.filter((t) => t.number_of_days >= 4);
    }

    result = result.filter((t) => {
      const p = t.discount_price ?? t.price;
      return p >= priceRange[0] && p <= priceRange[1];
    });

    if (minRating > 0) {
      result = result.filter((t) => (t.rating || 0) >= minRating);
    }

    result.sort((a, b) => {
      if (sortBy === "price_asc") {
        return (a.discount_price ?? a.price) - (b.discount_price ?? b.price);
      }
      if (sortBy === "price_desc") {
        return (b.discount_price ?? b.price) - (a.discount_price ?? a.price);
      }
      if (sortBy === "rating") {
        return (b.rating ?? 0) - (a.rating ?? 0);
      }
      // featured
      if (a.is_featured && !b.is_featured) return -1;
      if (!a.is_featured && b.is_featured) return 1;
      return 0;
    });

    return result;
  }, [
    tours,
    searchKeyword,
    selectedCategories,
    selectedServices,
    selectedDuration,
    priceRange,
    minRating,
    sortBy,
  ]);

  const toggleCategory = (slug: string) => {
    setSelectedCategories((prev) =>
      prev.includes(slug) ? prev.filter((c) => c !== slug) : [...prev, slug],
    );
  };

  const toggleService = (id: string) => {
    setSelectedServices((prev) =>
      prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id],
    );
  };

  const resetFilters = () => {
    setSearchKeyword("");
    setSelectedCategories([]);
    setSelectedServices([]);
    setSelectedDuration("all");
    setPriceRange([0, 20000000]);
    setMinRating(0);
    setSortBy("featured");
  };

  return (
    <div className="bg-gray-50 min-h-screen py-8">
      <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">
            Khám phá Tour Du Lịch
          </h1>
          <p className="text-gray-500 mt-2 text-sm">
            Tìm kiếm và lựa chọn hành trình phù hợp nhất với bạn
          </p>
        </div>

        {isMock && (
          <div className="bg-blue-50/70 border border-blue-100 rounded-2xl px-5 py-4 text-sm text-primary-700 flex items-start gap-3 mb-8 shadow-sm">
            <InformationCircleIcon className="w-5 h-5 text-primary-600 shrink-0 mt-0.5" />
            <div>
              <strong className="block mb-1 text-primary-800">Data Mock</strong>
              Đây chỉ là data mock, anh em nào code features này bổ sung vào sau
              nhé!
            </div>
          </div>
        )}

        <div className="flex flex-col lg:flex-row gap-8">
          <aside className="w-full lg:w-[320px] shrink-0">
            <TourFilters
              selectedCategories={selectedCategories}
              toggleCategory={toggleCategory}
              selectedServices={selectedServices}
              toggleService={toggleService}
              selectedDuration={selectedDuration}
              setSelectedDuration={setSelectedDuration}
              priceRange={priceRange}
              setPriceRange={setPriceRange}
              minRating={minRating}
              setMinRating={setMinRating}
              onReset={resetFilters}
            />
          </aside>

          <main className="flex-1">
            <div className="bg-white p-4 rounded-3xl border border-gray-100 shadow-sm mb-8 flex flex-col md:flex-row gap-4 items-center justify-between">
              <div className="relative w-full md:w-[400px]">
                <MagnifyingGlassIcon className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="Tìm kiếm điểm đến, tên tour..."
                  value={searchKeyword}
                  onChange={(e) => setSearchKeyword(e.target.value)}
                  className="w-full bg-gray-50 border border-gray-200 rounded-2xl py-3 pl-12 pr-4 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white transition-all text-gray-800 placeholder-gray-400"
                />
              </div>

              <div className="flex items-center gap-3 w-full md:w-auto">
                <span className="text-sm text-gray-500 font-medium whitespace-nowrap">
                  Sắp xếp:
                </span>
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value as any)}
                  className="bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-4 text-sm font-bold text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer"
                >
                  <option value="featured">Phổ biến nhất</option>
                  <option value="price_asc">Giá tăng dần</option>
                  <option value="price_desc">Giá giảm dần</option>
                  <option value="rating">Đánh giá cao</option>
                </select>
              </div>
            </div>

            <div className="mb-6">
              <h2 className="text-xl font-bold text-gray-800">
                Tìm thấy{" "}
                <span className="text-primary-600">{filteredTours.length}</span>{" "}
                kết quả phù hợp
              </h2>
            </div>

            {loading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 animate-pulse">
                {[1, 2, 3, 4, 5, 6].map((n) => (
                  <div
                    key={n}
                    className="bg-white rounded-3xl border border-gray-100 shadow-sm h-[400px]"
                  />
                ))}
              </div>
            ) : error ? (
              <div className="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm flex flex-col items-center">
                <ExclamationTriangleIcon className="w-12 h-12 text-red-400 mb-4" />
                <h3 className="text-lg font-bold text-gray-800">Lỗi dữ liệu</h3>
                <p className="text-sm text-gray-500 mt-2">{error}</p>
                <button
                  onClick={fetchTours}
                  className="mt-6 bg-primary-600 text-white font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-primary-700 transition-colors"
                >
                  Thử lại
                </button>
              </div>
            ) : filteredTours.length === 0 ? (
              <div className="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm flex flex-col items-center">
                <InboxIcon className="w-12 h-12 text-gray-300 mb-4" />
                <h3 className="text-lg font-bold text-gray-800">
                  Không có Tour phù hợp
                </h3>
                <p className="text-sm text-gray-500 mt-2">
                  Thử nới lỏng bộ lọc hoặc thay đổi từ khóa tìm kiếm nhé.
                </p>
                <button
                  onClick={resetFilters}
                  className="mt-6 bg-primary-50 text-primary-600 font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-primary-100 transition-colors"
                >
                  Xóa toàn bộ lọc
                </button>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                {filteredTours.map((tour) => (
                  <TourCard key={tour.id} tour={tour} />
                ))}
              </div>
            )}

            {!loading && filteredTours.length > 0 && (
              <div className="mt-12 flex justify-center">
                <div className="inline-flex gap-2 bg-white p-2 rounded-2xl border border-gray-100 shadow-sm">
                  <button className="w-10 h-10 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400 font-bold cursor-not-allowed">
                    &lt;
                  </button>
                  <button className="w-10 h-10 flex items-center justify-center rounded-xl bg-primary-600 text-white font-bold shadow-md shadow-primary-600/20">
                    1
                  </button>
                  <button className="w-10 h-10 flex items-center justify-center rounded-xl bg-white text-gray-600 hover:bg-gray-50 font-bold transition-colors">
                    2
                  </button>
                  <button className="w-10 h-10 flex items-center justify-center rounded-xl bg-white text-gray-600 hover:bg-gray-50 font-bold transition-colors">
                    3
                  </button>
                  <button className="w-10 h-10 flex items-center justify-center rounded-xl bg-white text-gray-600 hover:bg-gray-50 font-bold transition-colors">
                    &gt;
                  </button>
                </div>
              </div>
            )}
          </main>
        </div>
      </div>
    </div>
  );
};

export default Tours;
