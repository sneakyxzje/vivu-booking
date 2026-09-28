export interface DemoClockValue {
  now: string;
  real_now: string;
}

/** The API supplies the offset; wall clock remains unchanged for authentication/payments. */
export function businessNow(value?: { demo_clock?: DemoClockValue | null } | null): number {
  const clock = value?.demo_clock;
  return Date.now() + (clock ? Date.parse(clock.now) - Date.parse(clock.real_now) : 0);
}
