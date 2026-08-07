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
};

export default authService;
