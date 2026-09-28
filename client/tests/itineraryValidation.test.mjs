import test from 'node:test';
import assert from 'node:assert/strict';
import { fillItineraryDays, itineraryErrors, ngayRong } from '../src/components/guide/tour-form/itineraryValidation.ts';

const written = (day) => ({ ...ngayRong(day), title: `Ngày ${day}`, content: 'Tham quan và nghỉ ngơi.' });

test('increasing duration creates all missing days without changing existing content and ids', () => {
  const first = { ...written(1), id: 15, checkpoints: [{ id: 8, name: 'Điểm đón' }] };
  const result = fillItineraryDays([first], 3);
  assert.deepEqual(result.map(item => item.day_number), ['1', '2', '3']);
  assert.equal(result[0], first);
  assert.equal(result[1].title, '');
  assert.equal(result[2].content, '');
});

test('loading a legacy itinerary fills gaps and retains the correct day for saved content', () => {
  const third = { ...written(3), id: 17 };
  const result = fillItineraryDays([third, written(1)], 3);
  assert.deepEqual(result.map(item => item.day_number), ['1', '2', '3']);
  assert.equal(result[2], third);
});

test('reducing duration and transient invalid input never discard entered days', () => {
  const items = [written(1), written(2), written(3)];
  assert.deepEqual(fillItineraryDays(items, 2), items);
  for (const days of [0, NaN, 1.5, -1]) assert.equal(fillItineraryDays(items, days), items);
  assert.ok(itineraryErrors(items, 2).length);
  assert.deepEqual(fillItineraryDays(fillItineraryDays(items, 2), 3), items);
});

test('missing and whitespace-only content names the exact days that block saving', () => {
  assert.deepEqual(itineraryErrors([written(1)], 3), ['Chưa có lịch trình ngày 2, 3']);
  assert.deepEqual(itineraryErrors([written(1), ngayRong(2), { ...written(3), content: '  \n ' }], 3), [
    'Ngày 2 chưa có tiêu đề và hoạt động', 'Ngày 3 chưa có hoạt động',
  ]);
});

test('matching count is insufficient when day numbers duplicate or are out of range', () => {
  for (const items of [[written(1), written(1)], [written(1), written(3)]]) {
    assert.ok(itineraryErrors(items, 2).length);
  }
  assert.deepEqual(itineraryErrors([written(1), written(2), written(3)], 3), []);
  assert.deepEqual(itineraryErrors([written(1)], 1), []);
});
