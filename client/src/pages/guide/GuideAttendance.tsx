import { attendanceAccess, attendanceDate, attendanceNow, type AttendanceClock } from "@/utils/attendanceAccess";
import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { DemoClockNotice } from "@/components/DemoClockNotice";
import { Alert, Button, Card, Empty, Flex, Form, Image, Input, Modal, Progress, Select, Skeleton, Tabs, Tag, Typography, theme } from "antd";
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
import { guideSearchText } from "@/utils/guideAssignments";
import { changedPassengerIds, recordKey, type AttendanceMap } from "@/utils/attendanceDraft";
import { AttendancePhotoError, uploadAttendancePhoto } from "@/utils/attendancePhoto";
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

export const GuideAttendance: React.FC = () => {
  const { scheduleId } = useParams<{ scheduleId: string }>();
  return <AttendanceForSchedule key={scheduleId} scheduleId={scheduleId} />;
};

function AttendanceForSchedule({ scheduleId }: { scheduleId?: string }) {
  const feedback = useGuideFeedback();
  const { token } = theme.useToken();

  const [data, setData] = useState<AttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [reload, setReload] = useState(0);
  const [clock, setClock] = useState<AttendanceClock | null>(null);
  const [serverNow, setServerNow] = useState(NaN);
  const [activeCheckpointId, setActiveCheckpointId] = useState<number | null>(null);
  const [records, setRecords] = useState<AttendanceMap>({});
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [selectedPhoto, setSelectedPhoto] = useState<{
    file: File;
    previewUrl: string;
    checkpoint: AttendanceCheckpoint;
  } | null>(null);
  const [lastUploadedPhotoId, setLastUploadedPhotoId] = useState<number | null>(null);
  const [query, setQuery] = useState("");
  const [passengerFilter, setPassengerFilter] = useState("all");

  const [activeNote, setActiveNote] = useState<{
    passenger: AttendancePassenger;
    customerName: string;
    status: PassengerCheckinStatus;
  } | null>(null);
  const [noteInput, setNoteInput] = useState("");

  const fileInputRef = useRef<HTMLInputElement>(null);
  const photoUploadInFlight = useRef(false);
  const photoSectionRef = useRef<HTMLDivElement>(null);
  const photoPreviewUrl = selectedPhoto?.previewUrl;
  useEffect(() => {
    return () => { if (photoPreviewUrl) URL.revokeObjectURL(photoPreviewUrl); };
  }, [photoPreviewUrl]);
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

    let cancelled = false;
    feedback.clearLoadError("Chưa tải được điểm danh");

    guideService
      .getAttendance(Number(scheduleId))
      .then((result) => {
        if (cancelled) return;
        if (!result) {
          setLoadFailed(true);
          feedback.loadError(null, "Chưa tải được điểm danh");
          return;
        }

        setLoadFailed(false);
        setData(result);
        const timestamp = Date.parse(result.schedule.server_now);
        setClock({ timestamp, receivedAt: performance.now() });
        setServerNow(timestamp);
        const sorted = [...result.checkpoints].sort((a, b) =>
          (a.tour_itinerary?.day_number ?? 0) - (b.tour_itinerary?.day_number ?? 0) || a.sequence - b.sequence);
        const first = sorted.find(point => point.attendance_date === attendanceDate(timestamp)) ?? sorted[0];
        setActiveCheckpointId(first?.id ?? null);

        const initial: AttendanceMap = {};
        result.checkins.forEach((checkin) => {
          initial[recordKey(checkin.itinerary_checkpoint_id, checkin.booking_passenger_id)] = {
            status: checkin.status,
            note: checkin.note ?? "",
          };
        });
        setRecords(initial);
      })
      .catch((err) => { if (!cancelled) { setLoadFailed(true); feedback.loadError(err, "Chưa tải được điểm danh"); } })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [scheduleId, reload, feedback]);

  useEffect(() => {
    if (!clock) return;
    const tick = () => setServerNow(attendanceNow(clock, performance.now()));
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [clock]);

  const loadedScheduleId = data?.schedule.id;
  useEffect(() => {
    if (!loadedScheduleId) return;
    let cancelled = false;
    let syncing = false;
    const syncAccess = async () => {
      if (syncing) return;
      syncing = true;
      try {
        const latest = await guideService.getAttendance(loadedScheduleId);
        if (cancelled) return;
        if (!latest) throw new Error("Missing attendance data");
        const timestamp = Date.parse(latest.schedule.server_now);
        setClock({ timestamp, receivedAt: performance.now() });
        setServerNow(timestamp);
        // Refresh permissions without replacing unsaved attendance choices.
        setData(previous => previous ? { ...previous, schedule: latest.schedule, checkpoints: latest.checkpoints, bookings: latest.bookings } : previous);
      } catch (err) {
        if (!cancelled) {
          setClock(null);
          setServerNow(NaN);
          feedback.error(err, "Chưa kiểm tra được quyền điểm danh. Tạm khóa chỉnh sửa; hệ thống sẽ thử lại.");
        }
      } finally {
        syncing = false;
      }
    };
    const resume = () => {
      if (document.visibilityState === "hidden") return;
      setClock(null);
      setServerNow(NaN);
      void syncAccess();
    };
    const timer = window.setInterval(() => { if (document.visibilityState !== "hidden") void syncAccess(); }, 30000);
    window.addEventListener("focus", resume);
    document.addEventListener("visibilitychange", resume);
    return () => {
      cancelled = true;
      window.clearInterval(timer);
      window.removeEventListener("focus", resume);
      document.removeEventListener("visibilitychange", resume);
    };
  }, [loadedScheduleId, feedback]);

  const activeCheckpoint = useMemo(
    () => orderedCheckpoints.find((item) => item.id === activeCheckpointId) ?? null,
    [orderedCheckpoints, activeCheckpointId],
  );

  const readOnlyMessage = attendanceAccess(data?.schedule, activeCheckpoint, clock ? serverNow : NaN);
  const readOnly = readOnlyMessage !== null;
  const canWriteAt = (elapsed: number) => attendanceAccess(data?.schedule, activeCheckpoint, attendanceNow(clock, elapsed)) === null;
  const savedRecords = useMemo(() => Object.fromEntries((data?.checkins ?? []).map(checkin => [
    recordKey(checkin.itinerary_checkpoint_id, checkin.booking_passenger_id),
    { status: checkin.status, note: checkin.note ?? "" },
  ])), [data?.checkins]);
  // A locked day displays persisted facts, never yesterday's unsaved draft.
  const visibleRecords = readOnly ? savedRecords : records;

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
      const record = visibleRecords[recordKey(activeCheckpointId, passenger.id)];
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
  }, [allPassengers, visibleRecords, activeCheckpointId]);

  const setStatus = (
    passenger: AttendancePassenger,
    customerName: string,
    status: PassengerCheckinStatus,
  ) => {
    if (readOnly || saving || activeCheckpointId === null) return;

    const key = recordKey(activeCheckpointId, passenger.id);
    const existing = records[key];

    if (requiresNote(status)) {
      setActiveNote({ passenger, customerName, status });
      setNoteInput(existing?.note ?? "");
    } else {
      setRecords(prev => ({ ...prev, [key]: { status, note: "" } }));
    }
  };

  const handleSaveNote = () => {
    if (!canWriteAt(performance.now()) || !activeNote || activeCheckpointId === null || noteInput.trim().length < MIN_ATTENDANCE_NOTE_LENGTH) return;

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
    if (!canWriteAt(performance.now()) || !data || !scheduleId || activeCheckpointId === null || saving) return;
    if (activeCheckpoint?.is_required_photo && activePhotos.length === 0 && stats.pending === 0) {
      feedback.warning("Thêm ảnh của đoàn trước khi lưu đủ khách tại điểm dừng này.");
      return;
    }

    const payload: AttendanceCheckinInput[] = [];
    const thieuGhiChu: string[] = [];
    const changedIds = new Set(changedPassengerIds(activeCheckpointId, allPassengers, records, savedRecords));

    allPassengers.forEach((passenger) => {
      if (!changedIds.has(passenger.id)) return;
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
      feedback.error(null, `Cần ghi chú ít nhất ${MIN_ATTENDANCE_NOTE_LENGTH} ký tự cho: ${thieuGhiChu.join(", ")}`);
      return;
    }

    if (payload.length === 0) {
      feedback.warning("Chưa chọn trạng thái cho hành khách nào.");
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
        const content = `Đã lưu điểm danh cho ${result.saved} hành khách.` +
          (boQua > 0 ? ` Bỏ qua ${boQua} người thuộc đơn chưa xác nhận.` : "");
        if (boQua > 0) feedback.warning(content);
        else feedback.success(content);
      } else {
        feedback.error(null, "Chưa nhận được kết quả lưu điểm danh. Vui lòng kiểm tra lại.");
      }
    } catch (err) {
      feedback.error(err, "Chưa lưu được điểm danh. Các lựa chọn của bạn vẫn được giữ lại.");
    } finally {
      setSaving(false);
    }
  };

  const handlePhotoSelection = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    // Reset immediately so the same file can be selected again after any failure.
    event.target.value = "";
    if (!file || !activeCheckpoint || photoUploadInFlight.current || saving) return;
    if (file.size > 5 * 1024 * 1024) { feedback.warning("Ảnh không được vượt quá 5MB. Hãy chọn ảnh nhỏ hơn."); return; }
    if (file.type && !file.type.startsWith("image/")) { feedback.warning("Vui lòng chọn tệp hình ảnh."); return; }
    // Keep the target together with the file, even if page data refreshes while previewing.
    setSelectedPhoto({ file, previewUrl: URL.createObjectURL(file), checkpoint: selectedPhoto?.checkpoint ?? activeCheckpoint });
  };

  const handlePhotoUpload = async () => {
    if (!selectedPhoto || !scheduleId || photoUploadInFlight.current || saving) return;
    const { file, checkpoint } = selectedPhoto;

    photoUploadInFlight.current = true;
    setUploading(true);
    try {
      const result = await uploadAttendancePhoto(Number(scheduleId), checkpoint.id, file, {
        getAttendance: guideService.getAttendance,
        upload: guideService.uploadCheckinPhoto,
        elapsed: () => performance.now(),
        onAccess: (latest, freshClock) => {
          setClock(freshClock);
          setServerNow(freshClock.timestamp);
          setData(previous => previous ? { ...previous, schedule: latest.schedule, checkpoints: latest.checkpoints, bookings: latest.bookings } : previous);
        },
      });

      if (result?.photo) {
        const photo = result.photo;
        setData((prev) => (prev ? { ...prev, photos: [photo, ...prev.photos] } : prev));
        setLastUploadedPhotoId(photo.id);
        setSelectedPhoto(null);
        photoSectionRef.current?.scrollIntoView({ behavior: "smooth", block: "center" });
        feedback.success(`Đã thêm ảnh tại ${checkpoint.name}.`);
      } else {
        feedback.error(null, "Chưa nhận được kết quả tải ảnh. Vui lòng kiểm tra lại.");
      }
    } catch (err) {
      if (err instanceof AttendancePhotoError) feedback.error(null, err.message);
      else feedback.error(err, "Chưa tải được ảnh check-in. Vui lòng thử lại.");
    } finally {
      photoUploadInFlight.current = false;
      setUploading(false);
    }
  };

  const dirtyCheckpointIds = orderedCheckpoints.filter(point =>
    attendanceAccess(data?.schedule, point, clock ? serverNow : NaN) === null &&
    changedPassengerIds(point.id, allPassengers, records, savedRecords).length > 0).map(point => point.id);
  const dirtyCount = readOnly || activeCheckpointId === null ? 0 : changedPassengerIds(activeCheckpointId, allPassengers, records, savedRecords).length;
  const hasUnsavedChanges = dirtyCheckpointIds.length > 0;
  useEffect(() => {
    if (!hasUnsavedChanges) return;
    const warn = (event: BeforeUnloadEvent) => { event.preventDefault(); event.returnValue = ""; };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [hasUnsavedChanges]);

  const activeDay = activeCheckpoint?.tour_itinerary?.day_number ?? 0;
  const dayPoints = groupedByDay.find(([day]) => day === activeDay)?.[1] ?? [];
  const pointIndex = dayPoints.findIndex(point => point.id === activeCheckpointId);
  const today = attendanceDate(serverNow);
  const selectPoint = (id: number) => {
    setActiveCheckpointId(id);
    setPassengerFilter("all");
    setQuery("");
  };
  const search = guideSearchText(query);
  const filteredBookings = (data?.bookings ?? []).map(booking => ({
    ...booking,
    passengers: (booking.passengers ?? []).filter(passenger => {
      const record = activeCheckpointId === null ? undefined : visibleRecords[recordKey(activeCheckpointId, passenger.id)];
      const matchesStatus = passengerFilter === "all" || (passengerFilter === "pending" ? !record : record?.status === passengerFilter);
      return matchesStatus && guideSearchText(`${passenger.name} ${booking.customer_name} BK${booking.id} BK-${booking.id} ${booking.customer_phone ?? ""}`).includes(search);
    }),
  })).filter(booking => booking.passengers.length > 0);
  const missingLists = (data?.bookings ?? []).filter(booking => (booking.passengers?.length ?? 0) < booking.guests);
  const needsPhotoToSave = !readOnly && activeCheckpoint?.is_required_photo && activePhotos.length === 0 && stats.total > 0 && stats.pending === 0;

  if (loading) return <Skeleton active paragraph={{ rows: 10 }} />;
  if (loadFailed || !data) return <Empty description="Chưa có dữ liệu điểm danh"><Flex justify="center" gap="small"><Button onClick={() => { setLoading(true); setReload(value => value + 1); }}>Tải lại</Button><Link to="/guide/tours"><Button>Về danh sách tour</Button></Link></Flex></Empty>;

  return (
    <Flex vertical gap="middle">
      <div>
        <Link to="/guide/tours">← Tour của tôi</Link>
        <Typography.Title level={3} style={{ margin: "8px 0 4px" }}>Điểm danh</Typography.Title>
        <Typography.Text strong>{data.tour.title}</Typography.Text><br />
        <Typography.Text type="secondary">Chuyến #{data.schedule.id} · Khởi hành {formatDateTime(data.schedule.start_date)}</Typography.Text>
      </div>
      <DemoClockNotice clock={data.schedule.demo_clock} />
      {groupedByDay.length === 0 ? <Empty description="Chưa có điểm dừng. Liên hệ điều hành để bổ sung." /> : <>
        <Card styles={{ body: { paddingTop: 0 } }}>
          <Tabs activeKey={String(activeDay)} onChange={key => { const point = groupedByDay.find(([day]) => day === Number(key))?.[1][0]; if (point) selectPoint(point.id); }}
            items={groupedByDay.map(([day, points]) => ({
              key: String(day), disabled: saving || uploading,
              label: <Flex vertical gap={2}>
                <Typography.Text strong>Ngày {day}{points[0]?.attendance_date === today ? " · Hôm nay" : ""}{points.some(point => dirtyCheckpointIds.includes(point.id)) ? " •" : ""}</Typography.Text>
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>{points[0]?.attendance_date?.split("-").reverse().join("/")}</Typography.Text>
              </Flex>,
            }))} />
          <Flex vertical gap="small">
            <Flex justify="space-between" align="center" gap="small" wrap>
              <Typography.Text strong>Điểm dừng {pointIndex + 1}/{dayPoints.length}</Typography.Text>
              <Flex gap="small">
                <Button disabled={saving || uploading || pointIndex <= 0} onClick={() => selectPoint(dayPoints[pointIndex - 1].id)}>Điểm trước</Button>
                <Button disabled={saving || uploading || pointIndex >= dayPoints.length - 1} onClick={() => selectPoint(dayPoints[pointIndex + 1].id)}>Điểm tiếp</Button>
              </Flex>
            </Flex>
            <Select aria-label="Chọn điểm dừng" value={activeCheckpointId} disabled={saving || uploading} onChange={selectPoint}
              style={{ width: "100%" }} options={dayPoints.map((point, index) => ({ value: point.id, label: `${index + 1}. ${point.name}${dirtyCheckpointIds.includes(point.id) ? " · Chưa lưu" : ""}` }))} />
          </Flex>
        </Card>
        {activeCheckpoint && <>
          {readOnly && <Alert showIcon type="info" title={readOnlyMessage} />}
          <Card>
            <Flex vertical gap="middle">
              <Flex justify="space-between" align="start" gap="small" wrap>
                <div style={{ flex: "1 1 220px", minWidth: 0 }}>
                  <Typography.Title level={4} style={{ margin: 0, overflowWrap: "anywhere" }}>{activeCheckpoint.name}</Typography.Title>
                  {activeCheckpoint.description && <Typography.Text type="secondary">{activeCheckpoint.description}</Typography.Text>}
                </div>
                <Tag>{readOnly ? "Chỉ xem" : "Điểm danh hôm nay"}</Tag>
              </Flex>
              <Flex gap="small" justify="space-between" wrap>
                <Typography.Text>{stats.recorded}/{stats.total} khách đã ghi{dirtyCount > 0 ? ` · ${dirtyCount} chưa lưu` : ""}</Typography.Text>
                <Typography.Text type={stats.pending > 0 ? "warning" : "secondary"}>{stats.pending > 0 ? `Còn ${stats.pending} khách` : stats.total > 0 ? "Đã ghi đủ khách" : "Chưa có hành khách"}</Typography.Text>
              </Flex>
              <Progress percent={stats.percent} showInfo={false} status="normal" style={{ margin: 0 }} />
              <div ref={photoSectionRef} style={{ scrollMarginTop: 88 }}>
                <Flex vertical gap="middle">
                  <Flex justify="space-between" gap="small" wrap align="center">
                    <Flex vertical gap={4} style={{ flex: "1 1 200px", minWidth: 0 }}>
                      <Typography.Text strong>Ảnh tại {activeCheckpoint.name} ({activePhotos.length})</Typography.Text>
                      {activeCheckpoint.is_required_photo && <div><Tag color={activePhotos.length ? "success" : "warning"}>{activePhotos.length ? "Đã có ảnh" : "Cần bổ sung ảnh"}</Tag></div>}
                    </Flex>
                    <Button loading={uploading} disabled={readOnly || saving} onClick={() => fileInputRef.current?.click()}>Thêm ảnh</Button>
                  </Flex>
                  {activePhotos.length === 0 ? <Typography.Text type="secondary">Chưa có ảnh tại điểm này.</Typography.Text> : <Image.PreviewGroup>
                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(130px, 1fr))", gap: 12 }}>
                      {activePhotos.map((photo, index) => <Flex vertical gap="small" key={photo.id} style={{ minWidth: 0 }}>
                        <Image src={photo.image_path} alt={`Ảnh ${index + 1} tại ${activeCheckpoint.name}`} width="100%" height={140} style={{ objectFit: "cover", borderRadius: 8 }} />
                        <Flex gap={4} vertical>
                          <Typography.Text type="secondary" style={{ fontSize: 12 }}>{photo.captured_at || photo.created_at ? formatDateTime(photo.captured_at ?? photo.created_at) : "Đã tải lên"}</Typography.Text>
                          {photo.id === lastUploadedPhotoId && <div><Tag color="success">Vừa tải lên</Tag></div>}
                        </Flex>
                      </Flex>)}
                    </div>
                  </Image.PreviewGroup>}
                </Flex>
              </div>
              <input ref={fileInputRef} type="file" accept="image/*" hidden onChange={handlePhotoSelection} />
            </Flex>
          </Card>
          <Card>
            <Flex vertical gap="middle">
              <Typography.Title level={4} style={{ margin: 0 }}>Hành khách</Typography.Title>
              <Flex gap="small" wrap>
                <Input.Search aria-label="Tìm hành khách" placeholder="Tên khách, mã đơn hoặc số điện thoại" value={query} allowClear onChange={event => setQuery(event.target.value)} style={{ flex: "2 1 240px" }} />
                <Select aria-label="Lọc trạng thái điểm danh" value={passengerFilter} onChange={setPassengerFilter} style={{ flex: "1 1 190px" }} options={[
                  { value: "all", label: `Tất cả (${stats.total})` }, { value: "pending", label: `Chưa điểm danh (${stats.pending})` },
                  ...ATTENDANCE_STATUS_ORDER.map(status => ({ value: status, label: `${ATTENDANCE_STATUSES[status].label} (${stats[status]})` })),
                ]} />
              </Flex>
              {missingLists.length > 0 && <Alert showIcon type="warning" title={`Chưa khai đủ: ${missingLists.map(booking => `BK${booking.id} (thiếu ${booking.guests - (booking.passengers?.length ?? 0)} người)`).join(", ")}`} description="Xác nhận khách thuộc đúng đơn và báo điều hành bổ sung. Danh sách tự cập nhật khi bạn quay lại màn hình." />}
              {filteredBookings.length === 0 ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={allPassengers.length === 0 ? "Chưa có danh sách hành khách" : "Không có hành khách phù hợp"}>
                {(query || passengerFilter !== "all") && <Button onClick={() => { setQuery(""); setPassengerFilter("all"); }}>Xem tất cả khách</Button>}
              </Empty> : filteredBookings.map(booking => <Flex key={booking.id} vertical>
                <Flex gap="small" wrap align="center" style={{ padding: "10px 12px", background: token.colorFillAlter, borderRadius: token.borderRadius }}>
                  <Typography.Text strong>BK{booking.id} · {booking.customer_name}</Typography.Text>
                  {booking.customer_phone && <Typography.Link href={`tel:${booking.customer_phone}`}>{booking.customer_phone}</Typography.Link>}
                </Flex>
                {booking.passengers.map(passenger => {
                  const record = visibleRecords[recordKey(activeCheckpoint.id, passenger.id)];
                  const saved = savedRecords[recordKey(activeCheckpoint.id, passenger.id)];
                  const changed = !readOnly && record && (record.status !== saved?.status || record.note !== saved?.note);
                  return <Flex key={passenger.id} vertical gap="small" style={{ padding: "16px 0", borderBottom: `1px solid ${token.colorBorderSecondary}` }}>
                    <Flex justify="space-between" align="center" gap="middle" wrap>
                      <Flex vertical gap={4} style={{ flex: "1 1 180px", minWidth: 0 }}>
                        <Typography.Text strong style={{ overflowWrap: "anywhere" }}>{passenger.name}</Typography.Text>
                        <Flex align="center" gap="small" wrap>
                          <Typography.Text type="secondary" style={{ fontSize: 12 }}>{passenger.type === "adult" ? "Người lớn" : passenger.type === "child" ? "Trẻ em" : "Em bé"}{!record ? " · Chưa điểm danh" : changed ? " · Chưa lưu" : " · Đã lưu"}</Typography.Text>
                          {changed && <Button type="link" size="small" disabled={saving} aria-label={`Hoàn tác thay đổi của ${passenger.name}`} onClick={() => {
                            setRecords(previous => {
                              const next = { ...previous };
                              const key = recordKey(activeCheckpoint.id, passenger.id);
                              if (saved) next[key] = saved;
                              else delete next[key];
                              return next;
                            });
                          }}>Hoàn tác</Button>}
                        </Flex>
                        {passenger.note && <Typography.Text type="secondary">{passenger.note}</Typography.Text>}
                      </Flex>
                      {readOnly ? <Tag color={record?.status === "present" ? "success" : record ? "warning" : "default"}>{record ? ATTENDANCE_STATUSES[record.status].label : "Chưa điểm danh"}</Tag> : <Flex gap="small" wrap style={{ flex: "1 1 270px", justifyContent: "flex-end" }}>
                        <Button type={record?.status === "present" ? "primary" : "default"} aria-pressed={record?.status === "present"} aria-label={`Có mặt: ${passenger.name}`} disabled={saving} onClick={() => setStatus(passenger, booking.customer_name, "present")} style={{ flex: "1 1 90px" }}>Có mặt</Button>
                        <Select aria-label={`Trạng thái khác của ${passenger.name}`} placeholder="Vắng / khác" value={record && record.status !== "present" ? record.status : undefined} disabled={saving}
                          onChange={status => setStatus(passenger, booking.customer_name, status)} style={{ flex: "1 1 160px", minWidth: 0 }}
                          options={ATTENDANCE_STATUS_ORDER.filter(status => status !== "present").map(status => ({ value: status, label: ATTENDANCE_STATUSES[status].label }))} />
                      </Flex>}
                    </Flex>
                    {record && requiresNote(record.status) && <Flex gap="small" align="center" wrap>
                      <Typography.Text type={noteIsValid(record.status, record.note) ? "secondary" : "danger"}>{record.note || "Chưa có lý do"}</Typography.Text>
                      {!readOnly && <Button type="link" size="small" disabled={saving} onClick={() => { setActiveNote({ passenger, customerName: booking.customer_name, status: record.status }); setNoteInput(record.note); }}>Sửa lý do</Button>}
                    </Flex>}
                  </Flex>;
                })}
              </Flex>)}
            </Flex>
          </Card>
          <Card size="small" style={{ position: "sticky", bottom: 12, zIndex: 10, boxShadow: token.boxShadowSecondary }}>
            <Flex justify="space-between" gap="small" align="center" wrap>
              <Flex vertical style={{ flex: "1 1 200px", minWidth: 0 }} aria-live="polite">
                <Typography.Text strong>{readOnly ? "Chỉ xem điểm danh" : dirtyCount > 0 ? `${dirtyCount} khách có thay đổi chưa lưu` : "Không có thay đổi chưa lưu"}</Typography.Text>
                <Typography.Text type="secondary" ellipsis>{activeCheckpoint.name}</Typography.Text>
                {dirtyCheckpointIds.some(id => id !== activeCheckpointId) && <Button type="link" style={{ padding: 0, height: "auto", justifyContent: "start", whiteSpace: "normal", textAlign: "left" }} disabled={saving || uploading}
                  onClick={() => selectPoint(dirtyCheckpointIds.find(id => id !== activeCheckpointId)!)}>Xem điểm khác còn thay đổi chưa lưu</Button>}
              </Flex>
              {needsPhotoToSave ? <Button type="primary" loading={uploading} disabled={saving} onClick={() => fileInputRef.current?.click()} style={{ flex: "0 1 200px" }}>Thêm ảnh để lưu</Button>
                : <Button type="primary" loading={saving} disabled={readOnly || uploading || dirtyCount === 0} onClick={handleSave} style={{ flex: "0 1 200px" }}>Lưu điểm danh{dirtyCount > 0 ? ` (${dirtyCount})` : ""}</Button>}
            </Flex>
          </Card>
        </>}
      </>}
      <Modal open={selectedPhoto !== null} title="Xem ảnh trước khi tải" closable={!uploading} mask={{ closable: false }}
        onCancel={() => { if (!photoUploadInFlight.current) setSelectedPhoto(null); }}
        footer={<Flex gap="small" justify="end" wrap>
          <Button disabled={uploading} onClick={() => setSelectedPhoto(null)}>Hủy</Button>
          <Button disabled={uploading} onClick={() => fileInputRef.current?.click()}>Chọn ảnh khác</Button>
          <Button type="primary" loading={uploading} disabled={!selectedPhoto || saving} onClick={() => void handlePhotoUpload()}>Tải ảnh lên</Button>
        </Flex>}>
        {selectedPhoto && <Flex vertical gap="middle">
          <Flex vertical gap={4}>
            <Typography.Text strong>{selectedPhoto.checkpoint.name}</Typography.Text>
            <Typography.Text type="secondary">Ngày {selectedPhoto.checkpoint.tour_itinerary?.day_number ?? "—"} · {selectedPhoto.checkpoint.attendance_date?.split("-").reverse().join("/")} · Chuyến #{data.schedule.id}</Typography.Text>
          </Flex>
          <Image key={selectedPhoto.previewUrl} src={selectedPhoto.previewUrl} alt={`Ảnh sẽ tải tại ${selectedPhoto.checkpoint.name}`} width="100%" style={{ maxHeight: "45vh", objectFit: "contain", borderRadius: 8 }} />
          <Flex vertical gap={4}>
            <Typography.Text style={{ overflowWrap: "anywhere" }}>{selectedPhoto.file.name}</Typography.Text>
            <Typography.Text type="secondary">{(selectedPhoto.file.size / (1024 * 1024)).toFixed(2)} MB · {uploading ? "Đang tải ảnh…" : "Chưa tải lên"}</Typography.Text>
          </Flex>
        </Flex>}
      </Modal>
      <Modal open={activeNote !== null} title={activeNote ? "Nhập lý do: " + ATTENDANCE_STATUSES[activeNote.status].label : "Nhập lý do"} onCancel={() => setActiveNote(null)} onOk={handleSaveNote} okText="Áp dụng" cancelText="Hủy" okButtonProps={{ disabled: readOnly || noteInput.trim().length < MIN_ATTENDANCE_NOTE_LENGTH }} mask={{ closable: false }}>
        {readOnly && <Alert type="info" showIcon title="Chỉ xem" description={readOnlyMessage} />}
        {activeNote && <Typography.Paragraph>Hành khách: <strong>{activeNote.passenger.name}</strong> · Đơn của {activeNote.customerName}</Typography.Paragraph>}
        <Form layout="vertical" disabled={readOnly}><Form.Item htmlFor="GuideAttendance-field-1" label="Gợi ý lý do"><Select id="GuideAttendance-field-1" placeholder="Chọn lý do để điền nhanh" value={null} options={SUGGESTED_REASONS.map(reason => ({ value: reason, label: reason }))} onChange={setNoteInput} /></Form.Item>
          <Form.Item htmlFor="GuideAttendance-field-2" label="Lý do" required extra="Tối thiểu 10 ký tự."><Input.TextArea id="GuideAttendance-field-2" rows={4} maxLength={2000} showCount value={noteInput} onChange={event => setNoteInput(event.target.value)} placeholder="Nhập lý do của hành khách" /></Form.Item>
        </Form>
      </Modal>
    </Flex>
  );
};

export default GuideAttendance;
