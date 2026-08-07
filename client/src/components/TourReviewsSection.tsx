
import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Star, CheckCircle2, ThumbsUp, MessageSquare, Filter } from "lucide-react";
import tourService from "@/services/tourService";
import { useAuth } from "@/hooks/useAuth";


interface Review {
  id: number;
  userName: string;
  userAvatar?: string;
  rating: number;
  date: string;
  comment: string;
  verifiedBooking: boolean;
  likes: number;
}

export const TourReviewsSection: React.FC<{
  tourId: number;
  tourTitle?: string;
}> = ({ tourId }) => {
  const [reviews, setReviews] = useState<Review[]>([]);

  const [activeFilter, setActiveFilter] = useState<number | "all">("all");
  
  // New review form
  const { user } = useAuth();
  const [newRating, setNewRating] = useState(5);
  const [newComment, setNewComment] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);
  const [submitError, setSubmitError] = useState("");

  const loadReviews = async () => {
  try {
    const res = await tourService.getReviews(tourId);

    const list = Array.isArray(res)
      ? res
      : res.data || [];

    setReviews(
      list.map((item: any) => ({
        id: item.id,
        userName: item.user?.name ?? "Khách hàng",
        userAvatar: item.user?.avatar,
        rating: item.rating,
        date: new Date(item.created_at).toLocaleDateString("vi-VN"),
        comment: item.comment,
        verifiedBooking: true,
        likes: item.likes ?? 0,
      }))
    );
  } catch (err) {
    console.error(err);
  }
};
useEffect(() => {
  loadReviews();
}, [tourId]);
  const filteredReviews = reviews.filter((r) => {
    if (activeFilter === "all") return true;
    return r.rating === activeFilter;
  });

  const averageRating = reviews.length
    ? reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length
    : 0;
  const ratingBreakdown = [5, 4, 3, 2, 1].map((star) => {
    const count = reviews.filter((r) => r.rating === star).length;
    const pct = reviews.length ? Math.round((count / reviews.length) * 100) : 0;
    return { star, count, pct };
  });

  const handleAddReview = async (e: React.FormEvent) => {
  e.preventDefault();

  console.log("tourId =", tourId);

  if (!newComment.trim()) return;

  try {
    setSubmitting(true);
    setSubmitError("");
    await tourService.review(tourId, {
      rating: newRating,
      comment: newComment,
    });

    setNewComment("");
    setNewRating(5);

    setShowSuccess(true);

    loadReviews();

    setTimeout(() => {
      setShowSuccess(false);
    }, 3000);
  } catch (error) {
    const response = (error as { response?: { data?: { message?: string } } }).response?.data;
    setSubmitError(response?.message ?? "Không thể gửi đánh giá. Vui lòng thử lại.");
  } finally {
    setSubmitting(false);
  }
};

  const handleLike = (id: number) => {
    setReviews((prev) =>
      prev.map((r) => (r.id === id ? { ...r, likes: r.likes + 1 } : r))
    );
  };

  return (
    <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm space-y-8">
      
      {/* Header */}
      <div>
        <div className="flex items-center gap-2">
          <span className="bg-amber-50 text-amber-700 text-xs font-bold px-3 py-1 rounded-full border border-amber-200 uppercase tracking-wider flex items-center gap-1">
            <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" /> Đánh giá thực tế
          </span>
        </div>
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta mt-2">
          Đánh giá & Bình luận từ du khách
        </h2>
        <p className="text-xs text-gray-500 mt-1">Đánh giá từ khách hàng đã trải nghiệm dịch vụ của Vivu Booking</p>
      </div>

      {/* RATING SUMMARY DASHBOARD */}
      <div className="bg-gray-50/80 rounded-lg p-6 border border-gray-200/60 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        
        {/* Overall Score */}
        <div className="text-center md:border-r border-gray-200/80 pr-0 md:pr-6">
          <div className="text-4xl md:text-5xl font-bold text-gray-900 font-plus-jakarta">
            {reviews.length ? averageRating.toFixed(1) : "—"}
          </div>
          <div className="flex items-center justify-center gap-1 my-2">
            {[1, 2, 3, 4, 5].map((s) => (
              <Star
                key={s}
                className={`w-5 h-5 ${s <= Math.round(averageRating) ? "fill-amber-400 text-amber-400" : "text-gray-300"}`}
              />
            ))}
          </div>
          <span className="text-xs font-medium text-gray-500">
            {reviews.length ? `Dựa trên ${reviews.length} đánh giá` : "Chưa có đánh giá nào"}
          </span>
        </div>

        {/* Rating Breakdown Bars */}
        <div className="md:col-span-2 space-y-2 text-xs">
          {ratingBreakdown.map((bar) => (
            <div key={bar.star} className="flex items-center gap-3">
              <span className="font-semibold text-gray-700 w-10 flex items-center gap-1">
                {bar.star} <Star className="w-3 h-3 fill-amber-400 text-amber-400 inline" />
              </span>
              <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-amber-400 rounded-full transition-all duration-500"
                  style={{ width: `${bar.pct}%` }}
                />
              </div>
              <span className="text-gray-400 w-8 text-right">{bar.pct}%</span>
            </div>
          ))}
        </div>
      </div>

      {/* FILTER TABS */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1">
        <span className="text-xs font-semibold text-gray-500 flex items-center gap-1 mr-2">
          <Filter className="w-3.5 h-3.5" /> Lọc theo:
        </span>
        {[
          { key: "all", label: "Tất cả" },
          { key: 5, label: "5 sao (⭐ 5)" },
          { key: 4, label: "4 sao (⭐ 4)" },
          { key: 3, label: "3 sao (⭐ 3)" },
        ].map((f) => (
          <button
            key={String(f.key)}
            onClick={() => setActiveFilter(f.key as any)}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${
              activeFilter === f.key
                ? "bg-primary-600 text-white shadow-xs"
                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* REVIEWS LIST */}
      <div className="space-y-4">
        {filteredReviews.map((rev) => (
          <div key={rev.id} className="bg-gray-50/50 rounded-lg p-5 border border-gray-100 space-y-3">
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-primary-100 text-primary-700 font-bold flex items-center justify-center overflow-hidden border border-primary-200">
                  {rev.userAvatar ? (
                    <img src={rev.userAvatar} alt={rev.userName} className="w-full h-full object-cover" />
                  ) : (
                    rev.userName.charAt(0).toUpperCase()
                  )}
                </div>
                <div>
                  <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                    {rev.userName}
                    {rev.verifiedBooking && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                        <CheckCircle2 className="w-3 h-3 text-emerald-600" /> Đã tham gia tour
                      </span>
                    )}
                  </h4>
                  <span className="text-[11px] text-gray-400">{rev.date}</span>
                </div>
              </div>

              <div className="flex items-center gap-1">
                {[...Array(5)].map((_, idx) => (
                  <Star
                    key={idx}
                    className={`w-4 h-4 ${
                      idx < rev.rating ? "fill-amber-400 text-amber-400" : "text-gray-200"
                    }`}
                  />
                ))}
              </div>
            </div>

            <p className="text-xs sm:text-sm text-gray-700 leading-relaxed pl-1">{rev.comment}</p>

            <div className="flex items-center gap-4 pt-2 text-xs text-gray-400 border-t border-gray-200/40">
              <button
                onClick={() => handleLike(rev.id)}
                className="hover:text-primary-600 flex items-center gap-1.5 transition-colors font-medium"
              >
                <ThumbsUp className="w-3.5 h-3.5" /> Hữu ích ({rev.likes})
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* WRITE A REVIEW FORM */}
      <div className="bg-gray-50 rounded-lg p-6 border border-gray-200/80 space-y-4">
        <h3 className="text-sm font-bold text-gray-900 flex items-center gap-2 uppercase tracking-wider">
          <MessageSquare className="w-4 h-4 text-primary-600" /> Viết nhận xét về tour này
        </h3>

        {!user ? (
          <div className="p-4 bg-white border border-gray-200 rounded-xl text-xs text-gray-600 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span>Bạn cần đăng nhập bằng tài khoản đã đặt tour này để viết đánh giá.</span>
            <Link
              to="/login"
              className="inline-flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition-colors shrink-0"
            >
              Đăng nhập để đánh giá
            </Link>
          </div>
        ) : showSuccess ? (
          <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600" /> Cảm ơn bạn đã gửi đánh giá! Nhận xét của bạn đã được hiển thị.
          </div>
        ) : (
          <form onSubmit={handleAddReview} className="space-y-4">
            {submitError && (
              <div className="p-3.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-semibold">
                {submitError}
              </div>
            )}

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">Đánh giá số sao</label>
              <div className="flex items-center gap-1 pt-1">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setNewRating(star)}
                    className="p-1 hover:scale-110 transition-transform"
                  >
                    <Star
                      className={`w-6 h-6 ${
                        star <= newRating ? "fill-amber-400 text-amber-400" : "text-gray-300"
                      }`}
                    />
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">Nội dung bình luận</label>
              <textarea
                rows={3}
                placeholder="Chia sẻ trải nghiệm thực tế của bạn về khách sạn, hướng dẫn viên, món ăn..."
                value={newComment}
                onChange={(e) => setNewComment(e.target.value)}
                className="w-full p-3.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                required
              />
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs rounded-xl shadow-xs transition-colors disabled:opacity-50"
            >
              {submitting ? "Đang gửi nhận xét..." : "Gửi đánh giá ngay"}
            </button>
          </form>
        )}
      </div>

    </div>
  );
};

export default TourReviewsSection;
