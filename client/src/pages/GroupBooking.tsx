import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Building2, Phone, Search, Users } from "lucide-react";
import tourService from "@/services/tourService";
import bookingService from "@/services/bookingService";
import type { GroupBookingPublicView, Tour } from "@/types";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * Đặt tour theo đoàn — phía khách.
 *
 * Trang này KHÔNG bán chỗ. Nó nhận yêu cầu: đoàn đông không đặt như khách lẻ — không kế toán nào
 * duyệt chuyển 80 triệu qua cổng trong mười phút giữ chỗ, giá đoàn phải thương lượng, và lúc gửi
 * yêu cầu công ty còn chưa biết chính xác những ai đi. Điều hành sẽ gọi lại báo giá; mã tra cứu
 * là chìa khóa theo dõi cả quá trình, không cần tài khoản.
 */
export default function GroupBooking() {
  const [searchParams] = useSearchParams();

  const [tours, setTours] = useState<Tour[]>([]);
  const [tourId, setTourId] = useState("");
  const [schedules, setSchedules] = useState<{ id: number; start_date: string }[]>([]);
  const [scheduleId, setScheduleId] = useState("");

  const [contactName, setContactName] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [contactPhone, setContactPhone] = useState("");
  const [guests, setGuests] = useState("20");
  const [companyName, setCompanyName] = useState("");
  const [taxCode, setTaxCode] = useState("");
  const [invoiceAddress, setInvoiceAddress] = useState("");
  const [note, setNote] = useState("");

  const [sending, setSending] = useState(false);
  const [formError, setFormError] = useState("");
  const [sentToken, setSentToken] = useState("");

  // --- Tra cứu ---
  const [lookupCode, setLookupCode] = useState(searchParams.get("code") ?? "");
  const [looking, setLooking] = useState(false);
  const [lookupError, setLookupError] = useState("");
  const [view, setView] = useState<GroupBookingPublicView | null>(null);

  useEffect(() => {
    tourService
      .getAll()
      .then((res) => setTours(res.data))
      .catch((err) => console.error("Lỗi tải danh sách tour:", err));
  }, []);

  // Chọn tour thì tải các ngày khởi hành còn nhận đặt của tour đó.
  useEffect(() => {
    setSchedules([]);
    setScheduleId("");
    if (!tourId) return;

    tourService
      .getById(tourId)
      .then((res) => {
        const mo = (res.data.schedules ?? []).filter((s) => s.status === "open");
        setSchedules(mo.map((s) => ({ id: s.id, start_date: s.start_date })));
      })
      .catch((err) => console.error("Lỗi tải lịch khởi hành:", err));
  }, [tourId]);

  const gui = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setFormError("");

    try {
      const res = await bookingService.createGroupRequest({
        tour_id: Number(tourId),
        tour_schedule_id: Number(scheduleId),
        contact_name: contactName.trim(),
        contact_email: contactEmail.trim(),
        contact_phone: contactPhone.trim(),
        estimated_guests: Number(guests),
        company_name: companyName.trim() || undefined,
        tax_code: taxCode.trim() || undefined,
        invoice_address: invoiceAddress.trim() || undefined,
        note: note.trim() || undefined,
      });

      setSentToken(res.data?.data?.public_token ?? "");
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
        ?.response?.data;
      const firstFieldError = response?.errors ? Object.values(response.errors)[0]?.[0] : null;
      setFormError(firstFieldError || response?.message || "Không gửi được yêu cầu.");
    } finally {
      setSending(false);
    }
  };

  const traCuu = async (code?: string) => {
    const token = (code ?? lookupCode).trim();
    if (!token) return;

    setLooking(true);
    setLookupError("");
    setView(null);

    try {
      const res = await bookingService.getGroupRequest(token);
      setView(res.data?.data ?? null);
    } catch {
      setLookupError("Không tìm thấy yêu cầu với mã này. Kiểm tra lại mã trong thư xác nhận.");
    } finally {
      setLooking(false);
    }
  };

  const rut = async () => {
    if (!view) return;
    try {
      await bookingService.withdrawGroupRequest(view.public_token);
      traCuu(view.public_token);
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setLookupError(response?.message || "Không rút được yêu cầu.");
    }
  };

  return (
    <div className="mx-auto max-w-5xl px-4 py-10 space-y-10">
      <div className="text-center space-y-3">
        <h1 className="text-3xl font-bold text-gray-900 tracking-tight">Đặt tour theo đoàn</h1>
        <p className="mx-auto max-w-2xl text-sm text-gray-500">
          Đoàn từ 5 người trở lên: công ty, trường học, hội nhóm. Bạn gửi yêu cầu — điều hành gọi
          lại <b>báo giá riêng cho đoàn</b>, thống nhất xong mới chốt chỗ và thanh toán nhiều đợt.
          Không cần trả tiền ngay khi gửi.
        </p>

        {/* Ba bước, nói trước để khách không chờ một cái giá hiện ra ngay */}
        <div className="mx-auto grid max-w-3xl grid-cols-1 gap-3 sm:grid-cols-3 text-left">
          {[
            { icon: <Users className="h-4 w-4" />, title: "1. Gửi yêu cầu", text: "Chọn chuyến, ước tính số người. Chưa cần danh sách tên." },
            { icon: <Phone className="h-4 w-4" />, title: "2. Nhận báo giá", text: "Điều hành gọi lại thương lượng giá đoàn, thường mềm hơn giá lẻ." },
            { icon: <Building2 className="h-4 w-4" />, title: "3. Chốt và đặt cọc", text: "Đồng ý giá thì chốt chỗ, đặt cọc, danh sách khách nộp sau." },
          ].map((item) => (
            <div key={item.title} className="rounded-xl border border-gray-100 bg-white p-4">
              <p className="flex items-center gap-1.5 text-sm font-bold text-primary-700">
                {item.icon}
                {item.title}
              </p>
              <p className="mt-1 text-xs text-gray-500">{item.text}</p>
            </div>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-5">
        {/* Form gửi yêu cầu */}
        <div className="lg:col-span-3">
          {sentToken ? (
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 space-y-3">
              <h2 className="text-lg font-bold text-emerald-900">Đã nhận yêu cầu của bạn</h2>
              <p className="text-sm text-emerald-800">
                Điều hành sẽ liên hệ báo giá qua số điện thoại bạn để lại. Đây là <b>mã tra cứu</b>
                {" "}— giữ lại để theo dõi yêu cầu, ai giữ mã người đó xem được:
              </p>
              <p className="rounded-lg bg-white px-4 py-3 text-center font-mono text-sm font-bold text-gray-900 break-all">
                {sentToken}
              </p>
              <button
                type="button"
                onClick={() => {
                  setView(null);
                  setLookupCode(sentToken);
                  setSentToken("");
                  traCuu(sentToken);
                }}
                className="text-sm font-semibold text-emerald-800 hover:underline cursor-pointer"
              >
                Xem trạng thái yêu cầu →
              </button>
            </div>
          ) : (
            <form onSubmit={gui} className="rounded-2xl border border-gray-100 bg-white p-6 space-y-4 shadow-xs">
              <h2 className="text-lg font-bold text-gray-900">Gửi yêu cầu</h2>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Tour</label>
                  <select
                    required
                    value={tourId}
                    onChange={(e) => setTourId(e.target.value)}
                    className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm cursor-pointer focus:outline-none focus:border-primary-500"
                  >
                    <option value="">— Chọn tour —</option>
                    {tours.map((t) => (
                      <option key={t.id} value={t.id}>{t.title}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Ngày khởi hành</label>
                  <select
                    required
                    value={scheduleId}
                    onChange={(e) => setScheduleId(e.target.value)}
                    disabled={!tourId}
                    className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm cursor-pointer disabled:bg-gray-50 disabled:text-gray-400 focus:outline-none focus:border-primary-500"
                  >
                    <option value="">
                      {tourId && schedules.length === 0 ? "Tour này chưa có chuyến nhận đặt" : "— Chọn ngày —"}
                    </option>
                    {schedules.map((s) => (
                      <option key={s.id} value={s.id}>{formatDateTime(s.start_date)}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Người đại diện</label>
                  <input
                    required
                    type="text"
                    value={contactName}
                    onChange={(e) => setContactName(e.target.value)}
                    placeholder="Người điều hành sẽ gọi cho ai?"
                    className="w-full rounded-md border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    Số người (ước tính)
                  </label>
                  <input
                    required
                    type="number"
                    min={5}
                    max={500}
                    value={guests}
                    onChange={(e) => setGuests(e.target.value)}
                    className="w-full rounded-md border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                  />
                  <span className="mt-1 block text-[10px] text-gray-400">
                    Con số ước tính là đủ — số chính xác chốt sau khi thống nhất giá.
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Điện thoại</label>
                  <input
                    required
                    type="tel"
                    value={contactPhone}
                    onChange={(e) => setContactPhone(e.target.value)}
                    className="w-full rounded-md border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</label>
                  <input
                    required
                    type="email"
                    value={contactEmail}
                    onChange={(e) => setContactEmail(e.target.value)}
                    className="w-full rounded-md border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                  />
                </div>
              </div>

              {/* Đoàn doanh nghiệp gần như luôn cần hóa đơn — hỏi ngay từ đầu đỡ một cuộc gọi */}
              <details className="rounded-lg border border-gray-100 bg-gray-50/50 p-3">
                <summary className="cursor-pointer text-xs font-semibold text-gray-600">
                  Thông tin xuất hóa đơn VAT (nếu cần)
                </summary>
                <div className="mt-3 space-y-3">
                  <input
                    type="text"
                    value={companyName}
                    onChange={(e) => setCompanyName(e.target.value)}
                    placeholder="Tên công ty"
                    className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                  />
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input
                      type="text"
                      value={taxCode}
                      onChange={(e) => setTaxCode(e.target.value)}
                      placeholder="Mã số thuế"
                      className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                    />
                    <input
                      type="text"
                      value={invoiceAddress}
                      onChange={(e) => setInvoiceAddress(e.target.value)}
                      placeholder="Địa chỉ xuất hóa đơn"
                      className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                    />
                  </div>
                </div>
              </details>

              <div>
                <label className="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Yêu cầu riêng</label>
                <textarea
                  rows={2}
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder="VD: Đoàn có 3 người ăn chay, muốn thêm gala tối ngày cuối..."
                  className="w-full rounded-md border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
                />
              </div>

              {formError && (
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">{formError}</p>
              )}

              <button
                type="submit"
                disabled={sending}
                className="w-full rounded-lg bg-primary-600 py-3 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-40 cursor-pointer"
              >
                {sending ? "Đang gửi..." : "Gửi yêu cầu — chưa phải trả tiền"}
              </button>
            </form>
          )}
        </div>

        {/* Tra cứu */}
        <div className="lg:col-span-2 space-y-4">
          <div className="rounded-2xl border border-gray-100 bg-white p-6 space-y-3 shadow-xs">
            <h2 className="flex items-center gap-2 text-lg font-bold text-gray-900">
              <Search className="h-4 w-4" />
              Tra cứu yêu cầu
            </h2>
            <div className="flex gap-2">
              <input
                type="text"
                value={lookupCode}
                onChange={(e) => setLookupCode(e.target.value)}
                placeholder="Dán mã tra cứu..."
                className="min-w-0 flex-1 rounded-md border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm font-mono focus:outline-none focus:border-primary-500"
              />
              <button
                type="button"
                onClick={() => traCuu()}
                disabled={looking || !lookupCode.trim()}
                className="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-40 cursor-pointer"
              >
                {looking ? "..." : "Xem"}
              </button>
            </div>

            {lookupError && <p className="text-xs font-medium text-rose-600">{lookupError}</p>}

            {view && (
              <div className="space-y-3 rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-sm font-bold text-gray-900">{view.tour_title}</span>
                  <span className="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-gray-700 border border-gray-200">
                    {view.status_label}
                  </span>
                </div>
                <p className="text-xs text-gray-500">
                  Khởi hành {formatDateTime(view.start_date ?? "")} · ước tính {view.estimated_guests} người
                </p>

                {view.quote && (
                  <div className={`rounded-lg p-3 text-sm ${view.quote.expired ? "bg-gray-100 text-gray-500" : "bg-sky-50 text-sky-900"}`}>
                    <p>
                      Báo giá: <b>{formatPrice(view.quote.price_per_person)}</b>/người
                      {view.quote.free_slots > 0 && <>, miễn phí {view.quote.free_slots} suất</>}
                    </p>
                    {view.quote.note && <p className="mt-0.5 text-xs">{view.quote.note}</p>}
                    <p className="mt-0.5 text-xs">
                      {view.quote.expired
                        ? "Báo giá đã hết hiệu lực — liên hệ điều hành để nhận giá mới."
                        : `Hiệu lực tới ${formatDateTime(view.quote.expires_at ?? "")}. Đồng ý thì gọi điều hành để chốt.`}
                    </p>
                  </div>
                )}

                {view.rejected_reason && (
                  <p className="rounded-lg bg-rose-50 p-3 text-xs text-rose-800">{view.rejected_reason}</p>
                )}

                {view.booking && (
                  <div className="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                    <p>
                      Đã chốt: <b>{view.booking.guests} khách</b> · tổng{" "}
                      <b>{formatPrice(view.booking.total_amount)}</b>
                      {view.booking.paid_in_full ? " · đã thanh toán đủ" : " · đang thanh toán theo đợt"}
                    </p>
                    <Link
                      to={`/booking-lookup?code=${view.booking.public_token}`}
                      className="mt-1 inline-block text-xs font-semibold text-emerald-800 hover:underline"
                    >
                      Xem đơn và khai danh sách khách →
                    </Link>
                  </div>
                )}

                {(view.status === "pending_quote" || view.status === "quoted") && (
                  <button
                    type="button"
                    onClick={rut}
                    className="text-xs font-semibold text-rose-600 hover:underline cursor-pointer"
                  >
                    Rút yêu cầu này
                  </button>
                )}
              </div>
            )}
          </div>

          <p className="rounded-xl border border-gray-100 bg-white p-4 text-xs text-gray-400">
            Đoàn dưới 5 người? <Link to="/tours" className="font-semibold text-primary-600 hover:underline">Đặt theo form khách lẻ</Link> — thấy giá ngay và giữ chỗ trực tuyến.
          </p>
        </div>
      </div>
    </div>
  );
}
