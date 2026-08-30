import { useCallback, useEffect, useState } from "react";
import adminService from "@/services/adminService";
import type { CancellationPolicyRule } from "@/services/adminService";
import { DateTimePicker } from "@/components/DateTimePicker";

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
  if (rule.max_days_before === null) return `Từ ${rule.min_days_before} ngày trở lên`;

  if (rule.min_days_before === 0) return `Dưới ${rule.max_days_before} ngày`;

  return `Từ ${rule.min_days_before} đến dưới ${rule.max_days_before} ngày`;
};

const bacTrong: CancellationPolicyRule = {
  min_days_before: 0,
  max_days_before: null,
  refund_percent: 0,
  note: "",
};

/**
 * "YYYY-MM-DD HH:mm:ss" của máy chủ sang "YYYY-MM-DDTHH:mm" mà ô datetime-local đọc được.
 *
 * Cắt chuỗi chứ không đi qua `new Date()`: máy chủ trả giờ Việt Nam dạng mộc, dựng Date từ nó là
 * để trình duyệt tự gán múi giờ rồi cộng trừ thêm - mốc 0h mùng 1 sẽ hiện thành 7h mùng 1.
 */
const sangOChonGio = (moc: string | null | undefined) =>
  moc ? moc.replace(" ", "T").slice(0, 16) : "";

/** Chiều ngược lại, gửi về máy chủ đúng dạng nó lưu. */
const sangChuoiMayChu = (oChon: string) =>
  oChon ? `${oChon.replace("T", " ")}:00` : "";

/** Mốc "bây giờ" theo đồng hồ trình duyệt, làm giá trị mặc định cho ô chọn. */
const bayGio = () => {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
};

export default function CancellationPolicyManagement() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [hieuLuc, setHieuLuc] = useState(bayGio());
  /** Mốc của bản đã lưu, để biết bản đang mở đã tới giờ chưa. Khác `hieuLuc` là ô đang gõ dở. */
  const [hieuLucDaLuu, setHieuLucDaLuu] = useState<string | null>(null);
  const [rules, setRules] = useState<CancellationPolicyRule[]>([]);

  const load = useCallback(async () => {
    setLoading(true);

    try {
      const policy = await adminService.getCancellationPolicy();

      if (policy) {
        setName(policy.name);
        setDescription(policy.description ?? "");
        setHieuLuc(sangOChonGio(policy.effective_from) || bayGio());
        setHieuLucDaLuu(policy.effective_from ?? null);
        // Xếp bậc xa nhất lên đầu, đúng chiều người ta đọc: hủy sớm thì hoàn nhiều.
        setRules(
          [...policy.rules].sort((a, b) => b.min_days_before - a.min_days_before),
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
      const luuXong = await adminService.updateCancellationPolicy({
        name: name.trim(),
        description: description.trim() || null,
        effective_from: sangChuoiMayChu(hieuLuc),
        rules: rules.map((r) => ({
          min_days_before: Number(r.min_days_before) || 0,
          max_days_before:
            r.max_days_before === null || String(r.max_days_before) === ""
              ? null
              : Number(r.max_days_before),
          refund_percent: Number(r.refund_percent) || 0,
          note: r.note?.trim() || null,
        })),
      });

      setNotice({
        type: "success",
        text: luuXong && new Date(hieuLuc) > new Date()
          ? `Đã hẹn. Bảng phí này áp dụng từ ${hieuLuc.replace("T", " ")}; từ giờ tới lúc đó vẫn chạy bảng cũ.`
          : "Đã lưu. Đơn đặt trước thời điểm này giữ nguyên điều khoản cũ.",
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
          Một bảng phí duy nhất, áp cho toàn bộ tour. Mức hoàn tính theo số ngày còn lại tới lúc
          khởi hành.
        </p>
      </div>

      {/*
        Bản đang mở đã tới giờ áp dụng chưa.

        Không nói ra thì người mở màn hình này tưởng thứ mình đang nhìn là thứ hệ thống đang tính
        tiền theo — trong khi nó có thể là bản hẹn cho tháng sau, còn bảng phí thật vẫn là bản cũ.
      */}
      {hieuLucDaLuu && new Date(hieuLucDaLuu.replace(" ", "T")) > new Date() && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          <strong>Bảng phí này chưa có hiệu lực.</strong> Nó bắt đầu áp dụng từ{" "}
          {hieuLucDaLuu}. Từ giờ tới lúc đó, đơn mới vẫn theo bảng phí trước đó.
        </div>
      )}

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

        {/*
          Mốc áp dụng.

          Để mặc định là bây giờ, tức bấm lưu là áp ngay — đó là việc hay làm nhất. Chọn một mốc
          xa hơn khi muốn công bố trước rồi mới áp, cách các công ty đổi điều khoản thật.
        */}
        {/* <div> chứ không <label>: bộ chọn là một nút mở bảng, bọc trong <label> thì bấm vào
            nhãn cũng mở bảng, và bấm ra ngoài để đóng lại vướng vào chính nhãn ấy. */}
        <div className="space-y-1.5">
          <span className="block text-xs font-semibold uppercase text-gray-500">
            Áp dụng từ{" "}
            <span className="normal-case font-normal text-gray-400">
              (giờ Việt Nam; để nguyên là áp dụng ngay)
            </span>
          </span>
          <DateTimePicker
            withTime
            minDate={new Date()}
            value={hieuLuc}
            onChange={setHieuLuc}
            placeholder="Chọn mốc hiệu lực"
            className="max-w-xs"
          />
          <span className="block text-[11px] text-gray-400">
            Không đặt được vào quá khứ: đơn đã bán đã chép bảng phí vào chính nó nên đặt lùi cũng
            không đổi được gì.
          </span>
        </div>

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
            Số ngày còn lại tới lúc khởi hành. Phải có một bậc bắt đầu từ 0 ngày, nếu không thì hủy
            sát ngày đi sẽ không rơi vào bậc nào.
          </p>
        </div>

        <table className="min-w-full divide-y divide-gray-100 text-sm">
          <thead className="bg-gray-50 text-left text-xs font-bold uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 w-40">Từ (ngày)</th>
              <th className="px-4 py-3 w-40">Đến (ngày)</th>
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
                    max={365}
                    value={rule.min_days_before}
                    onChange={(e) => suaBac(index, { min_days_before: Number(e.target.value) })}
                    className="w-full rounded border border-gray-200 px-2 py-1.5 text-sm"
                  />
                  <span className="mt-1 block text-[11px] text-gray-400">{nhanKhoang(rule)}</span>
                </td>
                <td className="px-4 py-2.5">
                  <input
                    type="number"
                    min={1}
                    max={365}
                    placeholder="Không giới hạn"
                    value={rule.max_days_before ?? ""}
                    onChange={(e) =>
                      suaBac(index, {
                        max_days_before: e.target.value === "" ? null : Number(e.target.value),
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
