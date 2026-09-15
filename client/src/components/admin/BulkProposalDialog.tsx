import { useState, useEffect } from "react";
import adminService, { type ProposalStatsResponse } from "@/services/adminService";
import { Toast } from "@/components/admin/CustomAlert";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from "recharts";
import DateTimePicker from "@/components/DateTimePicker";


interface BulkProposalDialogProps {
  scheduleId: number;
  scheduleStartDate?: string;
  isOpen: boolean;
  onClose: () => void;
}

const PIE_COLORS = ["#10b981", "#f43f5e", "#f59e0b", "#6b7280"]; // Green, Red, Amber, Gray

export function BulkProposalDialog({ scheduleId, scheduleStartDate, isOpen, onClose }: BulkProposalDialogProps) {
  const [activeTab, setActiveTab] = useState<"create" | "stats">("create");
  
  // Create State
  const [reason, setReason] = useState("");
  const [proposedDate, setProposedDate] = useState("");
  const [deadline, setDeadline] = useState("");

  const [saving, setSaving] = useState(false);

  // Stats State
  const [stats, setStats] = useState<ProposalStatsResponse | null>(null);
  const [statsLoading, setStatsLoading] = useState(false);

  const [toast, setToast] = useState<{ isOpen: boolean; type: "success" | "error" | "info"; message: string }>({ isOpen: false, type: "success", message: "" });

  useEffect(() => {
    if (isOpen) {
      setActiveTab("create");
      setReason("");
      setProposedDate("");
      setDeadline("");

      loadStats();
    }
  }, [isOpen, scheduleId]);

  const loadStats = async () => {
    setStatsLoading(true);
    try {
      const data = await adminService.getProposalStats(scheduleId);
      setStats(data);
    } catch (e) {
      console.error(e);
    } finally {
      setStatsLoading(false);
    }
  };

  if (!isOpen) return null;

  const startDateObj = scheduleStartDate ? new Date(scheduleStartDate) : new Date();
  const maxDeadlineObj = new Date(startDateObj);
  maxDeadlineObj.setDate(maxDeadlineObj.getDate() + 2);

  const handleCreate = async () => {
    if (!reason || !deadline || !proposedDate) {
      setToast({ isOpen: true, type: "error", message: "Vui lòng nhập đầy đủ thông tin bắt buộc" });
      return;
    }

    const selectedDate = new Date(deadline);
    if (selectedDate <= new Date()) {
      setToast({ isOpen: true, type: "error", message: "Hạn chót phải lớn hơn thời gian hiện tại" });
      return;
    }

    if (selectedDate < startDateObj || selectedDate > maxDeadlineObj) {
      setToast({ isOpen: true, type: "error", message: "Hạn chót phải nằm trong khoảng từ lúc khởi hành đến sau đó 2 ngày" });
      return;
    }

    if (proposedDate && new Date(proposedDate) < startDateObj) {
      setToast({ isOpen: true, type: "error", message: "Ngày đề xuất không được nằm trong quá khứ so với ngày khởi hành gốc" });
      return;
    }

    setSaving(true);
    try {
      await adminService.sendBulkProposals(scheduleId, {
        reason,
        proposed_date: proposedDate || undefined,
        response_deadline: deadline,
      });
      setToast({ isOpen: true, type: "success", message: "Đã gửi đề xuất thành công!" });
      setTimeout(() => {
        setToast({ isOpen: false, type: "success", message: "" });
        onClose();
      }, 1500);
    } catch (error: any) {
      const msg = error.response?.data?.message || "Lỗi khi gửi đề xuất";
      setToast({ isOpen: true, type: "error", message: msg });
    } finally {
      setSaving(false);
    }
  };



  const handleCancelBooking = async (bookingId: number) => {
    if (!confirm("Bạn có chắc chắn muốn hủy đơn hàng này không? Quá trình này sẽ giải phóng chỗ trên chuyến đi hiện tại.")) return;
    
    setSaving(true);
    try {
      await adminService.cancelBooking(bookingId, {
        reason: "Khách hàng không đồng ý dời lịch hoặc quá hạn phản hồi Đề xuất thay đổi",
        note: "Hủy từ Dashboard Đề xuất thay đổi",
        refund_method: "manual"
      });
      setToast({ isOpen: true, type: "success", message: "Đã hủy đơn thành công!" });
      loadStats(); // Tải lại danh sách
    } catch (error: any) {
      const msg = error.response?.data?.message || "Lỗi khi hủy đơn";
      setToast({ isOpen: true, type: "error", message: msg });
    } finally {
      setSaving(false);
    }
  };

  const renderStats = () => {
    if (statsLoading) return <div className="p-4 text-center text-sm text-gray-500">Đang tải thống kê...</div>;
    if (!stats) return null;

    const isZero = stats.total === 0;

    const data = isZero 
      ? [{ name: "Chưa có đề xuất", value: 1, color: "#e5e7eb" }]
      : [
          { name: "Đồng ý", value: stats.accepted, color: "#10b981" },
          { name: "Từ chối", value: stats.rejected, color: "#f43f5e" },
          { name: "Chờ phản hồi", value: stats.pending, color: "#9ca3af" },
          { name: "Hết hạn", value: stats.expired, color: "#f59e0b" },
        ];

    const CustomTooltip = ({ active, payload }: any) => {
      if (active && payload && payload.length) {
        if (isZero) return null;
        const item = payload[0].payload;
        const percent = ((item.value / stats.total) * 100).toFixed(1);
        return (
          <div className="bg-white p-3 border border-gray-200 rounded-lg shadow-sm text-sm">
            <p className="font-bold mb-1" style={{ color: item.color }}>{item.name}</p>
            <p className="text-gray-700">Số lượng: <strong>{item.value}/{stats.total}</strong> khách</p>
            <p className="text-gray-500">Tỷ lệ: <strong>{percent}%</strong></p>
          </div>
        );
      }
      return null;
    };

    return (
      <div className="space-y-4">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-slate-50 p-3 rounded-lg border text-center">
            <p className="text-xs text-gray-500 uppercase font-bold">Tổng số</p>
            <p className="text-xl font-bold text-gray-900">{stats.total}</p>
          </div>
          <div className="bg-emerald-50 p-3 rounded-lg border border-emerald-100 text-center">
            <p className="text-xs text-emerald-600 uppercase font-bold">Đã chốt</p>
            <p className="text-xl font-bold text-emerald-700">{stats.accepted}</p>
          </div>
          <div className="bg-rose-50 p-3 rounded-lg border border-rose-100 text-center">
            <p className="text-xs text-rose-600 uppercase font-bold">Từ chối</p>
            <p className="text-xl font-bold text-rose-700">{stats.rejected}</p>
          </div>
          <div className="bg-amber-50 p-3 rounded-lg border border-amber-100 text-center">
            <p className="text-xs text-amber-600 uppercase font-bold">Đang chờ</p>
            <p className="text-xl font-bold text-amber-700">{stats.pending + stats.expired}</p>
          </div>
        </div>
        
        <div className="h-64 mb-8">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={data}
                cx="50%"
                cy="50%"
                innerRadius={60}
                outerRadius={80}
                paddingAngle={5}
                dataKey="value"
              >
                {data.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip content={<CustomTooltip />} />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>

        <div className="border-t border-gray-200 pt-6">
          <h3 className="text-sm font-bold text-slate-800 mb-4">Danh sách chi tiết</h3>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="w-full text-left text-sm text-gray-600">
              <thead className="bg-gray-50 text-xs uppercase text-gray-700 border-b border-gray-200">
                <tr>
                  <th className="px-4 py-3 font-bold">Khách hàng</th>
                  <th className="px-4 py-3 font-bold">Mã Đơn</th>
                  <th className="px-4 py-3 font-bold">Trạng thái</th>
                  <th className="px-4 py-3 font-bold text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {stats.proposals?.map((p) => {
                  const b = p.booking;
                  const isPending = p.status === "pending" || p.status === "expired";
                  const isRejected = p.status === "rejected";
                  const canCancel = (isPending || isRejected) && b.status !== "cancelled";

                  return (
                    <tr key={p.id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-4 py-3">
                        <div className="font-bold text-slate-800">{b.customer_name}</div>
                        <div className="text-xs text-slate-500">{b.customer_phone}</div>
                      </td>
                      <td className="px-4 py-3 font-mono text-xs">{b.public_token}</td>
                      <td className="px-4 py-3">
                        {p.status === "accepted" && <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Đồng ý</span>}
                        {p.status === "rejected" && <span className="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700">Từ chối</span>}
                        {p.status === "pending" && <span className="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-700">Chờ P.Hồi</span>}
                        {p.status === "expired" && <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">Hết hạn</span>}
                      </td>
                      <td className="px-4 py-3 text-right">
                        {canCancel ? (
                          <button
                            onClick={() => handleCancelBooking(b.id)}
                            disabled={saving}
                            className="text-[11px] font-bold text-rose-600 bg-rose-50 border border-rose-200 px-2 py-1 rounded hover:bg-rose-100 disabled:opacity-50 transition-colors"
                          >
                            Hủy đơn
                          </button>
                        ) : (
                          <span className="text-[11px] text-gray-400 italic">
                            {b.status === "cancelled" ? "Đã hủy" : "Không áp dụng"}
                          </span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div 
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
      onClick={onClose}
    >
      <div 
        className="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-200 overflow-hidden animate-scale-up flex flex-col max-h-[90vh] relative"
        onClick={(e) => e.stopPropagation()}
      >
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-full transition-colors z-10"
          title="Đóng"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
        
        <div className="flex border-b border-gray-100 bg-slate-50/50 pr-12">
          <button
            className={`flex-1 py-4 text-sm font-bold transition-colors ${activeTab === "create" ? "text-primary-700 border-b-2 border-primary-600 bg-white shadow-sm" : "text-gray-500 hover:text-gray-800 hover:bg-gray-100"}`}
            onClick={() => setActiveTab("create")}
          >
            <div className="flex items-center justify-center gap-2">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
              Gửi thông báo & Yêu cầu phản hồi
            </div>
          </button>
          <button
            className={`flex-1 py-4 text-sm font-bold transition-colors ${activeTab === "stats" ? "text-primary-700 border-b-2 border-primary-600 bg-white shadow-sm" : "text-gray-500 hover:text-gray-800 hover:bg-gray-100"}`}
            onClick={() => {
              setActiveTab("stats");
              loadStats();
            }}
          >
            <div className="flex items-center justify-center gap-2">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
              Thống kê phản hồi
            </div>
          </button>
        </div>

        <div className="p-6 md:p-8 overflow-y-auto flex-1 bg-white">
          {activeTab === "create" ? (
            <div className="space-y-6">
              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">
                  Lý do thay đổi <span className="text-rose-500">*</span>
                </label>
                <textarea
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-50 transition-all bg-gray-50 hover:bg-white"
                  rows={3}
                  placeholder="Khách sẽ thấy lý do này trong email..."
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">
                  Ngày đề xuất đổi sang <span className="text-rose-500">*</span>
                </label>
                <DateTimePicker
                  value={proposedDate}
                  onChange={setProposedDate}
                  withTime
                  minDate={startDateObj}
                  placeholder="Chọn thời gian gợi ý cho khách hàng..."
                  className="w-full"
                  buttonClassName="flex w-full items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-3 text-left text-sm text-gray-800 transition-all hover:bg-gray-50 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-50"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">
                  Hạn chót phản hồi <span className="text-rose-500">*</span>
                </label>
                <DateTimePicker
                  value={deadline}
                  onChange={setDeadline}
                  withTime
                  minDate={startDateObj}
                  maxDate={maxDeadlineObj}
                  placeholder="Chọn thời gian hết hạn..."
                  className="w-full"
                  buttonClassName="w-full flex items-center gap-2 rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left text-sm text-gray-800 transition-all hover:bg-white focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-50"
                />
              </div>


            </div>
          ) : (
            renderStats()
          )}
        </div>

        <div className="p-4 md:p-6 border-t border-gray-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
          <button
            onClick={onClose}
            className="px-6 py-2.5 text-sm font-bold text-slate-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 hover:text-slate-900 shadow-sm transition-all"
            disabled={saving}
          >
            Đóng
          </button>
          {activeTab === "create" && (
            <button
              onClick={handleCreate}
              disabled={saving}
              className="px-6 py-2.5 text-sm font-bold text-white bg-primary-600 rounded-xl hover:bg-primary-700 hover:shadow-md disabled:opacity-50 transition-all inline-flex items-center gap-2"
            >
              {saving ? (
                <>
                  <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  Đang gửi...
                </>
              ) : (
                <>
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                  Gửi thông báo hàng loạt
                </>
              )}
            </button>
          )}
        </div>
      </div>
      
      {toast.isOpen && (
        <Toast
          message={toast.message}
          type={toast.type}
          isOpen={toast.isOpen}
          onClose={() => setToast({ ...toast, isOpen: false })}
        />
      )}
    </div>
  );
}
