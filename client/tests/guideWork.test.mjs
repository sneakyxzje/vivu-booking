import test from 'node:test';
import assert from 'node:assert/strict';
import { guideTrips, guideTourGroups, isTripToday, todayAttendanceProgress } from '../src/utils/guideWork.ts';

const schedule = (id, status, start_date) => ({ id, status, start_date });
const tour = (id, schedules) => ({ id, title: `Tour Đà Nẵng ${id}`, start_location: 'Hà Nội', end_location: 'Đà Nẵng', schedules });

test('running departures take priority while the remaining departures stay newest first', () => {
  const data = [tour(1, [schedule(1, 'open', '2026-12-01')]), tour(2, [
    schedule(2, 'completed', '2026-09-01'), schedule(3, 'open', '2026-11-01'), schedule(4, 'in_progress', '2026-10-01'),
  ])];
  const groups = guideTourGroups(data, 'active', '');
  assert.deepEqual(groups.map(group => group.tour.id), [2, 1]);
  assert.deepEqual(groups[0].trips.map(trip => trip.schedule.id), [4, 3]);
  assert.deepEqual(guideTourGroups(data, 'history', '')[0].trips.map(trip => trip.schedule.id), [2]);
});

test('effective status determines running/history, and cancelled departures remain viewable', () => {
  const data = [tour(1, [{ ...schedule(1, 'open', '2026-10-01'), effective_status: 'completed' }, schedule(2, 'cancelled', '2026-12-01')])];
  assert.equal(guideTourGroups(data, 'active', '').length, 0);
  assert.equal(guideTourGroups(data, 'history', '')[0].trips.length, 2);
  assert.equal(guideTourGroups(data, 'running', '').length, 0);
});

test('search supports unaccented Vietnamese and departure numbers', () => {
  const data = [tour(1, [schedule(42, 'open', '2026-10-01'), schedule(43, 'open', '2026-10-02')])];
  assert.equal(guideTourGroups(data, 'all', 'da nang').length, 1);
  assert.deepEqual(guideTourGroups(data, 'all', '42')[0].trips.map(trip => trip.schedule.id), [42]);
});

test('today includes a multiday running trip and uses server Vietnamese date for departures', () => {
  const trips = guideTrips([tour(1, [
    schedule(1, 'in_progress', '2026-10-01T08:00:00+07:00'),
    { ...schedule(2, 'open', '2026-10-04T08:00:00+07:00'), server_now: '2026-10-03T17:30:00Z' },
    { ...schedule(3, 'cancelled', '2026-10-04T08:00:00+07:00'), server_now: '2026-10-04T00:30:00+07:00' },
  ])]);
  assert.deepEqual(trips.map(trip => isTripToday(trip, Date.parse('2026-09-01'))), [true, true, false]);
});

const attendance = () => ({
  schedule: { server_now: '2026-10-04T08:00:00+07:00' },
  checkpoints: [{ id: 1, attendance_date: '2026-10-03', is_required_photo: false },
    { id: 2, attendance_date: '2026-10-04', is_required_photo: true }],
  bookings: [{ passengers: [{ id: 10 }, { id: 11 }] }],
  checkins: [{ itinerary_checkpoint_id: 1, booking_passenger_id: 10 },
    { itinerary_checkpoint_id: 2, booking_passenger_id: 10 }, { itinerary_checkpoint_id: 2, booking_passenger_id: 11 }],
  photos: [],
});

test('today progress excludes past points and requires both attendance and required photos', () => {
  const data = attendance();
  assert.deepEqual(todayAttendanceProgress(data), { total: 1, complete: 0, remaining: 1, passengers: 2, missingPassengerLists: 0 });
  data.photos.push({ itinerary_checkpoint_id: 2 });
  assert.equal(todayAttendanceProgress(data).complete, 1);
});

test('duplicate checkins and undeclared bookings never make a checkpoint look completed', () => {
  const data = attendance();
  data.photos.push({ itinerary_checkpoint_id: 2 });
  data.checkins[2].booking_passenger_id = 10;
  assert.equal(todayAttendanceProgress(data).complete, 0);
  data.checkins[2].booking_passenger_id = 11;
  data.bookings.push({ passengers: [] });
  assert.equal(todayAttendanceProgress(data).complete, 0);
  assert.equal(todayAttendanceProgress(data).missingPassengerLists, 1);
});
