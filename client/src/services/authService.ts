import api from "./api";

const authService = {
  login: (payload: { email: string; password: string }) =>
    api.post("/login", payload),

  register: (payload: Record<string, unknown>) =>
    api.post("/register", payload),

  logout: () => api.post("/logout"),

  getMe: () => api.get("/me"),
};

export default authService;
