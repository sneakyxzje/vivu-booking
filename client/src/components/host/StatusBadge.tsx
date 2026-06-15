import React from "react";
import type { Tour } from "@/types";
import type { BookingStatus } from "@/types/host";

type TourStatus = Tour["status"];

const tourStyles: Record<TourStatus, string> = {
  pending: "bg-amber-50 text-amber-700 border-amber-200",
  active: "bg-emerald-50 text-emerald-700 border-emerald-200",
  inactive: "bg-gray-100 text-gray-600 border-gray-200",
};

const tourLabels: Record<TourStatus, string> = {
  pending: "Chờ duyệt",
  active: "Đang hoạt động",
  inactive: "Ngừng",
};

const bookingStyles: Record<BookingStatus, string> = {
  pending: "bg-amber-50 text-amber-700 border-amber-200",
  confirmed: "bg-emerald-50 text-emerald-700 border-emerald-200",
  cancelled: "bg-red-50 text-red-600 border-red-200",
};

const bookingLabels: Record<BookingStatus, string> = {
  pending: "Chờ xác nhận",
  confirmed: "Đã xác nhận",
  cancelled: "Đã hủy",
};

export const TourStatusBadge: React.FC<{ status: TourStatus }> = ({
  status,
}) => (
  <span
    className={`inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border ${tourStyles[status]}`}
  >
    {tourLabels[status]}
  </span>
);

export const BookingStatusBadge: React.FC<{ status: BookingStatus }> = ({
  status,
}) => (
  <span
    className={`inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border ${bookingStyles[status]}`}
  >
    {bookingLabels[status]}
  </span>
);
