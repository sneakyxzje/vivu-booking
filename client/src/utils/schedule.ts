export const statusLabel: Record<string, string> = {
  open: "Đang mở bán",
  closed: "Đã đóng bán",
  confirmed: "Đã chốt chạy",
  in_progress: "Đang di chuyển",
  completed: "Đã hoàn thành",
  cancelled: "Đã hủy",
  active: "Đang hoạt động",
  inactive: "Tạm dừng",
  full: "Hết chỗ",
};

export const statusClasses: Record<string, string> = {
  open: "bg-emerald-50 border-emerald-200 border text-emerald-800",
  closed: "bg-gray-100 border-gray-200 border text-gray-700",
  confirmed: "bg-blue-50 border-blue-200 border text-blue-800",
  in_progress: "bg-amber-50 border-amber-200 border text-amber-800",
  completed: "bg-indigo-50 border-indigo-200 border text-indigo-800",
  cancelled: "bg-rose-50 border-rose-200 border text-rose-850",
  active: "bg-emerald-550 border-emerald-200 border bg-emerald-50 text-emerald-800",
  inactive: "bg-gray-100 border-gray-200 border text-gray-600",
  full: "bg-red-50 border-red-200 border text-red-700",
};
