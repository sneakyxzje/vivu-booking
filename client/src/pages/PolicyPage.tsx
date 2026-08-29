import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
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
 * Không có câu nào mô tả một quy trình mà mã không thực hiện. Mỗi câu tương ứng với một luật đang
 * chạy: phí tính trên giá trị đơn, tiền hoàn kẹp dưới ở 0, hãng hủy thì hoàn đủ, không chuyển
 * chuyến được sau hạn chốt, và sửa bảng phí không hồi tố lên đơn đã bán.
 *
 * ## Về bố cục: đây là văn bản, không phải bảng điều khiển
 *
 * Một trang điều khoản đọc từ trên xuống. Cắt nó thành các thẻ có viền là chia một mạch văn thành
 * những ô rời rạc, và mắt phải nhảy giữa các ô thay vì trôi theo dòng.
 *
 * Bề rộng khớp thanh điều hướng để trang không thụt vào so với phần còn lại của website; riêng
 * phần chữ giữ `max-w-3xl` vì dòng dài quá 80 ký tự thì mắt lạc dòng khi xuống hàng. Bảng phí
 * được trải rộng hơn, vì nó là dữ liệu chứ không phải văn xuôi.
 */

const CauHoi = ({
  hoi,
  children,
}: {
  hoi: string;
  children: React.ReactNode;
}) => (
  <div className="mt-7">
    <h3 className="text-title-md text-ink">{hoi}</h3>
    <div className="mt-2 space-y-2.5 text-body-md text-body [&_strong]:font-semibold [&_strong]:text-ink">
      {children}
    </div>
  </div>
);

export default function PolicyPage() {
  const [data, setData] = useState<PolicyResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const { hash } = useLocation();

  useEffect(() => {
    policyService
      .get()
      .then((res) => (res ? setData(res) : setError(true)))
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, []);

  /*
   * Cuộn tới mục được trỏ bằng neo `#`.
   *
   * Trình duyệt tự làm việc này khi tải trang thật, nhưng điều hướng phía client thì không: React
   * đổi nội dung mà không nạp lại trang, nên neo bị bỏ qua. Thiếu chỗ này thì `/terms` chuyển
   * hướng sang đây rồi dừng ở đầu trang, và người bấm "Điều khoản sử dụng" nhìn thấy bảng phí hủy.
   *
   * Chờ `data` vì các mục chỉ tồn tại sau khi tải xong - cuộn trước đó thì không có gì để cuộn tới.
   */
  useEffect(() => {
    if (!data || !hash) return;

    document.getElementById(hash.slice(1))?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }, [data, hash]);

  return (
    <div className="bg-canvas animate-fade-in">
      {/* Cùng bề rộng và cùng lề với thanh điều hướng, để trang không lệch khỏi phần còn lại. */}
      <div className="mx-auto max-w-[1440px] px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <span className="tag-upper bg-primary-50 text-primary-700">Điều khoản</span>

        <h1 className="text-display-xl text-ink mt-4 sm:text-[32px]">
          Chính sách &amp; Điều khoản
        </h1>

        <p className="text-body-md text-body mt-3 max-w-3xl">
          Mức hoàn tiền, quy định đổi chuyến, điều khoản sử dụng và cách chúng tôi giữ dữ liệu của
          bạn — gộp trong một trang. Bảng phí ở mục 1 đọc thẳng từ hệ thống, là mức thực sự áp khi
          bạn hủy, không phải một bản chép tay có thể lệch.
        </p>

        {loading && (
          <p className="text-body-sm text-muted mt-10">Đang tải chính sách...</p>
        )}

        {error && (
          <p className="text-body-sm mt-10 text-rose-700">
            Không tải được chính sách. Vui lòng thử lại, hoặc gọi tổng đài{" "}
            <strong className="font-semibold">1900 1234</strong> để được đọc trực tiếp.
          </p>
        )}

        {data && (
          <>
            {data.cancellation.effective_from && (
              <p className="text-caption-sm text-muted mt-6">
                Áp dụng từ {data.cancellation.effective_from}. Đơn đặt trước thời điểm này giữ
                nguyên điều khoản cũ.
              </p>
            )}

            {/* --- 1. Bảng phí hủy --- */}
            <h2 className="text-display-sm text-ink mt-12">
              1. Mức hoàn khi bạn hủy đơn
            </h2>

            <p className="text-body-md text-body mt-3 max-w-3xl">
              {data.cancellation.description
                ?? "Phí hủy tăng dần khi càng sát ngày khởi hành, vì chi phí đã cam kết với nhà cung cấp càng khó hủy."}
            </p>

            <div className="mt-5 max-w-4xl overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-hairline">
                    <th className="text-caption-sm text-muted py-2.5 pr-6 font-normal uppercase tracking-wide">
                      Hủy trước ngày khởi hành
                    </th>
                    <th className="text-caption-sm text-muted w-28 py-2.5 pr-6 font-normal uppercase tracking-wide">
                      Được hoàn
                    </th>
                    <th className="text-caption-sm text-muted py-2.5 font-normal uppercase tracking-wide">
                      Vì sao
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.cancellation.rules.map((bac, i) => (
                    <tr key={i} className="border-b border-hairline-soft last:border-b-0">
                      <td className="text-body-md text-ink py-3.5 pr-6 whitespace-nowrap">
                        {bac.window}
                      </td>
                      <td className="text-body-md text-ink py-3.5 pr-6 font-semibold tabular-nums">
                        {bac.refund_percent}%
                      </td>
                      <td className="text-body-sm text-muted py-3.5">{bac.note ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <p className="text-body-md text-body mt-5 max-w-3xl">
              Mức hoàn tính theo số ngày còn lại tới giờ khởi hành. Trước khi xác nhận hủy, hệ thống
              hiện sẵn số tiền bạn nhận lại — bạn thấy con số rồi mới quyết.
            </p>

            {/* --- 2. Hỏi đáp --- */}
            <h2 className="text-display-sm text-ink mt-14">2. Câu hỏi thường gặp</h2>

            <div className="max-w-3xl">
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
                  bất kỳ khoản nào, bất kể còn mấy ngày tới ngày đi. Bảng phí phía trên chỉ áp khi
                  chính bạn là người hủy.
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
                  hành khi bạn hủy. Bảng phí mới chỉ áp cho đơn đặt từ thời điểm nó có hiệu lực trở
                  đi.
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
                  Vào{" "}
                  <Link
                    to="/booking-lookup"
                    className="font-semibold text-primary-600 hover:underline"
                  >
                    Tra cứu đơn
                  </Link>{" "}
                  bằng mã đơn, hoặc mở mục Đơn của tôi nếu bạn có tài khoản. Trước khi xác nhận, hệ
                  thống hiện sẵn số tiền bạn sẽ được hoàn theo đúng bảng trên.
                </p>
              </CauHoi>
            </div>

            {/* --- 3. Điều khoản sử dụng --- */}
            <h2 id="dieu-khoan" className="text-display-sm text-ink mt-14 scroll-mt-24">
              3. Điều khoản sử dụng
            </h2>

            <ul className="text-body-md text-body mt-3 max-w-3xl list-disc space-y-2.5 pl-5 marker:text-muted-soft">
              <li>
                Bạn chịu trách nhiệm về tính chính xác của thông tin cung cấp khi đặt tour: họ tên,
                giấy tờ tùy thân, số điện thoại và email liên hệ. Thông tin sai có thể khiến bạn
                không lên được phương tiện hoặc không nhận được thông báo về chuyến đi.
              </li>
              <li>
                Đơn đặt tour chỉ được giữ chỗ trong{" "}
                <strong className="font-semibold text-ink">
                  {data.booking.payment_ttl_minutes} phút
                </strong>{" "}
                kể từ khi khởi tạo. Quá thời hạn mà chưa thanh toán, hệ thống tự hủy đơn và nhường
                chỗ cho khách khác.
              </li>
              <li>
                Đơn đã thanh toán là cam kết giữ chỗ chính thức giữa Vivu Booking và bạn, kèm theo
                bảng phí hủy tại thời điểm đặt như nêu ở mục 1.
              </li>
              <li>
                Vivu Booking có quyền từ chối hoặc hủy các đơn có dấu hiệu gian lận, kèm hoàn tiền
                theo quy định.
              </li>
              <li>
                Mỗi chuyến có số khách tối thiểu để khởi hành. Không đủ số lượng tới hạn chốt danh
                sách, công ty hủy chuyến và hoàn đủ tiền — xem mục 2.
              </li>
            </ul>

            {/* --- 4. Chính sách bảo mật --- */}
            <h2 id="bao-mat" className="text-display-sm text-ink mt-14 scroll-mt-24">
              4. Chính sách bảo mật
            </h2>

            <ul className="text-body-md text-body mt-3 max-w-3xl list-disc space-y-2.5 pl-5 marker:text-muted-soft">
              <li>
                Thông tin cá nhân của bạn chỉ được dùng để xử lý đơn đặt tour, làm bảo hiểm du lịch
                và chăm sóc khách hàng.
              </li>
              <li>
                Mật khẩu được mã hóa một chiều — kể cả nhân viên công ty cũng không đọc được. Giao
                dịch thanh toán xử lý qua cổng VNPay với chữ ký bảo mật; Vivu Booking không lưu
                thông tin thẻ của bạn.
              </li>
              <li>
                Chúng tôi không chia sẻ dữ liệu khách hàng cho bên thứ ba ngoài phạm vi phục vụ
                chuyến đi, tức đơn vị vận chuyển và lưu trú của chính chuyến bạn đặt.
              </li>
              <li>
                Mọi lần công ty liên hệ với bạn về một đơn — gọi điện, nhắn tin, gửi email — đều
                được ghi lại kèm thời điểm và nội dung, để cả hai bên đối chiếu khi cần.
              </li>
              <li>
                Bạn có thể yêu cầu chỉnh sửa hoặc xóa thông tin cá nhân qua email hỗ trợ bên dưới.
              </li>
            </ul>

            {/* --- 5. Liên hệ --- */}
            <h2 className="text-display-sm text-ink mt-14">5. Còn điều gì chưa rõ</h2>

            <p className="text-body-md text-body mt-3 max-w-3xl">
              Gọi tổng đài{" "}
              <a href="tel:19001234" className="font-semibold text-primary-600 hover:underline">
                1900 1234
              </a>{" "}
              trong khung 8:00 – 21:00 hằng ngày, hoặc gửi email tới{" "}
              <a
                href="mailto:info@vivubooking.vn"
                className="font-semibold text-primary-600 hover:underline"
              >
                info@vivubooking.vn
              </a>
              .
            </p>
          </>
        )}
      </div>
    </div>
  );
}
