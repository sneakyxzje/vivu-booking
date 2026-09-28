import type { AttendancePassenger, PassengerCheckinStatus } from "../types/guide";

export type AttendanceRecord = { status: PassengerCheckinStatus; note: string };
export type AttendanceMap = Record<string, AttendanceRecord>;
export const recordKey = (checkpointId: number, passengerId: number) => `${checkpointId}:${passengerId}`;

/** Only explicit changes for passengers still in the manifest belong in a save request. */
export function changedPassengerIds(checkpointId: number, passengers: Pick<AttendancePassenger, "id">[], draft: AttendanceMap, saved: AttendanceMap): number[] {
  return passengers.filter(passenger => {
    const key = recordKey(checkpointId, passenger.id);
    const record = draft[key];
    return record && (record.status !== saved[key]?.status || record.note !== saved[key]?.note);
  }).map(passenger => passenger.id);
}
