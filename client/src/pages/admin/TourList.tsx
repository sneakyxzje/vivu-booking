import { useEffect, useState, useMemo, type ChangeEvent } from "react";
import type { Guide } from "@/types";
import adminService from "@/services/adminService";
import tourService from "@/services/tourService";
import { Toast } from "@/components/admin/CustomAlert";
import { TableActions } from "@/components/admin/TableActions";
import { Modal } from "@/components/admin/Modal";

type TourStatus = "active" | "inactive";

interface Tour {
  id: number;
  title: string;
  price: string;
  status: TourStatus;
  start_location: string;
  guide_id?: number | null;
}

export default function TourList() {
  const [search, setSearch] = useState<string>("");
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);

  // Danh sách Hướng dẫn viên (Fetch từ API)
  const [guides, setGuides] = useState<Guide[]>([]);

  // States quản lý chỉ định HDV
  const [selectedTour, setSelectedTour] = useState<Tour | null>(null);
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);

  // --- CUSTOM ALERTS TOAST ---
  const [toast, setToast] = useState<{ message: string; type: "success" | "error" | "info"; isOpen: boolean }>({
    message: "",
    type: "success",
    isOpen: false,
  });

  const showToast = (message: string, type: "success" | "error" | "info" = "success") => {
    setToast({ message, type, isOpen: true });
  };

  // Lấy danh sách tour từ API
  const fetchTours = async () => {
    setLoading(true);
    try {
      const res = await tourService.getAll();
      if (res.success) {
        setTours(res.data as unknown as Tour[] || []);
      }
    } catch (err) {
      console.error("Error loading tours: ", err);
    } finally {
      setLoading(false);
    }
  };

  // Lấy danh sách Hướng dẫn viên để phân công
  const fetchGuides = async () => {
    try {
      const res = await adminService.getGuides();
      if (res) {
        setGuides(res.data.filter((g) => g.status === "active") || []);
      }
    } catch (err) {
      console.error("Error loading guides: ", err);
    }
  };

  useEffect(() => {
    fetchTours();
    fetchGuides();
  }, []);

  const handleSearch = (e: ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const filteredTours = tours.filter((t) =>
    t.title.toLowerCase().includes(search.toLowerCase())
  );

  // Thống kê nhanh KPIs
  const stats = useMemo(() => {
    const total = tours.length;
    const active = tours.filter((t) => t.status === "active").length;
    const inactive = tours.filter((t) => t.status === "inactive").length;
    const avgPrice = tours.length
      ? Math.round(tours.reduce((sum, t) => sum + Number(t.price), 0) / tours.length)
      : 0;

    return { total, active, inactive, avgPrice };
  }, [tours]);

  // Mở modal chỉ định HDV
  const openAssignModal = (tour: Tour) => {
    setSelectedTour(tour);
    setIsAssignModalOpen(true);
  };

  // Xác nhận chỉ định HDV qua API
  const handleConfirmAssign = async (guideId: number | null) => {
    if (!selectedTour) return;

    try {
      const success = await adminService.assignGuideToTour(selectedTour.id, guideId);
      if (success) {
        setTours((prev) =>
          prev.map((t) => {
            if (t.id === selectedTour.id) {
              return {
                ...t,
                guide_id: guideId,
              };
            }
            return t;
          })
        );
        showToast("Chỉ định hướng dẫn viên cho tour thành công!", "success");
      }
    } catch (err) {
      console.error("Lỗi chỉ định HDV cho tour: ", err);
      showToast("Đã xảy ra lỗi trong quá trình phân công.", "error");
    } finally {
      setIsAssignModalOpen(false);
      setSelectedTour(null);
    }
  };

  return (
    <div className="space-y-6">
      {/* HEADER */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
            Danh sách Tour du lịch
          </h1>
          <p className="text-sm text-gray-500">
            Xem danh sách, quản lý cấu trúc, chỉ định và phân công hướng dẫn viên cho các chương trình tour
          </p>
        </div>
        <div>
          <a
            href="/admin/tours/create"
            className="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 text-white rounded-md font-semibold text-sm hover:bg-primary-700 shadow-xs transition-colors cursor-pointer"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Thêm Tour mới
          </a>
        </div>
      </div>

      {/* KPI STATS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-5">
        {/* Tổng số Tour */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-primary-50 text-primary-600 rounded-md group-hover:bg-primary-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Tổng số Tour</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.total} chương trình</h3>
          </div>
        </div>

        {/* Đang hoạt động */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-emerald-50 text-emerald-600 rounded-md group-hover:bg-emerald-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Đang hoạt động</p>
            <h3 className="text-xl font-bold text-emerald-600 mt-1">{stats.active} Tour</h3>
          </div>
        </div>

        {/* Tạm ngưng hoạt động */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-rose-50 text-rose-600 rounded-md group-hover:bg-rose-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Tạm dừng</p>
            <h3 className="text-xl font-bold text-rose-600 mt-1">{stats.inactive} Tour</h3>
          </div>
        </div>

        {/* Giá trung bình */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-amber-50 text-amber-600 rounded-md group-hover:bg-amber-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Giá trung bình</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.avgPrice.toLocaleString()}đ</h3>
          </div>
        </div>
      </div>

      {/* FILTER & SEARCH */}
      <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
          {/* Thanh tìm kiếm */}
          <div className="relative md:col-span-11">
            <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              type="text"
              placeholder="Tìm kiếm tour theo tên hoặc địa điểm khởi hành..."
              value={search}
              onChange={handleSearch}
              className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50"
            />
          </div>

          {/* Xóa lọc */}
          <div className="md:col-span-1 flex">
            <button
              onClick={() => setSearch("")}
              className="w-full py-2.5 text-sm text-gray-500 hover:text-primary-600 bg-gray-50 border border-gray-100 rounded-md font-medium hover:bg-primary-50 transition-colors cursor-pointer"
            >
              Xóa lọc
            </button>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg border border-gray-200 shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 font-medium">
            Đang tải danh sách Tour du lịch...
          </div>
        ) : (
          <div className="overflow-x-visible">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-gray-200">
                  <th className="py-3.5 px-6 w-16 text-center">ID</th>
                  <th className="py-3.5 px-6 w-96">Tên chương trình Tour</th>
                  <th className="py-3.5 px-6">Điểm khởi hành</th>
                  <th className="py-3.5 text-right px-6">Giá gốc</th>
                  <th className="py-3.5 px-6">Hướng dẫn viên</th>
                  <th className="py-3.5 text-center px-6">Trạng thái</th>
                  <th className="py-3.5 text-center px-6">Hành động</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100 text-sm">
                {filteredTours.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="p-12 text-center text-gray-400">
                      Không tìm thấy Tour nào phù hợp với bộ lọc.
                    </td>
                  </tr>
                ) : (
                  filteredTours.map((tour) => {
                    const assignedGuide = guides.find((g) => g.id === tour.guide_id);
                    return (
                      <tr key={tour.id} className="hover:bg-gray-50/50 transition-colors">
                        {/* ID */}
                        <td className="py-3.5 px-6 text-center text-gray-500 font-mono">
                          #{tour.id}
                        </td>

                        {/* Tên Tour */}
                        <td className="py-3.5 px-6 font-semibold text-gray-900 max-w-sm">
                          {tour.title}
                        </td>

                        {/* Điểm đi */}
                        <td className="py-3.5 px-6 text-gray-700">
                          {tour.start_location}
                        </td>

                        {/* Giá */}
                        <td className="py-3.5 px-6 text-right font-bold text-gray-900">
                          {Number(tour.price).toLocaleString()} đ
                        </td>

                        {/* Cột Hướng dẫn viên */}
                        <td className="py-3.5 px-6">
                          <span className={`text-sm ${assignedGuide ? "text-gray-800 font-semibold" : "text-gray-400 italic"}`}>
                            {assignedGuide ? assignedGuide.name : "Chưa chỉ định"}
                          </span>
                        </td>

                        {/* Trạng thái */}
                        <td className="py-3.5 px-6 text-center">
                          <span
                            className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-semibold border ${tour.status === "active"
                                ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                                : "bg-rose-50 text-rose-700 border-rose-200"
                              }`}
                          >
                            <span className={`w-1.5 h-1.5 rounded-full ${tour.status === "active" ? "bg-emerald-500" : "bg-rose-500"}`}></span>
                            {tour.status === "active" ? "Hoạt động" : "Tạm dừng"}
                          </span>
                        </td>
                        {/* Hành động (3-dots dropdown) */}
                        <td className="py-3.5 px-6 text-center">
                          <TableActions
                            id={tour.id}
                            actions={[
                              {
                                label: "Chỉnh sửa",
                                onClick: () => showToast("Tính năng sửa tour đang cập nhật!", "info"),
                                icon: (
                                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                  </svg>
                                ),
                              },
                              {
                                label: "Phân công HDV",
                                onClick: () => openAssignModal(tour),
                                icon: (
                                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                  </svg>
                                ),
                              },
                              {
                                label: "Xóa tour",
                                onClick: () => showToast("Tính năng xóa tour đang cập nhật!", "info"),
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
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* ASSIGN GUIDE MODAL */}
      <Modal
        isOpen={isAssignModalOpen}
        onClose={() => {
          setIsAssignModalOpen(false);
          setSelectedTour(null);
        }}
        title="Chỉ định Hướng dẫn viên"
        subtitle="Chọn hướng dẫn viên phụ trách hành trình tour"
        size="md"
        footer={
          <button
            type="button"
            onClick={() => {
              setIsAssignModalOpen(false);
              setSelectedTour(null);
            }}
            className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none cursor-pointer"
          >
            Hủy bỏ
          </button>
        }
      >
        {selectedTour && (
          <div className="space-y-4">
            <div className="bg-primary-50/50 p-4 rounded-lg border border-primary-100/50">
              <p className="text-xs text-primary-600 font-semibold uppercase tracking-wider">Tên chương trình Tour</p>
              <p className="text-sm font-bold text-gray-900 mt-1.5 leading-relaxed">{selectedTour.title}</p>
            </div>

            <div className="space-y-2.5">
              <label className="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-2">
                Lựa chọn Hướng dẫn viên phụ trách
              </label>
              <div className="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                {/* Option: Bỏ chỉ định */}
                <label className="flex items-center gap-3 p-3.5 border rounded-lg hover:bg-gray-50/50 cursor-pointer transition-colors border-gray-200 select-none">
                  <input
                    type="radio"
                    name="guideSelect"
                    checked={selectedTour.guide_id === null || selectedTour.guide_id === undefined}
                    onChange={() => handleConfirmAssign(null)}
                    className="text-primary-600 focus:ring-primary-500 w-4 h-4"
                  />
                  <div>
                    <p className="text-sm font-bold text-gray-750">Chưa chỉ định (Trống)</p>
                    <p className="text-xs text-gray-400 mt-0.5">Không có hướng dẫn viên phụ trách tour này</p>
                  </div>
                </label>

                {/* List active guides */}
                {guides.map((guide) => (
                  <label
                    key={guide.id}
                    className="flex items-center gap-3 p-3.5 border rounded-lg hover:bg-gray-50/50 cursor-pointer transition-colors border-gray-200 select-none"
                  >
                    <input
                      type="radio"
                      name="guideSelect"
                      checked={selectedTour.guide_id === guide.id}
                      onChange={() => handleConfirmAssign(guide.id)}
                      className="text-primary-600 focus:ring-primary-500 w-4 h-4"
                    />
                    <div className="flex-1">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-bold text-gray-900 leading-tight">{guide.name}</p>
                        <span className="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 rounded font-bold shrink-0">
                          Sẵn sàng
                        </span>
                      </div>
                      <p className="text-xs text-gray-400 mt-1 font-mono">{guide.email}</p>
                      <p className="text-xs text-gray-400 mt-0.5 font-mono">{guide.phone ?? "Không có SĐT"}</p>
                    </div>
                  </label>
                ))}
              </div>
            </div>
          </div>
        )}
      </Modal>

      {/* --- CUSTOM ALERT TOAST --- */}
      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((prev) => ({ ...prev, isOpen: false }))}
      />
    </div>
  );
}