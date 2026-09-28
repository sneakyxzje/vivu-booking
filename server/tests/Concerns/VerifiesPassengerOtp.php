<?php

namespace Tests\Concerns;

use App\Mail\PassengerOtpMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

trait VerifiesPassengerOtp
{
    private function passengerAccessHeaders(Booking $booking): array
    {
        Mail::fake();
        $url = '/api/bookings/' . $booking->public_token . '/passengers';
        $challenge = $this->postJson($url . '/send-otp', ['email' => $booking->customer_email])
            ->assertOk()->json('data.challenge_id');
        $otp = Mail::sent(PassengerOtpMail::class)->last()->otp;
        $token = $this->postJson($url . '/verify-otp', ['challenge_id' => $challenge, 'otp' => $otp])
            ->assertOk()->json('data.access_token');

        return ['X-Passenger-Access' => $token];
    }
}
