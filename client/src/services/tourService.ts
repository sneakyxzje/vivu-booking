import api from "./api";

const tourService = {
  getAll: (params?: Record<string, unknown>) => api.get("/tours", { params }),

  getById: (id: number) => api.get(`/tours/${id}`),

  review: (tourId: number, payload: { rating: number; comment: string }) =>
    api.post(`/tours/${tourId}/reviews`, payload),
};

export default tourService;
