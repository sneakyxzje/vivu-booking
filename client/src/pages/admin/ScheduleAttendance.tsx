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
  const [previewPhotoUrl, setPreviewPhotoUrl] = useState<string | null>(null);

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
    return (
      <div className="py-20 text-center space-y-3">
        <div className="w-10 h-10 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-sm font-medium text-gray-500">Đang tải dữ liệu điểm danh chuyến...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="space-y-4 max-w-2xl mx-auto py-12">
        <div className="rounded-2xl bg-rose-50 border border-rose-200 p-6 text-center text-rose-700">
          <p className="font-semibold">{error || "Không thể tải dữ liệu điểm danh."}</p>
          <Link
            to="/admin/schedules"
            className="inline-block mt-4 text-xs font-bold text-rose-800 underline"
          >
            ← Quay lại Quản lý Chuyến
          </Link>
        </div>
      </div>
    );
  }

  const activeItinerary = data.itineraries.find((item) => item.id === activeItineraryId) ?? null;

  const presentCount = activeItinerary
    ? data.guests.filter((guest) => checkinByBooking.get(`${activeItinerary.id}:${guest.id}`)?.present).length
    : 0;

  const absentCount = activeItinerary
    ? data.guests.filter((guest) => {
      const c = checkinByBooking.get(`${activeItinerary.id}:${guest.id}`);
      return c && !c.present;
    }).length
    : 0;

  const uncheckCount = data.guests.length - presentCount - absentCount;

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {/* Header Info */}
      <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <Link
            to="/admin/schedules"
            className="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline mb-2"
          >
            ← Quay lại Quản lý Chuyến
          </Link>
          <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 font-jakarta">
            Báo cáo điểm danh & Check-in đoàn
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            <span className="font-semibold text-gray-800">{data.tour.title}</span> · Khởi hành:{" "}
            <span className="text-primary-700 font-medium">{formatDateTime(data.schedule.start_date)}</span>
            {data.schedule.guide ? (
              <span className="ml-2 font-bold text-emerald-700">· HDV: {data.schedule.guide.name}</span>
            ) : (
              <span className="ml-2 text-rose-600 font-medium">· Chưa phân công HDV</span>
            )}
          </p>
        </div>
      </div>

      {/* Tabs Ngày / Chặng */}
      <div className="flex flex-wrap gap-2.5">
        {data.itineraries.map((itinerary) => {
          const isActive = activeItineraryId === itinerary.id;
          return (
            <button
              key={itinerary.id}
              type="button"
              onClick={() => setActiveItineraryId(itinerary.id)}
              className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all ${isActive
                  ? "bg-primary-600 text-white shadow-md -translate-y-0.5"
                  : "bg-white border border-gray-100 text-gray-700 hover:bg-gray-50 shadow-sm"
                }`}
            >
              Ngày {itinerary.day_number}
              {itinerary.start_point && itinerary.end_point
                ? ` · (${itinerary.start_point} → ${itinerary.end_point})`
                : ""}
            </button>
          );
        })}
      </div>

      {data.itineraries.length === 0 ? (
        <div className="bg-white rounded-3xl border border-gray-100 p-12 text-center text-gray-500">
          Tour này chưa có lịch trình theo ngày.
        </div>
      ) : (
        activeItinerary && (
          <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            {/* Cột trái: Thống kê & Danh sách đơn */}
            <div className="xl:col-span-2 space-y-6">
              {/* Thống kê Nhanh */}
              <div className="grid grid-cols-3 gap-3">
                <div className="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 text-center">
                  <span className="text-2xl font-extrabold text-emerald-700 font-jakarta">
                    {presentCount}
                  </span>
                  <p className="text-[11px] font-bold text-emerald-800 uppercase tracking-wider mt-0.5">
                    Có mặt
                  </p>
                </div>
                <div className="bg-rose-50/70 border border-rose-200 rounded-2xl p-4 text-center">
                  <span className="text-2xl font-extrabold text-rose-700 font-jakarta">
                    {absentCount}
                  </span>
                  <p className="text-[11px] font-bold text-rose-800 uppercase tracking-wider mt-0.5">
                    Vắng mặt
                  </p>
                </div>
                <div className="bg-gray-50 border border-gray-200 rounded-2xl p-4 text-center">
                  <span className="text-2xl font-extrabold text-gray-700 font-jakarta">
                    {uncheckCount}
                  </span>
                  <p className="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-0.5">
                    Chưa ghi nhận
                  </p>
                </div>
              </div>

              {/* Danh sách hành khách */}
              <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-100">
                <div className="px-6 py-4 bg-gray-50/50 flex items-center justify-between">
                  <h2 className="text-xs font-extrabold uppercase tracking-wider text-gray-600">
                    Chi tiết điểm danh: {activeItinerary.title}
                  </h2>
                  <span className="text-xs font-semibold text-gray-500">
                    {data.guests.length} đơn đặt chỗ
                  </span>
                </div>

                {data.guests.length === 0 ? (
                  <p className="p-8 text-center text-sm text-gray-500">
                    Chưa có đơn đặt tour nào được xác nhận.
                  </p>
                ) : (
                  data.guests.map((guest) => {
                    const checkin = checkinByBooking.get(`${activeItinerary.id}:${guest.id}`);
                    return (
                      <div key={guest.id} className="p-6 flex items-center justify-between gap-4">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-gray-900 text-base">
                              {guest.customer_name}
                            </span>
                            <span className="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-mono text-[11px] font-semibold">
                              BK-{guest.id}
                            </span>
                          </div>
                          <p className="text-xs text-gray-500">
                            📞 {guest.customer_phone || "Không có SĐT"} · 👥 {guest.guests} khách
                          </p>
                          {guest.passengers && guest.passengers.length > 0 && (
                            <div className="flex flex-wrap gap-1 pt-1">
                              {guest.passengers.map((p) => (
                                <span
                                  key={p.id}
                                  className="px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 text-[11px]"
                                >
                                  👤 {p.name}
                                </span>
                              ))}
                            </div>
                          )}
                        </div>

                        {/* Status badge */}
                        <span
                          className={`shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold border ${checkin?.present
                              ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                              : checkin
                                ? "bg-rose-50 text-rose-700 border-rose-200"
                                : "bg-gray-100 text-gray-500 border-gray-200"
                            }`}
                        >
                          <span>{checkin?.present ? "✓" : checkin ? "✕" : "⏳"}</span>
                          <span>
                            {checkin?.present ? "Có mặt" : checkin ? "Vắng mặt" : "Chưa điểm danh"}
                          </span>
                        </span>
                      </div>
                    );
                  })
                )}
              </div>
            </div>

            {/* Cột phải: Ảnh check-in đoàn */}
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden p-6 space-y-4">
              <div>
                <h3 className="font-bold text-gray-900 text-base font-jakarta">
                  Ảnh Check-in đoàn
                </h3>
                <p className="text-xs text-gray-500 mt-0.5">
                  Do HDV chụp và gửi lên tại chặng này
                </p>
              </div>

              {activePhotos.length === 0 ? (
                <div className="border border-dashed border-gray-200 rounded-2xl p-8 text-center bg-gray-50/50">
                  <p className="text-xs text-gray-500">Chưa có ảnh check-in cho chặng này.</p>
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-3">
                  {activePhotos.map((photo) => (
                    <button
                      key={photo.id}
                      type="button"
                      onClick={() => setPreviewPhotoUrl(photo.image_path)}
                      className="group relative h-36 rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition-all text-left"
                    >
                      <img
                        src={photo.image_path}
                        alt="Ảnh check-in đoàn"
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                      />
                      <div className="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                        🔍 Xem phóng to
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        )
      )}

      {/* Lightbox Xem ảnh */}
      {previewPhotoUrl && (
        <div
          className="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4 cursor-pointer"
          onClick={() => setPreviewPhotoUrl(null)}
        >
          <div className="relative max-w-4xl w-full max-h-[90vh] overflow-hidden rounded-2xl">
            <img
              src={previewPhotoUrl}
              alt="Ảnh check-in phóng to"
              className="w-full h-full object-contain max-h-[85vh] mx-auto"
            />
            <p className="text-center text-white text-xs font-medium mt-2">
              Bấm bất kỳ đâu để đóng
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
