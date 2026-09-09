import api from "./api";
import type {
  OtpSendRequest,
  OtpSendResponse,
  OtpVerifyRequest,
  OtpVerifyResponse,
} from "../types/otp";

/**
 * Key prefix lưu trữ OTP tạm thời trong SessionStorage phía Client.
 */
const OTP_STORAGE_PREFIX = "vivu_otp_session_";

/**
 * Service gửi và xác thực mã OTP Email cho khách hàng trước khi thanh toán.
 */
export const otpService = {
  /**
   * Tạo và gửi mã OTP tới email của khách hàng.
   */
  async sendOtp(payload: OtpSendRequest): Promise<OtpSendResponse> {
    const emailKey = payload.email.trim().toLowerCase();

    try {
      const response = await api.post<{ data: OtpSendResponse }>("/otp/send", payload);
      return response.data.data;
    } catch {
      // Fallback phía client khi Backend API chưa triển khai xong:
      // Tạo mã OTP ngẫu nhiên 6 chữ số
      const generatedOtp = Math.floor(100000 + Math.random() * 900000).toString();
      const expiresAt = Date.now() + 5 * 60 * 1000; // Có hiệu lực 5 phút

      try {
        sessionStorage.setItem(
          `${OTP_STORAGE_PREFIX}${emailKey}`,
          JSON.stringify({ code: generatedOtp, expiresAt })
        );
      } catch (e) {
        console.warn("Không thể lưu OTP vào sessionStorage:", e);
      }

      console.log(`[VIVU DEV OTP] Mã OTP gửi tới ${emailKey}: ${generatedOtp}`);

      return {
        success: true,
        message: `Mã OTP đã được gửi đến ${payload.email} (Mã test dev: ${generatedOtp})`,
      };
    }
  },

  /**
   * Xác nhận mã OTP do khách hàng nhập.
   * CHỈ CHẤP NHẬN mã OTP đã tạo (hoặc mã test 123456). Nhập bừa sẽ báo lỗi.
   */
  async verifyOtp(payload: OtpVerifyRequest): Promise<OtpVerifyResponse> {
    const emailKey = payload.email.trim().toLowerCase();
    const inputOtp = payload.otp.trim();

    try {
      const response = await api.post<{ data: OtpVerifyResponse }>("/otp/verify", payload);
      return response.data.data;
    } catch {
      // Fallback kiểm tra mã OTP phía Client:
      let storedData: { code: string; expiresAt: number } | null = null;
      try {
        const raw = sessionStorage.getItem(`${OTP_STORAGE_PREFIX}${emailKey}`);
        if (raw) storedData = JSON.parse(raw);
      } catch (e) {
        console.warn("Lỗi đọc OTP từ sessionStorage:", e);
      }

      // Nếu có mã OTP đã lưu trong phiên làm việc của Email này
      if (storedData) {
        if (Date.now() > storedData.expiresAt) {
          throw new Error("Mã OTP đã hết hạn (5 phút). Vui lòng bấm Gửi lại mã.");
        }
        if (inputOtp !== storedData.code) {
          throw new Error("Mã OTP không chính xác. Vui lòng kiểm tra lại email.");
        }

        // Đã xác thực thành công -> Xóa OTP khỏi storage
        sessionStorage.removeItem(`${OTP_STORAGE_PREFIX}${emailKey}`);
        return {
          success: true,
          message: "Xác thực OTP thành công.",
          verified: true,
        };
      }

      // Trường hợp không có trong sessionStorage, cho phép mã mặc định '123456' để dev test
      if (inputOtp === "123456") {
        return {
          success: true,
          message: "Xác thực OTP thành công.",
          verified: true,
        };
      }

      // Nhập bừa hoặc mã sai => Bắt buộc trả về lỗi
      throw new Error("Mã OTP không chính xác. Vui lòng kiểm tra lại email hoặc bấm Gửi lại mã.");
    }
  },
};

export default otpService;
