import React, { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import guideService from "@/services/guideService";
import { TourFormBasicSection } from "@/components/guide/tour-form/TourFormBasicSection";
import { TourFormMediaSection } from "@/components/guide/tour-form/TourFormMediaSection";
import { TourFormItinerarySection } from "@/components/guide/tour-form/TourFormItinerarySection";
import { TourFormScheduleSection } from "@/components/guide/tour-form/TourFormScheduleSection";
import { TourFormTaxonomySection } from "@/components/guide/tour-form/TourFormTaxonomySection";
import { TourFormSidebar } from "@/components/guide/tour-form/TourFormSidebar";
import type {
  ScheduleFormItem,
  ItineraryFormItem,
  SelectOption,
  TourFormState,
} from "@/components/guide/tour-form/types";
import tourService from "@/services/tourService";
import adminService from "@/services/adminService";
import type { Guide } from "@/types";

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
  thumbnail_file: null as File | null,
  thumbnail_preview: "",
  images: [] as File[],
  image_previews: [] as string[],
  itineraries: [
    {
      day_number: "1",
      title: "",
      start_point: "",
      end_point: "",
      route_points: "",
      rest_stops: "",
      content: "",
    } as ItineraryFormItem,
  ],
  schedules: [{ start_date: "", max_people: "10", guide_id: "" } as ScheduleFormItem],
  category_ids: [] as number[],
  service_ids: [] as number[],
};

const fieldClass =
  "block w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100";

const labelClass = "block text-sm font-semibold text-gray-800 mb-2";

const formatPreviewPrice = (value: string) => {
  const amount = Number(value);
  if (!amount || Number.isNaN(amount)) return "0 đ";

  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(amount);
};

export const CreateTourForm: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [form, setForm] = useState(emptyForm);
  const [categories, setCategories] = useState<SelectOption[]>([]);
  const [services, setServices] = useState<SelectOption[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [availableGuidesBySchedule, setAvailableGuidesBySchedule] = useState<Record<number, Guide[]>>({});
  const [guideAvailabilityLoading, setGuideAvailabilityLoading] = useState(false);

  const previewPrice = useMemo(
    () => formatPreviewPrice(form.adult_price),
    [form.adult_price],
  );

  const selectedCategories = useMemo(
    () =>
      categories.filter((category) => form.category_ids.includes(category.id)),
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

  useEffect(() => {
    const numberOfDays = Number(form.number_of_days);

    if (!Number.isInteger(numberOfDays) || numberOfDays < 1) {
      setAvailableGuidesBySchedule({});
      return;
    }

    let cancelled = false;

    const loadAvailableGuides = async () => {
      setGuideAvailabilityLoading(true);

      const entries = await Promise.all(
        form.schedules.map(async (schedule, index) => {
          if (!schedule.start_date) {
            return [index, []] as const;
          }

          try {
            const guides = await adminService.getAvailableGuides(
              schedule.start_date,
              numberOfDays,
            );
            return [index, guides] as const;
          } catch {
            return [index, []] as const;
          }
        }),
      );

      if (!cancelled) {
        setAvailableGuidesBySchedule(Object.fromEntries(entries));
        setGuideAvailabilityLoading(false);
      }
    };

    loadAvailableGuides();

    return () => {
      cancelled = true;
    };
  }, [form.number_of_days, form.schedules]);

  useEffect(() => {
    if (!isEdit || !id) {
      setLoading(false);
      return;
    }

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
          adult_price: String(tour.adult_price ?? tour.discount_price ?? tour.price ?? ""),
          child_price: String(tour.child_price ?? ""),
          infant_price: String(tour.infant_price ?? 0),
          thumbnail: tour.thumbnail ?? "",
          number_of_days: String(tour.number_of_days),
          number_of_nights: String(tour.number_of_nights),
          start_location: tour.start_location,
          end_location: tour.end_location ?? "",
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
              route_points: item.route_points ?? "",
              rest_stops: item.rest_stops ?? "",
              content: item.content ?? "",
            })) ?? emptyForm.itineraries,
          schedules:
            tour.schedules?.map((item) => ({
              id: item.id,
              start_date: item.start_date,
              max_people: String(item.max_people ?? 10),
              guide_id: String(item.guide_id ?? ""),
            })) ?? emptyForm.schedules,
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

  const handleThumbnailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;

    setForm((prev) => {
      if (prev.thumbnail_preview) {
        URL.revokeObjectURL(prev.thumbnail_preview);
      }

      return {
        ...prev,
        thumbnail_file: file,
        thumbnail_preview: file ? URL.createObjectURL(file) : "",
        thumbnail: file ? "" : prev.thumbnail,
      };
    });

    if (error) setError("");
  };

  const handleGalleryChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files ?? []);

    setForm((prev) => {
      prev.image_previews.forEach((preview) => URL.revokeObjectURL(preview));

      return {
        ...prev,
        images: files,
        image_previews: files.map((file) => URL.createObjectURL(file)),
      };
    });

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

  const updateItinerary = (
    index: number,
    field: "day_number" | "title" | "start_point" | "end_point" | "route_points" | "rest_stops" | "content",
    value: string,
  ) => {
    setForm((prev) => ({
      ...prev,
      itineraries: prev.itineraries.map((item, i) =>
        i === index ? { ...item, [field]: value } : item,
      ),
    }));
  };

  const addItinerary = () => {
    setForm((prev) => {
      const maxDays = Number(prev.number_of_days);

      if (
        !Number.isInteger(maxDays) ||
        maxDays < 1 ||
        prev.itineraries.length >= maxDays
      ) {
        return prev;
      }

      return {
        ...prev,
        itineraries: [
          ...prev.itineraries,
          {
            day_number: String(prev.itineraries.length + 1),
            title: "",
            start_point: "",
            end_point: "",
            route_points: "",
            rest_stops: "",
            content: "",
          },
        ],
      };
    });
  };

  const removeItinerary = (index: number) => {
    setForm((prev) => ({
      ...prev,
      itineraries:
        prev.itineraries.length === 1
          ? prev.itineraries
          : prev.itineraries.filter((_, i) => i !== index),
    }));
  };

  const updateSchedule = (
    index: number,
    field: "start_date" | "max_people" | "guide_id",
    value: string,
  ) => {
    setForm((prev) => ({
      ...prev,
      schedules: prev.schedules.map((item, i) =>
        i === index ? { ...item, [field]: value } : item,
      ),
    }));
  };

  const addSchedule = () => {
    setForm((prev) => ({
      ...prev,
      schedules: [
        ...prev.schedules,
        { start_date: "", max_people: "10", guide_id: "" },
      ],
    }));
  };

  const removeSchedule = (index: number) => {
    setForm((prev) => ({
      ...prev,
      schedules:
        prev.schedules.length === 1
          ? prev.schedules
          : prev.schedules.filter((_, i) => i !== index),
    }));
  };

  const toggleId = (field: "category_ids" | "service_ids", id: number) => {
    setForm((prev) => {
      const list = prev[field];
      const next = list.includes(id)
        ? list.filter((x) => x !== id)
        : [...list, id];
      return { ...prev, [field]: next };
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError("");

    try {
      const maxDays = Number(form.number_of_days);
      const hasInvalidItinerary = form.itineraries.some((item) => {
        const dayNumber = Number(item.day_number);
        return (
          !Number.isInteger(dayNumber) ||
          dayNumber < 1 ||
          dayNumber > maxDays
        );
      });

      if (form.itineraries.length > maxDays || hasInvalidItinerary) {
        setError("Lịch trình chỉ được tối đa " + maxDays + " ngày.");
        return;
      }

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
            data?: {
              message?: string;
              errors?: Record<string, string[]>;
            };
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
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="w-full animate-pulse space-y-5">
        <div className="h-8 w-52 rounded-lg bg-gray-200" />
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
          <div className="h-[560px] rounded-lg bg-white border border-gray-100" />
          <div className="h-80 rounded-lg bg-white border border-gray-100" />
        </div>
      </div>
    );
  }

  return (
    <div className="w-full animate-fade-in">
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <Link
            to="/admin/tours"
            className="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700"
          >
            <svg
              className="h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 19l-7-7 7-7"
              />
            </svg>
            Danh sách tour
          </Link>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-gray-950">
            {isEdit ? "Sửa tour" : "Tạo tour mới"}
          </h1>
          <p className="mt-1 text-sm text-gray-500">
            {isEdit
              ? "Cập nhật thông tin tour đang quản lý."
              : "Tour sẽ được kích hoạt sau khi admin lưu."}
          </p>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <form
          onSubmit={handleSubmit}
          className="rounded-lg border border-gray-100 bg-white shadow-sm"
        >
          <div className="border-b border-gray-100 px-5 py-4 md:px-6">
            <h2 className="text-base font-bold text-gray-950">
              Thông tin tour
            </h2>
          </div>

          <div className="space-y-6 p-5 md:p-6">
            {error && (
              <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {error}
              </div>
            )}

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
              onChange={handleChange}
            />

            <TourFormMediaSection
              labelClass={labelClass}
              thumbnailName={form.thumbnail_file?.name ?? null}
              thumbnailPreview={form.thumbnail_preview}
              thumbnailUrl={form.thumbnail}
              imagePreviews={form.image_previews}
              onThumbnailChange={handleThumbnailChange}
              onGalleryChange={handleGalleryChange}
              onRemoveGalleryImage={removeGalleryImage}
            />

            <TourFormItinerarySection
              labelClass={labelClass}
              fieldClass={fieldClass}
              items={form.itineraries}
              maxDays={Math.max(1, Number(form.number_of_days) || 1)}
              onAdd={addItinerary}
              onRemove={removeItinerary}
              onChange={updateItinerary}
            />

            <TourFormScheduleSection
              labelClass={labelClass}
              fieldClass={fieldClass}
              items={form.schedules}
              numberOfDays={Math.max(1, Number(form.number_of_days) || 1)}
              availableGuidesBySchedule={availableGuidesBySchedule}
              availabilityLoading={guideAvailabilityLoading}
              onAdd={addSchedule}
              onRemove={removeSchedule}
              onChange={updateSchedule}
            />

            <TourFormTaxonomySection
              labelClass={labelClass}
              categories={categories}
              services={services}
              selectedCategoryIds={form.category_ids}
              selectedServiceIds={form.service_ids}
              onToggleCategory={(id) => toggleId("category_ids", id)}
              onToggleService={(id) => toggleId("service_ids", id)}
              optionsLoading={optionsLoading}
            />
          </div>

          <div className="flex flex-col-reverse gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end md:px-6">
            <Link
              to="/admin/tours"
              className="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100"
            >
              Hủy
            </Link>
            <button
              type="submit"
              disabled={submitting}
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {submitting && (
                <svg
                  className="h-4 w-4 animate-spin"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                  />
                  <path
                    className="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                  />
                </svg>
              )}
              {submitting
                ? "Đang lưu..."
                : isEdit
                  ? "Cập nhật tour"
                  : "Tạo tour"}
            </button>
          </div>
        </form>

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
        />
      </div>
    </div>
  );
};

export default CreateTourForm;

