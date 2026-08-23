import React, { useEffect, useMemo, useState } from "react";
import adminService from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { TableActions } from "@/components/admin/TableActions";
import { Pencil, Trash2 } from "lucide-react";
import type { Service, ServicePayload } from "@/types";

const emptyForm: ServicePayload = {
  name: "",
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
  const [notice, setNotice] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<ServicePayload>(emptyForm);
  const [formError, setFormError] = useState<string | null>(null);

  const activeCount = useMemo(() => services.filter((s) => s.is_active).length, [services]);
  const totalTours = useMemo(() => services.reduce((sum, s) => sum + (s.tours_count ?? 0), 0), [services]);

  const loadServices = async () => {
    setLoading(true);
    try {
      const result = await adminService.getServices();
      setServices(result?.data ?? []);
    } catch {
      setNotice({ type: "error", text: "Không thể tải danh sách dịch vụ. Vui lòng thử lại." });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadServices();
  }, []);

  const updateForm = (field: keyof ServicePayload, value: string | number | boolean | null) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const openCreateModal = () => {
    setEditingId(null);
    setForm(emptyForm);
    setFormError(null);
    setIsModalOpen(true);
  };

  const openEditModal = (item: Service) => {
    setEditingId(item.id);
    setForm({
      name: item.name,
      description: item.description ?? "",
      price: item.price ?? null,
      is_active: item.is_active ?? true,
    });
    setFormError(null);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setEditingId(null);
    setForm(emptyForm);
    setFormError(null);
  };

  const extractError = (error: unknown, fallback: string) => {
    const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
      .response?.data;
    const firstError = response?.errors ? Object.values(response.errors).flat()[0] : null;
    return String(firstError ?? response?.message ?? fallback);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setFormError(null);

    try {
      const payload: ServicePayload = {
        name: form.name.trim(),
        description: form.description?.trim() || undefined,
        price:
          form.price !== null && form.price !== undefined && String(form.price) !== ""
            ? Number(form.price)
            : null,
        is_active: form.is_active,
      };

      if (editingId) {
        await adminService.updateService(editingId, payload);
        setNotice({ type: "success", text: "Đã cập nhật dịch vụ thành công." });
      } else {
        await adminService.createService(payload);
        setNotice({ type: "success", text: "Đã tạo dịch vụ mới thành công." });
      }

      closeModal();
      await loadServices();
    } catch (error: unknown) {
      setFormError(extractError(error, "Không thể lưu dịch vụ. Vui lòng thử lại."));
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: number, name: string) => {
    if (
      !window.confirm(
        `Bạn chắc chắn muốn xóa dịch vụ "${name}"?\n\nDịch vụ sẽ bị gỡ khỏi tất cả tour đang sử dụng.`,
      )
    )
      return;

    try {
      await adminService.deleteService(id);
      setNotice({ type: "success", text: `Đã xóa dịch vụ "${name}".` });
      await loadServices();
    } catch (error: unknown) {
      setNotice({ type: "error", text: extractError(error, "Không thể xóa dịch vụ này.") });
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">Quản lý Dịch vụ đi kèm</h1>
          <p className="mt-1 text-sm text-gray-500">
            Những thứ tour đã bao gồm trong giá bán: khách sạn, ăn uống, bảo hiểm, vé tham quan.
            Chi phí phát sinh ngoài ý muốn nằm ở màn "Sự cố dọc đường".
          </p>
        </div>
        <button
          type="button"
          onClick={openCreateModal}
          className="shrink-0 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
        >
          Thêm dịch vụ
        </button>
      </div>

      {notice && (
        <div
          className={`rounded-lg px-4 py-3 text-sm font-medium ${
            notice.type === "success"
              ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
              : "bg-red-50 text-red-700 border border-red-200"
          }`}
        >
          {notice.text}
        </div>
      )}

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

      <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-gray-100 text-sm">
          <thead className="bg-gray-50 text-left text-xs font-bold uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3">Dịch vụ</th>
              <th className="px-4 py-3">Mô tả</th>
              <th className="px-4 py-3">Giá tham khảo</th>
              <th className="px-4 py-3 text-center">Đang dùng</th>
              <th className="px-4 py-3 text-center">Trạng thái</th>
              <th className="px-4 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {loading ? (
              <tr>
                <td className="px-4 py-6 text-center text-gray-500" colSpan={6}>
                  Đang tải...
                </td>
              </tr>
            ) : services.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-center text-gray-400" colSpan={6}>
                  Chưa có dịch vụ nào. Hãy thêm dịch vụ đầu tiên.
                </td>
              </tr>
            ) : (
              services.map((item) => (
                <tr key={item.id} className="transition-colors hover:bg-gray-50/60">
                  <td className="px-4 py-3 font-semibold text-gray-900">{item.name}</td>

                  <td className="max-w-xs px-4 py-3 text-gray-500">
                    <span className="line-clamp-2">
                      {item.description || <em className="text-gray-300">Chưa có mô tả</em>}
                    </span>
                  </td>

                  <td className="px-4 py-3 font-semibold text-gray-800">{formatPrice(item.price)}</td>

                  <td className="px-4 py-3 text-center text-gray-600">{item.tours_count ?? 0} tour</td>

                  <td className="px-4 py-3 text-center">
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-bold ${
                        item.is_active ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-500"
                      }`}
                    >
                      {item.is_active ? "Hoạt động" : "Tạm tắt"}
                    </span>
                  </td>

                  <td className="px-4 py-3 text-right">
                    <TableActions
                      id={item.id}
                      label="Thao tác dịch vụ"
                      actions={[
                        {
                          label: "Sửa dịch vụ",
                          onClick: () => openEditModal(item),
                          icon: <Pencil className="w-4 h-4" />,
                        },
                        {
                          label: "Xóa dịch vụ",
                          onClick: () => handleDelete(item.id, item.name),
                          icon: <Trash2 className="w-4 h-4" />,
                          variant: "danger",
                        },
                      ]}
                    />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        onSubmit={handleSubmit}
        title={editingId ? "Cập nhật dịch vụ" : "Thêm dịch vụ mới"}
        subtitle="Dịch vụ đi kèm được gắn vào tour và hiển thị cho khách khi xem chi tiết tour."
        size="lg"
        footer={
          <>
            <button
              type="button"
              onClick={closeModal}
              className="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100 cursor-pointer"
            >
              Hủy
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-700 disabled:opacity-60 cursor-pointer"
            >
              {submitting ? "Đang lưu..." : editingId ? "Cập nhật" : "Tạo dịch vụ"}
            </button>
          </>
        }
      >
        {formError && (
          <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {formError}
          </div>
        )}

        <label className="block space-y-1.5">
          <span className="text-xs font-semibold uppercase text-gray-500">
            Tên dịch vụ <span className="text-red-500">*</span>
          </span>
          <input
            required
            autoFocus
            placeholder="VD: Khách sạn 3 sao, Ăn uống 3 bữa..."
            value={form.name}
            onChange={(e) => updateForm("name", e.target.value)}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
        </label>

        <label className="block space-y-1.5">
          <span className="text-xs font-semibold uppercase text-gray-500">Mô tả ngắn</span>
          <input
            placeholder="VD: Phòng đôi tiêu chuẩn, điều hòa, wifi, buffet sáng..."
            value={form.description ?? ""}
            onChange={(e) => updateForm("description", e.target.value)}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
        </label>

        <label className="block space-y-1.5">
          {/*
            "Tham khảo" chứ không phải "phát sinh": con số này chỉ hiện lên trang chi tiết tour cho
            khách đọc, không cộng vào tiền đơn hàng ở bất cứ đâu. Nhãn cũ hứa một việc mà mã không
            làm, nên ai nhìn cũng tưởng khách sẽ bị thu thêm.
          */}
          <span className="text-xs font-semibold uppercase text-gray-500">Giá tham khảo (VNĐ/khách)</span>
          <input
            type="number"
            min="0"
            step="1000"
            placeholder="Để trống nếu không muốn hiện giá lên trang tour"
            value={form.price ?? ""}
            onChange={(e) => updateForm("price", e.target.value === "" ? null : Number(e.target.value))}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
        </label>

        <label className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-medium text-gray-700">
          <input
            type="checkbox"
            checked={form.is_active}
            onChange={(e) => updateForm("is_active", e.target.checked)}
          />
          Kích hoạt dịch vụ
        </label>
      </Modal>
    </div>
  );
}
