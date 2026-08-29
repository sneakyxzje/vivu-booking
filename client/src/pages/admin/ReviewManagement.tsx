import { useCallback, useEffect, useState } from "react";
import { Star, Check, X, MessageSquare, Loader2 } from "lucide-react";
import adminService from "@/services/adminService";
import type { AdminReview, AdminReviewStatus } from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { formatDateTime } from "@/utils/format";

/**
 * Hàng đợi kiểm duyệt đánh giá.
 *
 * Đánh giá là chữ của người ngoài in trên trang bán hàng của công ty. Không có bước duyệt thì một
 * dòng chửi bới, một số điện thoại quảng cáo hay một cáo buộc sai sự thật lên thẳng trang tour và
 * ở đó cho tới khi tình cờ có người thấy.
 *
 * Từ chối KHÔNG xóa bài: người viết cần đọc được lý do, và nếu họ khiếu nại thì phải mở lại được
 * đúng dòng chữ đã bị từ chối. Xóa hẳn là quyền của chính người viết.
 */

const TABS: { key: string; label: string }[] = [
  { key: "pending", label: "Chờ duyệt" },
  { key: "approved", label: "Đã duyệt" },
  { key: "rejected", label: "Đã từ chối" },
  { key: "", label: "Tất cả" },
];

const BADGE: Record<AdminReviewStatus, string> = {
  pending: "bg-amber-50 text-amber-700 border-amber-200",
  approved: "bg-emerald-50 text-emerald-700 border-emerald-200",
  rejected: "bg-rose-50 text-rose-700 border-rose-200",
};

const layLoi = (err: unknown, macDinh: string) =>
  (err as { response?: { data?: { message?: string } } })?.response?.data?.message || macDinh;

export default function ReviewManagement() {
  const [reviews, setReviews] = useState<AdminReview[]>([]);
  const [pendingCount, setPendingCount] = useState(0);
  const [statusFilter, setStatusFilter] = useState("pending");
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState("");

  const [rejecting, setRejecting] = useState<AdminReview | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [replying, setReplying] = useState<AdminReview | null>(null);
  const [replyText, setReplyText] = useState("");
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState("");

  const taiDanhSach = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getReviews(statusFilter);
      setReviews(result?.data ?? []);
      setPendingCount(result?.pending_count ?? 0);
    } catch (err) {
      console.error("Lỗi tải danh sách đánh giá:", err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    taiDanhSach();
  }, [taiDanhSach]);

  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(""), 5000);
    return () => clearTimeout(timer);
  }, [toast]);

  const duyet = async (review: AdminReview) => {
    setActionLoading(true);
    try {
      setToast(await adminService.approveReview(review.id));
      await taiDanhSach();
    } catch (err) {
      setToast(layLoi(err, "Không duyệt được đánh giá."));
    } finally {
      setActionLoading(false);
    }
  };

  const tuChoi = async () => {
    if (!rejecting) return;

    setActionLoading(true);
    setActionError("");

    try {
      setToast(await adminService.rejectReview(rejecting.id, rejectReason.trim()));
      setRejecting(null);
      setRejectReason("");
      await taiDanhSach();
    } catch (err) {
      setActionError(layLoi(err, "Không từ chối được đánh giá."));
    } finally {
      setActionLoading(false);
    }
  };

  const luuTraLoi = async () => {
    if (!replying) return;

    setActionLoading(true);
    setActionError("");

    try {
      setToast(await adminService.replyToReview(replying.id, replyText.trim()));
      setReplying(null);
      setReplyText("");
      await taiDanhSach();
    } catch (err) {
      setActionError(layLoi(err, "Không lưu được câu trả lời."));
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Kiểm duyệt đánh giá</h1>
          <p className="mt-1 text-sm text-gray-500">
            Đánh giá chỉ hiện trên trang tour và được tính vào điểm sau khi duyệt.
          </p>
        </div>
        {pendingCount > 0 && (
          <span className="rounded-full border border-amber-200 bg-amber-50 px-4 py-1.5 text-sm font-bold text-amber-700">
            {pendingCount} bài đang chờ
          </span>
        )}
      </div>

      {toast && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {toast}
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        {TABS.map((tab) => (
          <button
            key={tab.key || "all"}
            onClick={() => setStatusFilter(tab.key)}
            className={`rounded-xl px-4 py-2 text-sm font-semibold transition-colors ${
              statusFilter === tab.key
                ? "bg-primary-600 text-white"
                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
            }`}
          >
            {tab.label}
            {tab.key === "pending" && pendingCount > 0 && ` (${pendingCount})`}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
          <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
        </div>
      ) : reviews.length === 0 ? (
        <div className="rounded-xl border border-gray-100 bg-white py-20 text-center text-sm text-gray-500 shadow-sm">
          Không có đánh giá nào trong mục này.
        </div>
      ) : (
        <div className="space-y-4">
          {reviews.map((review) => (
            <article
              key={review.id}
              className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm space-y-3"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-bold text-gray-900">{review.user?.name ?? "Khách hàng"}</span>
                    <span className="text-xs text-gray-400">{review.user?.email}</span>
                    <span
                      className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${BADGE[review.status]}`}
                    >
                      {review.status_label}
                    </span>
                  </div>
                  <p className="mt-1 text-xs text-gray-500">
                    {review.tour?.title ?? "Tour đã xóa"}
                    {review.created_at && ` · ${formatDateTime(review.created_at)}`}
                  </p>
                </div>

                <div className="flex items-center gap-0.5">
                  {[...Array(5)].map((_, i) => (
                    <Star
                      key={i}
                      className={`h-4 w-4 ${i < review.rating ? "fill-amber-400 text-amber-400" : "text-gray-200"}`}
                    />
                  ))}
                </div>
              </div>

              <p className="whitespace-pre-line rounded-lg bg-gray-50 p-4 text-sm leading-relaxed text-gray-700">
                {review.comment}
              </p>

              {review.moderation_note && (
                <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                  <strong>Lý do từ chối:</strong> {review.moderation_note}
                  {review.moderated_by && ` — ${review.moderated_by}`}
                </p>
              )}

              {review.reply && (
                <div className="border-l-2 border-primary-200 pl-4">
                  <p className="text-[11px] font-bold text-primary-700">
                    Công ty đã trả lời
                    {review.replied_by && ` · ${review.replied_by}`}
                    {review.replied_at && ` · ${formatDateTime(review.replied_at)}`}
                  </p>
                  <p className="mt-1 whitespace-pre-line text-sm text-gray-700">{review.reply}</p>
                </div>
              )}

              <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-3">
                {review.status !== "approved" && (
                  <button
                    onClick={() => duyet(review)}
                    disabled={actionLoading}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                  >
                    <Check className="h-3.5 w-3.5" /> Duyệt
                  </button>
                )}

                {review.status !== "rejected" && (
                  <button
                    onClick={() => {
                      setRejecting(review);
                      setRejectReason("");
                      setActionError("");
                    }}
                    disabled={actionLoading}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 disabled:opacity-50"
                  >
                    <X className="h-3.5 w-3.5" /> Từ chối
                  </button>
                )}

                {/*
                  Chỉ trả lời được bài đã duyệt: viết câu trả lời dưới một đoạn chữ người ngoài
                  chưa đọc được là vô nghĩa, và nếu sau đó bài bị từ chối thì câu trả lời ấy nói
                  về một thứ không tồn tại.
                */}
                {review.status === "approved" && (
                  <button
                    onClick={() => {
                      setReplying(review);
                      setReplyText(review.reply ?? "");
                      setActionError("");
                    }}
                    disabled={actionLoading}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    <MessageSquare className="h-3.5 w-3.5" />
                    {review.reply ? "Sửa câu trả lời" : "Trả lời"}
                  </button>
                )}
              </div>
            </article>
          ))}
        </div>
      )}

      <Modal
        isOpen={rejecting !== null}
        onClose={() => setRejecting(null)}
        title="Từ chối đánh giá"
      >
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Bài viết không bị xóa. Người viết sẽ đọc được lý do bên dưới ở trang tour.
          </p>
          {actionError && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {actionError}
            </p>
          )}
          <textarea
            rows={3}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            placeholder="Ví dụ: nội dung chứa số điện thoại quảng cáo, không liên quan tới chuyến đi."
            className="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          />
          <div className="flex justify-end gap-2">
            <button
              onClick={() => setRejecting(null)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            >
              Hủy
            </button>
            <button
              onClick={tuChoi}
              disabled={actionLoading || rejectReason.trim().length < 10}
              className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50"
            >
              Từ chối
            </button>
          </div>
        </div>
      </Modal>

      <Modal
        isOpen={replying !== null}
        onClose={() => setReplying(null)}
        title="Trả lời đánh giá"
      >
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Câu trả lời hiện công khai dưới đánh giá. Để trống rồi lưu để gỡ câu trả lời đang có.
          </p>
          {actionError && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {actionError}
            </p>
          )}
          <textarea
            rows={4}
            value={replyText}
            onChange={(e) => setReplyText(e.target.value)}
            placeholder="Cảm ơn bạn đã phản hồi. Về chuyện xe đón muộn, chúng tôi đã..."
            className="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          />
          <div className="flex justify-end gap-2">
            <button
              onClick={() => setReplying(null)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            >
              Hủy
            </button>
            <button
              onClick={luuTraLoi}
              disabled={actionLoading}
              className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50"
            >
              Lưu
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
