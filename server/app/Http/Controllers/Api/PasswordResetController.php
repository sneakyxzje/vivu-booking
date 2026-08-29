<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Quên mật khẩu: xin liên kết đặt lại, rồi đặt mật khẩu mới bằng liên kết đó.
 *
 * ## Vì sao câu trả lời luôn giống nhau
 *
 * Cả hai điểm cuối trả về đúng một câu bất kể email có tồn tại hay không. Trả lời khác nhau là
 * biến trang quên mật khẩu thành công cụ dò xem địa chỉ nào có tài khoản ở đây - thông tin đủ để
 * dựng danh sách gửi thư lừa đảo nhắm đúng người đang chờ thư từ Vivu Booking.
 *
 * Cùng nguyên tắc đang áp ở `BookingController::resendLookupCode`.
 *
 * ## Tài khoản đã khóa
 *
 * Không gửi liên kết. Đăng nhập đã chặn tài khoản `status != active`, nên gửi liên kết cho họ là
 * dẫn người ta đi hết một vòng để cuối cùng vẫn không vào được - và tệ hơn, nó xác nhận cho người
 * đang thử rằng địa chỉ ấy có tài khoản thật.
 */
class PasswordResetController extends Controller
{
    /** Câu trả lời chung, cố ý không nói email có tồn tại hay không. */
    private const CAU_TRA_LOI_CHUNG = 'Nếu email này có tài khoản đang hoạt động, chúng tôi đã gửi '
        . 'liên kết đặt lại mật khẩu. Vui lòng kiểm tra hòm thư (kể cả mục Spam).';

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user && $user->status === 'active') {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $e) {
                // Hỏng SMTP không được biến thành lỗi 500 cho người dùng: họ không sửa được gì,
                // và thông báo lỗi khác với thông báo thành công lại để lộ email nào có tài khoản.
                Log::warning('Không gửi được thư đặt lại mật khẩu.', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->success(null, self::CAU_TRA_LOI_CHUNG);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.confirmed' => 'Hai lần nhập mật khẩu chưa khớp nhau.',
            'password.min' => 'Mật khẩu cần ít nhất 6 ký tự.',
        ]);

        $ketQua = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            /*
             * Thu hồi mọi phiên đăng nhập cũ.
             *
             * Người đặt lại mật khẩu thường đang nghĩ "có thể ai đó vào được tài khoản của tôi".
             * Đổi mật khẩu mà để nguyên các token Sanctum đã cấp thì kẻ kia vẫn ở trong - đúng thứ
             * mà thao tác này lẽ ra phải cắt đứt.
             */
            $user->tokens()->delete();

            event(new PasswordReset($user));
        });

        if ($ketQua !== Password::PASSWORD_RESET) {
            return $this->error(
                'Liên kết đặt lại mật khẩu không còn hiệu lực. Liên kết chỉ dùng được một lần và '
                . 'hết hạn sau ' . config('auth.passwords.users.expire', 60) . ' phút - hãy yêu cầu một liên kết mới.',
                422,
            );
        }

        return $this->success(null, 'Đã đổi mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
    }
}
