import type { ReactNode } from "react";

export interface SelectOption {
  id: number;
  name: string;
}

export interface ItineraryFormItem {
  id?: number;
  day_number: string;
  title: string;
  start_point: string;
  end_point: string;
  route_points: string[];
  rest_stops: string;
  content: string;
}

export interface ScheduleFormItem {
  id?: number;
  start_date: string;
  max_people: string;
  min_people: string;
  booking_deadline: string;
  status: string;
  guide_id: string;
}

export interface TourFormState {
  title: string;
  description: string;
  adult_price: string;
  child_price: string;
  infant_price: string;
  thumbnail: string;
  number_of_days: string;
  number_of_nights: string;
  start_location: string;
  end_location: string;
  vehicle_info: string;
  pickup_location: string;
  thumbnail_file: File | null;
  thumbnail_preview: string;
  images: File[];
  image_previews: string[];
  itineraries: ItineraryFormItem[];
  schedules: ScheduleFormItem[];
  category_ids: number[];
  service_ids: number[];
}

export interface TourFormSectionProps {
  labelClass: string;
  fieldClass: string;
}

export interface TourFormSummaryProps {
  title: string;
  previewPrice: string;
  startLocation: string;
  endLocation: string;
  thumbnailPreview: string;
  thumbnailUrl: string;
  selectedCategories: SelectOption[];
  selectedServices: SelectOption[];
  imageCount: number;
  numberOfDays: string;
  numberOfNights: string;
}

export type IconNode = ReactNode;

