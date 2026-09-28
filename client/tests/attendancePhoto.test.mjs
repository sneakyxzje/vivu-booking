import test from 'node:test';
import assert from 'node:assert/strict';
import { uploadAttendancePhoto, AttendancePhotoError } from '../src/utils/attendancePhoto.ts';

const file = new File(['image'], 'doan.png', { type: 'image/png' });
const snapshot = () => ({
  schedule: { id: 7, status: 'in_progress', can_record: true, server_now: '2026-10-04T10:00:00+07:00', recording_ends_at: '2026-10-05T18:00:00+07:00' },
  checkpoints: [{ id: 10, attendance_date: '2026-10-04', latitude: null, longitude: null }],
});
function setup(data = snapshot()) {
  const calls = [];
  const deps = {
    getAttendance: async id => { calls.push(['access', id]); return data; },
    elapsed: () => 100,
    onAccess: () => calls.push(['synchronized']),
    upload: async (...args) => { calls.push(['upload', ...args]); return { photo: { id: 99 } }; },
  };
  return { deps, calls };
}

test('keeps the selected file while waiting for fresh access after returning from the picker', async () => {
  const { deps, calls } = setup();
  let finishRefresh;
  deps.getAttendance = () => new Promise(resolve => { finishRefresh = resolve; });
  const pending = uploadAttendancePhoto(7, 10, file, deps);
  assert.equal(calls.length, 0);
  finishRefresh(snapshot());
  assert.deepEqual(await pending, { photo: { id: 99 } });
  assert.deepEqual(calls.map(call => call[0]), ['synchronized', 'upload']);
  assert.deepEqual(calls.at(-1), ['upload', 7, 10, file]);
});

test('past/future days, ended and cancelled trips still reject before uploading', async () => {
  for (const change of [
    data => { data.checkpoints[0].attendance_date = '2026-10-03'; },
    data => { data.checkpoints[0].attendance_date = '2026-10-05'; },
    data => { data.schedule.status = 'completed'; },
    data => { data.schedule.status = 'cancelled'; },
    data => { data.schedule.can_record = false; },
  ]) {
    const data = snapshot();
    change(data);
    const { deps, calls } = setup(data);
    await assert.rejects(uploadAttendancePhoto(7, 10, file, deps), AttendancePhotoError);
    assert.equal(calls.some(call => call[0] === 'upload'), false);
  }
});

test('a fresh server date after midnight blocks uploading to yesterday’s checkpoint', async () => {
  const data = snapshot();
  data.schedule.server_now = '2026-10-05T00:00:01+07:00';
  const { deps, calls } = setup(data);
  await assert.rejects(uploadAttendancePhoto(7, 10, file, deps), /Ngày này đã qua/);
  assert.equal(calls.some(call => call[0] === 'upload'), false);
});

test('oversize and non-image files report useful errors before checking access', async () => {
  const { deps, calls } = setup();
  await assert.rejects(uploadAttendancePhoto(7, 10, { size: 5 * 1024 * 1024 + 1, type: 'image/jpeg' }, deps), /5MB/);
  await assert.rejects(uploadAttendancePhoto(7, 10, new File(['text'], 'file.txt', { type: 'text/plain' }), deps), /hình ảnh/);
  assert.equal(calls.length, 0);
});

test('failed access refresh and removed checkpoints stop the upload', async () => {
  const { deps, calls } = setup(null);
  await assert.rejects(uploadAttendancePhoto(7, 10, file, deps), /Chưa kiểm tra được quyền/);
  const data = snapshot();
  data.checkpoints = [];
  deps.getAttendance = async () => data;
  await assert.rejects(uploadAttendancePhoto(7, 10, file, deps), /không còn trong chuyến/);
  assert.equal(calls.some(call => call[0] === 'upload'), false);
});

test('uploading works without GPS and can retry the same file after a storage failure', async () => {
  const { deps, calls } = setup();
  deps.getPosition = () => { throw new Error('GPS must not be requested'); };
  const successfulUpload = deps.upload;
  deps.upload = async () => { throw new Error('Upload failed'); };
  await assert.rejects(uploadAttendancePhoto(7, 10, file, deps), /Upload failed/);
  deps.upload = successfulUpload;
  await uploadAttendancePhoto(7, 10, file, deps);
  assert.deepEqual(calls.at(-1), ['upload', 7, 10, file]);
});
