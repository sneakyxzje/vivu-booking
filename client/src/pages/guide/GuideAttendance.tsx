import React, { useEffect, useMemo, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import guideService from "@/services/guideService";
import type { AttendanceData } from "@/types/guide";
import { formatDateTime } from "@/utils/format";

// key dạng `${itineraryId}:${bookingId}` -> có mặt hay không
type PresenceMap = Record<string, boolean>;

const presenceKey = (itineraryId: number, bookingId: number) =>
  `${itineraryId}:${bookingId}`;

export const GuideAttendance: React.FC = () => {
  const { scheduleId } = useParams<{ scheduleId: string }>();

  const [data, setData] = useState<AttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [activeItineraryId, setActiveItineraryId] = useState<number | null>(null);
  const [presence, setPresence] = useState<PresenceMap>({});
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [toast, setToast] = useState("");
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

        const initial: PresenceMap = {};
        result.checkins.forEach((checkin) => {
          initial[presenceKey(checkin.tour_itinerary_id, checkin.booking_id)] =
            checkin.present;
        });
        setPresence(initial);
      })
      .catch(() => setError("Không thể tải dữ liệu điểm danh."))
      .finally(() => setLoading(false));
  }, [scheduleId]);

  useEffect(() => {
    if (toast) {
      const t = setTimeout(() => setToast(""), 3000);
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

  const presentCount = useMemo(() => {
    if (!data || !activeItineraryId) return 0;
    return data.guests.filter(
      (guest) => presence[presenceKey(activeItineraryId, guest.id)],
    ).length;
  }, [data, activeItineraryId, presence]);

  const togglePresence = (bookingId: number) => {
    if (!activeItineraryId) return;
    const key = presenceKey(activeItineraryId, bookingId);
    setPresence((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const handleSave = async () => {
    if (!data || !scheduleId || !activeItineraryId) return;
    setSaving(true);
    try {
      const checkins = data.guests.map((guest) => ({
        booking_id: guest.id,
        present: Boolean(presence[presenceKey(activeItineraryId, guest.id)]),
      }));
      await guideService.saveAttendance(Number(scheduleId), activeItineraryId, checkins);
      setToast("Đã lưu điểm danh.");
    } catch {
      setToast("Không thể lưu điểm danh. Vui lòng thử lại.");
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
        setToast("Đã lưu ảnh check-in.");
      }
    } catch {
      setToast("Không thể tải ảnh lên. Vui lòng thử lại.");
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  if (loading) {
    return <div className="text-center py-16 text-gray-500">Đang tải...</div>;
  }

  if (error || !data) {
    return (
      <div className="space-y-4">
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {error || "Không thể tải dữ liệu điểm danh."}
        </div>
        <Link to="/guide/tours" className="text-sm font-semibold text-primary-600 hover:underline">
          ← Quay lại danh sách tour
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {toast && (
        <div className="fixed top-24 right-4 z-50 bg-emerald-600 text-white text-sm font-medium px-4 py-3 rounded-lg shadow-lg">
          {toast}
        </div>
      )}

      <div>
        <Link
          to="/guide/tours"
          className="text-sm font-semibold text-primary-600 hover:underline"
        >
          ← Tour của tôi
        </Link>
        <h1 className="text-2xl font-bold text-gray-900 mt-2">Điểm danh đoàn</h1>
        <p className="text-gray-500 text-sm mt-1">
          {data.tour.title} — khởi hành {formatDateTime(data.schedule.start_date)} ·{" "}
          {data.guests.length} đơn ({data.guests.reduce((sum, guest) => sum + guest.guests, 0)} khách)
        </p>
      </div>

      {/* Tabs theo chặng */}
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
          Tour này chưa có lịch trình để điểm danh.
        </div>
      ) : (
        activeItinerary && (
          <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            {/* Danh sách điểm danh */}
            <div className="xl:col-span-2 bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
              <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                  <h2 className="font-semibold text-gray-900">{activeItinerary.title}</h2>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Có mặt {presentCount}/{data.guests.length} đơn
                  </p>
                </div>
                <button
                  type="button"
                  onClick={handleSave}
                  disabled={saving || data.guests.length === 0}
                  className="px-4 py-2 bg-primary-600 text-white text-xs font-semibold rounded-lg hover:bg-primary-700 disabled:opacity-50"
                >
                  {saving ? "Đang lưu..." : "Lưu điểm danh"}
                </button>
              </div>

              {data.guests.length === 0 ? (
                <p className="p-6 text-sm text-gray-500">
                  Chưa có đơn nào được xác nhận cho lịch khởi hành này.
                </p>
              ) : (
                <ul className="divide-y divide-gray-50">
                  {data.guests.map((guest) => {
                    const checked = Boolean(
                      presence[presenceKey(activeItinerary.id, guest.id)],
                    );
                    return (
                      <li key={guest.id}>
                        <label className="px-6 py-4 flex items-center justify-between gap-4 cursor-pointer hover:bg-gray-50/50">
                          <div className="min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">
                              {guest.customer_name}
                              <span className="ml-2 font-mono text-xs text-gray-400">
                                BK-{guest.id}
                              </span>
                            </p>
                            <p className="text-xs text-gray-500 mt-0.5">
                              {guest.customer_phone || "Không có SĐT"} · {guest.guests} khách
                              {typeof guest.adult_count === "number"
                                ? ` (${guest.adult_count} người lớn, ${guest.child_count ?? 0} trẻ em, ${guest.infant_count ?? 0} em bé)`
                                : ""}
                            </p>
                            {guest.passengers && guest.passengers.length > 0 && (
                              <p className="text-xs text-gray-400 mt-1 truncate">
                                {guest.passengers.map((passenger) => passenger.name).join(", ")}
                              </p>
                            )}
                          </div>
                          <span className="flex items-center gap-2 shrink-0">
                            <span
                              className={`text-xs font-semibold ${checked ? "text-emerald-600" : "text-gray-400"}`}
                            >
                              {checked ? "Có mặt" : "Chưa điểm danh"}
                            </span>
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={() => togglePresence(guest.id)}
                              className="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                          </span>
                        </label>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>

            {/* Ảnh check-in đoàn */}
            <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
              <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 className="font-semibold text-gray-900">Ảnh check-in đoàn</h2>
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploading}
                  className="px-3 py-1.5 bg-primary-50 text-primary-600 text-xs font-semibold rounded-lg hover:bg-primary-100 disabled:opacity-50"
                >
                  {uploading ? "Đang tải..." : "+ Thêm ảnh"}
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
                <p className="p-6 text-sm text-gray-500">
                  Chưa có ảnh check-in cho chặng này.
                </p>
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
};

export default GuideAttendance;
