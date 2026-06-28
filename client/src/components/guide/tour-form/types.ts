import type { ReactNode } from "react";

export interface SelectOption {
  id: number;
  name: string;
}

export interface ItineraryFormItem {
  day_number: string;
  title: string;
  content: string;
}

export interface ScheduleFormItem {
  start_date: string;
  max_people: string;
}

export interface TourFormState {
  title: string;
  description: string;
  price: string;
  discount_price: string;
  thumbnail: string;
  number_of_days: string;
  number_of_nights: string;
  start_location: string;
  end_location: string;
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
