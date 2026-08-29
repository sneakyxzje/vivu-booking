import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { ChevronDown } from "lucide-react";
import policyService from "@/services/policyService";
import type { PolicyResponse } from "@/services/policyService";
import { formatPrice } from "@/utils/format";
import { useDocumentMeta } from "@/hooks/useDocumentMeta";

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
 * Toàn bộ nội dung trải đúng bề rộng thanh điều hướng, không thụt vào ở đâu cả.
 *
 * ## Vì sao mười hai mục, và vì sao theo thứ tự này
 *
 * Đây là khuôn chung của văn bản điều khoản lữ hành: căn cứ pháp lý, giải thích từ ngữ, quy định
 * từng trường hợp, bất khả kháng, thời hạn hoàn tiền, trách nhiệm hai bên, giải quyết tranh chấp,
 * hiệu lực. Khuôn ấy không phải trang trí - mỗi mục trả lời một câu mà người đọc kỹ sẽ hỏi, và
 * thiếu mục nào thì đó là câu bỏ ngỏ.
 *
 * Nội dung thì viết theo đúng thứ hệ thống này làm, không chép của ai. Chép chính sách của công ty
 * khác là chép mô tả **hệ thống của họ**: trang sẽ hứa những luật mã ở đây không thi hành, và chỗ
 * lệch ấy chỉ lộ ra khi có người thật đòi quyền lợi.
 *
 * Mục 1 định nghĩa "hạn chốt danh sách", "số khách tối thiểu", "bất khả kháng" đúng như enum và
 * cột trong cơ sở dữ liệu; mục 4 nói công ty không đơn phương chuyển chuyến vì `BookingTransfer`
 * bắt buộc phải có bản ghi khách đồng ý; mục 5 miễn phí đổi lịch cho bất khả kháng vì
 * `TransferReasonCategory::batKhaKhang()` đang làm đúng thế; mục 12 nói việc sửa chính sách không
 * hồi tố vì đơn chép `cancellation_policy_id` vào chính nó lúc đặt.
 */

/**
 * Một câu hỏi, gập lại được.
 *
 * Dùng thẻ `details` của trình duyệt chứ không tự dựng bằng `useState`: nó gập mở sẵn không cần
 * JavaScript, đọc được bằng bàn phím và trình đọc màn hình, và Ctrl+F của trình duyệt vẫn tìm thấy
 * chữ bên trong rồi tự bung ra. Dựng tay thì phải làm lại cả bốn thứ đó.
 */
const CauHoi = ({
  hoi,
  children,
}: {
  hoi: string;
  children: React.ReactNode;
}) => (
  <details className="group border-b border-hairline-soft">
    <summary className="text-title-md text-ink flex cursor-pointer list-none items-center justify-between gap-6 py-4 transition-colors hover:text-primary-600">
      {hoi}
      <ChevronDown className="text-muted h-4.5 w-4.5 shrink-0 transition-transform duration-200 group-open:rotate-180" />
    </summary>
    <div className="text-body-md text-body space-y-2.5 pb-5 [&_strong]:font-semibold [&_strong]:text-ink">
      {children}
    </div>
  </details>
);

export default function PolicyPage() {
  useDocumentMeta({
    title: "Điều khoản, chính sách hủy và bảo mật",
    description:
      "Điều kiện đặt tour, bảng phí hủy theo mốc thời gian, mức hoàn tiền và cách chúng tôi xử lý thông tin của bạn.",
  });

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

        <p className="text-body-md text-body mt-3">
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

            {/*
              Căn cứ pháp lý.

              Không phải để trang trí: chính sách hủy tour là quan hệ dân sự có luật chuyên ngành
              điều chỉnh, và một văn bản điều khoản không dẫn căn cứ thì đọc như nội quy tự đặt.
            */}
            <p className="text-body-sm text-muted mt-6">
              Văn bản này được xây dựng trên cơ sở Luật Du lịch số 09/2017/QH14, Nghị định
              168/2017/NĐ-CP quy định chi tiết một số điều của Luật Du lịch, và Bộ luật Dân sự số
              91/2015/QH13.
            </p>

            {/* --- 1. Giải thích từ ngữ --- */}
            <h2 className="text-display-sm text-ink mt-12">1. Giải thích từ ngữ</h2>

            <p className="text-body-md text-body mt-3">
              Trong toàn bộ văn bản này, các từ ngữ dưới đây được hiểu như sau:
            </p>

            <dl className="text-body-md mt-3 space-y-3">
              <div>
                <dt className="text-ink font-semibold">Chuyến khởi hành</dt>
                <dd className="text-body">
                  Một lần tổ chức cụ thể của một chương trình tour, có ngày giờ khởi hành, số chỗ
                  tối đa và số khách tối thiểu riêng. Cùng một tour có thể có nhiều chuyến khởi hành
                  khác nhau.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Hạn chốt danh sách</dt>
                <dd className="text-body">
                  Thời điểm công ty ngừng nhận đặt chỗ mới cho một chuyến và chốt số lượng với nhà
                  cung cấp, mặc định {data.booking.deadline_days} ngày trước giờ khởi hành. Sau mốc
                  này, chỗ của khách đã được thanh toán cho khách sạn và nhà vận chuyển.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Số khách tối thiểu</dt>
                <dd className="text-body">
                  Số khách thấp nhất để một chuyến đủ điều kiện khởi hành. Chuyến không đạt số này
                  tới hạn chốt danh sách sẽ bị hủy theo mục 3.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Sự kiện bất khả kháng</dt>
                <dd className="text-body">
                  Sự kiện xảy ra một cách khách quan, không thể lường trước và không thể khắc phục
                  dù đã áp dụng mọi biện pháp cần thiết trong khả năng cho phép: thiên tai, thời
                  tiết cực đoan, dịch bệnh, quyết định của cơ quan nhà nước có thẩm quyền, hoặc việc
                  nhà cung cấp dịch vụ không thực hiện được nghĩa vụ vì các lý do nêu trên.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Ngày làm việc</dt>
                <dd className="text-body">
                  Các ngày từ thứ Hai đến thứ Sáu, không tính ngày nghỉ lễ, Tết theo quy định của
                  pháp luật lao động.
                </dd>
              </div>
            </dl>

            {/* --- 2. Bảng phí hủy --- */}
            <h2 className="text-display-sm text-ink mt-14">
              2. Mức hoàn khi khách hàng hủy đơn
            </h2>

            <p className="text-body-md text-body mt-3">
              {data.cancellation.description
                ?? "Phí hủy tăng dần khi càng sát ngày khởi hành, vì chi phí đã cam kết với nhà cung cấp càng khó hủy."}
            </p>

            <div className="mt-5 overflow-x-auto">
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

            <p className="text-body-md text-body mt-5">
              Mức hoàn tính theo số ngày còn lại tới giờ khởi hành. Phí hủy tính trên tổng giá trị
              đơn; số tiền hoàn được trừ trên số khách hàng đã thực trả và không bao giờ nhỏ hơn 0
              — khách hàng không phải nộp thêm khi hủy. Trước khi xác nhận hủy, hệ thống hiện sẵn
              số tiền nhận lại để khách hàng đối chiếu.
            </p>

            {/* --- 3. Công ty hủy chuyến --- */}
            <h2 className="text-display-sm text-ink mt-14">
              3. Trường hợp công ty hủy chuyến
            </h2>

            <p className="text-body-md text-body mt-3">
              Công ty có thể hủy một chuyến khởi hành khi số khách không đạt mức tối thiểu tới hạn
              chốt danh sách, khi xảy ra sự kiện bất khả kháng, hoặc khi nhà cung cấp dịch vụ không
              thực hiện được nghĩa vụ. Trong mọi trường hợp công ty là bên hủy:
            </p>

            <ul className="text-body-md text-body mt-3 list-disc space-y-2 pl-5 marker:text-muted-soft">
              <li>
                Khách hàng được <strong className="font-semibold text-ink">hoàn 100%</strong> số
                tiền đã thanh toán, không trừ bất kỳ khoản phí nào, không phụ thuộc vào thời điểm
                hủy. Bảng phí ở mục 2 không áp dụng.
              </li>
              <li>
                Khách hàng được quyền chọn giữa nhận lại tiền hoặc chuyển sang một chuyến khởi hành
                khác. Nếu chọn chuyển và chuyến mới có giá cao hơn, công ty chịu phần chênh lệch.
              </li>
              <li>
                Công ty thông báo bằng email tới địa chỉ khách hàng đã đăng ký ngay khi có quyết
                định hủy, nêu rõ lý do và phương án xử lý.
              </li>
            </ul>

            {/* --- 4. Chuyển chuyến --- */}
            <h2 className="text-display-sm text-ink mt-14">4. Chuyển sang chuyến khác</h2>

            <ul className="text-body-md text-body mt-3 list-disc space-y-2 pl-5 marker:text-muted-soft">
              <li>
                Khách hàng đề nghị đổi chuyến cần báo trước giờ khởi hành ít nhất{" "}
                <strong className="font-semibold text-ink">
                  {data.transfer.notice_days} ngày
                </strong>
                . {data.transfer.free_transfers} lần đổi đầu tiên miễn phí; từ lần thứ{" "}
                {data.transfer.free_transfers + 1} trở đi thu phí đổi lịch{" "}
                <strong className="font-semibold text-ink">
                  {formatPrice(data.transfer.fee)}
                </strong>
                .
              </li>
              <li>
                Chênh lệch giá giữa hai chuyến được thu thêm hoặc hoàn lại tương ứng.
              </li>
              <li>
                Sau hạn chốt danh sách, đơn không còn chuyển được sang chuyến khác, vì chỗ ở chuyến
                cũ đã thanh toán cho nhà cung cấp. Trường hợp này xử lý theo mục 2.
              </li>
              <li>
                Công ty <strong className="font-semibold text-ink">không đơn phương</strong> chuyển
                khách sang chuyến khác. Mọi lần chuyển đều phải được trao đổi và có sự đồng ý của
                khách hàng; nội dung trao đổi được ghi lại kèm thời điểm.
              </li>
            </ul>

            {/* --- 5. Bất khả kháng --- */}
            <h2 className="text-display-sm text-ink mt-14">5. Sự kiện bất khả kháng</h2>

            <p className="text-body-md text-body mt-3">
              Khi xảy ra sự kiện bất khả kháng như định nghĩa tại mục 1, hai bên không phải chịu
              trách nhiệm về việc không thực hiện được nghĩa vụ do sự kiện đó gây ra. Công ty sẽ
              thông báo cho khách hàng trong thời gian sớm nhất và đưa ra phương án thay thế: đổi
              lịch trình, chuyển sang chuyến khởi hành khác, hoặc hủy chuyến và hoàn tiền theo mục
              3.
            </p>

            <p className="text-body-md text-body mt-3">
              Khách hàng phải dời chuyến vì sự kiện bất khả kháng{" "}
              <strong className="font-semibold text-ink">không chịu phí đổi lịch</strong>, kể cả
              khi đây không phải lần đổi đầu tiên.
            </p>

            {/* --- 6. Thời hạn và cách thức hoàn tiền --- */}
            <h2 className="text-display-sm text-ink mt-14">
              6. Thời hạn và cách thức hoàn tiền
            </h2>

            <ul className="text-body-md text-body mt-3 list-disc space-y-2 pl-5 marker:text-muted-soft">
              <li>
                Bộ phận chăm sóc khách hàng liên hệ trong vòng{" "}
                <strong className="font-semibold text-ink">3 ngày làm việc</strong> kể từ khi đơn
                được hủy, để xác nhận số tiền hoàn và thông tin tài khoản nhận tiền.
              </li>
              <li>
                Tiền được hoàn về đúng tài khoản đã dùng để thanh toán. Trường hợp thanh toán qua
                cổng VNPay, thời gian tiền về phụ thuộc thêm vào ngân hàng phát hành thẻ.
              </li>
              <li>
                Mọi khoản thu và hoàn của một đơn đều được ghi vào sổ giao dịch của đơn đó; khách
                hàng xem lại được khi tra cứu đơn.
              </li>
            </ul>

            {/* --- 7. Trách nhiệm của hai bên --- */}
            <h2 className="text-display-sm text-ink mt-14">7. Trách nhiệm của hai bên</h2>

            <p className="text-title-md text-ink mt-4">Công ty có trách nhiệm</p>

            <ul className="text-body-md text-body mt-2 list-disc space-y-2 pl-5 marker:text-muted-soft">
              <li>
                Tổ chức chuyến đi đúng chương trình đã bán: phương tiện di chuyển, lưu trú, các bữa
                ăn và điểm tham quan ghi trong lịch trình.
              </li>
              <li>
                Bố trí phương án thay thế tương đương khi một hạng mục trong chương trình không
                thực hiện được, và chịu chi phí phát sinh của phương án đó.
              </li>
              <li>
                Thông báo kịp thời mọi thay đổi ảnh hưởng tới chuyến đi, và không thu bất kỳ khoản
                phát sinh nào của khách hàng khi chưa có sự đồng ý của họ.
              </li>
            </ul>

            <p className="text-title-md text-ink mt-5">Khách hàng có trách nhiệm</p>

            <ul className="text-body-md text-body mt-2 list-disc space-y-2 pl-5 marker:text-muted-soft">
              <li>
                Cung cấp thông tin chính xác khi đặt tour và khai danh sách hành khách: họ tên, giấy
                tờ tùy thân, số điện thoại, email.
              </li>
              <li>
                Có mặt đúng giờ tại điểm tập kết. Khách không có mặt lúc khởi hành được xử lý như
                trường hợp hủy sát ngày theo mục 2.
              </li>
              <li>
                Tự chi trả các khoản chi tiêu cá nhân ngoài chương trình, và tuân thủ hướng dẫn của
                hướng dẫn viên về an toàn trong suốt hành trình.
              </li>
            </ul>

            {/* --- 8. Hỏi đáp --- */}
            <h2 className="text-display-sm text-ink mt-14">8. Câu hỏi thường gặp</h2>

            <div className="mt-1">
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
              9. Điều khoản sử dụng
            </h2>

            <ul className="text-body-md text-body mt-3 list-disc space-y-2.5 pl-5 marker:text-muted-soft">
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
              10. Bảo mật thông tin khách hàng
            </h2>

            <ul className="text-body-md text-body mt-3 list-disc space-y-2.5 pl-5 marker:text-muted-soft">
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

            {/* --- 11. Giải quyết tranh chấp --- */}
            <h2 className="text-display-sm text-ink mt-14">11. Giải quyết tranh chấp</h2>

            <p className="text-body-md text-body mt-3">
              Mọi vướng mắc phát sinh trong quá trình thực hiện được hai bên ưu tiên giải quyết bằng
              thương lượng trên tinh thần thiện chí. Khách hàng gửi phản ánh qua tổng đài hoặc email
              hỗ trợ; công ty phản hồi trong vòng 3 ngày làm việc.
            </p>

            <p className="text-body-md text-body mt-3">
              Trường hợp thương lượng không đạt kết quả, tranh chấp được đưa ra Tòa án nhân dân có
              thẩm quyền tại nơi công ty đặt trụ sở chính, theo quy định của pháp luật Việt Nam.
            </p>

            {/* --- 12. Hiệu lực --- */}
            <h2 className="text-display-sm text-ink mt-14">12. Hiệu lực thi hành</h2>

            <p className="text-body-md text-body mt-3">
              {data.cancellation.effective_from ? (
                <>
                  Bảng phí hủy tại mục 2 có hiệu lực từ{" "}
                  <strong className="font-semibold text-ink">
                    {data.cancellation.effective_from}
                  </strong>
                  .{" "}
                </>
              ) : null}
              Đơn đặt tour phát sinh trước thời điểm này tiếp tục áp dụng bảng phí tại thời điểm
              đặt — hệ thống lưu điều khoản vào từng đơn ngay khi khách hàng đặt, nên việc công ty
              cập nhật chính sách không làm thay đổi thỏa thuận đã ký kết.
            </p>

            <p className="text-body-md text-body mt-3">
              Công ty có quyền sửa đổi văn bản này và sẽ công bố phiên bản mới kèm thời điểm bắt đầu
              áp dụng ngay trên trang này.
            </p>

            {/* --- Liên hệ --- */}
            <h2 className="text-display-sm text-ink mt-14">Còn điều gì chưa rõ</h2>

            <p className="text-body-md text-body mt-3">
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
