import React, { useEffect, useMemo, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import guideService from "@/services/guideService";
import type { Tour } from "@/types";
import { TourStatusBadge } from "@/components/guide/GuideStatusBadge";
import { formatDateTime } from "@/utils/format";

const formatPrice = (v: number) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(v);

type StatusFilter = "all" | Tour["status"];

export const GuideTours: React.FC = () => {
  const [searchParams] = useSearchParams();
  const initialStatus = (searchParams.get("status") as StatusFilter) || "all";

  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>(initialStatus);
  const [search, setSearch] = useState("");

  useEffect(() => {
    guideService
      .getMyTours()
      .then((data) => setTours(data))
      .catch(() => setError("Không thể tải danh sách tour."))
      .finally(() => setLoading(false));
  }, []);

  const filtered = useMemo(() => {
    let result = [...tours];
    if (statusFilter !== "all") {
      result = result.filter((t) => t.status === statusFilter);
    }
    if (search.trim()) {
      const q = search.toLowerCase();
      result = result.filter(
        (t) =>
          t.title.toLowerCase().includes(q) ||
          t.start_location.toLowerCase().includes(q),
      );
    }
    return result;
  }, [tours, statusFilter, search]);

  const tabs: { key: StatusFilter; label: string }[] = [
    { key: "all", label: "Tất cả" },
    { key: "active", label: "Đang hoạt động" },
    { key: "inactive", label: "Tạm dừng" },
    { key: "full", label: "Hết chỗ" },
  ];

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Tour của tôi</h1>
        <p className="text-gray-500 text-sm mt-1">
          Theo dõi danh sách tour được admin phân công
        </p>
      </div>

      <div className="bg-white rounded-lg border border-gray-100 shadow-sm p-4 flex flex-col sm:flex-row gap-4">
        <input
          type="search"
          placeholder="Tìm theo tên tour, địa điểm..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="flex-1 rounded-xl bg-gray-50 border-none px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"
        />
        <div className="flex flex-wrap gap-2">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setStatusFilter(tab.key)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                statusFilter === tab.key
                  ? "bg-primary-600 text-white"
                  : "bg-gray-100 text-gray-600 hover:bg-gray-200"
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      {loading ? (
        <div className="text-center py-16 text-gray-500">Đang tải...</div>
      ) : filtered.length === 0 ? (
        <div className="bg-white rounded-lg border border-gray-100 p-12 text-center text-gray-500">
          Chưa có tour nào được phân công.
        </div>
      ) : (
        <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left text-gray-500 text-xs uppercase tracking-wide">
                  <th className="px-6 py-3 font-semibold">Tour</th>
                  <th className="px-4 py-3 font-semibold">Địa điểm</th>
                  <th className="px-4 py-3 font-semibold">Giá</th>
                  <th className="px-4 py-3 font-semibold">Thời lượng</th>
                  <th className="px-4 py-3 font-semibold">Dịch vụ đi kèm</th>
                  <th className="px-4 py-3 font-semibold">Trạng thái</th>
                  <th className="px-4 py-3 font-semibold">Điểm danh</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {filtered.map((tour) => (
                  <tr key={tour.id} className="hover:bg-gray-50/50">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <img
                          src={
                            tour.thumbnail ||
                            "https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=80"
                          }
                          alt=""
                          className="w-12 h-12 rounded-xl object-cover shrink-0"
                        />
                        <span className="font-medium text-gray-900 line-clamp-2 max-w-[220px]">
                          {tour.title}
                        </span>
                      </div>
                    </td>
                    <td className="px-4 py-4 text-gray-600">
                      {tour.start_location}
                    </td>
                    <td className="px-4 py-4 font-medium text-gray-900">
                      {formatPrice(tour.adult_price ?? tour.discount_price ?? tour.price)}
                    </td>
                    <td className="px-4 py-4 text-gray-600">
                      {tour.number_of_days}N
                      {tour.number_of_nights > 0
                        ? ` ${tour.number_of_nights}Đ`
                        : ""}
                    </td>
                    {/* Dịch vụ đi kèm tour - HDV cần biết để chuẩn bị */}
                    <td className="px-4 py-4">
                      {tour.services && tour.services.length > 0 ? (
                        <div className="flex flex-wrap gap-1 max-w-[200px]">
                          {tour.services.slice(0, 3).map((s) => (
                            <span
                              key={s.id}
                              title={s.description ?? s.name}
                              className="inline-flex items-center gap-1 bg-primary-50 text-primary-700 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-primary-100"
                            >
                              {s.icon && <span>{s.icon}</span>}
                              {s.name}
                            </span>
                          ))}
                          {tour.services.length > 3 && (
                            <span className="text-[10px] text-gray-400 font-medium">
                              +{tour.services.length - 3} khác
                            </span>
                          )}
                        </div>
                      ) : (
                        <span className="text-xs text-gray-400 italic">Chưa có</span>
                      )}
                    </td>
                    <td className="px-4 py-4">
                      <TourStatusBadge status={tour.status} />
                    </td>
                    <td className="px-4 py-4">
                      {tour.schedules && tour.schedules.length > 0 ? (
                        <div className="flex flex-col gap-1.5">
                          {tour.schedules.map((schedule) => (
                            <Link
                              key={schedule.id}
                              to={`/guide/attendance/${schedule.id}`}
                              className="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 bg-primary-50 hover:bg-primary-100 px-2.5 py-1.5 rounded-lg whitespace-nowrap"
                            >
                              {formatDateTime(schedule.start_date)}
                            </Link>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xs text-gray-400 italic">Chưa có lịch</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

export default GuideTours;
