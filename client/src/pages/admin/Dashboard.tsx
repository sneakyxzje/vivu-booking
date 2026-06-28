export default function Dashboard() {
  return (
    <div className="flex min-h-screen bg-gray-100">
      {/* MAIN */}
      <div className="flex-1">
        {/* HEADER */}
        <header className="bg-white shadow px-6 py-4 flex justify-between items-center">
          <h2 className="text-xl font-bold">Dashboard</h2>

          <div className="flex items-center gap-3">
            <span className="text-gray-500">Admin</span>
            <div className="w-10 h-10 rounded-full bg-slate-900"></div>
          </div>
        </header>

        {/* CONTENT */}
        <main className="p-6 space-y-6">
          {/* STATS */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-white p-6 rounded-xl shadow">
              <p className="text-gray-500">Tổng Tour</p>
              <h3 className="text-2xl font-bold">128</h3>
            </div>

            <div className="bg-white p-6 rounded-xl shadow">
              <p className="text-gray-500">Booking</p>
              <h3 className="text-2xl font-bold">54</h3>
            </div>

            <div className="bg-white p-6 rounded-xl shadow">
              <p className="text-gray-500">Doanh thu</p>
              <h3 className="text-2xl font-bold">120,000,000đ</h3>
            </div>
          </div>

          {/* CHART FAKE */}
          <div className="bg-white p-6 rounded-xl shadow">
            <h3 className="font-bold mb-4">Thống kê tuần</h3>
            <div className="h-40 flex items-end gap-2">
              {[40, 70, 30, 90, 60, 80, 50].map((h, i) => (
                <div
                  key={i}
                  className="bg-slate-900 w-8 rounded"
                  style={{ height: `${h}%` }}
                />
              ))}
            </div>
          </div>

          {/* TABLE */}
          <div className="bg-white p-6 rounded-xl shadow">
            <h3 className="font-bold mb-4">Tour mới nhất</h3>

            <table className="w-full text-left">
              <thead>
                <tr className="text-gray-500 border-b">
                  <th className="py-2">ID</th>
                  <th>Tên</th>
                  <th>Địa điểm</th>
                  <th>Giá</th>
                  <th>Trạng thái</th>
                </tr>
              </thead>

              <tbody>
                <tr className="border-b">
                  <td className="py-2">1</td>
                  <td>Hạ Long 3N2Đ</td>
                  <td>Quảng Ninh</td>
                  <td>3.500.000đ</td>
                  <td className="text-green-600">Active</td>
                </tr>

                <tr className="border-b">
                  <td className="py-2">2</td>
                  <td>Đà Nẵng - Hội An</td>
                  <td>Đà Nẵng</td>
                  <td>4.200.000đ</td>
                  <td className="text-red-600">Inactive</td>
                </tr>
              </tbody>
            </table>
          </div>
        </main>
      </div>
    </div>
  );
}