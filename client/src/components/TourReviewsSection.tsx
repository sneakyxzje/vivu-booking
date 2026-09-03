import React, { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Star, CheckCircle2, MessageSquare, Clock, XCircle, CornerDownRight } from "lucide-react";
import tourService from "@/services/tourService";
import type { TourReview, TourReviewSummary } from "@/services/tourService";
import { useAuth } from "@/hooks/useAuth";
import { formatDate } from "@/utils/format";

const SUMMARY_RONG: TourReviewSummary = {
  total: 0,
  average: null,
  breakdown: [5, 4, 3, 2, 1].map((star) => ({ star, count: 0, percent: 0 })),
};

/**
 * Đánh giá của một tour.
 *
 * Ba điều đáng nói về màn hình này:
 *
 * - **Phổ điểm lấy từ máy chủ**, không cộng lại từ danh sách đang hiện. Danh sách chỉ là một
 *   trang; cộng tại chỗ thì tour có 130 đánh giá lại báo "dựa trên 10 đánh giá".
 * - **Người viết thấy bài của chính mình** kèm nhãn chờ duyệt hoặc lý do bị từ chối. Người khác
 *   chỉ thấy bài đã duyệt.
 * - **Không còn nút "Hữu ích"**. Nó từng đếm trong bộ nhớ trình duyệt và về 0 sau mỗi lần tải
 *   lại — một con số trông như dữ liệu nhưng không phải, và không ai khác nhìn thấy.
 */
export const TourReviewsSection: React.FC<{
  tourId: number;
  tourTitle?: string;
}> = ({ tourId }) => {
  const { user } = useAuth();

  const [reviews, setReviews] = useState<TourReview[]>([]);
  const [summary, setSummary] = useState<TourReviewSummary>(SUMMARY_RONG);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);

  const [newRating, setNewRating] = useState(5);
  const [newComment, setNewComment] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");
  const [submitError, setSubmitError] = useState("");

  const loadReviews = useCallback(
    async (trang: number, noiTiep: boolean) => {
      setLoading(true);
      try {
        const res = await tourService.getReviews(tourId, { page: trang });
        // "Xem thêm" nối vào cuối; tải lại sau khi gửi bài thì thay hẳn danh sách.
        setReviews((cu) => (noiTiep ? [...cu, ...res.data] : res.data));
        setSummary(res.summary ?? SUMMARY_RONG);
        setPage(res.meta.current_page);
        setLastPage(res.meta.last_page);
      } finally {
        setLoading(false);
      }
    },
    [tourId],
  );

  useEffect(() => {
    loadReviews(1, false);
  }, [loadReviews]);

  const handleAddReview = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!newComment.trim()) return;

    try {
      setSubmitting(true);
      setSubmitError("");

      const res = await tourService.review(tourId, {
        rating: newRating,
        comment: newComment.trim(),
      });

      setNewComment("");
      setNewRating(5);
      // Dùng chính câu máy chủ trả về: nó là chỗ biết bài vừa gửi đang chờ duyệt hay không.
      setSuccessMessage(res?.message ?? "Đã gửi đánh giá.");

      await loadReviews(1, false);
      setTimeout(() => setSuccessMessage(""), 6000);
    } catch (error) {
      const response = (error as { response?: { data?: { message?: string } } }).response?.data;
      setSubmitError(response?.message ?? "Không thể gửi đánh giá. Vui lòng thử lại.");
    } finally {
      setSubmitting(false);
    }
  };

  const trungBinh = summary.average ?? 0;

  return (
    <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm space-y-8">
      <div>
        <div className="flex items-center gap-2">
          <span className="bg-amber-50 text-amber-700 text-xs font-bold px-3 py-1 rounded-full border border-amber-200 uppercase tracking-wider flex items-center gap-1">
            <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" /> Đánh giá thực tế
          </span>
        </div>
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta mt-2">
          Đánh giá &amp; Bình luận từ du khách
        </h2>
        <p className="text-xs text-gray-500 mt-1">
          Chỉ khách đã đi xong tour này mới viết được đánh giá.
        </p>
      </div>

      {/* Tổng kết điểm, lấy từ máy chủ trên toàn bộ đánh giá đã duyệt */}
      <div className="bg-gray-50/80 rounded-lg p-6 border border-gray-200/60 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        <div className="text-center md:border-r border-gray-200/80 pr-0 md:pr-6">
          <div className="text-4xl md:text-5xl font-bold text-gray-900 font-plus-jakarta">
            {summary.total ? trungBinh.toFixed(1) : "—"}
          </div>
          <div className="flex items-center justify-center gap-1 my-2">
            {[1, 2, 3, 4, 5].map((s) => (
              <Star
                key={s}
                className={`w-5 h-5 ${s <= Math.round(trungBinh) ? "fill-amber-400 text-amber-400" : "text-gray-300"}`}
              />
            ))}
          </div>
          <span className="text-xs font-medium text-gray-500">
            {summary.total ? `Dựa trên ${summary.total} đánh giá` : "Chưa có đánh giá nào"}
          </span>
        </div>

        <div className="md:col-span-2 space-y-2 text-xs">
          {summary.breakdown.map((bar) => (
            <div key={bar.star} className="flex items-center gap-3">
              <span className="font-semibold text-gray-700 w-10 flex items-center gap-1">
                {bar.star} <Star className="w-3 h-3 fill-amber-400 text-amber-400 inline" />
              </span>
              <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-amber-400 rounded-full transition-all duration-500"
                  style={{ width: `${bar.percent}%` }}
                />
              </div>
              <span className="text-gray-400 w-12 text-right">{bar.count}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="space-y-4">
        {reviews.length === 0 && !loading && (
          <p className="text-sm text-gray-400 text-center py-6">
            Chưa có đánh giá nào cho tour này. Bạn có thể là người đầu tiên sau khi đi về.
          </p>
        )}

        {reviews.map((rev) => (
          <div
            key={rev.id}
            className={`rounded-lg p-5 border space-y-3 ${
              rev.is_mine && rev.status !== "approved"
                ? "bg-amber-50/40 border-amber-200"
                : "bg-gray-50/50 border-gray-100"
            }`}
          >
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-primary-100 text-primary-700 font-bold flex items-center justify-center overflow-hidden border border-primary-200">
                  {rev.user?.avatar ? (
                    <img src={rev.user.avatar} alt={rev.user.name} className="w-full h-full object-cover" />
                  ) : (
                    (rev.user?.name ?? "K").charAt(0).toUpperCase()
                  )}
                </div>
                <div>
                  <h4 className="font-bold text-gray-900 text-sm flex flex-wrap items-center gap-2">
                    {rev.user?.name ?? "Khách hàng"}
                    {/*
                      Huy hiệu này nói đúng sự thật: máy chủ chỉ nhận đánh giá từ đơn đã hoàn tất,
                      nên mọi bài hiện ở đây đều của người đã đi. Trước kia nó được gắn cứng cho
                      mọi bài, kể cả khi luật còn cho phép đánh giá trước ngày khởi hành.
                    */}
                    <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                      <CheckCircle2 className="w-3 h-3 text-emerald-600" /> Đã tham gia tour
                    </span>
                    {rev.is_mine && rev.status === "pending" && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 bg-amber-100 border border-amber-300 px-2 py-0.5 rounded-full">
                        <Clock className="w-3 h-3" /> Chờ duyệt — chỉ bạn thấy
                      </span>
                    )}
                    {rev.is_mine && rev.status === "rejected" && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-rose-700 bg-rose-100 border border-rose-300 px-2 py-0.5 rounded-full">
                        <XCircle className="w-3 h-3" /> Không được đăng
                      </span>
                    )}
                  </h4>
                  <span className="text-[11px] text-gray-400">
                    {formatDate(rev.created_at)}
                  </span>
                </div>
              </div>

              <div className="flex items-center gap-1 shrink-0">
                {[...Array(5)].map((_, idx) => (
                  <Star
                    key={idx}
                    className={`w-4 h-4 ${idx < rev.rating ? "fill-amber-400 text-amber-400" : "text-gray-200"}`}
                  />
                ))}
              </div>
            </div>

            <p className="text-xs sm:text-sm text-gray-700 leading-relaxed pl-1">{rev.comment}</p>

            {rev.is_mine && rev.moderation_note && (
              <p className="text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-lg p-3">
                <strong>Lý do không được đăng:</strong> {rev.moderation_note}
              </p>
            )}

            {rev.reply && (
              <div className="ml-4 sm:ml-8 border-l-2 border-primary-200 pl-4 py-2 bg-white rounded-r-lg">
                <p className="text-[11px] font-bold text-primary-700 flex items-center gap-1.5">
                  <CornerDownRight className="w-3.5 h-3.5" />
                  Vivu Booking phản hồi
                  {rev.replied_at && (
                    <span className="font-normal text-gray-400">
                      · {formatDate(rev.replied_at)}
                    </span>
                  )}
                </p>
                <p className="text-xs sm:text-sm text-gray-700 leading-relaxed mt-1.5">{rev.reply}</p>
              </div>
            )}
          </div>
        ))}

        {page < lastPage && (
          <button
            onClick={() => loadReviews(page + 1, true)}
            disabled={loading}
            className="w-full py-3 rounded-xl border border-gray-200 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
          >
            {loading ? "Đang tải..." : `Xem thêm đánh giá (còn ${summary.total - reviews.length})`}
          </button>
        )}
      </div>

      <div className="bg-gray-50 rounded-lg p-6 border border-gray-200/80 space-y-4">
        <h3 className="text-sm font-bold text-gray-900 flex items-center gap-2 uppercase tracking-wider">
          <MessageSquare className="w-4 h-4 text-primary-600" /> Viết nhận xét về tour này
        </h3>

        {!user ? (
          <div className="p-4 bg-white border border-gray-200 rounded-xl text-xs text-gray-600 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span>Bạn cần đăng nhập bằng tài khoản đã đi tour này để viết đánh giá.</span>
            <Link
              to="/login"
              className="inline-flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition-colors shrink-0"
            >
              Đăng nhập để đánh giá
            </Link>
          </div>
        ) : successMessage ? (
          <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-start gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
            {successMessage}
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
                    aria-label={`${star} sao`}
                    className="p-1 hover:scale-110 transition-transform"
                  >
                    <Star
                      className={`w-6 h-6 ${star <= newRating ? "fill-amber-400 text-amber-400" : "text-gray-300"}`}
                    />
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">Nội dung bình luận</label>
              <textarea
                rows={3}
                minLength={10}
                placeholder="Chia sẻ trải nghiệm thực tế của bạn về khách sạn, hướng dẫn viên, món ăn..."
                value={newComment}
                onChange={(e) => setNewComment(e.target.value)}
                className="w-full p-3.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                required
              />
              <p className="mt-1 text-[11px] text-gray-400">
                Ít nhất 10 ký tự. Nhận xét hiện công khai sau khi được duyệt.
              </p>
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs rounded-xl shadow-xs transition-colors disabled:opacity-50"
            >
              {submitting ? "Đang gửi nhận xét..." : "Gửi đánh giá"}
            </button>
          </form>
        )}
      </div>
    </div>
  );
};

export default TourReviewsSection;
