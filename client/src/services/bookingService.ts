import api from "./api";

const bookingService = {
  create: (payload: Record<string, unknown>) => api.post("/bookings", payload),

  getMyBookings: () => api.get("/my-bookings"),
};

export default bookingService;
