<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Đánh giá tour, phía khách hàng.
 *
 * Ba luật, mỗi luật một lý do:
 *
 * 1. **Chỉ khách đã ĐI XONG mới đánh giá được.** Trước đây `confirmed` cũng qua, tức người mới đặt
 *    chỗ tuần trước đã chấm được năm sao cho một chuyến chưa khởi hành. Điểm số ấy không nói gì về
 *    chuyến đi, mà lại đứng chung bảng với điểm của người đã đi thật.
 *
 * 2. **Đánh giá mới phải chờ duyệt.** Xem `App\Enums\ReviewStatus` và migration 2026_08_30_000002.
 *
 * 3. **Người viết luôn thấy đánh giá của chính mình**, kèm trạng thái. Ẩn luôn cho tới khi duyệt
 *    thì họ tưởng bấm gửi không ăn và gửi lại — rồi gọi điện hỏi vì sao không thấy.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Đánh giá của một tour.
     *
     * Có phân trang: một tour bán chạy tích lại hàng trăm đánh giá, và trả hết về trong một lần là
     * bắt mọi lượt xem trang tải cả đống chữ mà gần như không ai cuộn hết.
     */
    public function index(Request $request, int $tourId): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $nguoiDung = auth('sanctum')->user();

        $reviews = Review::query()
            ->with(['user:id,name,avatar', 'repliedBy:id,name'])
            ->where('tour_id', $tourId)
            ->where(function ($q) use ($nguoiDung) {
                $q->approved();

                // Người viết thấy bài của chính mình dù đang chờ duyệt hoặc đã bị từ chối.
                if ($nguoiDung) {
                    $q->orWhere('user_id', $nguoiDung->id);
                }
            })
            ->latest()
            ->paginate($request->integer('per_page') ?: self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => $reviews->getCollection()->map(
                fn (Review $review) => $this->dong($review, $nguoiDung?->id),
            )->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'summary' => $this->tongKet($tourId),
        ]);
    }

    /**
     * Điểm trung bình và phổ điểm của tour.
     *
     * Tính ở máy chủ trên TOÀN BỘ đánh giá đã duyệt, không để giao diện tự cộng từ danh sách nó
     * đang cầm: từ khi có phân trang, danh sách ấy chỉ là mười bài đầu, và "4,8 sao dựa trên 10
     * đánh giá" trên một tour có 130 đánh giá là một con số sai đứng ở chỗ dễ tin nhất.
     *
     * @return array<string, mixed>
     */
    private function tongKet(int $tourId): array
    {
        $daDuyet = Review::query()->approved()->where('tour_id', $tourId);

        $tong = (clone $daDuyet)->count();

        $phoDiem = (clone $daDuyet)
            ->selectRaw('rating, count(*) as so_luong')
            ->groupBy('rating')
            ->pluck('so_luong', 'rating');

        return [
            'total' => $tong,
            'average' => $tong > 0 ? round((float) (clone $daDuyet)->avg('rating'), 1) : null,
            'breakdown' => collect([5, 4, 3, 2, 1])->map(fn (int $sao) => [
                'star' => $sao,
                'count' => (int) ($phoDiem[$sao] ?? 0),
                'percent' => $tong > 0 ? (int) round(((int) ($phoDiem[$sao] ?? 0)) / $tong * 100) : 0,
            ])->values(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
        ], [
            'comment.min' => 'Nhận xét cần ít nhất 10 ký tự để người đọc sau hiểu được ý bạn.',
        ]);

        $user = $request->user();

        /*
         * Phải là đơn ĐÃ HOÀN TẤT của chính người này.
         *
         * `completed` do tác vụ nền `bookings:finalize-completed` đóng lại sau khi chuyến kết thúc
         * (xem D03), nên nó là mốc đáng tin cho câu hỏi "người này đã đi chuyến đó chưa".
         *
         * `no_show` không nhận: khách không có mặt thì không có gì để kể về chuyến đi.
         */
        /*
         * Nhận diện theo TÀI KHOẢN, cố ý không nhận diện theo địa chỉ thư.
         *
         * Có một cách nới rộng nghe rất hợp lý: khách vãng lai đi xong, lập tài khoản bằng đúng
         * địa chỉ thư đã đặt, rồi được đánh giá. Nó giải quyết một mất mát thật — đơn vãng lai
         * không có `customer_id` nên nhóm ấy vĩnh viễn không đánh giá được, mà họ chiếm phần lớn
         * lượt đặt.
         *
         * Nhưng nó chỉ an toàn khi hệ thống **xác minh quyền sở hữu địa chỉ thư**, và ở đây thì
         * không: `AuthController::register` cấp thẻ đăng nhập ngay, không gửi thư xác minh, `User`
         * không dùng `MustVerifyEmail`. Nghĩa là bất kỳ ai biết một địa chỉ đã từng đặt tour đều
         * đăng ký được bằng chính địa chỉ đó và thừa hưởng lịch sử chuyến đi của người khác.
         *
         * Đánh giá là thứ đứng công khai cạnh tên tour và kéo điểm trung bình, nên bằng chứng
         * "người này đã đi" phải chắc chắn, không được là một lời khai tự nhận. Khóa ngoại
         * `customer_id` là bằng chứng chắc chắn; địa chỉ thư chưa xác minh thì không.
         *
         * Muốn mở cho khách vãng lai thì phải làm đúng một trong hai việc, và cả hai đều là việc
         * riêng chứ không phải nới một dòng ở đây:
         *
         *   1. Thêm xác minh địa chỉ thư lúc đăng ký, rồi mới so theo thư.
         *   2. Cho tài khoản **nhận** các đơn vãng lai cùng địa chỉ tại thời điểm đăng nhập — khi
         *      ấy `customer_id` được điền và phép kiểm này tự đúng, đồng thời khách cũng thấy đơn
         *      cũ trong mục "Đơn của tôi", điều họ vẫn phải tra bằng mã tra cứu.
         */
        $daDi = Booking::query()
            ->where('tour_id', $data['tour_id'])
            ->where('customer_id', $user->id)
            ->where('status', BookingStatus::Completed->value)
            ->exists();

        if (!$daDi) {
            return $this->error(
                'Chỉ khách đã đi xong tour này mới đánh giá được. Nếu bạn vừa kết thúc chuyến, '
                . 'đơn sẽ chuyển sang trạng thái hoàn tất trong ít phút.',
                403,
            );
        }

        /*
         * Mỗi khách một đánh giá cho mỗi tour; gửi lại thì SỬA bài cũ.
         *
         * Sửa xong quay về `pending`: nội dung đã đổi thì lần duyệt trước không còn nói về nội
         * dung đang có. Không đặt lại thì đây là đường để một bài đã duyệt bị thay bằng chữ khác
         * mà không ai đọc lại.
         */
        $review = Review::query()->updateOrCreate(
            [
                'tour_id' => $data['tour_id'],
                'user_id' => $user->id,
            ],
            [
                'rating' => $data['rating'],
                'comment' => $data['comment'],
                'status' => ReviewStatus::Pending,
                'moderated_at' => null,
                'moderated_by' => null,
                'moderation_note' => null,
            ],
        );

        return $this->success(
            $this->dong($review->fresh(['user:id,name,avatar']), $user->id),
            'Đã gửi đánh giá. Nội dung sẽ hiện công khai sau khi được duyệt.',
            201,
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = Review::find($id);

        if (!$review) {
            return $this->error('Không tìm thấy đánh giá', 404);
        }

        if ($request->user()->id !== $review->user_id) {
            return $this->error('Bạn không có quyền xóa đánh giá này', 403);
        }

        $review->delete();

        return $this->success(null, 'Đã xóa đánh giá');
    }

    /** @return array<string, mixed> */
    private function dong(Review $review, ?int $nguoiDungId): array
    {
        $laCuaMinh = $nguoiDungId !== null && $review->user_id === $nguoiDungId;

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
            'user' => $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
                'avatar' => $review->user->avatar,
            ] : null,
            'is_mine' => $laCuaMinh,
            // Trạng thái và lý do từ chối chỉ có nghĩa với người viết. Người đọc khác chỉ thấy
            // những bài đã duyệt, nên với họ hai trường này luôn là cùng một giá trị.
            'status' => $laCuaMinh ? $review->status->value : null,
            'status_label' => $laCuaMinh ? $review->status->label() : null,
            'moderation_note' => $laCuaMinh ? $review->moderation_note : null,
            'reply' => $review->reply,
            'replied_at' => $review->replied_at?->toIso8601String(),
            'replied_by' => $review->repliedBy?->name,
        ];
    }
}
