import React, { useState, useEffect, useMemo } from "react";
import type { Guide } from "@/types";
import adminService from "@/services/adminService";
import { Toast, ConfirmModal } from "@/components/admin/CustomAlert";
import { TableActions } from "@/components/admin/TableActions";
import { Modal } from "@/components/admin/Modal";

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
    <div className="space-y-6">
      {/* HEADER */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
            Quản lý Hướng dẫn viên
          </h1>
          <p className="text-sm text-gray-500">
            Quản lý danh sách, cấp tài khoản và phân công nhiệm vụ cho Hướng dẫn viên (Guide)
          </p>
        </div>
        <div>
          <button
            onClick={handleOpenCreateModal}
            className="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 text-white rounded-md font-semibold text-sm hover:bg-primary-700 shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 cursor-pointer"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Thêm hướng dẫn viên
          </button>
        </div>
      </div>

      {/* KPI STATS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
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
      </div>

      {/* FILTER & SEARCH */}
      <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
          {/* Thanh tìm kiếm */}
          <div className="relative md:col-span-8">
            <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>
            </span>
            <input
              type="text"
              placeholder="Tìm kiếm hướng dẫn viên theo tên, email, điện thoại, địa chỉ..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50"
            />
          </div>

          {/* Lọc trạng thái hoạt động */}
          <div className="md:col-span-3">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="all">Tất cả trạng thái</option>
              <option value="active">Đang hoạt động</option>
              <option value="inactive">Tạm dừng hoạt động</option>
            </select>
          </div>

          {/* Xóa lọc nhanh */}
          <div className="md:col-span-1 flex">
            <button
              onClick={() => {
                setSearch("");
                setStatusFilter("all");
              }}
              className="w-full py-2 text-sm text-gray-500 hover:text-primary-600 bg-gray-50 border border-gray-100 rounded-md font-medium hover:bg-primary-50 transition-colors cursor-pointer"
            >
              Xóa lọc
            </button>
          </div>
        </div>
      </div>

      {/* DATA TABLE */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 font-medium">
            Đang tải danh sách Hướng dẫn viên...
          </div>
        ) : (
          <div className="overflow-x-visible">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-gray-200">
                  <th className="py-3.5 px-6 w-16 text-center">ID</th>
                  <th className="py-3.5 px-6">Thông tin Hướng dẫn viên</th>
                  <th className="py-3.5 px-6">Điện thoại / Địa chỉ</th>
                  <th className="py-3.5 text-center px-6">Số Tour phụ trách</th>
                  <th className="py-3.5 px-6">Ngày tạo tài khoản</th>
                  <th className="py-3.5 text-center px-6">Trạng thái</th>
                  <th className="py-3.5 text-center px-6">Hành động</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-sm">
                {filteredGuides.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="p-12 text-center text-gray-400">
                      Không tìm thấy Hướng dẫn viên nào phù hợp.
                    </td>
                  </tr>
                ) : (
                  filteredGuides.map((guide) => (
                    <tr key={guide.id} className="hover:bg-gray-50/50 transition-colors">
                      {/* ID */}
                      <td className="py-3.5 px-6 text-center text-gray-500 font-mono">
                        #{guide.id}
                      </td>

                      {/* Basic Info */}
                      <td className="py-3.5 px-6">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-primary-600 text-white font-bold flex items-center justify-center text-sm shadow-inner uppercase">
                            {guide.name.charAt(0)}
                          </div>
                          <div>
                            <p className="font-semibold text-gray-900">{guide.name}</p>
                            <p className="text-xs text-gray-400 mt-0.5 font-mono">{guide.email}</p>
                          </div>
                        </div>
                      </td>

                      {/* SĐT & Địa chỉ */}
                      <td className="py-3.5 px-6">
                        <div>
                          <p className="font-medium text-gray-800 font-mono text-xs">{guide.phone ?? "Chưa cập nhật"}</p>
                          <p className="text-xs text-gray-400 mt-0.5 line-clamp-1">{guide.address ?? "Không có địa chỉ"}</p>
                        </div>
                      </td>

                      {/* Số Tour phụ trách */}
                      <td className="py-3.5 px-6 text-center font-bold text-primary-600">
                        {guide.assigned_tours_count ?? 0} tours
                      </td>

                      {/* Ngày gia nhập */}
                      <td className="py-3.5 px-6 text-gray-500 text-xs">
                        {guide.created_at}
                      </td>

                      {/* Trạng thái */}
                      <td className="py-3.5 px-6 text-center">
                        <span
                          className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-semibold border ${guide.status === "active"
                            ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                            : "bg-rose-50 text-rose-700 border-rose-200"
                            }`}
                        >
                          <span className={`w-1.5 h-1.5 rounded-full ${guide.status === "active" ? "bg-emerald-500" : "bg-rose-500"}`}></span>
                          {guide.status === "active" ? "Hoạt động" : "Tạm dừng"}
                        </span>
                      </td>

                      {/* Hành động */}
                      <td className="py-3.5 px-6 text-center">
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
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* PAGINATION */}
        {!loading && totalPages > 1 && (
          <div className="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-100 sm:px-6">
            <div className="flex-1 flex justify-between sm:hidden">
              <button
                disabled={currentPage === 1}
                onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
                className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
              >
                Trước
              </button>
              <button
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
                className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
              >
                Sau
              </button>
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-xs text-gray-500">
                  Hiển thị trang <span className="font-semibold text-gray-700">{currentPage}</span> / <span className="font-semibold text-gray-700">{totalPages}</span> trang (Tổng <span className="font-semibold text-gray-700">{totalGuidesCount}</span> HDV)
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <button
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage(1)}
                    className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Đầu
                  </button>
                  <button
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
                    className="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Trước
                  </button>
                  <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-primary-50 text-sm font-semibold text-primary-600">
                    {currentPage}
                  </span>
                  <button
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
                    className="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Sau
                  </button>
                  <button
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage(totalPages)}
                    className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Cuối
                  </button>
                </nav>
              </div>
            </div>
          </div>
        )}
      </div>
      {/* CREATE & EDIT FORM MODAL */}
      <Modal
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
            <button
              type="button"
              onClick={() => {
                setIsModalOpen(false);
                setCurrentGuide(null);
              }}
              className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 transition-all focus:outline-none cursor-pointer"
            >
              Đóng
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-primary-600 text-sm font-semibold rounded-md text-white hover:bg-primary-700 shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 cursor-pointer"
            >
              Lưu thay đổi
            </button>
          </>
        }
      >
        {currentGuide && (
          <div className="space-y-4">
            {/* Họ tên */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Họ và tên <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                required
                value={currentGuide.name || ""}
                onChange={(e) => setCurrentGuide((prev) => ({ ...prev, name: e.target.value }))}
                placeholder="Nhập họ tên hướng dẫn viên"
                className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium"
              />
            </div>

            {/* Email */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Email tài khoản {!currentGuide.id && <span className="text-rose-500">*</span>}
              </label>
              <input
                type="email"
                required={!currentGuide.id}
                disabled={!!currentGuide.id}
                value={currentGuide.email || ""}
                onChange={(e) => setCurrentGuide((prev) => ({ ...prev, email: e.target.value }))}
                placeholder="nguyenvanan@gmail.com"
                className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-400 bg-gray-50/50 font-medium"
              />
              {currentGuide.id && (
                <span className="text-[10px] text-gray-400 mt-1 block">Email không được phép thay đổi sau khi tạo</span>
              )}
            </div>

            {/* Password (Chỉ cho tạo mới) */}
            {!currentGuide.id && (
              <div>
                <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                  Mật khẩu khởi tạo <span className="text-rose-500">*</span>
                </label>
                <input
                  type="password"
                  required
                  value={currentGuide.password || ""}
                  onChange={(e) => setCurrentGuide((prev) => ({ ...prev, password: e.target.value }))}
                  placeholder="Tối thiểu 6 ký tự"
                  className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50 font-medium"
                />
              </div>
            )}

            {/* SĐT */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Số điện thoại
              </label>
              <input
                type="text"
                disabled={!!currentGuide.id}
                value={currentGuide.phone || ""}
                onChange={(e) => setCurrentGuide((prev) => ({ ...prev, phone: e.target.value }))}
                placeholder="09xxxxxxxx"
                className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-400 bg-gray-50/50 font-medium"
              />
              {currentGuide.id && (
                <span className="text-[10px] text-gray-400 mt-1 block">Số điện thoại không được phép thay đổi qua API này</span>
              )}
            </div>

            {/* Địa chỉ */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Địa chỉ thường trú
              </label>
              <input
                type="text"
                disabled={!!currentGuide.id}
                value={currentGuide.address || ""}
                onChange={(e) => setCurrentGuide((prev) => ({ ...prev, address: e.target.value }))}
                placeholder="Quận/Huyện, Tỉnh/Thành Phố"
                className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-400 bg-gray-50/50 font-medium"
              />
              {currentGuide.id && (
                <span className="text-[10px] text-gray-400 mt-1 block">Địa chỉ không được phép thay đổi qua API này</span>
              )}
            </div>

            {/* Trạng thái */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                Trạng thái hoạt động
              </label>
              <select
                value={currentGuide.status || "active"}
                onChange={(e) =>
                  setCurrentGuide((prev) => ({
                    ...prev,
                    status: e.target.value as "active" | "inactive",
                  }))
                }
                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
              >
                <option value="active">Đang hoạt động (Active)</option>
                <option value="inactive">Tạm dừng hoạt động (Inactive)</option>
              </select>
            </div>
          </div>
        )}
      </Modal>

      {/* --- CUSTOM ALERTS RENDER --- */}
      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((prev) => ({ ...prev, isOpen: false }))}
      />

      <ConfirmModal
        message={confirm.message}
        isOpen={confirm.isOpen}
        onConfirm={() => {
          setConfirm((prev) => ({ ...prev, isOpen: false }));
          confirm.onConfirm();
        }}
        onCancel={() => setConfirm((prev) => ({ ...prev, isOpen: false }))}
      />
    </div>
  );
}
