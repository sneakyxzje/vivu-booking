import React, { useEffect, useState } from "react";
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
  category_ids: [] as number[],
  service_ids: [] as number[],
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
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      try {
        const formData = await hostService.getFormData();
        setCategories(formData.categories);
        setServices(formData.services);

        if (isEdit && id) {
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
            category_ids: tour.categories?.map((c) => c.id) ?? [],
            service_ids: tour.services?.map((s) => s.id) ?? [],
          });
        }
      } catch {
        setError("Không thể tải dữ liệu form.");
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [id, isEdit]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (error) setError("");
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
      <div className="flex items-center justify-center h-64 text-gray-500">
        Đang tải...
      </div>
    );
  }

  return (
    <div className="max-w-3xl animate-fade-in">
      <div className="mb-6">
        <Link
          to="/host/tours"
          className="text-sm text-primary-600 hover:underline"
        >
          ← Quay lại danh sách tour
        </Link>
        <h1 className="text-2xl font-bold text-gray-900 mt-2">
          {isEdit ? "Sửa tour" : "Tạo tour mới"}
        </h1>
        <p className="text-gray-500 text-sm mt-1">
          Tour mới sẽ ở trạng thái <strong>chờ duyệt</strong> sau khi gửi.
        </p>
      </div>

      <form
        onSubmit={handleSubmit}
        className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6"
      >
        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
            {error}
          </div>
        )}

        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-2">
            Tiêu đề tour <span className="text-red-500">*</span>
          </label>
          <input
            name="title"
            required
            value={form.title}
            onChange={handleChange}
            placeholder="VD: Tour Hạ Long 2N1Đ"
            className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-2">
            Mô tả
          </label>
          <textarea
            name="description"
            rows={4}
            value={form.description}
            onChange={handleChange}
            className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Giá gốc (VND) <span className="text-red-500">*</span>
            </label>
            <input
              name="price"
              type="number"
              min={0}
              required
              value={form.price}
              onChange={handleChange}
              className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Giá giảm (VND)
            </label>
            <input
              name="discount_price"
              type="number"
              min={0}
              value={form.discount_price}
              onChange={handleChange}
              className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Số ngày <span className="text-red-500">*</span>
            </label>
            <input
              name="number_of_days"
              type="number"
              min={1}
              required
              value={form.number_of_days}
              onChange={handleChange}
              className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Số đêm <span className="text-red-500">*</span>
            </label>
            <input
              name="number_of_nights"
              type="number"
              min={0}
              required
              value={form.number_of_nights}
              onChange={handleChange}
              className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Điểm khởi hành <span className="text-red-500">*</span>
            </label>
            <input
              name="start_location"
              required
              value={form.start_location}
              onChange={handleChange}
              className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Điểm kết thúc
            </label>
            <input
              name="end_location"
              value={form.end_location}
              onChange={handleChange}
              className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-2">
            Ảnh thumbnail (URL)
          </label>
          <input
            name="thumbnail"
            type="url"
            value={form.thumbnail}
            onChange={handleChange}
            placeholder="https://..."
            className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500"
          />
        </div>

        {categories.length > 0 && (
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Danh mục
            </label>
            <div className="flex flex-wrap gap-3">
              {categories.map((c) => (
                <label
                  key={c.id}
                  className="flex items-center gap-2 text-sm text-gray-700"
                >
                  <input
                    type="checkbox"
                    checked={form.category_ids.includes(c.id)}
                    onChange={() => toggleId("category_ids", c.id)}
                    className="rounded border-gray-300 text-primary-600"
                  />
                  {c.name}
                </label>
              ))}
            </div>
          </div>
        )}

        {services.length > 0 && (
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Dịch vụ đi kèm
            </label>
            <div className="flex flex-wrap gap-3">
              {services.map((s) => (
                <label
                  key={s.id}
                  className="flex items-center gap-2 text-sm text-gray-700"
                >
                  <input
                    type="checkbox"
                    checked={form.service_ids.includes(s.id)}
                    onChange={() => toggleId("service_ids", s.id)}
                    className="rounded border-gray-300 text-primary-600"
                  />
                  {s.name}
                </label>
              ))}
            </div>
          </div>
        )}

        <div className="flex gap-3 pt-2">
          <button
            type="submit"
            disabled={submitting}
            className="flex-1 bg-primary-600 text-white font-semibold py-3 rounded-xl hover:bg-primary-700 disabled:opacity-50 transition-colors"
          >
            {submitting
              ? "Đang lưu..."
              : isEdit
                ? "Cập nhật tour"
                : "Gửi tour chờ duyệt"}
          </button>
          <Link
            to="/host/tours"
            className="px-6 py-3 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 text-center"
          >
            Hủy
          </Link>
        </div>
      </form>
    </div>
  );
};

export default HostTourForm;
