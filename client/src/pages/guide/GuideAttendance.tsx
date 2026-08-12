import React, { useEffect, useMemo, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import guideService from "@/services/guideService";
import type { AttendanceData, AttendanceGuest } from "@/types/guide";
import { formatDateTime } from "@/utils/format";

export type AttendanceStatus = "present" | "absent" | "late" | "left_early" | "excused";

export interface AttendanceStatusConfig {
  label: string;
  badgeClass: string;
  buttonClass: string;
  icon: string;
}

export const ATTENDANCE_STATUSES: Record<AttendanceStatus, AttendanceStatusConfig> = {
  present: {
    label: "Có mặt",
    badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    buttonClass: "bg-emerald-600 text-white shadow-sm ring-emerald-500",
    icon: "✓",
  },
  absent: {
    label: "Vắng mặt",
    badgeClass: "bg-rose-50 text-rose-700 border-rose-200",
    buttonClass: "bg-rose-600 text-white shadow-sm ring-rose-500",
    icon: "✕",
  },
  late: {
    label: "Đi trễ",
    badgeClass: "bg-amber-50 text-amber-700 border-amber-200",
    buttonClass: "bg-amber-500 text-white shadow-sm ring-amber-400",
    icon: "🕒",
  },
  left_early: {
    label: "Rời đoàn",
    badgeClass: "bg-purple-50 text-purple-700 border-purple-200",
    buttonClass: "bg-purple-600 text-white shadow-sm ring-purple-500",
    icon: "🏃",
  },
  excused: {
    label: "Có lý do",
    badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    buttonClass: "bg-blue-600 text-white shadow-sm ring-blue-500",
    icon: "💬",
  },
};

const SUGGESTED_REASONS = [
  "Khách báo tự đi riêng theo lịch cá nhân",
  "Khách mệt, nghỉ ngơi tại khách sạn",
  "Khách chưa đến kịp, đang trên đường tới điểm tập trung",
  "Không liên lạc được qua SĐT khách",
  "Khách rời đoàn về sớm theo nguyện vọng",
];

// Key: `${itineraryId}:${bookingId}` -> { status, note }
type AttendanceMap = Record<
  string,
  { status: AttendanceStatus; note: string }
>;

const attendanceKey = (itineraryId: number, bookingId: number) =>
  `${itineraryId}:${bookingId}`;

export const GuideAttendance: React.FC = () => {
  const { scheduleId } = useParams<{ scheduleId: string }>();

  const [data, setData] = useState<AttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [activeItineraryId, setActiveItineraryId] = useState<number | null>(null);
  const [records, setRecords] = useState<AttendanceMap>({});
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: "success" | "error" } | null>(null);

  // State cho Modal nhập ghi chú vắng mặt
  const [activeNoteGuest, setActiveNoteGuest] = useState<{
    guest: AttendanceGuest;
    status: AttendanceStatus;
  } | null>(null);
  const [noteInput, setNoteInput] = useState("");

  // Lightbox xem ảnh
  const [previewPhotoUrl, setPreviewPhotoUrl] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!scheduleId) return;

    guideService
      .getAttendance(Number(scheduleId))
      .then((result) => {
        if (!result) {
          setError("Không tìm thấy lịch khởi hành được phân công.");
          return;
        }
        setData(result);
        setActiveItineraryId(result.itineraries[0]?.id ?? null);

        const initial: AttendanceMap = {};
        result.checkins.forEach((checkin) => {
          initial[attendanceKey(checkin.tour_itinerary_id, checkin.booking_id)] = {
            status: checkin.present ? "present" : "absent",
            note: "",
          };
        });
        setRecords(initial);
      })
      .catch(() => setError("Không thể tải dữ liệu điểm danh."))
      .finally(() => setLoading(false));
  }, [scheduleId]);

  useEffect(() => {
    if (toast) {
      const t = setTimeout(() => setToast(null), 3000);
      return () => clearTimeout(t);
    }
  }, [toast]);

  const activeItinerary = useMemo(
    () => data?.itineraries.find((item) => item.id === activeItineraryId) ?? null,
    [data, activeItineraryId],
  );

  const activePhotos = useMemo(
    () =>
      (data?.photos ?? []).filter(
        (photo) => photo.tour_itinerary_id === activeItineraryId,
      ),
    [data, activeItineraryId],
  );

  // Thống kê tiến độ
  const stats = useMemo(() => {
    if (!data || !activeItineraryId)
      return { present: 0, absent: 0, late: 0, leftEarly: 0, total: 0, percent: 0 };

    const total = data.guests.length;
    let present = 0;
    let absent = 0;
    let late = 0;
    let leftEarly = 0;

    data.guests.forEach((g) => {
      const rec = records[attendanceKey(activeItineraryId, g.id)];
      if (!rec || rec.status === "present") present++;
      else if (rec.status === "absent") absent++;
      else if (rec.status === "late") late++;
      else if (rec.status === "left_early") leftEarly++;
    });

    const percent = total > 0 ? Math.round((present / total) * 100) : 0;
    return { present, absent, late, leftEarly, total, percent };
  }, [data, activeItineraryId, records]);

  const setStatus = (bookingId: number, status: AttendanceStatus) => {
    if (!activeItineraryId) return;
    const key = attendanceKey(activeItineraryId, bookingId);
    const existing = records[key];

    // Nếu chọn trạng thái khác "present", mở Modal nhập lý do nếu chưa có note
    if (status !== "present") {
      const targetGuest = data?.guests.find((g) => g.id === bookingId);
      if (targetGuest) {
        setActiveNoteGuest({ guest: targetGuest, status });
        setNoteInput(existing?.note || "");
      }
    }

    setRecords((prev) => ({
      ...prev,
      [key]: {
        status,
        note: existing?.note || "",
      },
    }));
  };

  const handleSaveNote = () => {
    if (!activeNoteGuest || !activeItineraryId) return;
    const key = attendanceKey(activeItineraryId, activeNoteGuest.guest.id);
    setRecords((prev) => ({
      ...prev,
      [key]: {
        status: activeNoteGuest.status,
        note: noteInput.trim(),
      },
    }));
    setActiveNoteGuest(null);
    setNoteInput("");
  };

  const handleSave = async () => {
    if (!data || !scheduleId || !activeItineraryId) return;
    setSaving(true);
    try {
      const checkins = data.guests.map((guest) => {
        const rec = records[attendanceKey(activeItineraryId, guest.id)];
        return {
          booking_id: guest.id,
          present: rec ? rec.status === "present" : true,
        };
      });
      await guideService.saveAttendance(Number(scheduleId), activeItineraryId, checkins);
      setToast({ message: "Đã lưu thông tin điểm danh thành công!", type: "success" });
    } catch {
      setToast({ message: "Không thể lưu điểm danh. Vui lòng thử lại.", type: "error" });
    } finally {
      setSaving(false);
    }
  };

  const handlePhotoUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file || !scheduleId || !activeItineraryId) return;

    setUploading(true);
    try {
      const photo = await guideService.uploadCheckinPhoto(
        Number(scheduleId),
        activeItineraryId,
        file,
      );
      if (photo) {
        setData((prev) => (prev ? { ...prev, photos: [photo, ...prev.photos] } : prev));
        setToast({ message: "Đã tải ảnh check-in đoàn thành công!", type: "success" });
      }
    } catch {
      setToast({ message: "Không thể tải ảnh lên. Vui lòng thử lại.", type: "error" });
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  if (loading) {
    return (
      <div className="py-20 text-center space-y-3">
        <div className="w-10 h-10 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-sm font-medium text-gray-500">Đang tải dữ liệu điểm danh...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="space-y-4 max-w-2xl mx-auto py-12">
        <div className="rounded-2xl bg-rose-50 border border-rose-200 p-6 text-center text-rose-700">
          <p className="font-semibold">{error || "Không thể tải dữ liệu điểm danh."}</p>
          <Link
            to="/guide/tours"
            className="inline-block mt-4 text-xs font-bold text-rose-800 underline"
          >
            ← Quay lại danh sách Tour của tôi
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {/* Notification Toast */}
      {toast && (
        <div
          className={`fixed top-20 right-4 z-50 px-4 py-3 rounded-xl shadow-xl text-sm font-semibold text-white transition-all transform animate-bounce ${
            toast.type === "success" ? "bg-emerald-600" : "bg-rose-600"
          }`}
        >
          {toast.message}
        </div>
      )}

      {/* Header Tour & HDV */}
      <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <Link
            to="/guide/tours"
            className="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline mb-2"
          >
            ← Quay lại danh sách Tour
          </Link>
          <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 font-jakarta">
            Điểm danh đoàn du lịch
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            <span className="font-semibold text-gray-800">{data.tour.title}</span> · Khởi hành:{" "}
            <span className="text-primary-700 font-medium">{formatDateTime(data.schedule.start_date)}</span>
          </p>
        </div>

        {/* Action Save Button */}
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={handleSave}
            disabled={saving || data.guests.length === 0}
            className="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 active:scale-95 text-white text-sm font-bold rounded-2xl shadow-md transition-all disabled:opacity-50"
          >
            {saving ? (
              <>
                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span>Đang lưu...</span>
              </>
            ) : (
              <>
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
                <span>Lưu tất cả điểm danh</span>
              </>
            )}
          </button>
        </div>
      </div>

      {/* Tabs Chặng/Ngày */}
      <div className="flex flex-wrap gap-2.5">
        {data.itineraries.map((itinerary) => {
          const isActive = activeItineraryId === itinerary.id;
          return (
            <button
              key={itinerary.id}
              type="button"
              onClick={() => setActiveItineraryId(itinerary.id)}
              className={`px-4 py-3 rounded-2xl text-xs font-bold transition-all ${
                isActive
                  ? "bg-primary-600 text-white shadow-md -translate-y-0.5"
                  : "bg-white border border-gray-100 text-gray-700 hover:bg-gray-50 shadow-sm"
              }`}
            >
              <div className="flex items-center gap-2">
                <span>Ngày {itinerary.day_number}</span>
                {itinerary.start_point && itinerary.end_point && (
                  <span className={`text-[10px] ${isActive ? "text-primary-100" : "text-gray-400"}`}>
                    ({itinerary.start_point} → {itinerary.end_point})
                  </span>
                )}
              </div>
            </button>
          );
        })}
      </div>

      {/* Content Main Area */}
      {data.itineraries.length === 0 ? (
        <div className="bg-white rounded-3xl border border-gray-100 p-12 text-center text-gray-500">
          Tour này chưa thiết lập lịch trình theo ngày.
        </div>
      ) : (
        activeItinerary && (
          <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            {/* Cột trái: Bảng điểm danh các đoàn khách */}
            <div className="xl:col-span-2 space-y-6">
              {/* Card Thống kê tiến độ & Tiêu đề Ngày */}
              <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h2 className="text-lg font-bold text-gray-900 font-jakarta">
                      Ngày {activeItinerary.day_number}: {activeItinerary.title}
                    </h2>
                    <p className="text-xs text-gray-500 mt-1">
                      Tổng số: <span className="font-bold text-gray-800">{stats.total} đơn khách hàng</span>
                    </p>
                  </div>
                  <div className="text-right">
                    <span className="text-2xl font-extrabold text-emerald-600 font-jakarta">
                      {stats.percent}%
                    </span>
                    <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                      Có mặt ({stats.present}/{stats.total})
                    </p>
                  </div>
                </div>

                {/* Progress bar */}
                <div className="w-full h-3 bg-gray-100 rounded-full overflow-hidden flex">
                  <div
                    className="bg-emerald-500 transition-all duration-500"
                    style={{ width: `${(stats.present / (stats.total || 1)) * 100}%` }}
                    title={`Có mặt: ${stats.present}`}
                  />
                  <div
                    className="bg-rose-500 transition-all duration-500"
                    style={{ width: `${(stats.absent / (stats.total || 1)) * 100}%` }}
                    title={`Vắng: ${stats.absent}`}
                  />
                  <div
                    className="bg-amber-400 transition-all duration-500"
                    style={{ width: `${(stats.late / (stats.total || 1)) * 100}%` }}
                    title={`Trễ: ${stats.late}`}
                  />
                </div>
              </div>

              {/* Danh sách Khách hàng & Bộ chọn 5 trạng thái */}
              <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-100">
                <div className="px-6 py-4 bg-gray-50/50 flex items-center justify-between">
                  <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-600">
                    Danh sách hành khách đặt tour
                  </h3>
                  <span className="text-xs font-medium text-gray-500">
                    Bấm nút chọn trạng thái tương ứng
                  </span>
                </div>

                {data.guests.length === 0 ? (
                  <p className="p-8 text-center text-sm text-gray-500">
                    Chưa có đơn đặt tour nào được xác nhận cho chuyến đi này.
                  </p>
                ) : (
                  data.guests.map((guest) => {
                    const key = attendanceKey(activeItinerary.id, guest.id);
                    const record = records[key] || { status: "present", note: "" };
                    const currentStatus = record.status;

                    return (
                      <div key={guest.id} className="p-6 space-y-4 hover:bg-gray-50/40 transition-colors">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                          {/* Thông tin khách */}
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
                              {typeof guest.adult_count === "number" && (
                                <span className="text-gray-400 font-normal">
                                  {" "}
                                  ({guest.adult_count}NL
                                  {guest.child_count ? `, ${guest.child_count}TE` : ""}
                                  {guest.infant_count ? `, ${guest.infant_count}EB` : ""})
                                </span>
                              )}
                            </p>

                            {/* Danh sách tên thành viên */}
                            {guest.passengers && guest.passengers.length > 0 && (
                              <div className="flex flex-wrap gap-1.5 pt-1">
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

                          {/* Bộ 5 nút bấm Trạng thái điểm danh */}
                          <div className="flex flex-wrap items-center gap-1.5 shrink-0">
                            {(Object.keys(ATTENDANCE_STATUSES) as AttendanceStatus[]).map((st) => {
                              const cfg = ATTENDANCE_STATUSES[st];
                              const isSelected = currentStatus === st;
                              return (
                                <button
                                  key={st}
                                  type="button"
                                  onClick={() => setStatus(guest.id, st)}
                                  className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1 ${
                                    isSelected
                                      ? cfg.buttonClass
                                      : "bg-gray-100 text-gray-600 hover:bg-gray-200"
                                  }`}
                                >
                                  <span>{cfg.icon}</span>
                                  <span>{cfg.label}</span>
                                </button>
                              );
                            })}
                          </div>
                        </div>

                        {/* Ghi chú khi khác present */}
                        {currentStatus !== "present" && (
                          <div className="pt-2 flex items-center justify-between gap-3 text-xs bg-amber-50/60 p-3 rounded-2xl border border-amber-100">
                            <div className="space-y-0.5">
                              <span className="font-bold text-amber-800">
                                Ghi chú ({ATTENDANCE_STATUSES[currentStatus].label}):
                              </span>
                              <p className="text-amber-900">
                                {record.note ? `"${record.note}"` : <i className="text-amber-600">Chưa có ghi chú lý do</i>}
                              </p>
                            </div>
                            <button
                              type="button"
                              onClick={() => {
                                setActiveNoteGuest({ guest, status: currentStatus });
                                setNoteInput(record.note);
                              }}
                              className="px-3 py-1 bg-amber-200 text-amber-900 rounded-lg font-bold hover:bg-amber-300 transition-colors shrink-0"
                            >
                              Sửa ghi chú
                            </button>
                          </div>
                        )}
                      </div>
                    );
                  })
                )}
              </div>
            </div>

            {/* Cột phải: Ảnh check-in đoàn */}
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden p-6 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-gray-900 text-base font-jakarta">
                    Ảnh Check-in đoàn
                  </h3>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Bằng chứng hình ảnh tại chặng này
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploading}
                  className="px-4 py-2 bg-primary-50 text-primary-700 hover:bg-primary-100 font-bold text-xs rounded-2xl transition-colors disabled:opacity-50"
                >
                  {uploading ? "Đang tải..." : "+ Thêm ảnh đoàn"}
                </button>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  capture="environment"
                  className="hidden"
                  onChange={handlePhotoUpload}
                />
              </div>

              {activePhotos.length === 0 ? (
                <div className="border border-dashed border-gray-200 rounded-2xl p-8 text-center space-y-2 bg-gray-50/50">
                  <span className="text-3xl">📸</span>
                  <p className="text-xs text-gray-500">Chưa có ảnh check-in nào cho ngày này.</p>
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="text-xs font-bold text-primary-600 hover:underline"
                  >
                    + Bấm để tải ảnh check-in đầu tiên
                  </button>
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
                        🔍 Phóng to
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        )
      )}

      {/* Modal Nhập ghi chú khi vắng / đi trễ / rời đoàn */}
      {activeNoteGuest && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl animate-fade-in">
            <div>
              <h3 className="text-lg font-bold text-gray-900 font-jakarta">
                Nhập lý do ({ATTENDANCE_STATUSES[activeNoteGuest.status].label})
              </h3>
              <p className="text-xs text-gray-500 mt-0.5">
                Khách hàng: <span className="font-bold text-gray-800">{activeNoteGuest.guest.customer_name}</span> (BK-{activeNoteGuest.guest.id})
              </p>
            </div>

            {/* Chip lý do gợi ý nhanh */}
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-gray-700">
                Gợi ý lý do phổ biến:
              </label>
              <div className="flex flex-wrap gap-1.5">
                {SUGGESTED_REASONS.map((reason, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => setNoteInput(reason)}
                    className="px-2.5 py-1 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 text-[11px] font-medium text-left transition-colors"
                  >
                    💡 {reason}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Ghi chú chi tiết:
              </label>
              <textarea
                rows={3}
                value={noteInput}
                onChange={(e) => setNoteInput(e.target.value)}
                placeholder="Nhập chi tiết ghi chú điểm danh..."
                className="w-full rounded-2xl border border-gray-200 p-3 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none"
              />
            </div>

            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setActiveNoteGuest(null)}
                className="px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={handleSaveNote}
                className="px-5 py-2 rounded-xl bg-primary-600 text-xs font-bold text-white hover:bg-primary-700 shadow-sm"
              >
                Xác nhận ghi chú
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Lightbox Phóng to ảnh check-in */}
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
};

export default GuideAttendance;
