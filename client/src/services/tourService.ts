import api from "./api";
import { extractArray, extractObject } from "@/utils/apiHelpers";
import type { Category, Service, Tour } from "../types";
import { buildTourPayload } from "@/services/guideService";

/** Thông tin phân trang đi kèm danh sách tour, ở khóa `meta` cạnh `data`. */
export interface TourListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

const META_MAC_DINH: TourListMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 12,
  total: 0,
};

const tourService = {
  /*
   * `data` vẫn là mảng tour như trước khi có phân trang, nên trang chủ và ô gợi ý tìm kiếm không
   * phải sửa gì. `meta` chỉ có ý nghĩa với màn hình danh sách; nơi nào không cần thì bỏ qua.
   */
  getAll: async (
    params?: Record<string, unknown>,
  ): Promise<{ data: Tour[]; meta: TourListMeta; success: boolean }> => {
    const response = await api.get("/tours", { params });
    const data = extractArray<Tour>(response);
    const meta: TourListMeta = response.data?.meta ?? {
      ...META_MAC_DINH,
      total: data.length,
    };

    return { success: true, data, meta };
  },

  getById: async (id: string): Promise<{ data: Tour; success: boolean }> => {
    const response = await api.get(`/tours/${id}`);
    const tour = extractObject<Tour>(response);

    if (!tour?.title) {
      throw new Error("Không tìm thấy tour.");
    }

    return { success: true, data: tour };
  },

  getReviews: async (tourId: number) => {
    const response = await api.get(`/reviews/${tourId}`);
    return response.data;
  },

  review: async (
    tourId: number,
    payload: {
      rating: number;
      comment: string;
    }
  ) => {
    const response = await api.post("/reviews", {
      tour_id: tourId,
      rating: payload.rating,
      comment: payload.comment,
    });

    return response.data;
  },

  deleteReview: async (id: number) => {
    return api.delete(`/reviews/${id}`);
  },

  getCategories: async (): Promise<Category[]> => {
    const response = await api.get("/categories");
    return extractArray<Category>(response);
  },

  getServices: async (): Promise<Service[]> => {
    const response = await api.get("/services");
    return extractArray<Service>(response);
  },

  createTour: async (payload: unknown): Promise<{ success: boolean }> => {
    const response = await api.post("/admin/tours", buildTourPayload(payload), {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return { success: response.data?.success !== false };
  },
};

export default tourService;