import bookingService from "@/services/bookingService";
import tourService from "@/services/tourService";
import type { Tour, TourSchedule } from "@/types";
import type { AxiosError } from "axios";
import type { ChangeEvent, FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { useParams } from "react-router-dom";

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
  errors: {
  customerName: string;
  customerPhone: string;
  customerEmail: string;
  tourScheduleId: string;
  guests: string;
};
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
  errors,
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
    <form onSubmit={onSubmit} className="grid md:grid-cols-2 gap-4">
      <input
        className="border rounded-xl p-3"
        placeholder="Họ và tên"
        value={form.customerName}
        onChange={handleInputChange("customerName")}
        required
      />
      {errors.customerName && (
  <p className="text-red-500 text-sm">
    {errors.customerName}
  </p>
)}

      <input
        className="border rounded-xl p-3"
        placeholder="Số điện thoại"
        value={form.customerPhone}
        onChange={handleInputChange("customerPhone")}
      />
      {errors.customerPhone && (
  <p className="text-red-500 text-sm">
    {errors.customerPhone}
  </p>
)}

      <input
        className="border rounded-xl p-3 md:col-span-2"
        placeholder="Email"
        type="email"
        value={form.customerEmail}
        onChange={handleInputChange("customerEmail")}
        required
      />
      {errors.customerEmail && (
  <p className="text-red-500 text-sm">
    {errors.customerEmail}
  </p>
)}

      <select
        className="border rounded-xl p-3 md:col-span-2"
        value={form.tourScheduleId}
        onChange={handleInputChange("tourScheduleId")}
        required
      >
        {schedules.map((schedule) => (
          <option key={schedule.id} value={schedule.id}>
            {schedule.start_date}
          </option>
        ))}
      </select>
      {errors.tourScheduleId && (
  <p className="text-red-500 text-sm">
    {errors.tourScheduleId}
  </p>
)}

      <input
        className="border rounded-xl p-3"
        type="number"
        placeholder="Số khách"
        min={1}
        value={form.guests}
        onChange={handleInputChange("guests")}
        required
      />
      {errors.guests && (
  <p className="text-red-500 text-sm mt-1">
    {errors.guests}
  </p>
)}

      <textarea
        className="border rounded-xl p-3 md:col-span-2"
        rows={4}
        placeholder="Ghi chú"
        value={form.note}
        onChange={handleInputChange("note")}
      />

      <div className="rounded-xl border p-4 flex items-center justify-between md:col-span-2">
        <span className="font-medium">Tổng tiền</span>
        <span className="text-xl font-bold">{formatCurrency(totalAmount)}</span>
      </div>

      {message ? (
        <div className="rounded-xl bg-gray-50 p-3 text-sm text-gray-700 md:col-span-2">
          {message}
        </div>
      ) : null}

      <button
        className="w-full rounded-xl bg-primary-600 py-3 font-semibold text-white disabled:opacity-60 md:col-span-2"
        disabled={submitting || !form.tourScheduleId}
      >
        {submitting ? "Đang đặt tour..." : "Xác nhận đặt tour"}
      </button>
    </form>
  );
};

const TourSummaryCard = ({ tour }: BookingSidebarProps) => (
  <div className="rounded-xl border p-4">
    <div className="text-sm text-gray-500">Tour bạn đang đặt</div>
    <div className="font-semibold mt-1">{tour.title}</div>
    <div className="text-sm text-gray-600 mt-2">
      {tour.start_location} - {tour.end_location ?? "Chưa có điểm đến"}
    </div>
  </div>
);

const ScheduleCard = ({ schedules }: { schedules: TourSchedule[] }) => (
  <div className="rounded-xl border p-4">
    <div className="text-sm text-gray-500">Thông tin chuyến khởi hành</div>
    <div className="mt-3 space-y-3">
      {schedules.length ? (
        schedules.map((schedule) => (
          <div key={schedule.id} className="rounded-lg bg-gray-50 p-3">
            <div className="font-medium">{schedule.start_date}</div>
            <div className="text-sm text-gray-600 mt-1">
              Đã đặt {schedule.booked_people}/{schedule.max_people}
            </div>
            <div className="text-xs uppercase tracking-wide text-gray-500 mt-1">
              {schedule.status}
            </div>
          </div>
        ))
      ) : (
        <div className="text-sm text-gray-500">Chưa có lịch khởi hành</div>
      )}
    </div>
  </div>
);

const PriceSummaryCard = ({ tour }: BookingSidebarProps) => {
  const unitPrice = tour.discount_price || tour.price;

  return (
    <div className="rounded-xl border p-4">
      <div className="text-sm text-gray-500">Tóm tắt giá</div>
      <div className="text-xl font-bold mt-1">{formatCurrency(unitPrice)}</div>
      {tour.discount_price ? (
        <div className="text-sm text-gray-400 line-through mt-1">
          {formatCurrency(tour.price)}
        </div>
      ) : null}
    </div>
  );
};

const BookingSidebar = ({ tour }: BookingSidebarProps) => {
  const schedules = tour.schedules ?? [];

  return (
    <div className="space-y-4">
      <TourSummaryCard tour={tour} />
      <ScheduleCard schedules={schedules} />
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
  const [errors, setErrors] = useState({
  customerName: "",
  customerPhone: "",
  customerEmail: "",
  tourScheduleId: "",
  guests: "",
});

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
  setForm((current) => ({
    ...current,
    [field]: value,
  }));

  setErrors((current) => ({
    ...current,
    [field]: "",
  }));
};

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    const newErrors = {
  customerName: "",
  customerPhone: "",
  customerEmail: "",
  tourScheduleId: "",
  guests: "",
};

let hasError = false;

if (!form.customerName.trim()) {
  newErrors.customerName = "Vui lòng nhập họ tên";
  hasError = true;
}

const phoneRegex = /^(0|\+84)[0-9]{9}$/;

if (!form.customerPhone.trim()) {
  newErrors.customerPhone = "Vui lòng nhập số điện thoại";
  hasError = true;
} else if (!phoneRegex.test(form.customerPhone)) {
  newErrors.customerPhone = "Số điện thoại không hợp lệ";
  hasError = true;
}

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

if (!form.customerEmail.trim()) {
  newErrors.customerEmail = "Vui lòng nhập email";
  hasError = true;
} else if (!emailRegex.test(form.customerEmail)) {
  newErrors.customerEmail = "Email không hợp lệ";
  hasError = true;
}

if (!form.tourScheduleId) {
  newErrors.tourScheduleId = "Vui lòng chọn ngày khởi hành";
  hasError = true;
}

if (form.guests < 1) {
  newErrors.guests = "Số khách phải lớn hơn 0";
  hasError = true;
}

setErrors(newErrors);

if (hasError) return;
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

      const paymentUrl = response.data.data.payment_url;

      if (paymentUrl) {
        window.location.href = paymentUrl;
        return;
      }

      setMessage("Đặt tour thành công nhưng chưa tạo được link thanh toán VNPay.");
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
    <div className="min-h-screen bg-white">
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="grid lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-4">
            <h1 className="text-2xl font-bold">Đặt tour</h1>
            <BookingForm
  form={form}
  errors={errors}
  message={message}
  schedules={schedules}
  submitting={submitting}
  totalAmount={totalAmount}
  onChange={updateForm}
  onSubmit={handleSubmit}
/>
          </div>

          <BookingSidebar tour={tour} />
        </div>
      </div>
    </div>
  );
};

export default BookingTour;
