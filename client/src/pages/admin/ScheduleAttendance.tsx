import { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import adminService from "@/services/adminService";
import type {
  AttendanceBooking,
  AttendanceCheckin,
  AttendanceCheckpoint,
  AttendanceItinerary,
  CheckpointPhoto,
} from "@/types/guide";
import { ATTENDANCE_STATUSES, ATTENDANCE_STATUS_ORDER } from "@/utils/attendance";
import { formatDateTime } from "@/utils/format";

/**
 * Báo cáo điểm danh của một chuyến, phía quản trị. Chỉ đọc.
 *
 * Cùng mô hình với màn hướng dẫn viên: từng hành khách tại từng điểm dừng. Điều hành cần thấy
 * cả người chưa được ghi nhận, nên màn này đi từ danh sách đoàn rồi tra ngược sang điểm danh,
 * chứ không liệt kê các bản ghi điểm danh đã có.
 */
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
  checkpoints: AttendanceCheckpoint[];
  bookings: AttendanceBooking[];
  total_passengers: number;
  checkins: AttendanceCheckin[];
  photos: CheckpointPhoto[];
}

export default function ScheduleAttendance() {
  const { scheduleId } = useParams<{ scheduleId: string }>();

  const [data, setData] = useState<AdminAttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [activeCheckpointId, setActiveCheckpointId] = useState<number | null>(null);
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
      })
      .catch(() => setError("Không thể tải dữ liệu điểm danh."))
      .finally(() => setLoading(false));
  }, [scheduleId]);

  const orderedCheckpoints = useMemo(() => {
    return [...(data?.checkpoints ?? [])].sort((a, b) => {
      const dayA = a.tour_itinerary?.day_number ?? 0;
      const dayB = b.tour_itinerary?.day_number ?? 0;
      return dayA !== dayB ? dayA - dayB : a.sequence - b.sequence;
    });
  }, [data]);

  useEffect(() => {
    if (activeCheckpointId === null && orderedCheckpoints.length > 0) {
      setActiveCheckpointId(orderedCheckpoints[0].id);
    }
  }, [orderedCheckpoints, activeCheckpointId]);

  /** Tra điểm danh theo cặp điểm dừng và hành khách. */
  const checkinByPassenger = useMemo(() => {
    const map = new Map<string, AttendanceCheckin>();
    data?.checkins.forEach((checkin) => {
      map.set(`${checkin.itinerary_checkpoint_id}:${checkin.booking_passenger_id}`, checkin);
    });
    return map;
  }, [data]);

  const activeCheckpoint = useMemo(
    () => orderedCheckpoints.find((item) => item.id === activeCheckpointId) ?? null,
    [orderedCheckpoints, activeCheckpointId],
  );

  const activePhotos = useMemo(
    () =>
      (data?.photos ?? []).filter((photo) => photo.itinerary_checkpoint_id === activeCheckpointId),
    [data, activeCheckpointId],
  );

  const allPassengers = useMemo(
    () => (data?.bookings ?? []).flatMap((booking) => booking.passengers ?? []),
    [data],
  );

  const stats = useMemo(() => {
    const counts = { present: 0, absent: 0, late: 0, left_early: 0, excused: 0 };
    const total = allPassengers.length;

    if (activeCheckpointId === null) {
      return { ...counts, total, recorded: 0, pending: total };
    }

    let recorded = 0;
    allPassengers.forEach((passenger) => {
      const checkin = checkinByPassenger.get(`${activeCheckpointId}:${passenger.id}`);
      if (!checkin) return;
      counts[checkin.status]++;
      recorded++;
    });

    return { ...counts, total, recorded, pending: total - recorded };
  }, [allPassengers, checkinByPassenger, activeCheckpointId]);

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

  const groupedByDay = orderedCheckpoints.reduce<[number, AttendanceCheckpoint[]][]>(
    (groups, checkpoint) => {
      const day = checkpoint.tour_itinerary?.day_number ?? 0;
      const existing = groups.find(([value]) => value === day);
      if (existing) {
        existing[1].push(checkpoint);
      } else {
        groups.push([day, [checkpoint]]);
      }
      return groups;
    },
    [],
  );

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <Link
            to="/admin/schedules"
            className="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline mb-2"
          >
            ← Quay lại Quản lý Chuyến
          </Link>
          <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 font-jakarta">
            Báo cáo điểm danh &amp; Check-in đoàn
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            <span className="font-semibold text-gray-800">{data.tour.title}</span> · Khởi hành:{" "}
            <span className="text-primary-700 font-medium">
              {formatDateTime(data.schedule.start_date)}
            </span>
            {data.schedule.guide ? (
              <span className="ml-2 font-bold text-emerald-700">
                · HDV: {data.schedule.guide.name}
              </span>
            ) : (
              <span className="ml-2 text-rose-600 font-medium">· Chưa phân công HDV</span>
            )}
          </p>
        </div>
      </div>

      {groupedByDay.length === 0 ? (
        <div className="bg-white rounded-3xl border border-gray-100 p-12 text-center text-gray-500">
          Tour này chưa khai báo điểm dừng nào nên chưa có dữ liệu điểm danh.
        </div>
      ) : (
        <>
          <div className="space-y-3">
            {groupedByDay.map(([day, checkpoints]) => (
              <div key={day} className="flex flex-wrap items-center gap-2">
                <span className="text-[11px] font-extrabold uppercase tracking-wider text-gray-500 w-16 shrink-0">
                  Ngày {day}
                </span>
                {checkpoints.map((checkpoint) => {
                  const isActive = activeCheckpointId === checkpoint.id;
                  const soAnh = data.photos.filter(
                    (photo) => photo.itinerary_checkpoint_id === checkpoint.id,
                  ).length;

                  return (
                    <button
                      key={checkpoint.id}
                      type="button"
                      onClick={() => setActiveCheckpointId(checkpoint.id)}
                      className={`px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 ${
                        isActive
                          ? "bg-primary-600 text-white shadow-md -translate-y-0.5"
                          : "bg-white border border-gray-100 text-gray-700 hover:bg-gray-50 shadow-sm"
                      }`}
                    >
                      <span>{checkpoint.name}</span>
                      {checkpoint.is_required_photo && (
                        <span title="Điểm dừng bắt buộc có ảnh check-in">
                          {soAnh > 0 ? "📷" : "⚠️"}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            ))}
          </div>

          {activeCheckpoint && (
            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
              <div className="xl:col-span-2 space-y-6">
                <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm space-y-4">
                  <div>
                    <h2 className="text-lg font-bold text-gray-900 font-jakarta">
                      {activeCheckpoint.name}
                    </h2>
                    <p className="text-xs text-gray-500 mt-1">
                      Ngày {activeCheckpoint.tour_itinerary?.day_number ?? "?"}
                      {activeCheckpoint.tour_itinerary?.title
                        ? ` · ${activeCheckpoint.tour_itinerary.title}`
                        : ""}{" "}
                      · <span className="font-bold text-gray-800">{stats.total} hành khách</span>
                    </p>
                  </div>

                  <div className="flex flex-wrap gap-2">
                    {ATTENDANCE_STATUS_ORDER.map((status) => (
                      <span
                        key={status}
                        className={`px-2.5 py-1 rounded-lg border text-[11px] font-bold ${ATTENDANCE_STATUSES[status].badgeClass}`}
                      >
                        {ATTENDANCE_STATUSES[status].icon} {ATTENDANCE_STATUSES[status].label}:{" "}
                        {stats[status]}
                      </span>
                    ))}
                    <span className="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-[11px] font-bold">
                      Chưa ghi nhận: {stats.pending}
                    </span>
                  </div>

                  {activeCheckpoint.is_required_photo && activePhotos.length === 0 && (
                    <p className="text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-2xl p-3">
                      ⚠️ Điểm dừng bắt buộc có ảnh check-in nhưng hướng dẫn viên chưa gửi ảnh nào.
                    </p>
                  )}
                </div>

                <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-100">
                  <div className="px-6 py-4 bg-gray-50/50 flex items-center justify-between">
                    <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-600">
                      Chi tiết theo từng hành khách
                    </h3>
                    <span className="text-xs font-semibold text-gray-500">
                      {data.bookings.length} đơn đặt chỗ
                    </span>
                  </div>

                  {data.bookings.length === 0 ? (
                    <p className="p-8 text-center text-sm text-gray-500">
                      Chưa có đơn đặt tour nào còn hiệu lực cho chuyến này.
                    </p>
                  ) : (
                    data.bookings.map((booking) => (
                      <div key={booking.id} className="p-6 space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-bold text-gray-900 text-base">
                            {booking.customer_name}
                          </span>
                          <span className="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-mono text-[11px] font-semibold">
                            BK-{booking.id}
                          </span>
                          <span className="text-xs text-gray-500">
                            📞 {booking.customer_phone || "Không có SĐT"} · 👥 {booking.guests} khách
                          </span>
                        </div>

                        {(booking.passengers ?? []).length === 0 ? (
                          <p className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">
                            Đơn này chưa khai danh sách hành khách.
                          </p>
                        ) : (
                          <div className="space-y-2">
                            {(booking.passengers ?? []).map((passenger) => {
                              const checkin = checkinByPassenger.get(
                                `${activeCheckpoint.id}:${passenger.id}`,
                              );
                              const config = checkin ? ATTENDANCE_STATUSES[checkin.status] : null;

                              return (
                                <div
                                  key={passenger.id}
                                  className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-xl border border-gray-100 p-3"
                                >
                                  <div className="min-w-0">
                                    <span className="font-semibold text-gray-900 text-sm">
                                      👤 {passenger.name}
                                    </span>
                                    {checkin?.note && (
                                      <p className="text-xs text-gray-500 mt-0.5 truncate">
                                        “{checkin.note}”
                                      </p>
                                    )}
                                  </div>

                                  <div className="flex items-center gap-1.5 shrink-0">
                                    {checkin?.is_late_entry && (
                                      <span
                                        className="px-2 py-1 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-[11px] font-bold"
                                        title="Ghi bù sau thời điểm thực tế"
                                      >
                                        Ghi bù
                                      </span>
                                    )}
                                    <span
                                      className={`inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold border ${
                                        config
                                          ? config.badgeClass
                                          : "bg-gray-100 text-gray-500 border-gray-200"
                                      }`}
                                    >
                                      <span>{config ? config.icon : "⏳"}</span>
                                      <span>{config ? config.label : "Chưa điểm danh"}</span>
                                    </span>
                                  </div>
                                </div>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    ))
                  )}
                </div>
              </div>

              <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden p-6 space-y-4">
                <div>
                  <h3 className="font-bold text-gray-900 text-base font-jakarta">Ảnh check-in</h3>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Do hướng dẫn viên chụp tại {activeCheckpoint.name}
                  </p>
                </div>

                {activePhotos.length === 0 ? (
                  <div className="border border-dashed border-gray-200 rounded-2xl p-8 text-center bg-gray-50/50">
                    <p className="text-xs text-gray-500">Chưa có ảnh nào tại điểm dừng này.</p>
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
                          alt={`Ảnh check-in tại ${activeCheckpoint.name}`}
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
          )}
        </>
      )}

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
