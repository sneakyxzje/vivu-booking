import type { Tour } from "./tour";

export interface GuideDashboardStats {
  totalTours: number;
  activeTours: number;
  fullTours: number;
  totalBookings: number;
  pendingBookings: number;
  revenue: number;
}

export type BookingStatus = "pending" | "confirmed" | "cancelled";

export interface GuideBooking {
  id: number;
  tour_id: number;
  tour_title: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  departure_date: string;
  guests: number;
  total_amount: number;
  status: BookingStatus;
  created_at: string;
}

/**
 * Hồ sơ năng lực — trả lời "ai phù hợp", khác với "ai đang rảnh".
 *
 * Mọi trường đều để trống được, và **không trường nào chặn được phân công**. Hồ sơ chỉ xếp thứ tự
 * và nhắc; luật chặn duy nhất vẫn là chống trùng lịch.
 */
export interface GuideProfile {
  languages: string[] | null;
  regions: string[] | null;
  max_group_size: number | null;
  note: string | null;
}

export interface GuideProfilePayload extends GuideProfile {
  /** Loại hình tour người này chuyên, tham chiếu danh mục thật của tour. */
  category_ids: number[];
}

export interface Guide {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  address: string | null;
  avatar: string | null;
  status: "active" | "inactive" | "blocked";
  assigned_tours_count: number;
  created_at: string;
  guide_profile?: GuideProfile | null;
  guide_categories?: { id: number; name: string }[];
}

/**
 * Một hướng dẫn viên đã được chấm cho một chuyến cụ thể.
 *
 * `blocked_reason` là câu **máy chủ sẽ từ chối bằng đúng câu đó** nếu vẫn cố lưu — không phải một
 * lời khuyên. Hiện chỉ có đúng một nguyên nhân: trùng lịch. Còn `warnings` thì ngược lại: nói ra
 * rồi để điều hành quyết.
 */
export interface GuideSuitability {
  id: number;
  name: string;
  phone: string | null;
  assigned: boolean;
  blocked_reason: string | null;
  score: number;
  matches: string[];
  warnings: string[];
  workload: number;
  languages: string[];
}

/*
 * Điểm danh.
 *
 * Mô hình là từng hành khách tại từng điểm dừng, không phải từng đơn theo ngày. Một đơn hai
 * người thì hai người điểm danh riêng, và một ngày hành trình có thể có nhiều điểm dừng.
 *
 * Các kiểu dưới đây phải khớp với phản hồi thật của
 * server/app/Http/Controllers/Api/Guide/AttendanceController.php. Khai sai ở đây thì TypeScript
 * kiểm mã dựa trên hợp đồng tưởng tượng: build vẫn xanh trong khi màn hình hỏng lúc chạy.
 */

export type PassengerCheckinStatus =
  | "present"
  | "absent"
  | "late"
  | "left_early"
  | "excused";

export interface AttendanceItinerary {
  id: number;
  day_number: number;
  title: string;
  start_point?: string | null;
  end_point?: string | null;
  route_points?: string | null;
}

export interface AttendanceCheckpoint {
  id: number;
  tour_itinerary_id: number;
  name: string;
  description?: string | null;
  /** Cột decimal nên Laravel trả về chuỗi, không phải số. */
  latitude?: string | number | null;
  longitude?: string | number | null;
  sequence: number;
  is_required_photo: boolean;
  tour_itinerary?: AttendanceItinerary | null;
}

export interface AttendancePassenger {
  id: number;
  booking_id: number;
  name: string;
  type: "adult" | "child" | "infant";
  note?: string | null;
}

/** Một đơn đặt chỗ trong danh sách đoàn, kèm từng người đi. */
export interface AttendanceBooking {
  id: number;
  customer_name: string;
  customer_phone: string | null;
  guests: number;
  adult_count?: number;
  child_count?: number;
  infant_count?: number;
  passengers: AttendancePassenger[];
}

export interface AttendanceCheckin {
  id: number;
  booking_passenger_id: number;
  tour_schedule_id: number;
  itinerary_checkpoint_id: number;
  status: PassengerCheckinStatus;
  note?: string | null;
  checked_at?: string | null;
  is_late_entry?: boolean;
  booking_passenger?: Pick<AttendancePassenger, "id" | "name" | "type" | "booking_id"> | null;
  itinerary_checkpoint?: Pick<AttendanceCheckpoint, "id" | "name" | "tour_itinerary_id"> | null;
}

export interface CheckpointPhoto {
  id: number;
  tour_itinerary_id: number;
  itinerary_checkpoint_id: number | null;
  image_path: string;
  latitude?: string | number | null;
  longitude?: string | number | null;
  captured_at?: string | null;
  created_at?: string;
  checkpoint?: Pick<AttendanceCheckpoint, "id" | "name"> | null;
}

export interface AttendanceData {
  schedule: {
    id: number;
    start_date: string;
    max_people: number;
    booked_people: number;
  };
  tour: {
    id: number;
    title: string;
    number_of_days: number;
  };
  checkpoints: AttendanceCheckpoint[];
  bookings: AttendanceBooking[];
  checkins: AttendanceCheckin[];
  photos: CheckpointPhoto[];
}

/** Một dòng gửi lên khi lưu điểm danh tại một điểm dừng. */
export interface AttendanceCheckinInput {
  booking_passenger_id: number;
  status: PassengerCheckinStatus;
  note?: string | null;
}

export interface SaveAttendanceResult {
  saved: number;
  created: number;
  updated: number;
  checkpoint: { id: number; name: string };
  checkins: AttendanceCheckin[];
}

export interface UploadCheckinPhotoResult {
  photo: CheckpointPhoto;
  distance_meters: number;
  warning: boolean;
  warning_message: string | null;
}

export type { Tour };





