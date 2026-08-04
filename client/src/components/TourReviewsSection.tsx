import React, { useState } from "react";
import { Star, CheckCircle2, ThumbsUp, MessageSquare, Filter } from "lucide-react";

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

const mockReviews: Review[] = [
  {
    id: 1,
    userName: "Nguyễn Hoàng Nam",
    userAvatar: "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=120",
    rating: 5,
    date: "15/07/2026",
    comment: "Chuyến đi tuyệt vời! Khách sạn 4 sao sạch đẹp, ăn uống phong phú hải sản rất tươi. Hướng dẫn viên anh Nam cực kỳ nhiệt tình và am hiểu văn hóa địa phương. Xe du lịch đời mới đi êm ái, bác tài vui tính.",
    verifiedBooking: true,
    likes: 12,
  },
  {
    id: 2,
    userName: "Trần Thị Mai Anh",
    userAvatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120",
    rating: 5,
    date: "02/07/2026",
    comment: "Lịch trình hợp lý, không bị gấp gáp. Cả gia đình mình có trẻ nhỏ và người lớn tuổi nhưng ai cũng hài lòng. Rất cảm ơn Vivu Booking đã chu đáo sắp xếp phòng liền kề cho gia đình.",
    verifiedBooking: true,
    likes: 8,
  },
  {
    id: 3,
    userName: "Lê Minh Tuấn",
    userAvatar: "https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=120",
    rating: 4,
    date: "20/06/2026",
    comment: "Tour tổ chức chuyên nghiệp, xe đón đúng giờ. Điểm trừ nhẹ là ngày thứ 2 thời tiết hơi nắng nhưng HDV đã linh hoạt điều chỉnh cho đoàn nghỉ ngơi hợp lý. 9/10 điểm!",
    verifiedBooking: true,
    likes: 5,
  },
];

export const TourReviewsSection: React.FC<{ tourTitle?: string }> = () => {
  const [reviews, setReviews] = useState<Review[]>(mockReviews);
  const [activeFilter, setActiveFilter] = useState<number | "all">("all");
  
  // New review form
  const [newRating, setNewRating] = useState(5);
  const [newComment, setNewComment] = useState("");
  const [newUserName, setNewUserName] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);

  const filteredReviews = reviews.filter((r) => {
    if (activeFilter === "all") return true;
    return r.rating === activeFilter;
  });

  const handleAddReview = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newComment.trim()) return;

    setSubmitting(true);
    setTimeout(() => {
      const created: Review = {
        id: Date.now(),
        userName: newUserName.trim() || "Khách hàng Vivu",
        rating: newRating,
        date: new Date().toLocaleDateString("vi-VN"),
        comment: newComment,
        verifiedBooking: true,
        likes: 0,
      };
      setReviews([created, ...reviews]);
      setNewComment("");
      setNewUserName("");
      setSubmitting(false);
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 4000);
    }, 600);
  };

  const handleLike = (id: number) => {
    setReviews((prev) =>
      prev.map((r) => (r.id === id ? { ...r, likes: r.likes + 1 } : r))
    );
  };

  return (
    <div className="bg-white rounded-3xl p-6 md:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.015)] space-y-8">
      
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
        <p className="text-xs text-gray-500 mt-1">100% đánh giá từ các khách hàng đã tham gia tour thực tế</p>
      </div>

      {/* RATING SUMMARY DASHBOARD */}
      <div className="bg-gray-50/80 rounded-2xl p-6 border border-gray-200/60 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        
        {/* Overall Score */}
        <div className="text-center md:border-r border-gray-200/80 pr-0 md:pr-6">
          <div className="text-4xl md:text-5xl font-extrabold text-gray-900 font-plus-jakarta">4.8</div>
          <div className="flex items-center justify-center gap-1 my-2">
            {[1, 2, 3, 4, 5].map((s) => (
              <Star key={s} className="w-5 h-5 fill-amber-400 text-amber-400" />
            ))}
          </div>
          <span className="text-xs font-medium text-gray-500">Dựa trên {reviews.length} đánh giá đã xác thực</span>
        </div>

        {/* Rating Breakdown Bars */}
        <div className="md:col-span-2 space-y-2 text-xs">
          {[
            { star: 5, pct: "85%", count: 18 },
            { star: 4, pct: "10%", count: 2 },
            { star: 3, pct: "5%", count: 1 },
            { star: 2, pct: "0%", count: 0 },
            { star: 1, pct: "0%", count: 0 },
          ].map((bar) => (
            <div key={bar.star} className="flex items-center gap-3">
              <span className="font-semibold text-gray-700 w-10 flex items-center gap-1">
                {bar.star} <Star className="w-3 h-3 fill-amber-400 text-amber-400 inline" />
              </span>
              <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-amber-400 rounded-full transition-all duration-500"
                  style={{ width: bar.pct }}
                />
              </div>
              <span className="text-gray-400 w-8 text-right">{bar.pct}</span>
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
          <div key={rev.id} className="bg-gray-50/50 rounded-2xl p-5 border border-gray-100 space-y-3">
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
      <div className="bg-gray-50 rounded-2xl p-6 border border-gray-200/80 space-y-4">
        <h3 className="text-sm font-bold text-gray-900 flex items-center gap-2 uppercase tracking-wider">
          <MessageSquare className="w-4 h-4 text-primary-600" /> Viết nhận xét về tour này
        </h3>

        {showSuccess ? (
          <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600" /> Cảm ơn bạn đã gửi đánh giá! Nhận xét của bạn đã được hiển thị.
          </div>
        ) : (
          <form onSubmit={handleAddReview} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Họ và tên người đánh giá</label>
                <input
                  type="text"
                  placeholder="Ví dụ: Nguyễn Văn A..."
                  value={newUserName}
                  onChange={(e) => setNewUserName(e.target.value)}
                  className="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                  required
                />
              </div>

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
