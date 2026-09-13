import { useState, useEffect, useRef, useMemo } from "react";
import { ChevronDown } from "lucide-react";
import adminService, { type ProposalStatsResponse, type MergeCandidate } from "@/services/adminService";
import { Toast } from "@/components/admin/CustomAlert";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from "recharts";
import DateTimePicker from "@/components/DateTimePicker";
import { formatDateTime } from "@/utils/format";

interface BulkProposalDialogProps {
  scheduleId: number;
  isOpen: boolean;
  onClose: () => void;
}

const PIE_COLORS = ["#10b981", "#f43f5e", "#f59e0b", "#6b7280"]; // Green, Red, Amber, Gray

const PRESET_CUSTOM_ACTIONS = [
  { value: "refund_voucher_100", label: "Phát hành Voucher bồi thường 100% giá trị đơn" },
  { value: "refund_voucher_110", label: "Phát hành Voucher bồi thường 110% (Tặng thêm 10%)" },
  { value: "hold_deposit_6m", label: "Bảo lưu cọc 06 tháng & Giữ vị trí ưu tiên" },
  { value: "priority_rebook_next", label: "Ưu tiên chuyển sang chuyến kế tiếp khởi hành" },
  { value: "manual_custom", label: "Nhập mã chính sách hệ thống tùy chỉnh..." },
];

const CustomSelect = ({ value, onChange, options, placeholder = "Chọn...", className = "" }: any) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  
  useEffect(() => {
    const handleOutsideClick = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener("mousedown", handleOutsideClick);
    return () => document.removeEventListener("mousedown", handleOutsideClick);
  }, []);

  const selectedOption = options.find((o: any) => o.value === value);

  return (
    <div className={`relative ${className}`} ref={containerRef}>
      <button 
        type="button"
        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-left bg-white flex justify-between items-center hover:border-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-colors"
        onClick={() => setIsOpen(!isOpen)}
      >
        <span className={selectedOption ? "text-gray-900 font-medium" : "text-gray-400"}>
          {selectedOption ? selectedOption.label : placeholder}
        </span>
        <ChevronDown className={`w-4 h-4 text-gray-500 transition-transform duration-150 ${isOpen ? 'rotate-180' : ''}`} />
      </button>
      {isOpen && (
        <div className="absolute z-[100] w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 max-h-60 overflow-y-auto">
          {options.map((opt: any) => (
            <div 
              key={opt.value}
              className={`px-3 py-2 text-sm cursor-pointer transition-colors ${value === opt.value ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-gray-700 hover:bg-gray-50'}`}
              onClick={() => {
                onChange(opt.value);
                setIsOpen(false);
              }}
            >
              {opt.label}
            </div>
          ))}
          {options.length === 0 && <div className="px-3 py-2 text-sm text-gray-400 text-center">Không có lựa chọn</div>}
        </div>
      )}
    </div>
  );
};

export function BulkProposalDialog({ scheduleId, isOpen, onClose }: BulkProposalDialogProps) {
  const [activeTab, setActiveTab] = useState<"create" | "stats">("create");
  
  // Create State
  const [reason, setReason] = useState("");
  const [deadline, setDeadline] = useState("");
  const [options, setOptions] = useState<{ id: string; label: string; system_action: string }[]>([
    { id: "refund", label: "Hủy và Hoàn tiền 100%", system_action: "refund" },
  ]);
  const [fallbackType, setFallbackType] = useState<string>("refund");
  const [fallbackTransferId, setFallbackTransferId] = useState<string>("");
  const [fallbackCustomPolicy, setFallbackCustomPolicy] = useState<string>("refund_voucher_100");
  const [fallbackCustomText, setFallbackCustomText] = useState<string>("");
  const [saving, setSaving] = useState(false);

  // Candidates state
  const [candidates, setCandidates] = useState<MergeCandidate[]>([]);
  const [loadingCandidates, setLoadingCandidates] = useState(false);

  // Stats State
  const [stats, setStats] = useState<ProposalStatsResponse | null>(null);
  const [statsLoading, setStatsLoading] = useState(false);

  const [toast, setToast] = useState<{ isOpen: boolean; type: "success" | "error" | "info"; message: string }>({ isOpen: false, type: "success", message: "" });

  useEffect(() => {
    if (isOpen) {
      setActiveTab("create");
      setReason("");
      setDeadline("");
      setOptions([{ id: "refund", label: "Hủy và Hoàn tiền 100%", system_action: "refund" }]);
      setFallbackType("refund");
      setFallbackTransferId("");
      setFallbackCustomPolicy("refund_voucher_100");
      setFallbackCustomText("");
      loadStats();
      loadCandidates();
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

  const loadCandidates = async () => {
    setLoadingCandidates(true);
    try {
      const res = await adminService.getMergeCandidates(scheduleId);
      if (res && res.candidates) {
        setCandidates(res.candidates);
        if (res.candidates.length > 0) {
          setFallbackTransferId(String(res.candidates[0].schedule_id));
        }
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoadingCandidates(false);
    }
  };

  const scheduleOptions = useMemo(() => {
    if (loadingCandidates) {
      return [{ value: "", label: "Đang tải danh sách chuyến khởi hành..." }];
    }
    if (candidates.length === 0) {
      return [{ value: "", label: "Không tìm thấy chuyến khác phù hợp" }];
    }
    return candidates.map((c) => {
      const dateStr = formatDateTime(c.start_date);
      const remainingText = c.remaining_seats > 0 ? `Còn ${c.remaining_seats} chỗ` : "Hết chỗ";
      const statusSuffix = c.can_merge ? "" : ` (${c.blocked_reason || "Không thể ghép"})`;
      return {
        value: String(c.schedule_id),
        label: `Chuyến #${c.schedule_id} - Khởi hành: ${dateStr} (${remainingText}${statusSuffix})`,
      };
    });
  }, [candidates, loadingCandidates]);

  if (!isOpen) return null;

  const handleCreate = async () => {
    if (!reason || !deadline || options.length === 0) {
      setToast({ isOpen: true, type: "error", message: "Vui lòng nhập đầy đủ thông tin" });
      return;
    }

    const selectedDate = new Date(deadline);
    if (selectedDate <= new Date()) {
      setToast({ isOpen: true, type: "error", message: "Hạn chót phải lớn hơn thời gian hiện tại" });
      return;
    }

    for (const opt of options) {
      if (opt.system_action.startsWith("transfer")) {
        const targetId = opt.system_action.split(":")[1];
        if (!targetId || targetId === "") {
          setToast({ isOpen: true, type: "error", message: `Vui lòng chọn chuyến đích cho phương án "${opt.label || 'Chuyển chuyến'}"` });
          return;
        }
      }
    }

    let finalFallbackAction = "";
    if (fallbackType === "none") {
      finalFallbackAction = "";
    } else if (fallbackType === "transfer") {
      const targetId = fallbackTransferId || (candidates.length > 0 ? String(candidates[0].schedule_id) : "");
      if (!targetId) {
        setToast({ isOpen: true, type: "error", message: "Vui lòng chọn chuyến đích cho hành động mặc định" });
        return;
      }
      finalFallbackAction = `transfer:${targetId}`;
    } else if (fallbackType === "custom") {
      if (fallbackCustomPolicy === "manual_custom") {
        if (!fallbackCustomText.trim()) {
          setToast({ isOpen: true, type: "error", message: "Vui lòng nhập mã chính sách tùy chỉnh" });
          return;
        }
        finalFallbackAction = fallbackCustomText.trim();
      } else {
        finalFallbackAction = fallbackCustomPolicy;
      }
    } else {
      const matchedOpt = options.find((o) => o.id === fallbackType);
      finalFallbackAction = matchedOpt ? matchedOpt.system_action : fallbackType;
    }

    setSaving(true);
    try {
      await adminService.sendBulkProposals(scheduleId, {
        reason,
        response_deadline: deadline,
        options,
        fallback_action: finalFallbackAction,
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

  const addOption = () => {
    const newId = `opt_${Date.now()}`;
    setOptions([...options, { id: newId, label: "", system_action: "" }]);
  };

  const updateOption = (index: number, key: keyof typeof options[0], value: string) => {
    const newOptions = [...options];
    newOptions[index][key] = value;
    setOptions(newOptions);
  };

  const removeOption = (index: number) => {
    const newOptions = [...options];
    newOptions.splice(index, 1);
    setOptions(newOptions);
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
              Tạo đề xuất mới
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

              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">
                  Các phương án cho khách chọn <span className="text-rose-500">*</span>
                </label>
                <div className="space-y-3 mb-3">
                  {options.map((opt, i) => {
                    const isTransfer = opt.system_action.startsWith("transfer");
                    const actionType = isTransfer ? "transfer" : opt.system_action;
                    const transferId = isTransfer ? opt.system_action.split(":")[1] || "" : "";

                    return (
                      <div key={opt.id} className="flex gap-3 items-start bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm transition-all hover:shadow-md">
                        <div className="flex-1 space-y-3">
                          <div>
                            <label className="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">Tiêu đề phương án</label>
                            <input
                              placeholder="Tiêu đề phương án (Khách sẽ thấy)"
                              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all bg-white"
                              value={opt.label}
                              onChange={(e) => updateOption(i, "label", e.target.value)}
                            />
                          </div>
                          <div className="flex gap-3">
                            <div className="flex-1">
                              <label className="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">Hành động hệ thống</label>
                                <CustomSelect
                                  value={actionType}
                                  onChange={(val: string) => {
                                    if (val === "transfer") {
                                      const defaultId = candidates.length > 0 ? String(candidates[0].schedule_id) : "";
                                      updateOption(i, "system_action", `transfer:${defaultId}`);
                                    } else {
                                      updateOption(i, "system_action", val);
                                    }
                                  }}
                                  options={[
                                    { value: "refund", label: "Hoàn tiền 100%" },
                                    { value: "cancel_no_refund", label: "Hủy không hoàn tiền" },
                                    { value: "transfer", label: "Chuyển sang chuyến khác" },
                                  ]}
                                />
                            </div>
                            {isTransfer && (
                              <div className="flex-1">
                                <label className="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">Chuyến đích đến</label>
                                <CustomSelect
                                  value={transferId}
                                  onChange={(val: string) => updateOption(i, "system_action", `transfer:${val}`)}
                                  options={scheduleOptions}
                                  placeholder="Chọn chuyến đích..."
                                />
                              </div>
                            )}
                          </div>
                        </div>
                        {options.length > 1 && (
                          <button 
                            type="button" 
                            onClick={() => removeOption(i)} 
                            className="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors mt-6"
                            title="Xóa phương án này"
                          >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                          </button>
                        )}
                      </div>
                    );
                  })}
                </div>
                <button type="button" onClick={addOption} className="inline-flex items-center gap-1 text-sm font-bold text-primary-600 hover:text-primary-700 bg-primary-50 hover:bg-primary-100 px-4 py-2 rounded-lg transition-colors">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                  Thêm phương án
                </button>
              </div>

              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">
                  Hành động mặc định khi hết hạn (Fallback Action)
                </label>
                <CustomSelect
                  value={fallbackType}
                  onChange={setFallbackType}
                  options={[
                    { value: "none", label: "Chờ xử lý thủ công (Không tự động can thiệp)" },
                    { value: "refund", label: "Tự động hủy chuyến & Hoàn tiền 100%" },
                    { value: "cancel_no_refund", label: "Tự động hủy chuyến (Không hoàn tiền)" },
                    { value: "transfer", label: "Tự động chuyển sang chuyến khác..." },
                    { value: "custom", label: "Tùy chỉnh mã hành động hệ thống..." },
                    ...options.map((opt, idx) => ({
                      value: opt.id,
                      label: `Theo phương án ${idx + 1}: ${opt.label || "Chưa đặt tên"}`
                    }))
                  ]}
                  className="w-full"
                />

                {fallbackType === "transfer" && (
                  <div className="mt-2.5 bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <label className="text-xs font-semibold text-gray-700 mb-1.5 block">
                      Chọn chuyến sẽ tự động chuyển tới khi hết hạn <span className="text-red-500">*</span>
                    </label>
                    <CustomSelect
                      value={fallbackTransferId}
                      onChange={setFallbackTransferId}
                      options={scheduleOptions}
                      placeholder="Chọn chuyến khởi hành phù hợp..."
                      className="w-full"
                    />
                  </div>
                )}

                {fallbackType === "custom" && (
                  <div className="mt-2.5 bg-gray-50 p-3 rounded-lg border border-gray-200 space-y-3">
                    <div>
                      <label className="text-xs font-semibold text-gray-700 mb-1.5 block">
                        Chọn chính sách bồi thường hệ thống <span className="text-red-500">*</span>
                      </label>
                      <CustomSelect
                        value={fallbackCustomPolicy}
                        onChange={setFallbackCustomPolicy}
                        options={PRESET_CUSTOM_ACTIONS}
                        placeholder="Chọn chính sách..."
                        className="w-full"
                      />
                    </div>
                    {fallbackCustomPolicy === "manual_custom" && (
                      <div>
                        <label className="text-xs font-semibold text-gray-700 mb-1 block">
                          Mã chính sách tùy chỉnh <span className="text-red-500">*</span>
                        </label>
                        <input
                          placeholder="Nhập mã chính sách hệ thống (ví dụ: voucher_special_50)"
                          className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all bg-white font-mono"
                          value={fallbackCustomText}
                          onChange={(e) => setFallbackCustomText(e.target.value)}
                        />
                      </div>
                    )}
                  </div>
                )}
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
                  Gửi đề xuất hàng loạt
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
