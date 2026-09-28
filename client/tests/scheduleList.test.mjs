import test from 'node:test';
import assert from 'node:assert/strict';
import { filterScheduleList, scheduleView, scheduleStatus } from '../src/utils/scheduleList.ts';

const trip = (id, date, status = 'open', extra = {}) => ({
  id, start_date: `${date} 08:00:00`, status, tour_id: 1,
  tour_title: 'Đà Nẵng – Hội An', guides: [], ...extra,
});
const filters = { view: 'upcoming', query: '', status: 'all', dateRange: null, unassigned: false };

test('admin tabs, labels and filters use server effective status when the stored state is behind', () => {
  const running = trip(1, '2026-10-01', 'open', { effective_status: 'in_progress' });
  const ended = trip(2, '2026-09-01', 'confirmed', { effective_status: 'completed' });
  assert.equal(scheduleStatus(running), 'in_progress');
  assert.equal(scheduleView(running), 'running');
  assert.deepEqual(filterScheduleList([running, ended], filters), []);
  assert.deepEqual(filterScheduleList([running, ended], { ...filters, view: 'running' }).map(s => s.id), [1]);
  assert.deepEqual(filterScheduleList([running, ended], { ...filters, view: 'history', status: 'completed' }).map(s => s.id), [2]);
  assert.equal(running.status, 'open');
});

test('upcoming trips show nearest departure first and keep history separate', () => {
  const rows = [trip(2, '2026-10-05'), trip(1, '2026-10-03', 'confirmed'), trip(3, '2026-10-01', 'cancelled')];
  assert.deepEqual(filterScheduleList(rows, filters).map(s => s.id), [1, 2]);
  assert.equal(rows[0].id, 2);
  assert.equal(scheduleView(trip(4, '2026-10-01', 'in_progress')), 'running');
});

test('history shows recent completed and cancelled trips first', () => {
  const rows = [trip(1, '2026-09-01', 'completed'), trip(2, '2026-09-05', 'cancelled'), trip(3, '2026-09-06')];
  assert.deepEqual(filterScheduleList(rows, { ...filters, view: 'history' }).map(s => s.id), [2, 1]);
});

test('search accepts Vietnamese without accents and a trip number with #', () => {
  const rows = [trip(123, '2026-10-01'), trip(456, '2026-10-02', 'open', { tour_title: 'Huế' })];
  assert.deepEqual(filterScheduleList(rows, { ...filters, query: '  da nang  ' }).map(s => s.id), [123]);
  assert.deepEqual(filterScheduleList(rows, { ...filters, query: '#456' }).map(s => s.id), [456]);
});

test('date range includes both endpoints regardless of departure time', () => {
  const rows = [trip(1, '2026-10-01'), trip(2, '2026-10-02'), trip(3, '2026-10-03'), trip(4, '2026-10-04')];
  assert.deepEqual(filterScheduleList(rows, { ...filters, dateRange: ['2026-10-02', '2026-10-03'] }).map(s => s.id), [2, 3]);
});

test('tour, status and missing guide filters combine without treating pending acceptance as unassigned', () => {
  const rows = [trip(1, '2026-10-01', 'confirmed'), trip(2, '2026-10-01', 'confirmed', { guides: [{ id: 1 }] }),
    trip(3, '2026-10-01'), trip(4, '2026-10-01', 'confirmed', { tour_id: 2 })];
  assert.deepEqual(filterScheduleList(rows, { ...filters, tourId: 1, status: 'confirmed', unassigned: true }).map(s => s.id), [1]);
});
