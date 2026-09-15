import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Select as AntSelect,
} from "antd";
import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, Banknote, Check, Copy, Loader2 } from "lucide-react";
import adminService from "@/services/adminService";
import type { RefundQueueRow } from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * Những đơn công ty còn nợ tiền khách.
 *
 * Trước màn hình này, `refund_amount` chỉ là một con số nằm trên bản ghi: hệ thống nói với khách
 * "bạn được hoàn 2.400.000đ" rồi thôi. Không chỗ nào trả lời được câu "đơn nào đã chuyển tiền,
 * đơn nào chưa" — mà đó là câu kế toán phải trả lời hằng ngày.
 *
 * Còn nợ = số phải hoàn trừ tổng các khoản hoàn đã ghi vào sổ. Ghi một khoản hoàn là cách một đơn
 * rời khỏi danh sách này; không có nút "đánh dấu đã xong" riêng, vì một cái tick không nói được
 * đã chuyển bao nhiêu, ngày nào, ai chuyển.
 */

const layLoi = (err: unknown, macDinh: string) =>
  (err as { response?: { data?: { message?: string } } })?.response?.data?.message || macDinh;

export default function RefundManagement({ onChanged }: { onChanged?: () => void } = {}) {
  const [rows, setRows] = useState<RefundQueueRow[]>([]);
  const [outstandingTotal, setOutstandingTotal] = useState(0);
  const [daTra, setDaTra] = useState(false);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState("");
  const [copied, setCopied] = useState<number | null>(null);

  const [paying, setPaying] = useState<RefundQueueRow | null>(null);
  const [amount, setAmount] = useState("");
  const [reference, setReference] = useState("");
  const [method, setMethod] = useState<"bank_transfer" | "cash">("bank_transfer");
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState("");

  const taiDanhSach = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getRefundQueue(daTra);
      setRows(result?.data ?? []);
      setOutstandingTotal(result?.outstanding_total ?? 0);
    } catch (err) {
      console.error("Lỗi tải danh sách hoàn tiền:", err);
    } finally {
      setLoading(false);
    }
  }, [daTra]);

  useEffect(() => {
    taiDanhSach();
  }, [taiDanhSach]);

  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(""), 6000);
    return () => clearTimeout(timer);
  }, [toast]);

  const moFormChi = (row: RefundQueueRow) => {
    setPaying(row);
    // Điền sẵn đúng số còn nợ: chi lẻ là ngoại lệ, chi đủ mới là việc thường ngày.
    setAmount(String(row.refund_outstanding));
    setReference("");
    setMethod("bank_transfer");
    setError("");
  };

  const ghiKhoanHoan = async () => {
    if (!paying) return;

    setActionLoading(true);
    setError("");

    try {
      setToast(
        await adminService.recordBookingPayment(paying.id, {
          kind: "refund",
          amount: Number(amount),
          method,
          reference: reference.trim() || undefined,
          note: "Hoàn tiền cho đơn đã hủy #" + paying.id,
        }),
      );
      setPaying(null);
      await taiDanhSach();
      onChanged?.();
    } catch (err) {
      setError(layLoi(err, "Không ghi được khoản hoàn."));
    } finally {
      setActionLoading(false);
    }
  };

  const chepSoTaiKhoan = async (row: RefundQueueRow) => {
    if (!row.refund_bank?.account_number) return;

    try {
      await navigator.clipboard.writeText(row.refund_bank.account_number);
      setCopied(row.id);
      setTimeout(() => setCopied(null), 2000);
    } catch {
      // Trình duyệt chặn quyền ghi bảng nhớ tạm. Không báo lỗi: số vẫn hiện trên màn hình để
      // người dùng gõ tay, và một hộp thoại lỗi ở đây chỉ làm phiền.
    }
  };

  return (
    <UIFlex vertical gap="large" ><p className="text-sm text-gray-500">
        Các đơn đã hủy còn nghĩa vụ trả tiền lại cho khách. Ghi khoản đã chuyển vào sổ để đơn rời
        khỏi danh sách này.
      </p><div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <p className="text-xs font-semibold uppercase tracking-wider text-amber-700">
          Tổng còn phải trả khách
        </p>
        <p className="mt-1 text-3xl font-bold text-amber-900">{formatPrice(outstandingTotal)}</p>
      </div>{toast && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {toast}
        </div>
      )}<UIFlex      gap={8}>{[
          { key: false, label: "Còn phải trả" },
          { key: true, label: "Đã trả xong" },
        ].map((tab) => (
          <AntButton type={(daTra === tab.key) ? "primary" : "default"} key={String(tab.key)} onClick={() => setDaTra(tab.key)} htmlType="button">{tab.label}</AntButton>
        ))}</UIFlex>{loading ? (
        <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
          <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
        </div>
      ) : rows.length === 0 ? (
        <UICard  ><UIFlex vertical gap="middle">{daTra ? "Chưa có khoản hoàn nào đã trả xong." : "Không còn khoản nào phải trả khách."}</UIFlex></UICard>
      ) : (
        <UIFlex vertical gap={16} >{rows.map((row) => (
            <UICard key={row.id} ><UIFlex vertical gap="middle"><UIFlex   wrap align="start" justify="space-between" gap={16}><div>
                  <p className="font-bold text-gray-900">
                    Đơn #{row.id} · {row.customer_name}
                  </p>
                  <p className="mt-0.5 text-xs text-gray-500">
                    {row.tour_title ?? "Tour đã xóa"}
                    {row.cancelled_at && ` · hủy ${formatDateTime(row.cancelled_at)}`}
                  </p>
                  <p className="mt-0.5 text-xs text-gray-400">
                    {row.customer_email}
                    {row.customer_phone && ` · ${row.customer_phone}`}
                  </p>
                </div><div className="text-right">
                  <p className="text-xs font-medium text-gray-500">Còn phải trả</p>
                  <p className="text-xl font-bold text-amber-700">
                    {formatPrice(row.refund_outstanding)}
                  </p>
                  {row.refunded > 0 && (
                    <p className="text-[11px] text-gray-400">
                      đã trả {formatPrice(row.refunded)} / {formatPrice(row.refund_due)}
                    </p>
                  )}
                </div></UIFlex>{row.cancel_reason && (
                <p className="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                  <strong>Lý do hủy:</strong> {row.cancel_reason}
                </p>
              )}{row.refund_bank ? (
                <div className="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-gray-50/60 p-3 text-xs">
                  <span>
                    <span className="text-gray-500">Số tài khoản:</span>{" "}
                    <strong className="font-mono text-gray-900">
                      {row.refund_bank.account_number}
                    </strong>
                    <AntButton onClick={() => chepSoTaiKhoan(row)} htmlType="button">{copied === row.id ? (
                        <>
                          <Check className="h-3 w-3" /> Đã chép
                        </>
                      ) : (
                        <>
                          <Copy className="h-3 w-3" /> Chép
                        </>
                      )}</AntButton>
                  </span>
                  <span>
                    <span className="text-gray-500">Ngân hàng:</span>{" "}
                    <strong>{row.refund_bank.bank_name ?? "—"}</strong>
                  </span>
                  <span>
                    <span className="text-gray-500">Chủ tài khoản:</span>{" "}
                    <strong>{row.refund_bank.account_holder ?? "—"}</strong>
                  </span>
                </div>
              ) : (
                /*
                 * Đơn hủy qua đường điều hành hoặc hủy cả chuyến thì khách chưa từng khai tài
                 * khoản. Nói thẳng ra để người làm biết phải gọi điện, thay vì để họ tìm mãi
                 * một ô số tài khoản không tồn tại.
                 */
                <p className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                  <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                  Khách chưa khai tài khoản nhận hoàn (đơn này không hủy qua đường khách tự gửi
                  yêu cầu). Gọi cho khách theo số ở trên để lấy thông tin trước khi chuyển.
                </p>
              )}{row.refund_outstanding > 0 && (
                <div className="border-t border-gray-100 pt-3">
                  <AntButton onClick={() => moFormChi(row)} type="primary" htmlType="button"><Banknote className="h-3.5 w-3.5" />Ghi nhận đã chuyển tiền
                  </AntButton>
                </div>
              )}</UIFlex></UICard>
          ))}</UIFlex>
      )}<Modal
        isOpen={paying !== null}
        onClose={() => setPaying(null)}
        title={`Ghi khoản hoàn cho đơn #${paying?.id ?? ""}`}
      >
        <UIFlex vertical gap={16} ><p className="text-sm text-gray-600">
            Ghi lại khoản tiền vừa chuyển cho khách. Sổ chỉ thêm dòng, không sửa dòng cũ — ghi
            nhầm thì ghi một dòng điều chỉnh, không xóa.
          </p>{error && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {error}
            </p>
          )}<label className="block">
            <span className="text-xs font-semibold text-gray-700">Số tiền</span>
            <AntInput type="number" min={1} value={amount} onChange={(e) => setAmount(e.target.value)} style={{ width: "100%" }} />
            <span className="mt-1 block text-[11px] text-gray-500">
              Còn nợ {formatPrice(paying?.refund_outstanding ?? 0)}
            </span>
          </label><label className="block">
            <span className="text-xs font-semibold text-gray-700">Hình thức</span>
            <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((method) ?? "")} onChange={(e) => setMethod(e as typeof method)} style={{ width: "100%" }} options={[{ value: String("bank_transfer"), label: "Chuyển khoản", disabled: false },{ value: String("cash"), label: "Tiền mặt", disabled: false }].flat().filter((option) => !!option)} />
          </label><label className="block">
            <span className="text-xs font-semibold text-gray-700">
              Mã giao dịch / chứng từ
            </span>
            <AntInput value={reference} onChange={(e) => setReference(e.target.value)} placeholder="FT26083012345" style={{ width: "100%" }} />
          </label><UIFlex     justify="end" gap={8}><AntButton onClick={() => setPaying(null)} htmlType="button">Hủy
            </AntButton><AntButton onClick={ghiKhoanHoan} disabled={actionLoading || Number(amount) <= 0} type="primary" htmlType="button">{actionLoading ? "Đang ghi..." : "Ghi vào sổ"}</AntButton></UIFlex></UIFlex>
      </Modal></UIFlex>
  );
}
