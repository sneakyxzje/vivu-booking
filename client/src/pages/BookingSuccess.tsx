import { DemoClockNotice } from "@/components/DemoClockNotice";
import { businessNow, type DemoClockValue } from "@/utils/demoClock";
import { Alert, Breadcrumb, Card, Descriptions, Flex, Result, Tag, Typography, Button as AntButton, Input as AntInput } from "antd";
import {
  Link,
  useLocation,
  useParams,
  useSearchParams,
} from "react-router-dom";
import { useEffect, useState } from "react";
import bookingService from "@/services/bookingService";
import { CreditCardIcon } from "@/components/Icons";
import { formatDateTime } from "@/utils/format";

type Booking = {
  demo_clock?: DemoClockValue | null;
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
  /**
   * Số tiền của liên kết thanh toán — khác `balance_due` ở lần trả đầu.
   *
   * Đơn vừa đặt còn thiếu cả giá tour nhưng chỉ phải cọc một phần, nên nút phải in con số này chứ
   * không phải số còn thiếu. In sai thì khách bấm vào và thấy cổng đòi một số khác.
   */
  payment_amount?: number;
  /** Hạn trả nốt phần còn lại, và việc đã quá hạn hay chưa. */
  balance_due_at?: string | null;
  balance_overdue?: boolean;
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

/**
 * Nhãn trạng thái.
 *
 * `conNo` tách "đã cọc" khỏi "đã thanh toán": cả hai đều mang trạng thái `confirmed` ở máy chủ,
 * nhưng với khách thì một bên còn việc phải làm và một bên thì không.
 */
const getStatusBadge = (status: string, conNo = 0) => {
  if (status === "awaiting_transfer") return <Tag color="warning">Chờ phản hồi ghép chuyến</Tag>;
  if (isPaidStatus(status)) return <Tag color={conNo > 0 ? "processing" : "success"}>{conNo > 0 ? "Đã cọc" : "Đã thanh toán"}</Tag>;
  if (isPendingStatus(status)) return <Tag color="warning">Chờ thanh toán</Tag>;
  return <Tag color="error">{status === "failed" ? "Thanh toán chưa hoàn tất" : isCancelledStatus(status) ? "Đã hủy" : status}</Tag>;
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

  // Proposals
  const [proposals, setProposals] = useState<any[]>([]);
  const [respondingProposalId, setRespondingProposalId] = useState<number | null>(null);
  const [proposalEmail, setProposalEmail] = useState(searchParams.get("email") ?? "");
  const [proposalError, setProposalError] = useState("");
  const [proposalErrorId, setProposalErrorId] = useState<number | null>(null);

  // Luôn tải bản mới nhất từ server (kể cả khi đã có dữ liệu từ trang đặt tour),
  // để trạng thái đơn phản ánh đúng khi bị admin hủy hoặc hết hạn giữ chỗ.
  useEffect(() => {
    if (!id) return;

    const loadBooking = async () => {
      try {
        const response = await bookingService.getById(id);
        setBooking(response.data.data as Booking);
        if (!proposalEmail) setProposalEmail(response.data.data.customer_email ?? "");
      } catch {
        if (!state) setBooking(null);
      } finally {
        setLoading(false);
      }
    };

    const loadProposals = async () => {
      try {
        const response = await bookingService.getProposals(id, proposalEmail.trim());
        setProposals(response.data?.data || []);
      } catch {
        // ignore
      }
    };

    // Reading proposals can expire a merge and cancel the booking; reload after it settles.
    void (async () => {
      if (proposalEmail.trim()) await loadProposals();
      await loadBooking();
    })();
  }, [id, state, proposalEmail]);

  const handleRespondProposal = async (proposalId: number, choiceId: string) => {
    if (respondingProposalId !== null) return;
    setProposalErrorId(proposalId);
    if (!proposalEmail.trim()) {
      setProposalError("Vui lòng nhập Email để xác nhận.");
      return;
    }

    if (!id) return;
    setRespondingProposalId(proposalId);
    setProposalError("");

    try {
      await bookingService.respondToProposal(id, {
        proposal_id: proposalId,
        choice_id: choiceId,
        customer_email: proposalEmail.trim(),
      });
    } catch (err: any) {
      setProposalError(err.response?.data?.message || "Lỗi khi xác nhận. Vui lòng thử lại.");
    } finally {
      try {
        const response = await bookingService.getProposals(id, proposalEmail.trim());
        setProposals(response.data?.data || []);
        const updated = await bookingService.getById(id);
        setBooking(updated.data.data as Booking);
      } catch { /* Keep the response error visible if refreshing also fails. */ }
      setRespondingProposalId(null);
    }
  };

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
        Math.floor((expiresAt - businessNow(booking)) / 1000),
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
   * Số của lần trả sắp tới, do máy chủ tính.
   *
   * Khác `remainingAmount` ở đúng lần đầu: đơn vừa đặt còn thiếu cả giá tour nhưng chỉ phải cọc một
   * phần. Lùi về số còn thiếu khi máy chủ chưa trả trường này, để đơn cũ vẫn hiện đúng.
   */
  const payNowAmount = Number(booking.payment_amount ?? remainingAmount);
  const isDeposit = pending && payNowAmount > 0 && payNowAmount < remainingAmount;
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
  /*
   * Đơn đã xác nhận mà vẫn còn nợ thì chưa phải "đã thanh toán".
   *
   * Trạng thái `confirmed` giờ có hai nghĩa: đã trả xong, hoặc mới cọc và chỗ đã được giữ. Gọi cả
   * hai là "Thanh toán thành công" nói với người mới cọc rằng họ không còn gì phải làm — rồi vài
   * tuần sau đơn của họ bị hủy vì chưa trả nốt.
   */
  const daCocChuaDu = paid && remainingAmount > 0;
  // Đơn cũ có thể còn lưu lời giải thích dài trước khi rút gọn lý do hủy.
  const cancellationReason = booking.cancel_reason
    ?.replace(/,\s*hệ thống tự hủy để nhường chỗ\.?$/u, "")
    .trim();

  const awaitingTransfer = booking.status === "awaiting_transfer";
  const headerTitle = awaitingTransfer ? "Chuyến ban đầu đã hủy" : daCocChuaDu
    ? "Đã nhận tiền cọc, chỗ của bạn được giữ"
    : paid
      ? "Thanh toán thành công!"
      : cancelled
        ? "Đơn đặt tour đã bị hủy"
        : paymentStatus === "failed"
          ? "Thanh toán chưa hoàn tất"
          : "Đặt tour thành công!";
  const headerDescription = awaitingTransfer ? "Vui lòng chọn chuyến thay thế bên dưới hoặc từ chối để được hoàn tiền. Hết hạn chưa phản hồi, đơn sẽ hủy và chuyển sang chờ hoàn tiền." : daCocChuaDu
    ? `Booking BK${booking.id} đã được giữ chỗ. Còn ${formatCurrency(remainingAmount)} cần thanh toán${booking.balance_due_at ? ` trước ngày ${formatDateTime(booking.balance_due_at)}` : ""}; chúng tôi đã gửi chi tiết về ${booking.customer_email}.`
    : paid
      ? `Booking BK${booking.id} đã được xác nhận. Thông tin hóa đơn và phiếu xác nhận đã được gửi về ${booking.customer_email}.`
      : cancelled
        ? `Đơn BK${booking.id} đã bị hủy${cancellationReason ? ` — lý do: ${cancellationReason}` : ""}. Nếu bạn đã thanh toán cho đơn này, chúng tôi sẽ liên hệ hoàn tiền. Cần hỗ trợ vui lòng liên hệ hotline.`
        : paymentStatus === "failed"
          ? "Giao dịch chưa hoàn tất hoặc đã bị hủy. Bạn có thể chọn tour khác hoặc liên hệ hỗ trợ để được kiểm tra."
          : `Chúng tôi đã ghi nhận yêu cầu đặt tour và gửi hướng dẫn thanh toán về ${booking.customer_email}. Vui lòng hoàn tất thanh toán để giữ chỗ.`;

  return (
    <div className="min-h-screen bg-gray-50 py-8 font-inter">
      <div className="mx-auto max-w-[1280px] px-4 sm:px-6">
        <Breadcrumb style={{ marginBottom: 24 }} items={[{ title: <Link to="/">Trang chủ</Link> }, { title: "Đơn đặt tour" }]} />

        <DemoClockNotice clock={booking.demo_clock} />
        <Card style={{ marginBottom: 24 }}>
          <Result status={cancelled ? "error" : paid ? "success" : "info"}
            title={<Typography.Title level={1} style={{ fontSize: 26, margin: 0 }}>{headerTitle}</Typography.Title>} subTitle={headerDescription}
            extra={<Flex justify="center" align="center" gap="middle" wrap>
              {getStatusBadge(booking.status, remainingAmount)}
              <Typography.Text strong>BK{booking.id}</Typography.Text>
              {(booking.public_token || id) && <Typography.Text copyable={{ text: String(booking.public_token ?? id), tooltips: ["Sao chép mã tra cứu", "Đã sao chép"] }}>Mã tra cứu</Typography.Text>}
            </Flex>} />
        </Card>

        <div className="grid gap-8 lg:grid-cols-12 items-start">
          <div className="lg:col-span-8 space-y-8">
            {/* ĐỀ XUẤT THAY ĐỔI TỪ HỆ THỐNG */}
            {proposals.filter(p => p.status === "pending").map((proposal) => (
              <div key={proposal.id} className="rounded-xl bg-amber-50 border border-amber-200 p-6 shadow-sm relative overflow-hidden">
                <div className="absolute top-0 left-0 w-1 h-full bg-amber-500"></div>
                <h2 className="text-lg font-bold text-amber-900 mb-2 flex items-center gap-2">
                  <svg className="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  Thông báo thay đổi chuyến đi
                </h2>
                <p className="text-sm text-amber-800 mb-4 whitespace-pre-line leading-relaxed">
                  {proposal.reason}
                </p>
                {proposal.schedule_snapshot && <p className="text-sm text-amber-900 mb-4">
                  Chuyến đề xuất: {proposal.schedule_snapshot.to.tour_title} · Khởi hành {formatDateTime(proposal.schedule_snapshot.to.start_date)}.
                  Kết thúc {formatDateTime(proposal.schedule_snapshot.to.end_date)}.
                  Hạn trả nốt: {formatDateTime(proposal.schedule_snapshot.to.booking_deadline)}. Giá đơn giữ nguyên.
                </p>}
                {proposal.schedule_snapshot && <div className="text-sm mb-4">
                  <p>Điểm đón: {proposal.schedule_snapshot.to.pickup_location || "Theo chương trình tour"}</p>
                  <a className="underline" href={`/tours/${proposal.schedule_snapshot.to.tour_slug}`} target="_blank" rel="noreferrer">Xem chương trình tour đề xuất</a>
                  {(proposal.schedule_snapshot.to.itineraries ?? []).map((day: { day_number: number; title: string; content?: string }) =>
                    <p key={day.day_number}>Ngày {day.day_number}: {day.title} — {day.content?.replace(/<[^>]*>/g, " ")}</p>) }
                </div>}
                {proposal.schedule_snapshot && <p className="text-sm mb-4">Chuyến ban đầu đã hủy. Đồng ý để chuyển sang chuyến thay thế; từ chối hoặc hết hạn chưa phản hồi sẽ hủy đơn và ghi nhận chờ hoàn đủ số tiền đã thu còn lại.</p>}

                <div className="bg-white p-4 rounded-lg border border-amber-100 shadow-sm">
                  <p className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3">
                    Vui lòng chọn 1 trong các phương án sau trước hạn chót: {formatDateTime(proposal.response_deadline)}
                  </p>

                  {proposalError && proposalErrorId === proposal.id && (
                    <Alert type="error" showIcon title={proposalError} style={{ marginBottom: 12 }} />
                  )}

                  <div className="flex flex-col gap-3">
                    <AntInput aria-label="Nhập email đặt tour của bạn để xác nhận..."
                      type="email"
                      value={proposalEmail}
                      onChange={(e) => setProposalEmail(e.target.value)}
                      placeholder="Nhập email đặt tour của bạn để xác nhận..."

                    />

                    <div className="flex flex-wrap gap-2">
                      {[{ id: "accept", label: "Đồng ý" }, { id: "reject", label: proposal.schedule_snapshot ? "Từ chối và nhận hoàn tiền" : "Từ chối" }].map((opt) => (
                        <AntButton htmlType="button"
                          key={opt.id}
                          onClick={() => handleRespondProposal(proposal.id, opt.id)}
                          loading={respondingProposalId === proposal.id} disabled={respondingProposalId !== null}
                          className="flex-1"
                          style={{ whiteSpace: "normal", height: "auto", minHeight: 44 }}
                        >
                          {respondingProposalId === proposal.id ? "Đang xử lý..." : opt.label}
                        </AntButton>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            ))}

            <Card title="Thông tin liên lạc">
              <Descriptions column={{ xs: 1, sm: 2, md: 3 }} items={[
                { key: "name", label: "Họ và tên", children: booking.customer_name },
                { key: "email", label: "Email", children: <Typography.Text style={{ overflowWrap: "anywhere" }}>{booking.customer_email}</Typography.Text> },
                { key: "phone", label: "Điện thoại", children: booking.customer_phone || "Chưa cung cấp" },
              ]} />
              <Typography.Paragraph strong style={{ marginTop: 16 }}>Ghi chú yêu cầu</Typography.Paragraph>
              <Typography.Paragraph style={{ whiteSpace: "pre-line", marginBottom: 0 }}>
                {booking.note || "Không có ghi chú đặc biệt kèm theo."}
              </Typography.Paragraph>
            </Card>

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
                  <span>{getStatusBadge(booking.status, remainingAmount)}</span>
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
                          ? isDeposit
                            ? `Đặt cọc ${formatCurrency(payNowAmount)} để giữ chỗ. Phần còn lại ${formatCurrency(remainingAmount - payNowAmount)} thanh toán trước ngày khởi hành.`
                            : "Để hoàn tất đặt tour và giữ chỗ chính thức, vui lòng thanh toán qua cổng VNPay."
                          : `Chỗ của bạn đã được giữ. Đơn còn thiếu ${formatCurrency(remainingAmount)}.`}
                      </p>

                      {/*
                        Hạn trả nốt hiện ngay cạnh số tiền, không giấu ở email.

                        Quá hạn này là đơn bị hủy và mất cọc, nên khách phải nhìn thấy nó ở đúng chỗ
                        họ nhìn khi tự hỏi "mình còn nợ bao nhiêu". Sát hạn thì đổi màu và nói thẳng
                        hậu quả — một dòng xám nhạt không đủ cho một khoản tiền sắp mất.
                      */}
                      {!pending && booking.balance_due_at && (
                        <p
                          className={`mt-1.5 text-xs font-semibold ${booking.balance_overdue
                            ? "text-rose-700"
                            : "text-emerald-800"
                            }`}
                        >
                          {booking.balance_overdue
                            ? `Đã quá hạn thanh toán ${formatDateTime(booking.balance_due_at)}. Vui lòng thanh toán ngay hoặc liên hệ tổng đài để đơn không bị hủy.`
                            : `Hạn thanh toán: ${formatDateTime(booking.balance_due_at)}. Quá hạn, đơn sẽ bị hủy và khoản đã đặt cọc không được hoàn lại.`}
                        </p>
                      )}
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
                  <AntButton type="primary" block size="large" href={booking.payment_url}>
                    {pending ? isDeposit ? "Đặt cọc " + formatCurrency(payNowAmount) + " ngay" : "Thanh toán " + formatCurrency(payNowAmount) + " ngay" : "Thanh toán nốt " + formatCurrency(payNowAmount)}
                  </AntButton>
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
                    <Alert type="success" showIcon title="Đã ghi nhận tài khoản của bạn." style={{ marginTop: 12 }} />
                  ) : (
                    <div className="mt-3 space-y-2">
                      <AntInput aria-label="Tên chủ tài khoản (như trên thẻ)"
                        value={refundForm.refund_account_holder}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, refund_account_holder: e.target.value }))
                        }
                        placeholder="Tên chủ tài khoản (như trên thẻ)"

                      />
                      <AntInput aria-label="Số tài khoản"
                        value={refundForm.refund_bank_account}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, refund_bank_account: e.target.value }))
                        }
                        placeholder="Số tài khoản"
                        inputMode="numeric"

                      />
                      <AntInput aria-label="Ngân hàng"
                        value={refundForm.refund_bank_name}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, refund_bank_name: e.target.value }))
                        }
                        placeholder="Ngân hàng"

                      />
                      <AntInput aria-label="Email bạn đã dùng khi đặt tour (để xác nhận)"
                        type="email"
                        value={refundForm.customer_email}
                        onChange={(e) =>
                          setRefundForm((f) => ({ ...f, customer_email: e.target.value }))
                        }
                        placeholder="Email bạn đã dùng khi đặt tour (để xác nhận)"

                      />
                      <p className="text-xs text-gray-500">
                        Chúng tôi hỏi lại email để chắc chắn người nhập số tài khoản đúng là chủ
                        đơn — mã tra cứu nằm trong thư và thư thì có thể được chuyển tiếp.
                      </p>

                      {refundError && (
                        <Alert type="error" showIcon title={refundError} />
                      )}

                      <AntButton
                        htmlType="button"
                        loading={refundSaving} type="primary" disabled={refundSaving || !refundForm.customer_email.trim()}
                        onClick={luuTaiKhoanHoanTien}
                        block
                      >
                        {refundSaving ? "Đang lưu..." : "Gửi thông tin tài khoản"}
                      </AntButton>
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
                    <span>{getStatusBadge(booking.status, remainingAmount)}</span>
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
