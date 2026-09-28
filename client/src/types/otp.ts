/**
 * Type definitions cho tính năng Xác thực OTP Email.
 */

export interface OtpSendRequest {
  email: string;
  customer_name?: string;
  tour_title?: string;
}

export interface OtpSendResponse {
  success: boolean;
  message: string;
  data: { challenge: string; expires_in: number };
}

export interface OtpVerifyRequest {
  challenge: string;
  email: string;
  otp: string;
}

export interface OtpVerifyResponse {
  success: boolean;
  message: string;
  data: { verification_token: string; expires_in: number };
}
