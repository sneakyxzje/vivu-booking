import tourService from "@/services/tourService";
import type { Tour, TourImage, TourSchedule } from "@/types";
import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import { StarIcon, ChevronRightIcon } from "@/components/Icons";
import { TourLeftDetails } from "./TourLeftDetails";
import { TourRightSidebar } from "./TourRightSidebar";

export default function TourDetail() {
  const { id } = useParams();
  const [tour, setTour] = useState<Tour | null>(null);
  const [loading, setLoading] = useState(true);
  const [selectedSchedule, setSelectedSchedule] = useState<TourSchedule | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        if (!id) return;
        const res = await tourService.getById(id);
        setTour(res.data);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [id]);

  useEffect(() => {
    if (tour && tour.schedules && tour.schedules.length > 0) {
      const firstBookable =
        tour.schedules.find((schedule) => {
          const availableSlots = schedule.max_people - schedule.booked_people;
          const isDeadlineOverdue = schedule.booking_deadline
            ? new Date(schedule.booking_deadline) < new Date()
            : false;

          return ["open", "active"].includes(schedule.status) && availableSlots > 0 && !isDeadlineOverdue;
        }) || tour.schedules[0];

      setSelectedSchedule(firstBookable);
    }
  }, [tour]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col justify-center items-center gap-4">
        <div className="w-12 h-12 rounded-full border-4 border-primary-100 border-t-primary-600 animate-spin"></div>
        <p className="text-gray-500 font-medium animate-pulse text-sm">
          Đang tải thông tin chi tiết tour...
        </p>
      </div>
    );
  }

  if (!tour) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col justify-center items-center gap-4">
        <svg
          className="w-16 h-16 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={1.5}
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
        <h3 className="text-xl font-bold text-gray-900">Không tìm thấy Tour</h3>
        <p className="text-gray-500 text-sm">
          Vui lòng kiểm tra lại đường dẫn hoặc quay về trang chủ.
        </p>
        <Link
          to="/tours"
          className="mt-2 px-5 py-2.5 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition-colors text-sm font-semibold shadow-md"
        >
          Xem danh sách Tour
        </Link>
      </div>
    );
  }

  const allImages = [
    tour.thumbnail,
    ...(tour.images?.map((img: TourImage) => img.image_path) || []),
  ].filter(Boolean) as string[];

  const renderGallery = () => {
    if (allImages.length === 0) return null;

    if (allImages.length === 1) {
      return (
        <div className="relative h-[320px] md:h-[480px] rounded-xl overflow-hidden shadow-sm border border-gray-100 group">
          <img
            src={allImages[0]}
            alt={tour.title}
            className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
          />
        </div>
      );
    }

    if (allImages.length === 2) {
      return (
        <div className="grid grid-cols-2 gap-3 h-[240px] md:h-[380px] rounded-xl overflow-hidden shadow-sm border border-gray-100">
          {allImages.map((img, idx) => (
            <div key={idx} className="relative overflow-hidden group h-full">
              <img
                src={img}
                alt=""
                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
              />
            </div>
          ))}
        </div>
      );
    }

    if (allImages.length === 3 || allImages.length === 4) {
      return (
        <div className="grid grid-cols-3 gap-3 h-[280px] md:h-[420px] rounded-xl overflow-hidden shadow-sm border border-gray-100">
          <div className="col-span-2 relative overflow-hidden group h-full">
            <img
              src={allImages[0]}
              alt=""
              className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
            />
          </div>
          <div className="col-span-1 grid grid-rows-2 gap-3 h-full">
            {allImages.slice(1, 3).map((img, idx) => (
              <div key={idx} className="relative overflow-hidden group h-full">
                <img
                  src={img}
                  alt=""
                  className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                />
              </div>
            ))}
          </div>
        </div>
      );
    }

    return (
      <div className="grid grid-cols-4 grid-rows-2 gap-3 h-[300px] md:h-[480px] rounded-xl overflow-hidden shadow-sm border border-gray-100">
        <div className="col-span-2 row-span-2 relative overflow-hidden group h-full">
          <img
            src={allImages[0]}
            alt={tour.title}
            className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
          />
        </div>
        {allImages.slice(1, 5).map((img, idx) => (
          <div
            key={idx}
            className="col-span-1 row-span-1 relative overflow-hidden group h-full"
          >
            <img
              src={img}
              alt=""
              className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
            />
          </div>
        ))}
      </div>
    );
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-16 font-inter">
      {/* Breadcrumb Navigation */}
      <div className="max-w-[1280px] mx-auto px-4 py-4 sm:px-6">
        <nav className="flex items-center gap-2 text-xs md:text-sm text-gray-500 font-medium">
          <Link to="/" className="hover:text-primary-600 transition-colors">
            Trang chủ
          </Link>
          <ChevronRightIcon className="w-3.5 h-3.5 text-gray-300" />
          <Link to="/tours" className="hover:text-primary-600 transition-colors">
            Tour trọn gói
          </Link>
          <ChevronRightIcon className="w-3.5 h-3.5 text-gray-300" />
          <span className="text-gray-900 truncate max-w-[200px] md:max-w-xs font-medium">
            {tour.title}
          </span>
        </nav>
      </div>

      {/* Header Info */}
      <div className="max-w-[1280px] mx-auto px-4 pb-6 sm:px-6">
        <div className="flex flex-wrap items-center gap-2 mb-3">
          {tour.categories?.map((cat) => (
            <span
              key={cat.id}
              className="bg-primary-50 text-primary-700 text-xs font-semibold px-2.5 py-1 rounded-full border border-primary-100"
            >
              {cat.name}
            </span>
          ))}
          {tour.is_featured && (
            <span className="bg-amber-50 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full border border-amber-100 flex items-center gap-1">
              <StarIcon className="w-3 h-3 text-amber-500" /> Nổi bật
            </span>
          )}
        </div>

        <h1 className="text-2xl md:text-4xl font-bold text-gray-900 tracking-tight leading-tight font-plus-jakarta">
          {tour.title}
        </h1>

        {/* Rating and Quick Stats */}
        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 mt-4 text-sm text-gray-600 border-b border-gray-200/60 pb-6">
          <div className="flex items-center gap-1">
            {tour.rating != null && (tour.review_count ?? 0) > 0 ? (
              <>
                <span className="flex items-center text-amber-500">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <StarIcon
                      key={star}
                      className={`w-4 h-4 ${star <= Math.round(tour.rating ?? 0) ? "" : "opacity-25"}`}
                    />
                  ))}
                </span>
                <span className="font-bold text-gray-900 ml-1">{tour.rating}</span>
                <span className="text-gray-400 font-mono">({tour.review_count} đánh giá)</span>
              </>
            ) : (
              <span className="text-gray-400">Chưa có đánh giá</span>
            )}
          </div>

          <div className="flex items-center gap-1.5">
            <svg
              className="w-4 h-4 text-gray-400 shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
              />
            </svg>
            <span>
              Khởi hành: <strong>{tour.start_location}</strong>
            </span>
          </div>

          {tour.end_location && (
            <div className="flex items-center gap-1.5">
              <svg
                className="w-4 h-4 text-gray-400 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L16 4m0 13V4m0 0L9 7"
                />
              </svg>
              <span>
                Điểm đến: <strong>{tour.end_location}</strong>
              </span>
            </div>
          )}
        </div>
      </div>

      {/* Modern Photo Grid */}
      <div className="max-w-[1280px] mx-auto px-4 pb-10 sm:px-6">
        {renderGallery()}
      </div>

      {/* Main Layout Grid */}
      <div className="max-w-[1280px] mx-auto px-4 sm:px-6">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          {/* Left Details Component */}
          <TourLeftDetails tour={tour} selectedSchedule={selectedSchedule} />

          {/* Right Sidebar Component */}
          <TourRightSidebar
            tour={tour}
            selectedSchedule={selectedSchedule}
            onScheduleChange={setSelectedSchedule}
          />

        </div>
      </div>
    </div>
  );
}
