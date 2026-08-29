import React, { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import authService from "@/services/authService";
import type { AxiosError } from "axios";

/**
 * Đặt mật khẩu mới bằng liên kết trong thư.
 *
 * `token` và `email` đến từ chuỗi truy vấn của liên kết, không phải do người dùng gõ — nên nếu
 * thiếu một trong hai thì trang không hiện biểu mẫu: nhận mật khẩu mới rồi mới báo "liên kết
 * hỏng" là bắt người ta gõ hai lần một thứ sẽ bị vứt đi.
 */
export const ResetPassword: React.FC = () => {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const token = params.get("token") ?? "";
  const email = params.get("email") ?? "";

  const [form, setForm] = useState({ password: "", password_confirmation: "" });
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    if (error) setError("");
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (form.password !== form.password_confirmation) {
      setError("Hai lần nhập mật khẩu chưa khớp nhau.");
      return;
    }

    setLoading(true);
    setError("");

    try {
      await authService.resetPassword({ token, email, ...form });
      setDone(true);
      // Chờ một nhịp để người dùng kịp đọc câu báo thành công rồi mới chuyển trang.
      setTimeout(() => navigate("/login", { replace: true }), 2500);
    } catch (err) {
      const axiosErr = err as AxiosError<{ message?: string }>;
      setError(
        axiosErr.response?.data?.message ||
          "Không đặt lại được mật khẩu. Vui lòng yêu cầu một liên kết mới.",
      );
    } finally {
      setLoading(false);
    }
  };

  const inputClass =
    "block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm";

  return (
    <div className="min-h-[calc(100vh-8rem)] bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
      <div className="w-full max-w-[480px]">
        <div className="bg-white rounded-lg shadow-xl p-8 md:p-10 border border-gray-100">
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-gray-900">Đặt mật khẩu mới</h2>
            {email && (
              <p className="mt-2 text-sm text-gray-600">
                Cho tài khoản <strong className="text-gray-900">{email}</strong>
              </p>
            )}
          </div>

          {!token || !email ? (
            <div className="space-y-6">
              <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                Liên kết không hợp lệ hoặc đã bị cắt mất khi sao chép. Hãy yêu cầu một liên kết mới.
              </div>
              <Link
                to="/forgot-password"
                className="flex w-full items-center justify-center rounded-full bg-primary-600 px-4 py-3.5 text-sm font-bold text-white hover:bg-primary-700 transition-colors"
              >
                Gửi lại liên kết
              </Link>
            </div>
          ) : done ? (
            <div className="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-800">
              Đã đổi mật khẩu. Đang chuyển bạn sang trang đăng nhập...
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
                  htmlFor="password"
                  className="block text-sm font-semibold text-gray-700 mb-2"
                >
                  Mật khẩu mới <span className="text-red-500">(*)</span>
                </label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  required
                  minLength={6}
                  value={form.password}
                  onChange={handleChange}
                  placeholder="Ít nhất 6 ký tự"
                  className={inputClass}
                />
              </div>

              <div>
                <label
                  htmlFor="password_confirmation"
                  className="block text-sm font-semibold text-gray-700 mb-2"
                >
                  Nhập lại mật khẩu mới <span className="text-red-500">(*)</span>
                </label>
                <input
                  id="password_confirmation"
                  name="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  required
                  minLength={6}
                  value={form.password_confirmation}
                  onChange={handleChange}
                  placeholder="Gõ lại mật khẩu vừa nhập"
                  className={inputClass}
                />
              </div>

              <p className="text-xs text-gray-500">
                Đổi mật khẩu sẽ đăng xuất tài khoản này khỏi mọi thiết bị khác.
              </p>

              <button
                type="submit"
                disabled={loading}
                className="flex w-full items-center justify-center rounded-full bg-primary-600 px-4 py-3.5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-60 transition-colors"
              >
                {loading ? "Đang lưu..." : "Đổi mật khẩu"}
              </button>
            </form>
          )}
        </div>
      </div>
    </div>
  );
};

export default ResetPassword;
