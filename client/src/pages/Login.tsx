import React, { useState, useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import authService from "@/services/authService";
import type { AxiosError } from "axios";
import type { AuthResponse } from "@/types";

export const Login: React.FC = () => {
  const navigate = useNavigate();
  const { login } = useAuth();

  const [form, setForm] = useState({ email: "", password: "" });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState("");

  const [fieldErrors, setFieldErrors] = useState({
  email: "",
  password: "",
});

  useEffect(() => {
    if (toast) {
      const timer = setTimeout(() => setToast(""), 3000);
      return () => clearTimeout(timer);
    }
  }, [toast]);
const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
  setForm((prev) => ({
    ...prev,
    [e.target.name]: e.target.value,
  }));

  setFieldErrors((prev) => ({
    ...prev,
    [e.target.name]: "",
  }));

  if (error) setError("");
};

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

const errors = {
  email: "",
  password: "",
};

let hasError = false;

if (!form.email.trim()) {
  errors.email = "Vui lòng nhập email hoặc số điện thoại";
  hasError = true;
} else {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const phoneRegex = /^(0|\+84)[0-9]{9}$/;

  if (
    !emailRegex.test(form.email.trim()) &&
    !phoneRegex.test(form.email.trim())
  ) {
    errors.email = "Email hoặc số điện thoại không hợp lệ";
    hasError = true;
  }
}

if (!form.password.trim()) {
  errors.password = "Vui lòng nhập mật khẩu";
  hasError = true;
} else if (form.password.length < 6) {
  errors.password = "Mật khẩu phải có ít nhất 6 ký tự";
  hasError = true;
}

setFieldErrors(errors);

if (hasError) return;

setLoading(true);
setError("");
    setLoading(true);
    setError("");

    try {
      const res = await authService.login(form);
      const data = res.data as AuthResponse;
      login(data.token, data.user);
      const dest =
        data.user.role === "guide"
          ? "/guide/dashboard"
          : data.user.role === "admin"
            ? "/admin/dashboard"
            : "/";
      navigate(dest, { replace: true });
    } catch (err) {
      const axiosErr = err as AxiosError<{ message?: string }>;
      setError(
        axiosErr.response?.data?.message ||
          "Đăng nhập thất bại. Vui lòng thử lại.",
      );
    } finally {
      setLoading(false);
    }
  };

  const handleSocialClick = () => {
    setToast("Tính năng đang phát triển");
  };

  return (
    <div className="min-h-[calc(100vh-8rem)] bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
      
      <div className="w-full max-w-[480px]">
        <div className="bg-white rounded-2xl shadow-xl p-8 md:p-10 border border-gray-100">
          {/* Header */}
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-gray-900">
              Đăng nhập
            </h2>
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
                htmlFor="email"
                className="block text-sm font-semibold text-gray-700 mb-2"
              >
                Số điện thoại hoặc email <span className="text-red-500">(*)</span>
              </label>
              <input
                id="email"
                name="email"
                type="text"
                autoComplete="email"
                required
                value={form.email}
                onChange={handleChange}
                placeholder="Số điện thoại hoặc email"
                className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:bg-white transition-colors text-sm"
              />
              {fieldErrors.email && (
  <p className="mt-1 text-sm text-red-500">
    {fieldErrors.email}
  </p>
)}
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <label
                  htmlFor="password"
                  className="block text-sm font-semibold text-gray-700"
                >
                  Mật khẩu <span className="text-red-500">(*)</span>
                </label>
                <Link
                  to="/forgot-password"
                  className="text-sm font-semibold text-primary-600 hover:text-primary-700 hover:underline transition-colors"
                >
                  Quên mật khẩu
                </Link>
              </div>
              <div className="relative">
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  value={form.password}
                  onChange={handleChange}
                  placeholder="Nhập mật khẩu"
                  className="block w-full rounded-xl bg-gray-50 border-none px-4 py-3.5 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:bg-white transition-colors text-sm pr-12"
                />
                <button type="button" className="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                </button>
              </div>
              {fieldErrors.password && (
  <p className="mt-1 text-sm text-red-500">
    {fieldErrors.password}
  </p>
)}
            </div>

            {/* Mock reCAPTCHA */}
            <div className="flex items-center justify-center mb-6">
              <div className="w-full max-w-[300px] border border-gray-200 bg-gray-50 rounded px-4 py-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <input type="checkbox" className="w-6 h-6 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                  <span className="text-sm text-gray-700">Tôi không phải là người máy</span>
                </div>
                <div className="flex flex-col items-center">
                  <svg className="w-8 h-8 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.24 12.24a8 8 0 11-1.6-4.66l-1.87 1.87a5.35 5.35 0 101.12 3.14l2.35-.35z" />
                  </svg>
                  <span className="text-[8px] text-gray-500 mt-1">reCAPTCHA</span>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <Link
                to="/register"
                className="flex items-center justify-center rounded-full border-2 border-primary-600 px-4 py-3 text-sm font-bold text-primary-600 hover:bg-primary-50 transition-colors"
              >
                Đăng ký ngay
              </Link>
              <button
                type="submit"
                disabled={loading}
                className="flex items-center justify-center rounded-full bg-primary-600 px-4 py-3 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-60 transition-colors"
              >
                {loading ? (
                  <svg className="h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                ) : (
                  "Đăng nhập"
                )}
              </button>
            </div>

            {/* Divider */}
            <div className="relative my-8">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-gray-200" />
              </div>
              <div className="relative flex justify-center text-sm">
                <span className="bg-white px-4 text-gray-400 font-medium">
                  Hoặc
                </span>
              </div>
            </div>

            {/* Social Buttons */}
            <div className="space-y-4">
              <button
                type="button"
                onClick={handleSocialClick}
                className="flex w-full items-center justify-center gap-3 rounded-full border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
              >
                <svg className="w-5 h-5 text-[#1877F2]" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                </svg>
                Tiếp tục với Facebook
              </button>

              <button
                type="button"
                onClick={handleSocialClick}
                className="flex w-full items-center justify-center gap-3 rounded-full border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
              >
                <svg className="w-5 h-5" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" />
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                </svg>
                Tiếp tục với Google
              </button>
            </div>
          </form>
        </div>
      </div>

      {/* Toast notification */}
      {toast && (
        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-lg shadow-lg text-sm z-50 animate-fade-in">
          {toast}
        </div>
      )}
    </div>
  );
};

export default Login;
