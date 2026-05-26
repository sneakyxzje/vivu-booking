import api from "./api";
import type { Tour } from "../types";

const mockTours: Tour[] = [
  {
    id: 1,
    host_id: 2,
    title: "Tour Du Thuyền 5 Sao Vịnh Hạ Long - Khám Phá Kỳ Quan Thiên Nhiên",
    slug: "tour-du-thuyen-5-sao-vinh-ha-long",
    description:
      "Trải nghiệm kỳ nghỉ dưỡng đẳng cấp trên du thuyền sang trọng 5 sao tại Vịnh Hạ Long. Khám phá Hang Sửng Sốt, chèo thuyền kayak tại Hang Luồn và ngắm hoàng hôn tuyệt đẹp trên boong tàu.",
    price: 3500000,
    discount_price: 2990000,
    thumbnail:
      "https://images.unsplash.com/photo-1528127269322-539801943592?auto=format&fit=crop&w=800&q=80",
    number_of_days: 2,
    number_of_nights: 1,
    start_location: "Hà Nội",
    end_location: "Hạ Long",
    is_featured: true,
    status: "active",
    rating: 4.9,
    review_count: 85,
    categories: [{ id: 1, name: "Tour nghỉ dưỡng", slug: "tour-nghi-duong" }],
    services: [
      { id: 1, name: "Ăn uống theo chương trình", icon: "restaurant" },
      { id: 2, name: "Vé tham quan", icon: "receipt" },
      { id: 3, name: "Hướng dẫn viên", icon: "support_agent" },
    ],
  },
  {
    id: 2,
    host_id: 2,
    title: "Tour Sa Pa 3 Ngày 2 Đêm - Chinh Phục Nóc Nhà Đông Dương Fansipan",
    slug: "tour-sa-pa-3-ngay-2-dem-fansipan",
    description:
      "Hành trình khám phá thị trấn sương mù Sa Pa bản Cát Cát thơ mộng và trải nghiệm cáp treo kỷ lục thế giới để chạm tay vào đỉnh Fansipan huyền thoại ở độ cao 3.143m.",
    price: 4200000,
    discount_price: 3450000,
    thumbnail:
      "https://images.unsplash.com/photo-1508873696983-2df519f0397e?auto=format&fit=crop&w=800&q=80",
    number_of_days: 3,
    number_of_nights: 2,
    start_location: "Hà Nội",
    end_location: "Sa Pa",
    is_featured: true,
    status: "active",
    rating: 4.8,
    review_count: 120,
    categories: [{ id: 2, name: "Tour leo núi", slug: "tour-leo-nui" }],
    services: [
      { id: 1, name: "Khách sạn 3 sao", icon: "hotel" },
      { id: 2, name: "Xe đưa đón khứ hồi", icon: "directions_bus" },
      { id: 3, name: "Vé cáp treo Fansipan", icon: "local_activity" },
    ],
  },
  {
    id: 3,
    host_id: 3,
    title: "Tour Đà Nẵng - Hội An - Bà Nà Hills 4 Ngày 3 Đêm Siêu Khuyến Mãi",
    slug: "tour-da-nang-hoi-an-ba-na-hills-4n3d",
    description:
      "Khám phá con đường di sản Miền Trung với Ngũ Hành Sơn hùng vĩ, phố cổ Hội An rực rỡ đèn lồng và tiên cảnh Bà Nà Hills cùng Cầu Vàng nổi tiếng thế giới.",
    price: 5800000,
    discount_price: 4990000,
    thumbnail:
      "https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=800&q=80",
    number_of_days: 4,
    number_of_nights: 3,
    start_location: "TP. Hồ Chí Minh",
    end_location: "Đà Nẵng",
    is_featured: true,
    status: "active",
    rating: 4.7,
    review_count: 98,
    categories: [{ id: 3, name: "Tour văn hóa", slug: "tour-van-hoa" }],
    services: [
      { id: 1, name: "Khách sạn 4 sao", icon: "hotel" },
      { id: 2, name: "Vé buffet Bà Nà", icon: "restaurant" },
      { id: 3, name: "Vé bay khứ hồi", icon: "flight" },
    ],
  },
  {
    id: 4,
    host_id: 3,
    title:
      "Tour Phú Quốc 3 Ngày 2 Đêm - Khám Phá Cano 4 Đảo & Cáp Treo Hòn Thơm",
    slug: "tour-phu-quoc-3-ngay-2-dem-cano-4-dao",
    description:
      "Tận hưởng thiên đường đảo ngọc Phú Quốc với làn nước xanh trong vắt tại Hòn Móng Tay, Hòn Gầm Ghì câu cá ngắm san hô và đi cáp treo vượt biển dài nhất thế giới.",
    price: 3900000,
    discount_price: 3200000,
    thumbnail:
      "https://images.unsplash.com/photo-1540206395-68808572332f?auto=format&fit=crop&w=800&q=80",
    number_of_days: 3,
    number_of_nights: 2,
    start_location: "TP. Hồ Chí Minh",
    end_location: "Phú Quốc",
    is_featured: true,
    status: "active",
    rating: 4.9,
    review_count: 142,
    categories: [{ id: 4, name: "Tour biển đảo", slug: "tour-bien-dao" }],
    services: [
      { id: 1, name: "Cano vận chuyển đảo", icon: "directions_boat" },
      { id: 2, name: "Chụp ảnh & Flycam miễn phí", icon: "photo_camera" },
      { id: 3, name: "Ăn trưa hải sản", icon: "restaurant" },
    ],
  },
  {
    id: 5,
    host_id: 4,
    title: "Tour Vòng Cung Đông Bắc Hà Giang - Đồng Văn - Mèo Vạc 3 Ngày 2 Đêm",
    slug: "tour-ha-giang-dong-van-meo-vac-3n2d",
    description:
      "Hành trình chinh phục những cung đường đèo hiểm trở bậc nhất Việt Nam: Đèo Mã Pí Lèng, Dinh thự họ Vương cổ kính, Cột cờ Lũng Cú thiêng liêng và đi thuyền trên sông Nho Quế.",
    price: 2800000,
    discount_price: 2450000,
    thumbnail:
      "https://images.unsplash.com/photo-1605538032432-a9f0c8d9baac?auto=format&fit=crop&w=800&q=80",
    number_of_days: 3,
    number_of_nights: 2,
    start_location: "Hà Nội",
    end_location: "Hà Giang",
    is_featured: false,
    status: "active",
    rating: 4.9,
    review_count: 76,
    categories: [{ id: 2, name: "Tour leo núi", slug: "tour-leo-nui" }],
    services: [
      { id: 1, name: "Xe limousine đưa đón", icon: "airport_shuttle" },
      { id: 2, name: "Homestay bản địa", icon: "home" },
      { id: 3, name: "Vé thuyền Sông Nho Quế", icon: "sailing" },
    ],
  },
  {
    id: 6,
    host_id: 4,
    title:
      "Tour Ninh Bình: Hoa Lư - Tràng An - Hang Múa Khám Phá Trọn Gói 1 Ngày",
    slug: "tour-ninh-binh-trang-an-hang-mua-1-ngay",
    description:
      "Chỉ trong 1 ngày, chiêm ngưỡng cố đô Hoa Lư lịch sử, ngồi thuyền nan len lỏi qua các hang động kỳ bí ở Tràng An và leo 500 bậc đá ngắm toàn cảnh Tam Cốc tuyệt đẹp từ đỉnh Hang Múa.",
    price: 1200000,
    discount_price: 950000,
    thumbnail:
      "https://images.unsplash.com/photo-1599707367072-cd6ada2bc375?auto=format&fit=crop&w=800&q=80",
    number_of_days: 1,
    number_of_nights: 0,
    start_location: "Hà Nội",
    end_location: "Ninh Bình",
    is_featured: false,
    status: "active",
    rating: 4.6,
    review_count: 64,
    categories: [{ id: 3, name: "Tour văn hóa", slug: "tour-van-hoa" }],
    services: [
      { id: 1, name: "Xe du lịch đời mới", icon: "directions_bus" },
      { id: 2, name: "Bữa trưa đặc sản dê núi", icon: "restaurant" },
      { id: 3, name: "Vé đò Tràng An", icon: "rowing" },
    ],
  },
];

const tourService = {
  getAll: async (
    params?: Record<string, unknown>,
  ): Promise<{ data: Tour[]; success: boolean; isMock?: boolean }> => {
    try {
      const response = await api.get("/tours", { params });

      const responseData = response.data;

      if (responseData) {
        // Trường hợp API trả về mảng trực tiếp
        if (
          Array.isArray(responseData) &&
          responseData.length > 0 &&
          typeof responseData[0] === "object" &&
          "title" in responseData[0]
        ) {
          return { success: true, data: responseData };
        }
        // Trường hợp API trả về đối tượng có thuộc tính data là mảng chứa các tours thực tế
        if (
          responseData.data &&
          Array.isArray(responseData.data) &&
          responseData.data.length > 0 &&
          typeof responseData.data[0] === "object" &&
          "title" in responseData.data[0]
        ) {
          return { success: true, data: responseData.data };
        }
        // Trường hợp API trả về { success: true, data: [...] }
        if (
          responseData.success &&
          Array.isArray(responseData.data) &&
          responseData.data.length > 0
        ) {
          return { success: true, data: responseData.data };
        }
      }

      // Nếu là placeholder (ví dụ: { success: true, message: "Placeholder..." }), tự động fallback về mock data
      return {
        success: true,
        data: mockTours,
        isMock: true,
      };
    } catch (error) {
      console.warn(
        "Không thể kết nối API /tours, sử dụng dữ liệu mẫu (mock data):",
        error,
      );
      return {
        success: true,
        data: mockTours,
        isMock: true,
      };
    }
  },

  getById: async (
    id: number,
  ): Promise<{ data: Tour; success: boolean; isMock?: boolean }> => {
    try {
      const response = await api.get(`/tours/${id}`);
      if (response.data && response.data.title) {
        return { success: true, data: response.data };
      }
      if (response.data && response.data.data && response.data.data.title) {
        return { success: true, data: response.data.data };
      }

      // Fallback
      const tour = mockTours.find((t) => t.id === id) || mockTours[0];
      return { success: true, data: tour, isMock: true };
    } catch (error) {
      const tour = mockTours.find((t) => t.id === id) || mockTours[0];
      return { success: true, data: tour, isMock: true };
    }
  },

  review: (tourId: number, payload: { rating: number; comment: string }) =>
    api.post(`/tours/${tourId}/reviews`, payload),
};

export default tourService;
export { mockTours };
