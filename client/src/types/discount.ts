export type DiscountCodeType = "percent" | "fixed";

export interface DiscountCode {
  id: number;
  code: string;
  name: string;
  type: DiscountCodeType;
  value: number;
  minimum_order_amount: number;
  max_discount_amount: number | null;
  usage_limit: number | null;
  used_count: number;
  starts_at: string | null;
  expires_at: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export type DiscountCodePayload = Omit<
  DiscountCode,
  "id" | "used_count" | "created_at" | "updated_at"
>;
