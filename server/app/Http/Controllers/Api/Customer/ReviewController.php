<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Booking;

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
       

        // Kiểm tra đã đánh giá chưa

        $exist = Review::where('tour_id',$request->tour_id)
            ->where('user_id',$user->id)
            ->exists();

        if($exist){

            return response()->json([
                'success'=>false,
                'message'=>'Bạn đã đánh giá tour này.'
            ],409);

        }

        $review = Review::create([

            'tour_id'=>$request->tour_id,

            'user_id'=>$user->id,

            'rating'=>$request->rating,

            'comment'=>$request->comment

        ]);

        return response()->json([

            'success'=>true,

            'message'=>'Đánh giá thành công',

            'data'=>$review

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