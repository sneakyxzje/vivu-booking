import bookingService from "@/services/bookingService";
import tourService from "@/services/tourService";
import type { Tour, TourSchedule } from "@/types";
import { formatDateTime } from "@/utils/format";
import {
  getAvailableSlots,
  getScheduleUnavailableReason,
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
  onSubmit: (event: FormEvent) => void;
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
}: BookingFormProps) => {
  const totalGuestCount = form.adultCount + form.childCount + form.infantCount;
  const selectedSchedule = schedules.find((schedule) => String(schedule.id) === form.tourScheduleId);
  const availableSlots = getScheduleAvailableSlots(selectedSchedule);
  const scheduleUnavailableReason = getScheduleUnavailableReason(selectedSchedule, tour.status);
  const isOverCapacity = Boolean(selectedSchedule) && totalGuestCount > availableSlots;

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

        {/*
          Danh sách hành khách KHÔNG khai ở đây nữa.

          Lúc bấm đặt, người đại diện thường chưa có trong tay số căn cước và ngày sinh của những
          người còn lại — bắt điền đủ trước khi thanh toán là bắt họ bỏ dở giỏ hàng đi hỏi từng
          người. Đặt chỗ chỉ cần số lượng và một người đại diện; danh sách khai sau qua liên kết
          riêng, hạn cuối là hạn chốt danh sách của chuyến.
        */}
        <div className="md:col-span-2 rounded-lg bg-primary-50/60 border border-primary-100 px-5 py-4">
          <p className="text-sm font-semibold text-primary-900">
            Thông tin từng hành khách khai sau
          </p>
          <p className="text-xs text-primary-800/80 mt-1 leading-relaxed">
            Đặt xong bạn sẽ nhận một liên kết để điền họ tên và giấy tờ của {totalGuestCount} khách
            trong đoàn. Cần có trước hạn chốt danh sách để làm bảo hiểm và khai báo lưu trú — không
            phải điền ngay bây giờ.
          </p>
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
  const navigate = useNavigate();

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

