import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { ChevronDown } from "lucide-react";
import policyService from "@/services/policyService";
import type { PolicyResponse } from "@/services/policyService";
import { formatPrice } from "@/utils/format";
import { useDocumentMeta } from "@/hooks/useDocumentMeta";

/**
 * Điều khoản thỏa thuận sử dụng dịch vụ — bản khách đọc.
 *
 * ## Vì sao trang này gọi API thay vì viết cứng
 *
 * Bảng phí hủy nằm trong cơ sở dữ liệu và điều hành sửa được. Chép nó thành chữ ở đây thì có hai
 * bản: bản khách đọc và bản hệ thống tính. Hai bản giống nhau đúng tới lần sửa đầu tiên, và từ đó
 * trang này hứa một đằng còn lúc hủy đơn trừ tiền một nẻo.
 *
 * Mấy con số khác cũng vậy — hạn báo trước, số lần đổi miễn phí, phí đổi lịch, thời gian giữ chỗ,
 * hạn chốt danh sách — đều là hằng số hoặc cấu hình có thật trong mã máy chủ, lấy về chứ không gõ lại.
 *
 * ## Khung văn bản mượn của ngành, nội dung thì không
 *
 * Bố cục hai phần và danh sách mục ở Phần I đi theo khuôn quen thuộc của điều khoản lữ hành nội
 * địa: giá, giá trẻ em, thanh toán, hủy và phí hủy, bất khả kháng, lưu trú, vận chuyển, hành lý,
 * bảo hiểm, sức khỏe, tranh chấp, hiệu lực. Khuôn ấy không phải trang trí — mỗi mục trả lời một
 * câu mà người đọc kỹ sẽ hỏi, và thiếu mục nào thì đó là câu bỏ ngỏ.
 *
 * Nhưng **từng câu bên trong viết theo đúng thứ hệ thống này làm**. Chép nội dung của công ty khác
 * là chép mô tả hệ thống của họ: trang sẽ hứa những luật mã ở đây không thi hành, và chỗ lệch ấy
 * chỉ lộ ra khi có người thật đòi quyền lợi. Vài chỗ dễ vấp nếu chép nguyên:
 *
 *   - Đặt cọc giữ chỗ. Tour ghép ở đây **không có** — trả đủ trong `payment_ttl_minutes` phút hoặc
 *     mất chỗ. Chỉ tour riêng mới chốt kèm cọc.
 *   - Giá trẻ em. Hệ thống chia **ba** hạng theo `adult_price` / `child_price` / `infant_price`,
 *     không phải bốn hạng với tỉ lệ 50% và 75% cố định.
 *   - Bảo hiểm. Công ty không bán gói bảo hiểm riêng và không cam kết mức đền bù nào; bảo hiểm chỉ
 *     có khi tour cụ thể liệt kê nó trong dịch vụ bao gồm.
 *   - Phụ thu phòng đơn. Không có bảng phụ thu trong hệ thống.
 *
 * ## Mục 7 nói thật về ghép chuyến
 *
 * Bản trước viết "công ty **không đơn phương** chuyển khách sang chuyến khác" — đúng với chuyển
 * chuyến (`BookingTransfer` bắt buộc có bản ghi khách đồng ý) nhưng **sai với ghép chuyến**, vốn
 * dồn khách của hai chuyến gần nhau mà không hỏi ai. Nay mục 7 tách hai việc, và kèm quyền từ chối
 * ngày mới để nhận hoàn 100%.
 *
 * Quyền ấy hiện thực hiện bằng tay: điều hành hủy đơn với `cancel_type = by_company`, nhánh hoàn
 * đủ đã có sẵn. Tự động hóa nó — mở một cửa cho khách bấm từ chối trong hạn — là việc còn lại.
 *
 * ## Về bố cục: đây là văn bản, không phải bảng điều khiển
 *
 * Một trang điều khoản đọc từ trên xuống. Cắt nó thành các thẻ có viền là chia một mạch văn thành
 * những ô rời rạc, và mắt phải nhảy giữa các ô thay vì trôi theo dòng.
 *
 * Hai neo `#dieu-khoan` và `#bao-mat` phải giữ nguyên: `/terms` và `/privacy` chuyển hướng vào
 * chúng, và hai đường dẫn ấy đã nằm trong thư gửi khách lẫn ô đồng ý ở trang đăng ký.
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

/** Tiêu đề mục, dùng chung cho cả hai phần để cỡ chữ và khoảng cách không lệch nhau. */
const Muc = ({
  id,
  children,
}: {
  id?: string;
  children: React.ReactNode;
}) => (
  <h2
    id={id}
    className={`text-display-sm text-ink mt-14${id ? " scroll-mt-24" : ""}`}
  >
    {children}
  </h2>
);

const Doan = ({ children }: { children: React.ReactNode }) => (
  <p className="text-body-md text-body mt-3">{children}</p>
);

const DanhSach = ({ children }: { children: React.ReactNode }) => (
  <ul className="text-body-md text-body mt-3 list-disc space-y-2 pl-5 marker:text-muted-soft">
    {children}
  </ul>
);

const Manh = ({ children }: { children: React.ReactNode }) => (
  <strong className="font-semibold text-ink">{children}</strong>
);

export default function PolicyPage() {
  useDocumentMeta({
    title: "Điều khoản, chính sách hủy và bảo mật",
    description:
      "Điều kiện đặt tour, bảng phí hủy theo mốc thời gian, mức hoàn tiền và cách chúng tôi xử lý dữ liệu cá nhân của bạn.",
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
        <span className="tag-upper bg-primary-50 text-primary-700">
          Điều khoản
        </span>

        <h1 className="text-display-xl text-ink mt-4 sm:text-[32px]">
          Điều khoản thỏa thuận sử dụng dịch vụ du lịch nội địa
        </h1>

        <p className="text-body-md text-body mt-3">
          Quý khách vui lòng đọc các điều khoản dưới đây trước khi đăng ký và sử
          dụng dịch vụ do Vivu Booking tổ chức. Việc tiếp tục sử dụng trang web
          này xác nhận Quý khách đã chấp thuận và tuân thủ những điều khoản đó.
        </p>

        <p className="text-body-md text-body mt-3">
          Nội dung gồm hai phần: <Manh>Phần I</Manh> — điều kiện bán các chương
          trình du lịch nội địa; <Manh>Phần II</Manh> — chính sách bảo vệ dữ liệu
          cá nhân.
        </p>

        {loading && (
          <p className="text-body-sm text-muted mt-10">
            Đang tải chính sách...
          </p>
        )}

        {error && (
          <p className="text-body-sm mt-10 text-rose-700">
            Không tải được chính sách. Vui lòng thử lại, hoặc gọi tổng đài{" "}
            <Manh>1900 1234</Manh> để được đọc trực tiếp.
          </p>
        )}

        {data && (
          <>
            <p className="text-body-sm text-muted mt-6">
              Văn bản này được xây dựng trên cơ sở Luật Du lịch số 09/2017/QH14,
              Nghị định 168/2017/NĐ-CP quy định chi tiết một số điều của Luật Du
              lịch, Bộ luật Dân sự số 91/2015/QH13 và Nghị định 13/2023/NĐ-CP về
              bảo vệ dữ liệu cá nhân.
            </p>

            <h2 className="text-display-sm text-ink mt-14 border-t border-hairline pt-8">
              PHẦN I — ĐIỀU KIỆN BÁN CÁC CHƯƠNG TRÌNH DU LỊCH NỘI ĐỊA
            </h2>

            {/* --- 1. Giải thích từ ngữ --- */}
            <Muc>1. Giải thích từ ngữ</Muc>

            <Doan>
              Trong toàn bộ văn bản này, các từ ngữ dưới đây được hiểu như sau:
            </Doan>

            <dl className="text-body-md mt-3 space-y-3">
              <div>
                <dt className="text-ink font-semibold">Chuyến khởi hành</dt>
                <dd className="text-body">
                  Một lần tổ chức cụ thể của một chương trình tour, có ngày giờ
                  khởi hành, ngày giờ kết thúc, số chỗ tối đa và số khách tối
                  thiểu riêng. Cùng một tour có thể có nhiều chuyến khởi hành
                  khác nhau.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Đơn hàng</dt>
                <dd className="text-body">
                  Một lần đăng ký cho <Manh>một nhóm khách đi cùng nhau</Manh>,
                  do một người đại diện thực hiện. Một chuyến khởi hành thường
                  gồm nhiều đơn hàng của những nhóm khách không quen nhau.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Hạn chốt danh sách</dt>
                <dd className="text-body">
                  Thời điểm công ty ngừng nhận đăng ký mới cho một chuyến khởi
                  hành và gửi danh sách khách cho các nhà cung cấp dịch vụ. Mặc
                  định là {data.booking.deadline_days} ngày trước giờ khởi hành,
                  từng chuyến có thể đặt mốc riêng và mốc đó hiển thị trên trang
                  chi tiết tour.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Số khách tối thiểu</dt>
                <dd className="text-body">
                  Số khách thấp nhất để một chuyến đủ điều kiện khởi hành. Chuyến
                  không đạt số này tới hạn chốt danh sách sẽ được ghép hoặc hủy
                  theo mục 6 và mục 7.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Sự kiện bất khả kháng</dt>
                <dd className="text-body">
                  Sự kiện xảy ra một cách khách quan, không thể lường trước và
                  không thể khắc phục dù đã áp dụng mọi biện pháp cần thiết trong
                  khả năng cho phép: thiên tai, thời tiết cực đoan, dịch bệnh,
                  hỏa hoạn, tai nạn, quyết định của cơ quan nhà nước có thẩm
                  quyền, hoặc việc nhà cung cấp dịch vụ không thực hiện được
                  nghĩa vụ vì các lý do nêu trên.
                </dd>
              </div>
              <div>
                <dt className="text-ink font-semibold">Ngày làm việc</dt>
                <dd className="text-body">
                  Các ngày từ thứ Hai đến thứ Sáu, không tính ngày nghỉ lễ, Tết
                  theo quy định của pháp luật lao động.
                </dd>
              </div>
            </dl>

            {/* --- 2. Giá tour --- */}
            <Muc>2. Giá chương trình du lịch</Muc>

            <Doan>
              <Manh>2.1.</Manh> Giá được niêm yết bằng Đồng Việt Nam (VNĐ) và đã
              bao gồm thuế theo quy định. Công ty không nhận thanh toán bằng
              ngoại tệ.
            </Doan>

            <Doan>
              <Manh>2.2.</Manh> Giá chỉ bao gồm những khoản được liệt kê rõ ràng
              trong mục <Manh>dịch vụ bao gồm</Manh> của từng chương trình tour.
              Công ty không có nghĩa vụ thanh toán bất kỳ chi phí nào không nằm
              trong danh mục đó.
            </Doan>

            <Doan>
              <Manh>2.3.</Manh> Đơn giá theo từng hạng khách được{" "}
              <Manh>sao chép vào đơn hàng tại thời điểm đặt</Manh>. Công ty điều
              chỉnh bảng giá về sau không làm thay đổi số tiền của đơn đã đặt, và
              hợp đồng in ra sau đó vẫn hiện đúng đơn giá khách hàng đã đồng ý.
            </Doan>

            <Doan>
              <Manh>2.4.</Manh> Các khoản chi tiêu cá nhân ngoài chương trình —
              đồ uống, giặt là, điện thoại, tham quan tự chọn — do khách hàng tự
              thanh toán trực tiếp với nhà cung cấp.
            </Doan>

            {/* --- 3. Giá trẻ em --- */}
            <Muc>3. Giá dành cho trẻ em</Muc>

            <Doan>
              Độ tuổi tính theo ngày sinh khai trong danh sách hành khách, đối
              chiếu với ngày khởi hành:
            </Doan>

            <DanhSach>
              <li>
                <Manh>Em bé dưới 2 tuổi:</Manh> áp dụng mức giá em bé của từng
                chương trình, thường là miễn phí dịch vụ. Em bé{" "}
                <Manh>không chiếm một chỗ ngồi riêng</Manh> trên phương tiện và
                ngủ chung với bố mẹ; vì vậy không được tính vào số chỗ của
                chuyến. Cha mẹ tự lo ăn uống và các chi phí phát sinh cho bé.
              </li>
              <li>
                <Manh>Trẻ em từ 2 đến dưới 12 tuổi:</Manh> áp dụng mức giá trẻ
                em của từng chương trình, chiếm một chỗ ngồi, không có chế độ
                giường riêng và ngủ chung phòng với người lớn đi kèm.
              </li>
              <li>
                <Manh>Từ 12 tuổi trở lên:</Manh> tính theo giá người lớn.
              </li>
            </DanhSach>

            <Doan>
              Mức giá cụ thể của ba hạng khách hiển thị trên trang chi tiết của
              từng tour và trên biểu mẫu đặt tour trước khi khách hàng xác nhận.
              Công ty không áp dụng một tỉ lệ phần trăm cố định cho mọi chương
              trình.
            </Doan>

            {/* --- 4. Đăng ký và thanh toán --- */}
            <Muc>4. Đăng ký và thanh toán</Muc>

            <Doan>
              <Manh>4.1.</Manh> Khách hàng đăng ký trực tiếp trên trang web,
              không bắt buộc phải tạo tài khoản. Sau khi đặt, hệ thống cấp một{" "}
              <Manh>mã tra cứu</Manh> gửi tới địa chỉ thư điện tử đã đăng ký; mã
              này dùng để xem đơn, khai danh sách hành khách và theo dõi tình
              trạng thanh toán.
            </Doan>

            <Doan>
              <Manh>4.2.</Manh> Đơn hàng được giữ chỗ trong{" "}
              <Manh>{data.booking.payment_ttl_minutes} phút</Manh> kể từ khi khởi
              tạo. Quá thời hạn mà chưa thanh toán, hệ thống tự hủy đơn và trả
              chỗ lại cho khách hàng khác. Việc chậm trễ thanh toán dẫn tới mất
              chỗ không thuộc trách nhiệm của công ty.
            </Doan>

            <Doan>
              <Manh>4.3.</Manh> Chương trình bán theo chỗ được thanh toán{" "}
              <Manh>một lần, đủ giá trị đơn hàng</Manh> trong thời hạn giữ chỗ
              nêu trên. Công ty hiện không áp dụng hình thức đặt cọc cho nhóm
              chương trình này. Riêng chương trình tổ chức riêng theo đoàn được
              chốt qua báo giá và có thể thu tiền cọc theo thỏa thuận ghi trong
              báo giá đó.
            </Doan>

            <Doan>
              <Manh>4.4.</Manh> Thanh toán trực tuyến qua cổng VNPay. Ngoài ra
              công ty ghi nhận các khoản thu bằng chuyển khoản hoặc tiền mặt do
              bộ phận điều hành nhập vào sổ giao dịch của đơn. Mọi khoản thu và
              hoàn của một đơn đều được ghi vào sổ ấy và khách hàng xem lại được
              khi tra cứu đơn.
            </Doan>

            <div className="text-body-sm text-muted mt-4 rounded-lg border border-hairline bg-surface-subtle px-4 py-3">
              <p className="text-ink font-semibold">
                Thông tin chuyển khoản (dữ liệu mẫu phục vụ thử nghiệm)
              </p>
              <p className="mt-1">
                Chủ tài khoản: Công ty Cổ phần Du lịch Vivu Booking — Số tài
                khoản: 0123 4567 8910 — Ngân hàng: VNPay Demo Bank, Chi nhánh Hà
                Nội.
              </p>
              <p className="mt-1">
                Nội dung chuyển khoản ghi rõ <Manh>mã tra cứu đơn</Manh> và số
                điện thoại người đăng ký.
              </p>
            </div>

            <Doan>
              <Manh>4.5.</Manh> Khách hàng chịu trách nhiệm về tính chính xác của
              thông tin đã cung cấp. Công ty sử dụng thông tin này để làm thủ tục
              với các nhà cung cấp dịch vụ; nếu sai lệch dẫn tới phải điều chỉnh,
              khách hàng thanh toán các chi phí phát sinh (nếu có).
            </Doan>

            {/* --- 5. Hủy đơn theo yêu cầu của khách hàng --- */}
            <Muc>5. Hủy đơn hàng theo yêu cầu của khách hàng</Muc>

            <Doan>
              <Manh>5.1.</Manh> Mục này áp dụng đối với trường hợp khách hàng chủ
              động yêu cầu hủy đơn hàng. Trường hợp công ty hủy chuyến khởi hành
              được điều chỉnh tại mục 6 và không áp dụng biểu phí tại mục này.
            </Doan>

            <Doan>
              <Manh>5.2.</Manh> Mức hoàn được xác định căn cứ vào số ngày còn lại
              tính đến giờ khởi hành ghi trên đơn hàng, theo giờ Việt Nam:
            </Doan>

            <div className="mt-5 overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-hairline">
                    <th className="text-caption-sm text-muted py-2.5 pr-6 font-normal tracking-wide uppercase">
                      Hủy trước ngày khởi hành
                    </th>
                    <th className="text-caption-sm text-muted w-28 py-2.5 pr-6 font-normal tracking-wide uppercase">
                      Được hoàn
                    </th>
                    <th className="text-caption-sm text-muted py-2.5 font-normal tracking-wide uppercase">
                      Vì sao
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.cancellation.rules.map((bac, i) => (
                    <tr
                      key={i}
                      className="border-b border-hairline-soft last:border-b-0"
                    >
                      <td className="text-body-md text-ink py-3.5 pr-6 whitespace-nowrap">
                        {bac.window}
                      </td>
                      <td className="text-body-md text-ink py-3.5 pr-6 font-semibold tabular-nums">
                        {bac.refund_percent}%
                      </td>
                      <td className="text-body-sm text-muted py-3.5">
                        {bac.note ?? "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {data.cancellation.description ? (
              <p className="text-body-sm text-muted mt-3">
                {data.cancellation.description}
              </p>
            ) : null}

            <Doan>
              <Manh>5.3.</Manh> Số ngày quy định tại khoản 5.2 được tính đến phần
              lẻ và không làm tròn lên. Yêu cầu hủy gửi trước giờ khởi hành 47
              giờ tương ứng 1,96 ngày và được xếp vào bậc dưới 2 ngày.
            </Doan>

            <Doan>
              <Manh>5.4.</Manh> Phí hủy được tính trên tổng giá trị đơn hàng. Số
              tiền hoàn bằng số tiền khách hàng đã thực thanh toán trừ đi phí hủy
              và trong mọi trường hợp <Manh>không nhỏ hơn 0</Manh>. Khách hàng
              không phải thanh toán thêm bất kỳ khoản nào khi hủy, kể cả khi phí
              hủy lớn hơn số tiền đã thanh toán.
            </Doan>

            <Doan>
              <Manh>5.5.</Manh> Đơn hàng chưa thanh toán được khách hàng hủy trực
              tiếp trên hệ thống và không làm phát sinh nghĩa vụ tài chính của
              khách hàng.
            </Doan>

            <Doan>
              <Manh>5.6.</Manh> Đơn hàng đã thanh toán một phần hoặc toàn bộ chỉ
              được hủy sau khi khách hàng gửi yêu cầu hủy và được công ty chấp
              thuận. Đơn hàng giữ nguyên hiệu lực cho đến thời điểm được chấp
              thuận. Kết quả xử lý, bao gồm cả trường hợp từ chối và lý do từ
              chối, được thông báo tới địa chỉ thư điện tử khách hàng đã đăng ký.
            </Doan>

            <Doan>
              <Manh>5.7.</Manh> Trước khi xác nhận hủy, hệ thống hiển thị mức
              hoàn và số tiền dự kiến nhận lại tương ứng với thời điểm gửi yêu
              cầu để khách hàng đối chiếu.
            </Doan>

            <Doan>
              <Manh>5.8.</Manh> Khách hàng không có mặt tại điểm đón vào giờ khởi
              hành và không thông báo trước được xác định là không sử dụng dịch
              vụ. Đơn hàng trong trường hợp này không được hoàn tiền.
            </Doan>

            <Doan>
              <Manh>5.9.</Manh> Đơn hàng không còn được hủy kể từ thời điểm
              chuyến khởi hành bắt đầu. Chuyến đang thực hiện hoặc đã kết thúc
              không thuộc phạm vi điều chỉnh của mục này.
            </Doan>

            <Doan>
              <Manh>5.10.</Manh> Việc hủy đơn hàng có hiệu lực kể từ thời điểm
              được ghi nhận trên hệ thống và không được khôi phục. Khách hàng có
              nhu cầu tham gia trở lại thực hiện đặt đơn hàng mới theo chỗ còn
              trống tại thời điểm đặt.
            </Doan>

            {/* --- 6. Công ty hủy chuyến --- */}
            <Muc>6. Công ty hủy chuyến khởi hành</Muc>

            <Doan>
              <Manh>6.1.</Manh> Công ty có quyền hủy một chuyến khởi hành trong
              các trường hợp sau: số lượng khách đăng ký không đạt số khách tối
              thiểu tính đến hạn chốt danh sách; xảy ra sự kiện bất khả kháng
              theo định nghĩa tại mục 1; hoặc nhà cung cấp dịch vụ không thực
              hiện được nghĩa vụ và công ty không thu xếp được phương án thay thế
              phù hợp.
            </Doan>

            <Doan>
              <Manh>6.2.</Manh> Trong mọi trường hợp công ty là bên hủy, khách
              hàng được <Manh>hoàn 100%</Manh> số tiền đã thanh toán, không khấu
              trừ bất kỳ khoản phí nào và không phụ thuộc vào thời điểm hủy. Biểu
              phí quy định tại mục 5 không áp dụng.
            </Doan>

            <Doan>
              <Manh>6.3.</Manh> Khách hàng được lựa chọn giữa việc nhận lại toàn
              bộ số tiền đã thanh toán hoặc chuyển sang một chuyến khởi hành
              khác. Việc chuyển chuyến trong trường hợp này không thu phí đổi
              lịch và không bị giới hạn bởi hạn chốt danh sách của chuyến đã hủy.
              Trường hợp chuyến khởi hành mới có giá khác với chuyến ban đầu,
              phần chênh lệch được thu thêm hoặc hoàn lại tương ứng theo quy định
              tại mục 7.
            </Doan>

            <Doan>
              <Manh>6.4.</Manh> Đơn hàng chưa thanh toán thuộc chuyến khởi hành
              bị hủy được công ty hủy và hoàn trả toàn bộ số chỗ. Khách hàng được
              mời đặt lại một chuyến khởi hành khác.
            </Doan>

            <Doan>
              <Manh>6.5.</Manh> Công ty thông báo bằng thư điện tử tới địa chỉ
              khách hàng đã đăng ký ngay khi có quyết định hủy, nêu rõ lý do và
              phương án xử lý đối với đơn hàng.
            </Doan>

            {/* --- 7. Chuyển chuyến và ghép chuyến --- */}
            <Muc>7. Chuyển chuyến và ghép chuyến</Muc>

            <p className="text-title-md text-ink mt-4">
              7.1. Khách hàng đề nghị chuyển chuyến
            </p>

            <DanhSach>
              <li>
                {data.transfer.notice_days > 0 ? (
                  <>
                    Khách hàng đề nghị đổi chuyến cần báo trước giờ khởi hành ít
                    nhất <Manh>{data.transfer.notice_days} ngày</Manh>.{" "}
                  </>
                ) : (
                  <>
                    Khách hàng đề nghị đổi chuyến tới trước{" "}
                    <Manh>hạn chốt danh sách</Manh> của cả chuyến đang đặt lẫn
                    chuyến muốn đổi sang.{" "}
                  </>
                )}
                {data.transfer.free_transfers} lần đổi đầu tiên miễn phí; từ lần
                thứ {data.transfer.free_transfers + 1} trở đi thu phí đổi lịch{" "}
                <Manh>{formatPrice(data.transfer.fee)}</Manh>.
              </li>
              <li>
                Chênh lệch giá giữa hai chuyến được thu thêm hoặc hoàn lại tương
                ứng.
              </li>
              <li>
                Sau hạn chốt danh sách, đơn không còn chuyển được sang chuyến
                khác, vì chỗ ở chuyến cũ đã thanh toán cho nhà cung cấp. Trường
                hợp này xử lý theo mục 5.
              </li>
              <li>
                Công ty <Manh>không đơn phương</Manh> chuyển một đơn hàng riêng
                lẻ sang chuyến khác. Mọi lần chuyển theo đề nghị đều phải được
                trao đổi và có sự đồng ý của khách hàng; nội dung trao đổi được
                ghi lại kèm thời điểm.
              </li>
            </DanhSach>

            <p className="text-title-md text-ink mt-5">
              7.2. Công ty ghép hai chuyến khởi hành
            </p>

            <Doan>
              Khi một chuyến không đạt số khách tối thiểu, thay vì hủy chuyến,
              công ty có thể <Manh>ghép chuyến đó với một chuyến khác</Manh> của
              cùng chương trình tour để đủ điều kiện khởi hành. Việc ghép chỉ
              thực hiện khi thỏa mãn đồng thời các điều kiện sau:
            </Doan>

            <DanhSach>
              <li>Hai chuyến thuộc cùng một chương trình tour.</li>
              <li>
                Ngày khởi hành của hai chuyến chênh nhau{" "}
                <Manh>không quá 2 ngày</Manh>.
              </li>
              <li>
                Cả hai chuyến đều chưa tới hạn chốt danh sách, và chuyến tiếp
                nhận còn đủ chỗ cho toàn bộ khách được chuyển sang.
              </li>
              <li>
                Chương trình tổ chức riêng theo đoàn không áp dụng, vì khách hàng
                đã thanh toán để đi trọn chuyến của riêng mình.
              </li>
            </DanhSach>

            <Doan>
              Khác với khoản 7.1, đây là <Manh>quyết định của công ty</Manh> và
              không cần sự đồng ý trước của từng khách hàng. Công ty thông báo
              bằng thư điện tử ngay khi hoàn tất, nêu rõ ngày khởi hành cũ, ngày
              khởi hành mới và lý do.
            </Doan>

            <Doan>
              Khách hàng <Manh>không chấp nhận ngày khởi hành mới</Manh> có quyền
              yêu cầu hủy đơn và được <Manh>hoàn 100%</Manh> số tiền đã thanh
              toán, không áp dụng biểu phí tại mục 5. Vui lòng liên hệ tổng đài
              trong vòng 3 ngày làm việc kể từ khi nhận thông báo.
            </Doan>

            {/* --- 8. Bất khả kháng và thay đổi lộ trình --- */}
            <Muc>8. Sự kiện bất khả kháng và thay đổi lộ trình</Muc>

            <Doan>
              <Manh>8.1.</Manh> Khi xảy ra sự kiện bất khả kháng như định nghĩa
              tại mục 1, hai bên không phải chịu trách nhiệm về việc không thực
              hiện được nghĩa vụ do sự kiện đó gây ra. Mỗi bên có trách nhiệm cố
              gắng tối đa để giảm thiểu tổn thất cho bên kia. Công ty thông báo
              trong thời gian sớm nhất và đưa ra phương án thay thế: đổi lịch
              trình, chuyển sang chuyến khởi hành khác, hoặc hủy chuyến và hoàn
              tiền theo mục 6.
            </Doan>

            <Doan>
              <Manh>8.2.</Manh> Khách hàng phải dời chuyến vì sự kiện bất khả
              kháng <Manh>không chịu phí đổi lịch</Manh>, kể cả khi đây không
              phải lần đổi đầu tiên.
            </Doan>

            <Doan>
              <Manh>8.3.</Manh> Tùy tình hình thực tế, công ty có quyền sắp xếp
              lại thứ tự các điểm tham quan trong chương trình vì sự thuận tiện
              hoặc an toàn của khách hàng, với điều kiện{" "}
              <Manh>giữ nguyên số lượng và tiêu chuẩn</Manh> các hạng mục đã bán.
              Hạng mục không thực hiện được sẽ được thay thế bằng phương án tương
              đương do công ty chịu chi phí, hoặc hoàn lại phần giá trị tương ứng.
            </Doan>

            {/* --- 9. Thời hạn và cách thức hoàn tiền --- */}
            <Muc>9. Thời hạn và cách thức hoàn tiền</Muc>

            <DanhSach>
              <li>
                Bộ phận chăm sóc khách hàng liên hệ trong vòng{" "}
                <Manh>3 ngày làm việc</Manh> kể từ khi đơn được hủy, để xác nhận
                số tiền hoàn và thông tin tài khoản nhận tiền.
              </li>
              <li>
                Tiền được hoàn về đúng tài khoản đã dùng để thanh toán. Trường
                hợp thanh toán qua cổng VNPay, thời gian tiền về phụ thuộc thêm
                vào ngân hàng phát hành thẻ.
              </li>
              <li>
                Khách hàng đổi tài khoản nhận tiền hoàn phải xác thực bằng địa
                chỉ thư điện tử đã dùng để đặt tour.
              </li>
              <li>
                Mọi khoản thu và hoàn của một đơn đều được ghi vào sổ giao dịch
                của đơn đó; khách hàng xem lại được khi tra cứu đơn.
              </li>
              <li>
                Trường hợp hoàn tiền do sự kiện bất khả kháng, thời gian hoàn phụ
                thuộc thêm vào quy định của các nhà cung cấp dịch vụ liên quan.
              </li>
            </DanhSach>

            {/* --- 10. Lưu trú --- */}
            <Muc>10. Lưu trú</Muc>

            <Doan>
              Khách sạn được bố trí theo tiêu chuẩn tương ứng với mức giá của
              chương trình khách hàng đã chọn, trên cơ sở phòng hai giường đơn
              hoặc một giường đôi tùy cơ cấu phòng của từng khách sạn. Trường hợp
              cần thay đổi vì bất kỳ lý do nào, khách sạn thay thế có tiêu chuẩn
              tương đương và được thông báo trước khi khởi hành.
            </Doan>

            <Doan>
              Giờ nhận phòng và trả phòng theo quy định của từng khách sạn, thông
              thường nhận phòng sau 14 giờ và trả phòng trước 12 giờ. Yêu cầu đặc
              biệt về phòng được đáp ứng tùy khả năng của khách sạn và có thể
              phát sinh chi phí.
            </Doan>

            {/* --- 11. Vận chuyển --- */}
            <Muc>11. Vận chuyển</Muc>

            <Doan>
              Phương tiện vận chuyển tùy theo từng chương trình và được ghi trong
              phần thông tin tour. Số chỗ trên phương tiện được tính theo{" "}
              <Manh>số ghế</Manh>: em bé dưới 2 tuổi đi cùng người lớn không
              chiếm một ghế riêng.
            </Doan>

            <Doan>
              Giờ khởi hành, giờ dự kiến tới điểm đến, giờ rời điểm đến và giờ về
              hiển thị trên trang chi tiết của từng chuyến là{" "}
              <Manh>giờ dự kiến</Manh>. Thời gian thực tế có thể thay đổi vì tình
              hình giao thông, thời tiết hoặc điều chỉnh của đơn vị vận chuyển
              công cộng. Công ty thông báo cho khách hàng khi thời gian cho phép
              và không chịu trách nhiệm bồi thường đối với các thiệt hại phát
              sinh từ sự chậm trễ nằm ngoài khả năng kiểm soát.
            </Doan>

            {/* --- 12. Hành lý --- */}
            <Muc>12. Hành lý</Muc>

            <Doan>
              Khách hàng tự bảo quản hành lý và tài sản cá nhân trong suốt hành
              trình. Công ty không chịu trách nhiệm về việc thất lạc hoặc hư hỏng
              hành lý, nhưng có trách nhiệm hỗ trợ khách hàng liên hệ và khai báo
              với các bên liên quan để truy tìm. Việc bồi thường (nếu có) theo
              quy định của đơn vị cung cấp dịch vụ vận chuyển.
            </Doan>

            {/* --- 13. Bảo hiểm --- */}
            <Muc>13. Bảo hiểm du lịch</Muc>

            <Doan>
              Công ty <Manh>không tự phát hành</Manh> và không cam kết một mức
              đền bù bảo hiểm cố định cho mọi chương trình. Bảo hiểm du lịch chỉ
              được áp dụng khi nó xuất hiện trong danh mục{" "}
              <Manh>dịch vụ bao gồm</Manh> của chương trình cụ thể mà khách hàng
              đặt; khi đó điều kiện, phạm vi và mức đền bù theo quy tắc của đơn vị
              bảo hiểm phát hành.
            </Doan>

            <Doan>
              Khách hàng có nhu cầu bảo hiểm với mức trách nhiệm cao hơn vui lòng
              chủ động mua thêm và thông báo cho công ty trước ngày khởi hành.
            </Doan>

            {/* --- 14. Yêu cầu đặc biệt và cam kết sức khỏe --- */}
            <Muc>14. Yêu cầu đặc biệt và cam kết về sức khỏe</Muc>

            <Doan>
              <Manh>14.1.</Manh> Các yêu cầu đặc biệt — suất ăn kiêng, hỗ trợ di
              chuyển, ghép phòng — phải được thông báo ngay tại thời điểm đăng ký.
              Công ty cố gắng đáp ứng trong khả năng nhưng không chịu trách nhiệm
              về việc nhà cung cấp dịch vụ từ chối.
            </Doan>

            <Doan>
              <Manh>14.2.</Manh> Khách hàng và những người cùng đi trong đơn hàng
              tự cam kết đủ sức khỏe tham gia chương trình đã chọn. Trường hợp
              phát sinh vấn đề sức khỏe trong hành trình, công ty hỗ trợ liên hệ
              cơ sở y tế; các chi phí khám chữa bệnh, lưu trú và vận chuyển phát
              sinh ngoài chương trình do khách hàng chi trả, trừ phần thuộc phạm
              vi bảo hiểm (nếu có).
            </Doan>

            <Doan>
              <Manh>14.3.</Manh> Khách hàng từ 14 tuổi trở lên mang theo giấy tờ
              tùy thân còn hạn; trẻ em dưới 14 tuổi mang giấy khai sinh bản chính
              hoặc bản sao có chứng thực. Hướng dẫn viên đối chiếu danh sách hành
              khách tại điểm đón.
            </Doan>

            {/* --- 15. Trách nhiệm của hai bên --- */}
            <Muc>15. Trách nhiệm của hai bên</Muc>

            <p className="text-title-md text-ink mt-4">Công ty có trách nhiệm</p>

            <DanhSach>
              <li>
                Tổ chức chuyến đi đúng chương trình đã bán: phương tiện di
                chuyển, lưu trú, các bữa ăn và điểm tham quan ghi trong lịch
                trình.
              </li>
              <li>
                Bố trí phương án thay thế tương đương khi một hạng mục trong
                chương trình không thực hiện được, và chịu chi phí phát sinh của
                phương án đó.
              </li>
              <li>
                Thông báo kịp thời mọi thay đổi ảnh hưởng tới chuyến đi, và không
                thu bất kỳ khoản phát sinh nào của khách hàng khi chưa có sự đồng
                ý của họ.
              </li>
              <li>
                Bố trí hướng dẫn viên phụ trách đoàn và ghi nhận tình hình đoàn
                tại các điểm dừng trong hành trình.
              </li>
            </DanhSach>

            <p className="text-title-md text-ink mt-5">
              Khách hàng có trách nhiệm
            </p>

            <DanhSach>
              <li>
                Cung cấp thông tin chính xác khi đặt tour và khai danh sách hành
                khách trước hạn chốt danh sách: họ tên, ngày sinh, giấy tờ tùy
                thân, số điện thoại và địa chỉ thư điện tử.
              </li>
              <li>
                Chỉ định một người liên hệ cho mỗi đơn hàng, để hướng dẫn viên
                biết liên lạc với ai khi cần.
              </li>
              <li>
                Có mặt đúng giờ tại điểm tập kết. Khách không có mặt lúc khởi
                hành được xử lý theo khoản 5.8.
              </li>
              <li>
                Tự chi trả các khoản chi tiêu cá nhân ngoài chương trình, và tuân
                thủ hướng dẫn của hướng dẫn viên về an toàn trong suốt hành trình.
              </li>
            </DanhSach>

            {/* --- 16. Điều khoản sử dụng website --- */}
            <Muc id="dieu-khoan">16. Điều khoản sử dụng trang web</Muc>

            <DanhSach>
              <li>
                Khách hàng chịu trách nhiệm về tính chính xác của thông tin cung
                cấp khi đặt tour. Thông tin sai có thể khiến khách hàng không lên
                được phương tiện hoặc không nhận được thông báo về chuyến đi.
              </li>
              <li>
                Mã tra cứu đơn hàng có giá trị như chìa khóa truy cập đơn. Khách
                hàng có trách nhiệm giữ kín mã này; các thao tác nhạy cảm như thay
                đổi tài khoản nhận tiền hoàn còn đòi xác thực thêm bằng địa chỉ
                thư điện tử đã đăng ký.
              </li>
              <li>
                Đơn đã thanh toán là cam kết giữ chỗ chính thức giữa Vivu Booking
                và khách hàng, kèm theo bảng phí hủy tại thời điểm đặt như nêu ở
                mục 5.
              </li>
              <li>
                Công ty có quyền từ chối hoặc hủy các đơn có dấu hiệu gian lận,
                kèm hoàn tiền theo quy định.
              </li>
              <li>
                Chỉ khách hàng đã hoàn thành chuyến đi mới được đánh giá chương
                trình đó, và nội dung đánh giá được kiểm duyệt trước khi hiển thị
                công khai. Trường hợp không được duyệt, công ty nêu lý do qua thư
                điện tử và khách hàng có thể chỉnh sửa rồi gửi lại.
              </li>
            </DanhSach>

            {/* --- 17. Giải quyết tranh chấp --- */}
            <Muc>17. Giải quyết tranh chấp</Muc>

            <Doan>
              Mọi vướng mắc phát sinh trong quá trình thực hiện được hai bên ưu
              tiên giải quyết bằng thương lượng trên tinh thần thiện chí trong
              thời hạn 30 ngày kể từ ngày một bên đưa ra. Khách hàng gửi phản ánh
              qua tổng đài hoặc thư điện tử hỗ trợ; công ty phản hồi trong vòng 3
              ngày làm việc.
            </Doan>

            <Doan>
              Hết thời hạn nêu trên mà tranh chấp không được giải quyết, hoặc một
              trong hai bên không đồng ý với kết quả thương lượng, tranh chấp được
              đưa ra Tòa án nhân dân có thẩm quyền theo quy định của pháp luật
              Việt Nam.
            </Doan>

            {/* --- 18. Hiệu lực --- */}
            <Muc>18. Hiệu lực thi hành</Muc>

            <Doan>
              {data.cancellation.effective_from ? (
                <>
                  Bảng phí hủy tại mục 5 có hiệu lực từ{" "}
                  <Manh>{data.cancellation.effective_from}</Manh>.{" "}
                </>
              ) : null}
              Đơn đặt tour phát sinh trước thời điểm này tiếp tục áp dụng bảng phí
              tại thời điểm đặt — hệ thống lưu điều khoản vào từng đơn ngay khi
              khách hàng đặt, nên việc công ty cập nhật chính sách không làm thay
              đổi thỏa thuận đã ký kết.
            </Doan>

            <Doan>
              Đơn hàng cùng các văn bản kèm theo — biên nhận, chương trình tour,
              hợp đồng du lịch — được xem là bộ hồ sơ có giá trị ràng buộc giữa
              hai bên. Do chương trình bán cho nhóm khách đăng ký chung một đơn,
              người đại diện và những người cùng đi được coi là cùng chấp thuận
              toàn bộ nội dung kể từ thời điểm thanh toán, không phụ thuộc vào
              việc từng người có ký tên hay không.
            </Doan>

            <Doan>
              Công ty có quyền sửa đổi văn bản này và sẽ công bố phiên bản mới kèm
              thời điểm bắt đầu áp dụng ngay trên trang này.
            </Doan>

            {/* --- 19. Hỏi đáp --- */}
            <Muc>19. Câu hỏi thường gặp</Muc>

            <div className="mt-1">
              <CauHoi hoi="Tôi được hoàn bao nhiêu tiền khi hủy?">
                <p>
                  Phần trăm lấy theo bảng ở mục 5, tính theo số ngày còn lại tới
                  giờ khởi hành.
                </p>
                <p>
                  Có một chi tiết dễ nhầm:{" "}
                  <strong>phí hủy tính trên tổng giá trị đơn</strong>, còn tiền
                  hoàn thì trừ trên <strong>số bạn đã thực trả</strong>. Đổi lại,
                  tiền hoàn không bao giờ âm — hủy tour thì bạn không phải nộp
                  thêm đồng nào, kể cả khi phí hủy lớn hơn số đã trả.
                </p>
              </CauHoi>

              <CauHoi hoi="Công ty hủy chuyến thì tôi có mất phí không?">
                <p>
                  Không. Chuyến bị hủy vì phía công ty — thời tiết, không đủ số
                  khách tối thiểu, sự cố nhà cung cấp — thì bạn được{" "}
                  <strong>hoàn 100% số đã trả</strong>, không trừ bất kỳ khoản
                  nào, bất kể còn mấy ngày tới ngày đi. Bảng phí ở mục 5 chỉ áp
                  khi chính bạn là người hủy.
                </p>
              </CauHoi>

              <CauHoi hoi="Chuyến của tôi bị ghép sang ngày khác, tôi có phải đi không?">
                <p>
                  Không bắt buộc. Ghép chuyến là quyết định của công ty khi một
                  chuyến không đủ khách tối thiểu, và ngày mới chênh không quá 2
                  ngày so với ngày bạn đặt.
                </p>
                <p>
                  Nếu ngày mới không tiện, bạn{" "}
                  <strong>yêu cầu hủy và được hoàn 100%</strong> — liên hệ tổng
                  đài trong vòng 3 ngày làm việc kể từ khi nhận thư thông báo. Xem
                  khoản 7.2.
                </p>
              </CauHoi>

              <CauHoi hoi="Tôi đặt tour mà chưa có tài khoản thì tra cứu thế nào?">
                <p>
                  Sau khi đặt, hệ thống gửi <strong>mã tra cứu</strong> về email
                  bạn đã điền. Mở trang tra cứu đơn, dán mã vào là xem được đơn,
                  khai danh sách hành khách và theo dõi thanh toán — không cần
                  đăng nhập.
                </p>
                <p>
                  Mất mã thì dùng chức năng gửi lại mã về email. Riêng việc đổi
                  tài khoản nhận tiền hoàn còn phải nhập đúng email đã đặt: chỉ
                  giữ mã tra cứu là chưa đủ.
                </p>
              </CauHoi>

              <CauHoi hoi="Em bé đi cùng có phải mua chỗ không?">
                <p>
                  Em bé dưới 2 tuổi không chiếm ghế riêng nên không tính vào số
                  chỗ của chuyến — hai người lớn kèm một em bé vẫn đặt được chuyến
                  chỉ còn 2 chỗ trống. Biểu mẫu đặt tour hiện rõ{" "}
                  <strong>số chỗ chiếm trên xe</strong> để bạn đối chiếu.
                </p>
              </CauHoi>

              <CauHoi hoi="Tôi khai thiếu thông tin hành khách thì sao?">
                <p>
                  Khai dần được, không bắt điền đủ mới cho lưu. Nhưng phải xong
                  trước <strong>hạn chốt danh sách</strong>: sau mốc đó danh sách
                  đã gửi cho khách sạn và nhà xe nên không sửa được nữa.
                </p>
              </CauHoi>
            </div>

            <h2 className="text-display-sm text-ink mt-16 border-t border-hairline pt-8">
              PHẦN II — CHÍNH SÁCH BẢO VỆ DỮ LIỆU CÁ NHÂN
            </h2>

            {/* --- II.1 Tổng quan --- */}
            <Muc id="bao-mat">1. Tổng quan</Muc>

            <Doan>
              Vivu Booking tôn trọng quyền riêng tư của khách hàng. Chính sách này
              nêu rõ việc thu thập, xử lý và sử dụng dữ liệu cá nhân trên trang
              web của chúng tôi, theo Nghị định 13/2023/NĐ-CP về bảo vệ dữ liệu cá
              nhân.
            </Doan>

            <Doan>
              Dữ liệu cá nhân được hiểu là thông tin gắn liền với một cá nhân cụ
              thể hoặc giúp xác định một cá nhân cụ thể. Bằng việc đặt tour hoặc
              tạo tài khoản, khách hàng đồng ý cho chúng tôi thu thập và xử lý dữ
              liệu cá nhân theo chính sách này. Nếu không đồng ý, vui lòng không
              sử dụng dịch vụ.
            </Doan>

            {/* --- II.2 Dữ liệu thu thập --- */}
            <Muc>2. Dữ liệu cá nhân được thu thập</Muc>

            <DanhSach>
              <li>
                <Manh>Của người đặt tour:</Manh> họ tên, số điện thoại, địa chỉ
                thư điện tử, địa chỉ liên hệ.
              </li>
              <li>
                <Manh>Của từng hành khách trong đơn:</Manh> họ tên, ngày sinh,
                giới tính, quốc tịch, số giấy tờ tùy thân, yêu cầu đặc biệt (nếu
                có).
              </li>
              <li>
                <Manh>Của tài khoản đăng ký:</Manh> tên đăng nhập, mật khẩu đã mã
                hóa một chiều, lịch sử đơn hàng và đánh giá.
              </li>
              <li>
                <Manh>Thông tin nhận tiền hoàn:</Manh> số tài khoản, tên chủ tài
                khoản, ngân hàng — chỉ thu thập khi phát sinh nghĩa vụ hoàn tiền.
              </li>
              <li>
                <Manh>Trong hành trình:</Manh> dữ liệu điểm danh tại các điểm dừng
                và hình ảnh do hướng dẫn viên ghi nhận, phục vụ việc đối chiếu khi
                có khiếu nại.
              </li>
            </DanhSach>

            <Doan>
              Chúng tôi <Manh>không lưu thông tin thẻ thanh toán</Manh>. Giao dịch
              được xử lý trọn vẹn trên cổng VNPay; hệ thống chỉ nhận lại mã giao
              dịch và kết quả.
            </Doan>

            <Doan>
              Khi khách hàng cung cấp dữ liệu của người khác — hành khách đi cùng
              trong đơn — khách hàng cam kết đã được những người đó đồng ý cho
              chia sẻ thông tin với chúng tôi.
            </Doan>

            {/* --- II.3 Mục đích --- */}
            <Muc>3. Mục đích xử lý dữ liệu</Muc>

            <DanhSach>
              <li>Xác thực khách hàng và xử lý đơn đặt tour.</li>
              <li>
                Lập danh sách đoàn gửi cho các nhà cung cấp dịch vụ của chính
                chuyến đi đó: đơn vị vận chuyển, khách sạn, nhà hàng, điểm tham
                quan.
              </li>
              <li>
                Liên hệ về chuyến đi: xác nhận đơn, nhắc lịch khởi hành, thông báo
                thay đổi, xử lý yêu cầu hủy hoặc chuyển chuyến.
              </li>
              <li>Xuất chứng từ và hợp đồng theo quy định pháp luật.</li>
              <li>Thực hiện yêu cầu của cơ quan nhà nước có thẩm quyền.</li>
            </DanhSach>

            <Doan>
              Chúng tôi <Manh>không sử dụng</Manh> dữ liệu của khách hàng cho mục
              đích quảng cáo của bên thứ ba, và không bán dữ liệu cho bất kỳ ai.
            </Doan>

            {/* --- II.4 Tiết lộ --- */}
            <Muc>4. Tổ chức, cá nhân được tiếp cận dữ liệu</Muc>

            <DanhSach>
              <li>
                Nhân sự của công ty theo phân quyền: bộ phận điều hành tiếp cận
                thông tin đơn hàng và danh sách khách; hướng dẫn viên chỉ tiếp cận
                danh sách của chính chuyến mình phụ trách.
              </li>
              <li>
                Nhà cung cấp dịch vụ của chuyến đi, trong phạm vi cần thiết để
                phục vụ khách hàng.
              </li>
              <li>Đơn vị cung cấp cổng thanh toán, để xử lý giao dịch.</li>
              <li>
                Cơ quan nhà nước có thẩm quyền, khi có yêu cầu hợp pháp bằng văn
                bản.
              </li>
            </DanhSach>

            <Doan>
              Mọi lần công ty liên hệ với khách hàng về một đơn — gọi điện, nhắn
              tin, gửi thư — đều được ghi lại kèm thời điểm và nội dung, để cả hai
              bên đối chiếu khi cần.
            </Doan>

            {/* --- II.5 Lưu trữ --- */}
            <Muc>5. Thời gian lưu trữ</Muc>

            <Doan>
              Dữ liệu cá nhân được lưu trong thời gian cần thiết để thực hiện mục
              đích đã nêu, và trong thời hạn lưu trữ chứng từ theo quy định của
              pháp luật kế toán. Dữ liệu của đơn hàng đã hoàn tất được giữ lại để
              đối chiếu khi có khiếu nại phát sinh sau chuyến đi.
            </Doan>

            <Doan>
              Dữ liệu được lưu trữ và xử lý tại Việt Nam. Chúng tôi không chuyển
              dữ liệu cá nhân của khách hàng ra nước ngoài.
            </Doan>

            {/* --- II.6 Quyền của khách hàng --- */}
            <Muc>6. Quyền của khách hàng đối với dữ liệu</Muc>

            <DanhSach>
              <li>
                Được biết dữ liệu nào đang được xử lý và xử lý cho mục đích gì.
              </li>
              <li>
                Yêu cầu chỉnh sửa dữ liệu chưa chính xác. Thông tin liên hệ của
                đơn hàng sửa được trực tiếp trên hệ thống; danh sách hành khách sửa
                được trước hạn chốt danh sách.
              </li>
              <li>
                Yêu cầu xóa dữ liệu, trừ phần bắt buộc lưu theo quy định pháp luật
                hoặc cần cho việc giải quyết tranh chấp đang diễn ra.
              </li>
              <li>Rút lại sự đồng ý và yêu cầu ngừng xử lý dữ liệu.</li>
              <li>Khiếu nại tới cơ quan nhà nước có thẩm quyền.</li>
            </DanhSach>

            <Doan>
              Yêu cầu gửi tới địa chỉ thư điện tử hỗ trợ bên dưới. Chúng tôi phản
              hồi trong vòng 3 ngày làm việc.
            </Doan>

            {/* --- II.7 An toàn --- */}
            <Muc>7. Biện pháp bảo vệ và rủi ro</Muc>

            <DanhSach>
              <li>
                Mật khẩu được mã hóa một chiều — kể cả nhân viên công ty cũng
                không đọc được.
              </li>
              <li>
                Truy cập dữ liệu phân theo vai trò, và các thao tác chạm tới tiền
                hoặc tới chỗ ngồi đều để lại nhật ký ghi rõ ai thực hiện, lúc nào,
                vì lý do gì.
              </li>
              <li>
                Các cửa dễ bị dò — đăng nhập, đăng ký, quên mật khẩu, kiểm mã giảm
                giá — đều có giới hạn số lần thử.
              </li>
            </DanhSach>

            <Doan>
              Không có hệ thống kỹ thuật nào an toàn tuyệt đối. Chúng tôi áp dụng
              các biện pháp trong khả năng và sẽ thông báo cho khách hàng cùng cơ
              quan có thẩm quyền theo quy định nếu xảy ra sự cố ảnh hưởng tới dữ
              liệu cá nhân.
            </Doan>

            {/* --- II.8 Liên hệ --- */}
            <Muc>8. Thông tin liên hệ</Muc>

            <Doan>
              Công ty Cổ phần Du lịch Vivu Booking — Địa chỉ: 1 Đại Cồ Việt, Hai
              Bà Trưng, Hà Nội. Tổng đài <Manh>1900 1234</Manh>. Thư điện tử hỗ
              trợ: <Manh>hotro@vivubooking.vn</Manh>. Phụ trách bảo vệ dữ liệu cá
              nhân: <Manh>dpo@vivubooking.vn</Manh>.
            </Doan>

            <p className="text-body-sm text-muted mt-3">
              Thông tin doanh nghiệp, số tài khoản và địa chỉ liên hệ trên trang
              này là dữ liệu mẫu phục vụ mục đích thử nghiệm hệ thống.
            </p>

            {/* --- Liên hệ --- */}
            <Muc>Còn điều gì chưa rõ</Muc>

            <Doan>
              Gọi tổng đài <Manh>1900 1234</Manh> hoặc gửi câu hỏi qua{" "}
              <Link
                to="/contact"
                className="text-primary-600 font-semibold hover:underline"
              >
                trang liên hệ
              </Link>
              . Chúng tôi trả lời trong vòng 3 ngày làm việc.
            </Doan>
          </>
        )}
      </div>
    </div>
  );
}
