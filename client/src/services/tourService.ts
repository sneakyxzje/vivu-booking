import api from "./api";
import { extractArray, extractObject } from "@/utils/apiHelpers";
import type { Tour } from "../types";
import { buildTourPayload } from "@/services/guideService";

const tourService = {
  getAll: async (
    params?: Record<string, unknown>,
  ): Promise<{ data: Tour[]; success: boolean }> => {
    const response = await api.get("/tours", { params });
    const data = extractArray<Tour>(response);
    return { success: true, data };
  },

  getById: async (id: number): Promise<{ data: Tour; success: boolean }> => {
    const response = await api.get(`/tours/${id}`);
    const tour = extractObject<Tour>(response);

    if (!tour?.title) {
      throw new Error("Không tìm thấy tour.");
    }

    return { success: true, data: tour };
  },

  review: (tourId: number, payload: { rating: number; comment: string }) =>
    api.post(`/tours/${tourId}/reviews`, payload),

  createTour: async (payload: unknown): Promise<{ success: boolean }> => {
    const response = await api.post("/admin/tours", buildTourPayload(payload), {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return { success: response.data?.success !== false };
  },
};

export default tourService;
