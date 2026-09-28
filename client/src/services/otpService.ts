import api from "@/services/api";
import type {
  OtpSendRequest,
  OtpSendResponse,
  OtpVerifyRequest,
  OtpVerifyResponse,
} from "@/types/otp";

/**
 * Service gửi và xác thực mã OTP Email cho khách hàng trước khi thanh toán.
 */
export const otpService = {
  /**
   * Gửi mã OTP tới email của khách hàng.
   */
  async sendOtp(payload: OtpSendRequest): Promise<OtpSendResponse> {
    try {
      const response = await api.post<OtpSendResponse>("/bookings/send-otp", payload);
      return response.data;
    } catch (error: any) {
      const message = error.response?.data?.message || "Không thể gửi mã OTP. Vui lòng thử lại.";
      throw new Error(message);
    }
  },

  /**
   * Xác nhận mã OTP do khách hàng nhập.
   */
  async verifyOtp(payload: OtpVerifyRequest): Promise<OtpVerifyResponse> {
    try {
      const response = await api.post<OtpVerifyResponse>("/bookings/verify-otp", payload);
      return response.data;
    } catch (error: any) {
      const message = error.response?.data?.message || "Xác thực mã OTP thất bại.";
      throw new Error(message);
    }
  },
};

export default otpService;
