import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Select as AntSelect,
  Table as AntTable,
  Typography as AntTypography,
} from "antd";
import React, { useState, useEffect, useMemo } from "react";
import type { Guide } from "@/types";
import adminService from "@/services/adminService";
import { Toast, ConfirmModal } from "@/components/admin/CustomAlert";
import { TableActions } from "@/components/admin/TableActions";
import { Modal } from "@/components/admin/Modal";
import { GuideProfileModal } from "@/components/admin/GuideProfileModal";

export default function GuideManagement() {
  const [guides, setGuides] = useState<Guide[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalGuidesCount, setTotalGuidesCount] = useState(0);

  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [isModalOpen, setIsModalOpen] = useState(false);

  // State chứa thông tin guide đang thêm/sửa
  const [currentGuide, setCurrentGuide] = useState<Partial<Guide> & { password?: string } | null>(null);

  // Hồ sơ năng lực đi biểu mẫu riêng: sửa nghề nghiệp khác với sửa tài khoản đăng nhập.
  const [profileGuide, setProfileGuide] = useState<Guide | null>(null);

  // --- CUSTOM ALERT STATES ---
  const [toast, setToast] = useState<{ message: string; type: "success" | "error" | "info"; isOpen: boolean }>({
    message: "",
    type: "success",
    isOpen: false,
  });

  const showToast = (message: string, type: "success" | "error" | "info" = "success") => {
    setToast({ message, type, isOpen: true });
  };

  const [confirm, setConfirm] = useState<{ message: string; isOpen: boolean; onConfirm: () => void }>({
    message: "",
    isOpen: false,
    onConfirm: () => { },
  });

  const triggerConfirm = (message: string, onConfirm: () => void) => {
    setConfirm({ message, isOpen: true, onConfirm });
  };

  // Fetch danh sách hướng dẫn viên từ Backend
  const fetchGuides = async () => {
    setLoading(true);
    try {
      const res = await adminService.getGuides(currentPage);
      if (res) {
        setGuides(res.data || []);
        setTotalPages(res.last_page || 1);
        setTotalGuidesCount(res.total || 0);
      }
    } catch (err) {
      console.error("Lỗi lấy danh sách HDV: ", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchGuides();
  }, [currentPage]);

  // Thống kê KPIs từ dữ liệu đã tải về
  const stats = useMemo(() => {
    const total = totalGuidesCount;
    const active = guides.filter((g) => g.status === "active").length;
    const inactive = guides.filter((g) => g.status === "inactive").length;
    return { total, active, inactive };
  }, [guides, totalGuidesCount]);

  // Bộ lọc tìm kiếm
  const filteredGuides = useMemo(() => {
    let result = [...guides];

    if (search.trim()) {
      const q = search.toLowerCase();
      result = result.filter(
        (g) =>
          g.name.toLowerCase().includes(q) ||
          g.email.toLowerCase().includes(q) ||
          (g.phone && g.phone.includes(q)) ||
          (g.address && g.address.toLowerCase().includes(q))
      );
    }

    if (statusFilter !== "all") {
      result = result.filter((g) => g.status === statusFilter);
    }

    return result;
  }, [guides, search, statusFilter]);

  // Mở modal Thêm mới
  const handleOpenCreateModal = () => {
    setCurrentGuide({
      name: "",
      email: "",
      password: "",
      phone: "",
      address: "",
      status: "active",
    });
    setIsModalOpen(true);
  };

  // Mở modal Sửa
  const handleOpenEditModal = (guide: Guide) => {
    setCurrentGuide({ ...guide });
    setIsModalOpen(true);
  };

  // Lưu Form Thêm/Sửa gọi API
  const handleSaveGuide = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentGuide || !currentGuide.name || !currentGuide.email) return;

    try {
      if (currentGuide.id) {
        // Chỉ cập nhật name và status theo đúng validation của BE
        const res = await adminService.updateGuide(currentGuide.id, {
          name: currentGuide.name,
          status: currentGuide.status,
        });
        if (res) {
          showToast("Cập nhật thông tin hướng dẫn viên thành công!", "success");
          fetchGuides();
        }
      } else {
        // Thêm mới (Cần có mật khẩu)
        if (!currentGuide.password || currentGuide.password.length < 6) {
          showToast("Mật khẩu là bắt buộc và phải có tối thiểu 6 ký tự!", "error");
          return;
        }
        const res = await adminService.createGuide({
          name: currentGuide.name,
          email: currentGuide.email,
          password: currentGuide.password,
          phone: currentGuide.phone || null,
          address: currentGuide.address || null,
          avatar: null,
          status: currentGuide.status || "active",
        });
        if (res) {
          showToast("Tạo hướng dẫn viên mới thành công!", "success");
          fetchGuides();
        }
      }
      setIsModalOpen(false);
      setCurrentGuide(null);
    } catch (err: any) {
      console.error("Lỗi khi lưu HDV: ", err);
      const msg = err.response?.data?.message || "Đã xảy ra lỗi khi lưu thông tin. Hãy kiểm tra lại email có bị trùng lặp không.";
      showToast(msg, "error");
    }
  };

  // Đổi nhanh trạng thái hoạt động
  const handleToggleStatus = async (guide: Guide) => {
    const nextStatus = guide.status === "active" ? "inactive" : "active";
    try {
      const res = await adminService.updateGuide(guide.id, {
        name: guide.name,
        status: nextStatus,
      });
      if (res) {
        setGuides((prev) =>
          prev.map((g) => (g.id === guide.id ? { ...g, status: nextStatus } : g))
        );
        showToast("Cập nhật trạng thái hướng dẫn viên thành công!", "success");
      }
    } catch (err) {
      console.error("Lỗi cập nhật trạng thái HDV: ", err);
      showToast("Không thể cập nhật trạng thái hướng dẫn viên.", "error");
    }
  };

  // Xóa Hướng dẫn viên
  const handleDeleteGuide = async (guideId: number) => {
    triggerConfirm(
      "Bạn có chắc chắn muốn xóa hướng dẫn viên này khỏi hệ thống (Xóa mềm)?",
      async () => {
        try {
          const success = await adminService.deleteGuide(guideId);
          if (success) {
            showToast("Xóa hướng dẫn viên thành công!", "success");
            fetchGuides();
          }
        } catch (err) {
          console.error("Lỗi khi xóa hướng dẫn viên: ", err);
          showToast("Có lỗi xảy ra khi xóa hướng dẫn viên.", "error");
        }
      }
    );
  };

  return (
    <UIFlex vertical gap="large" >{/* HEADER */}<div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <AntTypography.Title level={3} >Quản lý Hướng dẫn viên
          </AntTypography.Title>
          <p className="text-sm text-gray-500">
            Quản lý danh sách, cấp tài khoản và phân công nhiệm vụ cho Hướng dẫn viên (Guide)
          </p>
        </div>
        <div>
          <AntButton onClick={handleOpenCreateModal} type="primary" htmlType="button"><svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>Thêm hướng dẫn viên
          </AntButton>
        </div>
      </div>{/* KPI STATS CARDS */}<div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
        {/* Tổng số HDV */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-primary-50 text-primary-600 rounded-md group-hover:bg-primary-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Tổng số HDV</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.total} nhân sự</h3>
          </div>
        </div>

        {/* Đang hoạt động */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-emerald-50 text-emerald-600 rounded-md group-hover:bg-emerald-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Đang hoạt động (Trang này)</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1 text-emerald-650">{stats.active} HDV</h3>
          </div>
        </div>

        {/* Tạm dừng hoạt động */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-rose-50 text-rose-600 rounded-md group-hover:bg-rose-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Tạm dừng (Trang này)</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1 text-rose-605">{stats.inactive} nhân sự</h3>
          </div>
        </div>
      </div>{/* FILTER & SEARCH */}<UICard  ><UIFlex vertical gap="middle"><div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
          {/* Thanh tìm kiếm */}
          <div  className="relative md:col-span-8"><AntInput prefix={<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>} type="text" placeholder="Tìm kiếm hướng dẫn viên theo tên, email, điện thoại, địa chỉ..." value={search} onChange={(e) => setSearch(e.target.value)} style={{ width: "100%" }} /></div>

          {/* Lọc trạng thái hoạt động */}
          <div className="md:col-span-3">
            <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((statusFilter) ?? "")} onChange={(e) => setStatusFilter(e)} style={{ width: "100%" }} options={[{ value: String("all"), label: "Tất cả trạng thái", disabled: false },{ value: String("active"), label: "Đang hoạt động", disabled: false },{ value: String("inactive"), label: "Tạm dừng hoạt động", disabled: false }].flat().filter((option) => !!option)} />
          </div>

          {/* Xóa lọc nhanh */}
          <div className="md:col-span-1 flex">
            <AntButton onClick={() => {
                setSearch("");
                setStatusFilter("all");
              }} style={{ width: "100%" }} htmlType="button">Xóa lọc
            </AntButton>
          </div>
        </div></UIFlex></UICard>{/* DATA TABLE */}<UICard  ><UIFlex vertical gap="middle">{loading ? (
          <div className="p-12 text-center text-gray-500 font-medium">
            Đang tải danh sách Hướng dẫn viên...
          </div>
        ) : (
          <div className="overflow-x-visible">
            <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={filteredGuides.map((guide) => (
                    {key: guide.id, cells: [<>
                        #{guide.id}
                      </>,<>
                        <UIFlex    align="center"  gap={12}><div className="w-10 h-10 rounded-full bg-primary-600 text-white font-bold flex items-center justify-center text-sm shadow-inner uppercase">
                            {guide.name.charAt(0)}
                          </div><div>
                            <p className="font-semibold text-gray-900">{guide.name}</p>
                            <p className="text-xs text-gray-400 mt-0.5 font-mono">{guide.email}</p>
                          </div></UIFlex>
                      </>,<>
                        <div>
                          <p className="font-medium text-gray-800 font-mono text-xs">{guide.phone ?? "Chưa cập nhật"}</p>
                          <p className="text-xs text-gray-400 mt-0.5 line-clamp-1">{guide.address ?? "Không có địa chỉ"}</p>
                        </div>
                      </>,<>
                        {guide.guide_profile ? (
                          <UIFlex vertical gap={4} ><UIFlex   wrap   gap={4}>{(guide.guide_categories ?? []).length === 0 ? (
                                <span className="text-[11px] text-gray-400">Chưa khai chuyên môn</span>
                              ) : (
                                (guide.guide_categories ?? []).map((loai) => (
                                  <span
                                    key={loai.id}
                                    className="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
                                  >
                                    {loai.name}
                                  </span>
                                ))
                              )}</UIFlex>{(guide.guide_profile.regions ?? []).length > 0 && (
                              <p className="text-[11px] text-gray-500 line-clamp-1">
                                Tuyến quen: {(guide.guide_profile.regions ?? []).join(", ")}
                              </p>
                            )}{(guide.guide_profile.languages ?? []).length > 0 && (
                              <p className="text-[11px] text-gray-400 line-clamp-1">
                                {(guide.guide_profile.languages ?? []).join(", ")}
                              </p>
                            )}</UIFlex>
                        ) : (
                          <AntButton htmlType="button" onClick={() => setProfileGuide(guide)}>Chưa có hồ sơ — bổ sung
                          </AntButton>
                        )}
                      </>,<>
                        {guide.assigned_tours_count ?? 0} tours
                      </>,<>
                        {guide.created_at}
                      </>,<>
                        <span
                          className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-semibold border ${guide.status === "active"
                            ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                            : "bg-rose-50 text-rose-700 border-rose-200"
                            }`}
                        >
                          <span className={`w-1.5 h-1.5 rounded-full ${guide.status === "active" ? "bg-emerald-500" : "bg-rose-500"}`}></span>
                          {guide.status === "active" ? "Hoạt động" : "Tạm dừng"}
                        </span>
                      </>,<>
                        <TableActions
                          id={guide.id}
                          actions={[
                            {
                              label: "Chỉnh sửa",
                              onClick: () => handleOpenEditModal(guide),
                              icon: (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                              ),
                            },
                            {
                              label: "Hồ sơ năng lực",
                              onClick: () => setProfileGuide(guide),
                              icon: (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                              ),
                            },
                            {
                              label: guide.status === "inactive" ? "Kích hoạt" : "Tạm ngưng",
                              onClick: () => handleToggleStatus(guide),
                              variant: guide.status === "inactive" ? "success" : "warning",
                              icon: guide.status === "inactive" ? (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                              ) : (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                              ),
                            },
                            {
                              label: "Xóa bỏ",
                              onClick: () => handleDeleteGuide(guide.id),
                              variant: "danger",
                              icon: (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                              ),
                            },
                          ]}
                        />
                      </>], rowProps: {}}
                  ))}
    columns={[{ key: "0", title: <>ID</>, align: "center", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Thông tin Hướng dẫn viên</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Điện thoại / Địa chỉ</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Hồ sơ năng lực</>, align: "left", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Số Tour phụ trách</>, align: "center", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Ngày tạo tài khoản</>, align: "left", render: (_value, record) => record.cells[5] },{ key: "6", title: <>Trạng thái</>, align: "center", render: (_value, record) => record.cells[6] },{ key: "7", title: <>Hành động</>, align: "center", render: (_value, record) => record.cells[7] }]}
    onRow={(record) => record.rowProps}
    locale={{ emptyText: <>
                      Không tìm thấy Hướng dẫn viên nào phù hợp.
                    </> }} />
          </div>
        )}{/* PAGINATION */}{!loading && totalPages > 1 && (
          <div className="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-100 sm:px-6">
            <div className="flex-1 flex justify-between sm:hidden">
              <AntButton disabled={currentPage === 1} onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))} htmlType="button">Trước
              </AntButton>
              <AntButton disabled={currentPage === totalPages} onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))} htmlType="button">Sau
              </AntButton>
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-xs text-gray-500">
                  Hiển thị trang <span className="font-semibold text-gray-700">{currentPage}</span> / <span className="font-semibold text-gray-700">{totalPages}</span> trang (Tổng <span className="font-semibold text-gray-700">{totalGuidesCount}</span> HDV)
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <AntButton disabled={currentPage === 1} onClick={() => setCurrentPage(1)} htmlType="button">Đầu
                  </AntButton>
                  <AntButton disabled={currentPage === 1} onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))} htmlType="button">Trước
                  </AntButton>
                  <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-primary-50 text-sm font-semibold text-primary-600">
                    {currentPage}
                  </span>
                  <AntButton disabled={currentPage === totalPages} onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))} htmlType="button">Sau
                  </AntButton>
                  <AntButton disabled={currentPage === totalPages} onClick={() => setCurrentPage(totalPages)} htmlType="button">Cuối
                  </AntButton>
                </nav>
              </div>
            </div>
          </div>
        )}</UIFlex></UICard>{/* CREATE & EDIT FORM MODAL */}<Modal
        isOpen={isModalOpen && !!currentGuide}
        onClose={() => {
          setIsModalOpen(false);
          setCurrentGuide(null);
        }}
        title={currentGuide?.id ? `Sửa thông tin HDV: #${currentGuide.id}` : "Thêm mới Hướng dẫn viên"}
        subtitle={currentGuide?.id ? "Cập nhật các thông tin được phép sửa đổi" : "Nhập đầy đủ thông tin để cấp tài khoản Guide"}
        onSubmit={handleSaveGuide}
        size="xl"
        footer={
          <>
            <AntButton htmlType="button" onClick={() => {
                setIsModalOpen(false);
                setCurrentGuide(null);
              }}>Đóng
            </AntButton>
            <AntButton htmlType="submit" type="primary">Lưu thay đổi
            </AntButton>
          </>
        }
      >
        {currentGuide && (
          <UIFlex vertical gap={16} >{/* Họ tên */}<div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Họ và tên <span className="text-rose-500">*</span>
              </label>
              <AntInput type="text" required value={currentGuide.name || ""} onChange={(e) => setCurrentGuide((prev) => ({ ...prev, name: e.target.value }))} placeholder="Nhập họ tên hướng dẫn viên" style={{ width: "100%" }} />
            </div>{/* Email */}<div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Email tài khoản {!currentGuide.id && <span className="text-rose-500">*</span>}
              </label>
              <AntInput type="email" required={!currentGuide.id} disabled={!!currentGuide.id} value={currentGuide.email || ""} onChange={(e) => setCurrentGuide((prev) => ({ ...prev, email: e.target.value }))} placeholder="nguyenvanan@gmail.com" style={{ width: "100%" }} />
              {currentGuide.id && (
                <span className="text-[10px] text-gray-400 mt-1 block">Email không được phép thay đổi sau khi tạo</span>
              )}
            </div>{/* Password (Chỉ cho tạo mới) */}{!currentGuide.id && (
              <div>
                <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                  Mật khẩu khởi tạo <span className="text-rose-500">*</span>
                </label>
                <AntInput type="password" required value={currentGuide.password || ""} onChange={(e) => setCurrentGuide((prev) => ({ ...prev, password: e.target.value }))} placeholder="Tối thiểu 6 ký tự" style={{ width: "100%" }} />
              </div>
            )}{/* SĐT */}<div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Số điện thoại
              </label>
              <AntInput type="text" disabled={!!currentGuide.id} value={currentGuide.phone || ""} onChange={(e) => setCurrentGuide((prev) => ({ ...prev, phone: e.target.value }))} placeholder="09xxxxxxxx" style={{ width: "100%" }} />
              {currentGuide.id && (
                <span className="text-[10px] text-gray-400 mt-1 block">Số điện thoại không được phép thay đổi qua API này</span>
              )}
            </div>{/* Địa chỉ */}<div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Địa chỉ thường trú
              </label>
              <AntInput type="text" disabled={!!currentGuide.id} value={currentGuide.address || ""} onChange={(e) => setCurrentGuide((prev) => ({ ...prev, address: e.target.value }))} placeholder="Quận/Huyện, Tỉnh/Thành Phố" style={{ width: "100%" }} />
              {currentGuide.id && (
                <span className="text-[10px] text-gray-400 mt-1 block">Địa chỉ không được phép thay đổi qua API này</span>
              )}
            </div>{/* Trạng thái */}<div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Trạng thái hoạt động
              </label>
              <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((currentGuide.status || "active") ?? "")} onChange={(e) =>
                  setCurrentGuide((prev) => ({
                    ...prev,
                    status: e as "active" | "inactive",
                  }))} style={{ width: "100%" }} options={[{ value: String("active"), label: "Đang hoạt động (Active)", disabled: false },{ value: String("inactive"), label: "Tạm dừng hoạt động (Inactive)", disabled: false }].flat().filter((option) => !!option)} />
            </div></UIFlex>
        )}
      </Modal><GuideProfileModal
        guide={profileGuide}
        onClose={() => setProfileGuide(null)}
        onSaved={(message) => {
          showToast(message, "success");
          fetchGuides();
        }}
        onError={(message) => showToast(message, "error")}
      />{/* --- CUSTOM ALERTS RENDER --- */}<Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((prev) => ({ ...prev, isOpen: false }))}
      /><ConfirmModal
        message={confirm.message}
        isOpen={confirm.isOpen}
        onConfirm={() => {
          setConfirm((prev) => ({ ...prev, isOpen: false }));
          confirm.onConfirm();
        }}
        onCancel={() => setConfirm((prev) => ({ ...prev, isOpen: false }))}
      /></UIFlex>
  );
}
