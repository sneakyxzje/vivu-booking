import React from "react";
import { useNavigate } from "react-router-dom";
import type { Tour, TourSchedule } from "@/types";
import { formatDateTime } from "@/utils/format";
import {
  getAvailableSlots,
  getScheduleUnavailableReason,
  isDeadlineOverdue,
  isScheduleBookable,
} from "@/utils/schedule";
import {
  ShieldCheckIcon,
  SupportIcon,
  CreditCardIcon,
} from "@/components/Icons";

interface TourRightSidebarProps {
  tour: Tour;
  /**
   * Chuyến đang chọn, do bảng "Lịch trình khởi hành" ở cột nội dung quyết định.
   *
   * Thanh bên chỉ ĐỌC, không đổi lựa chọn nữa — nên không còn `onScheduleChange`. Hai nơi cùng
   * sửa một trạng thái thì phải nhìn giống nhau, mà ô xổ xuống ở đây không hiện được giá, số chỗ
   * hay lý do chặn của từng ngày như bảng kia.
   */
  selectedSchedule: TourSchedule | null;
}

const formatPrice = (value: number | string) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(Number(value));

const getUnavailableReason = (schedule: TourSchedule | null, tour: Tour) =>
  getScheduleUnavailableReason(schedule, tour.status);

export const TourRightSidebar: React.FC<TourRightSidebarProps> = ({
  tour,
  selectedSchedule,
}) => {
  const navigate = useNavigate();

  const availableSlots = getAvailableSlots(selectedSchedule);
  const bookedPercent = selectedSchedule
    ? (selectedSchedule.booked_people / selectedSchedule.max_people) * 100
    : 0;
  const selectedUnavailableReason = getUnavailableReason(selectedSchedule, tour);

  const handleBooking = () => {
    if (!selectedSchedule || !isScheduleBookable(selectedSchedule, tour.status)) return;

    const params = new URLSearchParams({
      schedule_id: String(selectedSchedule.id),
    });

    navigate(`/tours/${tour.slug}/booking?${params.toString()}`);
  };

  return (
    <div className="lg:col-span-4 lg:sticky lg:top-24">
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6 md:p-7 space-y-6">
        <div>
          <p className="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">
            Giá tour trọn gói chỉ từ
          </p>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl md:text-3xl font-bold text-red-600 font-plus-jakarta">
              {formatPrice(tour.adult_price)}
            </span>
          </div>
        </div>

        {/*
          Không còn ô chọn ngày ở đây.

          Bảng "Lịch trình khởi hành" ở cột nội dung đã là chỗ chọn ngày, và nó hiện sẵn giá, số
          chỗ, hạn chốt cùng lý do không đặt được của từng chuyến. Giữ thêm một ô xổ xuống ở thanh
          bên là hai chỗ làm cùng một việc trên cùng một trang, mà cái ở đây lại nghèo thông tin
          hơn hẳn — người dùng chọn ở đâu cũng được nhưng nhìn thấy hai thứ khác nhau.

          Thanh bên giữ phần **báo lại chuyến đang chọn**: còn mấy chỗ, hạn chốt, và nút đặt.
        */}
        <div className="border-t border-gray-100 pt-4">
          <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
            Ngày khởi hành đang chọn
          </label>

          {tour.schedules?.length ? (
            <div className="space-y-3">
              <p className="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm font-semibold text-gray-800">
                {selectedSchedule
                  ? formatDateTime(selectedSchedule.start_date)
                  : "Chọn một ngày ở bảng Lịch trình khởi hành"}
              </p>

              {selectedSchedule && (
                <div className="bg-gray-50 p-3.5 rounded-xl border border-gray-200 text-xs">
                  <div className="flex justify-between font-semibold mb-2">
                    <span className="text-gray-500">Tình trạng chỗ</span>
                    <span className={`font-bold ${selectedUnavailableReason ? "text-red-600" : "text-primary-700"}`}>
                      {selectedUnavailableReason ?? `Còn trống ${availableSlots} chỗ`}
                    </span>
                  </div>

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

                  <div className="mt-3 pt-3 border-t border-gray-200 flex items-center justify-between text-gray-500 font-medium">
                    <span>Hạn chót đăng ký</span>
                    <span className={`font-bold ${isDeadlineOverdue(selectedSchedule) ? "text-red-600" : "text-gray-900"}`}>
                      {selectedSchedule.booking_deadline ? formatDateTime(selectedSchedule.booking_deadline) : "Không giới hạn"}
                    </span>
                  </div>
                  {isDeadlineOverdue(selectedSchedule) && (
                    <p className="mt-1 text-right text-[10px] font-bold uppercase text-red-650 animate-pulse">
                      Đã quá hạn chốt nhận khách
                    </p>
                  )}
                </div>
              )}
            </div>
          ) : (
            <div className="p-3 bg-rose-50 border border-rose-100 text-rose-700 rounded-xl text-xs font-semibold">
              Hiện chưa có lịch khởi hành mới cho tour này.
            </div>
          )}
        </div>

        <button
          disabled={!selectedSchedule || Boolean(selectedUnavailableReason)}
          onClick={handleBooking}
          className="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 rounded-xl shadow-md hover:shadow-lg transform active:scale-97 transition-all duration-300 disabled:opacity-50 disabled:pointer-events-none disabled:shadow-none text-center block text-sm cursor-pointer"
        >
          {selectedUnavailableReason ?? "Đặt tour ngay"}
        </button>

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

      <div className="mt-6 bg-white border border-gray-100 p-5 rounded-xl shadow-sm flex items-center gap-4">
        <div className="w-12 h-12 bg-primary-50 rounded-lg flex items-center justify-center text-primary-600 font-bold font-plus-jakarta text-lg">
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