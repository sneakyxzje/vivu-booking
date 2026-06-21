import React, { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import hostService from "@/services/hostService";

const emptyForm = {
  title: "",
  description: "",
  price: "",
  discount_price: "",
  thumbnail: "",
  number_of_days: "1",
  number_of_nights: "0",
  start_location: "",
  end_location: "",
  thumbnail_file: null as File | null,
  thumbnail_preview: "",
  images: [] as File[],
  image_previews: [] as string[],
  itineraries: [{ day_number: "1", title: "", content: "" }],
  schedules: [{ start_date: "", max_people: "10" }],
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

export const HostTourForm: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [form, setForm] = useState(emptyForm);
  const [categories, setCategories] = useState<{ id: number; name: string }[]>(
    [],
  );
  const [services, setServices] = useState<{ id: number; name: string }[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  const previewPrice = useMemo(
    () => formatPreviewPrice(form.discount_price || form.price),
    [form.discount_price, form.price],
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
        const formData = await hostService.getFormData();
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
    if (!isEdit || !id) {
      setLoading(false);
      return;
    }

    const loadTour = async () => {
      try {
        const tour = await hostService.getTourById(Number(id));
        if (!tour) {
          setError("Không tìm thấy tour.");
          return;
        }
        setForm({
          title: tour.title,
          description: tour.description ?? "",
          price: String(tour.price),
          discount_price: tour.discount_price
            ? String(tour.discount_price)
            : "",
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
              day_number: String(item.day_number),
              title: item.title,
              content: item.content ?? "",
            })) ?? emptyForm.itineraries,
          schedules:
            tour.schedules?.map((item) => ({
              start_date: item.start_date,
              max_people: String(item.max_people ?? 10),
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
    field: "day_number" | "title" | "content",
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
    setForm((prev) => ({
      ...prev,
      itineraries: [
        ...prev.itineraries,
        {
          day_number: String(prev.itineraries.length + 1),
          title: "",
          content: "",
        },
      ],
    }));
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
    field: "start_date" | "max_people",
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
      schedules: [...prev.schedules, { start_date: "", max_people: "10" }],
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
      if (isEdit && id) {
        await hostService.updateTour(Number(id), form);
      } else {
        await hostService.createTour(form);
      }
      navigate("/host/tours");
    } catch {
      setError("Không thể lưu tour. Vui lòng thử lại.");
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
            to="/host/tours"
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
              : "Tour sẽ được gửi duyệt sau khi lưu."}
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
          <span className="h-2 w-2 rounded-full bg-amber-500" />
          Chờ duyệt
        </span>
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

            <div>
              <label className={labelClass}>
                Tiêu đề tour <span className="text-red-500">*</span>
              </label>
              <input
                name="title"
                required
                value={form.title}
                onChange={handleChange}
                placeholder="VD: Tour Hạ Long 2N1Đ"
                className={fieldClass}
              />
            </div>

            <div>
              <label className={labelClass}>Mô tả</label>
              <textarea
                name="description"
                rows={5}
                value={form.description}
                onChange={handleChange}
                placeholder="Điểm nổi bật, trải nghiệm chính, lịch trình tổng quan..."
                className={`${fieldClass} resize-y`}
              />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={labelClass}>
                  Giá gốc (VND) <span className="text-red-500">*</span>
                </label>
                <input
                  name="price"
                  type="number"
                  min={0}
                  required
                  value={form.price}
                  onChange={handleChange}
                  placeholder="3500000"
                  className={fieldClass}
                />
              </div>
              <div>
                <label className={labelClass}>Giá giảm (VND)</label>
                <input
                  name="discount_price"
                  type="number"
                  min={0}
                  value={form.discount_price}
                  onChange={handleChange}
                  placeholder="3200000"
                  className={fieldClass}
                />
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={labelClass}>
                  Số ngày <span className="text-red-500">*</span>
                </label>
                <input
                  name="number_of_days"
                  type="number"
                  min={1}
                  required
                  value={form.number_of_days}
                  onChange={handleChange}
                  className={fieldClass}
                />
              </div>
              <div>
                <label className={labelClass}>
                  Số đêm <span className="text-red-500">*</span>
                </label>
                <input
                  name="number_of_nights"
                  type="number"
                  min={0}
                  required
                  value={form.number_of_nights}
                  onChange={handleChange}
                  className={fieldClass}
                />
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={labelClass}>
                  Điểm khởi hành <span className="text-red-500">*</span>
                </label>
                <input
                  name="start_location"
                  required
                  value={form.start_location}
                  onChange={handleChange}
                  placeholder="Hà Nội"
                  className={fieldClass}
                />
              </div>
              <div>
                <label className={labelClass}>Điểm kết thúc</label>
                <input
                  name="end_location"
                  value={form.end_location}
                  onChange={handleChange}
                  placeholder="Hạ Long"
                  className={fieldClass}
                />
              </div>
            </div>

            <div>
              <label className={labelClass}>Ảnh thumbnail</label>
              <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center transition hover:border-primary-300 hover:bg-primary-50/60">
                <input
                  name="thumbnail_file"
                  type="file"
                  accept="image/*"
                  onChange={handleThumbnailChange}
                  className="sr-only"
                />
                <svg
                  className="h-8 w-8 text-primary-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={1.8}
                    d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"
                  />
                </svg>
                <span className="mt-3 text-sm font-semibold text-gray-800">
                  Chọn ảnh từ máy
                </span>
                <span className="mt-1 text-xs text-gray-500">
                  PNG, JPG, WEBP tối đa 5MB
                </span>
                {form.thumbnail_file && (
                  <span className="mt-3 max-w-full truncate rounded-lg bg-white px-3 py-1 text-xs font-medium text-gray-600">
                    {form.thumbnail_file.name}
                  </span>
                )}
              </label>
            </div>

            <div>
              <label className={labelClass}>Bộ ảnh tour</label>
              <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center transition hover:border-primary-300 hover:bg-primary-50/60">
                <input
                  name="images"
                  type="file"
                  accept="image/*"
                  multiple
                  onChange={handleGalleryChange}
                  className="sr-only"
                />
                <svg
                  className="h-7 w-7 text-primary-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={1.8}
                    d="M4 16l4-4a2 2 0 012.83 0L14 15m-2-2l1.5-1.5a2 2 0 012.83 0L20 15M4 6h16M6 20h12a2 2 0 002-2V8a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"
                  />
                </svg>
                <span className="mt-2 text-sm font-semibold text-gray-800">
                  Chọn nhiều ảnh
                </span>
                <span className="mt-1 text-xs text-gray-500">
                  Mỗi ảnh tối đa 5MB
                </span>
              </label>

              {form.image_previews.length > 0 && (
                <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                  {form.image_previews.map((preview, index) => (
                    <div
                      key={preview}
                      className="group relative overflow-hidden rounded-lg border border-gray-100 bg-gray-100"
                    >
                      <img
                        src={preview}
                        alt=""
                        className="aspect-[4/3] w-full object-cover"
                      />
                      <button
                        type="button"
                        onClick={() => removeGalleryImage(index)}
                        className="absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm transition hover:bg-red-50 hover:text-red-600"
                        aria-label="Xóa ảnh"
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
                            d="M6 18L18 6M6 6l12 12"
                          />
                        </svg>
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
              <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                  <h3 className="text-sm font-bold text-gray-950">
                    Lịch trình theo ngày
                  </h3>
                  <p className="mt-1 text-xs text-gray-500">
                    Lưu vào bảng tour_itineraries.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={addItinerary}
                  className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50"
                >
                  Thêm ngày
                </button>
              </div>

              <div className="space-y-3">
                {form.itineraries.map((item, index) => (
                  <div
                    key={index}
                    className="rounded-lg border border-gray-100 bg-white p-4"
                  >
                    <div className="mb-3 flex items-center justify-between gap-3">
                      <p className="text-sm font-semibold text-gray-900">
                        Ngày {index + 1}
                      </p>
                      {form.itineraries.length > 1 && (
                        <button
                          type="button"
                          onClick={() => removeItinerary(index)}
                          className="text-xs font-semibold text-red-600 hover:text-red-700"
                        >
                          Xóa
                        </button>
                      )}
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-[120px_minmax(0,1fr)]">
                      <div>
                        <label className={labelClass}>Số ngày</label>
                        <input
                          type="number"
                          min={1}
                          required
                          value={item.day_number}
                          onChange={(e) =>
                            updateItinerary(
                              index,
                              "day_number",
                              e.target.value,
                            )
                          }
                          className={fieldClass}
                        />
                      </div>
                      <div>
                        <label className={labelClass}>Tiêu đề</label>
                        <input
                          required
                          value={item.title}
                          onChange={(e) =>
                            updateItinerary(index, "title", e.target.value)
                          }
                          placeholder="VD: Khởi hành - tham quan vịnh"
                          className={fieldClass}
                        />
                      </div>
                    </div>
                    <div className="mt-3">
                      <label className={labelClass}>Nội dung</label>
                      <textarea
                        required
                        rows={3}
                        value={item.content}
                        onChange={(e) =>
                          updateItinerary(index, "content", e.target.value)
                        }
                        placeholder="Mô tả hoạt động trong ngày..."
                        className={`${fieldClass} resize-y`}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
              <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                  <h3 className="text-sm font-bold text-gray-950">
                    Lịch khởi hành
                  </h3>
                  <p className="mt-1 text-xs text-gray-500">
                    Lưu vào bảng tour_schedules.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={addSchedule}
                  className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50"
                >
                  Thêm lịch
                </button>
              </div>

              <div className="space-y-3">
                {form.schedules.map((item, index) => (
                  <div
                    key={index}
                    className="grid grid-cols-1 gap-3 rounded-lg border border-gray-100 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_160px_auto]"
                  >
                    <div>
                      <label className={labelClass}>Ngày khởi hành</label>
                      <input
                        type="date"
                        required
                        value={item.start_date}
                        onChange={(e) =>
                          updateSchedule(index, "start_date", e.target.value)
                        }
                        className={fieldClass}
                      />
                    </div>
                    <div>
                      <label className={labelClass}>Số khách tối đa</label>
                      <input
                        type="number"
                        min={1}
                        required
                        value={item.max_people}
                        onChange={(e) =>
                          updateSchedule(index, "max_people", e.target.value)
                        }
                        className={fieldClass}
                      />
                    </div>
                    <div className="flex items-end">
                      <button
                        type="button"
                        onClick={() => removeSchedule(index)}
                        disabled={form.schedules.length === 1}
                        className="w-full rounded-lg border border-gray-200 bg-white px-3 py-3 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                      >
                        Xóa
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {optionsLoading && (
              <div className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                Đang tải danh mục và dịch vụ...
              </div>
            )}

            {categories.length > 0 && (
              <div>
                <label className={labelClass}>Danh mục</label>
                <div className="flex flex-wrap gap-2">
                  {categories.map((category) => {
                    const checked = form.category_ids.includes(category.id);

                    return (
                      <label
                        key={category.id}
                        className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                          checked
                            ? "border-primary-500 bg-primary-50 text-primary-700"
                            : "border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:bg-gray-50"
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => toggleId("category_ids", category.id)}
                          className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        {category.name}
                      </label>
                    );
                  })}
                </div>
              </div>
            )}

            {services.length > 0 && (
              <div>
                <label className={labelClass}>Dịch vụ đi kèm</label>
                <div className="flex flex-wrap gap-2">
                  {services.map((service) => {
                    const checked = form.service_ids.includes(service.id);

                    return (
                      <label
                        key={service.id}
                        className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                          checked
                            ? "border-primary-500 bg-primary-50 text-primary-700"
                            : "border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:bg-gray-50"
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => toggleId("service_ids", service.id)}
                          className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        {service.name}
                      </label>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          <div className="flex flex-col-reverse gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end md:px-6">
            <Link
              to="/host/tours"
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
                  : "Gửi tour chờ duyệt"}
            </button>
          </div>
        </form>

        <aside className="space-y-4 lg:sticky lg:top-6 lg:self-start">
          <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
            <div className="aspect-[16/10] bg-gray-100">
              {form.thumbnail_preview || form.thumbnail ? (
                <img
                  src={form.thumbnail_preview || form.thumbnail}
                  alt=""
                  className="h-full w-full object-cover"
                />
              ) : (
                <div className="flex h-full items-center justify-center bg-primary-50 text-primary-600">
                  <svg
                    className="h-10 w-10"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={1.8}
                      d="M3 16l5-5a2 2 0 012.83 0L14 14m-1-1l2-2a2 2 0 012.83 0L21 14M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                </div>
              )}
            </div>
            <div className="p-5">
              <div className="flex items-start justify-between gap-3">
                <h2 className="line-clamp-2 text-lg font-bold text-gray-950">
                  {form.title || "Tên tour"}
                </h2>
                <span className="shrink-0 rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700">
                  {form.number_of_days || 1}N
                  {Number(form.number_of_nights) > 0
                    ? ` ${form.number_of_nights}Đ`
                    : ""}
                </span>
              </div>
              <p className="mt-3 text-xl font-bold text-primary-600">
                {previewPrice}
              </p>
              <div className="mt-4 space-y-2 text-sm text-gray-600">
                <div className="flex items-center gap-2">
                  <svg
                    className="h-4 w-4 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M12 11c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                    />
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M19 9c0 5-7 11-7 11S5 14 5 9a7 7 0 1114 0z"
                    />
                  </svg>
                  <span className="truncate">
                    {form.start_location || "Điểm khởi hành"}
                    {form.end_location ? ` - ${form.end_location}` : ""}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div className="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
            <h3 className="text-sm font-bold text-gray-950">Đã chọn</h3>
            <div className="mt-4 space-y-4">
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">
                  Bộ ảnh
                </p>
                <p className="mt-2 text-sm text-gray-600">
                  {form.images.length > 0
                    ? `${form.images.length} ảnh sẽ được tải lên`
                    : "Chưa chọn ảnh"}
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">
                  Danh mục
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {selectedCategories.length > 0 ? (
                    selectedCategories.map((category) => (
                      <span
                        key={category.id}
                        className="rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700"
                      >
                        {category.name}
                      </span>
                    ))
                  ) : (
                    <span className="text-sm text-gray-400">Chưa chọn</span>
                  )}
                </div>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">
                  Dịch vụ
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {selectedServices.length > 0 ? (
                    selectedServices.map((service) => (
                      <span
                        key={service.id}
                        className="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700"
                      >
                        {service.name}
                      </span>
                    ))
                  ) : (
                    <span className="text-sm text-gray-400">Chưa chọn</span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
};

export default HostTourForm;
