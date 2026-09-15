import { Flex as AntFlex, Typography as AntTypography, Button, Card, Col, Row, Statistic, Tabs } from "antd";
import { useCallback, useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { ArrowDownLeft, ArrowUpRight, BookOpen } from "lucide-react";
import adminService from "@/services/adminService";
import { formatPrice } from "@/utils/format";
import TransactionRegister from "./TransactionRegister";
import ReceivableManagement from "./ReceivableManagement";
import RefundManagement from "./RefundManagement";

/**
 * Một cửa duy nhất cho mọi câu hỏi về tiền.
 *
 * Ba màn này vốn nằm rời ở ba mục menu, và người dùng phải nhớ vào đâu để hỏi gì. Nhưng chúng đọc
 * chung một sổ giao dịch — chỉ khác góc nhìn:
 *
 *   - **Sổ giao dịch** — chuyện đã xảy ra: từng đồng vào và ra, xếp theo thời gian.
 *   - **Phải thu** — chuyện chưa xong theo chiều khách nợ công ty.
 *   - **Phải trả** — chuyện chưa xong theo chiều công ty nợ khách.
 *
 * Gộp lại còn cho một thứ mà tách ra không có: dải số ở đầu trang luôn hiện **cả hai chiều còn
 * treo** cùng lúc, nên mở màn là nắm được tình hình mà chưa cần bấm tab nào.
 *
 * Tab lưu trong địa chỉ (`?tab=`) để gửi liên kết cho kế toán mở đúng chỗ, và để bấm nút quay lại
 * của trình duyệt không văng ra khỏi trang.
 */

type TabKey = "ledger" | "receivables" | "refunds";

const TABS: { key: TabKey; label: string; icon: typeof BookOpen; hint: string }[] = [
  { key: "ledger", label: "Sổ giao dịch", icon: BookOpen, hint: "Tiền đã vào và đã ra" },
  { key: "receivables", label: "Phải thu", icon: ArrowDownLeft, hint: "Khách còn nợ công ty" },
  { key: "refunds", label: "Phải trả", icon: ArrowUpRight, hint: "Công ty còn nợ khách" },
];

export default function FinanceHub() {
  const [searchParams, setSearchParams] = useSearchParams();
  const tabHienTai = (searchParams.get("tab") as TabKey) || "ledger";
  const tab: TabKey = TABS.some((t) => t.key === tabHienTai) ? tabHienTai : "ledger";

  /*
   * Hai con số treo, nạp riêng ở đây.
   *
   * Chúng KHÔNG theo bộ lọc ngày của tab sổ: "công ty đang nợ khách bao nhiêu" là câu hỏi về hiện
   * tại, không phải về một khoảng thời gian. Lọc chúng theo tháng đang xem sẽ ra một con số trông
   * hợp lý mà vô nghĩa.
   */
  const [phaiThu, setPhaiThu] = useState({ total: 0, count: 0 });
  const [phaiTra, setPhaiTra] = useState({ total: 0, count: 0 });

  const napSoTreo = useCallback(async () => {
    try {
      const [thu, tra] = await Promise.all([
        adminService.getReceivables(),
        adminService.getRefundQueue(false),
      ]);

      setPhaiThu({
        total: thu?.outstanding_total ?? 0,
        count: thu?.total ?? 0,
      });
      setPhaiTra({
        total: tra?.outstanding_total ?? 0,
        count: tra?.data?.length ?? 0,
      });
    } catch (err) {
      console.error("Không nạp được số dư treo:", err);
    }
  }, []);

  useEffect(() => {
    napSoTreo();
  }, [napSoTreo, tab]);

  const doiTab = (key: TabKey) => {
    // `replace` để bấm quay lại không phải lùi qua từng tab đã xem.
    setSearchParams(key === "ledger" ? {} : { tab: key }, { replace: true });
  };

  return <AntFlex vertical gap="large">
    <div><AntTypography.Title level={3}>Sổ giao dịch</AntTypography.Title><AntTypography.Text type="secondary">Tiền vào, tiền ra và các khoản còn phải thu, phải trả.</AntTypography.Text></div>
    <Row gutter={[16, 16]}>
      <Col xs={24} md={12}><Card><Statistic title="Khách còn nợ công ty" value={phaiThu.total} formatter={(value) => formatPrice(Number(value))} />
        <AntFlex justify="space-between" align="center" wrap gap="small"><AntTypography.Text type="secondary">{phaiThu.count} đơn chưa thu đủ</AntTypography.Text><Button onClick={() => doiTab("receivables")}>Xem khoản phải thu</Button></AntFlex>
      </Card></Col>
      <Col xs={24} md={12}><Card><Statistic title="Công ty còn nợ khách" value={phaiTra.total} formatter={(value) => formatPrice(Number(value))} />
        <AntFlex justify="space-between" align="center" wrap gap="small"><AntTypography.Text type="secondary">{phaiTra.count} đơn chờ hoàn</AntTypography.Text><Button onClick={() => doiTab("refunds")}>Xem khoản phải trả</Button></AntFlex>
      </Card></Col>
    </Row>
    <Tabs activeKey={tab} onChange={(key) => doiTab(key as TabKey)} destroyOnHidden items={TABS.map(({ key, label, icon: Icon, hint }) => ({
      key, label, icon: <Icon size={16} />, children: <AntFlex vertical gap="middle"><AntTypography.Text type="secondary">{hint}</AntTypography.Text>
        {key === "ledger" ? <TransactionRegister /> : key === "receivables" ? <ReceivableManagement /> : <RefundManagement onChanged={napSoTreo} />}
      </AntFlex>,
    }))} />
  </AntFlex>;
}
