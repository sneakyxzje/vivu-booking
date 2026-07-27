<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'comment' => 'required|string'
        ]);


        // Kiểm tra đã đăng nhập
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để đánh giá.'
            ], 401);
        }


        // Không cho đánh giá trùng tour
        $existReview = Review::where('tour_id', $request->tour_id)
            ->where('user_id', Auth::id())
            ->first();


        if ($existReview) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã đánh giá tour này rồi.'
            ], 400);
        }


        $review = Review::create([
            'tour_id' => $request->tour_id,
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Đánh giá thành công.',
            'data' => $review->load('user')
        ], 201);
    }



    /**
     * Xem chi tiết đánh giá
     */
    public function show($id)
    {
        $review = Review::with('user')
            ->find($id);


        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đánh giá.'
            ], 404);
        }


        return response()->json([
            'success' => true,
            'data' => $review
        ]);
    }



    /**
     * Cập nhật đánh giá
     */
    public function update(Request $request, $id)
    {
        $review = Review::find($id);


        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đánh giá.'
            ], 404);
        }


        // Kiểm tra quyền sửa
        if ($review->user_id != Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền sửa đánh giá này.'
            ], 403);
        }


        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string'
        ]);


        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Cập nhật đánh giá thành công.',
            'data' => $review
        ]);
    }




    /**
     * Xóa đánh giá
     */
    public function destroy($id)
    {
        $review = Review::find($id);


        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đánh giá.'
            ], 404);
        }


        // Kiểm tra quyền xóa
        if ($review->user_id != Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa đánh giá này.'
            ], 403);
        }


        $review->delete();


        return response()->json([
            'success' => true,
            'message' => 'Xóa đánh giá thành công.'
        ]);
    }
}