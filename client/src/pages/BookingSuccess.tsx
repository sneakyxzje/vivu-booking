import {
  Link,
  useLocation,
  useParams,
  useSearchParams,
} from "react-router-dom";
import { useEffect, useState } from "react";
import bookingService from "@/services/bookingService";
import { RefundPolicyCard } from "@/components/RefundPolicyCard";
import { CreditCardIcon, ChevronRightIcon } from "@/components/Icons";
import { formatDateTime } from "@/utils/format";

type Booking = {
  id: number;
  public_token?: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  departure_date?: string;
  guests: number;
  adult_count?: number;
  child_count?: number;
  infant_count?: number;
  total_amount: number;
  /** Tổng đã thu thực, tính từ sổ giao dịch. Vắng mặt ở các đơn tạo trước khi có sổ. */
  net_paid?: number;
  balance_due?: number;
  /** Nghĩa vụ hoàn chốt lúc hủy, và phần trong đó đã thực trả. */
  refund_amount?: number | null;
  refunded?: number | null;
  discount_code?: string | null;
  discount_amount?: number;
  status: string;
  expires_at?: string | null;
  cancel_reason?: string | null;
  note?: string | null;
  payment_url?: string;
  vnpay_transaction_no?: string | null;
  paid_at?: string | null;
  confirmed_at?: string | null;
  created_at?: string;
  tour?: {
    id: number;
    public_token?: string;
    title: string;
    thumbnail: string | null;
    adult_price: number;
    start_location?: string | null;
    end_location?: string | null;
    vehicle_info?: string | null;
    pickup_location?: string | null;
  };
  schedule?: {
    id: number;
    public_token?: string;
    start_date: string;
    /** Đoàn đông có thể có nhiều hướng dẫn viên. */
    guides?: {
      id: number;
      name: string;
      phone?: string | null;
    }[];
  };
};

const formatCurrency = (value?: number) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));

/*
 * ĐÃ GỠ: bản `formatDateTime` riêng của trang này.
 *
 * Nó dùng `dateStyle: "medium"`, mà với `vi-VN` cho ra "9 thg 9, 2026" — dạng có chữ, khác hẳn
 * "09/09/2026" mà mọi màn hình còn lại đang hiện. Đây là trang xác nhận đặt tour, tức chỗ khách
 * đối chiếu ngày khởi hành với thư xác nhận và với trang tra cứu; ba nơi ấy phải viết ngày giống
 * nhau thì mới đối chiếu được.
 *
 * Dùng `formatDateTime` dùng chung ở `@/utils/format`.
 */

const isPaidStatus = (status: string) =>
  ["confirmed", "paid", "completed", "đã thanh toán", "thành công"].includes(
    status.toLowerCase(),
  );
const isPendingStatus = (status: string) =>
  ["pending", "chờ thanh toán", "chờ xử lý"].includes(status.toLowerCase());
const isCancelledStatus = (status: string) =>
  ["cancelled", "failed", "hủy", "đã hủy"].includes(status.toLowerCase());

const getStatusBadge = (status: string) => {
  if (isPaidStatus(status)) {
    return (
      <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold px-3 py-1 rounded-full">
        Đã thanh toán
      </span>
    );
  }
  if (isPendingStatus(status)) {
    return (
      <span className="bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold px-3 py-1 rounded-full animate-pulse">
        Chờ thanh toán
      </span>
    );
  }
  return (
    <span className="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold px-3 py-1 rounded-full">
      {isCancelledStatus(status) ? "Thanh toán chưa hoàn tất" : status}
    </span>
  );
};

export default function BookingSuccess() {
  const { state } = useLocation();
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const [booking, setBooking] = useState<Booking | null>(
    (state as Booking | null) ?? null,
  );
  const [loading, setLoading] = useState(!state && Boolean(id));
  const [remainingSeconds, setRemainingSeconds] = useState<number | null>(null);

  /* Tài khoản nhận tiền hoàn, cho các khoản hoàn do công ty khởi xướng. */
  const [refundForm, setRefundForm] = useState({
    refund_account_holder: "",
    refund_bank_account: "",
    refund_bank_name: "",
    /*
     * Địa chỉ thư đã dùng khi đặt — máy chủ đòi để xác thực.
     *
     * Mã tra cứu một mình là chưa đủ cho thao tác này: nó đi trong thư, mà thư thì được chuyển
     * tiếp và mở trên máy dùng chung. Đây là ô quyết định tiền chảy về đâu, nên nó cần đúng mức
     * bảo vệ mà tuyến sửa danh sách hành khách đã có từ trước.
     */
    customer_email: "",
  });
  const [refundSaving, setRefundSaving] = useState(false);
  const [refundSaved, setRefundSaved] = useState(false);
  const [refundError, setRefundError] = useState("");

  const luuTaiKhoanHoanTien = async () => {
    // Không có mã tra cứu thì không gọi được điểm cuối công khai — đơn mở từ state điều hướng
    // hiếm khi thiếu nó, nhưng kiểu dữ liệu cho phép.
    if (!booking?.public_token) return;

    setRefundSaving(true);
    setRefundError("");

    try {
      await bookingService.updateRefundAccount(booking.public_token, refundForm);
      setRefundSaved(true);
    } catch (err) {
      const data = (
        err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      )?.response?.data;

      setRefundError(
        (data?.errors ? Object.values(data.errors).flat()[0] : null) ??
          data?.message ??
          "Không lưu được thông tin tài khoản. Vui lòng thử lại.",
      );
    } finally {
      setRefundSaving(false);
    }
  };
  const paymentStatus = searchParams.get("payment_status");

  // Luôn tải bản mới nhất từ server (kể cả khi đã có dữ liệu từ trang đặt tour),
  // để trạng thái đơn phản ánh đúng khi bị admin hủy hoặc hết hạn giữ chỗ.
  useEffect(() => {
    if (!id) return;

    const loadBooking = async () => {
      try {
        const response = await bookingService.getById(id);
        setBooking(response.data.data as Booking);
      } catch {
        if (!state) setBooking(null);
      } finally {
        setLoading(false);
      }
    };

    loadBooking();
  }, [id, state]);

  // Đếm ngược thời gian giữ chỗ; hết giờ thì tải lại đơn (server sẽ trả trạng thái đã hủy)
  useEffect(() => {
    if (!booking?.expires_at || !isPendingStatus(booking.status)) {
      setRemainingSeconds(null);
      return;
    }

    const expiresAt = new Date(booking.expires_at).getTime();
    if (Number.isNaN(expiresAt)) return;

    const tick = () => {
      const secondsLeft = Math.max(
        0,
        Math.floor((expiresAt - Date.now()) / 1000),
      );
      setRemainingSeconds(secondsLeft);

      if (secondsLeft <= 0) {
        window.clearInterval(timer);
        const token = id ?? booking.public_token;
        if (token) {
          bookingService
            .getById(token)
            .then((response) => setBooking(response.data.data as Booking))
            .catch(() => undefined);
        }
      }
    };

    const timer = window.setInterval(tick, 1000);
    tick();

    return () => window.clearInterval(timer);
  }, [booking?.expires_at, booking?.status, booking?.public_token, id]);

  const formatRemaining = (seconds: number) => {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(rest).padStart(2, "0")}`;
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 font-inter">
        <div className="text-sm font-semibold text-gray-500">
          Đang tải thông tin đặt tour...
        </div>
      </div>
    );
  }

  if (!booking) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 font-inter">
        <div className="rounded-xl bg-white p-10 shadow-sm border border-gray-100 text-center max-w-md mx-4">
          <div className="w-16 h-16 bg-rose-50 rounded-lg flex items-center justify-center text-rose-500 mx-auto mb-4">
            <svg
              className="w-8 h-8"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-gray-900 font-plus-jakarta">
            Không tìm thấy thông tin đặt tour
          </h2>
          <p className="mt-2 text-sm text-gray-500">
            Vui lòng quay lại danh sách hoặc chọn tour khác để thực hiện đặt
            chỗ.
          </p>
          <Link
            to="/tours"
            className="mt-6 inline-block w-full rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 text-sm shadow-md transition-all duration-300"
          >
            Xem danh sách Tour
          </Link>
        </div>
      </div>
    );
  }

  const paid = isPaidStatus(booking.status);
  const pending = isPendingStatus(booking.status);
  const cancelled = isCancelledStatus(booking.status);
  /*
   * Số đã thu và số còn thiếu đọc từ sổ giao dịch, không suy ra từ trạng thái đơn.
   *
   * Suy từ trạng thái thì `confirmed` luôn có nghĩa là đã trả đủ — sai trong một trường hợp có
   * thật: khách chuyển khoản thiếu, điều hành ghi vào sổ đúng số đã nhận rồi vẫn xác nhận đơn.
   *
   * `??` giữ lối cũ cho các đơn tạo trước khi có sổ giao dịch.
   */
  const paidAmount = Number(
    booking.net_paid ?? (paid ? booking.total_amount : 0),
  );
  const remainingAmount = Number(
    booking.balance_due ?? (pending ? booking.total_amount : 0),
  );
  /*
   * Công ty còn nợ khách bao nhiêu.
   *
   * `refund_amount` là nghĩa vụ chốt lúc hủy; `refunded` là phần đã thực trả. Hiệu số là thứ khách
   * còn phải nhận, và chỉ khi nó dương thì mới hỏi số tài khoản — không có gì để hoàn mà vẫn thu
   * thập thông tin ngân hàng là giữ một thứ không dùng tới.
   */
  const refundOutstanding = Math.max(
    0,
    Number(booking.refund_amount ?? 0) - Number(booking.refunded ?? 0),
  );
  const discountAmount = Number(booking.discount_amount ?? 0);
  const subtotalAmount = Number(booking.total_amount) + discountAmount;
  const guestBreakdown = `${booking.adult_count ?? 0} người lớn, ${booking.child_count ?? 0} trẻ em, ${booking.infant_count ?? 0} em bé`;
  const headerTitle = paid
    ? "Thanh toán thành công!"
    : cancelled
      ? "Đơn đặt tour đã bị hủy"
      : paymentStatus === "failed"
        ? "Thanh toán chưa hoàn tất"
        : "Đặt tour thành công!";
  const headerDescription = paid
    ? `Booking BK${booking.id} đã được xác nhận. Thông tin hóa đơn và phiếu xác nhận đã được gửi về ${booking.customer_email}.`
    : cancelled
      ? `Đơn BK${booking.id} đã bị hủy${booking.cancel_reason ? ` — lý do: ${booking.cancel_reason}` : ""}. Nếu bạn đã thanh toán cho đơn này, chúng tôi sẽ liên hệ hoàn tiền. Cần hỗ trợ vui lòng liên hệ hotline.`
      : paymentStatus === "failed"
        ? "Giao dịch chưa hoàn tất hoặc đã bị hủy. Bạn có thể chọn tour khác hoặc liên hệ hỗ trợ để được kiểm tra."
        : `Chúng tôi đã ghi nhận yêu cầu đặt tour và gửi hướng dẫn thanh toán về ${booking.customer_email}. Vui lòng hoàn tất thanh toán để giữ chỗ.`;

  return (
    <div className="min-h-screen bg-gray-50 py-8 font-inter">
      <div className="mx-auto max-w-[1280px] px-4 sm:px-6">
        <nav className="flex items-center gap-2 text-xs md:text-sm text-gray-500 font-medium mb-6">
          <Link to="/" className="hover:text-primary-600 transition-colors">
            Trang chủ
          </Link>
          <ChevronRightIcon className="w-3.5 h-3.5 text-gray-300" />
          <span className="text-gray-900 font-medium">Hóa đơn đặt tour</span>
        </nav>

        <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm mb-8 flex flex-col md:flex-row items-center gap-6">
          <div
            className={`w-16 h-16 rounded-lg border flex items-center justify-center shrink-0 ${paid ? "bg-emerald-50 border-emerald-100 text-emerald-600" : cancelled ? "bg-rose-50 border-rose-100 text-rose-600" : "bg-amber-50 border-amber-100 text-amber-600"}`}
          >
            <svg
              className="w-8 h-8"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              {paid ? (
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2.5}
                  d="M5 13l4 4L19 7"
                />
              ) : (
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2.2}
                  d="M12 8v4m0 4h.01M12 3a9 9 0 110 18 9 9 0 010-18z"
                />
              )}
            </svg>
          </div>
          <div className="text-center md:text-left flex-1">
            <div className="flex flex-col gap-2 md:flex-row md:items-center">
              <h1 className="text-2xl md:text-2xl font-bold text-gray-900 font-plus-jakarta tracking-tight">
                {headerTitle}
              </h1>
              <span className="md:ml-2">{getStatusBadge(booking.status)}</span>
            </div>
            <p className="mt-1.5 text-sm text-gray-500 leading-relaxed max-w-3xl">
              {headerDescription}
            </p>
          </div>
          <div className="text-center md:text-right shrink-0">
            <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
              Mã booking
            </p>
            <p className="text-2xl font-bold text-primary-600 font-mono">
              BK{booking.id}
            </p>
            {(booking.public_token || id) && (
              <button
                type="button"
                onClick={() =>
                  navigator.clipboard?.writeText(
                    String(booking.public_token ?? id),
                  )
                }
                title="Sao chép mã tra cứu để xem lại đơn mà không cần đăng nhập"
                className="mt-2 text-[11px] font-semibold text-gray-500 hover:text-primary-600 transition-colors"
              >
                Sao chép mã tra cứu
              </button>
            )}
          </div>
        </div>

        <div className="grid gap-8 lg:grid-cols-12 items-start">
          <div className="lg:col-span-8 space-y-8">
            {/*
              Điều khoản hủy và số tiền hoàn nếu hủy bây giờ. Đơn đã hủy rồi thì không cần nữa.

              Thẻ này đã được viết xong từ lâu (`RefundPolicyCard`) — đúng thứ tài liệu 03 mục 5.2
              đòi là bắt buộc — nhưng không trang nào import nó, nên trên thực tế khách vãng lai
              không có chỗ nào xem trước mức hoàn trước khi quyết định hủy. Chỗ trống ở đây chỉ còn
              lại đúng dòng chú thích này.
            */}
            {!cancelled && (booking.public_token || id) && (
              <RefundPolicyCard publicToken={String(booking.public_token ?? id)} />
            )}

            <div className="rounded-xl bg-white p-6 md:p-8 border border-gray-100 shadow-sm">
              <h2 className="mb-6 text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
                Thông tin liên lạc
              </h2>
              <div className="grid gap-6 sm:grid-cols-3 text-sm">
                <div>
                  <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                    Họ và tên
                  </p>
                  <p className="mt-1.5 text-base font-bold text-gray-800">
                    {booking.customer_name}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                    Email liên hệ
                  </p>
                  <p className="mt-1.5 text-base font-semibold text-gray-800 break-all font-mono">
                    {booking.customer_email}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                    Số điện thoại
                  </p>
                  <p className="mt-1.5 text-base font-bold text-gray-800 font-mono">
                    {booking.customer_phone || "Đang cập nhật"}
                  </p>
                </div>
              </div>
              <div className="mt-8 pt-6 border-t border-gray-100">
                <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                  Ghi chú yêu cầu
                </p>
                <p className="mt-1.5 text-sm text-gray-600 italic whitespace-pre-line leading-relaxed">
                  {booking.note || "Không có ghi chú đặc biệt kèm theo."}
                </p>
              </div>
            </div>

            <div className="rounded-xl bg-white p-6 md:p-8 border border-gray-100 shadow-sm">
              <h2 className="mb-6 text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
                Chi tiết hóa đơn đặt chỗ
              </h2>
              <div className="divide-y divide-gray-100 text-sm">
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Mã đặt chỗ</span>
                  <span className="font-bold text-primary-600 tracking-wider">
                    BK{booking.id}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Thời gian đặt tour
                  </span>
                  <span className="font-semibold text-gray-800 font-mono">
                    {formatDateTime(booking.created_at)}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Ngày khởi hành
                  </span>
                  <span className="font-bold text-gray-800 font-mono">
                    {formatDateTime(
                      booking.departure_date || booking.schedule?.start_date,
                    )}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Số lượng hành khách
                  </span>
                  <span className="font-bold text-gray-800">
                    {guestBreakdown} ({booking.guests} khách)
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Tạm tính</span>
                  <span className="font-bold text-gray-900">
                    {formatCurrency(subtotalAmount)}
                  </span>
                </div>
                {discountAmount > 0 && (
                  <div className="flex justify-between py-4">
                    <span className="text-gray-500 font-medium">
                      Giảm giá{" "}
                      {booking.discount_code
                        ? `(${booking.discount_code})`
                        : ""}
                    </span>
                    <span className="font-bold text-emerald-600">
                      - {formatCurrency(discountAmount)}
                    </span>
                  </div>
                )}
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Tổng giá trị booking
                  </span>
                  <span className="font-bold text-gray-900">
                    {formatCurrency(booking.total_amount)}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Số tiền đã thanh toán
                  </span>
                  <span className="font-bold text-emerald-600 font-mono">
                    {formatCurrency(paidAmount)}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Số tiền cần thanh toán thêm
                  </span>
                  <span className="font-bold text-red-600 font-mono">
                    {formatCurrency(remainingAmount)}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Mã giao dịch VNPay
                  </span>
                  <span className="font-bold text-gray-800 font-mono">
                    {booking.vnpay_transaction_no || "-"}
                  </span>
                </div>
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">
                    Thời gian thanh toán
                  </span>
                  <span className="font-semibold text-gray-800 font-mono">
                    {formatDateTime(booking.paid_at)}
                  </span>
                </div>
                <div className="flex justify-between py-4 items-center">
                  <span className="text-gray-500 font-medium">
                    Trạng thái đặt chỗ
                  </span>
                  <span>{getStatusBadge(booking.status)}</span>
                </div>
              </div>

              {/*
                Khối thanh toán hiện cả khi đơn ĐÃ xác nhận mà vẫn còn thiếu tiền — trường hợp
                khách chuyển khoản thiếu và điều hành ghi vào sổ đúng số đã nhận.
              */}
              {booking.payment_url && (pending || remainingAmount > 0) && (
                <div className="mt-8 p-5 bg-emerald-50 border border-emerald-100 rounded-lg space-y-4">
                  <div className="flex items-start gap-3.5">
                    <div className="p-2.5 bg-emerald-500 rounded-xl text-white shrink-0">
                      <CreditCardIcon className="w-5 h-5" />
                    </div>
                    <div>
                      <h4 className="font-bold text-emerald-900 text-sm">
                        {pending
                          ? "Thanh toán trực tuyến VNPay an toàn"
                          : "Thanh toán phần còn lại"}
                      </h4>
                      <p className="text-xs text-emerald-700 leading-relaxed mt-0.5">
                        {pending
                          ? "Để hoàn tất đặt tour và giữ chỗ chính thức, vui lòng thanh toán qua cổng VNPay."
                          : `Chỗ của bạn đã được giữ. Đơn còn thiếu ${formatCurrency(remainingAmount)}.`}
                      </p>
                    </div>
                  </div>
                  {pending && remainingSeconds !== null && (
                    <div
                      className={`flex items-center justify-between rounded-xl border px-4 py-3 ${remainingSeconds <= 120 ? "bg-rose-50 border-rose-200" : "bg-amber-50 border-amber-200"}`}
                    >
                      <span
                        className={`text-xs font-semibold ${remainingSeconds <= 120 ? "text-rose-700" : "text-amber-700"}`}
                      >
                        Vui lòng thanh toán trước hạn để được giữ chỗ
                      </span>
                      <span
                        className={`text-base font-bold font-mono tabular-nums ${remainingSeconds <= 120 ? "text-rose-600" : "text-amber-700"}`}
                      >
                        {formatRemaining(remainingSeconds)}
                      </span>
                    </div>
                  )}
                  <a
                    href={booking.payment_url}
                    className="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 text-center rounded-xl shadow-md hover:shadow-lg transition-all duration-300 text-sm cursor-pointer"
                  >
                    {pending
                      ? `Thanh toán ${formatCurrency(remainingAmount)} ngay`
                      : `Thanh toán nốt ${formatCurrency(remainingAmount)}`}
                  </a>
                </div>
              )}

              {/*
                Đơn bị hủy mà công ty còn nợ tiền: hỏi tài khoản để chuyển.

                Ở luồng khách tự xin hủy thì form kia đã hỏi rồi. Nhưng khi CÔNG TY hủy — hủy cả
                chuyến, hoặc điều hành hủy đơn — khách không mở form nào cả, nên nghĩa vụ hoàn sinh
                ra mà không có nơi để trả. Trước khối này, kế toán phải gọi điện xin số tài khoản và
                ghi vào sổ tay riêng.
              */}
              {refundOutstanding > 0 && (
                <div className="mt-8 p-5 bg-amber-50/70 border border-amber-200 rounded-lg text-sm">
                  <h4 className="font-bold text-amber-900">
                    Nhận lại {formatCurrency(refundOutstanding)}
                  </h4>
                  <p className="mt-1 text-gray-700">
                    Vui lòng cho chúng tôi biết tài khoản nhận tiền. Khoản hoàn sẽ được chuyển
                    trong thời gian sớm nhất.
                  </p>

                  {refundSaved ? (
                    <p className="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800">
                      Đã ghi nhận tài khoản của Quý khách.
                    </p>
                  ) : (
                    <div className="mt-3 space-y-2">
                      <input
                        value={refundForm.refund_account_holder}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, refund_account_holder: e.target.value }))
                        }
                        placeholder="Tên chủ tài khoản (như trên thẻ)"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2"
                      />
                      <input
                        value={refundForm.refund_bank_account}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, refund_bank_account: e.target.value }))
                        }
                        placeholder="Số tài khoản"
                        inputMode="numeric"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2"
                      />
                      <input
                        value={refundForm.refund_bank_name}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, refund_bank_name: e.target.value }))
                        }
                        placeholder="Ngân hàng"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2"
                      />
                      <input
                        type="email"
                        value={refundForm.customer_email}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, customer_email: e.target.value }))
                        }
                        placeholder="Email bạn đã dùng khi đặt tour (để xác nhận)"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2"
                      />
                      <p className="text-xs text-gray-500">
                        Chúng tôi hỏi lại email để chắc chắn người nhập số tài khoản đúng là chủ
                        đơn — mã tra cứu nằm trong thư và thư thì có thể được chuyển tiếp.
                      </p>

                      {refundError && (
                        <p className="text-rose-700">{refundError}</p>
                      )}

                      <button
                        type="button"
                        disabled={refundSaving || !refundForm.customer_email.trim()}
                        onClick={luuTaiKhoanHoanTien}
                        className="w-full rounded-lg bg-amber-600 py-3 font-bold text-white hover:bg-amber-700 disabled:opacity-50"
                      >
                        {refundSaving ? "Đang lưu..." : "Gửi thông tin tài khoản"}
                      </button>
                    </div>
                  )}
                </div>
              )}

              {!cancelled && (
                <div className="mt-8 p-5 bg-blue-50/60 border border-blue-100 rounded-lg space-y-3 text-sm">
                  <h4 className="font-bold text-blue-900">
                    Hướng dẫn tập trung & di chuyển
                  </h4>
                  <div className="space-y-1.5 text-gray-700">
                    <p>
                      <span className="text-gray-500">Điểm đón:</span>{" "}
                      <strong className="text-gray-900">
                        {booking.tour?.pickup_location ||
                          `${booking.tour?.start_location ?? "Điểm khởi hành"} (chi tiết sẽ gửi qua email)`}
                      </strong>
                    </p>
                    <p>
                      <span className="text-gray-500">
                        Thời gian khởi hành:
                      </span>{" "}
                      <strong className="text-gray-900">
                        {formatDateTime(booking.departure_date)}
                      </strong>
                      <span className="text-gray-500">
                        {" "}
                        — vui lòng có mặt trước ít nhất 30 phút
                      </span>
                    </p>
                    {booking.tour?.vehicle_info && (
                      <p>
                        <span className="text-gray-500">Phương tiện:</span>{" "}
                        <strong className="text-gray-900">
                          {booking.tour.vehicle_info}
                        </strong>
                      </p>
                    )}
                    <p>
                      <span className="text-gray-500">Hướng dẫn viên:</span>{" "}
                      {(booking.schedule?.guides ?? []).length === 0 ? (
                        <strong className="text-gray-900">Đang sắp xếp</strong>
                      ) : (
                        (booking.schedule?.guides ?? []).map((guide, i) => (
                          <span key={guide.id}>
                            {i > 0 && ", "}
                            <strong className="text-gray-900">
                              {guide.name}
                            </strong>
                            {guide.phone ? ` — ${guide.phone}` : ""}
                          </span>
                        ))
                      )}
                    </p>
                  </div>
                  <p className="text-xs text-blue-700">
                    Mang theo giấy tờ tùy thân và mã booking BK{booking.id} khi
                    lên xe. Mọi thắc mắc vui lòng liên hệ hotline hoặc hướng dẫn
                    viên.
                  </p>
                </div>
              )}
            </div>
          </div>

          <div className="lg:col-span-4 lg:sticky lg:top-24">
            <div className="rounded-xl bg-white p-6 md:p-7 border border-gray-100 shadow-sm space-y-6">
              <h2 className="text-lg md:text-xl font-bold text-gray-900 font-plus-jakarta">
                Phiếu xác nhận booking
              </h2>
              <div className="rounded-lg border border-gray-100 p-4 space-y-4 bg-gray-50/50">
                <div className="relative h-44 rounded-xl overflow-hidden border border-gray-200">
                  <img
                    src={
                      booking.tour?.thumbnail || "https://placehold.co/600x400"
                    }
                    alt={booking.tour?.title || "Tour image"}
                    className="h-full w-full object-cover"
                  />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900 line-clamp-2 leading-tight">
                    {booking.tour?.title || `Booking Tour #${booking.id}`}
                  </h3>
                  <p className="mt-1.5 text-xs text-primary-600 font-bold">
                    Mã booking: BK{booking.id}
                  </p>
                </div>
                <hr className="border-gray-200/60" />
                <div className="space-y-3.5 text-xs text-gray-600">
                  <div className="flex justify-between items-center">
                    <span className="font-medium text-gray-400">
                      Ngày khởi hành
                    </span>
                    <span className="font-bold text-gray-800 font-mono">
                      {formatDateTime(booking.departure_date)}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="font-medium text-gray-400">Số khách</span>
                    <span className="font-bold text-gray-800">
                      {booking.guests} khách
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="font-medium text-gray-400">
                      Trạng thái
                    </span>
                    <span>{getStatusBadge(booking.status)}</span>
                  </div>
                  <div className="border-t border-gray-200/60 pt-4 flex justify-between items-baseline">
                    <span className="font-bold text-gray-800 text-sm">
                      Tổng cộng
                    </span>
                    <span className="text-xl font-bold text-red-600 font-mono">
                      {formatCurrency(booking.total_amount)}
                    </span>
                  </div>
                </div>
              </div>
              {/*
                Việc tiếp theo của khách, đặt trên cả "về trang chủ".

                Danh sách hành khách nay khai sau khi đặt, nên đây là hành động còn dang dở duy
                nhất trên trang này — nó phải nổi hơn hai liên kết điều hướng bên dưới, không thì
                khách đóng tab và quên mất.
              */}
              {(booking.public_token || id) && (
                <Link
                  to={`/bookings/${booking.public_token ?? id}/passengers`}
                  className="block w-full rounded-xl bg-amber-50 border border-amber-200 text-center py-3 text-sm text-amber-900 hover:bg-amber-100 transition-colors cursor-pointer"
                >
                  <span className="font-bold block">
                    Khai thông tin {booking.guests} hành khách
                  </span>
                  <span className="text-xs text-amber-800/80">
                    Cần xong trước hạn chốt danh sách để làm bảo hiểm
                  </span>
                </Link>
              )}

              <Link
                to="/"
                className="block w-full rounded-xl border border-gray-200 hover:bg-gray-50 text-center font-semibold py-3 text-sm text-gray-700 transition-all duration-300 cursor-pointer"
              >
                Về trang chủ
              </Link>
              <Link
                to="/tours"
                className="block w-full rounded-xl bg-primary-600 hover:bg-primary-700 text-center font-semibold py-3 text-sm text-white transition-all duration-300 cursor-pointer"
              >
                Xem tour khác
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
