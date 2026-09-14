import { useState, useEffect } from "react";
import adminService, { type ProposalStatsResponse } from "@/services/adminService";
import { Toast } from "@/components/admin/CustomAlert";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from "recharts";
import DateTimePicker from "@/components/DateTimePicker";


interface BulkProposalDialogProps {
  scheduleId: number;
  isOpen: boolean;
  onClose: () => void;
}

const PIE_COLORS = ["#10b981", "#f43f5e", "#f59e0b", "#6b7280"]; // Green, Red, Amber, Gray

export function BulkProposalDialog({ scheduleId, isOpen, onClose }: BulkProposalDialogProps) {
  const [activeTab, setActiveTab] = useState<"create" | "stats">("create");
  
  // Create State
  const [reason, setReason] = useState("");
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

  const handleCreate = async () => {
    if (!reason || !deadline) {
      setToast({ isOpen: true, type: "error", message: "Vui lòng nhập đầy đủ thông tin" });
      return;
    }

    const selectedDate = new Date(deadline);
    if (selectedDate <= new Date()) {
      setToast({ isOpen: true, type: "error", message: "Hạn chót phải lớn hơn thời gian hiện tại" });
      return;
    }



    setSaving(true);
    try {
      await adminService.sendBulkProposals(scheduleId, {
        reason,
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



  const renderStats = () => {
    if (statsLoading) return <div className="p-4 text-center text-sm text-gray-500">Đang tải thống kê...</div>;
    if (!stats || stats.total === 0) return <div className="p-4 text-center text-sm text-gray-500">Chưa có đề xuất nào cho chuyến này.</div>;

    const data = [
      { name: "Đồng ý", value: stats.accepted },
      { name: "Từ chối", value: stats.rejected },
      { name: "Chờ phản hồi", value: stats.pending },
      { name: "Hết hạn", value: stats.expired },
    ].filter(d => d.value > 0);

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
        
        <div className="h-64">
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
                {data.map((_entry, index) => (
                  <Cell key={`cell-${index}`} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                ))}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
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
                  Hạn chót phản hồi <span className="text-rose-500">*</span>
                </label>
                <DateTimePicker
                  value={deadline}
                  onChange={setDeadline}
                  withTime
                  minDate={new Date()}
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
