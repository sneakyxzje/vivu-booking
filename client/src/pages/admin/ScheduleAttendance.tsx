import { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import adminService from "@/services/adminService";
import type { AttendanceCheckin, AttendanceGuest, AttendanceItinerary, CheckpointPhoto } from "@/types/guide";
import { formatDateTime } from "@/utils/format";

interface AdminAttendanceData {
  schedule: {
    id: number;
    start_date: string;
    max_people: number;
    booked_people: number;
    guide?: { id: number; name: string; phone?: string | null } | null;
  };
  tour: { id: number; title: string; number_of_days: number };
  itineraries: AttendanceItinerary[];
  guests: AttendanceGuest[];
  checkins: AttendanceCheckin[];
  photos: CheckpointPhoto[];
}

export default function ScheduleAttendance() {
  const { scheduleId } = useParams<{ scheduleId: string }>();

  const [data, setData] = useState<AdminAttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [activeItineraryId, setActiveItineraryId] = useState<number | null>(null);

  useEffect(() => {
    if (!scheduleId) return;

    adminService
      .getScheduleAttendance(Number(scheduleId))
      .then((result: AdminAttendanceData | null) => {
        if (!result) {
          setError("Không tìm thấy lịch khởi hành.");
          return;
        }
        setData(result);
        setActiveItineraryId(result.itineraries[0]?.id ?? null);
      })
      .catch(() => setError("Không thể tải dữ liệu điểm danh."))
      .finally(() => setLoading(false));
  }, [scheduleId]);

  const checkinByBooking = useMemo(() => {
    const map = new Map<string, AttendanceCheckin>();
    data?.checkins.forEach((checkin) => {
      map.set(`${checkin.tour_itinerary_id}:${checkin.booking_id}`, checkin);
    });
    return map;
  }, [data]);

  const activePhotos = useMemo(
    () => (data?.photos ?? []).filter((photo) => photo.tour_itinerary_id === activeItineraryId),
    [data, activeItineraryId],
  );

  if (loading) {
    return <div className="text-center py-16 text-gray-500">Đang tải...</div>;
  }

  if (error || !data) {
    return (
      <div className="space-y-4">
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {error || "Không thể tải dữ liệu điểm danh."}
        </div>
        <Link to="/admin/tours" className="text-sm font-semibold text-primary-600 hover:underline">
          ← Quay lại danh sách tour
        </Link>
      </div>
    );
  }

  const activeItinerary = data.itineraries.find((item) => item.id === activeItineraryId) ?? null;
  const presentCount = activeItinerary
    ? data.guests.filter((guest) => checkinByBooking.get(`${activeItinerary.id}:${guest.id}`)?.present).length
    : 0;

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <Link
          to={`/admin/tours/${data.tour.id}`}
          className="text-sm font-semibold text-primary-600 hover:underline"
        >
          ← Chi tiết tour
        </Link>
        <h1 className="text-2xl font-bold text-gray-900 mt-2">Điểm danh chuyến đi</h1>
        <p className="text-gray-500 text-sm mt-1">
          {data.tour.title} — khởi hành {formatDateTime(data.schedule.start_date)}
          {data.schedule.guide ? ` · HDV: ${data.schedule.guide.name}` : " · Chưa phân công HDV"}
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {data.itineraries.map((itinerary) => (
          <button
            key={itinerary.id}
            type="button"
            onClick={() => setActiveItineraryId(itinerary.id)}
            className={`px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors ${
              activeItineraryId === itinerary.id
                ? "bg-primary-600 text-white"
                : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
            }`}
          >
            Ngày {itinerary.day_number}
            {itinerary.start_point && itinerary.end_point
              ? ` · ${itinerary.start_point} → ${itinerary.end_point}`
              : ""}
          </button>
        ))}
      </div>

      {data.itineraries.length === 0 ? (
        <div className="bg-white rounded-lg border border-gray-100 p-12 text-center text-gray-500">
          Tour này chưa có lịch trình.
        </div>
      ) : (
        activeItinerary && (
          <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            <div className="xl:col-span-2 bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-gray-100">
                <h2 className="font-semibold text-gray-900">{activeItinerary.title}</h2>
                <p className="text-xs text-gray-500 mt-0.5">
                  Có mặt {presentCount}/{data.guests.length} đơn
                </p>
              </div>

              {data.guests.length === 0 ? (
                <p className="p-6 text-sm text-gray-500">Chưa có đơn nào được xác nhận.</p>
              ) : (
                <ul className="divide-y divide-gray-50">
                  {data.guests.map((guest) => {
                    const checkin = checkinByBooking.get(`${activeItinerary.id}:${guest.id}`);
                    return (
                      <li key={guest.id} className="px-6 py-4 flex items-center justify-between gap-4">
                        <div className="min-w-0">
                          <p className="text-sm font-medium text-gray-900 truncate">
                            {guest.customer_name}
                            <span className="ml-2 font-mono text-xs text-gray-400">BK-{guest.id}</span>
                          </p>
                          <p className="text-xs text-gray-500 mt-0.5">
                            {guest.customer_phone || "Không có SĐT"} · {guest.guests} khách
                          </p>
                          {guest.passengers && guest.passengers.length > 0 && (
                            <p className="text-xs text-gray-400 mt-1 truncate">
                              {guest.passengers.map((passenger) => passenger.name).join(", ")}
                            </p>
                          )}
                        </div>
                        <span
                          className={`shrink-0 inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold border ${
                            checkin?.present
                              ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                              : checkin
                                ? "bg-rose-50 text-rose-700 border-rose-200"
                                : "bg-gray-50 text-gray-500 border-gray-200"
                          }`}
                        >
                          {checkin?.present ? "Có mặt" : checkin ? "Vắng mặt" : "Chưa điểm danh"}
                        </span>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>

            <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-gray-100">
                <h2 className="font-semibold text-gray-900">Ảnh check-in đoàn</h2>
              </div>
              {activePhotos.length === 0 ? (
                <p className="p-6 text-sm text-gray-500">Chưa có ảnh check-in cho chặng này.</p>
              ) : (
                <div className="p-4 grid grid-cols-2 gap-3">
                  {activePhotos.map((photo) => (
                    <a
                      key={photo.id}
                      href={photo.image_path}
                      target="_blank"
                      rel="noreferrer"
                      className="block h-32 rounded-lg overflow-hidden border border-gray-100"
                    >
                      <img
                        src={photo.image_path}
                        alt="Ảnh check-in đoàn"
                        className="w-full h-full object-cover"
                        loading="lazy"
                      />
                    </a>
                  ))}
                </div>
              )}
            </div>
          </div>
        )
      )}
    </div>
  );
}
