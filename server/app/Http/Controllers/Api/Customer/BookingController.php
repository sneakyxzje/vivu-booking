<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentLog;
use App\Models\TourSchedule;
use App\Services\VNPayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'guests' => 'required|integer|min:1',
            'note' => 'nullable|string|max:1000',
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

            $availableSeats = $schedule->max_people - $schedule->booked_people;

            if ($data['guests'] > $availableSeats) {
                throw ValidationException::withMessages([
                    'guests' => 'Số chỗ còn lại không đủ cho booking này.',
                ]);
            }

            $tour = $schedule->tour;
            $unitPrice = $tour->discount_price ?: $tour->price;
            $totalAmount = $unitPrice * $data['guests'];

            $booking = Booking::create([
                'tour_id' => $tour->id,
                'customer_id' => $user?->id,
                'guest_id' => $user ? null : $guestId,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'departure_date' => $schedule->start_date,
                'guests' => $data['guests'],
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'note' => $data['note'] ?? null,
            ]);
            $schedule->increment('booked_people', $data['guests']);
            $schedule->refresh();

            if ($schedule->booked_people >= $schedule->max_people) {
                $schedule->update(['status' => 'full']);
            }

            $this->refreshTourAvailability($tour);

            return $booking->load(['tour', 'schedule']);
        });

        $response = response()->json([
            'success' => true,
            'message' => 'Đặt tour thành công. Vui lòng thanh toán để hoàn tất.',
            'data' => [
                'booking' => $booking,
                'payment_url' => $this->vnpayService->createPayment($booking),
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

    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy đơn hàng.',
        ]);

        $booking = Booking::with(['schedule', 'tour'])->where('id', $id)
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

        if ($bookingId) {
            DB::transaction(function () use ($bookingId, $isSuccessful, $isValidSignature, $request) {
                $booking = Booking::query()
                    ->with(['schedule', 'tour.schedules'])
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
                    return;
                }

                $booking->update([
                    'status' => $isSuccessful ? 'confirmed' : 'cancelled',
                    'vnpay_transaction_no' => $isSuccessful
                        ? $request->query('vnp_TransactionNo')
                        : null,
                    'paid_at' => $isSuccessful ? now() : null,
                    'confirmed_at' => $isSuccessful ? now() : null,
                ]);
                if (!$isSuccessful && $booking->schedule) {
                    $booking->schedule->decrement('booked_people', $booking->guests);
                    $booking->schedule->refresh();

                    if ($booking->schedule->status === 'full' && $booking->schedule->booked_people < $booking->schedule->max_people) {
                        $booking->schedule->update(['status' => 'active']);
                    }

                    $this->refreshTourAvailability($booking->tour);
                }
            });
        }

        return redirect()->away(URL::query($frontendUrl . '/payment-result', [
            'status' => $isSuccessful ? 'success' : 'failed',
            'booking_id' => $bookingId,
        ]));
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

