import { useCallback, useEffect, useState } from "react";
import adminService from "@/services/adminService";
import type { CancellationPolicyRule } from "@/services/adminService";

/**
 * B06 - Chính sách hủy. **Một bảng phí duy nhất, áp cho mọi tour.**
 *
 * Trước đây màn này là danh sách nhiều chính sách, mỗi tour chọn một cái. Bỏ đi vì nó sinh ra
 * câu hỏi "cái nào áp cho đơn nào" ở mọi màn hình chạm tới tiền — mà công ty cỡ này không có lý
 * do nghiệp vụ nào để tour Hạ Long và tour Sapa hoàn tiền theo hai bảng khác nhau.
 *
 * Giữ nguyên một điều: **đơn chép bảng phí vào chính nó lúc đặt**. Sửa ở đây chỉ áp cho đơn đặt
 * từ lúc này trở đi; đơn đã bán giữ đúng điều khoản khách đã đồng ý. Câu đó in ngay trên màn hình
 * chứ không giấu trong tài liệu, vì nó là thứ người sửa cần biết trước khi bấm lưu.
 */

const nhanKhoang = (rule: CancellationPolicyRule) => {
  const tuNgay = Math.floor(rule.min_hours_before / 24);

  if (rule.max_hours_before === null) return `Từ ${tuNgay} ngày trở lên`;

  const denNgay = Math.floor(rule.max_hours_before / 24);

  if (rule.min_hours_before === 0) {
    return denNgay > 0 ? `Dưới ${denNgay} ngày` : `Dưới ${rule.max_hours_before} giờ`;
  }

  return `Từ ${tuNgay} đến dưới ${denNgay} ngày`;
};

const bacTrong: CancellationPolicyRule = {
  min_hours_before: 0,
  max_hours_before: null,
  refund_percent: 0,
  note: "",
};

export default function CancellationPolicyManagement() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [rules, setRules] = useState<CancellationPolicyRule[]>([]);

  const load = useCallback(async () => {
    setLoading(true);

    try {
      const policy = await adminService.getCancellationPolicy();

      if (policy) {
        setName(policy.name);
        setDescription(policy.description ?? "");
        // Xếp bậc xa nhất lên đầu, đúng chiều người ta đọc: hủy sớm thì hoàn nhiều.
        setRules(
          [...policy.rules].sort((a, b) => b.min_hours_before - a.min_hours_before),
        );
      }
    } catch {
      setNotice({ type: "error", text: "Không tải được chính sách hủy." });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const suaBac = (index: number, thayDoi: Partial<CancellationPolicyRule>) =>
    setRules((truoc) => truoc.map((r, i) => (i === index ? { ...r, ...thayDoi } : r)));

  const luu = async () => {
    setSaving(true);
    setNotice(null);

    try {
      await adminService.updateCancellationPolicy({
        name: name.trim(),
        description: description.trim() || null,
        rules: rules.map((r) => ({
          min_hours_before: Number(r.min_hours_before) || 0,
          max_hours_before:
            r.max_hours_before === null || String(r.max_hours_before) === ""
              ? null
              : Number(r.max_hours_before),
          refund_percent: Number(r.refund_percent) || 0,
          note: r.note?.trim() || null,
        })),
      });

      setNotice({
        type: "success",
        text: "Đã lưu. Đơn đặt trước thời điểm này giữ nguyên điều khoản cũ.",
      });

      load();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setNotice({ type: "error", text: response?.message || "Không lưu được chính sách." });
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <p className="py-16 text-center text-sm text-gray-500">Đang tải chính sách hủy...</p>;
  }

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-950">Chính sách hủy</h1>
        <p className="mt-1 text-sm text-gray-500">
          Một bảng phí duy nhất, áp cho toàn bộ tour. Mức hoàn tính theo số giờ còn lại tính tới
          giờ khởi hành.
        </p>
      </div>

      {/*
        Câu quan trọng nhất của màn hình này, nên nó đứng trên cùng chứ không nằm cuối trang:
        sửa bảng phí KHÔNG đổi điều khoản của đơn đã bán.
      */}
      <div className="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <strong>Sửa ở đây chỉ áp cho đơn đặt từ lúc này trở đi.</strong> Mỗi đơn đã chép bảng phí
        vào chính nó tại thời điểm khách đặt, nên khách vẫn được hưởng đúng điều khoản họ đã đồng ý.
      </div>

      {notice && (
        <div
          className={`rounded-xl px-4 py-3 text-sm font-medium ${
            notice.type === "success"
              ? "border border-emerald-200 bg-emerald-50 text-emerald-800"
              : "border border-rose-200 bg-rose-50 text-rose-700"
          }`}
        >
          {notice.text}
        </div>
      )}

      <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <label className="block space-y-1.5">
          <span className="text-xs font-semibold uppercase text-gray-500">Tên chính sách</span>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
        </label>

        <label className="block space-y-1.5">
          <span className="text-xs font-semibold uppercase text-gray-500">
            Diễn giải <span className="normal-case font-normal text-gray-400">(khách đọc được)</span>
          </span>
          <textarea
            rows={3}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
        </label>
      </div>

      <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div className="border-b border-gray-100 px-5 py-3">
          <h2 className="text-sm font-bold text-gray-900">Các bậc phí</h2>
          <p className="mt-0.5 text-xs text-gray-500">
            Phải có một bậc bắt đầu từ 0 giờ, nếu không thì hủy sát ngày đi sẽ không rơi vào bậc
            nào.
          </p>
        </div>

        <table className="min-w-full divide-y divide-gray-100 text-sm">
          <thead className="bg-gray-50 text-left text-xs font-bold uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 w-40">Từ (giờ)</th>
              <th className="px-4 py-3 w-40">Đến (giờ)</th>
              <th className="px-4 py-3 w-32">Hoàn (%)</th>
              <th className="px-4 py-3">Ghi chú</th>
              <th className="px-4 py-3 w-20"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {rules.map((rule, index) => (
              <tr key={index}>
                <td className="px-4 py-2.5">
                  <input
                    type="number"
                    min={0}
                    value={rule.min_hours_before}
                    onChange={(e) => suaBac(index, { min_hours_before: Number(e.target.value) })}
                    className="w-full rounded border border-gray-200 px-2 py-1.5 text-sm"
                  />
                  <span className="mt-1 block text-[11px] text-gray-400">{nhanKhoang(rule)}</span>
                </td>
                <td className="px-4 py-2.5">
                  <input
                    type="number"
                    min={1}
                    placeholder="Không giới hạn"
                    value={rule.max_hours_before ?? ""}
                    onChange={(e) =>
                      suaBac(index, {
                        max_hours_before: e.target.value === "" ? null : Number(e.target.value),
                      })
                    }
                    className="w-full rounded border border-gray-200 px-2 py-1.5 text-sm"
                  />
                </td>
                <td className="px-4 py-2.5">
                  <input
                    type="number"
                    min={0}
                    max={100}
                    value={rule.refund_percent}
                    onChange={(e) => suaBac(index, { refund_percent: Number(e.target.value) })}
                    className="w-full rounded border border-gray-200 px-2 py-1.5 text-sm font-bold"
                  />
                </td>
                <td className="px-4 py-2.5">
                  <input
                    value={rule.note ?? ""}
                    onChange={(e) => suaBac(index, { note: e.target.value })}
                    placeholder="VD: Khách sạn chưa chốt phòng"
                    className="w-full rounded border border-gray-200 px-2 py-1.5 text-sm"
                  />
                </td>
                <td className="px-4 py-2.5 text-right">
                  <button
                    type="button"
                    onClick={() => setRules((truoc) => truoc.filter((_, i) => i !== index))}
                    className="rounded px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                  >
                    Xóa
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="border-t border-gray-100 px-5 py-3">
          <button
            type="button"
            onClick={() => setRules((truoc) => [...truoc, { ...bacTrong }])}
            className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-50"
          >
            Thêm bậc
          </button>
        </div>
      </div>

      <div className="flex justify-end gap-2">
        <button
          type="button"
          onClick={load}
          disabled={saving}
          className="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          Hoàn tác
        </button>
        <button
          type="button"
          onClick={luu}
          disabled={saving}
          className="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
        >
          {saving ? "Đang lưu..." : "Lưu chính sách"}
        </button>
      </div>
    </div>
  );
}
