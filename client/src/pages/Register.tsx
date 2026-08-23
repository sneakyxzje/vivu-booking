import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import authService from "@/services/authService";
import type { AxiosError } from "axios";
import type { AuthResponse } from "@/types";

export const Register: React.FC = () => {
  const navigate = useNavigate();
  const { login } = useAuth();

  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    phone: "",
    address: "",
  });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    if (error) setError("");
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError("");

    if (form.password !== form.password_confirmation) {
      setError("Mật khẩu xác nhận không khớp.");
      setLoading(false);
      return;
    }

    try {
      const res = await authService.register(form);
      const data = res.data as AuthResponse;
      login(data.token, data.user);
      navigate("/", { replace: true });
    } catch (err) {
      const axiosErr = err as AxiosError<{
        message?: string;
        errors?: Record<string, string[]>;
      }>;
      const msg =
        axiosErr.response?.data?.message ||
        "Đăng ký thất bại. Vui lòng thử lại.";
      const fieldErrors = axiosErr.response?.data?.errors;
      if (fieldErrors) {
        const first = Object.values(fieldErrors).flat()[0];
        setError(first || msg);
      } else {
        setError(msg);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-[calc(100vh-8rem)] bg-gray-50 flex flex-col items-center justify-center px-4 py-12">

      <div className="w-full max-w-[600px]">
        <div className="bg-white rounded-lg shadow-xl p-8 md:p-10 border border-gray-100">
          {/* Header */}
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-gray-900">
              Đăng ký tài khoản
            </h2>
            <p className="mt-2 text-sm text-gray-500">
              Tạo tài khoản để trải nghiệm dịch vụ tốt nhất
            </p>
          </div>

          {/* Form */}
          <form className="space-y-6" onSubmit={handleSubmit}>
            {error && (
              <div className="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                {error}
              </div>
            )}

            <div>
              <label
                htmlFor="name"
                className="block text-sm font-semibold text-gray-700 mb-2"
              >
                Họ và tên <span className="text-red-500">(*)</span>
              </label>
              <input
                id="name"
                name="name"
                type="text"
                autoComplete="name"
                required
                value={form.name}
                onChange={handleChange}
                placeholder="Nhập họ và tên"
                className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
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
                  value={form.email}
                  onChange={handleChange}
                  placeholder="you@example.com"
                  className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm"
                />
              </div>

              <div>
                <label
                  htmlFor="phone"
                  className="block text-sm font-semibold text-gray-700 mb-2"
                >
                  Số điện thoại <span className="text-red-500">(*)</span>
                </label>
                <input
                  id="phone"
                  name="phone"
                  type="tel"
                  autoComplete="tel"
                  required
                  value={form.phone}
                  onChange={handleChange}
                  placeholder="Nhập số điện thoại"
                  className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm"
                />
              </div>
            </div>

            <div>
              <label
                htmlFor="address"
                className="block text-sm font-semibold text-gray-700 mb-2"
              >
                Địa chỉ
              </label>
              <input
                id="address"
                name="address"
                type="text"
                autoComplete="street-address"
                value={form.address}
                onChange={handleChange}
                placeholder="Nhập địa chỉ của bạn"
                className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <label
                  htmlFor="password"
                  className="block text-sm font-semibold text-gray-700 mb-2"
                >
                  Mật khẩu <span className="text-red-500">(*)</span>
                </label>
                <div className="relative">
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="new-password"
                    required
                    value={form.password}
                    onChange={handleChange}
                    placeholder="Tạo mật khẩu"
                    className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm pr-12"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600"
                  >
                    {showPassword ? (
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                      </svg>
                    ) : (
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    )}
                  </button>
                </div>
              </div>

              <div>
                <label
                  htmlFor="password_confirmation"
                  className="block text-sm font-semibold text-gray-700 mb-2"
                >
                  Xác nhận mật khẩu <span className="text-red-500">(*)</span>
                </label>
                <div className="relative">
                  <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type={showConfirmPassword ? "text" : "password"}
                    autoComplete="new-password"
                    required
                    value={form.password_confirmation}
                    onChange={handleChange}
                    placeholder="Nhập lại mật khẩu"
                    className="block w-full rounded-xl bg-gray-50/50 border border-gray-200 px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all duration-300 text-sm pr-12"
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    className="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600"
                  >
                    {showConfirmPassword ? (
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                      </svg>
                    ) : (
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    )}
                  </button>
                </div>
              </div>
            </div>

            <div className="flex items-start gap-3 pt-2">
              <input
                id="terms"
                type="checkbox"
                required
                className="mt-1 h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <label htmlFor="terms" className="text-sm text-gray-600 leading-relaxed">
                Tôi đồng ý với{" "}
                <Link
                  to="/terms"
                  className="font-bold text-primary-600 hover:text-primary-700 hover:underline transition-colors"
                >
                  Điều khoản dịch vụ
                </Link>{" "}
                và{" "}
                <Link
                  to="/privacy"
                  className="font-bold text-primary-600 hover:text-primary-700 hover:underline transition-colors"
                >
                  Chính sách bảo mật
                </Link>
              </label>
            </div>

            {/* Một nút, chiếm hết chiều ngang — đối xứng với trang đăng nhập. */}
            <button
              type="submit"
              disabled={loading}
              className="flex w-full items-center justify-center rounded-full bg-primary-600 px-4 py-3.5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-60 transition-colors mt-8"
            >
              {loading ? (
                <svg className="h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              ) : (
                "Đăng ký"
              )}
            </button>
          </form>

          {/* Lối quay lại trang đăng nhập. Đăng ký bằng Facebook/Google đã gỡ, xem Login.tsx. */}
          <div className="mt-8 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
            Đã có tài khoản?{" "}
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

export default Register;
