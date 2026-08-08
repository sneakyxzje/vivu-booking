import React, { useEffect, useMemo, useState } from "react";
import adminService from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import type { Category, CategoryPayload } from "@/types";

const emptyForm: CategoryPayload = {
  name: "",
  description: "",
  is_active: true,
};

export default function CategoryManagement() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [notice, setNotice] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<CategoryPayload>(emptyForm);
  const [formError, setFormError] = useState<string | null>(null);

  const activeCount = useMemo(
    () => categories.filter((item) => item.is_active).length,
    [categories],
  );
  const totalTours = useMemo(
    () => categories.reduce((sum, item) => sum + (item.tours_count ?? 0), 0),
    [categories],
  );

  const loadCategories = async () => {
    setLoading(true);
    try {
      const result = await adminService.getCategories();
      setCategories(result?.data ?? []);
    } catch {
      setNotice({ type: "error", text: "Không thể tải danh sách danh mục. Vui lòng thử lại." });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCategories();
  }, []);

  const updateForm = (field: keyof CategoryPayload, value: string | boolean) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const openCreateModal = () => {
    setEditingId(null);
    setForm(emptyForm);
    setFormError(null);
    setIsModalOpen(true);
  };

  const openEditModal = (item: Category) => {
    setEditingId(item.id);
    setForm({
      name: item.name,
      description: item.description ?? "",
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
      const payload: CategoryPayload = {
        name: form.name.trim(),
        description: form.description?.trim() || undefined,
        is_active: form.is_active,
      };

      if (editingId) {
        await adminService.updateCategory(editingId, payload);
        setNotice({ type: "success", text: "Đã cập nhật danh mục thành công." });
      } else {
        await adminService.createCategory(payload);
        setNotice({ type: "success", text: "Đã tạo danh mục mới thành công." });
      }

      closeModal();
      await loadCategories();
    } catch (error: unknown) {
      setFormError(extractError(error, "Không thể lưu danh mục. Vui lòng thử lại."));
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (item: Category) => {
    if (!window.confirm(`Bạn chắc chắn muốn xóa danh mục "${item.name}"?`)) return;

    try {
      await adminService.deleteCategory(item.id);
      setNotice({ type: "success", text: `Đã xóa danh mục "${item.name}".` });
      await loadCategories();
    } catch (error: unknown) {
      setNotice({ type: "error", text: extractError(error, "Không thể xóa danh mục này.") });
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">Quản lý Danh mục Tour</h1>
          <p className="mt-1 text-sm text-gray-500">
            Nhóm phân loại tour hiển thị ở bộ lọc và nhãn trên thẻ tour: biển đảo, nghỉ dưỡng, khám phá...
          </p>
        </div>
        <button
          type="button"
          onClick={openCreateModal}
          className="shrink-0 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
        >
          Thêm danh mục
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
          <p className="text-xs font-semibold uppercase text-gray-400">Tổng danh mục</p>
          <p className="mt-2 text-2xl font-bold text-gray-900">{categories.length}</p>
        </div>
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase text-gray-400">Đang hiển thị</p>
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
              <th className="px-4 py-3">Danh mục</th>
              <th className="px-4 py-3">Đường dẫn (slug)</th>
              <th className="px-4 py-3">Mô tả</th>
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
            ) : categories.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-center text-gray-400" colSpan={6}>
                  Chưa có danh mục nào. Hãy thêm danh mục đầu tiên.
                </td>
              </tr>
            ) : (
              categories.map((item) => (
                <tr key={item.id} className="transition-colors hover:bg-gray-50/60">
                  <td className="px-4 py-3 font-semibold text-gray-900">{item.name}</td>
                  <td className="px-4 py-3 font-mono text-xs text-gray-500">{item.slug}</td>
                  <td className="max-w-xs px-4 py-3 text-gray-500">
                    <span className="line-clamp-2">
                      {item.description || <em className="text-gray-300">Chưa có mô tả</em>}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-center text-gray-600">{item.tours_count ?? 0} tour</td>
                  <td className="px-4 py-3 text-center">
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-bold ${
                        item.is_active ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-500"
                      }`}
                    >
                      {item.is_active ? "Hiển thị" : "Đã ẩn"}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      onClick={() => openEditModal(item)}
                      className="mr-3 font-semibold text-primary-600 hover:text-primary-700"
                    >
                      Sửa
                    </button>
                    <button
                      onClick={() => handleDelete(item)}
                      disabled={(item.tours_count ?? 0) > 0}
                      title={
                        (item.tours_count ?? 0) > 0
                          ? "Còn tour đang thuộc danh mục này"
                          : "Xóa danh mục"
                      }
                      className="font-semibold text-red-600 hover:text-red-700 disabled:cursor-not-allowed disabled:text-gray-300"
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

      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        onSubmit={handleSubmit}
        title={editingId ? "Cập nhật danh mục" : "Thêm danh mục mới"}
        subtitle="Danh mục dùng để phân loại tour ở bộ lọc và nhãn hiển thị trên thẻ tour."
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
              {submitting ? "Đang lưu..." : editingId ? "Cập nhật" : "Tạo danh mục"}
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
            Tên danh mục <span className="text-red-500">*</span>
          </span>
          <input
            required
            autoFocus
            placeholder="VD: Trekking, Du lịch tâm linh..."
            value={form.name}
            onChange={(e) => updateForm("name", e.target.value)}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
          <span className="block text-xs text-gray-400">
            Đường dẫn (slug) sẽ được tạo tự động từ tên danh mục.
          </span>
        </label>

        <label className="block space-y-1.5">
          <span className="text-xs font-semibold uppercase text-gray-500">Mô tả ngắn</span>
          <input
            placeholder="VD: Các tour leo núi, đi bộ đường dài"
            value={form.description ?? ""}
            onChange={(e) => updateForm("description", e.target.value)}
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          />
        </label>

        <label className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-medium text-gray-700">
          <input
            type="checkbox"
            checked={form.is_active}
            onChange={(e) => updateForm("is_active", e.target.checked)}
          />
          Hiển thị cho khách hàng
        </label>
      </Modal>
    </div>
  );
}
