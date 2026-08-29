import React, { useState } from "react";
import { Link } from "react-router-dom";
import authService from "@/services/authService";
import type { AxiosError } from "axios";

/**
 * Xin liên kết đặt lại mật khẩu.
 *
 * Sau khi gửi, màn hình chuyển hẳn sang trạng thái "đã gửi" và KHÔNG nói email có tài khoản hay
 * không — máy chủ cũng trả về đúng một câu cho cả hai trường hợp, vì trả lời khác nhau là biến
 * trang này thành công cụ dò xem địa chỉ nào đã đăng ký ở đây.
 */
export const ForgotPassword: React.FC = () => {
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError("");

    try {
      const res = await authService.forgotPassword({ email: email.trim() });
      setMessage(res.data?.message ?? "Đã gửi yêu cầu. Vui lòng kiểm tra hòm thư.");
    } catch (err) {
      const axiosErr = err as AxiosError<{ message?: string }>;
      setError(
        axiosErr.response?.data?.message ||
          "Không gửi được yêu cầu. Vui lòng thử lại sau ít phút.",
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-[calc(100vh-8rem)] bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
      <div className="w-full max-w-[480px]">
        <div className="bg-white rounded-lg shadow-xl p-8 md:p-10 border border-gray-100">
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-gray-900">Quên mật khẩu</h2>
            <p className="mt-2 text-sm text-gray-600">
              Nhập email bạn đã dùng để đăng ký. Chúng tôi sẽ gửi một liên kết để bạn chọn mật khẩu
              mới.
            </p>
          </div>

          {message ? (
            <div className="space-y-6">
              <div className="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-800">
                {message}
              </div>
              <p className="text-sm text-gray-600">
                Liên kết chỉ dùng được một lần và sẽ hết hạn sau ít phút. Không thấy thư? Kiểm tra
                mục Spam, hoặc{" "}
                <button
                  type="button"
                  onClick={() => setMessage("")}
                  className="font-semibold text-primary-600 hover:text-primary-700 hover:underline"
                >
                  gửi lại
                </button>
                .
              </p>
              <Link
                to="/login"
                className="flex w-full items-center justify-center rounded-full bg-primary-600 px-4 py-3.5 text-sm font-bold text-white hover:bg-primary-700 transition-colors"
              >
                Quay lại đăng nhập
              </Link>
            </div>
          ) : (
            <form className="space-y-6" onSubmit={handleSubmit}>
              {error && (
                <div className="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                  {error}
                </div>
              )}

              <div>
                <label
                  htmlFor="email"
                  className="block text-sm font-semibold text-gray-700 mb-2"
                >
                  Email <span className="text-red-500">(*)</span>
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  value={email}
                  onChange={(e) => {
                    setEmail(e.target.value);
                    if (error) setError("");
                  }}
                  placeholder="email@cua-ban.com"
                  className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="flex w-full items-center justify-center rounded-full bg-primary-600 px-4 py-3.5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-60 transition-colors"
              >
                {loading ? "Đang gửi..." : "Gửi liên kết đặt lại"}
              </button>
            </form>
          )}

          <div className="mt-8 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
            Nhớ ra mật khẩu rồi?{" "}
            <Link
              to="/login"
              className="font-bold text-primary-600 hover:text-primary-700 hover:underline transition-colors"
            >
              Đăng nhập
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
