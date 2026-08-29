import React, { useState } from "react";
import { Link } from "react-router-dom";
import { CheckCircle2, Mail, MapPin, Phone } from "lucide-react";
import api from "@/services/api";
import { useDocumentMeta } from "@/hooks/useDocumentMeta";

/**
 * Trang liên hệ, có form thật.
 *
 * Trước đây `/contact` trỏ vào cùng một trang chữ tĩnh với `/about`: một số điện thoại, một địa
 * chỉ email, không ô nào để gõ. Ai muốn hỏi phải tự mở ứng dụng thư của mình ra, và phần lớn thì
 * thôi không hỏi nữa.
 *
 * Không đòi đăng nhập: người viết vào đây phần lớn là người CHƯA đặt gì và đang cân nhắc.
 */
export const ContactPage: React.FC = () => {
  useDocumentMeta({
    title: "Liên hệ",
    description:
      "Gửi câu hỏi tới bộ phận điều hành Vivu Booking, hoặc gọi tổng đài 1900 1234 từ 8:00 đến 21:00 hằng ngày.",
  });

  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    subject: "",
    message: "",
  });
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState("");
  const [error, setError] = useState("");

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => {
    setForm((cu) => ({ ...cu, [e.target.name]: e.target.value }));
    if (error) setError("");
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setError("");

    try {
      const res = await api.post("/contact", {
        ...form,
        phone: form.phone.trim() || null,
        subject: form.subject.trim() || null,
      });
      setSent(res.data?.message ?? "Đã gửi lời nhắn.");
      setForm({ name: "", email: "", phone: "", subject: "", message: "" });
    } catch (err) {
      const axiosErr = err as { response?: { data?: { message?: string } } };
      setError(
        axiosErr.response?.data?.message ??
          "Không gửi được lời nhắn. Bạn có thể gọi tổng đài 1900 1234.",
      );
    } finally {
      setSending(false);
    }
  };

  const fieldClass =
    "mt-1 w-full rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20";

  return (
    <div className="min-h-screen bg-gray-50/60 py-10">
      <div className="mx-auto max-w-5xl px-4 sm:px-6">
        <nav className="mb-8 flex items-center gap-2 text-xs font-medium text-gray-500 md:text-sm">
          <Link to="/" className="transition-colors hover:text-primary-600">
            Trang chủ
          </Link>
          <span className="text-gray-300">/</span>
          <span className="text-gray-900">Liên hệ</span>
        </nav>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
          <aside className="space-y-4 lg:col-span-2">
            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
              <h1 className="font-plus-jakarta text-xl font-bold text-gray-900">Liên hệ</h1>
              <p className="mt-2 text-sm leading-relaxed text-gray-600">
                Cần tư vấn chọn tour, hỏi về một đơn đã đặt, hay muốn báo giá cho đoàn? Gọi tổng
                đài để được trả lời ngay, hoặc để lại lời nhắn bên cạnh.
              </p>

              <div className="mt-6 space-y-4 text-sm">
                <div className="flex items-start gap-3">
                  <Phone className="mt-0.5 h-4 w-4 shrink-0 text-primary-600" />
                  <div>
                    <p className="font-bold text-primary-600">1900 1234</p>
                    <p className="text-xs text-gray-500">8:00 – 21:00 hằng ngày</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <Mail className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <p className="font-semibold text-gray-800">info@vivubooking.vn</p>
                </div>
                <div className="flex items-start gap-3">
                  <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <p className="text-gray-700">
                    Phố Kiều Mai, Phúc Diễn, Bắc Từ Liêm, Hà Nội
                  </p>
                </div>
              </div>
            </div>

            {/*
              Đưa người đang có việc gấp đi đúng chỗ.
              Một câu hỏi về đơn đã đặt trả lời được trong ba giây bằng mã tra cứu, nhanh hơn
              nhiều so với chờ điều hành đọc lời nhắn.
            */}
            <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-5 text-sm">
              <p className="font-bold text-blue-900">Hỏi về đơn đã đặt?</p>
              <p className="mt-1 text-xs leading-relaxed text-blue-800">
                Tra cứu bằng mã đơn sẽ nhanh hơn: bạn xem được ngay trạng thái, giờ khởi hành và
                thông tin hướng dẫn viên.
              </p>
              <Link
                to="/booking-lookup"
                className="mt-3 inline-block rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-blue-700"
              >
                Tra cứu đơn
              </Link>
            </div>
          </aside>

          <div className="lg:col-span-3">
            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm md:p-8">
              <h2 className="font-plus-jakarta text-lg font-bold text-gray-900">
                Gửi lời nhắn
              </h2>
              <p className="mt-1 text-xs text-gray-500">
                Chúng tôi trả lời trong 24 giờ làm việc.
              </p>

              {sent ? (
                <div className="mt-6 space-y-4">
                  <div className="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                    {sent}
                  </div>
                  <button
                    onClick={() => setSent("")}
                    className="text-sm font-semibold text-primary-600 hover:underline"
                  >
                    Gửi thêm một lời nhắn khác
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="mt-6 space-y-4">
                  {error && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                      {error}
                    </div>
                  )}

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label className="block">
                      <span className="text-xs font-semibold text-gray-700">
                        Họ tên <span className="text-rose-500">*</span>
                      </span>
                      <input
                        name="name"
                        required
                        maxLength={255}
                        value={form.name}
                        onChange={handleChange}
                        placeholder="Nguyễn Văn A"
                        className={fieldClass}
                      />
                    </label>
                    <label className="block">
                      <span className="text-xs font-semibold text-gray-700">
                        Email <span className="text-rose-500">*</span>
                      </span>
                      <input
                        name="email"
                        type="email"
                        required
                        maxLength={255}
                        value={form.email}
                        onChange={handleChange}
                        placeholder="email@cua-ban.com"
                        className={fieldClass}
                      />
                    </label>
                  </div>

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label className="block">
                      <span className="text-xs font-semibold text-gray-700">Số điện thoại</span>
                      <input
                        name="phone"
                        maxLength={20}
                        value={form.phone}
                        onChange={handleChange}
                        placeholder="0901234567"
                        className={fieldClass}
                      />
                    </label>
                    <label className="block">
                      <span className="text-xs font-semibold text-gray-700">Tiêu đề</span>
                      <input
                        name="subject"
                        maxLength={255}
                        value={form.subject}
                        onChange={handleChange}
                        placeholder="Hỏi về tour Hạ Long tháng sau"
                        className={fieldClass}
                      />
                    </label>
                  </div>

                  <label className="block">
                    <span className="text-xs font-semibold text-gray-700">
                      Nội dung <span className="text-rose-500">*</span>
                    </span>
                    <textarea
                      name="message"
                      rows={5}
                      required
                      minLength={10}
                      maxLength={2000}
                      value={form.message}
                      onChange={handleChange}
                      placeholder="Bạn cần chúng tôi hỗ trợ điều gì?"
                      className={`${fieldClass} resize-y`}
                    />
                    <span className="mt-1 block text-[11px] text-gray-400">
                      {form.message.trim().length}/10 ký tự tối thiểu
                    </span>
                  </label>

                  <button
                    type="submit"
                    disabled={sending || form.message.trim().length < 10}
                    className="w-full rounded-full bg-primary-600 px-4 py-3.5 text-sm font-bold text-white transition-colors hover:bg-primary-700 disabled:opacity-60"
                  >
                    {sending ? "Đang gửi..." : "Gửi lời nhắn"}
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ContactPage;
