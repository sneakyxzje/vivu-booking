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

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Sổ giao dịch</h1>
        <p className="mt-1 text-sm text-gray-500">
          Tiền vào, tiền ra, và hai chiều còn treo — cùng một nguồn số liệu.
        </p>
      </div>

      {/*
        Dải tình hình, hiện ở mọi tab.

        Đây là thứ trả lời câu "hôm nay đứng ở đâu" mà không phải bấm gì: còn phải đòi bao nhiêu,
        còn phải trả bao nhiêu, và mỗi bên bao nhiêu đơn.
      */}
      <div className="grid gap-3 sm:grid-cols-2">
        <button
          type="button"
          onClick={() => doiTab("receivables")}
          className={`rounded-xl border p-5 text-left transition-colors ${
            phaiThu.total > 0
              ? "border-amber-200 bg-amber-50 hover:bg-amber-100/70"
              : "border-gray-200 bg-white hover:bg-gray-50"
          }`}
        >
          <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-700">
            <ArrowDownLeft className="h-4 w-4" />
            Khách còn nợ công ty
          </p>
          <p className="mt-1 text-2xl font-bold tabular-nums text-amber-900">
            {formatPrice(phaiThu.total)}
          </p>
          <p className="mt-0.5 text-xs text-gray-500">{phaiThu.count} đơn chưa thu đủ</p>
        </button>

        <button
          type="button"
          onClick={() => doiTab("refunds")}
          className={`rounded-xl border p-5 text-left transition-colors ${
            phaiTra.total > 0
              ? "border-rose-200 bg-rose-50 hover:bg-rose-100/70"
              : "border-gray-200 bg-white hover:bg-gray-50"
          }`}
        >
          <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-rose-700">
            <ArrowUpRight className="h-4 w-4" />
            Công ty còn nợ khách
          </p>
          <p className="mt-1 text-2xl font-bold tabular-nums text-rose-900">
            {formatPrice(phaiTra.total)}
          </p>
          <p className="mt-0.5 text-xs text-gray-500">{phaiTra.count} đơn chờ hoàn</p>
        </button>
      </div>

      <div className="flex flex-wrap gap-1.5 border-b border-gray-200">
        {TABS.map(({ key, label, icon: Icon, hint }) => (
          <button
            key={key}
            type="button"
            onClick={() => doiTab(key)}
            title={hint}
            aria-current={tab === key ? "page" : undefined}
            className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors ${
              tab === key
                ? "border-primary-600 text-primary-700"
                : "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800"
            }`}
          >
            <Icon className="h-4 w-4" />
            {label}
          </button>
        ))}
      </div>

      {tab === "ledger" && <TransactionRegister />}
      {tab === "receivables" && <ReceivableManagement />}
      {tab === "refunds" && <RefundManagement />}
    </div>
  );
}
