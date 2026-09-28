import type { ExtendedSchedule } from "@/types";

export type ScheduleView = "upcoming" | "running" | "history";

export const scheduleStatusText: Record<ExtendedSchedule["status"], string> = {
  open: "Chưa chốt chuyến",
  confirmed: "Đã chốt chuyến",
  in_progress: "Đang diễn ra",
  completed: "Đã kết thúc",
  cancelled: "Đã hủy",
};

export const scheduleStatus = (schedule: Pick<ExtendedSchedule, "status" | "effective_status">) => schedule.effective_status ?? schedule.status;

export function scheduleView(schedule: Pick<ExtendedSchedule, "status" | "effective_status">): ScheduleView {
  const status = scheduleStatus(schedule);
  if (status === "completed" || status === "cancelled") return "history";
  return status === "in_progress" ? "running" : "upcoming";
}

export function filterScheduleList(schedules: ExtendedSchedule[], filters: {
  view: ScheduleView;
  query: string;
  tourId?: number;
  status: string;
  dateRange: [string, string] | null;
  unassigned: boolean;
}): ExtendedSchedule[] {
  const normalize = (text: string) => text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLowerCase();
  const query = normalize(filters.query.trim());
  return schedules.filter(schedule => {
    const date = schedule.start_date.slice(0, 10);
    return scheduleView(schedule) === filters.view
      && (!filters.tourId || schedule.tour_id === filters.tourId)
      && (filters.status === "all" || scheduleStatus(schedule) === filters.status)
      && (!filters.unassigned || (schedule.guides ?? []).length === 0)
      && (!filters.dateRange || (date >= filters.dateRange[0] && date <= filters.dateRange[1]))
      && (!query || normalize(schedule.tour_title).includes(query) || String(schedule.id).includes(query.replace(/^#/, "")));
  }).sort((a, b) => {
    const time = Date.parse(a.start_date) - Date.parse(b.start_date);
    return filters.view === "history" ? -time || b.id - a.id : time || a.id - b.id;
  });
}
