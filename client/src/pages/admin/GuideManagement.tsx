import React, { useState, useMemo } from "react";
import type { Guide } from "@/types";

const INITIAL_GUIDES: Guide[] = [
  {
    id: 1,
    name: "Lê Văn Tám",
    email: "levantam@gmail.com",
    phone: "0912123456",
    address: "Hoàn Kiếm, Hà Nội",
    avatar: null,
    status: "active",
    assigned_tours_count: 3,
    created_at: "2026-05-20 09:00:00",
  },
  {
    id: 2,
    name: "Phạm Hồng Thái",
    email: "phamhongthai@gmail.com",
    phone: "0988776655",
    address: "Hải Châu, Đà Nẵng",
    avatar: null,
    status: "active",
    assigned_tours_count: 1,
    created_at: "2026-05-25 10:15:00",
  },
  {
    id: 3,
    name: "Nguyễn Thị Định",
    email: "nguyenthidinh@gmail.com",
    phone: "0909090909",
    address: "Quận 1, TP Hồ Chí Minh",
    avatar: null,
    status: "blocked",
    assigned_tours_count: 0,
    created_at: "2026-06-01 14:30:00",
  },
  {
    id: 4,
    name: "Trần Hưng Đạo (Guide mặc định)",
    email: "guide@gmail.com",
    phone: "0977889900",
    address: "Hạ Long, Quảng Ninh",
    avatar: null,
    status: "active",
    assigned_tours_count: 2,
    created_at: "2026-06-17 16:32:37",
  },
];

export default function GuideManagement() {
  const [guides, setGuides] = useState<Guide[]>(INITIAL_GUIDES);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [currentGuide, setCurrentGuide] = useState<Partial<Guide> | null>(null);

  // Thống kê KPIs
  const stats = useMemo(() => {
    const total = guides.length;
    const active = guides.filter((g) => g.status === "active").length;
    const inactive = guides.filter((g) => g.status === "inactive").length;
    const blocked = guides.filter((g) => g.status === "blocked").length;
    return { total, active, inactive, blocked };
  }, [guides]);

  // Bộ lọc
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
      phone: "",
      address: "",
      status: "active",
      assigned_tours_count: 0,
    });
    setIsModalOpen(true);
  };

  // Mở modal Sửa
  const handleOpenEditModal = (guide: Guide) => {
    setCurrentGuide({ ...guide });
    setIsModalOpen(true);
  };

  // Lưu Form Thêm/Sửa
  const handleSaveGuide = (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentGuide || !currentGuide.name || !currentGuide.email) return;

    if (currentGuide.id) {
      // Sửa
      setGuides((prev) =>
        prev.map((g) => (g.id === currentGuide.id ? (currentGuide as Guide) : g))
      );
    } else {
      // Thêm mới
      const newId = guides.length > 0 ? Math.max(...guides.map((g) => g.id)) + 1 : 1;
      const nowStr = new Date().toISOString().replace("T", " ").substring(0, 19);
      const newGuide: Guide = {
        id: newId,
        name: currentGuide.name,
        email: currentGuide.email,
        phone: currentGuide.phone || null,
        address: currentGuide.address || null,
        avatar: null,
        status: currentGuide.status || "active",
        assigned_tours_count: 0,
        created_at: nowStr,
      };
      setGuides((prev) => [...prev, newGuide]);
    }

    setIsModalOpen(false);
    setCurrentGuide(null);
  };

  // Đổi trạng thái khóa/mở khóa nhanh
  const handleToggleStatus = (guideId: number) => {
    setGuides((prev) =>
      prev.map((g) => {
        if (g.id === guideId) {
          const nextStatus = g.status === "blocked" ? "active" : "blocked";
          return { ...g, status: nextStatus };
        }
        return g;
      })
    );
  };

  // Xóa Hướng dẫn viên
  const handleDeleteGuide = (guideId: number) => {
    if (window.confirm("Bạn có chắc chắn muốn xóa hướng dẫn viên này khỏi hệ thống?")) {
      setGuides((prev) => prev.filter((g) => g.id !== guideId));
    }
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
            Quản lý danh sách, thêm mới, cập nhật và phân công nhiệm vụ cho Hướng dẫn viên (Guide)
          </p>
        </div>
        <div>
          <button
            onClick={handleOpenCreateModal}
            className="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-semibold text-sm hover:bg-indigo-700 shadow-sm transition-colors"
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
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-indigo-50 text-indigo-600 rounded-xl group-hover:bg-indigo-100 transition-colors">
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
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-emerald-50 text-emerald-600 rounded-xl group-hover:bg-emerald-100 transition-colors">
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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Đang hoạt động</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1 text-emerald-600">{stats.active} HDV</h3>
          </div>
        </div>

        {/* Đang bị khóa */}
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-rose-50 text-rose-600 rounded-xl group-hover:bg-rose-100 transition-colors">
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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Đã tạm khóa</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1 text-rose-600">{stats.blocked} tài khoản</h3>
          </div>
        </div>
      </div>

      {/* FILTER & SEARCH */}
      <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm space-y-4">
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
              className="w-full pl-10 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50/50"
            />
          </div>

          {/* Lọc trạng thái hoạt động */}
          <div className="md:col-span-3">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white"
            >
              <option value="all">Tất cả trạng thái</option>
              <option value="active">Đang hoạt động</option>
              <option value="blocked">Đang bị khóa</option>
            </select>
          </div>

          {/* Xóa lọc nhanh */}
          <div className="md:col-span-1 flex">
            <button
              onClick={() => {
                setSearch("");
                setStatusFilter("all");
              }}
              className="w-full py-2 text-sm text-gray-500 hover:text-indigo-600 bg-gray-50 border border-gray-100 rounded-xl font-medium hover:bg-indigo-50 transition-colors"
            >
              Xóa lọc
            </button>
          </div>
        </div>
      </div>

      {/* DATA TABLE */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-gray-50 text-gray-500 text-xs font-semibold uppercase tracking-wider border-b border-gray-100">
                <th className="p-4 w-16 text-center">ID</th>
                <th className="p-4">Thông tin Hướng dẫn viên</th>
                <th className="p-4">Điện thoại / Địa chỉ</th>
                <th className="p-4 text-center">Số Tour phụ trách</th>
                <th className="p-4">Ngày tạo tài khoản</th>
                <th className="p-4 text-center">Trạng thái</th>
                <th className="p-4 text-center">Hành động</th>
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
                    <td className="p-4 text-center text-gray-500 font-mono">
                      #{guide.id}
                    </td>

                    {/* Basic Info */}
                    <td className="p-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-sm shadow-inner uppercase">
                          {guide.name.charAt(0)}
                        </div>
                        <div>
                          <p className="font-semibold text-gray-900">{guide.name}</p>
                          <p className="text-xs text-gray-400 mt-0.5 font-mono">{guide.email}</p>
                        </div>
                      </div>
                    </td>

                    {/* SĐT & Địa chỉ */}
                    <td className="p-4">
                      <div>
                        <p className="font-medium text-gray-800 font-mono text-xs">{guide.phone ?? "Chưa cập nhật"}</p>
                        <p className="text-xs text-gray-400 mt-0.5 line-clamp-1">{guide.address ?? "Không có địa chỉ"}</p>
                      </div>
                    </td>

                    {/* Số Tour phụ trách */}
                    <td className="p-4 text-center font-bold text-indigo-600">
                      {guide.assigned_tours_count} tours
                    </td>

                    {/* Ngày gia nhập */}
                    <td className="p-4 text-gray-500 text-xs">
                      {guide.created_at}
                    </td>

                    {/* Trạng thái */}
                    <td className="p-4 text-center">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${
                          guide.status === "active"
                            ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                            : "bg-rose-50 text-rose-700 border-rose-200"
                        }`}
                      >
                        <span className={`w-1.5 h-1.5 rounded-full ${guide.status === "active" ? "bg-emerald-500" : "bg-rose-500"}`}></span>
                        {guide.status === "active" ? "Hoạt động" : "Bị tạm khóa"}
                      </span>
                    </td>

                    {/* Hành động */}
                    <td className="p-4 text-center">
                      <div className="flex items-center justify-center gap-1.5">
                        <button
                          onClick={() => handleOpenEditModal(guide)}
                          className="px-2.5 py-1.5 text-xs text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 font-medium transition-colors"
                        >
                          Sửa
                        </button>
                        <button
                          onClick={() => handleToggleStatus(guide.id)}
                          className={`px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                            guide.status === "blocked"
                              ? "text-emerald-600 bg-emerald-50 hover:bg-emerald-100"
                              : "text-amber-600 bg-amber-50 hover:bg-amber-100"
                          }`}
                        >
                          {guide.status === "blocked" ? "Mở khóa" : "Khóa"}
                        </button>
                        <button
                          onClick={() => handleDeleteGuide(guide.id)}
                          className="px-2.5 py-1.5 text-xs text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 font-medium transition-colors"
                        >
                          Xóa
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* CREATE & EDIT FORM MODAL */}
      {isModalOpen && currentGuide && (
        <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center bg-black/50 p-4">
          <div className="relative bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-gray-100 overflow-hidden transform transition-all duration-300">
            {/* Modal Header */}
            <div className="bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white flex justify-between items-center">
              <div>
                <h3 className="text-lg font-bold">
                  {currentGuide.id ? `Sửa thông tin HDV: #${currentGuide.id}` : "Thêm mới Hướng dẫn viên"}
                </h3>
                <p className="text-xs text-indigo-100 mt-1">
                  {currentGuide.id ? "Cập nhật các thông tin chi tiết về nhân sự" : "Nhập đầy đủ thông tin để cấp tài khoản Guide"}
                </p>
              </div>
              <button
                onClick={() => {
                  setIsModalOpen(false);
                  setCurrentGuide(null);
                }}
                className="p-1.5 bg-white/10 hover:bg-white/20 rounded-full transition-colors focus:outline-none"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Modal Body (Form) */}
            <form onSubmit={handleSaveGuide}>
              <div className="p-6 space-y-4">
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
                    className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50/50 font-medium"
                  />
                </div>

                {/* Email */}
                <div>
                  <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Email tài khoản <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="email"
                    required
                    value={currentGuide.email || ""}
                    onChange={(e) => setCurrentGuide((prev) => ({ ...prev, email: e.target.value }))}
                    placeholder="nguyenvanan@gmail.com"
                    className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50/50 font-medium"
                  />
                </div>

                {/* SĐT */}
                <div>
                  <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Số điện thoại
                  </label>
                  <input
                    type="text"
                    value={currentGuide.phone || ""}
                    onChange={(e) => setCurrentGuide((prev) => ({ ...prev, phone: e.target.value }))}
                    placeholder="09xxxxxxxx"
                    className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50/50 font-medium"
                  />
                </div>

                {/* Địa chỉ */}
                <div>
                  <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Địa chỉ thường trú
                  </label>
                  <input
                    type="text"
                    value={currentGuide.address || ""}
                    onChange={(e) => setCurrentGuide((prev) => ({ ...prev, address: e.target.value }))}
                    placeholder="Quận/Huyện, Tỉnh/Thành Phố"
                    className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50/50 font-medium"
                  />
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
                        status: e.target.value as "active" | "inactive" | "blocked",
                      }))
                    }
                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white"
                  >
                    <option value="active">Đang hoạt động (Active)</option>
                    <option value="blocked">Khóa tài khoản (Blocked)</option>
                  </select>
                </div>
              </div>

              {/* Modal Footer */}
              <div className="bg-gray-50 px-6 py-4 flex justify-end gap-2 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => {
                    setIsModalOpen(false);
                    setCurrentGuide(null);
                  }}
                  className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-xl text-gray-700 hover:bg-gray-100 transition-colors"
                >
                  Đóng
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-indigo-600 text-sm font-semibold rounded-xl text-white hover:bg-indigo-700 shadow-sm transition-colors"
                >
                  Lưu thay đổi
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
