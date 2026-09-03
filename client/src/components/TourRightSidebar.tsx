import React from "react";
import { useNavigate } from "react-router-dom";
import type { Tour, TourSchedule } from "@/types";
import {
  getScheduleUnavailableReason,
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
          Thanh bên không nhắc lại chuyện chuyến nữa.

          Ngày khởi hành, số chỗ còn, hạn chốt và lý do không đặt được đều đã nằm trong thẻ chuyến
          đang chọn ở bảng "Lịch trình khởi hành" — nơi người dùng vừa bấm để chọn. In lại chúng
          cách đó một cột là bắt người ta đọc hai lần cùng một thứ, và mỗi lần thêm một chỗ có thể
          lệch khi dữ liệu đổi.

          Còn lại đúng ba thứ ở đây: giá từ, nút đặt, và mấy dòng cam kết.

          Nút vẫn tự nói được tình trạng: không có chuyến nào chọn được thì `getUnavailableReason`
          trả "Tạm hết lịch", và nhãn nút thành đúng câu ấy kèm trạng thái vô hiệu.
        */}
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