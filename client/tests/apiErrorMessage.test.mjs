import test from 'node:test';
import assert from 'node:assert/strict';
import { apiErrorMessage } from '../src/utils/apiErrorMessage.ts';

const fallback = 'Chưa gửi được báo cáo sự cố.';
test('specific validation errors take precedence over the generic API summary', () => {
  assert.equal(apiErrorMessage({ response: { status: 422, data: {
    message: 'The given data was invalid.', errors: {
      description: ['Diễn biến cần ít nhất 20 ký tự.'],
      occurred_at: ['Thời điểm xảy ra không được ở tương lai.'],
    },
  } } }, fallback), 'Diễn biến cần ít nhất 20 ký tự.\nThời điểm xảy ra không được ở tương lai.');
});
test('business rejections retain their actionable explanation', () => {
  const reason = 'Chuyến đã kết thúc, bạn chỉ có thể xem lại điểm danh.';
  assert.equal(apiErrorMessage({ response: { status: 403, data: { message: reason } } }, fallback), reason);
});
test('server diagnostics and malformed responses use the operation-specific fallback', () => {
  for (const error of [null, undefined, {}, { response: { data: '<html>error</html>' } },
    { response: { status: 500, data: { message: 'SQLSTATE secret', errors: { db: ['stack trace'] } } } },
    { response: { data: { message: 'Server Error' } } }]) {
    assert.equal(apiErrorMessage(error, fallback), fallback);
  }
});
test('authentication, throttling, network and timeout failures explain the next action', () => {
  assert.match(apiErrorMessage({ response: { status: 401 } }, fallback), /đăng nhập lại/);
  assert.match(apiErrorMessage({ response: { status: 429 } }, fallback), /chờ một lát/);
  assert.match(apiErrorMessage({ code: 'ERR_NETWORK' }, fallback), /Kiểm tra kết nối mạng/);
  assert.match(apiErrorMessage({ code: 'ECONNABORTED' }, fallback), /Kết nối quá lâu/);
});
