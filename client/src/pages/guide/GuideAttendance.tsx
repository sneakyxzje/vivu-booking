import React, { useEffect, useMemo, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import guideService from "@/services/guideService";
import type {
  AttendanceCheckinInput,
  AttendanceCheckpoint,
  AttendanceData,
  AttendancePassenger,
  PassengerCheckinStatus,
} from "@/types/guide";
import {
  ATTENDANCE_STATUSES,
  ATTENDANCE_STATUS_ORDER,
  MIN_ATTENDANCE_NOTE_LENGTH,
  noteIsValid,
  requiresNote,
  SUGGESTED_REASONS,
} from "@/utils/attendance";
import { formatDateTime } from "@/utils/format";

/**
 * H11 - Điểm danh của hướng dẫn viên.
 *
 * Đơn vị điểm danh là từng hành khách tại từng điểm dừng, không phải từng đơn theo ngày. Một
 * đơn hai người thì hai người có thể khác trạng thái, và một ngày hành trình có nhiều điểm dừng
 * nên khách vắng ở điểm tham quan buổi chiều không đồng nghĩa với không lên xe buổi sáng.
 *
 * Màn này chỉ gửi những người hướng dẫn viên thực sự bấm. Không mặc định "có mặt" cho phần còn
 * lại: điểm danh là dữ liệu đối chiếu khi khiếu nại, đoán hộ một trạng thái chưa ai xác nhận là
 * tạo ra bằng chứng giả.
 */

type AttendanceRecord = { status: PassengerCheckinStatus; note: string };
type AttendanceMap = Record<string, AttendanceRecord>;

const recordKey = (checkpointId: number, passengerId: number) => `${checkpointId}:${passengerId}`;

const errorMessage = (error: unknown, fallback: string): string => {
  const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return typeof message === "string" && message.length > 0 ? message : fallback;
};

/** Lấy tọa độ hiện tại. Máy chủ bắt buộc có tọa độ mới nhận ảnh check-in. */
const currentPosition = (): Promise<{ latitude: number; longitude: number }> =>
  new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error("Thiết bị không hỗ trợ định vị."));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) =>
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        }),
      () => reject(new Error("Không lấy được vị trí. Vui lòng bật định vị và cho phép truy cập.")),
      { enableHighAccuracy: true, timeout: 15000 },
    );
  });

export const GuideAttendance: React.FC = () => {
  const { scheduleId } = useParams<{ scheduleId: string }>();

  const [data, setData] = useState<AttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [activeCheckpointId, setActiveCheckpointId] = useState<number | null>(null);
  const [records, setRecords] = useState<AttendanceMap>({});
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: "success" | "error" } | null>(null);

  const [activeNote, setActiveNote] = useState<{
    passenger: AttendancePassenger;
    customerName: string;
    status: PassengerCheckinStatus;
  } | null>(null);
  const [noteInput, setNoteInput] = useState("");

  const [previewPhotoUrl, setPreviewPhotoUrl] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  /** Điểm dừng sắp theo ngày rồi tới thứ tự trong ngày, giống thứ tự đoàn thực sự đi qua. */
  const orderedCheckpoints = useMemo(() => {
    return [...(data?.checkpoints ?? [])].sort((a, b) => {
      const dayA = a.tour_itinerary?.day_number ?? 0;
      const dayB = b.tour_itinerary?.day_number ?? 0;
      return dayA !== dayB ? dayA - dayB : a.sequence - b.sequence;
    });
  }, [data]);

  const groupedByDay = useMemo(() => {
    const groups = new Map<number, AttendanceCheckpoint[]>();
    orderedCheckpoints.forEach((checkpoint) => {
      const day = checkpoint.tour_itinerary?.day_number ?? 0;
      groups.set(day, [...(groups.get(day) ?? []), checkpoint]);
    });
    return [...groups.entries()].sort((a, b) => a[0] - b[0]);
  }, [orderedCheckpoints]);

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

        const initial: AttendanceMap = {};
        result.checkins.forEach((checkin) => {
          initial[recordKey(checkin.itinerary_checkpoint_id, checkin.booking_passenger_id)] = {
            status: checkin.status,
            note: checkin.note ?? "",
          };
        });
        setRecords(initial);
      })
      .catch((err) => setError(errorMessage(err, "Không thể tải dữ liệu điểm danh.")))
      .finally(() => setLoading(false));
  }, [scheduleId]);

  // Chọn sẵn điểm dừng đầu tiên sau khi đã biết thứ tự thật, không dựa vào thứ tự trả về.
  useEffect(() => {
    if (activeCheckpointId === null && orderedCheckpoints.length > 0) {
      setActiveCheckpointId(orderedCheckpoints[0].id);
    }
  }, [orderedCheckpoints, activeCheckpointId]);

  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(null), 4000);
    return () => clearTimeout(timer);
  }, [toast]);

  const activeCheckpoint = useMemo(
    () => orderedCheckpoints.find((item) => item.id === activeCheckpointId) ?? null,
    [orderedCheckpoints, activeCheckpointId],
  );

  const activePhotos = useMemo(
    () => (data?.photos ?? []).filter((photo) => photo.itinerary_checkpoint_id === activeCheckpointId),
    [data, activeCheckpointId],
  );

  const allPassengers = useMemo(
    () => (data?.bookings ?? []).flatMap((booking) => booking.passengers ?? []),
    [data],
  );

  const stats = useMemo(() => {
    const total = allPassengers.length;
    const counts: Record<PassengerCheckinStatus, number> = {
      present: 0,
      absent: 0,
      late: 0,
      left_early: 0,
      excused: 0,
    };

    if (activeCheckpointId === null) {
      return { ...counts, total, recorded: 0, pending: total, percent: 0 };
    }

    let recorded = 0;
    allPassengers.forEach((passenger) => {
      const record = records[recordKey(activeCheckpointId, passenger.id)];
      if (!record) return;
      counts[record.status]++;
      recorded++;
    });

    return {
      ...counts,
      total,
      recorded,
      pending: total - recorded,
      percent: total > 0 ? Math.round((recorded / total) * 100) : 0,
    };
  }, [allPassengers, records, activeCheckpointId]);

  const setStatus = (
    passenger: AttendancePassenger,
    customerName: string,
    status: PassengerCheckinStatus,
  ) => {
    if (activeCheckpointId === null) return;

    const key = recordKey(activeCheckpointId, passenger.id);
    const existing = records[key];

    setRecords((prev) => ({
      ...prev,
      [key]: { status, note: existing?.note ?? "" },
    }));

    // Trạng thái nào cần lý do thì mở ngay ô nhập, đỡ phải nhớ quay lại điền.
    if (requiresNote(status)) {
      setActiveNote({ passenger, customerName, status });
      setNoteInput(existing?.note ?? "");
    }
  };

  const handleSaveNote = () => {
    if (!activeNote || activeCheckpointId === null) return;

    setRecords((prev) => ({
      ...prev,
      [recordKey(activeCheckpointId, activeNote.passenger.id)]: {
        status: activeNote.status,
        note: noteInput.trim(),
      },
    }));

    setActiveNote(null);
    setNoteInput("");
  };

  const handleSave = async () => {
    if (!data || !scheduleId || activeCheckpointId === null) return;

    const payload: AttendanceCheckinInput[] = [];
    const thieuGhiChu: string[] = [];

    allPassengers.forEach((passenger) => {
      const record = records[recordKey(activeCheckpointId, passenger.id)];
      if (!record) return;

      if (!noteIsValid(record.status, record.note)) {
        thieuGhiChu.push(passenger.name);
        return;
      }

      payload.push({
        booking_passenger_id: passenger.id,
        status: record.status,
        note: record.note.trim() || null,
      });
    });

    if (thieuGhiChu.length > 0) {
      setToast({
        message:
          `Cần ghi chú ít nhất ${MIN_ATTENDANCE_NOTE_LENGTH} ký tự cho: ` + thieuGhiChu.join(", "),
        type: "error",
      });
      return;
    }

    if (payload.length === 0) {
      setToast({ message: "Chưa chọn trạng thái cho hành khách nào.", type: "error" });
      return;
    }

    setSaving(true);
    try {
      const result = await guideService.saveAttendance(
        Number(scheduleId),
        activeCheckpointId,
        payload,
      );

      // Ghi lại theo phản hồi của máy chủ chứ không giữ nguyên trạng thái đang gõ: máy chủ có
      // thể bỏ qua hành khách của đơn chưa xác nhận, giữ nguyên màn hình sẽ hiện sai.
      if (result) {
        setData((prev) => {
          if (!prev) return prev;
          const conLai = prev.checkins.filter(
            (checkin) => checkin.itinerary_checkpoint_id !== activeCheckpointId,
          );
          return { ...prev, checkins: [...conLai, ...result.checkins] };
        });

        setRecords((prev) => {
          const next = { ...prev };
          allPassengers.forEach((passenger) => {
            delete next[recordKey(activeCheckpointId, passenger.id)];
          });
          result.checkins.forEach((checkin) => {
            next[recordKey(activeCheckpointId, checkin.booking_passenger_id)] = {
              status: checkin.status,
              note: checkin.note ?? "",
            };
          });
          return next;
        });

        const boQua = payload.length - result.saved;
        setToast({
          message:
            `Đã lưu điểm danh cho ${result.saved} hành khách.` +
            (boQua > 0 ? ` Bỏ qua ${boQua} người thuộc đơn chưa xác nhận.` : ""),
          type: "success",
        });
      }
    } catch (err) {
      setToast({
        message: errorMessage(err, "Không thể lưu điểm danh. Vui lòng thử lại."),
        type: "error",
      });
    } finally {
      setSaving(false);
    }
  };

  const handlePhotoUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file || !scheduleId || activeCheckpointId === null) return;

    setUploading(true);
    try {
      const coords = await currentPosition();
      const result = await guideService.uploadCheckinPhoto(
        Number(scheduleId),
        activeCheckpointId,
        file,
        coords,
      );

      if (result?.photo) {
        const photo = result.photo;
        setData((prev) => (prev ? { ...prev, photos: [photo, ...prev.photos] } : prev));
        setToast({
          message: result.warning
            ? result.warning_message ?? "Đã lưu ảnh nhưng vị trí chụp ở xa điểm dừng."
            : "Đã tải ảnh check-in thành công.",
          type: result.warning ? "error" : "success",
        });
      }
    } catch (err) {
      const fallback =
        err instanceof Error ? err.message : "Không thể tải ảnh lên. Vui lòng thử lại.";
      setToast({ message: errorMessage(err, fallback), type: "error" });
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
            Quay lại danh sách Tour của tôi
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {toast && (
        <div
          className={`fixed top-20 right-4 z-50 max-w-sm px-4 py-3 rounded-xl shadow-xl text-sm font-semibold text-white ${
            toast.type === "success" ? "bg-emerald-600" : "bg-rose-600"
          }`}
        >
          {toast.message}
        </div>
      )}

      <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <Link
            to="/guide/tours"
            className="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline mb-2"
          >
            Quay lại danh sách Tour
          </Link>
          <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 font-jakarta">
            Điểm danh đoàn du lịch
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            <span className="font-semibold text-gray-800">{data.tour.title}</span> · Khởi hành:{" "}
            <span className="text-primary-700 font-medium">
              {formatDateTime(data.schedule.start_date)}
            </span>
          </p>
        </div>

        <button
          type="button"
          onClick={handleSave}
          disabled={saving || activeCheckpointId === null || allPassengers.length === 0}
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
              <span>Lưu điểm danh điểm dừng này</span>
            </>
          )}
        </button>
      </div>

      {/* Điểm dừng, gom theo ngày hành trình */}
      {groupedByDay.length === 0 ? (
        <div className="bg-white rounded-3xl border border-gray-100 p-12 text-center space-y-2">
          <p className="text-gray-600 font-semibold">Tour này chưa thiết lập điểm dừng nào.</p>
          <p className="text-xs text-gray-500">
            Quản trị viên cần khai báo điểm dừng cho từng ngày trong lịch trình trước khi điểm danh.
          </p>
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
                  const soAnh = (data.photos ?? []).filter(
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
                      {/* Điểm dừng bắt buộc có ảnh — nói bằng chữ thay vì biểu tượng phải đoán. */}
                      {checkpoint.is_required_photo && (
                        <span
                          className={`text-[10px] font-bold uppercase tracking-wide ${
                            isActive
                              ? "text-white/80"
                              : soAnh > 0
                                ? "text-emerald-600"
                                : "text-amber-600"
                          }`}
                        >
                          {soAnh > 0 ? "Có ảnh" : "Thiếu ảnh"}
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
                {/* Tiến độ tại điểm dừng đang chọn */}
                <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm space-y-4">
                  <div className="flex items-start justify-between gap-4">
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
                    <div className="text-right shrink-0">
                      <span className="text-2xl font-extrabold text-primary-600 font-jakarta">
                        {stats.percent}%
                      </span>
                      <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                        Đã ghi ({stats.recorded}/{stats.total})
                      </p>
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-2">
                    {ATTENDANCE_STATUS_ORDER.map((status) => (
                      <span
                        key={status}
                        className={`px-2.5 py-1 rounded-lg border text-[11px] font-bold ${ATTENDANCE_STATUSES[status].badgeClass}`}
                      >
                        {ATTENDANCE_STATUSES[status].label}:{" "}
                        {stats[status]}
                      </span>
                    ))}
                    <span className="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-[11px] font-bold">
                      Chưa ghi: {stats.pending}
                    </span>
                  </div>

                  {activeCheckpoint.is_required_photo && activePhotos.length === 0 && (
                    <p className="text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-2xl p-3">
                      Điểm dừng này bắt buộc có ảnh check-in. Vui lòng chụp ảnh đoàn trước khi rời điểm.
                    </p>
                  )}
                </div>

                {/* Danh sách hành khách theo từng đơn */}
                <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-100">
                  <div className="px-6 py-4 bg-gray-50/50 flex items-center justify-between">
                    <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-600">
                      Danh sách đoàn
                    </h3>
                    <span className="text-xs font-medium text-gray-500">
                      Điểm danh theo từng người
                    </span>
                  </div>

                  {data.bookings.length === 0 ? (
                    <p className="p-8 text-center text-sm text-gray-500">
                      Chưa có đơn đặt tour nào được xác nhận cho chuyến đi này.
                    </p>
                  ) : (
                    data.bookings.map((booking) => (
                      <div key={booking.id} className="p-6 space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-bold text-gray-900 text-base">
                            {booking.customer_name}
                          </span>
                          <span className="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-mono text-[11px] font-semibold">
                            BK-{booking.id}
                          </span>
                          <span className="text-xs text-gray-500">
                            {booking.customer_phone || "Không có SĐT"} · {booking.guests} khách
                          </span>
                        </div>

                        {(booking.passengers ?? []).length === 0 ? (
                          <p className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">
                            Đơn này chưa khai danh sách hành khách nên chưa điểm danh được.
                          </p>
                        ) : (
                          <div className="space-y-3">
                            {(booking.passengers ?? []).map((passenger) => {
                              const record = records[recordKey(activeCheckpoint.id, passenger.id)];
                              const thieuGhiChu =
                                record && !noteIsValid(record.status, record.note);

                              return (
                                <div
                                  key={passenger.id}
                                  className="rounded-2xl border border-gray-100 p-4 space-y-3 hover:bg-gray-50/40 transition-colors"
                                >
                                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                      <span className="font-semibold text-gray-900 text-sm">
                                        {passenger.name}
                                      </span>
                                      <span className="px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 text-[11px] font-medium">
                                        {passenger.type === "adult"
                                          ? "Người lớn"
                                          : passenger.type === "child"
                                            ? "Trẻ em"
                                            : "Em bé"}
                                      </span>
                                      {!record && (
                                        <span className="px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 text-[11px] font-semibold">
                                          Chưa ghi
                                        </span>
                                      )}
                                    </div>

                                    <div className="flex flex-wrap items-center gap-1.5 shrink-0">
                                      {ATTENDANCE_STATUS_ORDER.map((status) => {
                                        const config = ATTENDANCE_STATUSES[status];
                                        const isSelected = record?.status === status;
                                        return (
                                          <button
                                            key={status}
                                            type="button"
                                            onClick={() =>
                                              setStatus(passenger, booking.customer_name, status)
                                            }
                                            className={`px-2.5 py-1.5 rounded-xl text-[11px] font-bold transition-all flex items-center gap-1 ${
                                              isSelected
                                                ? config.buttonClass
                                                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
                                            }`}
                                          >
                                            {config.label}
                                          </button>
                                        );
                                      })}
                                    </div>
                                  </div>

                                  {record && requiresNote(record.status) && (
                                    <div
                                      className={`flex items-center justify-between gap-3 text-xs p-3 rounded-xl border ${
                                        thieuGhiChu
                                          ? "bg-rose-50 border-rose-200"
                                          : "bg-amber-50/60 border-amber-100"
                                      }`}
                                    >
                                      <div className="space-y-0.5 min-w-0">
                                        <span
                                          className={`font-bold ${thieuGhiChu ? "text-rose-800" : "text-amber-800"}`}
                                        >
                                          Ghi chú ({ATTENDANCE_STATUSES[record.status].label}):
                                        </span>
                                        <p
                                          className={`truncate ${thieuGhiChu ? "text-rose-900" : "text-amber-900"}`}
                                        >
                                          {record.note ? (
                                            `"${record.note}"`
                                          ) : (
                                            <i>
                                              Bắt buộc, tối thiểu {MIN_ATTENDANCE_NOTE_LENGTH} ký tự
                                            </i>
                                          )}
                                        </p>
                                      </div>
                                      <button
                                        type="button"
                                        onClick={() => {
                                          setActiveNote({
                                            passenger,
                                            customerName: booking.customer_name,
                                            status: record.status,
                                          });
                                          setNoteInput(record.note);
                                        }}
                                        className="px-3 py-1 bg-white border border-gray-200 text-gray-800 rounded-lg font-bold hover:bg-gray-50 transition-colors shrink-0"
                                      >
                                        Sửa ghi chú
                                      </button>
                                    </div>
                                  )}
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

              {/* Ảnh check-in của điểm dừng đang chọn */}
              <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden p-6 space-y-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="font-bold text-gray-900 text-base font-jakarta">Ảnh check-in</h3>
                    <p className="text-xs text-gray-500 mt-0.5">
                      Ảnh gắn tọa độ tại {activeCheckpoint.name}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploading}
                    className="px-4 py-2 bg-primary-50 text-primary-700 hover:bg-primary-100 font-bold text-xs rounded-2xl transition-colors disabled:opacity-50 shrink-0"
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
                  <div className="border border-dashed border-gray-200 rounded-2xl p-8 text-center space-y-2 bg-gray-50/50">
                    <p className="text-xs text-gray-500">Chưa có ảnh nào tại điểm dừng này.</p>
                    <p className="text-[11px] text-gray-400">
                      Ảnh cần quyền định vị để đối chiếu với tọa độ điểm dừng.
                    </p>
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
                          Phóng to
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

      {/* Nhập lý do cho trạng thái khác "có mặt" */}
      {activeNote && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl animate-fade-in">
            <div>
              <h3 className="text-lg font-bold text-gray-900 font-jakarta">
                Nhập lý do ({ATTENDANCE_STATUSES[activeNote.status].label})
              </h3>
              <p className="text-xs text-gray-500 mt-0.5">
                Hành khách: <span className="font-bold text-gray-800">{activeNote.passenger.name}</span>{" "}
                · Đơn của {activeNote.customerName}
              </p>
            </div>

            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-gray-700">Gợi ý lý do phổ biến:</label>
              <div className="flex flex-wrap gap-1.5">
                {SUGGESTED_REASONS.map((reason) => (
                  <button
                    key={reason}
                    type="button"
                    onClick={() => setNoteInput(reason)}
                    className="px-2.5 py-1 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 text-[11px] font-medium text-left transition-colors"
                  >
                    {reason}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Ghi chú chi tiết:</label>
              <textarea
                rows={3}
                value={noteInput}
                onChange={(event) => setNoteInput(event.target.value)}
                placeholder="Ghi lại chuyện đã xảy ra, đủ để đọc lại vẫn hiểu..."
                className="w-full rounded-2xl border border-gray-200 p-3 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none"
              />
              <p
                className={`text-[11px] mt-1 font-semibold ${
                  noteInput.trim().length >= MIN_ATTENDANCE_NOTE_LENGTH
                    ? "text-emerald-700"
                    : "text-gray-500"
                }`}
              >
                {noteInput.trim().length}/{MIN_ATTENDANCE_NOTE_LENGTH} ký tự tối thiểu
              </p>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setActiveNote(null)}
                className="px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={handleSaveNote}
                disabled={noteInput.trim().length < MIN_ATTENDANCE_NOTE_LENGTH}
                className="px-5 py-2 rounded-xl bg-primary-600 text-xs font-bold text-white hover:bg-primary-700 shadow-sm disabled:opacity-50"
              >
                Xác nhận ghi chú
              </button>
            </div>
          </div>
        </div>
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
};

export default GuideAttendance;
