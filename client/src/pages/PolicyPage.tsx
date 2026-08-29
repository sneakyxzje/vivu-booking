import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import policyService from "@/services/policyService";
import type { PolicyResponse } from "@/services/policyService";
import { formatPrice } from "@/utils/format";

/**
 * Chính sách hủy, đổi và hoàn tiền — bản khách đọc.
 *
 * ## Vì sao trang này gọi API thay vì viết cứng
 *
 * Bảng phí hủy nằm trong cơ sở dữ liệu và điều hành sửa được. Chép nó thành chữ ở đây thì có hai
 * bản: bản khách đọc và bản hệ thống tính. Hai bản giống nhau đúng tới lần sửa đầu tiên, và từ đó
 * trang này hứa một đằng còn lúc hủy đơn trừ tiền một nẻo.
 *
 * Mấy con số trong phần hỏi đáp cũng vậy — hạn báo trước, phí đổi lịch, thời gian giữ chỗ — đều là
 * hằng số có thật trong mã máy chủ, lấy về chứ không gõ lại.
 *
 * ## Phần hỏi đáp trả lời theo đúng thứ hệ thống làm
 *
 * Không có câu nào mô tả một quy trình mà mã không thực hiện. Mỗi câu dưới đây tương ứng với một
 * luật đang chạy: phí tính trên giá trị đơn, tiền hoàn kẹp dưới ở 0, hãng hủy thì hoàn đủ, không
 * chuyển chuyến được sau hạn chốt, và sửa bảng phí không hồi tố lên đơn đã bán.
 */

const CauHoi = ({ hoi, children }: { hoi: string; children: React.ReactNode }) => (
  <details className="group border-b border-gray-100 py-4 last:border-b-0">
    <summary className="flex cursor-pointer list-none items-start justify-between gap-4 text-[15px] font-semibold text-gray-900">
      {hoi}
      <span className="mt-0.5 shrink-0 text-gray-400 transition-transform group-open:rotate-45">
        +
      </span>
    </summary>
    <div className="mt-3 space-y-2 text-[15px] leading-relaxed text-gray-600">{children}</div>
  </details>
);

export default function PolicyPage() {
  const [data, setData] = useState<PolicyResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    policyService
      .get()
      .then((res) => {
        if (res) setData(res);
        else setError(true);
      })
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="bg-gray-50 py-12">
      <div className="mx-auto max-w-3xl px-4">
        <header className="mb-8">
          <h1 className="text-3xl font-bold tracking-tight text-gray-950">
            Chính sách hủy, đổi và hoàn tiền
          </h1>
          <p className="mt-2 text-[15px] leading-relaxed text-gray-600">
            Toàn bộ mức phí dưới đây là mức hệ thống thực sự áp khi bạn hủy hoặc đổi chuyến. Bảng
            này lấy trực tiếp từ hệ thống, không phải bản chép tay.
          </p>
        </header>

        {loading && (
          <p className="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
            Đang tải chính sách...
          </p>
        )}

        {error && (
          <p className="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
            Không tải được chính sách. Vui lòng thử lại, hoặc gọi tổng đài{" "}
            <strong>1900 1234</strong> để được đọc trực tiếp.
          </p>
        )}

        {data && (
          <div className="space-y-8">
            {/* --- Bảng phí hủy --- */}
            <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
              <div className="border-b border-gray-100 px-6 py-5">
                <h2 className="text-lg font-bold text-gray-900">
                  {data.cancellation.name}
                </h2>
                {data.cancellation.description && (
                  <p className="mt-1.5 text-sm leading-relaxed text-gray-600">
                    {data.cancellation.description}
                  </p>
                )}
                {data.cancellation.effective_from && (
                  <p className="mt-2 text-xs text-gray-400">
                    Áp dụng từ {data.cancellation.effective_from}. Đơn đặt trước thời điểm này giữ
                    nguyên điều khoản cũ.
                  </p>
                )}
              </div>

              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                  <thead className="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-500">
                    <tr>
                      <th className="px-6 py-3">Hủy trước ngày khởi hành</th>
                      <th className="px-6 py-3 w-32">Được hoàn</th>
                      <th className="px-6 py-3">Vì sao</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {data.cancellation.rules.map((bac, i) => (
                      <tr key={i}>
                        <td className="px-6 py-3.5 font-medium text-gray-900">{bac.window}</td>
                        <td className="px-6 py-3.5">
                          <span
                            className={`font-bold ${
                              bac.refund_percent >= 70
                                ? "text-emerald-700"
                                : bac.refund_percent > 0
                                  ? "text-amber-700"
                                  : "text-rose-700"
                            }`}
                          >
                            {bac.refund_percent}%
                          </span>
                        </td>
                        <td className="px-6 py-3.5 text-gray-500">{bac.note ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            {/* --- Hỏi đáp --- */}
            <section className="rounded-2xl border border-gray-100 bg-white px-6 py-2 shadow-sm">
              <h2 className="border-b border-gray-100 py-4 text-lg font-bold text-gray-900">
                Câu hỏi thường gặp
              </h2>

              <CauHoi hoi="Tôi được hoàn bao nhiêu tiền khi hủy?">
                <p>
                  Phần trăm lấy theo bảng phía trên, tính theo số ngày còn lại tới giờ khởi hành.
                </p>
                <p>
                  Có một chi tiết dễ nhầm: <strong>phí hủy tính trên tổng giá trị đơn</strong>, còn
                  tiền hoàn thì trừ trên <strong>số bạn đã thực trả</strong>. Nếu bạn mới đặt cọc
                  một phần mà hủy sát ngày đi, phần cọc ấy có thể mất hết. Đổi lại, tiền hoàn không
                  bao giờ âm — hủy tour thì bạn không phải nộp thêm đồng nào, kể cả khi phí hủy lớn
                  hơn số đã trả.
                </p>
              </CauHoi>

              <CauHoi hoi="Công ty hủy chuyến thì tôi có mất phí không?">
                <p>
                  Không. Chuyến bị hủy vì phía công ty — thời tiết, không đủ số khách tối thiểu, sự
                  cố nhà cung cấp — thì bạn được <strong>hoàn 100% số đã trả</strong>, không trừ
                  bất kỳ khoản nào, bất kể còn mấy ngày tới ngày đi.
                </p>
                <p>
                  Bảng phí phía trên chỉ áp khi <em>bạn</em> là người hủy.
                </p>
              </CauHoi>

              <CauHoi hoi="Chuyến không đủ khách thì sao?">
                <p>
                  Mỗi chuyến có một số khách tối thiểu để chạy được. Không đạt tới hạn chốt danh
                  sách thì công ty hủy chuyến và hoàn đủ tiền, hoặc mời bạn chuyển sang một chuyến
                  khác nếu bạn muốn — quyền chọn là của bạn.
                </p>
              </CauHoi>

              <CauHoi hoi="Tôi đổi sang chuyến khác được không?">
                <p>
                  Được, và <strong>{data.transfer.free_transfers} lần đầu miễn phí</strong>. Bạn
                  cần báo trước ngày khởi hành ít nhất{" "}
                  <strong>{data.transfer.notice_days} ngày</strong>; từ lần đổi thứ{" "}
                  {data.transfer.free_transfers + 1} trở đi có phí đổi lịch{" "}
                  <strong>{formatPrice(data.transfer.fee)}</strong>, vì mỗi lần đổi đều kéo theo
                  việc báo lại với khách sạn và nhà xe.
                </p>
                <p>
                  Chuyến mới đắt hơn thì bù chênh lệch, rẻ hơn thì được hoàn phần chênh. Việc đổi
                  chuyến do bộ phận điều hành thực hiện sau khi trao đổi và thống nhất với bạn —
                  công ty không tự dời ngày đi của khách.
                </p>
              </CauHoi>

              <CauHoi hoi="Vì sao sát ngày đi lại không đổi được chuyến nữa?">
                <p>
                  Mỗi chuyến có một <strong>hạn chốt danh sách</strong>, mặc định{" "}
                  {data.booking.deadline_days} ngày trước khởi hành. Qua mốc đó, suất của bạn đã
                  được trả tiền cho khách sạn và nhà xe, không rút lại được — nên nó không chuyển đi
                  đâu được nữa.
                </p>
                <p>
                  Nếu sau mốc đó bạn không đi được, đây là trường hợp hủy đơn chứ không phải đổi
                  chuyến, và áp bảng phí phía trên.
                </p>
              </CauHoi>

              <CauHoi hoi="Đặt xong bao lâu thì phải thanh toán?">
                <p>
                  Đơn giữ chỗ trong <strong>{data.booking.payment_ttl_minutes} phút</strong>. Quá
                  thời gian đó mà chưa thanh toán, hệ thống tự hủy đơn và trả chỗ lại cho khách
                  khác. Bạn vẫn đặt lại được nếu chuyến còn chỗ.
                </p>
              </CauHoi>

              <CauHoi hoi="Công ty sửa bảng phí thì đơn tôi đã đặt có bị ảnh hưởng không?">
                <p>
                  Không. Đơn của bạn <strong>giữ nguyên bảng phí tại thời điểm bạn đặt</strong> —
                  hệ thống chép điều khoản vào chính đơn đó lúc đặt, chứ không đọc lại bảng hiện
                  hành khi bạn hủy.
                </p>
                <p>
                  Bảng phí mới chỉ áp cho đơn đặt từ thời điểm nó có hiệu lực trở đi.
                </p>
              </CauHoi>

              <CauHoi hoi="Đi dọc đường phát sinh chi phí thì ai trả?">
                <p>
                  Những gì tour đã bao gồm — di chuyển từ điểm A tới điểm B, chỗ ở, các bữa ăn ghi
                  trong chương trình — thì công ty lo, kể cả khi phải đổi phương án vì mưa bão hay
                  xe hỏng. Đó là thứ công ty đã bán cho bạn.
                </p>
                <p>
                  Chi tiêu cá nhân ngoài chương trình — đồ uống thêm, mua sắm, dịch vụ bạn tự chọn
                  thêm — thì bạn tự chi trả. Mọi khoản phát sinh cần bạn trả đều phải được thông báo
                  và có sự đồng ý của bạn trước khi thu.
                </p>
              </CauHoi>

              <CauHoi hoi="Tôi hủy đơn ở đâu?">
                <p>
                  Vào <Link to="/booking-lookup" className="font-semibold text-primary-600 hover:underline">Tra cứu đơn</Link>{" "}
                  bằng mã đơn, hoặc mở mục Đơn của tôi nếu bạn có tài khoản. Trước khi xác nhận, hệ
                  thống hiện sẵn số tiền bạn sẽ được hoàn theo đúng bảng trên — bạn thấy con số rồi
                  mới quyết.
                </p>
              </CauHoi>
            </section>

            <p className="text-center text-sm text-gray-500">
              Còn thắc mắc? Gọi <strong className="text-gray-700">1900 1234</strong> (8:00 – 21:00
              hằng ngày) hoặc xem thêm{" "}
              <Link to="/terms" className="font-semibold text-primary-600 hover:underline">
                Điều khoản sử dụng
              </Link>
              .
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
