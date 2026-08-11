import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import {
  ArrowLeft,
  CalendarDays,
  Clock,
  UserRound,
  Users,
} from "lucide-react";
import adminService from "@/services/adminService";
import type { Guide, Tour, TourSchedule } from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime, getEndDate } from "@/utils/format";
import { statusLabel, statusClasses } from "@/utils/schedule";

export default function AdminTourDetail() {
  const { id } = useParams<{ id: string }>();
  const [tour, setTour] = useState<Tour | null>(null);
  const [guides, setGuides] = useState<Guide[]>([]);
  const [loading, setLoading] = useState(true);
  const [assigningScheduleId, setAssigningScheduleId] = useState<number | null>(
    null,
  );
  const [pendingGuideIds, setPendingGuideIds] = useState<Record<number, string>>({});
  const [toast, setToast] = useState({
    message: "",
    type: "success" as "success" | "error" | "info",
    isOpen: false,
  });
  const [cancellingScheduleId, setCancellingScheduleId] = useState<number | null>(null);
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

  const assignGuide = async (
    schedule: TourSchedule,
    guideId: number | null,
  ) => {
    setAssigningScheduleId(schedule.id);

    try {
      await adminService.assignGuideToSchedule(schedule.id, guideId);
      const guide = guides.find((item) => item.id === guideId) ?? null;

      setPendingGuideIds((current) => ({
        ...current,
        [schedule.id]: guideId === null ? "" : String(guideId),
      }));
      setTour((current) =>
        current
          ? {
              ...current,
              schedules: current.schedules?.map((item) =>
                item.id === schedule.id
                  ? { ...item, guide_id: guideId, guide }
                  : item,
              ),
            }
          : current,
      );
      setToast({
        message:
          guideId === null
            ? "Đã bỏ phân công hướng dẫn viên."
            : "Phân công hướng dẫn viên thành công.",
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

  const handleUpdateStatus = async (scheduleId: number, nextStatus: string, reason?: string) => {
    try {
      console.log(`Updating schedule ${scheduleId} to status: ${nextStatus}`);
      
      setTour((current) => {
        if (!current) return null;
        return {
          ...current,
          schedules: current.schedules?.map((item) => {
            if (item.id === scheduleId) {
              const updated = {
                ...item,
                status: nextStatus as any,
              };
              if (nextStatus === "cancelled") {
                updated.cancelled_reason = reason || "Điều hành hủy chuyến";
              }
              return updated;
            }
            return item;
          }),
        };
      });

      setToast({
        message: `Đã cập nhật trạng thái chuyến đi thành "${statusLabel[nextStatus]}".`,
        type: "success",
        isOpen: true,
      });
    } catch {
      setToast({
        message: "Không thể cập nhật trạng thái chuyến đi.",
        type: "error",
        isOpen: true,
      });
    }
  };

  const openCancelDialog = (scheduleId: number) => {
    setCancellingScheduleId(scheduleId);
    setCancelReasonInput("");
    setIsCancelModalOpen(true);
  };

  const confirmCancelSchedule = () => {
    if (!cancellingScheduleId) return;
    handleUpdateStatus(cancellingScheduleId, "cancelled", cancelReasonInput || "Lý do bất khả kháng");
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
      <div className="rounded-lg border border-gray-200 bg-white p-10 text-center">
        <p className="font-semibold text-gray-900">Không tìm thấy tour.</p>
        <Link
          to="/admin/tours"
          className="mt-4 inline-flex text-sm font-semibold text-primary-600"
        >
          Quay lại danh sách
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <Link
            to="/admin/tours"
            className="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-primary-600"
          >
            <ArrowLeft className="h-4 w-4" />
            Danh sách tour
          </Link>
          <h1 className="text-2xl font-bold text-gray-950">{tour.title}</h1>
        </div>
        <span
          className={
            "rounded px-3 py-1.5 text-xs font-semibold " +
            (tour.status === "active"
              ? "bg-emerald-50 text-emerald-700"
              : tour.status === "full"
                ? "bg-red-50 text-red-700"
                : "bg-gray-100 text-gray-600")
          }
        >
          {statusLabel[tour.status]}
        </span>
      </div>

      <section className="overflow-hidden rounded-lg border border-gray-200 bg-white">
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
                <p className="text-xs font-semibold uppercase text-gray-400">Giá tour</p>
                <p className="mt-1 font-bold text-gray-950">
                  {Number(tour.adult_price ?? tour.discount_price ?? tour.price).toLocaleString("vi-VN")} đ
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">Thời lượng</p>
                <p className="mt-1 font-semibold text-gray-900">
                  {tour.number_of_days} ngày {tour.number_of_nights} đêm
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">Khởi hành</p>
                <p className="mt-1 font-semibold text-gray-900">{tour.start_location}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-gray-400">Kết thúc</p>
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
      </section>

      <section className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="font-bold text-gray-950">Các chuyến đi</h2>
          <p className="mt-1 text-sm text-gray-500">
            Phân công hướng dẫn viên riêng cho từng lịch khởi hành.
          </p>
        </div>

        {tour.schedules?.length ? (
          <div className="divide-y divide-gray-100">
            {tour.schedules.map((schedule) => {
              const status = schedule.status || "open";
              const deadline = schedule.booking_deadline;
              const minPeople = schedule.min_people || 5;
              const isOverdue = deadline ? new Date(deadline) < new Date() : false;

              return (
                <div
                  key={schedule.id}
                  className="p-6 hover:bg-gray-50/50 transition-colors"
                >
                  <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    {/* Main Info */}
                    <div className="space-y-3 flex-1">
                      <div className="flex flex-wrap items-center gap-3">
                        <span className="text-xs font-bold text-primary-700 font-mono">
                          CHUYẾN #{schedule.id}
                        </span>
                        <span
                          className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                            statusClasses[status] || statusClasses.open
                          }`}
                        >
                          {statusLabel[status]}
                        </span>
                        {status === "cancelled" && schedule.cancelled_reason && (
                          <span className="text-xs text-rose-600 bg-rose-50 px-2 py-1 rounded-lg border border-rose-100">
                            Lý do hủy: {schedule.cancelled_reason}
                          </span>
                        )}
                      </div>

                      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {/* Time */}
                        <div className="flex items-center gap-2.5">
                          <CalendarDays className="h-4.5 w-4.5 text-gray-400 shrink-0" />
                          <div className="text-xs">
                            <p className="text-gray-400">Thời gian khởi hành</p>
                            <p className="font-semibold text-gray-900 mt-0.5">
                              {formatDateTime(schedule.start_date)} -{" "}
                              {getEndDate(schedule.start_date, tour.number_of_days)}
                            </p>
                          </div>
                        </div>

                        {/* Booking deadline */}
                        <div className="flex items-center gap-2.5">
                          <Clock className={`h-4.5 w-4.5 shrink-0 ${isOverdue && status === "open" ? "text-amber-500 animate-pulse" : "text-gray-400"}`} />
                          <div className="text-xs">
                            <p className="text-gray-400">Hạn đặt (Booking Deadline)</p>
                            <p className="font-semibold text-gray-900 mt-0.5">
                              {deadline ? formatDateTime(deadline) : "Không giới hạn"}
                              {isOverdue && status === "open" && (
                                <span className="ml-1.5 text-[9px] bg-amber-50 text-amber-700 px-1 py-0.5 rounded font-bold uppercase tracking-wide">Quá hạn</span>
                              )}
                            </p>
                          </div>
                        </div>

                        {/* Guest capacity */}
                        <div className="flex items-center gap-2.5">
                          <Users className="h-4.5 w-4.5 text-gray-400 shrink-0" />
                          <div className="text-xs">
                            <p className="text-gray-400">Tình trạng chỗ</p>
                            <p className="font-semibold text-gray-900 mt-0.5">
                              {schedule.booked_people} / {schedule.max_people} khách{" "}
                              <span className="text-gray-400 font-normal">
                                (Tối thiểu: {minPeople})
                              </span>
                            </p>
                          </div>
                        </div>
                      </div>

                      {/* Guide Assign Section */}
                      <div className="pt-2 flex flex-col gap-2 sm:flex-row sm:items-center max-w-lg">
                        <div className="shrink-0 text-xs font-semibold text-gray-500 flex items-center gap-1">
                          <UserRound className="h-3.5 w-3.5" /> Hướng dẫn viên:
                        </div>
                        <select
                          id={"schedule-guide-" + schedule.id}
                          value={
                            pendingGuideIds[schedule.id] ??
                            String(schedule.guide_id ?? "")
                          }
                          disabled={assigningScheduleId === schedule.id || status === "cancelled" || status === "completed"}
                          onChange={(event) =>
                            setPendingGuideIds((current) => ({
                              ...current,
                              [schedule.id]: event.target.value,
                            }))
                          }
                          className="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-gray-50"
                        >
                          <option value="">Chưa phân công</option>
                          {guides.map((guide) => (
                            <option key={guide.id} value={guide.id}>
                              {guide.name}
                            </option>
                          ))}
                        </select>
                        <button
                          type="button"
                          disabled={
                            assigningScheduleId === schedule.id ||
                            status === "cancelled" ||
                            status === "completed" ||
                            (pendingGuideIds[schedule.id] ??
                              String(schedule.guide_id ?? "")) ===
                              String(schedule.guide_id ?? "")
                          }
                          onClick={() => {
                            const value =
                              pendingGuideIds[schedule.id] ??
                              String(schedule.guide_id ?? "");
                            assignGuide(schedule, value ? Number(value) : null);
                          }}
                          className="shrink-0 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 transition-colors"
                        >
                          {assigningScheduleId === schedule.id ? "Đang lưu..." : "Lưu HDV"}
                        </button>
                      </div>
                    </div>

                    {/* State controller menu */}
                    <div className="flex flex-col gap-2 shrink-0 w-full sm:w-auto lg:items-end">
                      <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        Vận hành chuyến
                      </p>

                      <div className="flex flex-wrap gap-1.5">
                        {/* Open/Close toggle */}
                        {status === "open" && (
                          <button
                            type="button"
                            onClick={() => handleUpdateStatus(schedule.id, "closed")}
                            className="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-200"
                          >
                            Đóng bán
                          </button>
                        )}
                        {status === "closed" && (
                          <button
                            type="button"
                            onClick={() => handleUpdateStatus(schedule.id, "open")}
                            className="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 transition-all active:scale-95 duration-200"
                          >
                            Mở bán lại
                          </button>
                        )}

                        {/* Confirm action */}
                        {(status === "open" || status === "closed") && (
                          <button
                            type="button"
                            onClick={() => handleUpdateStatus(schedule.id, "confirmed")}
                            className="rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 shadow-sm transition-all active:scale-95 duration-200"
                          >
                            Chốt chuyến
                          </button>
                        )}

                        {/* Start tour action */}
                        {status === "confirmed" && (
                          <button
                            type="button"
                            onClick={() => handleUpdateStatus(schedule.id, "in_progress")}
                            className="rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 shadow-sm transition-all active:scale-95 duration-200"
                          >
                            Bắt đầu chuyến
                          </button>
                        )}

                        {/* Complete tour action */}
                        {status === "in_progress" && (
                          <button
                            type="button"
                            onClick={() => handleUpdateStatus(schedule.id, "completed")}
                            className="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 shadow-sm transition-all active:scale-95 duration-200"
                          >
                            Kết thúc chuyến
                          </button>
                        )}

                        {/* Cancel action */}
                        {(status === "open" || status === "closed" || status === "confirmed") && (
                          <button
                            type="button"
                            onClick={() => openCancelDialog(schedule.id)}
                            className="rounded-lg border border-rose-150 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-all active:scale-95 duration-200"
                          >
                            Hủy chuyến
                          </button>
                        )}

                        {/* Closed states */}
                        {(status === "completed" || status === "cancelled") && (
                          <span className="text-xs text-gray-400 italic">
                            Chuyến đi đã kết thúc vòng đời
                          </span>
                        )}
                      </div>

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
        )}
      </section>

      <section className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="font-bold text-gray-950">Lịch trình theo ngày</h2>
        </div>
        {tour.itineraries?.length ? (
          <div className="divide-y divide-gray-100">
            {tour.itineraries
              .slice()
              .sort((a, b) => a.day_number - b.day_number)
              .map((item) => (
                <div key={item.id} className="grid gap-3 px-6 py-5 sm:grid-cols-[90px_1fr]">
                  <div className="font-bold text-primary-700">Ngày {item.day_number}</div>
                  <div>
                    <h3 className="font-semibold text-gray-950">{item.title}</h3>
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
        )}
      </section>

      {/* Modal Hủy Chuyến */}
      {isCancelModalOpen && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in pointer-events-auto">
          <div className="bg-white w-full max-w-sm rounded-xl shadow-2xl border border-gray-100 p-6 flex flex-col items-center text-center animate-scale-up">
            <div className="p-3.5 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 mb-4">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h4 className="text-base font-bold text-gray-900 mb-1">Xác nhận hủy chuyến đi</h4>
            <p className="text-xs text-gray-500 mb-4">
              Vui lòng cung cấp lý do chi tiết hủy chuyến đi này. Chỗ ngồi sẽ được trả lại và không thể phục hồi.
            </p>
            <textarea
              value={cancelReasonInput}
              onChange={(e) => setCancelReasonInput(e.target.value)}
              placeholder="Nhập lý do hủy (ví dụ: Không đủ khách tối thiểu, lý do thời tiết...)"
              rows={3}
              className="w-full rounded-xl border border-gray-250 bg-white p-3 text-xs text-gray-800 placeholder:text-gray-400 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 mb-4 resize-none"
            />
            <div className="flex w-full gap-2">
              <button
                type="button"
                onClick={() => setIsCancelModalOpen(false)}
                className="flex-1 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl transition-colors"
              >
                Hủy bỏ
              </button>
              <button
                type="button"
                onClick={confirmCancelSchedule}
                disabled={!cancelReasonInput.trim()}
                className="flex-1 py-2 text-xs font-semibold text-white rounded-xl shadow-md bg-rose-600 hover:bg-rose-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Hủy chuyến
              </button>
            </div>
          </div>
        </div>
      )}

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((current) => ({ ...current, isOpen: false }))}
      />
    </div>
  );
}