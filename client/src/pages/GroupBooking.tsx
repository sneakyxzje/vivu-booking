import { Form, Alert, Select, InputNumber, Collapse, Button as AntButton, Input as AntInput } from "antd";
import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Building2, Phone, Search, Users } from "lucide-react";
import bookingService from "@/services/bookingService";
import tourService from "@/services/tourService";
import type { GroupBookingPublicView, Tour } from "@/types";
import { formatDateTime, formatPrice } from "@/utils/format";
import { validateEmail, validatePhone } from "@/utils/validation";

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
    if (sending) return;
    if (!tourId || !scheduleId) { setFormError("Vui lòng chọn tour và ngày khởi hành."); return; }
    if (!Number.isInteger(Number(guests)) || Number(guests) < 5 || Number(guests) > 500) {
      setFormError("Vui lòng nhập số người từ 5 đến 500.");
      return;
    }

    if (!validateEmail(contactEmail.trim())) {
      setFormError("Địa chỉ Email liên hệ không hợp lệ.");
      return;
    }

    if (!validatePhone(contactPhone.trim())) {
      setFormError("Số điện thoại liên hệ không hợp lệ (gồm 10 chữ số).");
      return;
    }

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
              <AntButton
                htmlType="button"
                onClick={() => {
                  setView(null);
                  setLookupCode(sentToken);
                  setSentToken("");
                  traCuu(sentToken);
                }}

              >
                Xem trạng thái yêu cầu →
              </AntButton>
            </div>
          ) : (
            <Form component={false} layout="vertical"><form onSubmit={gui} className="rounded-2xl border border-gray-100 bg-white p-6 space-y-4 shadow-xs">
              <h2 className="text-lg font-bold text-gray-900">Gửi yêu cầu</h2>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Form.Item label={<>Tour</>} htmlFor="groupbooking-field-1" style={{ marginBottom: 0 }}>
                  <Select id="groupbooking-field-1" aria-label="Tour" style={{ width: "100%" }} value={tourId || undefined} placeholder="Chọn tour" showSearch optionFilterProp="label"
                    onChange={setTourId} options={tours.map(t => ({ value: String(t.id), label: t.title }))} />
                </Form.Item>

                <Form.Item label={<>Ngày khởi hành</>} htmlFor="groupbooking-field-2" style={{ marginBottom: 0 }}>
                  <Select id="groupbooking-field-2" aria-label="Ngày khởi hành" style={{ width: "100%" }} value={scheduleId || undefined} onChange={setScheduleId} disabled={!tourId}
                    placeholder={tourId && schedules.length === 0 ? "Tour này chưa có chuyến nhận đặt" : "Chọn ngày"}
                    options={schedules.map(s => ({ value: String(s.id), label: formatDateTime(s.start_date) }))} />
                </Form.Item>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Form.Item label={<>Người đại diện</>} htmlFor="groupbooking-field-3" style={{ marginBottom: 0 }}>
                  <AntInput id="groupbooking-field-3"
                    required
                    type="text"
                    value={contactName}
                    onChange={(e) => setContactName(e.target.value)}
                    placeholder="Người điều hành sẽ gọi cho ai?"

                  />
                </Form.Item>
                <Form.Item label={<>Số người (ước tính)
                </>} htmlFor="groupbooking-field-4" style={{ marginBottom: 0 }}>
                  <InputNumber id="groupbooking-field-4" aria-label="Số người ước tính" style={{ width: "100%" }} required min={5} max={500} precision={0} value={guests ? Number(guests) : null} onChange={value => setGuests(value === null ? "" : String(value))} />
                  <span className="mt-1 block text-[10px] text-gray-400">
                    Con số ước tính là đủ — số chính xác chốt sau khi thống nhất giá.
                  </span>
                </Form.Item>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Form.Item label={<>Điện thoại</>} htmlFor="groupbooking-field-5" style={{ marginBottom: 0 }}>
                  <AntInput id="groupbooking-field-5"
                    required
                    type="tel"
                    value={contactPhone}
                    onChange={(e) => setContactPhone(e.target.value)}

                  />
                </Form.Item>
                <Form.Item label={<>Email</>} htmlFor="groupbooking-field-6" style={{ marginBottom: 0 }}>
                  <AntInput id="groupbooking-field-6"
                    required
                    type="email"
                    value={contactEmail}
                    onChange={(e) => setContactEmail(e.target.value)}

                  />
                </Form.Item>
              </div>

              {/* Đoàn doanh nghiệp gần như luôn cần hóa đơn — hỏi ngay từ đầu đỡ một cuộc gọi */}
              <Collapse items={[{
                key: "invoice", label: "Thông tin xuất hóa đơn VAT (nếu cần)", children: <div className="space-y-3">
                  <AntInput aria-label="Tên công ty" value={companyName} onChange={e => setCompanyName(e.target.value)} placeholder="Tên công ty" />
                  <AntInput aria-label="Mã số thuế" value={taxCode} onChange={e => setTaxCode(e.target.value)} placeholder="Mã số thuế" />
                  <AntInput aria-label="Địa chỉ xuất hóa đơn" value={invoiceAddress} onChange={e => setInvoiceAddress(e.target.value)} placeholder="Địa chỉ xuất hóa đơn" />
                </div>
              }]} />

              <Form.Item label={<>Yêu cầu riêng</>} htmlFor="groupbooking-field-7" style={{ marginBottom: 0 }}>
                <AntInput.TextArea id="groupbooking-field-7"
                  rows={2}
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder="VD: Đoàn có 3 người ăn chay, muốn thêm gala tối ngày cuối..."

                />
              </Form.Item>

              {formError && (
                <Alert type="error" showIcon title={formError} />
              )}

              <AntButton
                htmlType="submit"
                loading={sending} disabled={sending}
                type="primary" block
              >
                {sending ? "Đang gửi..." : "Gửi yêu cầu báo giá"}
              </AntButton>
              <p className="text-sm text-gray-500">Bạn chưa cần thanh toán khi gửi yêu cầu.</p>
            </form></Form>
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
              <AntInput aria-label="Dán mã tra cứu..."
                type="text"
                value={lookupCode}
                onChange={(e) => setLookupCode(e.target.value)}
                placeholder="Dán mã tra cứu..."
                className="flex-1"
              />
              <AntButton
                htmlType="button"
                onClick={() => traCuu()}
                loading={looking} disabled={looking || !lookupCode.trim()}

              >
                {looking ? "..." : "Xem"}
              </AntButton>
            </div>

            {lookupError && <Alert type="error" showIcon title={lookupError} />}

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
                  <AntButton
                    htmlType="button"
                    onClick={rut}

                  >
                    Rút yêu cầu này
                  </AntButton>
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
