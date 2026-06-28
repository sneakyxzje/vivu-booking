
import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";

const formatPrice = (value: number | string) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(Number(value));

export default function TourDetail() {
  const { id } = useParams();
  const [tour, setTour] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`http://localhost:8000/api/tours/${id}`)
      .then((res) => res.json())
      .then((res) => {
        setTour(res.data);
      })
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) {
    return (
      <div className="min-h-screen flex justify-center items-center">
        Đang tải...
      </div>
    );
  }

  if (!tour) {
    return (
      <div className="min-h-screen flex justify-center items-center">
        Không tìm thấy tour
      </div>
    );
  }

  return (
    <div className="bg-slate-50 min-h-screen">
      {/* Banner */}
      <section className="max-w-7xl mx-auto px-4">
        <div className="relative h-[550px] rounded-3xl overflow-hidden">
          <img
          // chua co data luu anh nen t fix cung
            src="https://images.unsplash.com/photo-1511895426328-dc8714191300"
            alt={tour.title}
            className="w-full h-full object-cover"
          />

          <div className="absolute inset-0 bg-black/50" />

          <div className="absolute bottom-10 left-0 right-0">
            <div className="px-6">
              <h1 className="text-5xl font-bold text-white mb-4">
                {tour.title}
              </h1>

              <div className="flex flex-wrap gap-4 text-white">
                <span>{tour.start_location}</span>

                <span>
                  {tour.number_of_days} ngày {tour.number_of_nights} đêm
                </span>

                <span>
                  {tour.is_featured ? "Tour nổi bật" : "Tour thường"}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>
      

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 py-10">
        <div className="grid lg:grid-cols-3 gap-8">
          {/* Left */}
          <div className="lg:col-span-2 space-y-8">
            {/* Giới thiệu */}
            <div className="bg-white rounded-3xl p-8 shadow-sm">
              <h2 className="text-2xl font-bold mb-5">
                Giới thiệu tour
              </h2>

              <p className="text-gray-700 leading-8">
                {tour.description}
              </p>
            </div>

            {/* Hình ảnh */}
            <div className="bg-white rounded-3xl p-8 shadow-sm">
              <h2 className="text-2xl font-bold mb-6">
                Hình ảnh tour
              </h2>

              <div className="grid md:grid-cols-3 gap-4">
                {tour.images?.map((image: any) => (
                  <img
                    key={image.id}
                    // src={`http://localhost:8000/storage/${image.image_path}`}
                    src="https://images.unsplash.com/photo-1511895426328-dc8714191300"
                    alt=""
                    className="h-56 w-full rounded-2xl object-cover"
                  />
                ))}
              </div>
            </div>

            {/* Lịch trình */}
            <div className="bg-white rounded-3xl p-8 shadow-sm">
              <h2 className="text-2xl font-bold mb-8">
                Lịch trình tour
              </h2>

              <div className="space-y-6">
                {tour.itineraries?.map((item: any) => (
                  <div
                    key={item.id}
                    className="flex gap-4"
                  >
                    <div className="w-12 h-12 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold shrink-0">
                      {item.day_number}
                    </div>

                    <div>
                      <h3 className="font-bold text-lg">
                        {item.title}
                      </h3>

                      <p className="text-gray-600 mt-2">
                        {item.content}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Dịch vụ */}
            {tour.services?.length > 0 && (
              <div className="bg-white rounded-3xl p-8 shadow-sm">
                <h2 className="text-2xl font-bold mb-6">
                  Dịch vụ bao gồm
                </h2>

                <div className="grid md:grid-cols-2 gap-4">
                  {tour.services.map((service: any) => (
                    <div
                      key={service.id}
                      className="bg-green-50 border border-green-100 rounded-xl p-4"
                    >
                      ✓ {service.name}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Right */}
          <div>
            <div className="sticky top-24 bg-white rounded-3xl shadow-xl p-6">
              <div className="text-gray-400 line-through">
                {tour.discount_price &&
                  formatPrice(tour.price)}
              </div>

              <div className="text-red-600 text-4xl font-black">
                {formatPrice(
                  tour.discount_price || tour.price
                )}
              </div>

              <div className="mt-6">
                <label className="block mb-2 font-medium">
                  Ngày khởi hành
                </label>

                <select className="w-full border rounded-xl p-3">
                  {tour.schedules?.map((schedule: any) => (
                    <option key={schedule.id}>
                      {schedule.start_date}
                    </option>
                  ))}
                </select>
              </div>

              <button className="w-full mt-6 bg-primary-600 hover:bg-primary-700 text-white py-4 rounded-xl font-bold">
                Đặt tour ngay
              </button>

              <div className="mt-6 border-t pt-6 text-sm text-gray-500">
                <p>✓ Xác nhận nhanh</p>
                <p>✓ Hỗ trợ 24/7</p>
                <p>✓ Giá tốt nhất</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
