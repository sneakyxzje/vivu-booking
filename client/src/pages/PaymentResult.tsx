import { Button, Card, Flex, Result, Typography } from "antd";
import { useNavigate, useSearchParams } from "react-router-dom";

export const PaymentResult = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const bookingId = searchParams.get("booking_id");
  const succeeded = searchParams.get("status") === "success";
  return <div className="min-h-[70vh] flex items-center justify-center px-4 py-10">
    <Card style={{ width: "100%", maxWidth: 640 }}>
      <Result status={succeeded ? "success" : "warning"}
        title={succeeded ? "Thanh toán thành công" : "Thanh toán chưa hoàn tất"}
        subTitle={succeeded ? "Đơn đặt tour của bạn đã được xác nhận." : "Giao dịch chưa hoàn tất. Bạn có thể tra cứu đơn để kiểm tra trạng thái và thanh toán lại."}
        extra={<Flex justify="center" gap="small" wrap>
          <Button type="primary" onClick={() => navigate("/booking-lookup")}>Tra cứu đơn</Button>
          <Button onClick={() => navigate("/tours")}>Xem tour khác</Button>
          <Button onClick={() => navigate("/")}>Về trang chủ</Button>
        </Flex>}>
        {bookingId && <Typography.Paragraph style={{ textAlign: "center" }}>Mã đơn: #{bookingId}</Typography.Paragraph>}
      </Result>
    </Card>
  </div>;
};

export default PaymentResult;
