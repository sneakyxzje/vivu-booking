import { useEffect, useState, type ChangeEvent } from "react";
import type { Guide } from "@/types";

type TourStatus = "active" | "inactive";

interface Tour {
  id: number;
  title: string;
  price: string;
  status: TourStatus;
  start_location: string;
  guide_id?: number | null;
  guide_name?: string | null;
}

// Danh sách Hướng dẫn viên khả dụng (Mock) để chọn
const MOCK_ACTIVE_GUIDES: Guide[] = [
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

export default function TourList() {
  const [search, setSearch] = useState<string>("");
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);

  // States quản lý chỉ định HDV
  const [selectedTour, setSelectedTour] = useState<Tour | null>(null);
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);

  // 🔥 Fetch API
  useEffect(() => {
    fetch("http://localhost:8000/api/tours")
      .then((res) => res.json())
      .then((res) => {
        const fetchedTours = res.data || [];
        // Map giả lập gán ngẫu nhiên một số hướng dẫn viên ban đầu cho sinh động
        const toursWithGuides = fetchedTours.map((tour: Tour, index: number) => {
          if (index % 3 === 0) {
            return {
              ...tour,
              guide_id: MOCK_ACTIVE_GUIDES[0].id,
              guide_name: MOCK_ACTIVE_GUIDES[0].name,
            };
          } else if (index % 3 === 1) {
            return {
              ...tour,
              guide_id: MOCK_ACTIVE_GUIDES[1].id,
              guide_name: MOCK_ACTIVE_GUIDES[1].name,
            };
          }
          return {
            ...tour,
            guide_id: null,
            guide_name: null,
          };
        });
        setTours(toursWithGuides);
      })
      .catch((err) => {
        console.error("Error loading tours: ", err);
      })
      .finally(() => setLoading(false));
  }, []);

  const handleSearch = (e: ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const filteredTours = tours.filter((t) =>
    t.title.toLowerCase().includes(search.toLowerCase())
  );

  // Mở modal chỉ định HDV
  const openAssignModal = (tour: Tour) => {
    setSelectedTour(tour);
    setIsAssignModalOpen(true);
  };

  // Xác nhận chỉ định HDV
  const handleConfirmAssign = (guideId: number | null) => {
    if (!selectedTour) return;

    const assignedGuide = MOCK_ACTIVE_GUIDES.find((g) => g.id === guideId);

    setTours((prev) =>
      prev.map((t) => {
        if (t.id === selectedTour.id) {
          return {
            ...t,
            guide_id: guideId,
            guide_name: assignedGuide ? assignedGuide.name : null,
          };
        }
        return t;
      })
    );

    setIsAssignModalOpen(false);
    setSelectedTour(null);
  };

  return (
    <div className="p-8 bg-gray-100 min-h-screen">
      {/* Header */}
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Danh sách Tour</h1>

        <a
          href="/admin/tours/create"
          className="bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-800"
        >
          + Thêm Tour
        </a>
      </div>

      {/* Search */}
      <div className="bg-white p-4 rounded-xl shadow mb-6">
        <input
          type="text"
          placeholder="Tìm kiếm tour..."
          value={search}
          onChange={handleSearch}
          className="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring"
        />
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow overflow-hidden">
        {loading ? (
          <div className="p-6 text-center text-gray-500">
            Đang tải dữ liệu...
          </div>
        ) : (
          <table className="w-full text-left">
            <thead className="bg-slate-900 text-white">
              <tr>
                <th className="p-3 w-16">ID</th>
                <th className="p-3 w-72">Tên tour</th>
                <th className="p-3">Điểm đi</th>
                <th className="p-3">Giá</th>
                <th className="p-3">Hướng dẫn viên</th>
                <th className="p-3">Trạng thái</th>
                <th className="p-3 text-right">Hành động</th>
              </tr>
            </thead>

            <tbody>
              {filteredTours.map((tour) => (
                <tr key={tour.id} className="border-b hover:bg-gray-50">
                  <td className="p-3">{tour.id}</td>

                  <td className="p-3 font-medium">{tour.title}</td>

                  <td className="p-3">{tour.start_location}</td>

                  <td className="p-3 text-green-600 font-semibold">
                    {Number(tour.price).toLocaleString()} đ
                  </td>

                  {/* Cột Hướng dẫn viên */}
                  <td className="p-3">
                    <div className="flex items-center gap-2">
                      <span className={`text-sm ${tour.guide_name ? "text-gray-800 font-medium" : "text-gray-400 italic"}`}>
                        {tour.guide_name || "Chưa chỉ định"}
                      </span>
                      <button
                        onClick={() => openAssignModal(tour)}
                        title="Thay đổi hướng dẫn viên"
                        className="p-1 text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                      >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                          />
                        </svg>
                      </button>
                    </div>
                  </td>

                  <td className="p-3">
                    <span
                      className={`px-2 py-1 rounded text-sm ${
                        tour.status === "active"
                          ? "bg-green-100 text-green-700"
                          : "bg-red-100 text-red-700"
                      }`}
                    >
                      {tour.status === "active" ? "Hoạt động" : "Tạm dừng"}
                    </span>
                  </td>

                  <td className="p-3 text-right space-x-2">
                    <button className="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                      Sửa
                    </button>

                    <button className="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                      Xóa
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {!loading && filteredTours.length === 0 && (
          <div className="p-6 text-center text-gray-500">
            Không tìm thấy tour nào
          </div>
        )}
      </div>

      {/* ASSIGN GUIDE MODAL */}
      {isAssignModalOpen && selectedTour && (
        <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden border border-gray-100">
            {/* Modal Header */}
            <div className="bg-slate-900 p-4 text-white flex justify-between items-center">
              <h3 className="font-bold">Chỉ định Hướng dẫn viên</h3>
              <button
                onClick={() => {
                  setIsAssignModalOpen(false);
                  setSelectedTour(null);
                }}
                className="text-gray-400 hover:text-white transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-5 space-y-4">
              <div>
                <p className="text-xs text-gray-400 font-semibold uppercase tracking-wider">Tên tour du lịch</p>
                <p className="text-sm font-semibold text-gray-900 mt-1">{selectedTour.title}</p>
              </div>

              <div>
                <label className="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-2">
                  Lựa chọn Hướng dẫn viên phụ trách
                </label>
                <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
                  {/* Option: Bỏ chỉ định */}
                  <label className="flex items-center gap-3 p-3 border rounded-xl hover:bg-gray-50 cursor-pointer transition-colors border-gray-200">
                    <input
                      type="radio"
                      name="guideSelect"
                      checked={selectedTour.guide_id === null || selectedTour.guide_id === undefined}
                      onChange={() => handleConfirmAssign(null)}
                      className="text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                      <p className="text-sm font-semibold text-gray-700">Chưa chỉ định (Trống)</p>
                      <p className="text-xs text-gray-400">Không có hướng dẫn viên phụ trách tour này</p>
                    </div>
                  </label>

                  {/* List active guides */}
                  {MOCK_ACTIVE_GUIDES.map((guide) => (
                    <label
                      key={guide.id}
                      className="flex items-center gap-3 p-3 border rounded-xl hover:bg-gray-50 cursor-pointer transition-colors border-gray-200"
                    >
                      <input
                        type="radio"
                        name="guideSelect"
                        checked={selectedTour.guide_id === guide.id}
                        onChange={() => handleConfirmAssign(guide.id)}
                        className="text-indigo-600 focus:ring-indigo-500"
                      />
                      <div className="flex-1">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-bold text-gray-900">{guide.name}</p>
                          <span className="text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-200 px-1.5 py-0.5 rounded font-semibold">
                            Active
                          </span>
                        </div>
                        <p className="text-xs text-gray-500 mt-0.5 font-mono">{guide.email}</p>
                        <p className="text-xs text-gray-400 mt-0.5">{guide.phone ?? "Không có SĐT"}</p>
                      </div>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="bg-gray-50 px-5 py-3.5 flex justify-end gap-2 border-t">
              <button
                type="button"
                onClick={() => {
                  setIsAssignModalOpen(false);
                  setSelectedTour(null);
                }}
                className="px-4 py-2 bg-white border border-gray-200 text-xs font-semibold rounded-lg text-gray-700 hover:bg-gray-100 transition-colors"
              >
                Hủy bỏ
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}