import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Table as AntTable,
  Typography as AntTypography,
} from "antd";
import { useCallback, useEffect, useState } from "react";
import adminService from "@/services/adminService";
import type {
  ChangeRequest,
  ChangeRequestDetail,
  ChangeRequestStatus,
} from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * F06 - Điều hành duyệt yêu cầu hủy của khách.
 *
 * Duyệt ở đây là quyết định chi tiền, nên màn này phải nói đủ để người bấm chịu trách nhiệm
 * được: khách nhận bao nhiêu, chỗ có về kho không, và yêu cầu này còn duyệt được hay chuyến đã
 * khởi hành trong lúc nằm chờ.
 *
 * Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.
 */

const STATUS_TABS: { key: string; label: string }[] = [
  { key: "pending", label: "Chờ duyệt" },
  { key: "approved", label: "Đã duyệt" },
  { key: "rejected", label: "Đã từ chối" },
  { key: "all", label: "Tất cả" },
];

const STATUS_BADGE: Record<
  ChangeRequestStatus,
  { label: string; className: string }
> = {
  pending: {
    label: "Chờ duyệt",
    className: "bg-amber-50 text-amber-700 border-amber-200",
  },
  approved: {
    label: "Đã duyệt",
    className: "bg-emerald-50 text-emerald-700 border-emerald-200",
  },
  rejected: {
    label: "Đã từ chối",
    className: "bg-rose-50 text-rose-700 border-rose-200",
  },
  cancelled_by_customer: {
    label: "Khách đã rút",
    className: "bg-gray-100 text-gray-600 border-gray-200",
  },
};

const soTien = (value: string | number | null | undefined) =>
  formatPrice(Number(value ?? 0));

export default function ChangeRequestManagement() {
  const [requests, setRequests] = useState<ChangeRequest[]>([]);
  const [pendingCount, setPendingCount] = useState(0);
  const [statusFilter, setStatusFilter] = useState("pending");
  const [loading, setLoading] = useState(true);

  const [detail, setDetail] = useState<ChangeRequestDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [reviewNote, setReviewNote] = useState("");
  const [rejectMode, setRejectMode] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState("");
  const [toast, setToast] = useState("");

  const taiDanhSach = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getChangeRequests(statusFilter);
      setRequests(result?.requests?.data ?? []);
      setPendingCount(result?.pending_count ?? 0);
    } catch (err) {
      console.error("Lỗi tải danh sách yêu cầu:", err);
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

  const moChiTiet = async (id: number) => {
    setDetailLoading(true);
    setReviewNote("");
    setRejectMode(false);
    setActionError("");

    try {
      setDetail(await adminService.getChangeRequest(id));
    } catch (err) {
      console.error("Lỗi tải chi tiết yêu cầu:", err);
    } finally {
      setDetailLoading(false);
    }
  };

  const dongChiTiet = () => {
    setDetail(null);
    setReviewNote("");
    setRejectMode(false);
    setActionError("");
  };

  const layLoi = (err: unknown, macDinh: string) =>
    (err as { response?: { data?: { message?: string } } })?.response?.data
      ?.message || macDinh;

  const duyet = async () => {
    if (!detail) return;

    setActionLoading(true);
    setActionError("");

    try {
      setToast(
        await adminService.approveChangeRequest(
          detail.request.id,
          reviewNote.trim(),
        ),
      );
      dongChiTiet();
      taiDanhSach();
    } catch (err) {
      setActionError(layLoi(err, "Không duyệt được yêu cầu."));
    } finally {
      setActionLoading(false);
    }
  };

  const tuChoi = async () => {
    if (!detail || reviewNote.trim().length < 10) return;

    setActionLoading(true);
    setActionError("");

    try {
      setToast(
        await adminService.rejectChangeRequest(
          detail.request.id,
          reviewNote.trim(),
        ),
      );
      dongChiTiet();
      taiDanhSach();
    } catch (err) {
      setActionError(layLoi(err, "Không từ chối được yêu cầu."));
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {toast && (
        <div className="fixed top-20 right-4 z-50 max-w-md px-4 py-3 rounded-xl shadow-xl text-sm font-semibold text-white bg-emerald-600">
          {toast}
        </div>
      )}

      <UICard  ><UIFlex vertical gap="middle"><AntTypography.Title level={3} >Yêu cầu huỷ
        </AntTypography.Title><p className="text-sm text-gray-500 mt-1">
          Danh sách các yêu cầu huỷ đơn đặt tour của khách.
        </p>{pendingCount > 0 && (
          <p className="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-xs font-bold text-amber-800">
            {pendingCount} yêu cầu đang chờ xử lý
          </p>
        )}</UIFlex></UICard>

      <UIFlex   wrap   gap={8}>{STATUS_TABS.map((tab) => (
          <AntButton type={(statusFilter === tab.key) ? "primary" : "default"} key={tab.key} htmlType="button" onClick={() => setStatusFilter(tab.key)}>{tab.label}</AntButton>
        ))}</UIFlex>

      <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center space-y-3">
            <div className="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto" />
            <p className="text-xs text-gray-500">
              Đang tải danh sách yêu cầu...
            </p>
          </div>
        ) : requests.length === 0 ? (
          <p className="p-12 text-center text-sm text-gray-500">
            Không có yêu cầu nào trong mục này.
          </p>
        ) : (
          <div className="overflow-x-auto">
            <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={requests.map((item) => {
                  const badge = STATUS_BADGE[item.status];

                  return (
                    {key: item.id, cells: [<>
                        BK-{item.booking_id}
                        <span className="block font-sans font-normal text-gray-500 mt-0.5">
                          {item.booking?.tour?.title ?? ""}
                        </span>
                      </>,<>
                        {item.booking?.customer_name}
                      </>,<>
                        {formatDateTime(item.created_at)}
                      </>,<>
                        {soTien(item.estimated_refund)}
                        <span className="block font-normal text-gray-500">
                          {item.estimated_refund_percent}%
                        </span>
                      </>,<>
                        <span
                          className={`inline-flex px-2.5 py-1 rounded-lg border text-[11px] font-bold ${badge.className}`}
                        >
                          {badge.label}
                        </span>
                      </>,<>
                        <AntButton htmlType="button" onClick={() => moChiTiet(item.id)} type="primary">Xem
                        </AntButton>
                      </>], rowProps: {}}
                  );
                })}
    columns={[{ key: "0", title: <>Đơn</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Khách</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Gửi lúc</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Hoàn dự kiến</>, align: "left", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Trạng thái</>, align: "left", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Thao tác</>, align: "left", render: (_value, record) => record.cells[5] }]}
    onRow={(record) => record.rowProps}
     />
          </div>
        )}
      </div>

      <Modal
        isOpen={!!detail || detailLoading}
        onClose={dongChiTiet}
        size="2xl"
        title={`Yêu cầu hủy đơn BK-${detail?.request.booking_id ?? ""}`}
        subtitle={detail ? formatDateTime(detail.request.created_at) : ""}
      >
        {detailLoading && <p className="text-sm text-gray-500">Đang tải...</p>}

        {detail && (
          <UIFlex vertical gap={20} ><div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-700 space-y-1.5">
              <p>
                <span className="text-gray-500">Tour:</span>{" "}
                <strong className="text-gray-900">
                  {detail.request.booking?.tour?.title}
                </strong>
              </p>
              <p>
                <span className="text-gray-500">Khách:</span>{" "}
                <strong className="text-gray-900">
                  {detail.request.booking?.customer_name}
                </strong>
                {detail.request.booking?.customer_email
                  ? ` — ${detail.request.booking.customer_email}`
                  : ""}
              </p>
              <p>
                <span className="text-gray-500">Lý do khách nêu:</span>{" "}
                <strong className="text-gray-900">
                  {detail.request.request_note}
                </strong>
              </p>
            </div>{/* Chuyến khởi hành trong lúc yêu cầu nằm chờ thì không duyệt được nữa. */}{!detail.can_approve && detail.blocked_reason && (
              <div className="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3">
                <p className="text-sm font-bold text-rose-700">
                  Không duyệt được yêu cầu này
                </p>
                <p className="text-xs text-rose-700 mt-0.5">
                  {detail.blocked_reason}
                </p>
              </div>
            )}{/*
              Một con số duy nhất.
              Mức hoàn đã chốt lúc khách gửi và không có đường nào đổi được, nên hiện thêm mức
              tính lại tại thời điểm xem chỉ làm người duyệt phân vân giữa hai số. Muốn biết
              duyệt nhanh hay chậm thì đối chiếu ngày gửi với ngày duyệt, đều đã lưu sẵn.
            */}<div className="rounded-lg border border-emerald-200 bg-emerald-50/70 p-5">
              <p className="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">
                Sẽ hoàn cho khách
              </p>
              <p className="text-2xl font-extrabold text-emerald-800 mt-1">
                {soTien(detail.request.estimated_refund)}
              </p>
              <p className="text-[11px] text-emerald-700 mt-1">
                {detail.request.estimated_refund_percent}% giá trị đơn, chốt lúc
                khách gửi ngày {formatDateTime(detail.request.created_at)}
              </p>
            </div>{detail.seats_will_be_released ? (
              <p className="text-xs text-gray-600">
                Duyệt xong chỗ sẽ được trả về kho và chuyến bán tiếp được ngay.
              </p>
            ) : (
              <p className="rounded-lg bg-rose-100 px-4 py-3 text-xs font-semibold text-rose-800">
                Đơn này đã qua hạn chốt danh sách. Duyệt xong{" "}
                <strong>chỗ không quay lại kho</strong>, nó thành ghế chết và
                chỉ mở bán lại được bằng tay.
              </p>
            )}{detail.request.status === "pending" ? (
              <>
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1.5">
                    Ghi chú xử lý{" "}
                    {rejectMode && <span className="text-rose-500">*</span>}
                  </label>
                  <AntInput.TextArea rows={2} value={reviewNote} onChange={(e) => setReviewNote(e.target.value)} placeholder={
                      rejectMode
                        ? "Bắt buộc khi từ chối, tối thiểu 10 ký tự. Khách sẽ đọc được lý do này."
                        : "Không bắt buộc khi duyệt."
                    } style={{ width: "100%" }} />
                </div>

                {actionError && (
                  <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    {actionError}
                  </div>
                )}

                <UIFlex    align="center" justify="end" gap={8}>{rejectMode ? (
                    <>
                      <AntButton htmlType="button" onClick={() => setRejectMode(false)} disabled={actionLoading}>Quay lại
                      </AntButton>
                      <AntButton htmlType="button" onClick={tuChoi} disabled={
                          actionLoading || reviewNote.trim().length < 10
                        } type="primary" danger>{actionLoading ? "Đang xử lý..." : "Xác nhận từ chối"}</AntButton>
                    </>
                  ) : (
                    <>
                      <AntButton htmlType="button" onClick={() => setRejectMode(true)} disabled={actionLoading} danger>Từ chối
                      </AntButton>
                      <AntButton htmlType="button" onClick={duyet} disabled={actionLoading || !detail.can_approve} type="primary">{actionLoading ? "Đang xử lý..." : "Duyệt và hủy đơn"}</AntButton>
                    </>
                  )}</UIFlex>
              </>
            ) : (
              <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-700 space-y-1">
                <p className="font-bold text-gray-900">
                  {STATUS_BADGE[detail.request.status].label}
                  {detail.request.reviewed_at
                    ? ` lúc ${formatDateTime(detail.request.reviewed_at)}`
                    : ""}
                </p>
                {detail.request.review_note && (
                  <p>{detail.request.review_note}</p>
                )}
              </div>
            )}</UIFlex>
        )}
      </Modal>
    </div>
  );
}
