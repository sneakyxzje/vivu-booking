import React, { useEffect, useMemo, useState } from "react";
import adminService from "@/services/adminService";
import type { Service, ServicePayload } from "@/types";

// Giá trị mặc định khi mở form tạo mới
const emptyForm: ServicePayload = {
  name: "",
  icon: "",
  description: "",
  price: null,
  is_active: true,
};

// Hiển thị giá tiền theo định dạng VN
const formatPrice = (value: number | null | undefined) =>
  value == null ? "Miễn phí (bao gồm trong giá tour)" : `${Number(value).toLocaleString("vi-VN")}đ / khách`;

export default function ServiceManagement() {
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<ServicePayload>(emptyForm);

  // Thống kê nhanh
  const activeCount = useMemo(() => services.filter((s) => s.is_active).length, [services]);
  const totalTours = useMemo(() => services.reduce((sum, s) => sum + (s.tours_count ?? 0), 0), [services]);

  // --- Tải danh sách dịch vụ ---
  const loadServices = async () => {
    setLoading(true);
    try {
      const result = await adminService.getServices();
      setServices(result?.data ?? []);
    } catch {
      setMessage("Không thể tải danh sách dịch vụ. Vui lòng thử lại.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadServices();
  }, []);

  // --- Cập nhật form ---
  const updateForm = (field: keyof ServicePayload, value: string | number | boolean | null) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const resetForm = () => {
    setEditingId(null);
    setForm(emptyForm);
    setMessage(null);
  };

  // --- Bắt đầu sửa ---
  const startEdit = (item: Service) => {
    setEditingId(item.id);
    setForm({
      name: item.name,
      icon: item.icon ?? "",
      description: item.description ?? "",
      price: item.price ?? null,
      is_active: item.is_active ?? true,
    });
    setMessage(null);
    // Scroll lên đầu form
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  // --- Lưu form (tạo mới hoặc cập nhật) ---
  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setMessage(null);

    try {
      const payload: ServicePayload = {
        ...form,
        name: form.name.trim(),
        icon: form.icon?.trim() || undefined,
        description: form.description?.trim() || undefined,
        price: form.price !== null && form.price !== undefined && String(form.price) !== "" ? Number(form.price) : null,
      };

      if (editingId) {
        await adminService.updateService(editingId, payload);
        setMessage("✅ Đã cập nhật dịch vụ thành công.");
      } else {
        await adminService.createService(payload);
        setMessage("✅ Đã tạo dịch vụ mới thành công.");
      }
      resetForm();
      await loadServices();
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      const firstError = errors ? Object.values(errors).flat()[0] : null;
      setMessage(String(firstError ?? error?.response?.data?.message ?? "Không thể lưu dịch vụ. Vui lòng thử lại."));
    } finally {
      setSubmitting(false);
    }
  };

  // --- Xóa dịch vụ ---
  const handleDelete = async (id: number, name: string) => {
    if (!window.confirm(`Bạn chắc chắn muốn xóa dịch vụ "${name}"?\n\nDịch vụ sẽ bị gỡ khỏi tất cả tour đang sử dụng.`)) return;
    try {
      await adminService.deleteService(id);
      setMessage(`✅ Đã xóa dịch vụ "${name}".`);
      await loadServices();
    } catch {
      setMessage("❌ Không thể xóa dịch vụ này.");
    }
  };

  return (
    <div className="space-y-6">
      {/* HEADER */}
      <div>
        <h1 className="text-2xl font-bold text-gray-950">Quản lý Dịch vụ Phát sinh</h1>
        <p className="mt-1 text-sm text-gray-500">
          Quản lý danh mục dịch vụ đi kèm tour: khách sạn, ăn uống, bảo hiểm, vé tham quan...
        </p>
      </div>

      {/* STATS */}
      <div className="grid gap-4 md:grid-cols-3">
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Tổng dịch vụ</p>
          <p className="mt-2 text-2xl font-bold text-gray-900">{services.length}</p>
        </div>
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Đang hoạt động</p>
          <p className="mt-2 text-2xl font-bold text-emerald-600">{activeCount}</p>
        </div>
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Tổng lượt gắn vào tour</p>
          <p className="mt-2 text-2xl font-bold text-primary-600">{totalTours}</p>
        </div>
      </div>

      {/* FORM TẠO / SỬA */}
      <form
        onSubmit={handleSubmit}
        className="rounded-lg border border-gray-100 bg-white p-5 shadow-sm"
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-bold text-gray-950">
            {editingId ? "✏️ Cập nhật dịch vụ" : "➕ Thêm dịch vụ mới"}
          </h2>
          {editingId && (
            <button type="button" onClick={resetForm} className="text-sm font-semibold text-gray-500 hover:text-gray-800">
              Hủy chỉnh sửa
            </button>
          )}
        </div>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {/* Tên dịch vụ */}
          <label className="space-y-1.5 lg:col-span-2">
            <span className="text-xs font-semibold uppercase text-gray-500">Tên dịch vụ <span className="text-red-500">*</span></span>
            <input
              required
              placeholder="VD: Khách sạn 3 sao, Ăn uống 3 bữa..."
              value={form.name}
              onChange={(e) => updateForm("name", e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
            />
          </label>

          {/* Icon (emoji) */}
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">Icon (emoji)</span>
            <input
              placeholder="VD: 🏨 hoặc 🍽️"
              value={form.icon ?? ""}
              onChange={(e) => updateForm("icon", e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
            />
          </label>

          {/* Giá */}
          <label className="space-y-1.5">
            <span className="text-xs font-semibold uppercase text-gray-500">Giá (VNĐ/khách)</span>
            <input
              type="number"
              min="0"
              step="1000"
              placeholder="Để trống = bao gồm giá tour"
              value={form.price ?? ""}
              onChange={(e) => updateForm("price", e.target.value === "" ? null : Number(e.target.value))}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
            />
          </label>

          {/* Mô tả */}
          <label className="space-y-1.5 md:col-span-2 lg:col-span-3">
            <span className="text-xs font-semibold uppercase text-gray-500">Mô tả ngắn</span>
            <input
              placeholder="VD: Phòng đôi tiêu chuẩn, điều hòa, wifi, buffet sáng..."
              value={form.description ?? ""}
              onChange={(e) => updateForm("description", e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
            />
          </label>

          {/* Trạng thái */}
          <label className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 self-end">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => updateForm("is_active", e.target.checked)}
            />
            Kích hoạt
          </label>
        </div>

        {/* Thông báo */}
        {message && (
          <p className={`mt-4 rounded-lg px-3 py-2 text-sm font-medium ${message.startsWith("✅") ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}>
            {message}
          </p>
        )}

        <button
          disabled={submitting}
          className="mt-4 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60"
        >
          {submitting ? "Đang lưu..." : editingId ? "Cập nhật dịch vụ" : "Tạo dịch vụ mới"}
        </button>
      </form>

      {/* BẢNG DANH SÁCH */}
      <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-gray-100 text-sm">
          <thead className="bg-gray-50 text-left text-xs font-bold uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3">Dịch vụ</th>
              <th className="px-4 py-3">Mô tả</th>
              <th className="px-4 py-3">Giá phát sinh</th>
              <th className="px-4 py-3 text-center">Đang dùng</th>
              <th className="px-4 py-3 text-center">Trạng thái</th>
              <th className="px-4 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {loading ? (
              <tr>
                <td className="px-4 py-6 text-center text-gray-500" colSpan={6}>Đang tải...</td>
              </tr>
            ) : services.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-center text-gray-400" colSpan={6}>
                  Chưa có dịch vụ nào. Hãy thêm dịch vụ đầu tiên!
                </td>
              </tr>
            ) : (
              services.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50/60 transition-colors">
                  {/* Icon + Tên */}
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      {item.icon && <span className="text-lg">{item.icon}</span>}
                      <span className="font-semibold text-gray-900">{item.name}</span>
                    </div>
                  </td>

                  {/* Mô tả */}
                  <td className="px-4 py-3 text-gray-500 max-w-xs">
                    <span className="line-clamp-2">{item.description || <em className="text-gray-300">Chưa có mô tả</em>}</span>
                  </td>

                  {/* Giá */}
                  <td className="px-4 py-3 font-semibold text-gray-800">
                    {formatPrice(item.price)}
                  </td>

                  {/* Số tour đang dùng */}
                  <td className="px-4 py-3 text-center text-gray-600">
                    {item.tours_count ?? 0} tour
                  </td>

                  {/* Trạng thái */}
                  <td className="px-4 py-3 text-center">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${item.is_active ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-500"}`}>
                      {item.is_active ? "Hoạt động" : "Tạm tắt"}
                    </span>
                  </td>

                  {/* Thao tác */}
                  <td className="px-4 py-3 text-right">
                    <button
                      onClick={() => startEdit(item)}
                      className="mr-3 font-semibold text-primary-600 hover:text-primary-700"
                    >
                      Sửa
                    </button>
                    <button
                      onClick={() => handleDelete(item.id, item.name)}
                      className="font-semibold text-red-600 hover:text-red-700"
                    >
                      Xóa
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
