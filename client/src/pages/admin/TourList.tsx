import { useEffect, useState, useMemo, type ChangeEvent } from "react";
import { useNavigate } from "react-router-dom";
import type { TourSchedule } from "@/types";
import adminService from "@/services/adminService";
import type { TourDeletePreview, TrashedTour } from "@/services/adminService";
import { Toast } from "@/components/admin/CustomAlert";
import { TableActions } from "@/components/admin/TableActions";
import { Modal } from "@/components/admin/Modal";
import { AlertTriangle, Archive, Ban, RotateCcw, Trash2 } from "lucide-react";
import { formatDateTime } from "@/utils/format";

type TourStatus = "active" | "inactive" | "full";

interface Tour {
  id: number;
  title: string;
  price: number;
  status: TourStatus;
  start_location: string;
  schedules?: TourSchedule[];
}

export default function TourList() {
  const navigate = useNavigate();
  const [search, setSearch] = useState<string>("");
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);


  // --- CUSTOM ALERTS TOAST ---
  const [toast, setToast] = useState<{ message: string; type: "success" | "error" | "info"; isOpen: boolean }>({
    message: "",
    type: "success",
    isOpen: false,
  });

  const showToast = (message: string, type: "success" | "error" | "info" = "success") => {
    setToast({ message, type, isOpen: true });
  };

  /*
   * K06 - Xóa tour, đi qua một bước xem trước.
   *
   * Không dùng hộp xác nhận chung được: câu hỏi ở đây không phải "bạn có chắc không" mà là "tour
   * này có xóa được không, và nếu không thì vì sao". Cơ sở dữ liệu khai cascade từ tour xuống
   * đơn hàng, nên hậu quả của một cú bấm nhầm là mất sạch chứng từ tài chính - phải nói ra
   * trước, không phải sau.
   */
  const [deletingTour, setDeletingTour] = useState<Tour | null>(null);
  const [deletePreview, setDeletePreview] = useState<TourDeletePreview | null>(null);
  const [deleteBusy, setDeleteBusy] = useState(false);

  const openDeleteDialog = async (tour: Tour) => {
    setDeletingTour(tour);
    setDeletePreview(null);

    try {
      setDeletePreview(await adminService.getTourDeletePreview(tour.id));
    } catch (err) {
      console.error("Lỗi xem trước xóa tour:", err);
      showToast("Không đọc được thông tin tour này.", "error");
      setDeletingTour(null);
    }
  };

  const closeDeleteDialog = () => {
    setDeletingTour(null);
    setDeletePreview(null);
  };

  const chayThaoTacXoa = async (thucHien: () => Promise<string>) => {
    setDeleteBusy(true);

    try {
      showToast(await thucHien(), "success");
      closeDeleteDialog();
      fetchTours();
      fetchTrashed();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      showToast(response?.message || "Thao tác không thành công.", "error");
    } finally {
      setDeleteBusy(false);
    }
  };

  // --- Tour đã xóa, và đường khôi phục ---
  const [trashed, setTrashed] = useState<TrashedTour[]>([]);
  const [trashOpen, setTrashOpen] = useState(false);

  const fetchTrashed = async () => {
    try {
      setTrashed(await adminService.getTrashedTours());
    } catch (err) {
      console.error("Lỗi tải tour đã xóa:", err);
    }
  };

  const khoiPhuc = async (id: number) => {
    try {
      showToast(await adminService.restoreTour(id), "success");
      fetchTours();
      fetchTrashed();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      showToast(response?.message || "Không khôi phục được.", "error");
    }
  };

  // Lấy danh sách tour từ API
  const fetchTours = async () => {
    setLoading(true);
    try {
      const data = await adminService.getTours();
      setTours(data as unknown as Tour[] || []);
    } catch (err) {
      console.error("Error loading tours: ", err);
    } finally {
      setLoading(false);
    }
  };


  const [currentPage, setCurrentPage] = useState<number>(1);
  const itemsPerPage = 5;

  useEffect(() => {
    fetchTours();
    fetchTrashed();
  }, []);

  const handleSearch = (e: ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
    setCurrentPage(1);
  };

  const filteredTours = tours.filter((t) =>
    t.title.toLowerCase().includes(search.toLowerCase())
  );

  const totalItems = filteredTours.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const paginatedTours = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return filteredTours.slice(startIndex, startIndex + itemsPerPage);
  }, [filteredTours, currentPage, itemsPerPage]);

  // Thống kê nhanh KPIs
  const stats = useMemo(() => {
    const total = tours.length;
    const active = tours.filter((t) => t.status === "active").length;
    const inactive = tours.filter((t) => t.status === "inactive").length;
    const full = tours.filter((t) => t.status === "full").length;
    const avgPrice = tours.length
      ? Math.round(tours.reduce((sum, t) => sum + Number(t.price), 0) / tours.length)
      : 0;

    return { total, active, inactive, full, avgPrice };
  }, [tours]);


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
        <div className="flex items-center gap-3">
          {/*
            Chỉ hiện khi thật sự có tour đã xóa. Xóa mềm mà không có đường khôi phục thì chỉ là
            xóa cứng thêm một bước — lối vào phải nằm ngay chỗ người ta vừa xóa tour.
          */}
          {trashed.length > 0 && (
            <button
              type="button"
              onClick={() => setTrashOpen(true)}
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-canvas border border-hairline text-ink rounded-md text-button-sm hover:bg-surface-soft transition-colors cursor-pointer"
            >
              <Archive className="w-4 h-4" />
              {trashed.length} tour đã xóa
            </button>
          )}

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
      <div className="grid grid-cols-1 sm:grid-cols-5 gap-5">
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

        {/* Hết chỗ */}
        <div className="bg-white p-5 rounded-lg border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-red-50 text-red-600 rounded-xl group-hover:bg-red-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Hết chỗ</p>
            <h3 className="text-xl font-bold text-red-600 mt-1">{stats.full} Tour</h3>
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

      <div className="bg-white rounded-lg border border-gray-200 shadow-xs overflow-hidden flex flex-col">
        {loading ? (
          <div className="p-12 text-center text-gray-500 font-medium">
            Đang tải danh sách Tour du lịch...
          </div>
        ) : (
          <>
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
                  {paginatedTours.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="p-12 text-center text-gray-400">
                        Không tìm thấy Tour nào phù hợp với bộ lọc.
                      </td>
                    </tr>
                  ) : (
                    paginatedTours.map((tour) => {
                      const assignedCount =
                        tour.schedules?.filter((schedule) => (schedule.guides ?? []).length > 0)
                          .length ?? 0;
                      const scheduleCount = tour.schedules?.length ?? 0;
                      return (
                        <tr
                          key={tour.id}
                          onClick={() => navigate("/admin/tours/" + tour.id)}
                          className="cursor-pointer hover:bg-gray-50/50 transition-colors"
                        >
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
                            <span className={assignedCount > 0 ? "text-sm font-semibold text-gray-800" : "text-sm italic text-gray-400"}>
                              {scheduleCount === 0 ? (
                                "Chưa có lịch"
                              ) : (
                                <>{assignedCount}/{scheduleCount} chuyến đã phân công</>
                              )}
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
                              <span className={`w-1.5 h-1.5 rounded-full ${tour.status === "active" ? "bg-emerald-500" : tour.status === "full" ? "bg-red-500" : "bg-rose-500"}`}></span>
                              {tour.status === "active" ? "Hoạt động" : tour.status === "full" ? "Hết chỗ" : "Tạm dừng"}
                            </span>
                          </td>
                          {/* Hành động (3-dots dropdown) */}
                          <td
                            className="py-3.5 px-6 text-center"
                            onClick={(event) => event.stopPropagation()}
                          >
                            <TableActions
                              id={tour.id}
                              actions={[
                                {
                                  label: "Chỉnh sửa",
                                  onClick: () => navigate(`/admin/tours/${tour.id}/edit`),
                                  icon: (
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                  ),
                                },
                                {
                                  label: "Xem chi tiết",
                                  onClick: () => navigate("/admin/tours/" + tour.id),
                                  icon: (
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20H7v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                  ),
                                },
                                {
                                  label: "Xóa tour",
                                  hint: "Giữ nguyên đơn cũ và khôi phục lại được",
                                  onClick: () => openDeleteDialog(tour),
                                  variant: "danger",
                                  icon: <Trash2 className="w-4 h-4" />,
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

            {/* PAGINATION PANEL */}
            {totalPages >= 1 && (
              <div className="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-between flex-wrap gap-3">
                <span className="text-xs text-gray-500">
                  Hiển thị <strong className="text-gray-800">{Math.min(totalItems, (currentPage - 1) * itemsPerPage + 1)} - {Math.min(totalItems, currentPage * itemsPerPage)}</strong> trên tổng số <strong className="text-gray-800">{totalItems}</strong> Tour du lịch
                </span>

                <div className="flex items-center gap-1.5">
                  <button
                    type="button"
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage((c) => Math.max(1, c - 1))}
                    className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                  >
                    Trước
                  </button>
                  
                  {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                    <button
                      key={page}
                      type="button"
                      onClick={() => setCurrentPage(page)}
                      className={`rounded-lg px-3 py-1.5 text-xs font-bold cursor-pointer transition-all duration-150 ${
                        page === currentPage
                          ? "bg-primary-600 text-white shadow-xs"
                          : "border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                      }`}
                    >
                      {page}
                    </button>
                  ))}

                  <button
                    type="button"
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage((c) => Math.min(totalPages, c + 1))}
                    className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                  >
                    Sau
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/*
        Hộp thoại xóa tour.

        Hai kết cục, và hộp thoại đổi hẳn nội dung lẫn nút theo kết cục đó thay vì hiện một nút
        xóa rồi báo lỗi sau khi bấm:

          - Xóa được  -> nói rõ những gì sẽ mất theo, nút đỏ "Xóa vĩnh viễn".
          - Bị chặn   -> liệt kê từng thứ đang chặn kèm số lượng, và đổi nút thành "Ngừng bán".

        Hệ thống KHÔNG tự ngừng bán thay khi thấy không xóa được. Bấm "xóa" mà máy lặng lẽ làm
        việc khác là máy quyết thay người dùng; ở đây nó nói lý do rồi để họ chọn.
      */}
      <Modal
        isOpen={!!deletingTour}
        onClose={closeDeleteDialog}
        title={`Xóa tour: ${deletingTour?.title ?? ""}`}
        subtitle="Tour biến mất khỏi mọi danh sách nhưng dữ liệu vẫn còn nguyên, và khôi phục lại được."
        size="lg"
        footer={
          <>
            <button
              type="button"
              onClick={closeDeleteDialog}
              disabled={deleteBusy}
              className="px-4 py-2 bg-canvas border border-hairline text-button-sm rounded-lg text-ink hover:bg-surface-soft cursor-pointer"
            >
              Quay lại
            </button>

            {/*
              Ngừng bán luôn hiện nếu tour còn đang bán, kể cả khi xóa được. Hai việc khác nhau
              chứ không phải phương án dự phòng của nhau: ngừng bán giữ tour trong màn quản trị,
              xóa thì bỏ luôn khỏi danh sách.
            */}
            {deletePreview && !deletePreview.already_retired && (
              <button
                type="button"
                onClick={() => chayThaoTacXoa(() => adminService.retireTour(deletingTour!.id))}
                disabled={deleteBusy}
                className="inline-flex items-center gap-2 px-4 py-2 bg-canvas border border-hairline text-button-sm rounded-lg text-ink hover:bg-surface-soft disabled:opacity-40 cursor-pointer"
              >
                <Ban className="w-4 h-4" />
                Ngừng bán
              </button>
            )}

            {deletePreview?.can_delete && (
              <button
                type="button"
                onClick={() => chayThaoTacXoa(() => adminService.deleteTour(deletingTour!.id))}
                disabled={deleteBusy}
                className="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-button-sm rounded-lg hover:bg-rose-700 disabled:opacity-40 cursor-pointer"
              >
                <Trash2 className="w-4 h-4" />
                {deleteBusy ? "Đang xóa..." : "Xóa tour"}
              </button>
            )}
          </>
        }
      >
        {!deletePreview ? (
          <p className="text-body-sm text-muted">Đang kiểm tra tour...</p>
        ) : deletePreview.can_delete ? (
          <div className="space-y-4">
            <p className="rounded-lg bg-emerald-50 px-4 py-3 text-body-sm text-emerald-900">
              Tour sẽ biến mất khỏi trang khách và khỏi danh sách này, <b>nhưng không mất dữ liệu
              nào</b>. Bấm nhầm thì khôi phục lại được.
            </p>

            {/*
              Liệt kê những thứ Ở LẠI, không phải những thứ mất đi. Đây là điểm khác biệt của xóa
              mềm và cũng là câu người bấm cần nghe: đơn hàng của khách không đi đâu cả.
            */}
            <div>
              <p className="text-caption text-muted mb-2">Vẫn giữ nguyên:</p>
              <ul className="space-y-1 text-body-sm text-body">
                <li>{deletePreview.preserved.bookings} đơn đặt tour, kèm hành khách và sổ tiền</li>
                <li>{deletePreview.preserved.schedules} lịch khởi hành</li>
                <li>{deletePreview.preserved.reviews} đánh giá của khách</li>
                <li>{deletePreview.preserved.group_requests} yêu cầu booking đoàn</li>
              </ul>
            </div>

            {!deletePreview.already_retired && (
              <p className="text-body-sm text-muted">
                Nếu chỉ muốn tạm dừng bán mà vẫn giữ tour trong danh sách quản trị thì chọn{" "}
                <b>ngừng bán</b> thay vì xóa.
              </p>
            )}
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex gap-3 rounded-lg bg-amber-50 px-4 py-3">
              <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
              <p className="text-body-sm text-amber-900">
                Chưa xóa tour này được — vẫn còn đoàn đang trông vào nó.
              </p>
            </div>

            <ul className="space-y-2">
              {deletePreview.blockers.map((item) => (
                <li
                  key={item.key}
                  className="rounded-lg border border-hairline-soft px-4 py-3 text-body-sm text-body"
                >
                  {item.message}
                </li>
              ))}
            </ul>

            {deletePreview.already_retired ? (
              <p className="text-body-sm text-muted">
                Tour này hiện <b>đã ngừng bán</b> nên không nhận khách mới. Đợi chuyến chạy xong
                rồi xóa.
              </p>
            ) : (
              <p className="text-body-sm text-muted">
                Muốn thôi nhận khách mới ngay bây giờ thì chọn <b>ngừng bán</b> — chuyến đã chốt
                vẫn chạy đúng cam kết.
              </p>
            )}
          </div>
        )}
      </Modal>

      {/* Tour đã xóa, và nút khôi phục từng cái. */}
      <Modal
        isOpen={trashOpen}
        onClose={() => setTrashOpen(false)}
        title="Tour đã xóa"
        subtitle="Không hiện trên trang khách và trong danh sách quản trị. Dữ liệu vẫn còn nguyên."
        size="2xl"
        footer={
          <button
            type="button"
            onClick={() => setTrashOpen(false)}
            className="px-4 py-2 bg-canvas border border-hairline text-button-sm rounded-lg text-ink hover:bg-surface-soft cursor-pointer"
          >
            Đóng
          </button>
        }
      >
        {trashed.length === 0 ? (
          <p className="text-body-sm text-muted">Không có tour nào đã xóa.</p>
        ) : (
          <div className="space-y-2">
            {trashed.map((item) => (
              <div
                key={item.id}
                className="flex flex-wrap items-center gap-3 rounded-lg border border-hairline-soft px-4 py-3"
              >
                <div className="min-w-0 flex-1">
                  <p className="text-title-sm text-ink">{item.title}</p>
                  <p className="text-caption-sm text-muted mt-0.5">
                    {item.start_location} · xóa lúc {formatDateTime(item.deleted_at ?? "")}
                    {item.bookings_count > 0 && ` · giữ ${item.bookings_count} đơn`}
                  </p>
                </div>

                <button
                  type="button"
                  onClick={() => khoiPhuc(item.id)}
                  className="inline-flex items-center gap-2 px-3 py-2 bg-canvas border border-hairline text-button-sm rounded-lg text-ink hover:bg-surface-soft cursor-pointer"
                >
                  <RotateCcw className="w-4 h-4" />
                  Khôi phục
                </button>
              </div>
            ))}
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



