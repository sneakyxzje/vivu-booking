export interface Category {
  id: number;
  name: string;
  slug: string;
  icon?: string;
  is_active?: boolean;
}

export interface Service {
  id: number;
  name: string;
  icon?: string;
  description?: string;
}

export interface TourImage {
  id: number;
  tour_id: number;
  image_path: string;
}

export interface TourItinerary {
  id: number;
  tour_id: number;
  day_number: number;
  title: string;
  description: string;
}

export interface TourSchedule {
  id: number;
  tour_id: number;
  start_date: string;
  end_date: string;
  price: number;
  min_participants: number;
  max_participants: number;
  booked_count: number;
}

export interface Tour {
  id: number;
  host_id: number;
  title: string;
  slug: string;
  description: string | null;
  price: number;
  discount_price: number | null;
  thumbnail: string | null;
  number_of_days: number;
  number_of_nights: number;
  start_location: string;
  end_location: string | null;
  is_featured: boolean;
  status: "pending" | "active" | "inactive";
  created_at?: string;
  updated_at?: string;
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
  status?: "pending" | "active" | "inactive";
  page?: number;
  per_page?: number;
}
