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

        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm md:p-8">
          <h1 className="font-plus-jakarta text-2xl font-bold text-gray-900">
            Tra cứu đơn đặt tour
          </h1>
          <p className="mt-2 text-sm leading-relaxed text-gray-500">
            Nhập mã tra cứu được gửi trong email xác nhận đặt tour để xem trạng thái đơn,
            thông tin điểm đón và thanh toán. Bạn không cần đăng nhập.
          </p>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
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

          <div className="mt-6 border-t border-gray-100 pt-5 text-sm text-gray-500">
            <p className="font-semibold text-gray-700">Không tìm thấy mã tra cứu?</p>
            <ul className="mt-2 list-disc space-y-1 pl-5 leading-relaxed">
              <li>Mã nằm trong email xác nhận gửi ngay sau khi bạn đặt tour.</li>
              <li>
                Nếu bạn đặt tour bằng tài khoản đã đăng nhập, xem trực tiếp tại{" "}
                <Link to="/my-bookings" className="font-semibold text-primary-600 hover:underline">
                  Đơn của tôi
                </Link>
                .
              </li>
              <li>Cần hỗ trợ, vui lòng gọi tổng đài 1900 1234.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BookingLookup;
