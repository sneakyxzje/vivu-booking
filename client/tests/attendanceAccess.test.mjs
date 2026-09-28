import test from 'node:test';
import assert from 'node:assert/strict';
import { attendanceAccess, attendanceDate, attendanceNow } from '../src/utils/attendanceAccess.ts';

const schedule = { can_record: true, status: 'in_progress', recording_ends_at: '2026-10-05T18:00:00+07:00' };
const point = day => ({ attendance_date: `2026-10-0${day}` });

test('only today is editable; yesterday and tomorrow remain read only', () => {
  const now = Date.parse('2026-10-04T10:00:00+07:00');
  assert.match(attendanceAccess(schedule, point(3), now), /đã qua/);
  assert.equal(attendanceAccess(schedule, point(4), now), null);
  assert.match(attendanceAccess(schedule, point(5), now), /chưa tới/);
});

test('the server clock crosses Vietnamese midnight without consulting the device date', () => {
  const clock = { timestamp: Date.parse('2026-10-03T16:59:59Z'), receivedAt: 100 };
  const before = attendanceNow(clock, 1099);
  const after = attendanceNow(clock, 1100);
  assert.equal(attendanceDate(before), '2026-10-03');
  assert.equal(attendanceDate(after), '2026-10-04');
  assert.equal(attendanceAccess(schedule, point(3), before), null);
  assert.match(attendanceAccess(schedule, point(3), after), /đã qua/);
  assert.equal(attendanceAccess(schedule, point(4), after), null);
});

test('completed, cancelled, not yet departed and unsynchronized screens cannot write', () => {
  const now = Date.parse('2026-10-04T10:00:00+07:00');
  for (const changes of [{ status: 'completed' }, { status: 'cancelled' }, { can_record: false }]) {
    assert.notEqual(attendanceAccess({ ...schedule, ...changes }, point(4), now), null);
  }
  assert.notEqual(attendanceAccess(schedule, point(4), attendanceNow(null, 500)), null);
  assert.notEqual(attendanceAccess(schedule, null, now), null);
});

test('the end of the departure locks the current day without waiting for a reload', () => {
  assert.equal(attendanceAccess(schedule, point(5), Date.parse(schedule.recording_ends_at)), null);
  assert.match(attendanceAccess(schedule, point(5), Date.parse(schedule.recording_ends_at) + 1), /kết thúc/);
});
