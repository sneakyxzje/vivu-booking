import bookingService from "@/services/bookingService";
import tourService from "@/services/tourService";
import type { Tour, TourSchedule } from "@/types";
import type { AxiosError } from "axios";
import type { ChangeEvent, FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";

type BookingFormState = {
  customerName: string;
  customerPhone: string;
  customerEmail: string;
  tourScheduleId: string;
  guests: number;
  note: string;
};

type BookingFormProps = {
  form: BookingFormState;
  message: string | null;
  schedules: TourSchedule[];
  submitting: boolean;
  totalAmount: number;
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
  guests: 1,
  note: "",
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

const BookingForm = ({
  form,
  message,
  schedules,
  submitting,
  totalAmount,
  onChange,
  onSubmit,
}: BookingFormProps) => {
  const handleInputChange =
    (field: keyof BookingFormState) =>
    (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
      const value = field === "guests" ? Number(event.target.value) : event.target.value;
      onChange(field, value);
    };

  return (
    <form onSubmit={onSubmit} className="bg-white p-6 md:p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
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
            {schedules.map((schedule) => (
              <option key={schedule.id} value={schedule.id}>
                Khởi hành ngày: {schedule.start_date} (Còn lại: {schedule.max_people - schedule.booked_people} chỗ)
              </option>
            ))}
          </select>
        </div>

        {/* Số khách */}
        <div className="space-y-1.5">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider pl-0.5">
            Số lượng hành khách <span className="text-rose-500">*</span>
          </label>
          <input
            className="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium transition-all"
            type="number"
            placeholder="Số lượng hành khách"
            min={1}
            value={form.guests}
            onChange={handleInputChange("guests")}
            required
          />
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
                Danh sách thông tin hành khách tham gia ({form.guests} người)
              </h3>
              <p className="text-xs text-gray-500 mt-1">
                Vui lòng điền đầy đủ họ tên và giấy tờ cá nhân để Vivu Booking làm bảo hiểm du lịch & xếp vị trí xe.
              </p>
            </div>
          </div>

          <div className="space-y-4">
            {Array.from({ length: Math.max(1, form.guests) }).map((_, index) => (
              <div
                key={index}
                className="bg-slate-50/80 p-4.5 rounded-2xl border border-slate-200/90 space-y-3 relative"
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
                      defaultValue={index === 0 ? form.customerName : ""}
                      className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                      Loại khách & Ngày sinh
                    </label>
                    <div className="flex gap-2">
                      <select className="w-1/2 px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-700">
                        <option value="adult">Người lớn (&gt; 12 tuổi)</option>
                        <option value="child">Trẻ em (2-11 tuổi)</option>
                        <option value="infant">Em bé (&lt; 2 tuổi)</option>
                      </select>
                      <input
                        type="date"
                        className="w-1/2 px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500 text-gray-700"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                      Số CCCD / CMND / Hộ chiếu
                    </label>
                    <input
                      type="text"
                      placeholder="Nhập số giấy tờ cá nhân..."
                      className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-gray-600 mb-1">
                      Yêu cầu / Ghi chú đặc biệt
                    </label>
                    <input
                      type="text"
                      placeholder="VD: Ăn chay, say xe..."
                      className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl font-medium focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Tổng tiền */}
      <div className="bg-primary-50/60 border border-primary-100/50 px-6 py-4.5 rounded-2xl flex items-center justify-between">
        <span className="text-sm font-semibold text-primary-800">Tổng giá trị thanh toán</span>
        <span className="text-xl font-extrabold text-primary-600">{formatCurrency(totalAmount)}</span>
      </div>

      {message ? (
        <div className="rounded-2xl bg-rose-50 border border-rose-100 p-4 text-xs font-medium text-rose-700 flex items-center gap-2">
          <svg className="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
          {message}
        </div>
      ) : null}

      <button
        className="w-full rounded-2xl bg-primary-600 py-3.5 font-bold text-white shadow-md hover:bg-primary-700 hover:shadow-lg transition-all active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none text-sm"
        disabled={submitting || !form.tourScheduleId}
      >
        {submitting ? "Đang xử lý đặt tour..." : "Xác nhận đặt tour"}
      </button>
    </form>
  );
};

const TourSummaryCard = ({ tour }: BookingSidebarProps) => (
  <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm space-y-3">
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
  <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm space-y-4">
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
              className={`p-3.5 rounded-2xl border transition-all duration-300 ${
                isSelected
                  ? "bg-primary-50/50 border-primary-300 text-primary-900 shadow-xs"
                  : "bg-gray-50/40 border-slate-200 text-gray-600"
              }`}
            >
              <div className="flex items-center justify-between">
                <span className={`text-sm font-bold ${isSelected ? "text-primary-800" : "text-gray-800"}`}>
                  {schedule.start_date}
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
  const unitPrice = tour.discount_price || tour.price;

  return (
    <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm space-y-3">
      <span className="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 rounded-lg font-bold uppercase tracking-wider">
        Tóm tắt giá
      </span>
      <div className="flex justify-between items-baseline mt-2 pt-1">
        <span className="text-xs font-semibold text-gray-550 uppercase tracking-wider">Mỗi khách hàng:</span>
        <div className="text-right">
          <span className="text-lg font-extrabold text-primary-600">
            {formatCurrency(unitPrice)}
          </span>
          {tour.discount_price ? (
            <span className="block text-xs text-gray-400 line-through mt-0.5 font-medium">
              {formatCurrency(tour.price)}
            </span>
          ) : null}
        </div>
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
  const [tour, setTour] = useState<Tour | null>(null);
  const [form, setForm] = useState<BookingFormState>(initialForm);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const navigate = useNavigate();

  useEffect(() => {
    const loadTour = async () => {
      try {
        if (!id) return;

        const response = await tourService.getById(id);
        const firstSchedule = response.data.schedules?.[0];

        setTour(response.data);
        setForm((current) => ({
          ...current,
          tourScheduleId: firstSchedule ? String(firstSchedule.id) : "",
        }));
      } finally {
        setLoading(false);
      }
    };

    loadTour();
  }, [id]);

  const schedules = tour?.schedules ?? [];
  const unitPrice = tour ? tour.discount_price || tour.price : 0;
  const totalAmount = useMemo(
    () => unitPrice * Number(form.guests || 1),
    [form.guests, unitPrice],
  );

  const updateForm = (field: keyof BookingFormState, value: string | number) => {
    setForm((current) => ({ ...current, [field]: value }));
  };
  // const handleSubmit = async (event: FormEvent) => {
  //   event.preventDefault();
  //   if (!tour) return;

  //   setSubmitting(true);
  //   setMessage(null);

  //   try {
  //     const response = await bookingService.create({
  //       tour_id: tour.id,
  //       tour_schedule_id: Number(form.tourScheduleId),
  //       customer_name: form.customerName,
  //       customer_email: form.customerEmail,
  //       customer_phone: form.customerPhone,
  //       guests: Number(form.guests),
  //       note: form.note,
  //     });

  //     const paymentUrl = response.data.data.payment_url;

  //     if (paymentUrl) {
  //       window.location.href = paymentUrl;
  //       return;
  //     }


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
      guests: Number(form.guests),
      note: form.note,
    });

    const booking = {
      ...response.data.data.booking,
      payment_url: response.data.data.payment_url,
    };

    navigate(`/booking-success/${booking.id}`, {
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
              message={message}
              schedules={schedules}
              submitting={submitting}
              totalAmount={totalAmount}
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

