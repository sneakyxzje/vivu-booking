import React, { useEffect, useMemo, useState } from "react";
import adminService from "@/services/adminService";
import { TableActions } from "@/components/admin/TableActions";
import { Pencil, Trash2 } from "lucide-react";
import type { DiscountCode, DiscountCodePayload } from "@/types/discount";
import { DateTimePicker } from "@/components/DateTimePicker";
import { doiSangNgay } from "@/components/date/dateHelpers";

const emptyForm: DiscountCodePayload = {
  code: "",
  name: "",
  type: "percent",
  value: 10,
  minimum_order_amount: 0,
  max_discount_amount: null,
  usage_limit: null,
  starts_at: null,
  expires_at: null,
  is_active: true,
};

const formatCurrency = (value: number | null) =>
  value === null ? "--" : `${Number(value).toLocaleString("vi-VN")}đ`;

const formatDateInput = (value: string | null) => value?.slice(0, 10) ?? "";

export default function DiscountCodeManagement() {
  const [codes, setCodes] = useState<DiscountCode[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<DiscountCodePayload>(emptyForm);

  const activeCount = useMemo(() => codes.filter((item) => item.is_active).length, [codes]);
  const usedCount = useMemo(() => codes.reduce((sum, item) => sum + Number(item.used_count), 0), [codes]);

  const loadCodes = async () => {
    setLoading(true);
    try {
      const response = await adminService.getDiscountCodes();
      setCodes(response?.data ?? []);
    } catch {
      setMessage("Không thể tải danh sách mã giảm giá.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCodes();
  }, []);

  const updateForm = (field: keyof DiscountCodePayload, value: string | number | boolean | null) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const updateDiscountType = (type: DiscountCodePayload["type"]) => {
    setForm((current) => ({
      ...current,
      type,
      max_discount_amount: type === "percent" ? current.max_discount_amount : null,
    }));
  };

  const resetForm = () => {
    setEditingId(null);
    setForm(emptyForm);
    setMessage(null);
  };

  const normalizePayload = (): DiscountCodePayload => ({
    ...form,
    code: form.code.trim().toUpperCase(),
    name: form.name.trim(),
    value: Number(form.value),
    minimum_order_amount: Number(form.minimum_order_amount || 0),
    max_discount_amount: form.type === "percent" && form.max_discount_amount !== null
      ? Number(form.max_discount_amount)
      : null,
    usage_limit: form.usage_limit === null
      ? null
      : Number(form.usage_limit),
    starts_at: form.starts_at || null,
    expires_at: form.expires_at || null,
  });

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setMessage(null);

    try {
      const payload = normalizePayload();
      if (editingId) {
        await adminService.updateDiscountCode(editingId, payload);
        setMessage("Đã cập nhật mã giảm giá.");
      } else {
        await adminService.createDiscountCode(payload);
        setMessage("Đã tạo mã giảm giá.");
      }
      resetForm();
      await loadCodes();
    } catch (error: any) {
      const response = error?.response?.data;
      const firstError = response?.errors ? Object.values(response.errors).flat()[0] : null;
      setMessage(String(firstError ?? response?.message ?? "Không thể lưu mã giảm giá."));
    } finally {
      setSubmitting(false);
    }
  };

  const startEdit = (item: DiscountCode) => {
    setEditingId(item.id);
    setForm({
      code: item.code,
      name: item.name,
      type: item.type,
      value: item.value,
      minimum_order_amount: item.minimum_order_amount,
      max_discount_amount: item.max_discount_amount,
      usage_limit: item.usage_limit,
      starts_at: formatDateInput(item.starts_at),
      expires_at: formatDateInput(item.expires_at),
      is_active: item.is_active,
    });
    setMessage(null);
  };

  const removeCode = async (id: number) => {
    if (!window.confirm("Xóa mã giảm giá này?")) return;
    await adminService.deleteDiscountCode(id);
    await loadCodes();
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">Quản lý mã giảm giá</h1>
          <p className="mt-1 text-sm text-gray-500">Tạo và theo dõi mã giảm giá khách hàng có thể áp dụng khi đặt tour.</p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Tổng mã</p>
          <p className="mt-2 text-2xl font-bold text-gray-900">{codes.length}</p>
        </div>
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Đang hoạt động</p>
          <p className="mt-2 text-2xl font-bold text-emerald-600">{activeCount}</p>
        </div>
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Lượt đã dùng</p>
          <p className="mt-2 text-2xl font-bold text-primary-600">{usedCount}</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-bold text-gray-950">{editingId ? "Cập nhật mã" : "Tạo mã mới"}</h2>
          {editingId && (
            <button type="button" onClick={resetForm} className="text-sm font-semibold text-gray-500 hover:text-gray-800">Hủy sửa</button>
          )}
        </div>

        <div className="grid gap-4 md:grid-cols-4">
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">Mã giảm giá</span>
            <input required placeholder="VD: SUMMER20" value={form.code} onChange={(e) => updateForm("code", e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
          </label>
          <label className="space-y-1.5 md:col-span-2">
            <span className="text-xs font-semibold uppercase text-gray-500">Tên chương trình</span>
            <input required placeholder="VD: Ưu đãi hè" value={form.name} onChange={(e) => updateForm("name", e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
          </label>
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">Loại giảm</span>
            <select value={form.type} onChange={(e) => updateDiscountType(e.target.value as DiscountCodePayload["type"])} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500">
              <option value="percent">Giảm theo %</option>
              <option value="fixed">Giảm số tiền</option>
            </select>
          </label>
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">{form.type === "percent" ? "Phần trăm giảm" : "Số tiền giảm"}</span>
            <input required type="number" min="0" step="0.01" placeholder={form.type === "percent" ? "VD: 10" : "VD: 50000"} value={form.value} onChange={(e) => updateForm("value", Number(e.target.value))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
          </label>
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">Đơn tối thiểu</span>
            <input type="number" min="0" placeholder="VD: 1000000" value={form.minimum_order_amount} onChange={(e) => updateForm("minimum_order_amount", Number(e.target.value))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
          </label>
          {form.type === "percent" && (
            <label className="space-y-1.5">
              <span className="text-xs font-semibold uppercase text-gray-500">Giảm tối đa</span>
              <input type="number" min="0" placeholder="VD: 200000" value={form.max_discount_amount ?? ""} onChange={(e) => updateForm("max_discount_amount", e.target.value ? Number(e.target.value) : null)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
            </label>
          )}
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">Giới hạn lượt dùng</span>
            <input type="number" min="1" placeholder="VD: 100" value={form.usage_limit ?? ""} onChange={(e) => updateForm("usage_limit", e.target.value ? Number(e.target.value) : null)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
          </label>
          {/*
            Hai mốc hiệu lực của mã, không phải một bộ lọc — nên dùng bộ chọn một ngày, không dùng
            bộ chọn khoảng: mốc dựng sẵn kiểu "tháng trước" vô nghĩa với một mã sắp phát hành.
            `minDate` của ngày kết thúc bám theo ngày bắt đầu, chặn ngay trên lịch.
          */}
          <div className="space-y-1.5">
            <span className="block text-xs font-semibold uppercase text-gray-500">Ngày bắt đầu</span>
            <DateTimePicker
              value={formatDateInput(form.starts_at)}
              onChange={(giaTri) => updateForm("starts_at", giaTri || null)}
              placeholder="Chọn ngày bắt đầu"
            />
          </div>
          <div className="space-y-1.5">
            <span className="block text-xs font-semibold uppercase text-gray-500">Ngày kết thúc</span>
            {/*
              `minDate` dựng bằng `doiSangNgay`, không bằng `new Date("2026-08-30")`: chuỗi chỉ có
              ngày được JavaScript hiểu là 0 giờ UTC, nên ở múi giờ âm nó lùi mất một ngày. Hàm
              kia đọc bằng thành phần địa phương.
            */}
            <DateTimePicker
              value={formatDateInput(form.expires_at)}
              minDate={doiSangNgay(formatDateInput(form.starts_at)) ?? undefined}
              onChange={(giaTri) => updateForm("expires_at", giaTri || null)}
              placeholder="Chọn ngày kết thúc"
            />
          </div>
          <label className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700">
            <input type="checkbox" checked={form.is_active} onChange={(e) => updateForm("is_active", e.target.checked)} />
            Kích hoạt
          </label>
        </div>

        {message && <p className="mt-4 rounded-lg bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700">{message}</p>}

        <button disabled={submitting} className="mt-4 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
          {submitting ? "Đang lưu..." : editingId ? "Cập nhật mã" : "Tạo mã giảm giá"}
        </button>
      </form>

      <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-gray-100 text-sm">
          <thead className="bg-gray-50 text-left text-xs font-bold uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3">Mã</th>
              <th className="px-4 py-3">Giá trị</th>
              <th className="px-4 py-3">Điều kiện</th>
              <th className="px-4 py-3">Lượt dùng</th>
              <th className="px-4 py-3">Trạng thái</th>
              <th className="px-4 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {loading ? (
              <tr><td className="px-4 py-6 text-center text-gray-500" colSpan={6}>Đang tải...</td></tr>
            ) : codes.length === 0 ? (
              <tr><td className="px-4 py-6 text-center text-gray-500" colSpan={6}>Chưa có mã giảm giá.</td></tr>
            ) : codes.map((item) => (
              <tr key={item.id}>
                <td className="px-4 py-3">
                  <p className="font-bold text-gray-900">{item.code}</p>
                  <p className="text-xs text-gray-500">{item.name}</p>
                </td>
                <td className="px-4 py-3 font-semibold text-gray-800">
                  {item.type === "percent" ? `${item.value}%` : formatCurrency(item.value)}
                  {item.max_discount_amount !== null && <p className="text-xs font-normal text-gray-500">Tối đa {formatCurrency(item.max_discount_amount)}</p>}
                </td>
                <td className="px-4 py-3 text-gray-600">Từ {formatCurrency(item.minimum_order_amount)}</td>
                <td className="px-4 py-3 text-gray-600">{item.used_count}{item.usage_limit ? ` / ${item.usage_limit}` : ""}</td>
                <td className="px-4 py-3">
                  <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${item.is_active ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-500"}`}>
                    {item.is_active ? "Hoạt động" : "Tạm tắt"}
                  </span>
                </td>
                <td className="px-4 py-3 text-right">
                  <TableActions
                    id={item.id}
                    label="Thao tác mã giảm giá"
                    actions={[
                      {
                        label: "Sửa mã giảm giá",
                        onClick: () => startEdit(item),
                        icon: <Pencil className="w-4 h-4" />,
                      },
                      {
                        label: "Xóa mã giảm giá",
                        onClick: () => removeCode(item.id),
                        icon: <Trash2 className="w-4 h-4" />,
                        variant: "danger",
                      },
                    ]}
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

