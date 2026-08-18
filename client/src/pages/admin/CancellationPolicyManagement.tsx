import { useCallback, useEffect, useState } from "react";
import adminService from "@/services/adminService";
import type {
  CancellationPolicy,
  CancellationPolicyRule,
} from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";

/**
 * B06 - Quản lý chính sách hủy.
 *
 * Mỗi chính sách là một bảng phí gồm nhiều bậc theo số giờ còn lại tới khởi hành.
 * Tour trỏ tới chính sách; đơn hàng sao chép chính sách tại thời điểm đặt, nên sửa ở đây
 * không hồi tố lên đơn đã có.
 */

const BAC_MAC_DINH: CancellationPolicyRule[] = [
  { min_hours_before: 360, max_hours_before: null, refund_percent: 90 },
  { min_hours_before: 192, max_hours_before: 360, refund_percent: 70 },
  { min_hours_before: 96, max_hours_before: 192, refund_percent: 50 },
  { min_hours_before: 48, max_hours_before: 96, refund_percent: 30 },
  { min_hours_before: 0, max_hours_before: 48, refund_percent: 0 },
];

const nhanKhoang = (rule: CancellationPolicyRule) => {
  const tuNgay = Math.floor(rule.min_hours_before / 24);

  if (rule.max_hours_before === null) return `Từ ${tuNgay} ngày trở lên`;

  const denNgay = Math.floor(rule.max_hours_before / 24);

  if (rule.min_hours_before === 0) {
    return denNgay > 0 ? `Dưới ${denNgay} ngày` : `Dưới ${rule.max_hours_before} giờ`;
  }

  return `Từ ${tuNgay} đến dưới ${denNgay} ngày`;
};

export default function CancellationPolicyManagement() {
  const [policies, setPolicies] = useState<CancellationPolicy[]>([]);
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [isDefault, setIsDefault] = useState(false);
  const [rules, setRules] = useState<CancellationPolicyRule[]>(BAC_MAC_DINH);
  const [formError, setFormError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setPolicies(await adminService.getCancellationPolicies());
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openCreateModal = () => {
    setEditingId(null);
    setName("");
    setDescription("");
    setIsDefault(false);
    setRules(BAC_MAC_DINH);
    setFormError("");
    setIsModalOpen(true);
  };

  const openEditModal = (policy: CancellationPolicy) => {
    setEditingId(policy.id);
    setName(policy.name);
    setDescription(policy.description ?? "");
    setIsDefault(policy.is_default);
    setRules(
      policy.rules.length > 0
        ? policy.rules.map((r) => ({ ...r }))
        : BAC_MAC_DINH.map((r) => ({ ...r })),
    );
    setFormError("");
    setIsModalOpen(true);
  };

  const updateRule = (index: number, field: keyof CancellationPolicyRule, value: string) => {
    setRules((current) =>
      current.map((rule, i) => {
        if (i !== index) return rule;

        if (field === "max_hours_before") {
          return { ...rule, max_hours_before: value === "" ? null : Number(value) };
        }

        return { ...rule, [field]: Number(value) };
      }),
    );
  };

  const handleSubmit = async () => {
    setFormError("");

    if (!name.trim()) {
      setFormError("Vui lòng nhập tên chính sách.");
      return;
    }

    if (!rules.some((rule) => rule.min_hours_before === 0)) {
      setFormError(
        "Phải có một bậc bắt đầu từ 0 giờ để phủ trường hợp hủy sát ngày khởi hành.",
      );
      return;
    }

    setSubmitting(true);
    try {
      const payload = { name: name.trim(), description, is_default: isDefault, rules };
      const saved = editingId
        ? await adminService.updateCancellationPolicy(editingId, payload)
        : await adminService.createCancellationPolicy(payload);

      if (!saved) {
        setFormError("Không lưu được chính sách. Kiểm tra lại các bậc phí.");
        return;
      }

      setIsModalOpen(false);
      setNotice({
        type: "success",
        text: editingId ? "Đã cập nhật chính sách hủy." : "Đã tạo chính sách hủy.",
      });
      await load();
    } catch {
      setFormError("Không lưu được chính sách. Kiểm tra lại các bậc phí.");
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (policy: CancellationPolicy) => {
    try {
      const ok = await adminService.deleteCancellationPolicy(policy.id);
      setNotice(
        ok
          ? { type: "success", text: "Đã xóa chính sách hủy." }
          : { type: "error", text: "Không xóa được chính sách này." },
      );
      if (ok) await load();
    } catch {
      setNotice({
        type: "error",
        text: "Không xóa được. Chính sách đang được tour hoặc đơn đặt tour sử dụng.",
      });
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">Chính sách hủy</h1>
          {/*
            Nói ngay cách chọn chính sách hoạt động ra sao.

            Có nhiều chính sách mà không biết cái nào áp cho tour nào là câu hỏi đầu tiên người
            dùng gặp ở màn này. Trả lời ngay dưới tiêu đề, thay vì để họ tự suy ra từ các thẻ.
          */}
          <p className="mt-1 text-sm text-gray-500 max-w-2xl">
            Phí hủy theo mốc thời gian còn lại tới khởi hành. Mỗi tour <b>chọn một chính sách
            riêng</b> ở biểu mẫu tạo/sửa tour; tour nào không chọn thì dùng chính sách đang đánh
            dấu <b>mặc định</b>. Đơn đặt tour sao chép chính sách tại thời điểm đặt, nên sửa ở đây
            không đổi điều khoản của đơn đã bán.
          </p>
        </div>
        <button
          type="button"
          onClick={openCreateModal}
          className="cursor-pointer rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-primary-700"
        >
          Thêm chính sách
        </button>
      </div>

      {notice && (
        <div
          className={`rounded-lg border p-3 text-sm font-semibold ${
            notice.type === "success"
              ? "border-emerald-200 bg-emerald-50 text-emerald-800"
              : "border-rose-200 bg-rose-50 text-rose-800"
          }`}
        >
          {notice.text}
        </div>
      )}

      {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

      {!loading && policies.length === 0 && (
        <div className="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
          Chưa có chính sách hủy nào.
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        {policies.map((policy) => (
          <div key={policy.id} className="rounded-xl border border-gray-200 bg-white p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="font-bold text-gray-950">{policy.name}</h2>
                  {policy.is_default && (
                    <span className="rounded border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary-700">
                      Mặc định
                    </span>
                  )}
                </div>
                {/*
                  Nói rõ chính sách này đang chi phối cái gì.

                  Trước đây mọi thẻ đều hiện "0 tour đang sử dụng" — vì biểu mẫu tour chưa bao
                  giờ gửi `cancellation_policy_id` lên, nên không tour nào chọn được chính sách
                  riêng và cái mặc định âm thầm gánh hết. Nhìn vào không ai biết cái nào dùng ở
                  đâu. Nay gán được rồi thì con số này mới có nghĩa, và cái mặc định phải tự nói
                  ra vai trò của nó.
                */}
                <p className="mt-1 text-xs text-gray-500">
                  {policy.tours_count ?? 0} tour chọn riêng chính sách này
                  {policy.is_default && " · áp cho mọi tour chưa chọn riêng"}
                </p>
              </div>
              <div className="flex shrink-0 gap-2">
                <button
                  type="button"
                  onClick={() => openEditModal(policy)}
                  className="cursor-pointer rounded border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                >
                  Sửa
                </button>
                <button
                  type="button"
                  disabled={policy.is_default || (policy.tours_count ?? 0) > 0}
                  onClick={() => handleDelete(policy)}
                  className="cursor-pointer rounded border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Xóa
                </button>
              </div>
            </div>

            {policy.description && (
              <p className="mt-3 text-xs leading-relaxed text-gray-600">{policy.description}</p>
            )}

            <div className="mt-4 overflow-hidden rounded-lg border border-gray-200">
              <table className="w-full text-xs">
                <tbody className="divide-y divide-gray-100">
                  {policy.rules.map((rule, index) => (
                    <tr key={index} className="text-gray-600">
                      <td className="px-3 py-2">{nhanKhoang(rule)}</td>
                      <td className="px-3 py-2 text-right font-bold text-gray-800">
                        Hoàn {rule.refund_percent}%
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ))}
      </div>

      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingId ? "Sửa chính sách hủy" : "Thêm chính sách hủy"}
        onSubmit={handleSubmit}
      >
        <div className="space-y-4">
          {formError && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">
              {formError}
            </div>
          )}

          <div>
            <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-gray-500">
              Tên chính sách
            </label>
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
              placeholder="Chính sách hủy tiêu chuẩn"
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-gray-500">
              Mô tả
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
              placeholder="Vì sao phí tăng dần khi càng sát ngày khởi hành"
            />
          </div>

          <label className="flex cursor-pointer items-center gap-2 text-sm font-semibold text-gray-700">
            <input
              type="checkbox"
              checked={isDefault}
              onChange={(e) => setIsDefault(e.target.checked)}
              className="h-4 w-4 cursor-pointer"
            />
            Đặt làm chính sách mặc định
          </label>

          <div>
            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">
              Các bậc phí, tính bằng giờ còn lại tới khởi hành
            </p>
            <div className="space-y-2">
              {rules.map((rule, index) => (
                <div key={index} className="flex items-center gap-2">
                  <input
                    type="number"
                    min={0}
                    value={rule.min_hours_before}
                    onChange={(e) => updateRule(index, "min_hours_before", e.target.value)}
                    className="w-24 rounded border border-gray-200 px-2 py-1.5 text-sm"
                    title="Từ bao nhiêu giờ"
                  />
                  <span className="text-xs text-gray-400">đến</span>
                  <input
                    type="number"
                    min={1}
                    value={rule.max_hours_before ?? ""}
                    onChange={(e) => updateRule(index, "max_hours_before", e.target.value)}
                    className="w-24 rounded border border-gray-200 px-2 py-1.5 text-sm"
                    placeholder="không giới hạn"
                    title="Đến bao nhiêu giờ, để trống nếu là bậc xa nhất"
                  />
                  <span className="text-xs text-gray-400">giờ, hoàn</span>
                  <input
                    type="number"
                    min={0}
                    max={100}
                    value={rule.refund_percent}
                    onChange={(e) => updateRule(index, "refund_percent", e.target.value)}
                    className="w-20 rounded border border-gray-200 px-2 py-1.5 text-sm"
                  />
                  <span className="text-xs text-gray-400">%</span>
                  <button
                    type="button"
                    onClick={() => setRules((c) => c.filter((_, i) => i !== index))}
                    className="ml-auto cursor-pointer rounded border border-gray-200 px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-gray-50"
                  >
                    Bỏ
                  </button>
                </div>
              ))}
            </div>

            <button
              type="button"
              onClick={() =>
                setRules((c) => [
                  ...c,
                  { min_hours_before: 0, max_hours_before: null, refund_percent: 0 },
                ])
              }
              className="mt-2 cursor-pointer rounded border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50"
            >
              Thêm bậc
            </button>

            <p className="mt-2 text-[11px] leading-relaxed text-gray-500">
              Phải có một bậc bắt đầu từ 0 giờ, nếu không thì hủy sát ngày đi sẽ không rơi vào
              bậc nào và hệ thống lặng lẽ hoàn 0 phần trăm mà không có căn cứ.
            </p>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={() => setIsModalOpen(false)}
              className="cursor-pointer rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
            >
              Hủy
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="cursor-pointer rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-primary-700 disabled:opacity-50"
            >
              {submitting ? "Đang lưu..." : "Lưu"}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
