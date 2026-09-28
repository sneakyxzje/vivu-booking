import React from "react";
import { Tag } from "antd";
import type { Tour } from "@/types";
import type { BookingStatus } from "@/types/guide";

type TourStatus = Tour["status"];

const tourStyles: Record<TourStatus, string> = {
  active: "success",
  inactive: "default",
  full: "error",
};

const tourLabels: Record<TourStatus, string> = {
  active: "Đang hoạt động",
  inactive: "Tạm dừng",
  full: "Hết chỗ",
};

const bookingStyles: Record<BookingStatus, string> = {
  pending: "warning",
  confirmed: "success",
  cancelled: "error",
};

const bookingLabels: Record<BookingStatus, string> = {
  pending: "Chờ xác nhận",
  confirmed: "Đã xác nhận",
  cancelled: "Đã hủy",
};

export const TourStatusBadge: React.FC<{ status: TourStatus }> = ({
  status,
}) => (
  <Tag color={tourStyles[status]}>
    {tourLabels[status]}
  </Tag>
);

export const BookingStatusBadge: React.FC<{ status: BookingStatus }> = ({
  status,
}) => (
  <Tag color={bookingStyles[status]}>
    {bookingLabels[status]}
  </Tag>
);
