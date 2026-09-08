import React, { useEffect, useRef, useState } from "react";
import { Mail, ShieldCheck, RefreshCw, AlertCircle, X } from "lucide-react";
import otpService from "@/services/otpService";

interface OtpVerificationModalProps {
  isOpen: boolean;
  email: string;
  customerName: string;
  tourTitle?: string;
  onClose: () => void;
  onVerified: () => void;
}

export const OtpVerificationModal: React.FC<OtpVerificationModalProps> = ({
  isOpen,
  email,
  customerName,
  tourTitle,
  onClose,
  onVerified,
}) => {
  const [otpValues, setOtpValues] = useState<string[]>(Array(6).fill(""));
  const [loading, setLoading] = useState(false);
  const [resending, setResending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Đếm ngược 5 phút (300 giây) hết hạn OTP
  const [expirySeconds, setExpirySeconds] = useState(300);
  // Đếm ngược 60 giây để cho phép gửi lại
  const [resendSeconds, setResendSeconds] = useState(60);

  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (isOpen) {
      setOtpValues(Array(6).fill(""));
      setError(null);
      setSuccessMessage(null);
      setExpirySeconds(300);
      setResendSeconds(60);

      // Focus ô đầu tiên khi mở modal
      setTimeout(() => {
        inputRefs.current[0]?.focus();
      }, 100);
    }
  }, [isOpen]);

  // Đếm ngược 5 phút
  useEffect(() => {
    if (!isOpen || expirySeconds <= 0) return;
    const timer = setInterval(() => {
      setExpirySeconds((prev) => prev - 1);
    }, 1000);
    return () => clearInterval(timer);
  }, [isOpen, expirySeconds]);

  // Đếm ngược 60s resend
  useEffect(() => {
    if (!isOpen || resendSeconds <= 0) return;
    const timer = setInterval(() => {
      setResendSeconds((prev) => prev - 1);
    }, 1000);
    return () => clearInterval(timer);
  }, [isOpen, resendSeconds]);

  if (!isOpen) return null;

  const formatTimer = (totalSecs: number) => {
    const mins = Math.floor(totalSecs / 60);
    const secs = totalSecs % 60;
    return `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
  };

  const handleInputChange = (index: number, value: string) => {
    if (!/^\d*$/.test(value)) return;

    const newValues = [...otpValues];
    // Chỉ lấy ký tự cuối cùng vừa gõ
    newValues[index] = value.slice(-1);
    setOtpValues(newValues);
    setError(null);

    // Tự động chuyển con trỏ sang ô tiếp theo
    if (value && index < 5) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handleKeyDown = (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Backspace" && !otpValues[index] && index > 0) {
      inputRefs.current[index - 1]?.focus();
    }
  };

  const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData("text").trim();
    if (/^\d{6}$/.test(pastedData)) {
      const digits = pastedData.split("");
      setOtpValues(digits);
      inputRefs.current[5]?.focus();
    }
  };

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    const otpCode = otpValues.join("");
    if (otpCode.length < 6) {
      setError("Vui lòng nhập đủ 6 chữ số mã OTP.");
      return;
    }

    if (expirySeconds <= 0) {
      setError("Mã OTP đã hết hạn. Vui lòng bấm gửi lại mã mới.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await otpService.verifyOtp({ email, otp: otpCode });
      setSuccessMessage("Xác thực email thành công! Đang chuyển đến trang thanh toán...");
      setTimeout(() => {
        onVerified();
      }, 1200);
    } catch (err: unknown) {
      const errObj = err as { message?: string };
      setError(errObj.message || "Mã OTP không đúng. Vui lòng thử lại.");
    } finally {
      setLoading(false);
    }
  };

  const handleResendOtp = async () => {
    if (resendSeconds > 0 || resending) return;

    setResending(true);
    setError(null);
    setSuccessMessage(null);

    try {
      await otpService.sendOtp({ email, customer_name: customerName, tour_title: tourTitle });
      setOtpValues(Array(6).fill(""));
      setExpirySeconds(300);
      setResendSeconds(60);
      setSuccessMessage("Mã OTP mới đã được gửi lại vào email của bạn.");
      setTimeout(() => setSuccessMessage(null), 4000);
      inputRefs.current[0]?.focus();
    } catch {
      setError("Khởi tạo mã OTP thất bại. Vui lòng thử lại sau ít phút.");
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
      <div className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-100 p-6 md:p-8 space-y-6">
        {/* Nút đóng */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-1.5 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all cursor-pointer"
          aria-label="Đóng"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Icon & Title */}
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary-50 text-primary-600 ring-8 ring-primary-50/50 mb-1">
            <ShieldCheck className="w-7 h-7" />
          </div>
          <h3 className="text-xl font-bold text-slate-900 font-plus-jakarta">
            Xác thực Email đặt tour
          </h3>
          <p className="text-xs text-slate-500 leading-relaxed max-w-xs mx-auto">
            Hệ thống đã gửi mã OTP gồm 6 chữ số đến địa chỉ email:
          </p>
          <p className="text-sm font-bold text-primary-700 bg-primary-50/70 py-1.5 px-3 rounded-lg border border-primary-100/50 inline-block font-mono">
            {email}
          </p>
        </div>

        {/* Form nhập OTP */}
        <form onSubmit={handleVerify} className="space-y-5">
          <div className="flex items-center justify-center gap-2 sm:gap-2.5">
            {otpValues.map((digit, idx) => (
              <input
                key={idx}
                ref={(el) => (inputRefs.current[idx] = el)}
                type="text"
                inputMode="numeric"
                maxLength={1}
                value={digit}
                onChange={(e) => handleInputChange(idx, e.target.value)}
                onKeyDown={(e) => handleKeyDown(idx, e)}
                onPaste={handlePaste}
                className="w-11 h-13 sm:w-12 sm:h-14 text-center text-xl font-bold text-slate-900 border-2 border-slate-200 rounded-xl focus:border-primary-600 focus:ring-2 focus:ring-primary-100 focus:outline-none bg-slate-50/50 transition-all font-mono shadow-sm"
              />
            ))}
          </div>

          {/* Thông báo lỗi hoặc thành công */}
          {error && (
            <div className="flex items-center gap-2 p-3 text-xs font-medium text-rose-700 bg-rose-50 border border-rose-100 rounded-xl">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {successMessage && (
            <div className="flex items-center gap-2 p-3 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-xl">
              <ShieldCheck className="w-4 h-4 shrink-0 text-emerald-600" />
              <span>{successMessage}</span>
            </div>
          )}

          {/* Thời gian đếm ngược */}
          <div className="flex items-center justify-between text-xs text-slate-500 pt-1">
            <span className="flex items-center gap-1 font-medium">
              <Mail className="w-3.5 h-3.5 text-slate-400" />
              Hết hạn sau:{" "}
              <b className={`font-mono ${expirySeconds < 60 ? "text-rose-600 animate-pulse" : "text-slate-800"}`}>
                {formatTimer(expirySeconds)}
              </b>
            </span>

            <button
              type="button"
              onClick={handleResendOtp}
              disabled={resendSeconds > 0 || resending}
              className="flex items-center gap-1 font-semibold text-primary-600 hover:text-primary-700 disabled:opacity-40 disabled:pointer-events-none transition-all cursor-pointer"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${resending ? "animate-spin" : ""}`} />
              {resending ? "Đang gửi..." : resendSeconds > 0 ? `Gửi lại (${resendSeconds}s)` : "Gửi lại mã OTP"}
            </button>
          </div>

          {/* Action buttons */}
          <div className="pt-2">
            <button
              type="submit"
              disabled={loading || otpValues.join("").length < 6}
              className="w-full py-3.5 px-4 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none text-sm cursor-pointer"
            >
              {loading ? "Đang xác thực OTP..." : "Xác nhận & Tiếp tục thanh toán"}
            </button>
          </div>
        </form>

        <p className="text-[11px] text-center text-slate-400 leading-snug">
          Vui lòng kiểm tra kỹ cả thư mục <b>Spam/Rác</b> nếu bạn không tìm thấy email trong Hộp thư đến.
        </p>
      </div>
    </div>
  );
};

export default OtpVerificationModal;
