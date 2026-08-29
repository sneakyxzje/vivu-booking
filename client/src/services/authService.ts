import api from "./api";
import type { LoginPayload, RegisterPayload } from "@/types";

const authService = {
  login: (payload: LoginPayload) => api.post("/login", payload),

  register: (payload: RegisterPayload) => api.post("/register", payload),

  logout: () => api.post("/logout"),

  getMe: () => api.get("/me"),

  updateProfile: (payload: { name: string; phone?: string | null; address?: string | null }) =>
    api.put("/profile", payload),

  changePassword: (payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }) => api.put("/profile/password", payload),

  /*
   * Quên mật khẩu.
   *
   * Máy chủ cố ý trả về cùng một câu dù email có tài khoản hay không, nên màn hình gọi hàm này
   * KHÔNG được suy ra điều gì từ việc gọi thành công — chỉ hiển thị lại đúng câu nhận được.
   */
  forgotPassword: (payload: { email: string }) => api.post("/forgot-password", payload),

  resetPassword: (payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => api.post("/reset-password", payload),
};

export default authService;
