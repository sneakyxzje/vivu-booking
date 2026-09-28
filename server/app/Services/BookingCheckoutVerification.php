<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingCheckoutVerification
{
    public const REQUIRED = 'Bạn chưa xác thực email. Vui lòng nhận và nhập mã OTP trước khi đặt tour.';

    /** Token is a secret capability; only its hash is stored. */
    public function issue(string $email, ?int $actorId): string
    {
        $token = Str::random(64);
        DB::table('booking_checkout_verifications')->insert([
            'token_hash' => hash('sha256', $token),
            'email' => strtolower(trim($email)),
            'actor_id' => $actorId,
            'expires_at' => now()->addMinutes(15),
        ]);
        return $token;
    }

    public function create(Request $request, array $data, Closure $create, bool &$replayed): Booking
    {
        $token = $request->header('X-Booking-Verification', '');
        if (!preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) {
            throw new BusinessRuleException(self::REQUIRED, 403);
        }
        $requestKey = $request->header('Idempotency-Key', '');
        if (!Str::isUuid($requestKey)) {
            throw new BusinessRuleException('Yêu cầu đặt tour không hợp lệ. Vui lòng tải lại trang.', 422);
        }
        $fingerprint = $this->fingerprint($data);

        // Lock the proof before the schedule. Consumption and booking creation commit together.
        return DB::transaction(function () use ($token, $requestKey, $data, $fingerprint, $create, &$replayed) {
            $proof = DB::table('booking_checkout_verifications')
                ->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            $actorId = auth('sanctum')->id();
            if (!$proof || $proof->email !== strtolower(trim($data['customer_email']))
                || ($proof->actor_id === null ? null : (int) $proof->actor_id) !== $actorId) {
                throw new BusinessRuleException(self::REQUIRED, 403);
            }
            if ($proof->request_key !== null) {
                if ($proof->request_key !== $requestKey || !hash_equals($proof->payload_hash, $fingerprint)) {
                    throw new BusinessRuleException('Lượt xác thực này đã dùng cho một yêu cầu khác. Vui lòng xác thực lại email.', 409);
                }
                if (Carbon::parse($proof->consumed_at)->addDay()->lte(now())) {
                    throw new BusinessRuleException('Yêu cầu đã hết thời gian gửi lại. Vui lòng tra cứu đơn đã đặt.', 409);
                }
                $booking = Booking::with(['tour', 'schedule'])->find($proof->booking_id);
                if (!$booking) {
                    throw new BusinessRuleException('Đơn của yêu cầu này không còn khả dụng. Vui lòng liên hệ hỗ trợ.', 409);
                }
                $replayed = true;
                return $booking;
            }
            if (Carbon::parse($proof->expires_at)->lte(now())) {
                throw new BusinessRuleException('Xác thực email đã hết hạn. Vui lòng nhập OTP mới.', 403);
            }

            $booking = $create();
            DB::table('booking_checkout_verifications')->where('id', $proof->id)->update([
                'request_key' => $requestKey, 'payload_hash' => $fingerprint,
                'booking_id' => $booking->id, 'consumed_at' => now(),
            ]);
            return $booking;
        });
    }

    private function fingerprint(array $data): string
    {
        foreach (['tour_id', 'tour_schedule_id', 'adult_count', 'child_count', 'infant_count'] as $key) {
            $data[$key] = (int) ($data[$key] ?? 0);
        }
        $data['customer_email'] = strtolower(trim($data['customer_email']));
        $data['accept_terms'] = true;
        foreach (['customer_phone', 'note', 'discount_code'] as $key) {
            $data[$key] ??= null;
        }
        $data['passengers'] ??= [];
        $sort = function (array $value) use (&$sort): array {
            if (!array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }
            return $value;
        };
        return hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR));
    }
}
