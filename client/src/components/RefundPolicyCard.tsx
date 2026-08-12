import { useEffect, useState } from "react";
import bookingService from "@/services/bookingService";
import type { RefundQuote } from "@/services/bookingService";
import { formatPrice } from "@/utils/format";

/**
 * Điều khoản hủy của đơn, kèm số tiền khách thực nhận nếu hủy ngay bây giờ.
 *
 * Hiển thị con số TRƯỚC khi khách bấm hủy. Phần lớn khiếu nại sau hủy đến từ việc khách
 * không biết trước mình mất bao nhiêu, nên bảng phí đặt ngay cạnh con số để đối chiếu được
 * mình đang rơi vào bậc nào.
 */
export const RefundPolicyCard = ({ publicToken }: { publicToken: string }) => {
  const [quote, setQuote] = useState<RefundQuote | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let huy = false;

    const load = async () => {
      try {
        const response = await bookingService.getRefundQuote(publicToken);
        if (!huy) setQuote(response.data?.data ?? null);
      } catch {
        if (!huy) setQuote(null);
      } finally {
        if (!huy) setLoading(false);
      }
    };

    load();

    return () => {
      huy = true;
    };
  }, [publicToken]);

  if (loading || !quote) return null;

  const daKhoiHanh = quote.hours_before !== null && quote.hours_before < 0;
  const soNgayConLai =
    quote.hours_before !== null ? Math.floor(quote.hours_before / 24) : null;

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="text-sm font-bold text-gray-950">Điều khoản hủy</h3>
          {quote.policy_name && (
            <p className="mt-0.5 text-xs text-gray-500">{quote.policy_name}</p>
          )}
        </div>
        {soNgayConLai !== null && !daKhoiHanh && (
          <span className="shrink-0 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600">
            Còn {soNgayConLai} ngày tới khởi hành
          </span>
        )}
      </div>

      {daKhoiHanh ? (
        <p className="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
          Chuyến đi đã khởi hành nên đơn này không còn hủy được. Vui lòng liên hệ điều hành
          nếu cần hỗ trợ.
        </p>
      ) : (
        <div
          className={`mt-4 rounded-lg border p-4 ${
            quote.refund_amount > 0
              ? "border-emerald-200 bg-emerald-50"
              : "border-amber-200 bg-amber-50"
          }`}
        >
          <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
            Nếu hủy bây giờ
          </p>
          <p
            className={`mt-1 text-2xl font-bold ${
              quote.refund_amount > 0 ? "text-emerald-700" : "text-amber-700"
            }`}
          >
            {formatPrice(quote.refund_amount)}
          </p>
          <p className="mt-1 text-xs text-gray-600">
            Hoàn {quote.refund_percent}% giá trị đơn. Phí hủy{" "}
            {formatPrice(quote.cancellation_fee)} trên tổng {formatPrice(quote.total_amount)}.
          </p>
          {quote.paid_amount === 0 && (
            <p className="mt-1 text-xs text-gray-500">
              Đơn chưa thanh toán nên không có khoản nào để hoàn.
            </p>
          )}
        </div>
      )}

      {quote.rules && quote.rules.length > 0 && (
        <div className="mt-4">
          <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
            Bảng phí theo thời điểm hủy
          </p>
          <div className="overflow-hidden rounded-lg border border-gray-200">
            <table className="w-full text-xs">
              <tbody className="divide-y divide-gray-100">
                {quote.rules.map((rule) => {
                  const dangApDung = rule.refund_percent === quote.refund_percent && !daKhoiHanh;

                  return (
                    <tr
                      key={rule.window}
                      className={dangApDung ? "bg-primary-50 font-bold text-primary-800" : "text-gray-600"}
                    >
                      <td className="px-3 py-2">{rule.window}</td>
                      <td className="px-3 py-2 text-right">Hoàn {rule.refund_percent}%</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="mt-2 text-[11px] leading-relaxed text-gray-500">
            Phí hủy tính trên giá trị đơn, tiền hoàn trừ trên số tiền đã thanh toán. Hủy không
            bao giờ phát sinh khoản phải nộp thêm.
          </p>
        </div>
      )}
    </div>
  );
};
