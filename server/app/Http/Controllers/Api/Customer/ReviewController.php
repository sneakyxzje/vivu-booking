<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Danh sách đánh giá theo tour
     */
    public function index($tourId)
    {
        $reviews = Review::with('user')
            ->where('tour_id', $tourId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Thêm đánh giá
     */
    public function store(Request $request)
{
    $request->validate([
        'tour_id' => 'required|exists:tours,id',
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'required|string|max:1000',
    ]);

    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Bạn chưa đăng nhập.'
        ],401);
    }

    // Chỉ khách đã đặt và được xác nhận tour này mới được đánh giá.
    //
    // Phải nhận cả 'completed': từ D03, đơn của chuyến đã đi xong tự chuyển sang trạng thái đó.
    // Lọc đúng 'confirmed' thì khách vừa đi về xong lại mất quyền đánh giá, trong khi họ mới
    // chính là người có gì để nói. 'no_show' không nhận vì khách không thực sự đi chuyến này.
    $hasConfirmedBooking = Booking::query()
        ->where('tour_id', $request->tour_id)
        ->where('customer_id', $user->id)
        ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
        ->exists();

    if (!$hasConfirmedBooking) {
        return response()->json([
            'success' => false,
            'message' => 'Chỉ khách hàng đã đặt và hoàn tất tour này mới có thể đánh giá.'
        ], 403);
    }

    // Mỗi khách một đánh giá cho mỗi tour; gửi lại sẽ cập nhật đánh giá cũ
    $review = Review::query()->updateOrCreate(
        [
            'tour_id' => $request->tour_id,
            'user_id' => $user->id,
        ],
        [
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]
    );

    return response()->json([
        'success' => true,
        'message' => 'Đánh giá thành công',
        'data' => $review
    ],201);
}

    /**
     * Xóa đánh giá
     */
    public function destroy($id)
    {
        $review = Review::find($id);

        if(!$review){

            return response()->json([
                'message'=>'Không tìm thấy đánh giá'
            ],404);

        }

        if(auth()->id() != $review->user_id){

            return response()->json([
                'message'=>'Bạn không có quyền xóa'
            ],403);

        }

        $review->delete();

        return response()->json([
            'message'=>'Đã xóa đánh giá'
        ]);
    }
}