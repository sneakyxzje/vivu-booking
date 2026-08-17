import { useCallback, useEffect, useState } from "react";
import type { BookingLedger, GroupBookingRequestRow } from "@/types";
import adminService from "@/services/adminService";
import { Toast } from "@/components/admin/CustomAlert";
import { Modal } from "@/components/admin/Modal";
import Pagination from "@/components/common/Pagination";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * Booking theo đoàn — bàn làm việc của điều hành cho điểm 14.
 *
 * Một yêu cầu đoàn KHÔNG phải một đơn hàng. Nó là cuộc thương lượng: khách gửi yêu cầu, điều hành
 * báo giá (bao nhiêu lần cũng được), hai bên đồng ý rồi mới CHỐT — và chỉ lúc chốt mới sinh đơn
 * thật, chiếm chỗ thật. Vì thế màn này không đụng vào kho chỗ ở bất kỳ nút nào ngoài nút chốt.
 *
 * Tiền của đơn đoàn về nhiều đợt nên có sổ giao dịch riêng: từng khoản cọc, thanh toán nốt, hoàn —
 * chỉ thêm dòng, không ghi đè. Số đã thu là tổng của sổ.
 */

const TRANG_THAI = [
  { value: "", label: "Tất cả" },
  { value: "pending_quote", label: "Chờ báo giá" },
  { value: "quoted", label: "Đã báo giá" },
  { value: "confirmed", label: "Đã chốt" },
  { value: "rejected", label: "Đã từ chối" },
  { value: "withdrawn", label: "Khách đã rút" },
];

const mauTrangThai: Record<string, string> = {
  pending_quote: "bg-amber-50 text-amber-700 border-amber-200",
  quoted: "bg-sky-50 text-sky-700 border-sky-200",
  confirmed: "bg-emerald-50 text-emerald-700 border-emerald-200",
  rejected: "bg-rose-50 text-rose-700 border-rose-200",
  withdrawn: "bg-gray-50 text-gray-500 border-gray-200",
};

export default function GroupBookingManagement() {
  const [rows, setRows] = useState<GroupBookingRequestRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [toast, setToast] = useState<{ message: string; type: "success" | "error"; isOpen: boolean }>({
    message: "",
    type: "success",
    isOpen: false,
  });

  // --- Báo giá ---
  const [quoting, setQuoting] = useState<GroupBookingRequestRow | null>(null);
  const [quotePrice, setQuotePrice] = useState("");
  const [quoteFree, setQuoteFree] = useState("0");
  const [quoteExpires, setQuoteExpires] = useState("");
  const [quoteNote, setQuoteNote] = useState("");

  // --- Chốt ---
  const [confirming, setConfirming] = useState<GroupBookingRequestRow | null>(null);
  const [finalGuests, setFinalGuests] = useState("");

  // --- Từ chối ---
  const [rejecting, setRejecting] = useState<GroupBookingRequestRow | null>(null);
  const [rejectReason, setRejectReason] = useState("");

  // --- Sổ giao dịch của đơn đã chốt ---
  const [ledgerFor, setLedgerFor] = useState<GroupBookingRequestRow | null>(null);
  const [ledger, setLedger] = useState<BookingLedger | null>(null);
  const [payKind, setPayKind] = useState("deposit");
  const [payAmount, setPayAmount] = useState("");
  const [payMethod, setPayMethod] = useState("bank_transfer");
  const [payNote, setPayNote] = useState("");
  const [reduceTo, setReduceTo] = useState("");
  const [reduceReason, setReduceReason] = useState("");

  const [saving, setSaving] = useState(false);
  const [dialogError, setDialogError] = useState("");

  const notify = (message: string, type: "success" | "error" = "success") =>
    setToast({ message, type, isOpen: true });

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminService.getGroupBookings(page, statusFilter || undefined);
      setRows(res?.data ?? []);
      setLastPage(res?.last_page ?? 1);
      setTotal(res?.total ?? 0);
    } catch (err) {
      console.error("Lỗi tải yêu cầu đoàn:", err);
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const bat = <T,>(err: T) => {
    const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
    setDialogError(response?.message || "Thao tác không thành công.");
  };

  const guiBaoGia = async () => {
    if (!quoting) return;
    setSaving(true);
    setDialogError("");
    try {
      notify(
        await adminService.quoteGroupBooking(quoting.id, {
          price_per_person: Number(quotePrice),
          free_slots: Number(quoteFree),
          expires_at: quoteExpires,
          note: quoteNote.trim() || undefined,
        }),
      );
      setQuoting(null);
      loadData();
    } catch (err) {
      bat(err);
    } finally {
      setSaving(false);
    }
  };

  const chot = async () => {
    if (!confirming) return;
    setSaving(true);
    setDialogError("");
    try {
      notify(await adminService.confirmGroupBooking(confirming.id, Number(finalGuests)));
      setConfirming(null);
      loadData();
    } catch (err) {
      bat(err);
    } finally {
      setSaving(false);
    }
  };

  const tuChoi = async () => {
    if (!rejecting || rejectReason.trim().length < 10) return;
    setSaving(true);
    setDialogError("");
    try {
      notify(await adminService.rejectGroupBooking(rejecting.id, rejectReason.trim()));
      setRejecting(null);
      loadData();
    } catch (err) {
      bat(err);
    } finally {
      setSaving(false);
    }
  };

  const moSo = async (row: GroupBookingRequestRow) => {
    if (!row.booking) return;
    setLedgerFor(row);
    setLedger(null);
    setPayAmount("");
    setPayNote("");
    setReduceTo("");
    setReduceReason("");
    setDialogError("");
    try {
      setLedger(await adminService.getBookingLedger(row.booking.id));
    } catch (err) {
      console.error("Lỗi tải sổ giao dịch:", err);
    }
  };

  const ghiSo = async () => {
    if (!ledgerFor?.booking) return;
    setSaving(true);
    setDialogError("");
    try {
      notify(
        await adminService.recordBookingPayment(ledgerFor.booking.id, {
          kind: payKind,
          amount: Number(payAmount),
          method: payMethod,
          note: payNote.trim() || undefined,
        }),
      );
      setPayAmount("");
      setPayNote("");
      setLedger(await adminService.getBookingLedger(ledgerFor.booking.id));
      loadData();
    } catch (err) {
      bat(err);
    } finally {
      setSaving(false);
    }
  };

  const giamKhach = async () => {
    if (!ledgerFor?.booking) return;
    setSaving(true);
    setDialogError("");
    try {
      notify(
        await adminService.reduceBookingGuests(
          ledgerFor.booking.id,
          Number(reduceTo),
          reduceReason.trim() || undefined,
        ),
      );
      setReduceTo("");
      setReduceReason("");
      setLedger(await adminService.getBookingLedger(ledgerFor.booking.id));
      loadData();
    } catch (err) {
      bat(err);
    } finally {
      setSaving(false);
    }
  };

  // Tổng dự kiến hiện ngay trong hộp chốt, để điều hành thấy con số trước khi bấm.
  const tongDuKien = (row: GroupBookingRequestRow | null, guests: string): number | null => {
    if (!row?.quote || !guests) return null;
    const n = Number(guests);
    if (!Number.isFinite(n) || n <= row.quote.free_slots) return null;
    return (n - row.quote.free_slots) * row.quote.price_per_person;
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Booking theo đoàn</h1>
        <p className="text-sm text-gray-500 mt-1">
          Yêu cầu → báo giá → chốt. Chỉ bước chốt mới chiếm chỗ của chuyến; trước đó là thương
          lượng, giá do bạn quyết — hệ thống không tính hộ.
        </p>
      </div>

      {/* Lọc theo chặng của đường ống */}
      <div className="flex flex-wrap gap-2">
        {TRANG_THAI.map((item) => (
          <button
            key={item.value}
            type="button"
            onClick={() => {
              setStatusFilter(item.value);
              setPage(1);
            }}
            className={`rounded-full border px-3 py-1 text-xs font-semibold transition-colors cursor-pointer ${
              statusFilter === item.value
                ? "border-primary-600 bg-primary-600 text-white"
                : "border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      <div className="bg-white rounded-lg border border-gray-200 shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 font-medium">Đang tải...</div>
        ) : rows.length === 0 ? (
          <div className="p-12 text-center text-gray-400">Chưa có yêu cầu đoàn nào.</div>
        ) : (
          <div className="divide-y divide-gray-100">
            {rows.map((row) => (
              <div key={row.id} className="p-5 space-y-3">
                <div className="flex flex-wrap items-start gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-bold text-gray-900">{row.contact_name}</span>
                      {row.company_name && (
                        <span className="text-sm text-gray-600">— {row.company_name}</span>
                      )}
                      <span
                        className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                          mauTrangThai[row.status] ?? ""
                        }`}
                      >
                        {row.status_label}
                      </span>
                      {row.quote?.expired && row.status === "quoted" && (
                        <span className="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                          Báo giá đã hết hạn
                        </span>
                      )}
                    </div>

                    <p className="mt-1 text-xs text-gray-500">
                      {row.tour_title} · khởi hành {formatDateTime(row.start_date ?? "")} · ước
                      tính <b>{row.estimated_guests} người</b>
                      {row.remaining_seats !== null && (
                        <>
                          {" "}
                          ·{" "}
                          <span
                            className={
                              row.remaining_seats < row.estimated_guests
                                ? "font-semibold text-rose-600"
                                : "text-gray-500"
                            }
                          >
                            chuyến còn {row.remaining_seats} chỗ
                          </span>
                        </>
                      )}
                    </p>

                    <p className="mt-0.5 text-xs text-gray-400">
                      {row.contact_phone} · {row.contact_email}
                      {row.tax_code && <> · MST {row.tax_code}</>}
                    </p>

                    {row.note && <p className="mt-1 text-xs text-gray-600 italic">“{row.note}”</p>}

                    {row.quote && (
                      <p className="mt-1.5 text-xs text-gray-700">
                        Báo giá: <b>{formatPrice(row.quote.price_per_person)}</b>/người
                        {row.quote.free_slots > 0 && <>, miễn phí {row.quote.free_slots} suất</>}
                        {row.quote.expires_at && (
                          <> · hiệu lực tới {formatDateTime(row.quote.expires_at)}</>
                        )}
                      </p>
                    )}

                    {row.rejected_reason && (
                      <p className="mt-1.5 text-xs text-rose-700">Lý do từ chối: {row.rejected_reason}</p>
                    )}

                    {row.booking && (
                      <p className="mt-1.5 text-xs font-medium text-emerald-800">
                        Đơn #{row.booking.id}: {row.booking.guests} khách ·{" "}
                        {formatPrice(row.booking.total_amount)}
                        {row.booking.paid_in_full ? " · đã thu đủ" : " · chưa thu đủ"}
                      </p>
                    )}
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    {(row.status === "pending_quote" || row.status === "quoted") && (
                      <>
                        <button
                          type="button"
                          onClick={() => {
                            setQuoting(row);
                            setQuotePrice(String(row.quote?.price_per_person ?? ""));
                            setQuoteFree(String(row.quote?.free_slots ?? 0));
                            setQuoteExpires("");
                            setQuoteNote(row.quote?.note ?? "");
                            setDialogError("");
                          }}
                          className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700"
                        >
                          {row.quote ? "Báo giá lại" : "Báo giá"}
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            setRejecting(row);
                            setRejectReason("");
                            setDialogError("");
                          }}
                          className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                        >
                          Từ chối
                        </button>
                      </>
                    )}

                    {row.status === "quoted" && (
                      <button
                        type="button"
                        onClick={() => {
                          setConfirming(row);
                          setFinalGuests(String(row.estimated_guests));
                          setDialogError("");
                        }}
                        className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                      >
                        Chốt thành đơn
                      </button>
                    )}

                    {row.booking && (
                      <button
                        type="button"
                        onClick={() => moSo(row)}
                        className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                      >
                        Sổ thu tiền
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {!loading && lastPage > 1 && (
          <Pagination
            currentPage={page}
            lastPage={lastPage}
            total={total}
            perPage={15}
            onPageChange={setPage}
            itemLabel="yêu cầu"
          />
        )}
      </div>

      {/* Báo giá — giá là quyết định của con người, hộp thoại chỉ ghi lại */}
      <Modal
        isOpen={!!quoting}
        onClose={() => setQuoting(null)}
        title={`Báo giá cho ${quoting?.contact_name ?? ""}`}
        subtitle={`Đoàn ước tính ${quoting?.estimated_guests ?? 0} người. Giảm bao nhiêu là việc của bạn — hệ thống không gợi ý giá.`}
        onSubmit={(e) => {
          e.preventDefault();
          guiBaoGia();
        }}
        size="lg"
        footer={
          <>
            <button type="button" onClick={() => setQuoting(null)} className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 cursor-pointer">
              Đóng
            </button>
            <button type="submit" disabled={saving || !quotePrice || !quoteExpires} className="px-4 py-2 bg-primary-600 text-sm font-semibold rounded-md text-white hover:bg-primary-700 disabled:opacity-40 cursor-pointer">
              {saving ? "Đang lưu..." : "Lưu báo giá"}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Giá mỗi người (đ)
              </label>
              <input
                type="number"
                min={1}
                value={quotePrice}
                onChange={(e) => setQuotePrice(e.target.value)}
                className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Suất miễn phí
              </label>
              <input
                type="number"
                min={0}
                value={quoteFree}
                onChange={(e) => setQuoteFree(e.target.value)}
                className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
              />
              <span className="text-[10px] text-gray-400 mt-1 block">
                Thông lệ: trưởng đoàn đi không tính tiền. Miễn tiền nhưng vẫn chiếm ghế.
              </span>
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
              Báo giá có hiệu lực tới
            </label>
            <input
              type="datetime-local"
              value={quoteExpires}
              onChange={(e) => setQuoteExpires(e.target.value)}
              className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
            />
            <span className="text-[10px] text-gray-400 mt-1 block">
              Chỗ đang bán cho khách lẻ — giá không thể treo vô thời hạn cho một đoàn chưa chắc đi.
            </span>
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
              Ghi chú gửi khách
            </label>
            <textarea
              rows={2}
              value={quoteNote}
              onChange={(e) => setQuoteNote(e.target.value)}
              placeholder="VD: Giá đã gồm gala tối theo yêu cầu."
              className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
            />
          </div>

          {dialogError && (
            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{dialogError}</p>
          )}
        </div>
      </Modal>

      {/* Chốt — bước duy nhất chiếm chỗ */}
      <Modal
        isOpen={!!confirming}
        onClose={() => setConfirming(null)}
        title={`Chốt đoàn của ${confirming?.contact_name ?? ""}`}
        subtitle="Số khách chốt là con số hai bên vừa thống nhất — có thể khác số ước tính lúc gửi yêu cầu."
        onSubmit={(e) => {
          e.preventDefault();
          chot();
        }}
        size="md"
        footer={
          <>
            <button type="button" onClick={() => setConfirming(null)} className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 cursor-pointer">
              Đóng
            </button>
            <button type="submit" disabled={saving || !finalGuests} className="px-4 py-2 bg-emerald-600 text-sm font-semibold rounded-md text-white hover:bg-emerald-700 disabled:opacity-40 cursor-pointer">
              {saving ? "Đang chốt..." : "Chốt và tạo đơn"}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
              Số khách chốt
            </label>
            <input
              type="number"
              min={1}
              value={finalGuests}
              onChange={(e) => setFinalGuests(e.target.value)}
              className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
            />
            {confirming?.remaining_seats !== null && confirming?.remaining_seats !== undefined && (
              <span className="text-[10px] text-gray-400 mt-1 block">
                Chuyến còn {confirming.remaining_seats} chỗ. Thiếu chỗ thì máy chủ từ chối — đoàn
                to không phải lý do được vượt chỗ.
              </span>
            )}
          </div>

          {tongDuKien(confirming, finalGuests) !== null && (
            <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
              Tổng dự kiến:{" "}
              <b>{formatPrice(tongDuKien(confirming, finalGuests) as number)}</b>
              {" "}({finalGuests} người − {confirming?.quote?.free_slots ?? 0} suất miễn phí, giá{" "}
              {formatPrice(confirming?.quote?.price_per_person ?? 0)}/người)
            </p>
          )}

          {dialogError && (
            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{dialogError}</p>
          )}
        </div>
      </Modal>

      {/* Từ chối */}
      <Modal
        isOpen={!!rejecting}
        onClose={() => setRejecting(null)}
        title={`Từ chối yêu cầu của ${rejecting?.contact_name ?? ""}`}
        subtitle="Khách đọc được lý do này khi tra cứu — viết cho người nhận, không viết cho hồ sơ."
        onSubmit={(e) => {
          e.preventDefault();
          tuChoi();
        }}
        size="md"
        footer={
          <>
            <button type="button" onClick={() => setRejecting(null)} className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 cursor-pointer">
              Đóng
            </button>
            <button type="submit" disabled={saving || rejectReason.trim().length < 10} className="px-4 py-2 bg-rose-600 text-sm font-semibold rounded-md text-white hover:bg-rose-700 disabled:opacity-40 cursor-pointer">
              {saving ? "Đang gửi..." : "Từ chối"}
            </button>
          </>
        }
      >
        <div className="space-y-3">
          <textarea
            rows={3}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            placeholder="VD: Chuyến này chỉ còn 8 chỗ, không nhận thêm đoàn 40 người được. Gợi ý chuyển sang chuyến 25/09."
            className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-rose-400"
          />
          <p className="text-[11px] text-gray-400">Ít nhất 10 ký tự.</p>
          {dialogError && (
            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{dialogError}</p>
          )}
        </div>
      </Modal>

      {/* Sổ giao dịch + giảm số khách của đơn đã chốt */}
      <Modal
        isOpen={!!ledgerFor}
        onClose={() => setLedgerFor(null)}
        title={`Sổ thu tiền — đơn #${ledgerFor?.booking?.id ?? ""}`}
        subtitle="Chỉ thêm dòng, không sửa dòng cũ. Ghi nhầm thì ghi một dòng hoàn điều chỉnh lại."
        size="2xl"
        footer={
          <button type="button" onClick={() => setLedgerFor(null)} className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 cursor-pointer">
            Đóng
          </button>
        }
      >
        {!ledger ? (
          <p className="text-sm text-gray-500">Đang tải sổ...</p>
        ) : (
          <div className="space-y-5">
            <div className="grid grid-cols-3 gap-3 text-center">
              <div className="rounded-lg bg-gray-50 p-3">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Tổng đơn</p>
                <p className="text-sm font-bold text-gray-900">{formatPrice(ledger.total_amount)}</p>
              </div>
              <div className="rounded-lg bg-gray-50 p-3">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Đã thu thực</p>
                <p className="text-sm font-bold text-gray-900">{formatPrice(ledger.net_paid)}</p>
              </div>
              <div className="rounded-lg bg-gray-50 p-3">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Còn lại</p>
                <p className={`text-sm font-bold ${ledger.net_paid >= ledger.total_amount ? "text-emerald-700" : "text-amber-700"}`}>
                  {formatPrice(Math.max(0, ledger.total_amount - ledger.net_paid))}
                </p>
              </div>
            </div>

            {/* Đã thu vượt tổng (thường sau khi giảm khách): nói thẳng, để điều hành ghi hoàn. */}
            {ledger.net_paid > ledger.total_amount && (
              <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                Đã thu vượt tổng mới {formatPrice(ledger.net_paid - ledger.total_amount)} — thống
                nhất với khách rồi ghi một khoản hoàn bên dưới. Hệ thống không tự chuyển tiền.
              </p>
            )}

            <div className="space-y-1.5">
              {ledger.entries.length === 0 && (
                <p className="text-xs text-gray-400">Chưa có khoản nào. Tiền cọc về thì ghi dòng đầu tiên.</p>
              )}
              {ledger.entries.map((entry) => (
                <div key={entry.id} className="flex flex-wrap items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-xs">
                  <span className={`rounded px-1.5 py-0.5 font-semibold ${entry.kind === "refund" ? "bg-rose-50 text-rose-700" : "bg-emerald-50 text-emerald-700"}`}>
                    {entry.kind_label}
                  </span>
                  <span className="font-bold text-gray-900">
                    {entry.kind === "refund" ? "−" : "+"}
                    {formatPrice(entry.amount)}
                  </span>
                  <span className="text-gray-400">{formatDateTime(entry.paid_at)}</span>
                  {entry.note && <span className="text-gray-500">· {entry.note}</span>}
                  {entry.recorded_by && <span className="ml-auto text-gray-400">{entry.recorded_by} ghi</span>}
                </div>
              ))}
            </div>

            <div className="rounded-lg border border-gray-200 p-3 space-y-3">
              <p className="text-xs font-bold text-gray-900">Ghi khoản mới</p>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <select
                  value={payKind}
                  onChange={(e) => setPayKind(e.target.value)}
                  className="px-3 py-2 text-sm border border-gray-200 rounded-md bg-white cursor-pointer"
                >
                  <option value="deposit">Tiền cọc</option>
                  <option value="balance">Thanh toán phần còn lại</option>
                  <option value="refund">Hoàn tiền</option>
                </select>
                <input
                  type="number"
                  min={1}
                  placeholder="Số tiền (đ)"
                  value={payAmount}
                  onChange={(e) => setPayAmount(e.target.value)}
                  className="px-3 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
                />
                <select
                  value={payMethod}
                  onChange={(e) => setPayMethod(e.target.value)}
                  className="px-3 py-2 text-sm border border-gray-200 rounded-md bg-white cursor-pointer"
                >
                  <option value="bank_transfer">Chuyển khoản</option>
                  <option value="cash">Tiền mặt</option>
                  <option value="gateway">Qua cổng</option>
                </select>
              </div>
              <input
                type="text"
                placeholder="Ghi chú (VD: Cọc 30% theo hợp đồng)"
                value={payNote}
                onChange={(e) => setPayNote(e.target.value)}
                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
              />
              <div className="flex justify-end">
                <button
                  type="button"
                  onClick={ghiSo}
                  disabled={saving || !payAmount}
                  className="rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-40"
                >
                  {saving ? "Đang ghi..." : "Ghi vào sổ"}
                </button>
              </div>
            </div>

            {/*
              Giảm số khách — đặc quyền của đoàn, và chỉ trước hạn chốt danh sách.
              Đơn lẻ muốn đổi số người vẫn phải hủy đặt lại; máy chủ giữ luật đó, không phải màn này.
            */}
            <div className="rounded-lg border border-amber-200 bg-amber-50/40 p-3 space-y-3">
              <p className="text-xs font-bold text-amber-900">
                Giảm số khách (hiện {ledgerFor?.booking?.guests} người)
              </p>
              <p className="text-[11px] text-amber-800/80">
                “3 người bận việc” không phải lý do hủy cả đoàn. Chỉ được trước hạn chốt danh sách —
                sau đó phòng và suất ăn đã đặt, bớt người không bớt được chi phí.
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input
                  type="number"
                  min={1}
                  placeholder="Số khách mới"
                  value={reduceTo}
                  onChange={(e) => setReduceTo(e.target.value)}
                  className="px-3 py-2 text-sm border border-gray-200 rounded-md bg-white focus:outline-none focus:border-amber-500"
                />
                <input
                  type="text"
                  placeholder="Lý do"
                  value={reduceReason}
                  onChange={(e) => setReduceReason(e.target.value)}
                  className="px-3 py-2 text-sm border border-gray-200 rounded-md bg-white focus:outline-none focus:border-amber-500"
                />
              </div>
              <div className="flex justify-end">
                <button
                  type="button"
                  onClick={giamKhach}
                  disabled={saving || !reduceTo}
                  className="rounded-lg border border-amber-300 bg-white px-4 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-40"
                >
                  {saving ? "Đang xử lý..." : "Giảm số khách"}
                </button>
              </div>
            </div>

            {dialogError && (
              <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{dialogError}</p>
            )}
          </div>
        )}
      </Modal>

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((prev) => ({ ...prev, isOpen: false }))}
      />
    </div>
  );
}
