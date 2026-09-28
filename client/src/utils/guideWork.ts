import type { Tour, TourSchedule } from "../types/tour";
import type { AttendanceData } from "../types/guide";
import { attendanceDate } from "./attendanceAccess.ts";

export type GuideTrip = { tour: Tour; schedule: TourSchedule; status: TourSchedule["status"] };
export type GuideTripFilter = "active" | "running" | "history" | "all";
export const guideTrips = (tours: Tour[]): GuideTrip[] => tours.flatMap(tour =>
  (tour.schedules ?? []).map(schedule => ({ tour, schedule, status: schedule.effective_status ?? schedule.status })));
export const isTripHistory = (trip: GuideTrip) => trip.status === "completed" || trip.status === "cancelled";
export const isTripToday = (trip: GuideTrip, now: number) => !isTripHistory(trip) &&
  (trip.status === "in_progress" || attendanceDate(Date.parse(trip.schedule.start_date)) ===
    attendanceDate(Date.parse(trip.schedule.server_now ?? trip.schedule.demo_clock?.now ?? "") || now));

const newestFirst = (a: GuideTrip, b: GuideTrip) => Date.parse(b.schedule.start_date) - Date.parse(a.schedule.start_date) || b.schedule.id - a.schedule.id;
const searchText = (value: string) => value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLowerCase();

export function guideTourGroups(tours: Tour[], filter: GuideTripFilter, query: string) {
  const groups = new Map<number, { tour: Tour; trips: GuideTrip[] }>();
  for (const trip of guideTrips(tours)) {
    if (filter === "running" && trip.status !== "in_progress") continue;
    if (filter === "active" && isTripHistory(trip)) continue;
    if (filter === "history" && !isTripHistory(trip)) continue;
    if (!searchText(`${trip.tour.title} ${trip.tour.start_location} ${trip.tour.end_location ?? ""} ${trip.schedule.id}`).includes(searchText(query.trim()))) continue;
    if (!groups.has(trip.tour.id)) groups.set(trip.tour.id, { tour: trip.tour, trips: [] });
    groups.get(trip.tour.id)!.trips.push(trip);
  }
  const priority = (a: GuideTrip, b: GuideTrip) => Number(b.status === "in_progress") - Number(a.status === "in_progress") || newestFirst(a, b);
  return [...groups.values()].map(group => ({ ...group, trips: group.trips.sort(priority) }))
    .sort((a, b) => priority(a.trips[0], b.trips[0]));
}

export function todayAttendanceProgress(data: AttendanceData) {
  const today = attendanceDate(Date.parse(data.schedule.server_now));
  const checkpoints = data.checkpoints.filter(point => point.attendance_date === today);
  const passengers = new Set(data.bookings.flatMap(booking => (booking.passengers ?? []).map(person => person.id)));
  const missingPassengerLists = data.bookings.filter(booking => !(booking.passengers ?? []).length).length;
  const complete = checkpoints.filter(point => {
    const checked = new Set(data.checkins.filter(record => record.itinerary_checkpoint_id === point.id && passengers.has(record.booking_passenger_id)).map(record => record.booking_passenger_id));
    return !missingPassengerLists && passengers.size > 0 && checked.size === passengers.size &&
      (!point.is_required_photo || data.photos.some(photo => photo.itinerary_checkpoint_id === point.id));
  }).length;
  return { total: checkpoints.length, complete, remaining: checkpoints.length - complete, passengers: passengers.size, missingPassengerLists };
}
