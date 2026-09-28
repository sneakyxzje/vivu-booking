import type { GuideAssignment } from "../services/guideService";

export type AssignmentView = "pending" | "accepted" | "history";

export const assignmentView = (item: GuideAssignment): AssignmentView =>
  ["completed", "cancelled"].includes(item.status) ? "history" : item.accepted_at ? "accepted" : "pending";

export const guideSearchText = (value: string) => value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLowerCase().trim();

export function filterAssignments(items: GuideAssignment[], view: AssignmentView, query: string) {
  const search = guideSearchText(query);
  return items.filter(item => assignmentView(item) === view &&
    guideSearchText(`${item.tour_title ?? ""} #${item.schedule_id} ${item.co_guides.join(" ")}`).includes(search))
    .sort((a, b) => view === "history"
      ? Date.parse(b.start_date) - Date.parse(a.start_date) || b.schedule_id - a.schedule_id
      : Number(b.status === "in_progress") - Number(a.status === "in_progress") ||
        Date.parse(a.start_date) - Date.parse(b.start_date) || a.schedule_id - b.schedule_id);
}
