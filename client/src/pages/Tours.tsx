import React, { useState, useEffect, useMemo } from "react";
import { useSearchParams } from "react-router-dom";
import tourService from "@/services/tourService";
import type { Category, Service, Tour } from "@/types";
import { TourCard } from "@/components/TourCard";
import {
  MagnifyingGlassIcon,
  ExclamationTriangleIcon,
  InboxIcon,
} from "@/components/Icons";

import { TourFilters } from "@/components/TourFilters";

const DEFAULT_MAX_PRICE = 20000000;

export const Tours: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const urlKeyword = searchParams.get("q") ?? "";
  const urlStartLocation = searchParams.get("start_location") ?? "";
  const urlDuration = searchParams.get("duration") ?? "all";
  const urlCategories = searchParams.get("categories") ?? "";
  const [tours, setTours] = useState<Tour[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const [searchKeyword, setSearchKeyword] = useState(urlKeyword);
  const [selectedCategories, setSelectedCategories] = useState<string[]>(urlCategories ? urlCategories.split(",").filter(Boolean) : []);
  const [selectedServices, setSelectedServices] = useState<string[]>([]);
  const [selectedDuration, setSelectedDuration] = useState<string>(urlDuration);
  const [priceRange, setPriceRange] = useState<[number, number]>([0, DEFAULT_MAX_PRICE]);
  const [sortBy, setSortBy] = useState<"featured" | "price_asc" | "price_desc">("featured");

  const filterParams = useMemo(() => {
    const params: Record<string, string | number> = {
      sort: sortBy,
    };

    if (searchKeyword.trim()) params.q = searchKeyword.trim();
    if (urlStartLocation.trim()) params.start_location = urlStartLocation.trim();
    if (selectedCategories.length > 0) params.categories = selectedCategories.join(",");
    if (selectedServices.length > 0) params.services = selectedServices.join(",");
    if (selectedDuration !== "all") params.duration = selectedDuration;
    if (priceRange[0] > 0) params.min_price = priceRange[0];
    if (priceRange[1] < DEFAULT_MAX_PRICE) params.max_price = priceRange[1];

    return params;
  }, [searchKeyword, selectedCategories, selectedServices, selectedDuration, priceRange, sortBy, urlStartLocation]);

  const fetchTours = async (params = filterParams) => {
    setLoading(true);
    setError(null);
    try {
      const res = await tourService.getAll(params);
      setTours(res.data);
    } catch {
      setError("Đã xảy ra lỗi kết nối.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    setSearchKeyword(urlKeyword);
    setSelectedCategories(urlCategories ? urlCategories.split(",").filter(Boolean) : []);
    setSelectedDuration(urlDuration);
  }, [urlKeyword, urlCategories, urlDuration]);
  useEffect(() => {
    const loadFilterOptions = async () => {
      try {
        const [categoryData, serviceData] = await Promise.all([
          tourService.getCategories(),
          tourService.getServices(),
        ]);
        setCategories(categoryData);
        setServices(serviceData);
      } catch {
        setCategories([]);
        setServices([]);
      }
    };

    loadFilterOptions();
  }, []);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      fetchTours(filterParams);
    }, 300);

    return () => window.clearTimeout(timeoutId);
  }, [filterParams]);

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
    setPriceRange([0, DEFAULT_MAX_PRICE]);
    setSortBy("featured");
    setSearchParams({});
  };

  const hasActiveFilters = Boolean(
    searchKeyword.trim()
    || selectedCategories.length
    || selectedServices.length
    || selectedDuration !== "all"
    || priceRange[1] < DEFAULT_MAX_PRICE,
  );

  return (
    <div className="bg-gray-50 min-h-screen py-8">
      <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 tracking-tight">
            Khám phá Tour Du Lịch
          </h1>
          <p className="text-gray-500 mt-2 text-sm">
            Tìm kiếm và lựa chọn hành trình phù hợp nhất với bạn
          </p>
        </div>

        <div className="flex flex-col lg:flex-row gap-8">
          <aside className="w-full lg:w-[320px] shrink-0">
            <TourFilters
              categories={categories}
              services={services}
              selectedCategories={selectedCategories}
              toggleCategory={toggleCategory}
              selectedServices={selectedServices}
              toggleService={toggleService}
              selectedDuration={selectedDuration}
              setSelectedDuration={setSelectedDuration}
              priceRange={priceRange}
              setPriceRange={setPriceRange}
              maxPrice={DEFAULT_MAX_PRICE}
              onReset={resetFilters}
            />
          </aside>

          <main className="flex-1">
            <div className="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-8 flex flex-col md:flex-row gap-4 items-center justify-between">
              <div className="relative w-full md:w-[400px]">
                <MagnifyingGlassIcon className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="Tìm kiếm điểm đến, tên tour..."
                  value={searchKeyword}
                  onChange={(e) => setSearchKeyword(e.target.value)}
                  className="w-full bg-gray-50 border border-gray-200 rounded-lg py-3 pl-12 pr-4 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white transition-all text-gray-800 placeholder-gray-400"
                />
              </div>

              <div className="flex items-center gap-3 w-full md:w-auto">
                <span className="text-sm text-gray-500 font-medium whitespace-nowrap">Sắp xếp:</span>
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value as typeof sortBy)}
                  className="bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-4 text-sm font-bold text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer"
                >
                  <option value="featured">Phổ biến nhất</option>
                  <option value="price_asc">Giá tăng dần</option>
                  <option value="price_desc">Giá giảm dần</option>
                </select>
              </div>
            </div>

            <div className="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
              <h2 className="text-xl font-bold text-gray-800">
                Tìm thấy <span className="text-primary-600">{tours.length}</span> kết quả phù hợp
              </h2>
              {hasActiveFilters && (
                <p className="text-xs font-medium text-gray-500">Kết quả đang được lọc theo điều kiện bạn chọn.</p>
              )}
            </div>

            {loading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 animate-pulse">
                {[1, 2, 3, 4, 5, 6].map((n) => (
                  <div key={n} className="bg-white rounded-xl border border-gray-100 shadow-sm h-[400px]" />
                ))}
              </div>
            ) : error ? (
              <div className="text-center py-20 bg-white rounded-xl border border-gray-100 shadow-sm flex flex-col items-center">
                <ExclamationTriangleIcon className="w-12 h-12 text-red-400 mb-4" />
                <h3 className="text-lg font-bold text-gray-800">Lỗi dữ liệu</h3>
                <p className="text-sm text-gray-500 mt-2">{error}</p>
                <button onClick={() => fetchTours(filterParams)} className="mt-6 bg-primary-600 text-white font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-primary-700 transition-colors">
                  Thử lại
                </button>
              </div>
            ) : tours.length === 0 ? (
              <div className="text-center py-20 bg-white rounded-xl border border-gray-100 shadow-sm flex flex-col items-center">
                <InboxIcon className="w-12 h-12 text-gray-300 mb-4" />
                <h3 className="text-lg font-bold text-gray-800">Không có Tour phù hợp</h3>
                <p className="text-sm text-gray-500 mt-2">Thử nới lỏng bộ lọc hoặc thay đổi từ khóa tìm kiếm nhé.</p>
                <button onClick={resetFilters} className="mt-6 bg-primary-50 text-primary-600 font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-primary-100 transition-colors">
                  Xóa toàn bộ lọc
                </button>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                {tours.map((tour) => <TourCard key={tour.id} tour={tour} />)}
              </div>
            )}
          </main>
        </div>
      </div>
    </div>
  );
};

export default Tours;