import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Typography as AntTypography,
} from "antd";
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
    <UIFlex vertical gap="large" ><UIFlex   wrap align="end" justify="space-between" gap={16}><div>
          <AntTypography.Title level={3} >Kiểm duyệt đánh giá</AntTypography.Title>
          <p className="mt-1 text-sm text-gray-500">
            Đánh giá chỉ hiện trên trang tour và được tính vào điểm sau khi duyệt.
          </p>
        </div>{pendingCount > 0 && (
          <span className="rounded-full border border-amber-200 bg-amber-50 px-4 py-1.5 text-sm font-bold text-amber-700">
            {pendingCount} bài đang chờ
          </span>
        )}</UIFlex>{toast && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {toast}
        </div>
      )}<UIFlex   wrap   gap={8}>{TABS.map((tab) => (
          <AntButton type={(statusFilter === tab.key) ? "primary" : "default"} key={tab.key || "all"} onClick={() => setStatusFilter(tab.key)} htmlType="button">{tab.label}{tab.key === "pending" && pendingCount > 0 && ` (${pendingCount})`}</AntButton>
        ))}</UIFlex>{loading ? (
        <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
          <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
        </div>
      ) : reviews.length === 0 ? (
        <UICard  ><UIFlex vertical gap="middle">Không có đánh giá nào trong mục này.
        </UIFlex></UICard>
      ) : (
        <UIFlex vertical gap={16} >{reviews.map((review) => (
            <UICard key={review.id} ><UIFlex vertical gap="middle"><UIFlex   wrap align="start" justify="space-between" gap={12}><div>
                  <UIFlex   wrap align="center"  gap={8}><span className="font-bold text-gray-900">{review.user?.name ?? "Khách hàng"}</span><span className="text-xs text-gray-400">{review.user?.email}</span><span
                      className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${BADGE[review.status]}`}
                    >
                      {review.status_label}
                    </span></UIFlex>
                  <p className="mt-1 text-xs text-gray-500">
                    {review.tour?.title ?? "Tour đã xóa"}
                    {review.created_at && ` · ${formatDateTime(review.created_at)}`}
                  </p>
                </div><UIFlex    align="center"  gap={2}>{[...Array(5)].map((_, i) => (
                    <Star
                      key={i}
                      className={`h-4 w-4 ${i < review.rating ? "fill-amber-400 text-amber-400" : "text-gray-200"}`}
                    />
                  ))}</UIFlex></UIFlex><p className="whitespace-pre-line rounded-lg bg-gray-50 p-4 text-sm leading-relaxed text-gray-700">
                {review.comment}
              </p>{review.moderation_note && (
                <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                  <strong>Lý do từ chối:</strong> {review.moderation_note}
                  {review.moderated_by && ` — ${review.moderated_by}`}
                </p>
              )}{review.reply && (
                <div className="border-l-2 border-primary-200 pl-4">
                  <p className="text-[11px] font-bold text-primary-700">
                    Công ty đã trả lời
                    {review.replied_by && ` · ${review.replied_by}`}
                    {review.replied_at && ` · ${formatDateTime(review.replied_at)}`}
                  </p>
                  <p className="mt-1 whitespace-pre-line text-sm text-gray-700">{review.reply}</p>
                </div>
              )}<div className="flex flex-wrap gap-2 border-t border-gray-100 pt-3">
                {review.status !== "approved" && (
                  <AntButton onClick={() => duyet(review)} disabled={actionLoading} type="primary" htmlType="button"><Check className="h-3.5 w-3.5" />Duyệt
                  </AntButton>
                )}

                {review.status !== "rejected" && (
                  <AntButton onClick={() => {
                      setRejecting(review);
                      setRejectReason("");
                      setActionError("");
                    }} disabled={actionLoading} danger htmlType="button"><X className="h-3.5 w-3.5" />Từ chối
                  </AntButton>
                )}

                {/*
                  Chỉ trả lời được bài đã duyệt: viết câu trả lời dưới một đoạn chữ người ngoài
                  chưa đọc được là vô nghĩa, và nếu sau đó bài bị từ chối thì câu trả lời ấy nói
                  về một thứ không tồn tại.
                */}
                {review.status === "approved" && (
                  <AntButton onClick={() => {
                      setReplying(review);
                      setReplyText(review.reply ?? "");
                      setActionError("");
                    }} disabled={actionLoading} htmlType="button"><MessageSquare className="h-3.5 w-3.5" />{review.reply ? "Sửa câu trả lời" : "Trả lời"}</AntButton>
                )}
              </div></UIFlex></UICard>
          ))}</UIFlex>
      )}<Modal
        isOpen={rejecting !== null}
        onClose={() => setRejecting(null)}
        title="Từ chối đánh giá"
      >
        <UIFlex vertical gap={16} ><p className="text-sm text-gray-600">
            Bài viết không bị xóa. Người viết sẽ đọc được lý do bên dưới ở trang tour.
          </p>{actionError && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {actionError}
            </p>
          )}<AntInput.TextArea rows={3} value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} placeholder="Ví dụ: nội dung chứa số điện thoại quảng cáo, không liên quan tới chuyến đi." style={{ width: "100%" }} /><UIFlex     justify="end" gap={8}><AntButton onClick={() => setRejecting(null)} htmlType="button">Hủy
            </AntButton><AntButton onClick={tuChoi} disabled={actionLoading || rejectReason.trim().length < 10} type="primary" danger htmlType="button">Từ chối
            </AntButton></UIFlex></UIFlex>
      </Modal><Modal
        isOpen={replying !== null}
        onClose={() => setReplying(null)}
        title="Trả lời đánh giá"
      >
        <UIFlex vertical gap={16} ><p className="text-sm text-gray-600">
            Câu trả lời hiện công khai dưới đánh giá. Để trống rồi lưu để gỡ câu trả lời đang có.
          </p>{actionError && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {actionError}
            </p>
          )}<AntInput.TextArea rows={4} value={replyText} onChange={(e) => setReplyText(e.target.value)} placeholder="Cảm ơn bạn đã phản hồi. Về chuyện xe đón muộn, chúng tôi đã..." style={{ width: "100%" }} /><UIFlex     justify="end" gap={8}><AntButton onClick={() => setReplying(null)} htmlType="button">Hủy
            </AntButton><AntButton onClick={luuTraLoi} disabled={actionLoading} type="primary" htmlType="button">Lưu
            </AntButton></UIFlex></UIFlex>
      </Modal></UIFlex>
  );
}
