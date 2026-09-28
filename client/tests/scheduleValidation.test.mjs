import test from 'node:test';
import assert from 'node:assert/strict';
import { scheduleErrors } from '../src/components/guide/tour-form/scheduleValidation.ts';

const trip = (changes = {}) => ({ uid: 'trip-1', start_date: '2026-10-03T08:00', end_date: '2026-10-05T18:00',
  arrival_at: '', return_departure_at: '', booking_deadline: '2026-09-30T08:00', min_people: '10', max_people: '20', ...changes });

test('returning home before leaving the destination points to the return field', () => {
  const errors = scheduleErrors(trip({ return_departure_at: '2026-10-07T00:00' }));
  assert.equal(errors.end_date, 'Giờ về tới nơi phải bằng hoặc sau giờ khởi hành về.');
});

test('optional milestones may be empty and equal consecutive times are allowed', () => {
  assert.deepEqual(scheduleErrors(trip()), {});
  assert.deepEqual(scheduleErrors(trip({ arrival_at: '2026-10-03T08:00', return_departure_at: '2026-10-05T18:00' })), {});
});

test('arrival before departure and missing return date are flagged on their inputs', () => {
  assert.ok(scheduleErrors(trip({ arrival_at: '2026-10-02T08:00' })).arrival_at);
  assert.ok(scheduleErrors(trip({ end_date: '' })).end_date);
});

test('departure duplicates compare both day and time, not day alone', () => {
  assert.ok(scheduleErrors(trip(), [trip({ uid: 'other' })]).start_date);
  assert.deepEqual(scheduleErrors(trip(), [trip({ uid: 'other', start_date: '2026-10-03T09:00' })]), {});
});

test('deadline must precede departure and changing an existing deadline needs a reason', () => {
  assert.ok(scheduleErrors(trip({ booking_deadline: '2026-10-03T08:00' })).booking_deadline);
  assert.ok(scheduleErrors(trip({ id: 1, booking_deadline_goc: '2026-09-29T08:00' })).booking_deadline_reason);
  assert.deepEqual(scheduleErrors(trip({ id: 1, booking_deadline_goc: '2026-09-29T08:00', booking_deadline_reason: 'Nhà xe đổi ngày chốt danh sách.' })), {});
});

test('capacity and target must be positive integers and target cannot exceed capacity', () => {
  assert.ok(scheduleErrors(trip({ max_people: '0' })).max_people);
  assert.ok(scheduleErrors(trip({ min_people: '1.5' })).min_people);
  assert.ok(scheduleErrors(trip({ min_people: '21' })).min_people);
});
