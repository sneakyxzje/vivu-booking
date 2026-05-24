import api from "./api";
import type { LoginPayload, RegisterPayload } from "@/types";

const authService = {
  login: (payload: LoginPayload) => api.post("/login", payload),

  register: (payload: RegisterPayload) => api.post("/register", payload),

  logout: () => api.post("/logout"),

  getMe: () => api.get("/me"),
};

export default authService;
