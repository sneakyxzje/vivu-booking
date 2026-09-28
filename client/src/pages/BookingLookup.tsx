import { Form, Alert, Breadcrumb, Input as AntInput, Button as AntButton } from "antd";
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
        <Breadcrumb style={{ marginBottom: 24 }} items={[{ title: <Link to="/">Trang chủ</Link> }, { title: "Tra cứu đơn đặt tour" }]} />

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
          <Form component={false} layout="vertical"><form onSubmit={handleSubmit} className="space-y-4">
            <Form.Item label={<>Mã tra cứu đơn hàng
            </>} htmlFor="bookinglookup-field-1" style={{ marginBottom: 0 }}>
              <AntInput id="bookinglookup-field-1"
                required
                autoFocus
                value={code}
                onChange={(e) => {
                  setCode(e.target.value);
                  if (error) setError("");
                }}
                placeholder="Dán mã tra cứu hoặc đường dẫn trong email"

              />
            </Form.Item>

            {error && (
              <Alert showIcon type="error" title={error} />
            )}

            <AntButton
              htmlType="submit"
              loading={checking} disabled={checking || !code.trim()}
              type="primary" block
            >
              {checking ? "Đang tra cứu..." : "Tra cứu đơn hàng"}
            </AntButton>
          </form></Form>

          {/* TASK X06b: Phần Khôi Phục / Gửi Lại Mã Tra Cứu Dành Cho Khách Vãng Lai */}
          <div className="rounded-md border border-blue-100 bg-blue-50/50 p-5 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <p className="text-sm font-bold text-gray-800">
                  Quên hoặc không nhận được mã tra cứu?
                </p>
              </div>
              <AntButton
                htmlType="button"
                onClick={() => {
                  setShowResendForm(!showResendForm);
                  setResendSuccessMessage("");
                  setResendErrorMessage("");
                }}

              >
                {showResendForm ? "Ẩn khung gửi lại" : "Gửi lại mã qua Email →"}
              </AntButton>
            </div>

            {/* Form Gửi Lại Mã Tra Cứu */}
            {showResendForm && (
              <Form component={false} layout="vertical"><form
                onSubmit={handleResendSubmit}
                className="mt-3 pt-3 border-t border-blue-100 space-y-3.5 animate-fade-in"
              >
                <p className="text-xs text-gray-600">
                  Nhập Email và Số điện thoại bạn đã dùng khi đặt tour. Hệ thống
                  sẽ kiểm tra và gửi lại mã tra cứu về hộp thư của bạn.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <Form.Item label={<>Email nhận mã <span className="text-rose-500">*</span></>} htmlFor="bookinglookup-field-2" style={{ marginBottom: 0 }}>
                    <AntInput id="bookinglookup-field-2"
                      type="email"
                      required
                      value={resendEmail}
                      onChange={(e) => setResendEmail(e.target.value)}
                      placeholder="vidu@gmail.com"

                    />
                  </Form.Item>

                  <Form.Item label={<>Số điện thoại đặt tour
                  </>} htmlFor="bookinglookup-field-3" style={{ marginBottom: 0 }}>
                    <AntInput id="bookinglookup-field-3"
                      type="tel"
                      value={resendPhone}
                      onChange={(e) => setResendPhone(e.target.value)}
                      placeholder="0912345678"

                    />
                  </Form.Item>
                </div>

                {resendSuccessMessage && (
                  <Alert showIcon type="success" title={resendSuccessMessage} />
                )}

                {resendErrorMessage && (
                  <Alert showIcon type="error" title={resendErrorMessage} />
                )}

                <AntButton
                  htmlType="submit"
                  loading={resending} disabled={resending || !resendEmail.trim()}
                  type="primary" block
                >
                  {resending
                    ? "Đang gửi email..."
                    : "Gửi danh sách mã về Email"}
                </AntButton>
              </form></Form>
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
