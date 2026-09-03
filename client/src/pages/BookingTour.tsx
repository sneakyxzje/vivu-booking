import bookingService from "@/services/bookingService";
import policyService from "@/services/policyService";
import tourService from "@/services/tourService";
import type { Tour, TourSchedule } from "@/types";
import { formatDateTime } from "@/utils/format";
import {
  getAvailableSlots,
  getScheduleUnavailableReason,
  getSeatCount,
  HAN_CHOT_MAC_DINH_NGAY,
  isBalanceDeadlinePassed,
  isScheduleBookable,
} from "@/utils/schedule";
import type { AxiosError } from "axios";
import type { ChangeEvent, FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";

type BookingFormState = {
  customerName: string;
  customerPhone: string;
  customerEmail: string;
  tourScheduleId: string;
  adultCount: number;
  childCount: number;
  infantCount: number;
  note: string;
  discountCode: string;
  /** Khách đã tích ô "đã đọc và đồng ý chính sách hủy". Máy chủ cũng đòi và ghi lại mốc này. */
  acceptTerms: boolean;
};

type BookingFormProps = {
  form: BookingFormState;
  tour: Tour;
  message: string | null;
  schedules: TourSchedule[];
  submitting: boolean;
  subtotalAmount: number;
  discountAmount: number;
  totalAmount: number;
  appliedDiscountCode: string | null;
  discountApplying: boolean;
  onApplyDiscount: () => void;
  onClearDiscount: () => void;
  onChange: (field: keyof BookingFormState, value: string | number | boolean) => void;
  onSubmit: (event: FormEvent) => void;
  /** Hạn chốt mặc định theo cấu hình máy chủ, áp cho chuyến không đặt hạn riêng. */
  hanChotNgay: number;
  /**
   * Điều kiện thanh toán hai đợt, đọc từ máy chủ.
   *
   * `depositPercent` bằng 100 nghĩa là thu đủ ngay khi đặt — lúc ấy khối cọc không hiện, vì nói
   * "đặt cọc 100%" là một câu vô nghĩa với người đọc.
   */
  depositPercent: number;
  depositAmount: number;
  balanceDueDays: number;
};

type BookingSidebarProps = {
  tour: Tour;
};

const initialForm: BookingFormState = {
  customerName: "",
  customerPhone: "",
  customerEmail: "",
  tourScheduleId: "",
  adultCount: 1,
  childCount: 0,
  infantCount: 0,
  note: "",
  discountCode: "",
  acceptTerms: false,
};

const formatCurrency = (value: number) =>
  `${value.toLocaleString("vi-VN")} VND`;

const getErrorMessage = (error: unknown) => {
  const axiosError = error as AxiosError<{ message?: string }>;
  return axiosError.response?.data?.message ?? "Không thể đặt tour. Vui lòng thử lại.";
};

const PageState = ({ children }: { children: string }) => (
  <div className="min-h-screen flex items-center justify-center">{children}</div>
);
// Lý do chuyến không đặt được nằm ở @/utils/schedule, dùng chung với thanh bên trang chi tiết
// và bộ lọc tự chọn chuyến. Đừng chép lại logic này về đây.
const getScheduleAvailableSlots = getAvailableSlots;

const BookingForm = ({
  form,
  tour,
  message,
  schedules,
  submitting,
  subtotalAmount,
  discountAmount,
  totalAmount,
  appliedDiscountCode,
  discountApplying,
  onApplyDiscount,
  onClearDiscount,
  onChange,
  onSubmit,
  hanChotNgay,
  depositPercent,
  depositAmount,
  balanceDueDays,
}: BookingFormProps) => {
  const totalGuestCount = form.adultCount + form.childCount + form.infantCount;
  /*
   * Kho chỗ trừ theo GHẾ, không theo người: em bé đi cùng bố mẹ không chiếm chỗ riêng.
   *
   * Trước đây màn này so tổng số người với số chỗ còn lại, tức tính em bé vào ghế — chặt hơn cả
   * máy chủ. Gia đình hai người lớn kèm một em bé bị khóa nút "+" khi chuyến còn đúng hai chỗ,
   * dù máy chủ chấp nhận đơn ấy không chút vướng mắc.
   */
  const seatCount = getSeatCount(form.adultCount, form.childCount);
  const selectedSchedule = schedules.find((schedule) => String(schedule.id) === form.tourScheduleId);
  const availableSlots = getScheduleAvailableSlots(selectedSchedule);
  const scheduleUnavailableReason = getScheduleUnavailableReason(
    selectedSchedule,
    tour.status,
    hanChotNgay,
  );
  const isOverCapacity = Boolean(selectedSchedule) && seatCount > availableSlots;
  /*
   * Chuyến này có được cọc không — hỏi theo CHUYẾN, không theo cấu hình chung.
   *
   * Tỷ lệ cọc là một con số của cả hệ thống, nhưng việc có chia đợt hay không thì phụ thuộc chuyến
   * khách chọn: hạn trả nốt là ngày khởi hành trừ mười ngày, nên chuyến đi trong tuần tới có hạn ấy
   * ở quá khứ và máy chủ thu đủ ngay. Trước đây màn này chỉ nhìn `depositPercent`, nên nó hứa cọc
   * 50% cho cả những chuyến sát ngày rồi cổng thanh toán đòi nguyên giá.
   */
  const quaHanTraNot = isBalanceDeadlinePassed(selectedSchedule, balanceDueDays);
  const coChiaDot = depositPercent < 100 && totalAmount > 0 && Boolean(selectedSchedule);

  const handleInputChange =
    (field: keyof BookingFormState) =>
      (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
        const numberFields: Array<keyof BookingFormState> = ["adultCount", "childCount", "infantCount"];
        const value = numberFields.includes(field) ? Number(event.target.value) : event.target.value;
        onChange(field, value);
      };
  const updateGuestCount = (
    field: "adultCount" | "childCount" | "infantCount",
    delta: number,
  ) => {
    const minimum = field === "adultCount" ? 1 : 0;
    const nextValue = Math.max(minimum, Number(form[field] || 0) + delta);

    // Chỉ người lớn và trẻ em ăn vào kho chỗ; thêm em bé không cần còn ghế trống.
    const nextSeats =
      field === "infantCount"
        ? seatCount
        : seatCount - Number(form[field] || 0) + nextValue;

    if (delta > 0 && (!selectedSchedule || scheduleUnavailableReason || nextSeats > availableSlots)) {
      return;
    }

    /*
     * Mỗi em bé phải có một người lớn đi kèm.
     *
     * Em bé không chiếm ghế nên phép kiểm số chỗ ở trên không chặn được các cháu — một người lớn
     * đi cùng tám em bé vẫn lọt, chiếm đúng một ghế và trả đúng một vé. Máy chủ đã chặn, nhưng để
     * khách đâm vào lỗi ở bước cuối sau khi điền xong cả biểu mẫu là tệ; khóa nút ngay tại đây.
     */
    if (field === "infantCount" && delta > 0 && nextValue > form.adultCount) {
      return;
    }

    /*
     * Bớt người lớn thì bớt em bé theo, để không rơi vào thế đơn không gửi được.
     *
     * Hai người lớn hai em bé rồi bấm bớt một người lớn: nếu chỉ đổi mỗi số người lớn thì biểu mẫu
     * đứng ở một trạng thái mà máy chủ từ chối, và khách phải tự đoán ra mình cần sửa cái gì.
     */
    if (field === "adultCount" && delta < 0 && form.infantCount > nextValue) {
      onChange("infantCount", nextValue);
    }

    onChange(field, nextValue);
  };

  const guestRows = [
    {
      field: "adultCount" as const,
      label: "Người lớn",
      note: "12+ tuổi",
      price: Number(tour.adult_price || 0),
    },
    {
      field: "childCount" as const,
      label: "Trẻ em",
      note: "2-12 tuổi",
      price: Number(tour.child_price || 0),
    },
    {
      field: "infantCount" as const,
      label: "Em bé",
      note: "Dưới 2 tuổi",
      price: Number(tour.infant_price || 0),
    },
  ];

  return (
    <form
      onSubmit={onSubmit}
      className="bg-white p-6 md:p-8 rounded-xl border border-gray-100 shadow-sm space-y-6"
    >
      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        {/* Họ tên */}
        <div className="space-y-1.5">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Họ và tên <span className="text-rose-500">*</span>
          </label>
          <input
            className="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium transition-all"
            placeholder="Nhập họ và tên người đi"
            value={form.customerName}
            onChange={handleInputChange("customerName")}
            required
          />
        </div>

        {/* Số điện thoại */}
        <div className="space-y-1.5">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Số điện thoại
          </label>
          <input
            className="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium transition-all"
            placeholder="09xxxxxxxx"
            value={form.customerPhone}
            onChange={handleInputChange("customerPhone")}
          />
        </div>

        {/* Email */}
        <div className="space-y-1.5 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Địa chỉ Email <span className="text-rose-500">*</span>
          </label>
          <input
            className="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium transition-all"
            placeholder="nguyenvanan@gmail.com"
            type="email"
            value={form.customerEmail}
            onChange={handleInputChange("customerEmail")}
            required
          />
        </div>

        {/* Lịch khởi hành */}
        <div className="space-y-1.5 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Chọn ngày khởi hành mong muốn <span className="text-rose-500">*</span>
          </label>
          <select
            className="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white font-medium transition-all"
            value={form.tourScheduleId}
            onChange={handleInputChange("tourScheduleId")}
            required
          >
            {schedules.map((schedule) => {
              const reason = getScheduleUnavailableReason(schedule, tour.status, hanChotNgay);
              return (
                <option key={schedule.id} value={schedule.id} disabled={Boolean(reason)}>
                  Khởi hành: {formatDateTime(schedule.start_date)} (Còn {getScheduleAvailableSlots(schedule)} chỗ){schedule.booking_deadline ? ` - Hạn chốt: ${formatDateTime(schedule.booking_deadline)}` : ""}{reason ? ` (${reason})` : ""}
                </option>
              );
            })}
          </select>
        </div>
        {/* Số khách theo loại */}
        <div className="md:col-span-2 rounded-lg border border-slate-200 bg-white p-4.5 space-y-3">
          <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider">
              Số lượng khách theo loại <span className="text-rose-500">*</span>
            </label>
            {selectedSchedule && (
              <span className={`text-xs font-semibold ${scheduleUnavailableReason ? "text-rose-600" : "text-gray-500"}`}>
                {scheduleUnavailableReason ?? `Còn lại ${availableSlots} chỗ`}
              </span>
            )}
          </div>

          <div className="grid gap-3">
            {guestRows.map((item) => (
              <div
                key={item.field}
                className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3.5 py-3"
              >
                <div className="min-w-0">
                  <p className="text-sm font-bold text-gray-900">{item.label}</p>
                  <p className="text-xs font-medium text-gray-500">
                    {item.note} · {formatCurrency(item.price)}
                  </p>
                </div>
                <div className="flex h-10 items-center rounded-xl border border-slate-200 bg-white">
                  <button
                    type="button"
                    onClick={() => updateGuestCount(item.field, -1)}
                    disabled={item.field === "adultCount" ? form[item.field] <= 1 : form[item.field] <= 0}
                    className="h-10 w-10 text-lg font-bold text-gray-500 hover:text-primary-600 disabled:opacity-35 disabled:hover:text-gray-500"
                    aria-label={`Giảm ${item.label}`}
                  >
                    -
                  </button>
                  <span className="w-10 text-center text-sm font-bold text-gray-900">
                    {form[item.field]}
                  </span>
                  <button
                    type="button"
                    onClick={() => updateGuestCount(item.field, 1)}
                    disabled={
                      !selectedSchedule ||
                      Boolean(scheduleUnavailableReason) ||
                      // Em bé không chiếm ghế nên không bị số chỗ còn lại chặn...
                      (item.field !== "infantCount" && seatCount >= availableSlots) ||
                      // ...nhưng bị chặn bởi số người lớn: một lòng, một bé.
                      (item.field === "infantCount" && form.infantCount >= form.adultCount)
                    }
                    className="h-10 w-10 text-lg font-bold text-gray-500 hover:text-primary-600 disabled:opacity-35 disabled:hover:text-gray-500"
                    aria-label={`Tăng ${item.label}`}
                  >
                    +
                  </button>
                </div>
              </div>
            ))}

            {/*
              Nói vì sao nút "+" của em bé tắt.

              Nút xám mà không có lời giải thích là chỗ người dùng bấm đi bấm lại rồi bỏ cuộc. Câu
              này chỉ hiện đúng lúc chạm trần, không nằm đó suốt để làm rối biểu mẫu.
            */}
            {form.infantCount >= form.adultCount && form.infantCount > 0 && (
              <p className="rounded-lg bg-amber-50 px-3.5 py-2.5 text-xs leading-relaxed text-amber-800">
                Mỗi em bé cần một người lớn đi kèm, nên số em bé không vượt quá số người lớn. Em bé
                dưới 2 tuổi ngồi cùng bố mẹ nên <b>không chiếm chỗ riêng</b> trên xe.
              </p>
            )}
          </div>

          <div className="rounded-lg bg-primary-50/70 border border-primary-100 px-4 py-3 space-y-2">
            <div className="flex items-center justify-between text-sm text-primary-800">
              <span className="font-semibold">Tổng số khách</span>
              <span className="font-bold">{totalGuestCount} khách</span>
            </div>
            {/*
              Nói rõ số ghế khi nó khác số người, để khách hiểu vì sao đơn ba người chỉ trừ hai
              chỗ của chuyến — nếu không thì con số "còn lại X chỗ" trông như tính sai.
            */}
            {form.infantCount > 0 && (
              <div className="flex items-center justify-between text-xs text-primary-700/80">
                <span>Số chỗ chiếm trên xe</span>
                <span className="font-semibold">
                  {seatCount} chỗ · em bé dưới 2 tuổi ngồi cùng bố mẹ
                </span>
              </div>
            )}
            <div className="flex items-center justify-between border-t border-primary-100 pt-2">
              <span className="text-sm font-semibold text-primary-800">Tổng giá trị thanh toán</span>
              <span className="text-xl font-bold text-primary-600">{formatCurrency(totalAmount)}</span>
            </div>
            {isOverCapacity && (
              <p className="text-xs font-semibold text-rose-600">
                Số khách đang vượt quá số chỗ còn lại của lịch khởi hành.
              </p>
            )}
            {scheduleUnavailableReason && (
              <p className="text-xs font-semibold text-rose-600 mt-1">
                {scheduleUnavailableReason}. Vui lòng chọn ngày khởi hành khác.
              </p>
            )}
          </div>
        </div>



        {/* Mã giảm giá */}
        <div className="space-y-1.5 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Mã giảm giá
          </label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <input
              className="flex-1 px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-semibold uppercase transition-all"
              placeholder="Nhập mã giảm giá"
              value={form.discountCode}
              onChange={handleInputChange("discountCode")}
              disabled={Boolean(appliedDiscountCode)}
            />
            {appliedDiscountCode ? (
              <button
                type="button"
                onClick={onClearDiscount}
                className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-gray-600 hover:bg-gray-50"
              >
                Bỏ mã
              </button>
            ) : (
              <button
                type="button"
                onClick={onApplyDiscount}
                disabled={discountApplying || !form.discountCode.trim()}
                className="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {discountApplying ? "Đang áp dụng..." : "Áp dụng"}
              </button>
            )}
          </div>
          {appliedDiscountCode && (
            <p className="text-xs font-semibold text-emerald-600">
              Đã áp dụng mã {appliedDiscountCode}, giảm {formatCurrency(discountAmount)}.
            </p>
          )}
        </div>

        {/* Ghi chú */}
        <div className="space-y-1.5 md:col-span-2">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Ghi chú thêm (nếu có)
          </label>
          <textarea
            className="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium transition-all"
            rows={3}
            placeholder="Ví dụ: Ăn chay, phòng có giường em bé..."
            value={form.note}
            onChange={handleInputChange("note")}
          />
        </div>

        {/*
          Danh sách hành khách KHÔNG khai ở đây nữa.

          Lúc bấm đặt, người đại diện thường chưa có trong tay số căn cước và ngày sinh của những
          người còn lại — bắt điền đủ trước khi thanh toán là bắt họ bỏ dở giỏ hàng đi hỏi từng
          người. Đặt chỗ chỉ cần số lượng và một người đại diện; danh sách khai sau qua liên kết
          riêng, hạn cuối là hạn chốt danh sách của chuyến.
        */}

      </div>

      {/* Tổng tiền */}
      <div className="bg-primary-50/60 border border-primary-100/50 px-6 py-4.5 rounded-lg space-y-2">
        <div className="flex items-center justify-between text-sm text-primary-800">
          <span className="font-semibold">Tạm tính</span>
          <span className="font-bold">{formatCurrency(subtotalAmount)}</span>
        </div>
        {discountAmount > 0 && (
          <div className="flex items-center justify-between text-sm text-emerald-700">
            <span className="font-semibold">Giảm giá</span>
            <span className="font-bold">- {formatCurrency(discountAmount)}</span>
          </div>
        )}
        <div className="flex items-center justify-between border-t border-primary-100 pt-2">
          <span className="text-sm font-semibold text-primary-800">Tổng giá trị đơn</span>
          <span className="text-xl font-bold text-primary-600">{formatCurrency(totalAmount)}</span>
        </div>

        {/*
          Số phải trả NGAY, tách khỏi giá trị đơn.

          Khách bấm đặt rồi thấy cổng thanh toán hiện một con số khác giá tour thì họ dừng lại tự hỏi
          có nhầm không. Nói trước ở đây, ngay cạnh tổng tiền, là chỗ duy nhất kịp.
        */}
        {coChiaDot && !quaHanTraNot && (
          <div className="space-y-1.5 rounded-lg border border-primary-200 bg-white px-4 py-3">
            <div className="flex items-center justify-between">
              <span className="text-sm font-bold text-primary-800">
                Đặt cọc hôm nay ({depositPercent}%)
              </span>
              <span className="text-lg font-bold text-primary-700">
                {formatCurrency(depositAmount)}
              </span>
            </div>
            <p className="text-xs leading-relaxed text-muted">
              Phần còn lại <b>{formatCurrency(totalAmount - depositAmount)}</b> thanh toán chậm nhất{" "}
              <b>{balanceDueDays} ngày trước ngày khởi hành</b>. Chúng tôi sẽ gửi thư nhắc trước hạn.
              Quá hạn mà chưa thanh toán, đơn bị hủy và khoản đặt cọc không được hoàn lại.
            </p>
          </div>
        )}

        {/*
          Chuyến sát ngày: nói thẳng là thu đủ, và nói vì sao.

          Im lặng ở đây cũng sai như hứa cọc: khách vừa đọc chính sách "đặt cọc 50%" ở trang trước,
          nên họ đến cổng thanh toán với một con số trong đầu. Một dòng giải thích rẻ hơn nhiều so
          với một cuộc gọi lên tổng đài hỏi sao bị tính gấp đôi.
        */}
        {coChiaDot && quaHanTraNot && (
          <div className="space-y-1.5 rounded-lg border border-amber-200 bg-amber-50/70 px-4 py-3">
            <div className="flex items-center justify-between">
              <span className="text-sm font-bold text-amber-900">Thanh toán hôm nay (100%)</span>
              <span className="text-lg font-bold text-amber-900">{formatCurrency(totalAmount)}</span>
            </div>
            <p className="text-xs leading-relaxed text-amber-800">
              Chuyến này khởi hành trong vòng <b>{balanceDueDays} ngày</b> nên không chia hai đợt:
              hạn thanh toán phần còn lại đã qua, đơn hàng được thu đủ ngay khi đặt. Đặt sớm hơn cho
              chuyến khác, bạn chỉ cần cọc {depositPercent}%.
            </p>
          </div>
        )}
      </div>

      {scheduleUnavailableReason ? (
        <div className="rounded-lg bg-rose-50 border border-rose-100 p-4 text-xs font-medium text-rose-700 flex items-center gap-2">
          <svg className="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
          {scheduleUnavailableReason}. Vui lòng chọn ngày khởi hành khác ở phần thông tin ngày đi.
        </div>
      ) : message ? (
        <div className="rounded-lg bg-rose-50 border border-rose-100 p-4 text-xs font-medium text-rose-700 flex items-center gap-2">
          <svg className="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
          {message}
        </div>
      ) : null}

      {/*
        Ô đồng ý điều khoản — bắt buộc, và chỉ có ô đồng ý.

        Bảng phí hủy từng nằm ngay đây, với lập luận rằng con số đọc được tại chỗ thì khó nói là
        chưa từng nhìn thấy. Nhưng nó chép lại một phần của trang chính sách vào giữa biểu mẫu đặt
        tour, và bản sao ấy phải tự đi tìm dữ liệu, tự dựng lại cách trình bày, tự đúng theo. Sửa
        bảng phí ở một nơi mà quên nơi kia là hai văn bản nói hai điều khác nhau về cùng một khoản
        tiền — và tờ nào cũng đứng tên công ty.

        Bằng chứng khách được cho xem điều khoản trước khi trả tiền vẫn còn nguyên: hệ thống ghi
        `terms_accepted_at` lúc họ tích ô này, và đơn chép sẵn bảng phí đang hiệu lực vào chính nó
        lúc tạo. Ô tích cộng liên kết là đủ cho việc ấy; bảng phí thuộc về trang chính sách.
      */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
        <label className="flex items-start gap-2.5 cursor-pointer">
          <input
            type="checkbox"
            checked={form.acceptTerms}
            onChange={(event) => onChange("acceptTerms", event.target.checked)}
            className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
          />
          <span className="text-xs text-gray-700 leading-relaxed">
            Tôi đã đọc và đồng ý với{" "}
            <a
              href="/chinh-sach"
              target="_blank"
              rel="noreferrer"
              className="font-semibold text-primary-600 underline"
            >
              chính sách hủy và hoàn tiền
            </a>{" "}
            của Vivu Booking. Tôi hiểu rằng mức hoàn tiền phụ thuộc vào thời điểm hủy.
          </span>
        </label>
      </div>

      <button
        className="w-full rounded-lg bg-primary-600 py-3.5 font-bold text-white shadow-md hover:bg-primary-700 hover:shadow-lg transition-all active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none text-sm cursor-pointer"
        disabled={
          submitting ||
          !form.tourScheduleId ||
          isOverCapacity ||
          Boolean(scheduleUnavailableReason) ||
          !form.acceptTerms
        }
      >
        {submitting
          ? "Đang xử lý đặt tour..."
          : scheduleUnavailableReason ??
            (form.acceptTerms ? "Xác nhận đặt tour" : "Vui lòng đồng ý điều khoản để tiếp tục")}
      </button>
    </form>
  );
};

const TourSummaryCard = ({ tour }: BookingSidebarProps) => (
  <div className="bg-white p-5 rounded-xl border border-gray-100 shadow-sm space-y-3">
    <span className="text-[10px] bg-primary-50 text-primary-700 border border-primary-200 px-2.5 py-0.5 rounded-lg font-bold uppercase tracking-wider">
      Thông tin Tour
    </span>
    <h3 className="font-bold text-gray-900 leading-snug text-base font-plus-jakarta mt-2">
      {tour.title}
    </h3>
    <div className="flex items-center gap-2 text-xs text-gray-500 mt-3 pt-3 border-t border-gray-50">
      <svg className="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <span className="font-medium text-gray-500">
        Hành trình: <strong className="text-gray-800">{tour.start_location} → {tour.end_location ?? "Chưa rõ"}</strong>
      </span>
    </div>
  </div>
);

const ScheduleCard = ({ schedules, selectedScheduleId }: { schedules: TourSchedule[]; selectedScheduleId: string }) => (
  <div className="bg-white p-5 rounded-xl border border-gray-100 shadow-sm space-y-4">
    <span className="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-lg font-bold uppercase tracking-wider">
      Lịch khởi hành
    </span>
    <div className="space-y-2.5 mt-2 max-h-64 overflow-y-auto pr-1">
      {schedules.length ? (
        schedules.map((schedule) => {
          const isSelected = String(schedule.id) === selectedScheduleId;
          return (
            <div
              key={schedule.id}
              className={`p-3.5 rounded-lg border transition-all duration-300 ${isSelected
                  ? "bg-primary-50/50 border-primary-300 text-primary-900 shadow-xs"
                  : "bg-gray-50/40 border-slate-200 text-gray-600"
                }`}
            >
              <div className="flex items-center justify-between">
                <span className={`text-sm font-bold ${isSelected ? "text-primary-800" : "text-gray-800"}`}>
                  {formatDateTime(schedule.start_date)}
                </span>
                {isSelected && (
                  <span className="text-[10px] bg-primary-600 text-white px-2 py-0.5 rounded-full font-bold">
                    Đang chọn
                  </span>
                )}
              </div>
              <div className="flex justify-between items-center text-xs text-gray-500 mt-2 font-medium">
                <span>Số ghế đã đặt:</span>
                <span className="font-semibold text-gray-700">
                  {schedule.booked_people} / {schedule.max_people}
                </span>
              </div>
            </div>
          );
        })
      ) : (
        <div className="text-xs text-gray-400 italic p-2">Chưa có lịch khởi hành cho tour này.</div>
      )}
    </div>
  </div>
);

const PriceSummaryCard = ({ tour }: BookingSidebarProps) => {
  const prices = [
    { label: "Người lớn", note: "12+ tuổi", value: tour.adult_price },
    { label: "Trẻ em", note: "2-12 tuổi", value: tour.child_price },
    { label: "Em bé", note: "< 2 tuổi", value: tour.infant_price },
  ];

  return (
    <div className="bg-white p-5 rounded-xl border border-gray-100 shadow-sm space-y-3">
      <span className="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 rounded-lg font-bold uppercase tracking-wider">
        Bảng giá
      </span>
      <div className="space-y-2 mt-2 pt-1">
        {prices.map((item) => (
          <div key={item.label} className="flex justify-between items-baseline gap-3 text-sm">
            <span className="font-semibold text-gray-600">
              {item.label} <span className="text-xs font-medium text-gray-400">({item.note})</span>
            </span>
            <span className="font-bold text-primary-600 whitespace-nowrap">
              {formatCurrency(Number(item.value || 0))}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
};
const BookingSidebar = ({ tour, selectedScheduleId }: BookingSidebarProps & { selectedScheduleId: string }) => {
  const schedules = tour.schedules ?? [];

  return (
    <div className="space-y-5">
      <TourSummaryCard tour={tour} />
      <ScheduleCard schedules={schedules} selectedScheduleId={selectedScheduleId} />
      <PriceSummaryCard tour={tour} />
    </div>
  );
};

export const BookingTour = () => {
  // Slug của tour trên đường dẫn. Máy chủ cũng nhận id, nên liên kết cũ dạng số vẫn mở được.
  const { slug } = useParams();
  const [searchParams] = useSearchParams();
  const [tour, setTour] = useState<Tour | null>(null);
  const [form, setForm] = useState<BookingFormState>(initialForm);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [discountAmount, setDiscountAmount] = useState(0);
  const [appliedDiscountCode, setAppliedDiscountCode] = useState<string | null>(null);
  const [discountApplying, setDiscountApplying] = useState(false);
  /*
   * Số ngày trước khởi hành mà chuyến ngừng nhận đặt, lấy từ cấu hình máy chủ.
   *
   * Chuyến không đặt hạn chốt riêng vẫn có hạn — máy chủ suy ra bằng ngày khởi hành trừ số ngày
   * này. Giao diện phải dùng đúng con số ấy, không đoán, nếu không nó mời khách vào một chuyến mà
   * máy chủ sẽ từ chối ở bước cuối.
   */
  const [hanChotNgay, setHanChotNgay] = useState(HAN_CHOT_MAC_DINH_NGAY);
  /*
   * Điều kiện thanh toán hai đợt, cùng nguồn với bảng phí hủy.
   *
   * Mặc định trước khi tải xong là 100% — tức không hiện khối cọc. Đoán sai theo chiều ngược lại
   * thì trang hứa "chỉ trả 50%" trong khi máy chủ lấy đủ tiền, và khách phát hiện ở cổng thanh toán.
   */
  const [depositPercent, setDepositPercent] = useState(100);
  const [balanceDueDays, setBalanceDueDays] = useState(0);
  const navigate = useNavigate();

  useEffect(() => {
    let huy = false;

    policyService
      .get()
      .then((data) => {
        if (huy || !data) return;

        setHanChotNgay(data.booking.deadline_days || HAN_CHOT_MAC_DINH_NGAY);
        setDepositPercent(data.payment?.deposit_percent ?? 100);
        setBalanceDueDays(data.payment?.balance_due_days ?? 0);
      })
      .catch(() => undefined);

    return () => {
      huy = true;
    };
  }, []);

  useEffect(() => {
    const loadTour = async () => {
      try {
        if (!slug) return;

        const response = await tourService.getById(slug);
        const schedules = response.data.schedules ?? [];
        const scheduleIdFromQuery = searchParams.get("schedule_id");
        const selectedSchedule = schedules.find(
          (schedule) => String(schedule.id) === scheduleIdFromQuery,
        );
        const firstBookableSchedule = schedules.find((schedule) =>
          isScheduleBookable(schedule, response.data.status),
        );
        const firstSchedule = selectedSchedule && isScheduleBookable(selectedSchedule, response.data.status)
          ? selectedSchedule
          : firstBookableSchedule;

        if (selectedSchedule && !isScheduleBookable(selectedSchedule, response.data.status)) {
          setMessage("Lịch khởi hành này hiện không khả dụng, vui lòng chọn lịch khác.");
        }
        const adultCount = Math.max(1, Number(searchParams.get("adult_count") ?? initialForm.adultCount));
        const childCount = Math.max(0, Number(searchParams.get("child_count") ?? initialForm.childCount));
        const infantCount = Math.max(0, Number(searchParams.get("infant_count") ?? initialForm.infantCount));

        setTour(response.data);
        setForm((current) => ({
          ...current,
          tourScheduleId: firstSchedule ? String(firstSchedule.id) : "",
          adultCount: Number.isFinite(adultCount) ? adultCount : initialForm.adultCount,
          childCount: Number.isFinite(childCount) ? childCount : initialForm.childCount,
          infantCount: Number.isFinite(infantCount) ? infantCount : initialForm.infantCount,
        }));
      } finally {
        setLoading(false);
      }
    };

    loadTour();
  }, [slug]);

  const schedules = tour?.schedules ?? [];
  const subtotalAmount = useMemo(
    () =>
      tour
        ? form.adultCount * Number(tour.adult_price || 0)
        + form.childCount * Number(tour.child_price || 0)
        + form.infantCount * Number(tour.infant_price || 0)
        : 0,
    [form.adultCount, form.childCount, form.infantCount, tour],
  );
  const totalAmount = useMemo(
    () => Math.max(0, subtotalAmount - discountAmount),
    [discountAmount, subtotalAmount],
  );


  const updateForm = (field: keyof BookingFormState, value: string | number | boolean) => {
    setForm((current) => ({ ...current, [field]: value }));
    if (["adultCount", "childCount", "infantCount", "discountCode"].includes(field)) {
      setAppliedDiscountCode(null);
      setDiscountAmount(0);
    }
  };

  const applyDiscountCode = async () => {
    if (!form.discountCode.trim() || subtotalAmount <= 0) return;

    setDiscountApplying(true);
    setMessage(null);

    try {
      const response = await bookingService.validateDiscountCode({
        code: form.discountCode.trim(),
        order_amount: subtotalAmount,
        // Để máy chủ kiểm luôn giới hạn theo người, đúng phép đếm mà lượt tạo đơn dùng.
        email: form.customerEmail.trim() || undefined,
      });
      setAppliedDiscountCode(response.data.data.code);
      setDiscountAmount(Number(response.data.data.discount_amount));
      setForm((current) => ({ ...current, discountCode: response.data.data.code }));
    } catch (error) {
      setAppliedDiscountCode(null);
      setDiscountAmount(0);
      setMessage(getErrorMessage(error));
    } finally {
      setDiscountApplying(false);
    }
  };

  const clearDiscountCode = () => {
    setAppliedDiscountCode(null);
    setDiscountAmount(0);
    setForm((current) => ({ ...current, discountCode: "" }));
  };
  /*
   * Đặt chỗ không gửi kèm danh sách hành khách nữa.
   *
   * Máy chủ vẫn nhận trường `passengers` (đơn đoàn và màn quản trị dùng), chỉ là form khách lẻ
   * thôi không gửi. Danh sách khai sau qua liên kết theo mã tra cứu, hạn cuối là hạn chốt danh
   * sách của chuyến.
   */
  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();

    if (!tour) return;

    setSubmitting(true);
    setMessage(null);

    try {
      const response = await bookingService.create({
        tour_id: tour.id,
        tour_schedule_id: Number(form.tourScheduleId),
        customer_name: form.customerName,
        customer_email: form.customerEmail,
        customer_phone: form.customerPhone,
        adult_count: Number(form.adultCount),
        child_count: Number(form.childCount),
        infant_count: Number(form.infantCount),
        note: form.note,
        discount_code: appliedDiscountCode ?? undefined,
        // Máy chủ đòi trường này và ghi lại mốc xác nhận lên đơn — ô tích chỉ nằm trong trình
        // duyệt thì không phải bằng chứng, nó biến mất ngay khi đóng trang.
        accept_terms: form.acceptTerms,
      });

      const booking = {
        ...response.data.data.booking,
        payment_url: response.data.data.payment_url,
      };

      navigate(`/booking-success/${booking.public_token ?? booking.id}`, {
        state: booking,
      });
    } catch (error) {
      setMessage(getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <PageState>Đang tải...</PageState>;
  }

  if (!tour) {
    return <PageState>Không tìm thấy tour</PageState>;
  }

  return (
    <div className="min-h-screen bg-slate-50/50 py-10 font-inter">
      <div className="max-w-6xl mx-auto px-4">
        <div className="grid lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 font-plus-jakarta tracking-tight">
                Đặt tour du lịch
              </h1>
              <p className="text-xs text-gray-500 mt-1">
                Điền đầy đủ thông tin bên dưới để bắt đầu đặt chỗ và nhận ưu đãi tốt nhất.
              </p>
            </div>

            <BookingForm
              form={form}
              tour={tour}
              message={message}
              schedules={schedules}
              submitting={submitting}
              subtotalAmount={subtotalAmount}
              discountAmount={discountAmount}
              totalAmount={totalAmount}
              appliedDiscountCode={appliedDiscountCode}
              discountApplying={discountApplying}
              onApplyDiscount={applyDiscountCode}
              onClearDiscount={clearDiscountCode}
              onChange={updateForm}
              onSubmit={handleSubmit}
              hanChotNgay={hanChotNgay}
              depositPercent={depositPercent}
              // Làm tròn về đồng nguyên đúng như máy chủ làm, để con số hiện ở đây khớp với con số
              // cổng thanh toán yêu cầu.
              depositAmount={Math.round((totalAmount * depositPercent) / 100)}
              balanceDueDays={balanceDueDays}
            />
          </div>

          <div className="lg:col-span-1">
            <BookingSidebar tour={tour} selectedScheduleId={form.tourScheduleId} />
          </div>
        </div>
      </div>
    </div>
  );
};

export default BookingTour;

