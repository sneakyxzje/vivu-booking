import React, { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, ArrowRight, Loader2 } from "lucide-react";
import guideService from "@/services/guideService";
import { TourFormBasicSection } from "@/components/guide/tour-form/TourFormBasicSection";
import { TourFormMediaSection } from "@/components/guide/tour-form/TourFormMediaSection";
import { TourFormItinerarySection } from "@/components/guide/tour-form/TourFormItinerarySection";
import { TourFormScheduleSection } from "@/components/guide/tour-form/TourFormScheduleSection";
import { daDoiHanChot, khoaChuyenMoi, ngayRong } from "@/components/guide/tour-form/formHelpers";
import { LY_DO_DOI_HAN_TOI_THIEU } from "@/utils/schedule";
import { TourFormTaxonomySection } from "@/components/guide/tour-form/TourFormTaxonomySection";
import { TourFormSidebar } from "@/components/guide/tour-form/TourFormSidebar";
import {
  TourFormStepper,
  type BuocForm,
} from "@/components/guide/tour-form/TourFormStepper";
import type {
  ScheduleFormItem,
  SelectOption,
  TourFormState,
} from "@/components/guide/tour-form/types";
import tourService from "@/services/tourService";
import adminService from "@/services/adminService";
import type { Guide } from "@/types";
import { toDateTimeLocalValue } from "@/utils/format";

/**
 * Tạo và sửa tour, chia làm bốn bước.
 *
 * ## Vì sao chia bước
 *
 * Một tour đủ dùng cần khoảng bốn mươi ô nhập, trải trên năm khối lồng nhau. Đổ hết ra một
 * trang thì người tạo không biết mình đang ở đâu, đã xong chưa, và bấm Lưu là cách duy nhất để
 * biết còn thiếu gì. Bốn bước đặt ranh giới cho việc ấy, và thanh bước trả lời câu "còn thiếu
 * gì" ngay khi đang gõ, không đợi tới lúc máy chủ từ chối.
 *
 * Các bước KHÔNG khóa lẫn nhau — bấm thẳng vào bước 3 để mở thêm ngày khởi hành là việc người
 * điều hành làm hằng ngày, xem `TourFormStepper`.
 */

const BUOC: BuocForm[] = [
  { ten: "Thông tin & giá", moTa: "Tên tour, thời lượng, giá vé" },
  { ten: "Lịch trình", moTa: "Hành trình từng ngày" },
  { ten: "Lịch khởi hành", moTa: "Ngày đi, số chỗ, hướng dẫn viên" },
  { ten: "Ảnh & phân loại", moTa: "Ảnh bìa, danh mục, dịch vụ" },
];

const emptyForm: TourFormState = {
  title: "",
  description: "",
  adult_price: "",
  child_price: "",
  infant_price: "0",
  thumbnail: "",
  number_of_days: "1",
  number_of_nights: "0",
  start_location: "",
  end_location: "",
  vehicle_info: "",
  pickup_location: "",
  thumbnail_file: null as File | null,
  thumbnail_preview: "",
  images: [] as File[],
  image_previews: [] as string[],
  itineraries: [ngayRong(1)],
  // Không dựng sẵn một chuyến rỗng: lịch ở bước 3 mở chuyến bằng một cú bấm, còn một hàng trống
  // chờ điền thì vừa là lỗi chờ sẵn vừa không nói được ngày nào đang mở bán.
  schedules: [] as ScheduleFormItem[],
  category_ids: [] as number[],
  service_ids: [] as number[],
};

const fieldClass =
  "block w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100";

const labelClass = "block text-sm font-semibold text-gray-800 mb-2";

/**
 * Chuỗi chặng lưu trong cơ sở dữ liệu về lại thành danh sách ô nhập.
 *
 * Trả mảng rỗng khi tour không khai chặng nào. Trước đây trả về một hàng rỗng, làm tour đi thẳng
 * mở ra lúc nào cũng thấy một ô trống chờ điền.
 */
const parseRoutePoints = (value?: string | null): string[] =>
  (value ?? "")
    .split(/[,\n]/)
    .map((point) => point.trim())
    .filter(Boolean);

const formatPreviewPrice = (value: string) => {
  const amount = Number(value);
  if (!amount || Number.isNaN(amount)) return "0 đ";

  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(amount);
};

const laSoDuong = (giaTri: string) => {
  const so = Number(giaTri);
  return giaTri.trim() !== "" && !Number.isNaN(so) && so >= 0;
};

export const CreateTourForm: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [form, setForm] = useState(emptyForm);
  const [buoc, setBuoc] = useState(0);
  const [daGhe, setDaGhe] = useState<number[]>([0]);
  const [categories, setCategories] = useState<SelectOption[]>([]);
  const [services, setServices] = useState<SelectOption[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [guidesByUid, setGuidesByUid] = useState<Record<string, Guide[]>>({});

  /**
   * Đang hỏi máy chủ ai rảnh hay chưa — suy ra, không giữ thành state riêng.
   *
   * Chuyến nào đã có ngày mà chưa có mục trong `guidesByUid` thì câu trả lời chưa về. Một cờ
   * `loading` riêng chỉ là bản sao của điều này, và bản sao thì có lúc lệch.
   */
  const guideAvailabilityLoading = form.schedules.some(
    (schedule) => schedule.start_date && guidesByUid[schedule.uid] === undefined,
  );

  const previewPrice = useMemo(
    () => formatPreviewPrice(form.adult_price),
    [form.adult_price],
  );

  const selectedCategories = useMemo(
    () => categories.filter((category) => form.category_ids.includes(category.id)),
    [categories, form.category_ids],
  );

  const selectedServices = useMemo(
    () => services.filter((service) => form.service_ids.includes(service.id)),
    [services, form.service_ids],
  );

  useEffect(() => {
    const load = async () => {
      try {
        const formData = await guideService.getFormData();
        setCategories(formData.categories);
        setServices(formData.services);
      } catch {
        setCategories([]);
        setServices([]);
      } finally {
        setOptionsLoading(false);
      }
    };
    load();
  }, []);

  /**
   * Hướng dẫn viên đang rảnh cho từng chuyến, tra theo `uid` chứ không theo vị trí trong mảng —
   * danh sách chuyến được sắp lại theo ngày, nên vị trí không đứng yên.
   */
  useEffect(() => {
    const numberOfDays = Number(form.number_of_days);
    const soNgayHopLe = Number.isInteger(numberOfDays) && numberOfDays >= 1;

    let cancelled = false;

    const loadAvailableGuides = async () => {
      // Số ngày chưa hợp lệ thì không hỏi được ai rảnh — nhưng vẫn phải ghi một mục rỗng cho
      // mỗi chuyến, nếu không màn hình đứng mãi ở "đang tìm hướng dẫn viên".
      const entries = soNgayHopLe
        ? await Promise.all(
            form.schedules.map(async (schedule) => {
              if (!schedule.start_date) return [schedule.uid, []] as const;

              try {
                const guides = await adminService.getAvailableGuides(
                  schedule.start_date,
                  numberOfDays,
                );
                return [schedule.uid, guides] as const;
              } catch {
                return [schedule.uid, []] as const;
              }
            }),
          )
        : form.schedules.map((schedule) => [schedule.uid, []] as const);

      if (!cancelled) setGuidesByUid(Object.fromEntries(entries));
    };

    loadAvailableGuides();

    return () => {
      cancelled = true;
    };
  }, [form.number_of_days, form.schedules]);

  useEffect(() => {
    // Tạo mới thì không có gì để tải: `loading` đã khởi tạo bằng `isEdit` nên vốn đang là false.
    if (!isEdit || !id) return;

    const loadTour = async () => {
      try {
        const tour = await adminService.getTourById(Number(id));
        if (!tour) {
          setError("Không tìm thấy tour.");
          return;
        }
        setForm({
          title: tour.title,
          description: tour.description ?? "",
          adult_price: String(tour.adult_price ?? ""),
          child_price: String(tour.child_price ?? ""),
          infant_price: String(tour.infant_price ?? 0),
          thumbnail: tour.thumbnail ?? "",
          number_of_days: String(tour.number_of_days),
          number_of_nights: String(tour.number_of_nights),
          start_location: tour.start_location,
          end_location: tour.end_location ?? "",
          vehicle_info: tour.vehicle_info ?? "",
          pickup_location: tour.pickup_location ?? "",
          thumbnail_file: null,
          thumbnail_preview: "",
          images: [],
          image_previews: [],
          itineraries:
            tour.itineraries?.map((item) => ({
              id: item.id,
              day_number: String(item.day_number),
              title: item.title,
              start_point: item.start_point ?? "",
              end_point: item.end_point ?? "",
              route_points: parseRoutePoints(item.route_points),
              rest_stops: item.rest_stops ?? "",
              content: item.content ?? "",
              // Điểm dừng đọc về theo đúng thứ tự đã lưu, kèm id để máy chủ khớp lại thay vì
              // xóa rồi tạo mới — xóa là mất luôn bản ghi điểm danh gắn với nó.
              checkpoints: [...(item.checkpoints ?? [])]
                .sort((a, b) => a.sequence - b.sequence)
                .map((cp) => ({
                  id: cp.id,
                  name: cp.name,
                  description: cp.description ?? "",
                  is_required_photo: Boolean(cp.is_required_photo),
                })),
            })) ?? emptyForm.itineraries,
          schedules:
            tour.schedules?.map((item) => ({
              id: item.id,
              uid: khoaChuyenMoi(),
              start_date: toDateTimeLocalValue(item.start_date),
              /*
               * Mốc kết thúc đọc thẳng từ `end_date`, không dựng lại từ số ngày.
               *
               * Người sửa phải nhìn thấy đúng con số đang lưu rồi tự quyết có đổi hay không. Điền
               * sẵn một gợi ý ở đây là ghi đè dữ liệu thật ngay lần bấm Lưu đầu tiên, kể cả khi
               * người ta vào chỉ để sửa tiêu đề.
               */
              end_date: toDateTimeLocalValue(item.end_date),
              arrival_at: toDateTimeLocalValue(item.arrival_at),
              return_departure_at: toDateTimeLocalValue(item.return_departure_at),
              max_people: String(item.max_people ?? 10),
              min_people: String(item.min_people ?? 5),
              booking_deadline: toDateTimeLocalValue(item.booking_deadline),
              // Giữ bản gốc để biết hạn chốt có thực sự bị dời hay không: dời thì máy chủ đòi lý do.
              booking_deadline_goc: toDateTimeLocalValue(item.booking_deadline),
              booking_deadline_reason: "",
              status: String(item.status ?? "open"),
              guide_ids: (item.guides ?? []).map((guide) => String(guide.id)),
            })) ?? [],
          category_ids: tour.categories?.map((c) => c.id) ?? [],
          service_ids: tour.services?.map((s) => s.id) ?? [],
        });
      } catch {
        setError("Không thể tải dữ liệu tour.");
      } finally {
        setLoading(false);
      }
    };

    loadTour();
  }, [id, isEdit]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (error) setError("");
  };

  const datTruong = (name: string, value: string) => {
    setForm((prev) => ({ ...prev, [name]: value }));
    if (error) setError("");
  };

  const handleThumbnailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;

    setForm((prev) => {
      if (prev.thumbnail_preview) URL.revokeObjectURL(prev.thumbnail_preview);

      return {
        ...prev,
        thumbnail_file: file,
        thumbnail_preview: file ? URL.createObjectURL(file) : "",
        thumbnail: file ? "" : prev.thumbnail,
      };
    });

    if (error) setError("");
  };

  const boAnhBia = () => {
    setForm((prev) => {
      if (prev.thumbnail_preview) URL.revokeObjectURL(prev.thumbnail_preview);

      // Gửi `thumbnail` rỗng là cách nói với máy chủ rằng tour này thôi không dùng ảnh bìa nữa.
      return { ...prev, thumbnail_file: null, thumbnail_preview: "", thumbnail: "" };
    });
  };

  /** Chọn ảnh lần nữa là THÊM vào bộ ảnh, không thay thế bộ đang có. */
  const handleGalleryChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files ?? []);
    if (files.length === 0) return;

    setForm((prev) => ({
      ...prev,
      images: [...prev.images, ...files],
      image_previews: [
        ...prev.image_previews,
        ...files.map((file) => URL.createObjectURL(file)),
      ],
    }));

    // Cho phép chọn lại đúng tệp vừa chọn: không xóa thì sự kiện change không bắn lần thứ hai.
    e.target.value = "";
    if (error) setError("");
  };

  const removeGalleryImage = (index: number) => {
    setForm((prev) => {
      URL.revokeObjectURL(prev.image_previews[index]);

      return {
        ...prev,
        images: prev.images.filter((_, i) => i !== index),
        image_previews: prev.image_previews.filter((_, i) => i !== index),
      };
    });
  };

  const toggleId = (field: "category_ids" | "service_ids", id: number) => {
    setForm((prev) => {
      const list = prev[field];
      return {
        ...prev,
        [field]: list.includes(id) ? list.filter((x) => x !== id) : [...list, id],
      };
    });
  };

  /**
   * Còn thiếu gì ở từng bước.
   *
   * Cùng một bộ luật dùng cho ba việc: dấu cảnh báo trên thanh bước, danh sách "còn thiếu" ở cột
   * phải, và cái chặn lúc bấm Lưu. Ba chỗ nói cùng một điều vì chỉ có một chỗ định nghĩa nó.
   *
   * Luật ở đây khớp với luật máy chủ áp trong `AdminTourController` — số đêm không quá số ngày,
   * lịch trình không quá số ngày, khách tối thiểu không quá sức chứa, hạn chốt phải TRƯỚC giờ
   * khởi hành.
   */
  const loiTheoBuoc = useMemo<string[][]>(() => {
    const soNgay = Number(form.number_of_days);

    const buoc1: string[] = [];
    if (!form.title.trim()) buoc1.push("Chưa đặt tên tour");
    if (!form.start_location.trim()) buoc1.push("Chưa có điểm khởi hành");
    if (!laSoDuong(form.adult_price)) buoc1.push("Chưa nhập giá người lớn");
    if (!laSoDuong(form.child_price)) buoc1.push("Chưa nhập giá trẻ em");
    if (!laSoDuong(form.infant_price)) buoc1.push("Chưa nhập giá em bé");
    if (!Number.isInteger(soNgay) || soNgay < 1) buoc1.push("Số ngày phải từ 1 trở lên");
    if (Number(form.number_of_nights) > soNgay) buoc1.push("Số đêm đang lớn hơn số ngày");

    const buoc2: string[] = [];
    if (form.itineraries.length === 0) buoc2.push("Lịch trình chưa có ngày nào");
    if (Number.isInteger(soNgay) && form.itineraries.length > soNgay) {
      buoc2.push(`Lịch trình đang nhiều hơn ${soNgay} ngày của tour`);
    }
    const ngayThieu = form.itineraries.filter(
      (item) => !item.title.trim() || !item.content.trim(),
    ).length;
    if (ngayThieu > 0) buoc2.push(`${ngayThieu} ngày chưa có tiêu đề hoặc nội dung`);

    const buoc3: string[] = [];
    if (form.schedules.length === 0) buoc3.push("Chưa mở ngày khởi hành nào");
    if (form.schedules.some((item) => !item.start_date)) {
      buoc3.push("Có chuyến chưa chọn ngày khởi hành");
    }
    if (
      form.schedules.some(
        (item) => Number(item.min_people) > Number(item.max_people),
      )
    ) {
      buoc3.push("Có chuyến đặt khách tối thiểu lớn hơn sức chứa");
    }
    if (
      form.schedules.some(
        (item) => item.booking_deadline && item.booking_deadline >= item.start_date,
      )
    ) {
      buoc3.push("Có chuyến đặt hạn chốt không trước giờ khởi hành");
    }
    if (
      form.schedules.some(
        (item) =>
          daDoiHanChot(item) &&
          (item.booking_deadline_reason ?? "").trim().length < LY_DO_DOI_HAN_TOI_THIEU,
      )
    ) {
      buoc3.push("Có chuyến dời hạn chốt mà chưa ghi lý do");
    }
    if (form.schedules.some((item) => Number(item.max_people) < 1)) {
      buoc3.push("Có chuyến chưa đặt sức chứa");
    }
    /*
     * Hai chuyến khởi hành đúng cùng một phút.
     *
     * Nhiều chuyến trong một ngày thì được — ca sáng ca chiều là chuyện thường. Nhưng trùng khít
     * cả giờ thì khách nhìn hai dòng y hệt nhau trong ô chọn ngày và không biết chọn cái nào.
     */
    if (
      form.schedules.some(
        (item, i) =>
          item.start_date &&
          form.schedules.findIndex((khac) => khac.start_date === item.start_date) !== i,
      )
    ) {
      buoc3.push("Có hai chuyến khởi hành trùng đúng ngày giờ");
    }

    // Bước 4 không có gì bắt buộc: tour thiếu ảnh vẫn bán được, chỉ là bán kém hơn.
    return [buoc1, buoc2, buoc3, []];
  }, [form]);

  const thieu = useMemo(() => loiTheoBuoc.flat(), [loiTheoBuoc]);

  const doiBuoc = (den: number) => {
    const gioiHan = Math.min(Math.max(den, 0), BUOC.length - 1);
    setBuoc(gioiHan);
    setDaGhe((cu) => (cu.includes(gioiHan) ? cu : [...cu, gioiHan]));
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Đánh dấu đã ghé mọi bước, để bước còn thiếu hiện cảnh báo thay vì im lặng.
    setDaGhe(BUOC.map((_, i) => i));

    const buocLoi = loiTheoBuoc.findIndex((loi) => loi.length > 0);
    if (buocLoi !== -1) {
      setError(loiTheoBuoc[buocLoi][0]);
      doiBuoc(buocLoi);
      return;
    }

    setSubmitting(true);
    setError("");

    try {
      if (isEdit && id) {
        await adminService.updateTour(Number(id), form);
      } else {
        await tourService.createTour(form);
      }
      navigate("/admin/tours");
    } catch (submitError: unknown) {
      const response = (
        submitError as {
          response?: {
            data?: { message?: string; errors?: Record<string, string[]> };
          };
        }
      ).response?.data;
      const firstValidationError = response?.errors
        ? Object.values(response.errors).flat()[0]
        : undefined;

      setError(
        firstValidationError ??
          response?.message ??
          "Không thể lưu tour. Vui lòng thử lại.",
      );
      window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="w-full animate-pulse space-y-5">
        <div className="h-8 w-52 rounded-lg bg-gray-200" />
        <div className="h-16 rounded-xl bg-white" />
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div className="h-[560px] rounded-xl border border-gray-100 bg-white" />
          <div className="h-80 rounded-xl border border-gray-100 bg-white" />
        </div>
      </div>
    );
  }

  const laBuocCuoi = buoc === BUOC.length - 1;
  // Bước lịch khởi hành cần cả bề ngang cho lịch tháng, nên cột xem trước lui ra.
  const anCotPhai = buoc === 2;

  return (
    <div className="w-full animate-fade-in pb-4">
      <div className="mb-5">
        <Link
          to="/admin/tours"
          className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors hover:text-primary-700"
        >
          <ArrowLeft className="h-4 w-4" />
          Danh sách tour
        </Link>
        <h1 className="mt-2 text-2xl font-bold tracking-tight text-gray-950">
          {isEdit ? "Sửa tour" : "Tạo tour mới"}
        </h1>
        <p className="mt-1 text-sm text-gray-500">
          {isEdit
            ? "Bấm thẳng vào bước cần sửa, không phải đi lại từ đầu."
            : "Bốn bước. Bước nào còn thiếu sẽ hiện dấu cảnh báo ngay trên thanh bước."}
        </p>
      </div>

      <div className="mb-5">
        <TourFormStepper
          buocs={BUOC}
          hienTai={buoc}
          loiTheoBuoc={loiTheoBuoc}
          daGhe={daGhe}
          onChon={doiBuoc}
        />
      </div>

      {error && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      <div
        className={`grid gap-6 ${anCotPhai ? "" : "lg:grid-cols-[minmax(0,1fr)_320px]"}`}
      >
        <form
          id="tour-form"
          onSubmit={handleSubmit}
          /*
           * Enter trong một ô nhập sẽ gửi biểu mẫu — ở đây nghĩa là lưu tour giữa chừng, từ bước
           * 1, khi ba bước sau còn trống. Chỉ nút Lưu mới được gửi.
           */
          onKeyDown={(e) => {
            if (e.key === "Enter" && (e.target as HTMLElement).tagName === "INPUT") {
              e.preventDefault();
            }
          }}
        >
          {buoc === 0 && (
            <TourFormBasicSection
              labelClass={labelClass}
              fieldClass={fieldClass}
              title={form.title}
              description={form.description}
              adultPrice={form.adult_price}
              childPrice={form.child_price}
              infantPrice={form.infant_price}
              numberOfDays={form.number_of_days}
              numberOfNights={form.number_of_nights}
              startLocation={form.start_location}
              endLocation={form.end_location}
              vehicleInfo={form.vehicle_info}
              pickupLocation={form.pickup_location}
              onChange={handleChange}
              onSet={datTruong}
            />
          )}

          {buoc === 1 && (
            <TourFormItinerarySection
              labelClass={labelClass}
              fieldClass={fieldClass}
              items={form.itineraries}
              maxDays={Math.max(1, Number(form.number_of_days) || 1)}
              onChange={(itineraries) => setForm((prev) => ({ ...prev, itineraries }))}
            />
          )}

          {buoc === 2 && (
            <TourFormScheduleSection
              fieldClass={fieldClass}
              items={form.schedules}
              numberOfDays={Math.max(1, Number(form.number_of_days) || 1)}
              guidesByUid={guidesByUid}
              availabilityLoading={guideAvailabilityLoading}
              onChange={(schedules) => setForm((prev) => ({ ...prev, schedules }))}
            />
          )}

          {buoc === 3 && (
            <div className="space-y-5">
              <TourFormMediaSection
                labelClass={labelClass}
                thumbnailName={form.thumbnail_file?.name ?? null}
                thumbnailPreview={form.thumbnail_preview}
                thumbnailUrl={form.thumbnail}
                imagePreviews={form.image_previews}
                onThumbnailChange={handleThumbnailChange}
                onThumbnailRemove={boAnhBia}
                onGalleryChange={handleGalleryChange}
                onRemoveGalleryImage={removeGalleryImage}
              />

              <TourFormTaxonomySection
                labelClass={labelClass}
                categories={categories}
                services={services}
                selectedCategoryIds={form.category_ids}
                selectedServiceIds={form.service_ids}
                onToggleCategory={(cid) => toggleId("category_ids", cid)}
                onToggleService={(sid) => toggleId("service_ids", sid)}
                optionsLoading={optionsLoading}
              />
            </div>
          )}
        </form>

        {!anCotPhai && (
          <TourFormSidebar
            title={form.title}
            previewPrice={previewPrice}
            startLocation={form.start_location}
            endLocation={form.end_location}
            thumbnailPreview={form.thumbnail_preview}
            thumbnailUrl={form.thumbnail}
            selectedCategories={selectedCategories}
            selectedServices={selectedServices}
            imageCount={form.images.length}
            numberOfDays={form.number_of_days}
            numberOfNights={form.number_of_nights}
            soChuyen={form.schedules.length}
            thieu={thieu}
          />
        )}
      </div>

      {/* Thanh hành động dính đáy: nút Lưu luôn trong tầm tay, kể cả giữa một bước dài. */}
      <div className="sticky bottom-0 z-20 -mx-4 mt-6 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
        <div className="flex flex-col-reverse items-stretch gap-2 sm:flex-row sm:items-center">
          <span className="text-xs font-semibold text-gray-500 sm:mr-auto">
            Bước {buoc + 1}/{BUOC.length} · {BUOC[buoc].ten}
            {thieu.length > 0 && (
              <span className="ml-2 text-amber-600">còn {thieu.length} việc chưa xong</span>
            )}
          </span>

          <Link
            to="/admin/tours"
            className="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
          >
            Hủy
          </Link>

          {buoc > 0 && (
            <button
              type="button"
              onClick={() => doiBuoc(buoc - 1)}
              className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
            >
              <ArrowLeft className="h-4 w-4" />
              Quay lại
            </button>
          )}

          {!laBuocCuoi && (
            <button
              type="button"
              onClick={() => doiBuoc(buoc + 1)}
              className="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700"
            >
              Tiếp theo
              <ArrowRight className="h-4 w-4" />
            </button>
          )}

          {/*
            Khi sửa tour, nút Lưu có mặt ở mọi bước: người vào sửa thường chỉ đổi một thứ, bắt
            họ bấm "Tiếp theo" cho hết bốn bước rồi mới được lưu là vô cớ.
          */}
          {(laBuocCuoi || isEdit) && (
            <button
              type="submit"
              form="tour-form"
              disabled={submitting}
              className={`inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                laBuocCuoi
                  ? "bg-primary-600 text-white hover:bg-primary-700"
                  : "border border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100"
              }`}
            >
              {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
              {submitting ? "Đang lưu..." : isEdit ? "Cập nhật tour" : "Tạo tour"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default CreateTourForm;
