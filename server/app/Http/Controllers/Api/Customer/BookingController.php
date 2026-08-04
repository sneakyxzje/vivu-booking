<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Mail\BookingCreatedMail;
use App\Mail\BookingPaidMail;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\PaymentLog;
use App\Models\TourSchedule;
use App\Services\VNPayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingController extends Controller
{
    public function __construct(private VNPayService $vnpayService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'tour_schedule_id' => 'required|exists:tour_schedules,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'adult_count' => 'required|integer|min:1',
            'child_count' => 'nullable|integer|min:0',
            'infant_count' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:1000',
            'discount_code' => 'nullable|string|max:50',
        ]);

        $user = auth('sanctum')->user();

        if ($user && $user->role !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ khách hàng mới có thể đặt tour.',
            ], 403);
        }

        $guestId = null;
        $guestCookie = null;

        if (!$user) {
            $guestId = $request->cookie('guest_id') ?: (string) Str::uuid();

            if (!$request->cookie('guest_id')) {
                $guestCookie = cookie(
                    'guest_id',
                    $guestId,
                    60 * 24 * 30,
                    '/',
                    null,
                    app()->environment('production'),
                    true,
                    false,
                    'Lax'
                );
            }
        }

        $booking = DB::transaction(function () use ($data, $user, $guestId) {
            $schedule = TourSchedule::query()
                ->where('id', $data['tour_schedule_id'])
                ->where('tour_id', $data['tour_id'])
                ->lockForUpdate()
                ->first();

            if (!$schedule) {
                throw ValidationException::withMessages([
                    'tour_schedule_id' => 'Lịch khởi hành không thuộc tour đã chọn.',
                ]);
            }

            if ($schedule->status !== 'active' || $schedule->tour?->status !== 'active') {
                throw ValidationException::withMessages([
                    'tour_schedule_id' => 'Lịch khởi hành này hiện không khả dụng.',
                ]);
            }
            $adultCount = (int) $data['adult_count'];
            $childCount = (int) ($data['child_count'] ?? 0);
            $infantCount = (int) ($data['infant_count'] ?? 0);
            $totalGuests = $adultCount + $childCount + $infantCount;
            $availableSeats = $schedule->max_people - $schedule->booked_people;

            if ($totalGuests > $availableSeats) {
                throw ValidationException::withMessages([
                    'adult_count' => 'Số chỗ còn lại không đủ cho booking này.',
                ]);
            }

            $tour = $schedule->tour;
            $subtotalAmount = ($adultCount * (float) $tour->adult_price)
                + ($childCount * (float) $tour->child_price)
                + ($infantCount * (float) $tour->infant_price);
            $discount = $this->resolveDiscount($data['discount_code'] ?? null, (float) $subtotalAmount);
            $totalAmount = max(0, $subtotalAmount - $discount['amount']);

            $booking = Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $tour->id,
                'customer_id' => $user?->id,
                'guest_id' => $user ? null : $guestId,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'departure_date' => $schedule->start_date,
                'guests' => $totalGuests,
                'adult_count' => $adultCount,
                'child_count' => $childCount,
                'infant_count' => $infantCount,
                'total_amount' => $totalAmount,
                'discount_code_id' => $discount['model']?->id,
                'discount_code' => $discount['model']?->code,
                'discount_amount' => $discount['amount'],
                'status' => 'pending',
                'note' => $data['note'] ?? null,
            ]);
            if ($discount['model']) {
                $discount['model']->increment('used_count');
            }

            $schedule->increment('booked_people', $totalGuests);
            $schedule->refresh();

            if ($schedule->booked_people >= $schedule->max_people) {
                $schedule->update(['status' => 'full']);
            }

            $this->refreshTourAvailability($tour);

            return $booking->load(['tour', 'schedule']);
        });

        $paymentUrl = $this->vnpayService->createPayment($booking);
        $this->sendBookingCreatedMailAfterResponse($booking, $paymentUrl);

        $response = response()->json([
            'success' => true,
            'message' => 'Đặt tour thành công. Vui lòng thanh toán để hoàn tất.',
            'data' => [
                'booking' => $booking,
                'payment_url' => $paymentUrl,
            ],
        ], 201);

        return $guestCookie ? $response->cookie($guestCookie) : $response;
    }

    public function myBookings(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->with(['tour', 'schedule'])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    public function show(string $publicToken): JsonResponse
    {
        $booking = Booking::query()
            ->with(['tour', 'schedule'])
            ->where('public_token', $publicToken)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin đặt tour.',
            ], 404);
        }

        if ($booking->status === 'pending') {
            $booking->setAttribute('payment_url', $this->vnpayService->createPayment($booking));
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy đơn hàng.',
        ]);

        $booking = Booking::with(['schedule', 'tour', 'discountCode'])->where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt tour của bạn.',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đơn đặt tour đang ở trạng thái chờ duyệt (pending).',
            ], 400);
        }

        DB::transaction(function () use ($booking, $validated) {
            $booking->update([
                'status' => 'cancelled',
                'cancel_reason' => $validated['cancel_reason'],
            ]);

            if ($booking->schedule) {
                $booking->schedule->decrement('booked_people', $booking->guests);
                $booking->schedule->refresh();

                if ($booking->schedule->status === 'full' && $booking->schedule->booked_people < $booking->schedule->max_people) {
                    $booking->schedule->update(['status' => 'active']);
                }

                $this->refreshTourAvailability($booking->tour);
            }

            $this->releaseDiscountUsage($booking);
        });

        return response()->json([
            'success' => true,
            'message' => 'Hủy đơn đặt tour thành công.',
            'data' => $booking->refresh(),
        ]);
    }

    public function vnpayReturn(Request $request)
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $bookingId = $request->query('vnp_TxnRef');
        $isValidSignature = $this->hasValidVnpaySignature($request);
        $isSuccessful = $isValidSignature
            && $request->query('vnp_ResponseCode') === '00'
            && $request->query('vnp_TransactionStatus') === '00';

        $paidBooking = null;

        if ($bookingId) {
            $paidBooking = DB::transaction(function () use ($bookingId, $isSuccessful, $isValidSignature, $request) {
                $booking = Booking::query()
                    ->with(['schedule', 'tour.schedules', 'discountCode'])
                    ->lockForUpdate()
                    ->find($bookingId);

                PaymentLog::create([
                    'booking_id' => $booking?->id,
                    'provider' => 'vnpay',
                    'transaction_no' => $request->query('vnp_TransactionNo'),
                    'bank_code' => $request->query('vnp_BankCode'),
                    'response_code' => $request->query('vnp_ResponseCode'),
                    'transaction_status' => $request->query('vnp_TransactionStatus'),
                    'amount' => $request->query('vnp_Amount') ? $request->query('vnp_Amount') / 100 : null,
                    'is_valid_signature' => $isValidSignature,
                    'raw_payload' => $request->query(),
                ]);

                if (!$booking || $booking->status !== 'pending') {
                    return null;
                }

                $booking->update([
                    'status' => $isSuccessful ? 'confirmed' : 'cancelled',
                    'vnpay_transaction_no' => $isSuccessful
                        ? $request->query('vnp_TransactionNo')
                        : null,
                    'paid_at' => $isSuccessful ? now() : null,
                    'confirmed_at' => $isSuccessful ? now() : null,
                ]);

                if ($isSuccessful) {
                    return $booking->fresh(['tour', 'schedule', 'discountCode']);
                }

                $this->releaseDiscountUsage($booking);

                if ($booking->schedule) {
                    $booking->schedule->decrement('booked_people', $booking->guests);
                    $booking->schedule->refresh();

                    if ($booking->schedule->status === 'full' && $booking->schedule->booked_people < $booking->schedule->max_people) {
                        $booking->schedule->update(['status' => 'active']);
                    }

                    $this->refreshTourAvailability($booking->tour);
                }

                return null;
            });
        }

        if ($paidBooking) {
            $this->sendBookingPaidMailAfterResponse($paidBooking);
        }

        if ($bookingId) {
            $publicToken = $paidBooking?->public_token
                ?? Booking::query()->where('id', $bookingId)->value('public_token');

            if ($publicToken) {
                return redirect()->away(URL::query($frontendUrl . '/booking-success/' . $publicToken, [
                    'payment_status' => $isSuccessful ? 'success' : 'failed',
                ]));
            }
        }

        return redirect()->away(URL::query($frontendUrl . '/payment-result', [
            'status' => $isSuccessful ? 'success' : 'failed',
        ]));
    }

    private function resolveDiscount(?string $code, float $subtotalAmount): array
    {
        if (! $code) {
            return ['model' => null, 'amount' => 0.0];
        }

        $discountCode = DiscountCode::query()
            ->where('code', Str::upper($code))
            ->lockForUpdate()
            ->first();

        if (! $discountCode || ! $discountCode->isUsableFor($subtotalAmount)) {
            throw ValidationException::withMessages([
                'discount_code' => 'Mã giảm giá không hợp lệ hoặc không còn khả dụng.',
            ]);
        }

        return [
            'model' => $discountCode,
            'amount' => $discountCode->calculateDiscount($subtotalAmount),
        ];
    }

    private function releaseDiscountUsage(Booking $booking): void
    {
        if (! $booking->discountCode || $booking->discountCode->used_count <= 0) {
            return;
        }

        $booking->discountCode->decrement('used_count');
    }

    private function hasValidVnpaySignature(Request $request): bool
    {
        $hashSecret = env('VNPAY_HASH_SECRET');
        $secureHash = $request->query('vnp_SecureHash');

        if (!$hashSecret || !$secureHash) {
            return false;
        }

        $inputData = $request->query();
        unset($inputData['vnp_SecureHash'], $inputData['vnp_SecureHashType']);
        ksort($inputData);

        $hashData = [];

        foreach ($inputData as $key => $value) {
            if (str_starts_with($key, 'vnp_')) {
                $hashData[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        $calculatedHash = hash_hmac('sha512', implode('&', $hashData), $hashSecret);

        return hash_equals($calculatedHash, $secureHash);
    }

    private function sendBookingCreatedMailAfterResponse(Booking $booking, ?string $paymentUrl): void
    {
        app()->terminating(function () use ($booking, $paymentUrl) {
            $this->sendBookingCreatedMail($booking, $paymentUrl);
        });
    }
    private function sendBookingCreatedMail(Booking $booking, ?string $paymentUrl): void
    {
        try {
            Mail::to($booking->customer_email)->send(new BookingCreatedMail($booking, $paymentUrl));
        } catch (Throwable $exception) {
            Log::warning('Could not send booking confirmation email.', [
                'booking_id' => $booking->id,
                'customer_email' => $booking->customer_email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendBookingPaidMailAfterResponse(Booking $booking): void
    {
        app()->terminating(function () use ($booking) {
            $this->sendBookingPaidMail($booking);
        });
    }
    private function sendBookingPaidMail(Booking $booking): void
    {
        try {
            Mail::to($booking->customer_email)->send(new BookingPaidMail($booking));
        } catch (Throwable $exception) {
            Log::warning('Could not send paid booking confirmation email.', [
                'booking_id' => $booking->id,
                'customer_email' => $booking->customer_email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function refreshTourAvailability($tour): void
    {
        if (!$tour || $tour->status === 'inactive') {
            return;
        }

        $tour->loadMissing('schedules');

        $hasAvailableSchedule = $tour->schedules->contains(function ($schedule) {
            return $schedule->status === 'active'
                && (int) $schedule->booked_people < (int) $schedule->max_people;
        });

        $tour->update(['status' => $hasAvailableSchedule ? 'active' : 'full']);
    }
}
