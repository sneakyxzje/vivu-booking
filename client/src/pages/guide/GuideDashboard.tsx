import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import guideService from "@/services/guideService";
import type { GuideBooking, GuideDashboardStats } from "@/types/guide";
import type { Tour } from "@/types";
import {
  BookingStatusBadge,
  TourStatusBadge,
} from "@/components/guide/GuideStatusBadge";
import { formatDateTime, formatPrice } from "@/utils/format";

export const GuideDashboard: React.FC = () => {
  const [stats, setStats] = useState<GuideDashboardStats | null>(null);
  const [bookings, setBookings] = useState<GuideBooking[]>([]);
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      try {
        const [s, b, t] = await Promise.all([
          guideService.getDashboardStats(),
          guideService.getBookings(),
          guideService.getMyTours(),
        ]);
        setStats(s);
        setBookings(b);
        setTours(t);
      } catch {
        setError("Không thể tải dữ liệu tổng quan.");
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const fullTours = tours.filter((t) => t.status === "full");
  const recentBookings = bookings
    .filter((b) => b.status === "pending")
    .slice(0, 4);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64 text-gray-500">
        Đang tải...
      </div>
    );
  }

  if (error || !stats) {
    return (
      <div className="flex items-center justify-center h-64 text-red-500 text-sm">
        {error || "Không thể tải dữ liệu tổng quan."}
      </div>
    );
  }

  const statCards = [
    {
      label: "Tổng tour",
      value: stats.totalTours,
      sub: `${stats.activeTours} đang hoạt động`,
      color: "bg-primary-50 text-primary-600",
    },
    {
      label: "Hết chỗ",
      value: stats.fullTours,
      sub: "Tour đã kín chỗ",
      color: "bg-red-50 text-red-600",
    },
    {
      label: "Đặt chỗ",
      value: stats.totalBookings,
      sub: `${stats.pendingBookings} chờ xác nhận`,
      color: "bg-blue-50 text-blue-600",
    },
    {
      label: "Doanh thu",
      value: formatPrice(stats.revenue),
      sub: "Từ các đơn đã xác nhận",
      color: "bg-emerald-50 text-emerald-600",
      isPrice: true,
    },
  ];

  return (
    <div className="space-y-8 animate-fade-in">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Tổng quan</h1>
        <p className="text-gray-500 text-sm mt-1">
          Quản lý tour và đặt chỗ của bạn
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        {statCards.map((card) => (
          <div
            key={card.label}
            className="bg-white rounded-lg border border-gray-100 shadow-sm p-5"
          >
            <p className="text-sm text-gray-500 mb-2">{card.label}</p>
            <p
              className={`text-2xl font-bold ${card.isPrice ? "text-emerald-600 text-xl" : "text-gray-900"}`}
            >
              {card.value}
            </p>
            <p
              className={`text-xs mt-1 font-medium ${card.color.split(" ")[1]}`}
            >
              {card.sub}
            </p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 className="font-semibold text-gray-900">Tour hết chỗ</h2>
            <Link
              to="/guide/tours?status=full"
              className="text-sm text-primary-600 hover:underline"
            >
              Xem tất cả
            </Link>
          </div>
          {fullTours.length === 0 ? (
            <p className="p-6 text-sm text-gray-500">
              Không có tour hết chỗ.
            </p>
          ) : (
            <ul className="divide-y divide-gray-50">
              {fullTours.map((tour) => (
                <li
                  key={tour.id}
                  className="px-6 py-4 flex items-center justify-between gap-4"
                >
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {tour.title}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {tour.start_location} ·{" "}
                      {formatPrice(tour.adult_price ?? tour.discount_price ?? tour.price)}
                    </p>
                  </div>
                  <TourStatusBadge status={tour.status} />
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 className="font-semibold text-gray-900">
              Đặt chỗ chờ xác nhận
            </h2>
            <Link
              to="/guide/bookings?status=pending"
              className="text-sm text-primary-600 hover:underline"
            >
              Xem tất cả
            </Link>
          </div>
          {recentBookings.length === 0 ? (
            <p className="p-6 text-sm text-gray-500">Không có đặt chỗ mới.</p>
          ) : (
            <ul className="divide-y divide-gray-50">
              {recentBookings.map((b) => (
                <li
                  key={b.id}
                  className="px-6 py-4 flex items-center justify-between gap-4"
                >
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {b.customer_name}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {b.tour_title} · {b.guests} khách ·{" "}
                      {formatDateTime(b.departure_date)}
                    </p>
                  </div>
                  <BookingStatusBadge status={b.status} />
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
};

export default GuideDashboard;











