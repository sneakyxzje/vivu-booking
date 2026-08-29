import { useCallback, useEffect, useState } from "react";
import { Check, Download, Loader2, Mail, RotateCcw } from "lucide-react";
import adminService from "@/services/adminService";
import type { ContactMessage } from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { formatDateTime } from "@/utils/format";

/**
 * Hộp thư liên hệ, và danh sách đăng ký nhận bản tin.
 *
 * Hai thứ ở chung một màn hình vì chúng là hai đầu của cùng một chuyện: những người đã để lại địa
 * chỉ nhưng chưa mua gì. Trước đây cả hai đều là ngõ cụt — trang liên hệ không có form, và bảng
 * người đăng ký nhận tin không có màn hình nào đọc.
 */

const TABS = [
  { key: "inbox", label: "Hộp thư liên hệ" },
  { key: "newsletter", label: "Đăng ký nhận tin" },
] as const;

const layLoi = (err: unknown, macDinh: string) =>
  (err as { response?: { data?: { message?: string } } })?.response?.data?.message || macDinh;

export default function ContactMessages() {
  const [tab, setTab] = useState<(typeof TABS)[number]["key"]>("inbox");

  const [messages, setMessages] = useState<ContactMessage[]>([]);
  const [newCount, setNewCount] = useState(0);
  const [statusFilter, setStatusFilter] = useState("");
  const [loading, setLoading] = useState(true);

  const [subscribers, setSubscribers] = useState<
    { id: number; email: string; created_at: string }[]
  >([]);
  const [subscriberTotal, setSubscriberTotal] = useState(0);
  const [exporting, setExporting] = useState(false);

  const [handling, setHandling] = useState<ContactMessage | null>(null);
  const [note, setNote] = useState("");
  const [actionLoading, setActionLoading] = useState(false);
  const [toast, setToast] = useState("");
  const [error, setError] = useState("");

  const taiHopThu = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getContactMessages(statusFilter);
      setMessages(result?.data ?? []);
      setNewCount(result?.new_count ?? 0);
    } catch (err) {
      console.error("Lỗi tải hộp thư:", err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  const taiNguoiDangKy = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getNewsletterSubscribers();
      setSubscribers(result?.data ?? []);
      setSubscriberTotal(result?.total ?? 0);
    } catch (err) {
      console.error("Lỗi tải danh sách đăng ký:", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (tab === "inbox") taiHopThu();
    else taiNguoiDangKy();
  }, [tab, taiHopThu, taiNguoiDangKy]);

  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(""), 5000);
    return () => clearTimeout(timer);
  }, [toast]);

  const danhDau = async (message: ContactMessage, ghiChu?: string) => {
    setActionLoading(true);
    setError("");

    try {
      setToast(await adminService.toggleContactHandled(message.id, ghiChu));
      setHandling(null);
      setNote("");
      await taiHopThu();
    } catch (err) {
      setError(layLoi(err, "Không cập nhật được trạng thái."));
    } finally {
      setActionLoading(false);
    }
  };

  const xuatCsv = async () => {
    setExporting(true);
    try {
      await adminService.exportNewsletterSubscribers();
    } catch (err) {
      setToast(layLoi(err, "Không tải được tệp."));
    } finally {
      setExporting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Liên hệ &amp; bản tin</h1>
        <p className="mt-1 text-sm text-gray-500">
          Lời nhắn gửi từ trang liên hệ, và những người đã để lại email ở trang chủ.
        </p>
      </div>

      {toast && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {toast}
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`rounded-xl px-4 py-2 text-sm font-semibold transition-colors ${
              tab === t.key
                ? "bg-primary-600 text-white"
                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
            }`}
          >
            {t.label}
            {t.key === "inbox" && newCount > 0 && ` (${newCount})`}
          </button>
        ))}
      </div>

      {tab === "inbox" ? (
        <>
          <div className="flex flex-wrap gap-2">
            {[
              { key: "", label: "Tất cả" },
              { key: "new", label: "Chưa xử lý" },
              { key: "handled", label: "Đã xử lý" },
            ].map((f) => (
              <button
                key={f.key || "all"}
                onClick={() => setStatusFilter(f.key)}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                  statusFilter === f.key
                    ? "bg-gray-900 text-white"
                    : "bg-white text-gray-600 border border-gray-200 hover:bg-gray-50"
                }`}
              >
                {f.label}
              </button>
            ))}
          </div>

          {loading ? (
            <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
              <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
            </div>
          ) : messages.length === 0 ? (
            <div className="rounded-xl border border-gray-100 bg-white py-20 text-center text-sm text-gray-500 shadow-sm">
              Chưa có lời nhắn nào.
            </div>
          ) : (
            <div className="space-y-4">
              {messages.map((m) => (
                <article
                  key={m.id}
                  className={`rounded-xl border bg-white p-5 shadow-sm space-y-3 ${
                    m.status === "new" ? "border-amber-200" : "border-gray-100"
                  }`}
                >
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="font-bold text-gray-900">
                        {m.subject || "(không có tiêu đề)"}
                      </p>
                      <p className="mt-0.5 text-xs text-gray-500">
                        {m.name} · {m.email}
                        {m.phone && ` · ${m.phone}`}
                      </p>
                      {m.created_at && (
                        <p className="text-[11px] text-gray-400">{formatDateTime(m.created_at)}</p>
                      )}
                    </div>

                    <span
                      className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${
                        m.status === "new"
                          ? "border-amber-200 bg-amber-50 text-amber-700"
                          : "border-emerald-200 bg-emerald-50 text-emerald-700"
                      }`}
                    >
                      {m.status === "new" ? "Chưa xử lý" : "Đã xử lý"}
                    </span>
                  </div>

                  <p className="whitespace-pre-line rounded-lg bg-gray-50 p-4 text-sm leading-relaxed text-gray-700">
                    {m.message}
                  </p>

                  {m.handling_note && (
                    <p className="rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-600">
                      <strong>Ghi chú xử lý:</strong> {m.handling_note}
                      {m.handled_by && ` — ${m.handled_by}`}
                    </p>
                  )}

                  <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-3">
                    <a
                      href={`mailto:${m.email}?subject=${encodeURIComponent("Trả lời: " + (m.subject || "lời nhắn của bạn"))}`}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50"
                    >
                      <Mail className="h-3.5 w-3.5" /> Trả lời qua email
                    </a>

                    {m.status === "new" ? (
                      <button
                        onClick={() => {
                          setHandling(m);
                          setNote("");
                          setError("");
                        }}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                      >
                        <Check className="h-3.5 w-3.5" /> Đánh dấu đã xử lý
                      </button>
                    ) : (
                      <button
                        onClick={() => danhDau(m)}
                        disabled={actionLoading}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                      >
                        <RotateCcw className="h-3.5 w-3.5" /> Mở lại
                      </button>
                    )}
                  </div>
                </article>
              ))}
            </div>
          )}
        </>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div>
              <p className="text-2xl font-bold text-gray-900">{subscriberTotal}</p>
              <p className="text-xs text-gray-500">địa chỉ đã đăng ký nhận tin</p>
            </div>
            <button
              onClick={xuatCsv}
              disabled={exporting || subscriberTotal === 0}
              className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50"
            >
              <Download className="h-4 w-4" />
              {exporting ? "Đang tải..." : "Xuất CSV"}
            </button>
          </div>

          {/*
            Nói rõ hệ thống KHÔNG gửi bản tin. Người mở màn này dễ tưởng có nút "gửi thư cho tất
            cả" ở đâu đó, và đi tìm mãi một thứ cố ý không tồn tại.
          */}
          <p className="rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-xs leading-relaxed text-blue-900">
            Hệ thống chỉ thu thập địa chỉ, không tự gửi bản tin. Tải tệp CSV rồi nạp vào công cụ
            gửi thư hàng loạt (Mailchimp, Brevo...) — những công cụ đó đã có sẵn mẫu thư, thống kê
            tỷ lệ mở và nút hủy đăng ký theo đúng quy định.
          </p>

          {loading ? (
            <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
              <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
            </div>
          ) : subscribers.length === 0 ? (
            <div className="rounded-xl border border-gray-100 bg-white py-20 text-center text-sm text-gray-500 shadow-sm">
              Chưa có ai đăng ký nhận tin.
            </div>
          ) : (
            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                  <tr>
                    <th className="px-6 py-3 font-semibold">Email</th>
                    <th className="px-6 py-3 font-semibold">Ngày đăng ký</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {subscribers.map((s) => (
                    <tr key={s.id} className="hover:bg-gray-50/60">
                      <td className="px-6 py-3 font-medium text-gray-800">{s.email}</td>
                      <td className="px-6 py-3 text-xs text-gray-500">
                        {formatDateTime(s.created_at)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      <Modal
        isOpen={handling !== null}
        onClose={() => setHandling(null)}
        title="Đánh dấu đã xử lý"
      >
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Ghi lại đã làm gì với lời nhắn này, để người trực ca sau không gọi lại khách lần nữa.
          </p>

          {error && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {error}
            </p>
          )}

          <textarea
            rows={3}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Ví dụ: đã gọi lại, khách sẽ đặt tour Hạ Long tuần sau."
            className="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          />

          <div className="flex justify-end gap-2">
            <button
              onClick={() => setHandling(null)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            >
              Hủy
            </button>
            <button
              onClick={() => handling && danhDau(handling, note.trim() || undefined)}
              disabled={actionLoading}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
            >
              {actionLoading ? "Đang lưu..." : "Xong"}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
