import {
  Button as AntButton,
  Card as UICard,
  Checkbox as AntCheckbox,
  Flex as UIFlex,
  Input as AntInput,
  Modal as AntModal,
  Typography as AntTypography,
} from "antd";
import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft, CalendarDays, Clock, UserRound, Users } from "lucide-react";
import adminService from "@/services/adminService";
import type { Guide, Tour, TourSchedule } from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime, getEndDate } from "@/utils/format";
import { statusLabel, statusClasses, tourStatusLabel } from "@/utils/schedule";

export default function AdminTourDetail() {
  const { id } = useParams<{ id: string }>();
  const [tour, setTour] = useState<Tour | null>(null);
  const [guides, setGuides] = useState<Guide[]>([]);
  const [loading, setLoading] = useState(true);
  const [assigningScheduleId, setAssigningScheduleId] = useState<number | null>(
    null,
  );
  // Mỗi chuyến giữ danh sách hướng dẫn viên đang chọn nhưng chưa lưu.
  const [pendingGuideIds, setPendingGuideIds] = useState<
    Record<number, number[]>
  >({});
  const [toast, setToast] = useState({
    message: "",
    type: "success" as "success" | "error" | "info",
    isOpen: false,
  });
  const [cancellingScheduleId, setCancellingScheduleId] = useState<
    number | null
  >(null);
  const [cancelReasonInput, setCancelReasonInput] = useState("");
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);

  useEffect(() => {
    const load = async () => {
      if (!id) return;

      try {
        const [tourData, guideData] = await Promise.all([
          adminService.getTourById(Number(id)),
          adminService.getGuides(),
        ]);
        setTour(tourData);
        setGuides(
          guideData?.data.filter((guide) => guide.status === "active") ?? [],
        );
      } catch {
        setToast({
          message: "Không thể tải thông tin tour.",
          type: "error",
          isOpen: true,
        });
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [id]);

  const assignGuides = async (schedule: TourSchedule, guideIds: number[]) => {
    setAssigningScheduleId(schedule.id);

    try {
      await adminService.assignGuidesToSchedule(schedule.id, guideIds);
      const daChon = guides.filter((item) => guideIds.includes(item.id));

      setPendingGuideIds((current) => ({
        ...current,
        [schedule.id]: guideIds,
      }));
      setTour((current) =>
        current
          ? {
              ...current,
              schedules: current.schedules?.map((item) =>
                item.id === schedule.id ? { ...item, guides: daChon } : item,
              ),
            }
          : current,
      );
      setToast({
        message:
          guideIds.length === 0
            ? "Đã bỏ phân công hướng dẫn viên."
            : `Đã phân công ${guideIds.length} hướng dẫn viên.`,
        type: "success",
        isOpen: true,
      });
    } catch (error: unknown) {
      const message =
        (
          error as {
            response?: { data?: { message?: string } };
          }
        ).response?.data?.message ?? "Không thể phân công hướng dẫn viên.";
      setToast({ message, type: "error", isOpen: true });
    } finally {
      setAssigningScheduleId(null);
    }
  };

  const handleUpdateStatus = async (
    scheduleId: number,
    nextStatus: "open" | "closed" | "confirmed" | "cancelled",
    reason?: string,
  ) => {
    try {
      const updatedSchedule = await adminService.updateScheduleStatus(
        scheduleId,
        nextStatus,
        reason,
      );

      if (!updatedSchedule) {
        throw new Error("Missing updated schedule response");
      }

      setTour((current) =>
        current
          ? {
              ...current,
              schedules: current.schedules?.map((item) =>
                item.id === scheduleId ? { ...item, ...updatedSchedule } : item,
              ),
            }
          : current,
      );

      setToast({
        message: `Đã cập nhật trạng thái chuyến đi thành "${statusLabel[updatedSchedule.status]}".`,
        type: "success",
        isOpen: true,
      });
    } catch (error: unknown) {
      const message =
        (error as { response?: { data?: { message?: string } } }).response?.data
          ?.message ?? "Không thể cập nhật trạng thái chuyến đi.";
      setToast({ message, type: "error", isOpen: true });
    }
  };

  const openCancelDialog = (scheduleId: number) => {
    setCancellingScheduleId(scheduleId);
    setCancelReasonInput("");
    setIsCancelModalOpen(true);
  };

  const confirmCancelSchedule = () => {
    if (!cancellingScheduleId) return;
    handleUpdateStatus(
      cancellingScheduleId,
      "cancelled",
      cancelReasonInput || "Lý do bất khả kháng",
    );
    setIsCancelModalOpen(false);
    setCancellingScheduleId(null);
  };

  if (loading) {
    return (
      <div className="animate-pulse space-y-5">
        <div className="h-8 w-64 rounded bg-gray-200" />
        <div className="h-72 rounded-lg bg-gray-200" />
        <div className="h-80 rounded-lg bg-gray-200" />
      </div>
    );
  }

  if (!tour) {
    return (
      <UICard  ><UIFlex vertical gap="middle"><p className="font-semibold text-gray-900">Không tìm thấy tour.</p><Link
          to="/admin/tours"
          className="mt-4 inline-flex text-sm font-semibold text-primary-600"
        >
          Quay lại danh sách
        </Link></UIFlex></UICard>
    );
  }

  return (
    <UIFlex vertical gap="large" ><UIFlex   wrap align="center" justify="space-between" gap={16}><div>
          <Link
            to="/admin/tours"
            className="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-primary-600"
          >
            <ArrowLeft className="h-4 w-4" />
            Danh sách tour
          </Link>
          <AntTypography.Title level={3} >{tour.title}</AntTypography.Title>
        </div><span
          className={
            "rounded px-3 py-1.5 text-xs font-semibold " +
            (tour.status === "active"
              ? "bg-emerald-50 text-emerald-700"
              : tour.status === "full"
                ? "bg-red-50 text-red-700"
                : "bg-gray-100 text-gray-600")
          }
        >
          {tourStatusLabel[tour.status]}
        </span></UIFlex><section className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div className="grid lg:grid-cols-[320px_minmax(0,1fr)]">
          <div className="aspect-[4/3] bg-gray-100 lg:aspect-auto">
            {tour.thumbnail ? (
              <img
                src={tour.thumbnail}
                alt={tour.title}
                className="h-full w-full object-cover"
              />
            ) : (
              <div className="flex h-full min-h-56 items-center justify-center text-sm text-gray-400">
                Chưa có ảnh đại diện
              </div>
            )}
          </div>
          <div className="p-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">
                  Giá tour
                </p>
                <p className="mt-1 font-bold text-gray-950">
                  {Number(tour.adult_price).toLocaleString("vi-VN")} đ
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">
                  Thời lượng
                </p>
                <p className="mt-1 font-semibold text-gray-900">
                  {tour.number_of_days} ngày {tour.number_of_nights} đêm
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">
                  Khởi hành
                </p>
                <p className="mt-1 font-semibold text-gray-900">
                  {tour.start_location}
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">
                  Kết thúc
                </p>
                <p className="mt-1 font-semibold text-gray-900">
                  {tour.end_location || "Chưa cập nhật"}
                </p>
              </div>
            </div>

            {tour.description && (
              <p className="mt-6 whitespace-pre-line text-sm leading-6 text-gray-600">
                {tour.description}
              </p>
            )}

            <div className="mt-5 flex flex-wrap gap-2">
              {tour.categories?.map((category) => (
                <span
                  key={category.id}
                  className="rounded bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700"
                >
                  {category.name}
                </span>
              ))}
              {tour.services?.map((service) => (
                <span
                  key={service.id}
                  className="rounded bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                >
                  {service.name}
                </span>
              ))}
            </div>
          </div>
        </div>
      </section><UICard  ><UIFlex vertical gap="middle"><div className="border-b border-gray-200 px-6 py-4">
          <h2 className="font-bold text-gray-950">Các chuyến đi</h2>
          <p className="mt-1 text-sm text-gray-500">
            Phân công hướng dẫn viên riêng cho từng lịch khởi hành.
          </p>
        </div>{tour.schedules?.length ? (
          <div className="divide-y divide-gray-100">
            {tour.schedules.map((schedule) => {
              const status = schedule.status || "open";
              const deadline = schedule.booking_deadline;
              const minPeople = schedule.min_people || 5;
              const isOverdue = deadline
                ? new Date(deadline) < new Date()
                : false;

              return (
                <div
                  key={schedule.id}
                  className="p-6 hover:bg-gray-50/50 transition-colors"
                >
                  <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    {/* Main Info */}
                    <div className="space-y-3 flex-1">
                      <UIFlex   wrap align="center"  gap={12}><span className="text-xs font-bold text-primary-700 font-mono">
                          CHUYẾN #{schedule.id}
                        </span><span
                          className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                            statusClasses[status] || statusClasses.open
                          }`}
                        >
                          {statusLabel[status]}
                        </span>{status === "cancelled" &&
                          schedule.cancelled_reason && (
                            <span className="text-xs text-rose-600 bg-rose-50 px-2 py-1 rounded-lg border border-rose-100">
                              Lý do hủy: {schedule.cancelled_reason}
                            </span>
                          )}</UIFlex>

                      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {/* Time */}
                        <UIFlex    align="center"  gap={10}><CalendarDays className="h-4.5 w-4.5 text-gray-400 shrink-0" /><div className="text-xs">
                            <p className="text-gray-400">Thời gian khởi hành</p>
                            <p className="font-semibold text-gray-900 mt-0.5">
                              {formatDateTime(schedule.start_date)} -{" "}
                              {getEndDate(
                                schedule.start_date,
                                tour.number_of_days,
                              )}
                            </p>
                          </div></UIFlex>

                        {/* Booking deadline */}
                        <UIFlex    align="center"  gap={10}><Clock
                            className={`h-4.5 w-4.5 shrink-0 ${isOverdue && status === "open" ? "text-amber-500 animate-pulse" : "text-gray-400"}`}
                          /><div className="text-xs">
                            <p className="text-gray-400">
                              Hạn đặt (Booking Deadline)
                            </p>
                            <p className="font-semibold text-gray-900 mt-0.5">
                              {deadline
                                ? formatDateTime(deadline)
                                : "Không giới hạn"}
                              {isOverdue && status === "open" && (
                                <span className="ml-1.5 text-[9px] bg-amber-50 text-amber-700 px-1 py-0.5 rounded font-bold uppercase tracking-wide">
                                  Quá hạn
                                </span>
                              )}
                            </p>
                          </div></UIFlex>

                        {/* Guest capacity */}
                        <UIFlex    align="center"  gap={10}><Users className="h-4.5 w-4.5 text-gray-400 shrink-0" /><div className="text-xs">
                            <p className="text-gray-400">Tình trạng chỗ</p>
                            <p className="font-semibold text-gray-900 mt-0.5">
                              {schedule.booked_people} / {schedule.max_people}{" "}
                              khách{" "}
                              <span className="text-gray-400 font-normal">
                                (Tối thiểu: {minPeople})
                              </span>
                            </p>
                          </div></UIFlex>
                      </div>

                      {/*
                        Phân công hướng dẫn viên — chọn được nhiều người.

                        Đoàn đông thì một người không kham nổi. Bao nhiêu người là đủ do điều hành
                        quyết, hệ thống không tính hộ theo số khách. Luật duy nhất còn lại là một
                        người không đứng ở hai đoàn cùng lúc, và máy chủ kiểm việc đó.
                      */}
                      {(() => {
                        const daLuu = (schedule.guides ?? []).map(
                          (guide) => guide.id,
                        );
                        const dangChon = pendingGuideIds[schedule.id] ?? daLuu;
                        const khoa =
                          assigningScheduleId === schedule.id ||
                          status === "cancelled" ||
                          status === "completed";

                        const coThayDoi =
                          dangChon.length !== daLuu.length ||
                          dangChon.some((id) => !daLuu.includes(id));

                        return (
                          <div className="pt-2 space-y-2 max-w-lg">
                            <div className="text-xs font-semibold text-gray-500 flex items-center gap-1">
                              <UserRound className="h-3.5 w-3.5" /> Hướng dẫn
                              viên
                              {dangChon.length > 0 && (
                                <span className="text-primary-600">
                                  ({dangChon.length})
                                </span>
                              )}
                            </div>

                            <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                              {guides.length === 0 && (
                                <span className="text-xs text-gray-400">
                                  Chưa có hướng dẫn viên nào.
                                </span>
                              )}

                              {guides.map((guide) => (
                                <label
                                  key={guide.id}
                                  className="flex cursor-pointer items-center gap-1.5 text-xs text-gray-800"
                                >
                                  <AntCheckbox disabled={khoa} checked={dangChon.includes(guide.id)} onChange={() =>
                                      setPendingGuideIds((current) => ({
                                        ...current,
                                        [schedule.id]: dangChon.includes(
                                          guide.id,
                                        )
                                          ? dangChon.filter(
                                              (id) => id !== guide.id,
                                            )
                                          : [...dangChon, guide.id],
                                      }))
                                    } />
                                  {guide.name}
                                </label>
                              ))}
                            </div>

                            <AntButton htmlType="button" disabled={khoa || !coThayDoi} onClick={() => assignGuides(schedule, dangChon)} type="primary">{assigningScheduleId === schedule.id
                                ? "Đang lưu..."
                                : "Lưu HDV"}</AntButton>
                          </div>
                        );
                      })()}
                    </div>

                    {/* State controller menu */}
                    <div className="flex flex-col gap-2 shrink-0 w-full sm:w-auto lg:items-end">
                      <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        Vận hành chuyến
                      </p>

                      <UIFlex   wrap   gap={6}>{/* Open/Close toggle */}{status === "open" && (
                          <AntButton htmlType="button" onClick={() =>
                              handleUpdateStatus(schedule.id, "closed")
                            }>Đóng bán
                          </AntButton>
                        )}{status === "closed" && (
                          <AntButton htmlType="button" onClick={() =>
                              handleUpdateStatus(schedule.id, "open")
                            }>Mở bán lại
                          </AntButton>
                        )}{/* Confirm action */}{(status === "open" || status === "closed") && (
                          <AntButton htmlType="button" onClick={() =>
                              handleUpdateStatus(schedule.id, "confirmed")
                            } type="primary">Chốt chuyến
                          </AntButton>
                        )}{/* Cancel action */}{(status === "open" ||
                          status === "closed" ||
                          status === "confirmed") && (
                          <AntButton htmlType="button" onClick={() => openCancelDialog(schedule.id)} danger>Hủy chuyến
                          </AntButton>
                        )}{/* Closed states */}{(status === "completed" || status === "cancelled") && (
                          <span className="text-xs text-gray-400 italic">
                            Chuyến đi đã hoàn thành
                          </span>
                        )}</UIFlex>

                      <Link
                        to={`/admin/tour-schedules/${schedule.id}/attendance`}
                        className="mt-1 text-xs font-semibold text-primary-600 hover:underline flex items-center gap-1 lg:self-end"
                      >
                        Xem điểm danh & ảnh check-in →
                      </Link>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="p-10 text-center text-sm text-gray-500">
            Tour chưa có lịch khởi hành.
          </div>
        )}</UIFlex></UICard><UICard  ><UIFlex vertical gap="middle"><div className="border-b border-gray-200 px-6 py-4">
          <h2 className="font-bold text-gray-950">Lịch trình theo ngày</h2>
        </div>{tour.itineraries?.length ? (
          <div className="divide-y divide-gray-100">
            {tour.itineraries
              .slice()
              .sort((a, b) => a.day_number - b.day_number)
              .map((item) => (
                <div
                  key={item.id}
                  className="grid gap-3 px-6 py-5 sm:grid-cols-[90px_1fr]"
                >
                  <div className="font-bold text-primary-700">
                    Ngày {item.day_number}
                  </div>
                  <div>
                    <h3 className="font-semibold text-gray-950">
                      {item.title}
                    </h3>
                    <p className="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">
                      {item.content}
                    </p>
                  </div>
                </div>
              ))}
          </div>
        ) : (
          <div className="p-10 text-center text-sm text-gray-500">
            Chưa có lịch trình chi tiết.
          </div>
        )}</UIFlex></UICard>{/* Modal Hủy Chuyến */}{isCancelModalOpen && (
        <AntModal open title={<>
              Xác nhận hủy chuyến đi
            </>} width={720} onCancel={() => setIsCancelModalOpen(false)} closable={true} keyboard={true} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div className="p-3.5 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 mb-4">
              <svg
                className="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>

            <p className="text-xs text-gray-500 mb-4">
              Vui lòng cung cấp lý do chi tiết hủy chuyến đi này. Chỗ ngồi sẽ
              được trả lại và không thể phục hồi.
            </p>
            <AntInput.TextArea value={cancelReasonInput} onChange={(e) => setCancelReasonInput(e.target.value)} placeholder="Nhập lý do hủy (ví dụ: Không đủ khách tối thiểu, lý do thời tiết...)" rows={3} style={{ width: "100%" }} />
            <div className="flex w-full gap-2">
              <AntButton htmlType="button" onClick={() => setIsCancelModalOpen(false)}>Hủy bỏ
              </AntButton>
              <AntButton htmlType="button" onClick={confirmCancelSchedule} disabled={!cancelReasonInput.trim()} type="primary" danger>Hủy chuyến
              </AntButton>
            </div>
          </UIFlex></AntModal>
      )}<Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((current) => ({ ...current, isOpen: false }))}
      /></UIFlex>
  );
}
