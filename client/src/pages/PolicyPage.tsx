import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { ChevronDown } from "lucide-react";
import policyService from "@/services/policyService";
import type { PolicyResponse } from "@/services/policyService";
import { formatPrice } from "@/utils/format";
import { useDocumentMeta } from "@/hooks/useDocumentMeta";



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
                  khởi hành, ngày giờ kết thúc, số chỗ tối đa và số khách mục
                  tiêu riêng. Cùng một tour có thể có nhiều chuyến khởi hành
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
                <dt className="text-ink font-semibold">Số khách mục tiêu</dt>
                <dd className="text-body">
                  Mốc tham khảo để điều hành cân đối chuyến. Công ty vẫn tổ chức
                  cho khách đã thanh toán đủ đúng hạn dù chưa đạt số khách mục tiêu.
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
              khi chuyến còn chỗ và chưa tới hạn chốt danh sách, không bắt buộc
              phải tạo tài khoản. Sau khi đặt, hệ thống cấp một{" "}
              <Manh>mã tra cứu</Manh> gửi tới địa chỉ thư điện tử đã đăng ký; mã
              này dùng để xem đơn, khai danh sách hành khách và theo dõi tình
              trạng thanh toán.
            </Doan>

            <Doan>
              <Manh>4.2.</Manh> Đơn hàng được giữ chỗ tối đa{" "}
              <Manh>{data.booking.payment_ttl_minutes} phút</Manh> kể từ khi khởi
              tạo và không quá hạn chốt danh sách. Khách hàng cần hoàn tất khoản
              đặt cọc trong thời gian này; quá hạn chưa thanh toán thì đơn tự hủy.
            </Doan>

            <Doan>
              <Manh>4.3.</Manh> Chương trình bán theo chỗ được thanh toán làm{" "}
              <Manh>hai đợt</Manh>. Đợt một là khoản đặt cọc bằng{" "}
              <Manh>{data.payment.deposit_percent}% giá trị đơn hàng</Manh>, nộp
              trong thời hạn giữ chỗ nêu tại mục 4.2 và là điều kiện để chỗ được
              giữ chắc chắn. Đợt hai là phần còn lại, nộp{" "}
              <Manh>trước hạn chốt danh sách của chuyến</Manh>.
              Quy tắc này áp dụng cả khi đặt sát ngày đi.
            </Doan>

            <Doan>
              <Manh>4.4.</Manh> Công ty gửi thư nhắc thanh toán đợt hai{" "}
              tới địa chỉ đã đăng ký, kèm số tiền còn lại và liên kết thanh toán.
              Các mốc nhắc là {data.payment.reminder_days} ngày và{" "}
              {data.payment.final_notice_days} ngày trước hạn chốt; đơn đặt sát
              hạn có thể chỉ nhận lần nhắc cuối. Hạn thanh toán hiển thị trên đơn
              không thay đổi theo thời điểm nhận thư nhắc.
            </Doan>

            {/*
              Hậu quả của việc quá hạn phải nằm ngay đây, không đẩy xuống mục phí hủy.

              Khách đọc mục "thanh toán" là lúc họ quyết định có đặt hay không. Giấu điều khoản mất
              cọc xuống mục 5 nghĩa là họ chỉ gặp nó sau khi đã trả tiền.
            */}
            <Doan>
              <Manh>4.5.</Manh> Với đơn đặt tour theo chỗ đã thanh toán một phần,
              đến hạn chốt danh sách mà vẫn chưa trả đủ thì hệ thống hủy đơn và
              giữ lại khoản cọc <Manh>{data.payment.deposit_percent}% giá trị đơn</Manh>,
              tối đa bằng số tiền đã thu. Khoản đã trả vượt tiền cọc được ghi nhận
              để hoàn lại. Trường hợp này không áp dụng bảng phí khách chủ động hủy
              tại mục 5. Hạn thanh toán cụ thể hiển thị trên đơn hàng.
            </Doan>

            <Doan>
              <Manh>4.6.</Manh> Thanh toán trực tuyến qua cổng VNPay. Ngoài ra
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
              <Manh>4.7.</Manh> Khách hàng chịu trách nhiệm về tính chính xác của
              thông tin đã cung cấp. Công ty sử dụng thông tin này để làm thủ tục
              với các nhà cung cấp dịch vụ; nếu sai lệch dẫn tới phải điều chỉnh,
              khách hàng thanh toán các chi phí phát sinh (nếu có).
            </Doan>

            {/* --- 5. Hủy đơn theo yêu cầu của khách hàng --- */}
            <Muc>5. Hủy đơn hàng theo yêu cầu của khách hàng</Muc>

            <Doan>
              <Manh>5.1.</Manh> Mục này áp dụng đối với trường hợp khách hàng chủ
              động yêu cầu hủy đơn hàng. Đơn tự hủy vì chưa thanh toán đủ đúng hạn
              áp dụng mục 4.5. Trường hợp công ty hủy chuyến khởi hành được điều
              chỉnh tại mục 6 và không áp dụng biểu phí tại mục này.
            </Doan>

            <Doan>
              <Manh>5.2.</Manh> Phí hủy được xác định căn cứ vào số ngày còn lại
              tính đến giờ khởi hành ghi trên đơn hàng, theo giờ Việt Nam.
              Tỷ lệ dưới đây tính trên <Manh>tổng giá trị đơn</Manh>, không phải
              trên số tiền đã đặt cọc:
            </Doan>

            <div className="mt-5 overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-hairline">
                    <th className="text-caption-sm text-muted py-2.5 pr-6 font-normal tracking-wide uppercase">
                      Hủy trước ngày khởi hành
                    </th>
                    <th className="text-caption-sm text-muted py-2.5 font-normal tracking-wide uppercase">
                      Phí hủy trên giá trị đơn
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
                        {100 - bac.refund_percent}%
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

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
              <Manh>6.1.</Manh> Công ty <Manh>không hủy chuyến chỉ vì chưa đạt số
              khách mục tiêu</Manh>; vẫn tổ chức cho khách đã thanh toán đủ đúng hạn.
              Công ty có thể hủy khi xảy ra sự kiện bất khả kháng theo mục 1,
              hoặc nhà cung cấp không thực hiện được dịch vụ và không thu xếp
              được phương án thay thế phù hợp.
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
              khác. Công ty chỉ chuyển sau khi khách hàng đồng ý với phương án mới.
              Việc chuyển chuyến trong trường hợp này không thu phí đổi
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
              Công ty ưu tiên đề xuất ghép các chuyến chưa đạt số khách mục tiêu.
              Nếu không có chuyến phù hợp hoặc ghép xong vẫn dưới mục tiêu,
              công ty vẫn tổ chức cho khách đã thanh toán đủ đúng hạn.
              Chuyến nhận phải còn đủ sức chứa và cả hai chuyến chưa tới hạn chốt danh sách.
            </Doan>
            <Doan>
              Công ty gửi email nêu lịch trình đề xuất, hạn thanh toán phần còn lại
              và hạn phản hồi. <Manh>Chỉ chuyển khách sau khi khách đồng ý</Manh>;
              giá đơn giữ nguyên và không thu phí ghép chuyến.
              Chuyến nguồn được hủy ngay khi quyết định ghép. Nếu khách từ chối
              hoặc hết hạn chưa phản hồi, đơn được hủy và ghi nhận chờ hoàn đủ
              số tiền đã thu còn lại, không trừ phí hủy.
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
              <CauHoi hoi="Khi nào tôi phải thanh toán phần còn lại?">
                <p>
                  Với đơn đặt tour theo chỗ, bạn đặt cọc{" "}
                  <strong>{data.payment.deposit_percent}% giá trị đơn</strong> và
                  thanh toán phần còn lại <strong>trước hạn chốt danh sách của chuyến</strong>.
                  Hạn này mặc định là {data.booking.deadline_days} ngày trước giờ khởi hành;
                  nếu chuyến có hạn riêng, hãy theo ngày giờ hiển thị trên đơn.
                </p>
              </CauHoi>

              <CauHoi hoi="Đến hạn mà chưa thanh toán đủ thì tôi có mất cọc không?">
                <p>
                  Có. Với đơn đặt tour theo chỗ đã thanh toán một phần, đến hạn chốt
                  danh sách mà vẫn còn thiếu tiền thì hệ thống hủy đơn và giữ lại
                  khoản cọc <strong>{data.payment.deposit_percent}% giá trị đơn</strong>,
                  tối đa bằng số tiền đã thu. Phần đã trả vượt tiền cọc được ghi nhận
                  để hoàn lại.
                </p>
                <p>
                  Trường hợp này <strong>không áp dụng bảng phí hủy theo số ngày</strong> ở
                  mục 5. Nếu chưa thanh toán khoản nào, đơn hết hạn giữ chỗ sẽ tự hủy
                  và không có tiền cọc để khấu trừ.
                </p>
              </CauHoi>

              <CauHoi hoi="Nếu tôi chủ động hủy đơn thì được hoàn bao nhiêu?">
                <p>
                  Phí hủy áp dụng theo bảng ở mục 5, dựa trên thời điểm gửi yêu cầu
                  và bảng phí áp dụng cho đơn của bạn. Phí được tính trên{" "}
                  <strong>tổng giá trị đơn</strong>.
                </p>
                <p>
                  <strong>Tiền hoàn = số đã thanh toán − phí hủy</strong>, tối thiểu
                  bằng 0. Nếu bạn mới đóng cọc và phí hủy bằng hoặc lớn hơn khoản
                  cọc đó thì bạn không nhận lại tiền cọc và không phải nộp thêm.
                </p>
                <p>
                  Đơn đã thanh toán cần gửi yêu cầu và chờ điều hành duyệt hủy.
                  Trước khi gửi, bạn có thể xem số tiền hoàn dự kiến.
                </p>
              </CauHoi>

              <CauHoi hoi="Đơn đã hủy thì tiền hoàn có về ngay không?">
                <p>
                  Chưa. Hệ thống ghi nhận số tiền cần hoàn; điều hành xử lý hoàn tiền
                  và ghi nhận giao dịch sau đó. Nếu đơn có tiền được hoàn, bạn cần
                  cung cấp đúng thông tin tài khoản nhận tiền.
                </p>
              </CauHoi>

              <CauHoi hoi="Công ty hủy chuyến thì tôi có mất phí không?">
                <p>
                  Không. Nếu công ty hủy chuyến theo mục 6, bạn được{" "}
                  <strong>hoàn 100% số đã trả</strong>, không trừ bất kỳ khoản
                  nào, bất kể còn mấy ngày tới ngày đi. Bảng phí ở mục 5 chỉ áp
                  khi chính bạn là người hủy.
                </p>
              </CauHoi>

              <CauHoi hoi="Chuyến ít khách thì có bị hủy không?">
                <p>
                  Không hủy chỉ vì ít khách. Dù không ghép được chuyến hoặc ghép
                  xong vẫn chưa đạt số khách mục tiêu, công ty vẫn tổ chức cho
                  khách đã thanh toán đủ trước hạn chốt danh sách.
                </p>
              </CauHoi>

              <CauHoi hoi="Chuyến của tôi được đề xuất ghép, tôi có phải đồng ý không?">
                <p>
                  Không. Bạn nhận email và chọn đồng ý hoặc từ chối trên trang đơn
                  hàng. Khi ghép, chuyến ban đầu được hủy. Đồng ý thì chuyển sang
                  chuyến thay thế; từ chối hoặc hết hạn chưa phản hồi thì hủy đơn
                  và ghi nhận chờ hoàn đủ số tiền đã thu còn lại.
                </p>
              </CauHoi>

              <CauHoi hoi="Tôi đặt tour mà chưa có tài khoản thì tra cứu thế nào?">
                <p>
                  Sau khi đặt, hệ thống gửi <strong>mã tra cứu</strong> về email
                  bạn đã điền. Mở trang tra cứu đơn, nhập mã để xem đơn và theo
                  dõi thanh toán, không cần đăng nhập.
                </p>
                <p>
                  Để khai hoặc sửa thông tin hành khách, bạn xác thực bằng OTP
                  gửi tới email của đơn. Nếu đã đăng nhập đúng tài khoản sở hữu
                  đơn, bạn không cần xác thực OTP cho thao tác này.
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
                  Bạn có thể lưu phần đã khai và bổ sung trước <strong>hạn chốt danh sách</strong>.
                  Sau hạn này, vui lòng liên hệ điều hành. Nếu chuyến đã khởi hành,
                  báo HDV để điều hành bổ sung khách còn thiếu trong số suất đã đặt
                  và gửi bản cập nhật cho các nhà cung cấp liên quan.
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
