import test from 'node:test';
import assert from 'node:assert/strict';
import { assignmentView, filterAssignments, guideSearchText } from '../src/utils/guideAssignments.ts';
import { changedPassengerIds } from '../src/utils/attendanceDraft.ts';

const trip = (schedule_id, status, start_date, accepted_at = null) => ({ schedule_id, status, start_date, accepted_at, tour_title: 'Đà Nẵng', co_guides: ['Trần Hồng'] });

test('old unacknowledged assignments are history, not work waiting for a response', () => {
  const items = [trip(1, 'completed', '2026-09-01'), trip(2, 'cancelled', '2026-12-01'), trip(3, 'open', '2026-10-01'), trip(4, 'confirmed', '2026-11-01', '2026-09-01')];
  assert.deepEqual(items.map(assignmentView), ['history', 'history', 'pending', 'accepted']);
  assert.deepEqual(filterAssignments(items, 'pending', '').map(item => item.schedule_id), [3]);
  assert.deepEqual(filterAssignments(items, 'history', '').map(item => item.schedule_id), [2, 1]);
});

test('running assignments come first and upcoming work is ordered by departure', () => {
  const items = [trip(1, 'confirmed', '2026-11-01'), trip(2, 'open', '2026-10-10'), trip(3, 'in_progress', '2026-10-01')];
  assert.deepEqual(filterAssignments(items, 'pending', '').map(item => item.schedule_id), [3, 2, 1]);
});

test('assignment search accepts Vietnamese without accents, guide names and trip IDs', () => {
  const items = [trip(53, 'open', '2026-10-01')];
  for (const query of ['da nang', 'ĐÀ NẴNG', 'tran hong', '#53']) assert.equal(filterAssignments(items, 'pending', query).length, 1);
  assert.equal(guideSearchText(' ĐẶNG Thị Hà '), 'dang thi ha');
  assert.equal(filterAssignments(items, 'pending', 'không có').length, 0);
});

const present = { status: 'present', note: '' };
const absent = { status: 'absent', note: 'Khách đang nghỉ tại khách sạn' };
const passengers = [{ id: 1 }, { id: 2 }, { id: 3 }];

test('saving sends only explicitly changed passengers at the current point', () => {
  const saved = { '10:1': present };
  const draft = { ...saved, '10:2': absent, '11:3': present, '10:999': present };
  assert.deepEqual(changedPassengerIds(10, passengers, draft, saved), [2]);
  assert.deepEqual(changedPassengerIds(11, passengers, draft, saved), [3]);
  assert.deepEqual(changedPassengerIds(12, passengers, draft, saved), []);
});

test('editing only a reason is a change; reverting a status or completing a save clears it', () => {
  const saved = { '10:1': present, '10:2': absent };
  const draft = { ...saved, '10:2': { ...absent, note: 'Khách xin phép tự đi riêng' } };
  assert.deepEqual(changedPassengerIds(10, passengers, draft, saved), [2]);
  assert.deepEqual(changedPassengerIds(10, passengers, draft, draft), []);
  assert.deepEqual(changedPassengerIds(10, passengers, { ...saved, '10:1': { ...present } }, saved), []);
});
