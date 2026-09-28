import api from "./api";
import type { PassengerPayload } from "./bookingService";

export interface PassengerSupplement {
  id: number;
  tour_schedule_id: number;
  passengers: (PassengerPayload & { id: number })[];
  reported_by: string;
  reason: string;
  created_by_name: string;
  recorded_at: string;
  sent_at: string | null;
  sent_by_name: string | null;
  sent_to: string | null;
  sent_note: string | null;
}

export interface SupplementContext {
  guests: number;
  declared: number;
  missing: number;
  remaining: Record<PassengerPayload["type"], number>;
  is_group: boolean;
  can_supplement: boolean;
  unavailable_reason: string | null;
  deadline_passed: boolean;
  supplements: PassengerSupplement[];
}

export type SupplementInput = {
  passengers: PassengerPayload[];
  reported_by: string;
  reason: string;
};

const path = (bookingId: number) => `/admin/bookings/${bookingId}/passenger-supplements`;
export const passengerSupplementService = {
  async get(bookingId: number, signal?: AbortSignal): Promise<SupplementContext> {
    return (await api.get(path(bookingId), { signal })).data.data;
  },
  async append(bookingId: number, payload: SupplementInput & { request_key: string }): Promise<void> {
    await api.post(path(bookingId), payload);
  },
  async markSent(bookingId: number, id: number, payload: { sent_to: string; sent_note?: string }): Promise<void> {
    await api.post(`${path(bookingId)}/${id}/sent`, payload);
  },
  async download(bookingId: number, id: number): Promise<void> {
    const result = await api.get(`${path(bookingId)}/${id}/export`, { responseType: "blob" });
    const url = URL.createObjectURL(result.data);
    const link = document.createElement("a");
    link.href = url;
    link.download = `bo-sung-BK${bookingId}-${id}.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  },
};
