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
import { formatDateTime, parseDate } from "@/utils/format";

const getEndDate = (startDate: string, numberOfDays: number) => {
  const date = parseDate(startDate);
  date.setDate(date.getDate() + Math.max(0, numberOfDays - 1));
  return date.toLocaleDateString("vi-VN");
};

const statusLabel: Record<Tour["status"], string> = {
  active: "Đang hoạt động",
  inactive: "Tạm dừng",
  full: "Hết chỗ",
};

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
            {tour.schedules.map((schedule) => (
              <div
                key={schedule.id}
                className="grid gap-4 px-6 py-5 lg:grid-cols-[1fr_1fr_1fr_minmax(220px,1.2fr)] lg:items-center"
              >
                <div className="flex items-center gap-3">
                  <CalendarDays className="h-5 w-5 text-primary-600" />
                  <div>
                    <p className="text-xs text-gray-500">Thời gian chuyến</p>
                    <p className="text-sm font-semibold text-gray-900">
                      {formatDateTime(schedule.start_date)} -{" "}
                      {getEndDate(schedule.start_date, tour.number_of_days)}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Users className="h-5 w-5 text-gray-500" />
                  <div>
                    <p className="text-xs text-gray-500">Số khách</p>
                    <p className="text-sm font-semibold text-gray-900">
                      {schedule.booked_people}/{schedule.max_people}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Clock className="h-5 w-5 text-gray-500" />
                  <div>
                    <p className="text-xs text-gray-500">Trạng thái</p>
                    <p className="text-sm font-semibold text-gray-900">
                      {statusLabel[schedule.status]}
                    </p>
                  </div>
                </div>
                <div>
                  <label
                    htmlFor={"schedule-guide-" + schedule.id}
                    className="mb-1.5 flex items-center gap-2 text-xs font-semibold text-gray-600"
                  >
                    <UserRound className="h-4 w-4" />
                    Hướng dẫn viên
                  </label>
                  <div className="flex flex-col gap-2 sm:flex-row">
                    <select
                      id={"schedule-guide-" + schedule.id}
                      value={
                        pendingGuideIds[schedule.id] ??
                        String(schedule.guide_id ?? "")
                      }
                      disabled={assigningScheduleId === schedule.id}
                      onChange={(event) =>
                        setPendingGuideIds((current) => ({
                          ...current,
                          [schedule.id]: event.target.value,
                        }))
                      }
                      className="min-w-0 flex-1 rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 disabled:cursor-wait disabled:bg-gray-100"
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
                      className="shrink-0 rounded-md bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    >
                      {assigningScheduleId === schedule.id
                        ? "Đang lưu..."
                        : "Xác nhận"}
                    </button>
                  </div>
                </div>
              </div>
            ))}
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

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((current) => ({ ...current, isOpen: false }))}
      />
    </div>
  );
}