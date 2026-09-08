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
  expires_at?: string;
}

export interface OtpVerifyRequest {
  email: string;
  otp: string;
}

export interface OtpVerifyResponse {
  success: boolean;
  message: string;
  verified: boolean;
}
