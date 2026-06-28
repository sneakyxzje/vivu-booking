import { useEffect, useState, type ChangeEvent } from "react";

type TourStatus = "active" | "inactive";

interface Tour {
  id: number;
  title: string;
  price: string;
  status: TourStatus;
  start_location: string;
}

export default function TourList() {
  const [search, setSearch] = useState<string>("");
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);

  // 🔥 Fetch API
  useEffect(() => {
    fetch("http://localhost:8000/api/tours")
      .then((res) => res.json())
      .then((res) => {
        setTours(res.data || []);
      })
      .finally(() => setLoading(false));
  }, []);

  const handleSearch = (e: ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const filteredTours = tours.filter((t) =>
    t.title.toLowerCase().includes(search.toLowerCase())
  );

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
                <th className="p-3">ID</th>
                <th className="p-3">Tên tour</th>
                <th className="p-3">Điểm đi</th>
                <th className="p-3">Giá</th>
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
    </div>
  );
}