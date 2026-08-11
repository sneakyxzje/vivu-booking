import bookingService from "@/services/bookingService";
import tourService from "@/services/tourService";
import type { Tour, TourSchedule } from "@/types";
import { formatDateTime } from "@/utils/format";
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
  onChange: (field: keyof BookingFormState, value: string | number) => void;
  onSubmit: (event: FormEvent, passengers: PassengerFormItem[]) => void;
};

type PassengerFormItem = {
  name: string;
  type: PassengerType;
  dateOfBirth: string;
  identityNumber: string;
  note: string;
};

type BookingSidebarProps = {
  tour: Tour;
};

type PassengerType = "adult" | "child" | "infant";

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
const getScheduleAvailableSlots = (schedule: TourSchedule | null | undefined) =>
  schedule ? schedule.max_people - schedule.booked_people : 0;

const isScheduleDeadlineOverdue = (schedule: TourSchedule | null | undefined) =>
  schedule?.booking_deadline ? new Date(schedule.booking_deadline) < new Date() : false;

const getScheduleUnavailableReason = (
  schedule: TourSchedule | null | undefined,
  tourStatus?: Tour["status"],
) => {
  if (!schedule) return "Tạm hết lịch";
  if (tourStatus === "inactive") return "Tour đang tạm ngừng";
  if (schedule.status !== "open") {
    return "Lịch khởi hành này hiện không khả dụng";
  }
  if (isScheduleDeadlineOverdue(schedule)) return "Đã quá hạn đăng ký";
  if (getScheduleAvailableSlots(schedule) <= 0) return "Đã hết chỗ";
  return null;
};

const isScheduleBookable = (schedule: TourSchedule | null | undefined, tourStatus?: Tour["status"]) =>
  getScheduleUnavailableReason(schedule, tourStatus) === null;

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
}: BookingFormProps) => {
  const totalGuestCount = form.adultCount + form.childCount + form.infantCount;
  const selectedSchedule = schedules.find((schedule) => String(schedule.id) === form.tourScheduleId);
  const availableSlots = getScheduleAvailableSlots(selectedSchedule);
  const scheduleUnavailableReason = getScheduleUnavailableReason(selectedSchedule, tour.status);
  const isOverCapacity = Boolean(selectedSchedule) && totalGuestCount > availableSlots;
  const defaultPassengerTypes = useMemo<PassengerType[]>(
    () => [
      ...Array.from({ length: form.adultCount }, () => "adult" as const),
      ...Array.from({ length: form.childCount }, () => "child" as const),
      ...Array.from({ length: form.infantCount }, () => "infant" as const),
    ],
    [form.adultCount, form.childCount, form.infantCount],
  );
  const [passengers, setPassengers] = useState<PassengerFormItem[]>([]);

  // Đồng bộ số dòng hành khách với số lượng khách đã chọn, giữ lại nội dung đã nhập
  useEffect(() => {
    setPassengers((current) =>
      defaultPassengerTypes.map((fallback, index) => ({
        name: current[index]?.name ?? "",
        type: current[index]?.type ?? fallback,
        dateOfBirth: current[index]?.dateOfBirth ?? "",
        identityNumber: current[index]?.identityNumber ?? "",
        note: current[index]?.note ?? "",
      })),
    );
  }, [defaultPassengerTypes]);

  const updatePassenger = (
    index: number,
    field: keyof PassengerFormItem,
    value: string,
  ) => {
    setPassengers((current) =>
      current.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
    );
  };

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
    const nextTotal = totalGuestCount - Number(form[field] || 0) + nextValue;

    if (delta > 0 && (!selectedSchedule || scheduleUnavailableReason || nextTotal > availableSlots)) return;

    onChange(field, nextValue);
  };

  const guestRows = [
    {
      field: "adultCount" as const,
      label: "Người lớn",
      note: "12+ tuổi",
      price: Number(tour.adult_price || tour.discount_price || tour.price || 0),
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
      onSubmit={(event) => onSubmit(event, passengers)}
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
              const reason = getScheduleUnavailableReason(schedule, tour.status);
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
                    disabled={!selectedSchedule || Boolean(scheduleUnavailableReason) || totalGuestCount >= availableSlots}
                    className="h-10 w-10 text-lg font-bold text-gray-500 hover:text-primary-600 disabled:opacity-35 disabled:hover:text-gray-500"
                    aria-label={`Tăng ${item.label}`}
                  >
                    +
                  </button>
                </div>
              </div>
            ))}
          </div>

          <div className="rounded-lg bg-primary-50/70 border border-primary-100 px-4 py-3 space-y-2">
            <div className="flex items-center justify-between text-sm text-primary-800">
              <span className="font-semibold">Tổng số khách</span>
              <span className="font-bold">{totalGuestCount} khách</span>
            </div>
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

        {/* SECTION: THÔNG TIN CHI TIẾT HÀNH KHÁCH THAM GIA (CHỨC NĂNG 3) */}
        <div className="md:col-span-2 pt-4 border-t border-gray-100 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <span className="w-2 h-2 rounded-full bg-primary-600"></span>
                Danh sách thông tin hành khách tham gia ({totalGuestCount} người)
              </h3>
              <p className="text-xs text-gray-500 mt-1">
                Vui lòng điền đầy đủ họ tên và giấy tờ cá nhân để Vivu Booking làm bảo hiểm du lịch & xếp vị trí xe.
              </p>
            </div>
          </div>

          <div className="space-y-4">
            {passengers.map((passenger, index) => {
              const passengerType = passenger.type;
              const requiresIdentityDocument = passengerType === "adult";

              return (
                <div
                  key={index}
                  className="bg-slate-50/80 p-4.5 rounded-lg border border-slate-200/90 space-y-3 relative"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                      <span className="w-5 h-5 rounded-full bg-primary-600 text-white font-bold flex items-center justify-center text-[10px]">
                        {index + 1}
                      </span>
                      Hành khách #{index + 1}
                    </span>
                    <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-md ${index === 0 ? 'bg-primary-100 text-primary-700' : 'bg-gray-200 text-gray-600'}`}>
                      {index === 0 ? "Người đại diện đặt tour" : "Hành khách đi cùng"}
                    </span>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                      <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                        Họ và tên đầy đủ <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        placeholder="VD: NGUYEN VAN A"
                        value={passenger.name}
                        onChange={(event) => updatePassenger(index, "name", event.target.value)}
                        className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500"
                        required
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                        Loại khách & Ngày sinh
                      </label>
                      <div className="flex gap-2">
                        <select
                          className="w-1/2 px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-700"
                          value={passengerType}
                          onChange={(event) => updatePassenger(index, "type", event.target.value)}
                        >
                          <option value="adult">Người lớn (12+ tuổi)</option>
                          <option value="child">Trẻ em (2-12 tuổi)</option>
                          <option value="infant">Em bé (&lt; 2 tuổi)</option>
                        </select>
                        <input
                          type="date"
                          value={passenger.dateOfBirth}
                          onChange={(event) => updatePassenger(index, "dateOfBirth", event.target.value)}
                          className="w-1/2 px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-700"
                        />
                      </div>
                    </div>

                    {requiresIdentityDocument && (
                      <div>
                        <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                          Số CCCD / CMND / Hộ chiếu
                        </label>
                        <input
                          type="text"
                          placeholder="Nhập số giấy tờ cá nhân..."
                          value={passenger.identityNumber}
                          onChange={(event) => updatePassenger(index, "identityNumber", event.target.value)}
                          className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500"
                        />
                      </div>
                    )}

                    <div>
                      <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                        Yêu cầu / Ghi chú đặc biệt
                      </label>
                      <input
                        type="text"
                        placeholder="VD: Ăn chay, say xe..."
                        value={passenger.note}
                        onChange={(event) => updatePassenger(index, "note", event.target.value)}
                        className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500"
                      />
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
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
          <span className="text-sm font-semibold text-primary-800">Tổng giá trị thanh toán</span>
          <span className="text-xl font-bold text-primary-600">{formatCurrency(totalAmount)}</span>
        </div>
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

      <button
        className="w-full rounded-lg bg-primary-600 py-3.5 font-bold text-white shadow-md hover:bg-primary-700 hover:shadow-lg transition-all active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none text-sm cursor-pointer"
        disabled={submitting || !form.tourScheduleId || isOverCapacity || Boolean(scheduleUnavailableReason)}
      >
        {submitting ? "Đang xử lý đặt tour..." : scheduleUnavailableReason ?? "Xác nhận đặt tour"}
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
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const [tour, setTour] = useState<Tour | null>(null);
  const [form, setForm] = useState<BookingFormState>(initialForm);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [discountAmount, setDiscountAmount] = useState(0);
  const [appliedDiscountCode, setAppliedDiscountCode] = useState<string | null>(null);
  const [discountApplying, setDiscountApplying] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    const loadTour = async () => {
      try {
        if (!id) return;

        const response = await tourService.getById(id);
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
  }, [id]);

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

  const updateForm = (field: keyof BookingFormState, value: string | number) => {
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
  const handleSubmit = async (event: FormEvent, passengers: PassengerFormItem[]) => {
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
        passengers: passengers
          .filter((passenger) => passenger.name.trim())
          .map((passenger) => ({
            name: passenger.name.trim(),
            type: passenger.type,
            date_of_birth: passenger.dateOfBirth || null,
            identity_number: passenger.identityNumber.trim() || null,
            note: passenger.note.trim() || null,
          })),
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

