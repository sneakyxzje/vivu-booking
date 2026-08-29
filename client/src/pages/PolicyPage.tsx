import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  ArrowLeftRight,
  BadgeCheck,
  CalendarClock,
  ChevronDown,
  Phone,
  RotateCcw,
  ShieldCheck,
} from "lucide-react";
import policyService from "@/services/policyService";
import type { PolicyResponse, PolicyTier } from "@/services/policyService";
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
 * Không có câu nào mô tả một quy trình mà mã không thực hiện. Mỗi câu tương ứng với một luật đang
 * chạy: phí tính trên giá trị đơn, tiền hoàn kẹp dưới ở 0, hãng hủy thì hoàn đủ, không chuyển
 * chuyến được sau hạn chốt, và sửa bảng phí không hồi tố lên đơn đã bán.
 *
 * ## Về bố cục
 *
 * Đây là trang văn bản điều khoản, không phải trang bán hàng: không ảnh nền, không màu mè. Thứ
 * làm nó đáng tin là **con số dễ đọc và dễ đối chiếu** — nên bảng phí là phần lớn nhất trên trang,
 * mỗi bậc một hàng, phần trăm đặt ở cột phải cố định để mắt dò dọc xuống được.
 */

/** Màu của một mức hoàn. Ba bậc thôi, vì nhiều hơn thì không còn phân biệt được bằng mắt. */
const mauMucHoan = (percent: number) => {
  if (percent >= 70) return { chu: "text-emerald-700", nen: "bg-emerald-500" };
  if (percent > 0) return { chu: "text-amber-700", nen: "bg-amber-500" };
  return { chu: "text-rose-700", nen: "bg-rose-400" };
};

/**
 * Một bậc phí.
 *
 * Dùng lưới thay vì thẻ `table`: trên điện thoại nó xuống dòng thành khối đọc được, còn từ màn
 * hình vừa trở lên thì các cột thẳng hàng đúng như một bảng. Một khối mã cho cả hai, không phải
 * hai bản song song rồi lệch nhau.
 */
const HangBacPhi = ({ bac }: { bac: PolicyTier }) => {
  const mau = mauMucHoan(bac.refund_percent);

  return (
    <div className="grid grid-cols-[1fr_auto] items-start gap-x-6 gap-y-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_120px] sm:px-7 sm:py-5">
      <div className="min-w-0">
        <p className="text-title-md text-ink">{bac.window}</p>
        {bac.note && <p className="text-body-sm text-muted mt-1">{bac.note}</p>}
      </div>

      <div className="text-right">
        <span className={`text-display-md tabular-nums ${mau.chu}`}>
          {bac.refund_percent}%
        </span>

        {/*
          Thanh tỷ lệ: đọc được mức hoàn mà không cần so từng con số với nhau.
          Chiều dài đúng bằng phần trăm, nên nó là dữ liệu chứ không phải trang trí.
        */}
        <span className="mt-1.5 block h-1 w-full overflow-hidden rounded-full bg-surface-strong">
          <span
            className={`block h-full rounded-full ${mau.nen}`}
            style={{ width: `${bac.refund_percent}%` }}
          />
        </span>
      </div>
    </div>
  );
};

/** Một ý chính, ba cái đặt cạnh nhau ngay dưới tiêu đề. */
const YChinh = ({
  icon,
  tren,
  duoi,
}: {
  icon: React.ReactNode;
  tren: string;
  duoi: string;
}) => (
  <div className="card-surface flex items-start gap-3 px-4 py-4">
    <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600">
      {icon}
    </span>
    <div className="min-w-0">
      <p className="text-title-md text-ink">{tren}</p>
      <p className="text-body-sm text-muted mt-0.5">{duoi}</p>
    </div>
  </div>
);

const CauHoi = ({
  hoi,
  moSan = false,
  children,
}: {
  hoi: string;
  moSan?: boolean;
  children: React.ReactNode;
}) => (
  <details open={moSan} className="group border-b border-hairline-soft last:border-b-0">
    <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-title-md text-ink transition-colors hover:bg-surface-soft sm:px-7">
      {hoi}
      <ChevronDown className="h-4 w-4 shrink-0 text-muted transition-transform group-open:rotate-180" />
    </summary>
    <div className="space-y-3 px-5 pb-5 text-body-sm text-body sm:px-7 [&_strong]:font-semibold [&_strong]:text-ink">
      {children}
    </div>
  </details>
);

/** Khung xám lúc chờ, đúng hình dạng nội dung sắp hiện — đỡ giật hơn một dòng chữ "Đang tải". */
const KhungCho = () => (
  <div className="animate-pulse space-y-6">
    <div className="grid gap-3 sm:grid-cols-3">
      {[0, 1, 2].map((i) => (
        <div key={i} className="h-20 rounded-xl bg-surface-strong" />
      ))}
    </div>
    <div className="h-72 rounded-xl bg-surface-strong" />
  </div>
);

export default function PolicyPage() {
  const [data, setData] = useState<PolicyResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    policyService
      .get()
      .then((res) => (res ? setData(res) : setError(true)))
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, []);

  // Mức hoàn cao nhất trong bảng, dùng cho ô ý chính. Lấy từ dữ liệu chứ không viết sẵn "90%".
  const hoanCaoNhat = data
    ? Math.max(...data.cancellation.rules.map((r) => r.refund_percent), 0)
    : 0;

  return (
    <div className="bg-background animate-fade-in">
      {/* --- Đầu trang --- */}
      <header className="border-b border-hairline-soft bg-canvas">
        <div className="mx-auto max-w-4xl px-4 py-12 sm:py-16">
          <span className="tag-upper bg-primary-50 text-primary-700">Điều khoản</span>

          <h1 className="text-display-xl text-ink mt-4 sm:text-[34px]">
            Chính sách hủy, đổi và hoàn tiền
          </h1>

          <p className="text-body-md text-body mt-3 max-w-2xl">
            Toàn bộ mức phí dưới đây là mức hệ thống thực sự áp khi bạn hủy hoặc đổi chuyến. Bảng
            này đọc thẳng từ hệ thống, không phải một bản chép tay có thể lệch.
          </p>

          {data?.cancellation.effective_from && (
            <p className="text-caption-sm text-muted mt-5 flex items-center gap-2">
              <CalendarClock className="h-4 w-4 shrink-0" />
              Áp dụng từ {data.cancellation.effective_from} · Đơn đặt trước thời điểm này giữ
              nguyên điều khoản cũ
            </p>
          )}
        </div>
      </header>

      <div className="mx-auto max-w-4xl px-4 py-10 sm:py-12">
        {loading && <KhungCho />}

        {error && (
          <div className="card-surface border-rose-200 bg-rose-50 px-5 py-4">
            <p className="text-body-sm text-rose-800">
              Không tải được chính sách. Vui lòng thử lại, hoặc gọi tổng đài{" "}
              <strong className="font-semibold">1900 1234</strong> để được đọc trực tiếp.
            </p>
          </div>
        )}

        {data && (
          <div className="space-y-10">
            {/* --- Ba ý chính --- */}
            <section className="grid gap-3 sm:grid-cols-3">
              <YChinh
                icon={<RotateCcw className="h-4.5 w-4.5" />}
                tren={`Hoàn tới ${hoanCaoNhat}%`}
                duoi="Khi bạn hủy sớm, theo bảng bên dưới"
              />
              <YChinh
                icon={<ShieldCheck className="h-4.5 w-4.5" />}
                tren="Hoàn 100%"
                duoi="Nếu công ty là bên hủy chuyến"
              />
              <YChinh
                icon={<ArrowLeftRight className="h-4.5 w-4.5" />}
                tren={`Đổi chuyến miễn phí ${data.transfer.free_transfers} lần`}
                duoi={`Báo trước ít nhất ${data.transfer.notice_days} ngày`}
              />
            </section>

            {/* --- Bảng phí hủy --- */}
            <section>
              <div className="card-surface overflow-hidden">
                <div className="border-b border-hairline-soft px-5 py-5 sm:px-7">
                  <h2 className="text-display-sm text-ink">{data.cancellation.name}</h2>
                  {data.cancellation.description && (
                    <p className="text-body-sm text-muted mt-2 max-w-2xl">
                      {data.cancellation.description}
                    </p>
                  )}
                </div>

                <div className="hidden grid-cols-[minmax(0,1fr)_120px] gap-x-6 border-b border-hairline-soft bg-surface-soft px-7 py-2.5 sm:grid">
                  <span className="text-caption-sm text-muted uppercase tracking-wide">
                    Hủy trước ngày khởi hành
                  </span>
                  <span className="text-caption-sm text-muted text-right uppercase tracking-wide">
                    Được hoàn
                  </span>
                </div>

                <div className="divide-y divide-hairline-soft">
                  {data.cancellation.rules.map((bac, i) => (
                    <HangBacPhi key={i} bac={bac} />
                  ))}
                </div>
              </div>

              <p className="text-caption-sm text-muted mt-3 flex items-start gap-2 px-1">
                <BadgeCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary-500" />
                Mức hoàn tính theo số ngày còn lại tới giờ khởi hành. Trước khi xác nhận hủy, hệ
                thống hiện sẵn số tiền bạn nhận lại — bạn thấy con số rồi mới quyết.
              </p>
            </section>

            {/* --- Hỏi đáp --- */}
            <section>
              <h2 className="text-display-sm text-ink mb-4 px-1">Câu hỏi thường gặp</h2>

              <div className="card-surface overflow-hidden">
                <CauHoi hoi="Tôi được hoàn bao nhiêu tiền khi hủy?" moSan>
                  <p>
                    Phần trăm lấy theo bảng phía trên, tính theo số ngày còn lại tới giờ khởi hành.
                  </p>
                  <p>
                    Có một chi tiết dễ nhầm: <strong>phí hủy tính trên tổng giá trị đơn</strong>,
                    còn tiền hoàn thì trừ trên <strong>số bạn đã thực trả</strong>. Nếu bạn mới đặt
                    cọc một phần mà hủy sát ngày đi, phần cọc ấy có thể mất hết. Đổi lại, tiền hoàn
                    không bao giờ âm — hủy tour thì bạn không phải nộp thêm đồng nào, kể cả khi phí
                    hủy lớn hơn số đã trả.
                  </p>
                </CauHoi>

                <CauHoi hoi="Công ty hủy chuyến thì tôi có mất phí không?">
                  <p>
                    Không. Chuyến bị hủy vì phía công ty — thời tiết, không đủ số khách tối thiểu,
                    sự cố nhà cung cấp — thì bạn được <strong>hoàn 100% số đã trả</strong>, không
                    trừ bất kỳ khoản nào, bất kể còn mấy ngày tới ngày đi.
                  </p>
                  <p>Bảng phí phía trên chỉ áp khi chính bạn là người hủy.</p>
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
                    được trả tiền cho khách sạn và nhà xe, không rút lại được — nên nó không chuyển
                    đi đâu được nữa.
                  </p>
                  <p>
                    Nếu sau mốc đó bạn không đi được, đây là trường hợp hủy đơn chứ không phải đổi
                    chuyến, và áp bảng phí phía trên.
                  </p>
                </CauHoi>

                <CauHoi hoi="Đặt xong bao lâu thì phải thanh toán?">
                  <p>
                    Đơn giữ chỗ trong{" "}
                    <strong>{data.booking.payment_ttl_minutes} phút</strong>. Quá thời gian đó mà
                    chưa thanh toán, hệ thống tự hủy đơn và trả chỗ lại cho khách khác. Bạn vẫn đặt
                    lại được nếu chuyến còn chỗ.
                  </p>
                </CauHoi>

                <CauHoi hoi="Công ty sửa bảng phí thì đơn tôi đã đặt có bị ảnh hưởng không?">
                  <p>
                    Không. Đơn của bạn{" "}
                    <strong>giữ nguyên bảng phí tại thời điểm bạn đặt</strong> — hệ thống chép điều
                    khoản vào chính đơn đó lúc đặt, chứ không đọc lại bảng hiện hành khi bạn hủy.
                  </p>
                  <p>Bảng phí mới chỉ áp cho đơn đặt từ thời điểm nó có hiệu lực trở đi.</p>
                </CauHoi>

                <CauHoi hoi="Đi dọc đường phát sinh chi phí thì ai trả?">
                  <p>
                    Những gì tour đã bao gồm — di chuyển từ điểm A tới điểm B, chỗ ở, các bữa ăn ghi
                    trong chương trình — thì công ty lo, kể cả khi phải đổi phương án vì mưa bão hay
                    xe hỏng. Đó là thứ công ty đã bán cho bạn.
                  </p>
                  <p>
                    Chi tiêu cá nhân ngoài chương trình — đồ uống thêm, mua sắm, dịch vụ bạn tự chọn
                    thêm — thì bạn tự chi trả. Mọi khoản phát sinh cần bạn trả đều phải được thông
                    báo và có sự đồng ý của bạn trước khi thu.
                  </p>
                </CauHoi>

                <CauHoi hoi="Tôi hủy đơn ở đâu?">
                  <p>
                    Vào{" "}
                    <Link
                      to="/booking-lookup"
                      className="font-semibold text-primary-600 hover:underline"
                    >
                      Tra cứu đơn
                    </Link>{" "}
                    bằng mã đơn, hoặc mở mục Đơn của tôi nếu bạn có tài khoản. Trước khi xác nhận,
                    hệ thống hiện sẵn số tiền bạn sẽ được hoàn theo đúng bảng trên.
                  </p>
                </CauHoi>
              </div>
            </section>

            {/* --- Còn thắc mắc --- */}
            <section className="card-surface flex flex-col gap-4 bg-primary-50/50 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-7">
              <div>
                <p className="text-title-md text-ink">Còn điều gì chưa rõ?</p>
                <p className="text-body-sm text-muted mt-1">
                  Tổng đài 8:00 – 21:00 hằng ngày, hoặc xem thêm{" "}
                  <Link to="/terms" className="font-semibold text-primary-600 hover:underline">
                    Điều khoản sử dụng
                  </Link>
                  .
                </p>
              </div>

              <a
                href="tel:19001234"
                className="btn-primary shrink-0 whitespace-nowrap"
              >
                <Phone className="h-4 w-4" />
                1900 1234
              </a>
            </section>
          </div>
        )}
      </div>
    </div>
  );
}
