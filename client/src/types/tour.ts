export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  is_active?: boolean;
  tours_count?: number;
}

// Payload dùng cho form Admin tạo/sửa danh mục tour
export interface CategoryPayload {
  name: string;
  description?: string;
  is_active: boolean;
}

export interface Service {
  id: number;
  name: string;
  description?: string | null;
  price?: number | null;
  is_active?: boolean;
  tours_count?: number;
}

// Payload dùng cho form Admin tạo/sửa dịch vụ
export interface ServicePayload {
  name: string;
  description?: string;
  price?: number | null;
  is_active: boolean;
}

export interface TourImage {
  id: number;
  tour_id: number;
  image_path: string;
}

/** Một điểm dừng của lịch trình, đúng các cột bảng `itinerary_checkpoints` có. */
export interface ItineraryCheckpoint {
  id: number;
  tour_itinerary_id?: number;
  name: string;
  description?: string | null;
  latitude?: string | number | null;
  longitude?: string | number | null;
  sequence: number;
  is_required_photo: boolean;
}

export interface TourItinerary {
  id: number;
  tour_id: number;
  day_number: number;
  title: string;
  start_point?: string | null;
  end_point?: string | null;
  route_points?: string | null;
  rest_stops?: string | null;
  content: string;
  /** Máy chủ kèm sẵn ở màn quản trị (`with('itineraries.checkpoints')`). */
  checkpoints?: ItineraryCheckpoint[];
}

export interface Assignee {
  id: number;
  name: string;
  email?: string;
  phone?: string | null;
  status?: string;
  /**
   * Dữ liệu bảng nối phân công.
   *
   * `accepted_at` null nghĩa là người này **vẫn đang được phân công** nhưng chưa trả lời — không
   * phải chưa gán. Điều hành cần thấy khác biệt đó để còn nhắc.
   */
  pivot?: { accepted_at?: string | null };
}

/** Một lần hướng dẫn viên từ chối chuyến, kèm lý do. */
export interface GuideDecline {
  id: number;
  guide_id: number;
  guide_name: string | null;
  reason: string;
  declined_at: string | null;
}

export interface TourSchedule {
  id: number;
  tour_id: number;
  start_date: string;
  /**
   * Ngày kết thúc chuyến. Cột có thật trên `tour_schedules` và máy chủ vẫn trả về, chỉ là kiểu ở
   * đây khai thiếu — nên trước giờ giao diện phải tự suy ngày về từ số ngày của tour.
   */
  end_date?: string;
  /**
   * Mốc đoàn tới điểm đến, và mốc rời điểm đến để về.
   *
   * Giờ áng chừng do điều hành điền, có thể rỗng — trang chi tiết giấu dòng nào rỗng thay vì đoán.
   */
  arrival_at?: string | null;
  return_departure_at?: string | null;
  max_people: number;
  booked_people: number;
  // Vòng đời chuyến khởi hành, khớp App\Enums\ScheduleStatus.
  // Không gộp active / inactive / full vào đây: đó là giá trị của tours.status.
  // Sau migration chuẩn hóa, chuyến không bao giờ mang ba giá trị đó nữa.
  status: "open" | "closed" | "confirmed" | "in_progress" | "completed" | "cancelled";
  min_people?: number;
  /**
   * Số khách của các đơn ĐÃ THANH TOÁN. Khác `booked_people`, vốn đếm cả chỗ đang giữ.
   *
   * Đây là con số lệnh nền so với `min_people` khi quyết có chốt chuyến hay không, nên màn hình
   * phải nhìn cùng con số ấy. Máy chủ chỉ kèm nó ở danh sách tour của quản trị.
   */
  paid_people?: number;
  booking_deadline?: string;
  cancelled_reason?: string | null;
  /**
   * Các hướng dẫn viên phụ trách chuyến.
   *
   * Nhiều người chứ không một: đoàn đông thì điểm danh ở nhiều điểm dừng cùng lúc, khách tách
   * nhóm khi tham quan. Bao nhiêu người là đủ thì điều hành quyết, hệ thống không suy ra hộ.
   */
  guides?: Assignee[];
}

export interface ExtendedSchedule extends TourSchedule {
  tour_title: string;
  tour_id: number;
  number_of_days: number;
}

export interface Tour {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  adult_price: number;
  child_price: number;
  infant_price: number;
  thumbnail: string | null;
  number_of_days: number;
  number_of_nights: number;
  start_location: string;
  end_location: string | null;
  vehicle_info?: string | null;
  pickup_location?: string | null;
  is_featured: boolean;
  status: "active" | "inactive" | "full";
  created_at?: string;
  updated_at?: string;
  admin_id?: number;
  categories?: Category[];
  services?: Service[];
  images?: TourImage[];
  itineraries?: TourItinerary[];
  schedules?: TourSchedule[];
  rating?: number;
  review_count?: number;
}

export interface TourFilterParams {
  start_location?: string;
  destination?: string;
  category?: string;
  number_of_days?: number;
  duration?: "1" | "2-3" | "4+";
  is_featured?: boolean;
  status?: "active" | "inactive" | "full";
  page?: number;
  per_page?: number;
}

