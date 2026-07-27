<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\BookingPassenger;
use Illuminate\Http\Request;

class PassengerController extends Controller
{
    // Danh sách hành khách theo booking
    public function index($booking)
    {
        $passengers = BookingPassenger::where('booking_id', $booking)->get();

        return response()->json([
            'success' => true,
            'data' => $passengers
        ]);
    }

    // Thêm hành khách
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'full_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'identity_number' => 'nullable|string|max:20'
        ]);

        $passenger = BookingPassenger::create([
            'booking_id' => $request->booking_id,
            'full_name' => $request->full_name,
            'birth_date' => $request->birth_date,
            'gender' => $request->gender,
            'phone' => $request->phone,
            'email' => $request->email,
            'identity_number' => $request->identity_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thêm hành khách thành công.',
            'data' => $passenger
        ], 201);
    }

    // Xem chi tiết hành khách
    public function show($id)
    {
        $passenger = BookingPassenger::find($id);

        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hành khách.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $passenger
        ]);
    }

    // Cập nhật thông tin hành khách
    public function update(Request $request, $id)
    {
        $passenger = BookingPassenger::find($id);

        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hành khách.'
            ], 404);
        }

        $request->validate([
            'full_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'identity_number' => 'nullable|string|max:20'
        ]);

        $passenger->update([
            'full_name' => $request->full_name,
            'birth_date' => $request->birth_date,
            'gender' => $request->gender,
            'phone' => $request->phone,
            'email' => $request->email,
            'identity_number' => $request->identity_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật hành khách thành công.',
            'data' => $passenger
        ]);
    }

    // Xóa hành khách
    public function destroy($id)
    {
        $passenger = BookingPassenger::find($id);

        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hành khách.'
            ], 404);
        }

        $passenger->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa hành khách thành công.'
        ]);
    }
}