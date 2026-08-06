import { Link } from "react-router-dom";
import type { Tour } from "@/types";
import {
  MapPinIcon,
  ClockIcon,
  StarIcon,
  ChevronRightIcon,
} from "@/components/Icons";

const formatPrice = (value: number): string =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(value);

interface StarRatingProps {
  rating: number;
  reviewCount: number;
}

const StarRating: React.FC<StarRatingProps> = ({ rating, reviewCount }) => (
  <div className="flex items-center gap-1 text-sm">
    <div className="flex text-amber-400">
      {Array.from({ length: 5 }).map((_, i) => (
        <StarIcon key={i} className="w-4 h-4" filled={i < Math.floor(rating)} />
      ))}
    </div>
    <span className="font-bold text-gray-700 text-xs ml-1">{rating}</span>
    <span className="text-gray-400 text-xs">({reviewCount} đánh giá)</span>
  </div>
);

const PriceDisplay: React.FC<{ price: number }> = ({ price }) => (
  <div>
    <div className="text-gray-400 text-[11px] font-medium">Giá từ</div>
    <div className="text-primary-600 font-bold text-[16px] tracking-tight">
      {formatPrice(price)}
    </div>
    <div className="text-gray-400 text-[11px] font-medium">/ khách người lớn</div>
  </div>
);

interface TourCardProps {
  tour: Tour;
}

export const TourCard: React.FC<TourCardProps> = ({ tour }) => {
  const categoryName =
    tour.categories && tour.categories[0]
      ? tour.categories[0].name
      : "Tour Trọn Gói";

  const rating = tour.rating ?? null;
  const reviewCount = tour.review_count ?? 0;
  const adultPrice = tour.adult_price ?? tour.discount_price ?? tour.price;

  return (
    <article className="group bg-white rounded-xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex flex-col h-full">
      <div className="relative h-52 overflow-hidden bg-gray-100 shrink-0">
        <img
          src={
            tour.thumbnail ||
            "https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80"
          }
          alt={tour.title}
          loading="lazy"
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        />

        <div className="absolute top-4 left-4 z-10 flex flex-col gap-2">
          {tour.is_featured && (
            <span className="bg-amber-500 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg shadow-md">
              ★ Bán chạy
            </span>
          )}
        </div>

        <div className="absolute bottom-4 left-4 z-10">
          <span className="bg-black/50 backdrop-blur-md text-white text-xs font-medium px-3 py-1.5 rounded-xl flex items-center gap-1 border border-white/10">
            <MapPinIcon className="w-3.5 h-3.5 text-white shrink-0" />
            {tour.start_location}
          </span>
        </div>

        <div className="absolute bottom-4 right-4 z-10">
          <span className="bg-primary-600/90 text-white text-xs font-semibold px-3 py-1.5 rounded-xl shadow-md border border-primary-500/20 flex items-center gap-1">
            <ClockIcon className="w-3.5 h-3.5 text-white shrink-0" />
            {tour.number_of_days} Ngày
            {tour.number_of_nights > 0 ? ` ${tour.number_of_nights} Đêm` : ""}
          </span>
        </div>
      </div>
      <div className="p-5 flex flex-col flex-1">
        <span className="text-[11px] font-bold text-primary-600 uppercase tracking-wider mb-2 block">
          {categoryName}
        </span>

        <Link to={`/tours/${tour.id}`} className="block mb-2">
          <h3 className="font-bold text-gray-900 text-[15px] leading-snug line-clamp-2 group-hover:text-primary-600 transition-colors h-[42px]">
            {tour.title}
          </h3>
        </Link>

        <div className="mb-4">
          {rating !== null && reviewCount > 0 ? (
            <StarRating rating={rating} reviewCount={reviewCount} />
          ) : (
            <span className="text-xs text-gray-400">Chưa có đánh giá</span>
          )}
        </div>

        {tour.services && tour.services.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-5 mt-auto">
            {tour.services.slice(0, 2).map((srv) => (
              <span
                key={srv.id}
                className="bg-gray-100 text-gray-600 text-[10px] font-medium px-2 py-0.5 rounded-md"
              >
                ✓ {srv.name}
              </span>
            ))}
            {tour.services.length > 2 && (
              <span className="text-gray-400 text-[10px] self-center">
                +{tour.services.length - 2}
              </span>
            )}
          </div>
        )}

        <div className="border-t border-gray-100 my-4 shrink-0" />

        <div className="flex items-center justify-between mt-auto shrink-0">
          <PriceDisplay price={adultPrice} />

          <Link
            to={`/tours/${tour.id}`}
            className="bg-primary-50 hover:bg-primary-600 text-primary-600 hover:text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all duration-300 flex items-center gap-1"
          >
            Đặt ngay
            <ChevronRightIcon className="w-3.5 h-3.5" />
          </Link>
        </div>
      </div>
    </article>
  );
};

export default TourCard;