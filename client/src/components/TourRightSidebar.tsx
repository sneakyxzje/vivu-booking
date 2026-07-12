import React from "react";
import { useNavigate } from "react-router-dom";
import type { Tour } from "@/types";
import {
  ShieldCheckIcon,
  SupportIcon,
  CreditCardIcon,
} from "@/components/Icons";

interface TourRightSidebarProps {
  tour: Tour;
  selectedSchedule: any;
  onScheduleChange: (schedule: any) => void;
}

const formatPrice = (value: number | string) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(Number(value));

export const TourRightSidebar: React.FC<TourRightSidebarProps> = ({
  tour,
  selectedSchedule,
  onScheduleChange,
}) => {
  const navigate = useNavigate();

  const availableSlots = selectedSchedule
    ? selectedSchedule.max_people - selectedSchedule.booked_people
    : 0;
  const bookedPercent = selectedSchedule
    ? (selectedSchedule.booked_people / selectedSchedule.max_people) * 100
    : 0;

  const handleSelectChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const sId = Number(e.target.value);
    const found = tour.schedules?.find((s: any) => s.id === sId);
    if (found) {
      onScheduleChange(found);
    }
  };

  return (
    <div className="lg:col-span-4 lg:sticky lg:top-24">
      <div className="bg-white rounded-3xl border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.025)] p-6 md:p-7 space-y-6">
        {/* Price section */}
        <div>
          <p className="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">
            Giá tour trọn gói chỉ từ
          </p>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl md:text-3xl font-black text-red-600 font-plus-jakarta">
              {formatPrice(tour.discount_price || tour.price)}
            </span>
            {tour.discount_price && (
              <span className="text-sm text-gray-400 line-through font-medium font-mono">
                {formatPrice(tour.price)}
              </span>
            )}
          </div>
        </div>

        {/* Schedules departure dates */}
        <div className="border-t border-gray-100 pt-4">
          <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
            Chọn Ngày Khởi Hành
          </label>

          {tour.schedules?.length ? (
            <div className="space-y-3">
              <select
                value={selectedSchedule?.id || ""}
                onChange={handleSelectChange}
                className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-3.5 text-sm font-semibold text-gray-800 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-all duration-300"
              >
                {tour.schedules.map((schedule: any) => (
                  <option key={schedule.id} value={schedule.id}>
                    {schedule.start_date}
                  </option>
                ))}
              </select>

              {/* Progress Bar & Available slots info */}
              {selectedSchedule && (
                <div className="bg-gray-50 p-3.5 rounded-xl border border-gray-200 text-xs">
                  <div className="flex justify-between font-semibold mb-2">
                    <span className="text-gray-500">Tình trạng chỗ</span>
                    <span className={`font-bold ${availableSlots > 0 ? "text-primary-700" : "text-red-600"}`}>
                      {availableSlots > 0 ? `Còn trống ${availableSlots} chỗ` : "Hết chỗ"}
                    </span>
                  </div>

                  {/* Progress line */}
                  <div className="w-full bg-gray-200 h-2 rounded-full overflow-hidden mb-2">
                    <div
                      className={`h-full rounded-full transition-all duration-500 ${
                        bookedPercent >= 80 ? "bg-red-500" : "bg-primary-600"
                      }`}
                      style={{ width: `${bookedPercent}%` }}
                    ></div>
                  </div>

                  <div className="text-[10px] text-gray-400 font-mono">
                    Đã đặt: {selectedSchedule.booked_people} / {selectedSchedule.max_people} khách tối đa.
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="p-3 bg-rose-50 border border-rose-100 text-rose-700 rounded-xl text-xs font-semibold">
              Hiện chưa có lịch khởi hành mới cho tour này.
            </div>
          )}
        </div>

        {/* Book button */}
        <button
          disabled={!selectedSchedule || availableSlots <= 0 || tour.status === "inactive"}
          onClick={() => navigate(`/tours/${tour.id}/booking`)}
          className="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 rounded-xl shadow-md hover:shadow-lg transform active:scale-97 transition-all duration-300 disabled:opacity-50 disabled:pointer-events-none disabled:shadow-none text-center block text-sm cursor-pointer"
        >
          {!selectedSchedule
            ? "Tạm hết lịch"
            : availableSlots <= 0
            ? "Đã hết chỗ"
            : "Đặt tour ngay"}
        </button>

        {/* Safety Badges */}
        <div className="border-t border-gray-100 pt-4 space-y-3.5 text-xs text-gray-500">
          <div className="flex items-center gap-2.5">
            <ShieldCheckIcon className="w-5 h-5 text-emerald-500 shrink-0" />
            <span>Xác nhận giao dịch an toàn & tức thì</span>
          </div>
          <div className="flex items-center gap-2.5">
            <SupportIcon className="w-5 h-5 text-primary-600 shrink-0" />
            <span>Hỗ trợ khách hàng chuyên nghiệp 24/7</span>
          </div>
          <div className="flex items-center gap-2.5">
            <CreditCardIcon className="w-5 h-5 text-primary-600 shrink-0" />
            <span>Nhiều phương thức thanh toán an toàn, linh hoạt</span>
          </div>
        </div>
      </div>

      {/* Operator Card */}
      <div className="mt-6 bg-white border border-gray-100 p-5 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.015)] flex items-center gap-4">
        <div className="w-12 h-12 bg-primary-50 rounded-2xl flex items-center justify-center text-primary-600 font-bold font-plus-jakarta text-lg">
          VB
        </div>
        <div>
          <h5 className="font-bold text-gray-900 text-sm">Vivu Booking</h5>
          <p className="text-xs text-gray-400">Đơn vị tổ chức lữ hành chuyên nghiệp</p>
          <a
            href="tel:19001234"
            className="inline-block text-xs font-bold text-primary-600 mt-1 hover:underline"
          >
            Hotline: 1900 1234
          </a>
        </div>
      </div>
    </div>
  );
};
