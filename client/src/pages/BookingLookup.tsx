import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import bookingService from "@/services/bookingService";

// Cho phép khách dán cả đường dẫn đầy đủ trong email, tự lấy ra mã tra cứu
const extractCode = (input: string) => {
  const value = input.trim();
  const fromUrl = value.match(/booking-success\/([^/?#\s]+)/i);
  return (fromUrl ? fromUrl[1] : value).trim();
};

export const BookingLookup: React.FC = () => {
  const navigate = useNavigate();
  const [code, setCode] = useState("");
  const [checking, setChecking] = useState(false);
  const [error, setError] = useState("");

  // Task X06b: State gửi lại mã tra cứu qua Email
  const [showResendForm, setShowResendForm] = useState(false);
  const [resendEmail, setResendEmail] = useState("");
  const [resendPhone, setResendPhone] = useState("");
  const [resending, setResending] = useState(false);
  const [resendSuccessMessage, setResendSuccessMessage] = useState("");
  const [resendErrorMessage, setResendErrorMessage] = useState("");

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();

    const lookupCode = extractCode(code);
    if (!lookupCode) return;

    setChecking(true);
    setError("");

    try {
      await bookingService.getById(lookupCode);
      navigate(`/booking-success/${lookupCode}`);
    } catch {
      setError(
        "Không tìm thấy đơn đặt tour với mã này. Vui lòng kiểm tra lại mã tra cứu trong email xác nhận.",
      );
    } finally {
      setChecking(false);
    }
  };

  const handleResendSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!resendEmail.trim()) return;

    setResending(true);
    setResendSuccessMessage("");
    setResendErrorMessage("");

    try {
      const response = await bookingService.resendLookupCode({
        email: resendEmail.trim(),
        phone: resendPhone.trim() || undefined,
      });

      setResendSuccessMessage(
        response.data?.message ||
          "Danh sách mã tra cứu đã được gửi về email của bạn. Vui lòng kiểm tra hộp thư!",
      );
      setResendEmail("");
      setResendPhone("");
    } catch {
      setResendErrorMessage(
        "Không thể kết nối đến máy chủ. Vui lòng thử lại sau.",
      );
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="min-h-[70vh] bg-gray-50/60 py-10">
      <div className="mx-auto max-w-2xl px-4 sm:px-6">
        <nav className="mb-6 flex items-center gap-2 text-xs font-medium text-gray-500 md:text-sm">
          <Link to="/" className="transition-colors hover:text-primary-600">
            Trang chủ
          </Link>
          <span className="text-gray-300">/</span>
          <span className="text-gray-900">Tra cứu đơn đặt tour</span>
        </nav>

        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm md:p-8 space-y-6">
          <div>
            <h1 className="font-plus-jakarta text-2xl font-bold text-gray-900">
              Tra cứu đơn đặt tour
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-gray-500">
              Nhập mã tra cứu được gửi trong email xác nhận đặt tour để xem
              trạng thái đơn, thông tin điểm đón và thanh toán.
            </p>
          </div>

          {/* Form Tra Cứu Đơn */}
          <form onSubmit={handleSubmit} className="space-y-4">
            <label className="block space-y-1.5">
              <span className="text-xs font-bold uppercase tracking-wider text-gray-700">
                Mã tra cứu đơn hàng
              </span>
              <input
                required
                autoFocus
                value={code}
                onChange={(e) => {
                  setCode(e.target.value);
                  if (error) setError("");
                }}
                placeholder="Dán mã tra cứu hoặc đường dẫn trong email"
                className="w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-3.5 font-mono text-sm text-gray-900 transition-all focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
            </label>

            {error && (
              <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {error}
              </div>
            )}

            <button
              type="submit"
              disabled={checking || !code.trim()}
              className="w-full rounded-xl bg-primary-600 py-3.5 text-sm font-bold text-white shadow-sm transition-all hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {checking ? "Đang tra cứu..." : "Tra cứu đơn hàng"}
            </button>
          </form>

          {/* TASK X06b: Phần Khôi Phục / Gửi Lại Mã Tra Cứu Dành Cho Khách Vãng Lai */}
          <div className="rounded-md border border-blue-100 bg-blue-50/50 p-5 space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <p className="text-sm font-bold text-gray-800">
                  Quên hoặc không nhận được mã tra cứu?
                </p>
              </div>
              <button
                type="button"
                onClick={() => {
                  setShowResendForm(!showResendForm);
                  setResendSuccessMessage("");
                  setResendErrorMessage("");
                }}
                className="text-xs font-bold text-primary-600 hover:text-primary-700 underline"
              >
                {showResendForm ? "Ẩn khung gửi lại" : "Gửi lại mã qua Email →"}
              </button>
            </div>

            {/* Form Gửi Lại Mã Tra Cứu */}
            {showResendForm && (
              <form
                onSubmit={handleResendSubmit}
                className="mt-3 pt-3 border-t border-blue-100 space-y-3.5 animate-fade-in"
              >
                <p className="text-xs text-gray-600">
                  Nhập Email và Số điện thoại bạn đã dùng khi đặt tour. Hệ thống
                  sẽ kiểm tra và gửi lại mã tra cứu về hộp thư của bạn.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1">
                      Email nhận mã <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="email"
                      required
                      value={resendEmail}
                      onChange={(e) => setResendEmail(e.target.value)}
                      placeholder="vidu@gmail.com"
                      className="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs text-gray-900 outline-none focus:border-primary-500 shadow-xs"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1">
                      Số điện thoại đặt tour
                    </label>
                    <input
                      type="tel"
                      value={resendPhone}
                      onChange={(e) => setResendPhone(e.target.value)}
                      placeholder="0912345678"
                      className="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs text-gray-900 outline-none focus:border-primary-500 shadow-xs"
                    />
                  </div>
                </div>

                {resendSuccessMessage && (
                  <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
                    ✅ {resendSuccessMessage}
                  </div>
                )}

                {resendErrorMessage && (
                  <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700">
                    ⚠️ {resendErrorMessage}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={resending || !resendEmail.trim()}
                  className="w-full sm:w-auto px-5 py-2.5 rounded-md bg-primary-600 hover:bg-primary-700 text-white font-bold text-xs shadow-xs disabled:opacity-50 transition-colors"
                >
                  {resending
                    ? "Đang gửi email..."
                    : "Gửi danh sách mã về Email"}
                </button>
              </form>
            )}
          </div>

          {/* Gợi ý hướng dẫn */}
          <div className="border-t border-gray-100 pt-5 text-sm text-gray-500">
            <p className="font-semibold text-gray-700">
              Gợi ý tìm kiếm mã tra cứu:
            </p>
            <ul className="mt-2 list-disc space-y-1 pl-5 leading-relaxed text-xs">
              <li>
                Mã tra cứu nằm trong Email xác nhận được tự động gửi ngay sau
                khi đặt tour thành công.
              </li>
              <li>
                Nếu bạn đặt tour bằng tài khoản đã đăng nhập, xem trực tiếp tại{" "}
                <Link
                  to="/my-bookings"
                  className="font-semibold text-primary-600 hover:underline"
                >
                  Đơn của tôi
                </Link>
                .
              </li>
              <li>
                Cần hỗ trợ gấp, vui lòng liên hệ hotline tổng đài:{" "}
                <strong>1900 1234</strong>.
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BookingLookup;
