import {
  App as AntApp,
  Button as AntButton,
  Card as UICard,
  Checkbox as AntCheckbox,
  Flex as UIFlex,
  Input as AntInput,
  Table as AntTable,
  Typography as AntTypography,
} from "antd";
import React, { useEffect, useMemo, useState } from "react";
import adminService from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { TableActions } from "@/components/admin/TableActions";
import { Pencil, Trash2 } from "lucide-react";
import type { Category, CategoryPayload } from "@/types";

const emptyForm: CategoryPayload = {
  name: "",
  description: "",
  is_active: true,
};

export default function CategoryManagement() {
  const { modal } = AntApp.useApp();
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
    if (!(await modal.confirm({ title: "Xác nhận xóa", content: `Bạn chắc chắn muốn xóa danh mục "${item.name}"?`, okText: "Xóa", cancelText: "Giữ lại", okButtonProps: { danger: true }, mask: { closable: false } }))) return;

    try {
      await adminService.deleteCategory(item.id);
      setNotice({ type: "success", text: `Đã xóa danh mục "${item.name}".` });
      await loadCategories();
    } catch (error: unknown) {
      setNotice({ type: "error", text: extractError(error, "Không thể xóa danh mục này.") });
    }
  };

  return (
    <UIFlex vertical gap="large" ><div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <AntTypography.Title level={3} >Quản lý Danh mục Tour</AntTypography.Title>
          <p className="mt-1 text-sm text-gray-500">
            Nhóm phân loại tour hiển thị ở bộ lọc và nhãn trên thẻ tour: biển đảo, nghỉ dưỡng, khám phá...
          </p>
        </div>
        <AntButton htmlType="button" onClick={openCreateModal} type="primary">Thêm danh mục
        </AntButton>
      </div>{notice && (
        <div
          className={`rounded-lg px-4 py-3 text-sm font-medium ${
            notice.type === "success"
              ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
              : "bg-red-50 text-red-700 border border-red-200"
          }`}
        >
          {notice.text}
        </div>
      )}<div className="grid gap-4 md:grid-cols-3">
        <UICard  ><UIFlex vertical gap="middle"><p className="text-xs font-semibold uppercase text-gray-400">Tổng danh mục</p><p className="mt-2 text-2xl font-bold text-gray-900">{categories.length}</p></UIFlex></UICard>
        <UICard  ><UIFlex vertical gap="middle"><p className="text-xs font-semibold uppercase text-gray-400">Đang hiển thị</p><p className="mt-2 text-2xl font-bold text-emerald-600">{activeCount}</p></UIFlex></UICard>
        <UICard  ><UIFlex vertical gap="middle"><p className="text-xs font-semibold uppercase text-gray-400">Tổng lượt gắn vào tour</p><p className="mt-2 text-2xl font-bold text-primary-600">{totalTours}</p></UIFlex></UICard>
      </div><div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
        <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }} loading={loading}
    dataSource={loading ? [] : categories.map((item) => (
                {key: item.id, cells: [<>{item.name}</>,<>{item.slug}</>,<>
                    <span className="line-clamp-2">
                      {item.description || <em className="text-gray-300">Chưa có mô tả</em>}
                    </span>
                  </>,<>{item.tours_count ?? 0} tour</>,<>
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-bold ${
                        item.is_active ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-500"
                      }`}
                    >
                      {item.is_active ? "Hiển thị" : "Đã ẩn"}
                    </span>
                  </>,<>
                    <TableActions
                      id={item.id}
                      label="Thao tác danh mục"
                      actions={[
                        {
                          label: "Sửa danh mục",
                          onClick: () => openEditModal(item),
                          icon: <Pencil className="w-4 h-4" />,
                        },
                        {
                          label: "Xóa danh mục",
                          onClick: () => handleDelete(item),
                          icon: <Trash2 className="w-4 h-4" />,
                          variant: "danger",
                          // Còn tour thuộc danh mục thì khóa, và nói luôn vì sao — trước đây
                          // lý do chỉ nằm trong tooltip, phải rê chuột mới thấy.
                          disabled: (item.tours_count ?? 0) > 0,
                          hint:
                            (item.tours_count ?? 0) > 0
                              ? `Còn ${item.tours_count} tour đang thuộc danh mục này`
                              : undefined,
                        },
                      ]}
                    />
                  </>], rowProps: {}}
              ))}
    columns={[{ key: "0", title: <>Danh mục</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Đường dẫn (slug)</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Mô tả</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Đang dùng</>, align: "center", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Trạng thái</>, align: "center", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Thao tác</>, align: "right", render: (_value, record) => record.cells[5] }]}
    onRow={(record) => record.rowProps}
    locale={{ emptyText: <>
                  Chưa có danh mục nào. Hãy thêm danh mục đầu tiên.
                </> }} />
      </div><Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        onSubmit={handleSubmit}
        title={editingId ? "Cập nhật danh mục" : "Thêm danh mục mới"}
        subtitle="Danh mục dùng để phân loại tour ở bộ lọc và nhãn hiển thị trên thẻ tour."
        size="lg"
        footer={
          <>
            <AntButton htmlType="button" onClick={closeModal}>Hủy
            </AntButton>
            <AntButton htmlType="submit" disabled={submitting} type="primary">{submitting ? "Đang lưu..." : editingId ? "Cập nhật" : "Tạo danh mục"}</AntButton>
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
          <AntInput required autoFocus placeholder="VD: Trekking, Du lịch tâm linh..." value={form.name} onChange={(e) => updateForm("name", e.target.value)} style={{ width: "100%" }} />
          <span className="block text-xs text-gray-400">
            Đường dẫn (slug) sẽ được tạo tự động từ tên danh mục.
          </span>
        </label>

        <label className="block space-y-1.5">
          <span className="text-xs font-semibold uppercase text-gray-500">Mô tả ngắn</span>
          <AntInput placeholder="VD: Các tour leo núi, đi bộ đường dài" value={form.description ?? ""} onChange={(e) => updateForm("description", e.target.value)} style={{ width: "100%" }} />
        </label>

        <label className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-medium text-gray-700">
          <AntCheckbox checked={form.is_active} onChange={(e) => updateForm("is_active", e.target.checked)} />
          Hiển thị cho khách hàng
        </label>
      </Modal></UIFlex>
  );
}
