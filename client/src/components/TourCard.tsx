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

/*
 * Sao màu mực, không phải vàng.
 *
 * Quy tắc lấy từ DESIGN.md: trong ngữ cảnh du lịch, sao vàng trông rẻ tiền. Điểm đánh giá là
 * tín hiệu tin cậy cao nhất trên thẻ, nên nó được đọc bằng độ đậm của chữ chứ không bằng màu.
 */
const StarRating: React.FC<StarRatingProps> = ({ rating, reviewCount }) => (
  <div className="flex items-center gap-1.5">
    <div className="flex text-ink">
      {Array.from({ length: 5 }).map((_, i) => (
        <StarIcon key={i} className="w-3.5 h-3.5" filled={i < Math.floor(rating)} />
      ))}
    </div>
    <span className="text-body-sm font-semibold text-ink">{rating}</span>
    <span className="text-body-sm text-muted">({reviewCount} đánh giá)</span>
  </div>
);

const PriceDisplay: React.FC<{ price: number }> = ({ price }) => (
  <div>
    <div className="text-caption-sm text-muted">Giá từ</div>
    <div className="text-display-sm text-primary-600">{formatPrice(price)}</div>
    <div className="text-caption-sm text-muted">/ khách người lớn</div>
  </div>
);

interface TourCardProps {
  tour: Tour;
}

/**
 * Thẻ tour — tương đương `property-card` của DESIGN.md.
 *
 * Ảnh dẫn dắt, bo 14px, phẳng cho tới khi rê chuột rồi mới nổi lên đúng **một** tầng bóng của
 * hệ. Bỏ hiệu ứng nhấc thẻ lên (`-translate-y`) vì hệ này chỉ đổi độ nổi, không đổi vị trí —
 * thẻ nhúc nhích khi rê chuột làm cả lưới thẻ rung theo con trỏ.
 */
export const TourCard: React.FC<TourCardProps> = ({ tour }) => {
  const categoryName =
    tour.categories && tour.categories[0]
      ? tour.categories[0].name
      : "Tour Trọn Gói";

  const rating = tour.rating ?? null;
  const reviewCount = tour.review_count ?? 0;
  const adultPrice = tour.adult_price;

  return (
    <article className="group card-surface card-hover overflow-hidden flex flex-col h-full">
      <div className="relative h-52 overflow-hidden bg-surface-strong shrink-0">
        <img
          src={
            tour.thumbnail ||
            "https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80"
          }
          alt={tour.title}
          loading="lazy"
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        />

        {/* Huy hiệu nổi trên ảnh: viên trắng, đúng tầng bóng của hệ — kiểu "guest favorite". */}
        {tour.is_featured && (
          <div className="absolute top-4 left-4 z-10">
            <span className="badge-pill bg-canvas text-ink shadow-float">Bán chạy</span>
          </div>
        )}

        <div className="absolute bottom-4 left-4 right-4 z-10 flex items-center justify-between gap-2">
          <span className="badge-pill bg-black/55 backdrop-blur-md text-white">
            <MapPinIcon className="w-3 h-3 shrink-0" />
            {tour.start_location}
          </span>

          <span className="badge-pill bg-canvas text-ink shadow-float">
            <ClockIcon className="w-3 h-3 shrink-0" />
            {tour.number_of_days} Ngày
            {tour.number_of_nights > 0 ? ` ${tour.number_of_nights} Đêm` : ""}
          </span>
        </div>
      </div>

      <div className="p-5 flex flex-col flex-1">
        <span className="tag-upper text-primary-600 px-0 mb-2">{categoryName}</span>

        <Link to={`/tours/${tour.slug}`} className="block mb-2">
          <h3 className="text-title-md text-ink line-clamp-2 group-hover:text-primary-600 transition-colors h-[42px]">
            {tour.title}
          </h3>
        </Link>

        <div className="mb-4">
          {rating !== null && reviewCount > 0 ? (
            <StarRating rating={rating} reviewCount={reviewCount} />
          ) : (
            <span className="text-body-sm text-muted-soft">Chưa có đánh giá</span>
          )}
        </div>

        {tour.services && tour.services.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-5 mt-auto">
            {tour.services.slice(0, 2).map((srv) => (
              <span
                key={srv.id}
                className="badge-pill bg-surface-strong text-body"
              >
                {srv.name}
              </span>
            ))}
            {tour.services.length > 2 && (
              <span className="text-badge text-muted-soft self-center">
                +{tour.services.length - 2}
              </span>
            )}
          </div>
        )}

        <div className="border-t border-hairline-soft my-4 shrink-0" />

        <div className="flex items-end justify-between gap-3 mt-auto shrink-0">
          <PriceDisplay price={adultPrice} />

          <Link
            to={`/tours/${tour.slug}`}
            className="btn-pill bg-primary-50 text-primary-600 hover:bg-primary-600 hover:text-white shrink-0"
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
