<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Mail\ReviewModeratedMail;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Hàng đợi kiểm duyệt đánh giá, và chỗ công ty trả lời khách.
 *
 * Từ chối KHÔNG xóa bản ghi: người viết cần đọc được lý do, và nếu họ khiếu nại thì điều hành phải
 * mở lại được đúng dòng chữ đã bị từ chối. Xóa hẳn chỉ dành cho chính người viết.
 */
class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'tour_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $reviews = Review::query()
            ->with(['user:id,name,email', 'tour:id,title,slug', 'repliedBy:id,name', 'moderatedBy:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['tour_id'] ?? null, fn ($q, $tourId) => $q->where('tour_id', $tourId))
            /*
             * Bài chờ duyệt lên đầu, bất kể bộ lọc nào đang bật.
             *
             * Đây là màn hình để LÀM một việc, không phải để đọc lịch sử: thứ cần thấy trước là
             * thứ đang chờ người ta quyết.
             */
            ->orderByRaw("case when status = ? then 0 else 1 end", [ReviewStatus::Pending->value])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        $reviews->getCollection()->transform(fn (Review $review) => $this->dong($review));

        // Số bài đang chờ đi kèm mọi lần gọi, kể cả khi đang lọc theo trạng thái khác: đó là con
        // số hiện trên huy hiệu ở thanh điều hướng, và nó phải đúng dù màn hình đang xem gì.
        return $this->success($reviews->toArray() + [
            'pending_count' => Review::query()->where('status', ReviewStatus::Pending->value)->count(),
        ], 'Lấy danh sách đánh giá thành công');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $review = Review::find($id);

        if (!$review) {
            return $this->error('Không tìm thấy đánh giá', 404);
        }

        $review->forceFill([
            'status' => ReviewStatus::Approved,
            'moderated_at' => now(),
            'moderated_by' => $request->user()->id,
            'moderation_note' => null,
        ])->save();

        $this->baoNguoiViet($review);

        return $this->success(
            $this->dong($review->fresh(['user:id,name,email', 'tour:id,title,slug', 'moderatedBy:id,name'])),
            'Đã duyệt. Đánh giá này giờ hiện công khai và được tính vào điểm của tour.',
        );
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Phải ghi lý do từ chối — người viết có quyền biết vì sao chữ của họ không được đăng.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự.',
        ]);

        $review = Review::find($id);

        if (!$review) {
            return $this->error('Không tìm thấy đánh giá', 404);
        }

        $review->forceFill([
            'status' => ReviewStatus::Rejected,
            'moderated_at' => now(),
            'moderated_by' => $request->user()->id,
            'moderation_note' => trim($data['reason']),
        ])->save();

        $this->baoNguoiViet($review);

        return $this->success(
            $this->dong($review->fresh(['user:id,name,email', 'tour:id,title,slug', 'moderatedBy:id,name'])),
            'Đã từ chối. Đánh giá không hiện công khai và không tính vào điểm của tour.',
        );
    }

    /**
     * Báo cho người viết biết bài của họ được đăng hay bị từ chối.
     *
     * Phải là thư, không phải thông báo trong hệ thống: hộp thông báo chỉ mở cho điều hành và
     * hướng dẫn viên, khách không có màn hình nào để đọc.
     *
     * Người viết vẫn thấy trạng thái khi mở lại trang tour, nên đây không phải lỗ hổng — nhưng nó
     * đòi họ chủ động quay lại đúng chỗ và để ý một dòng chữ nhỏ. Đánh giá là thứ người ta bỏ công
     * viết rồi chờ xem có được đăng không; im lặng khiến họ tưởng bấm gửi không ăn và gửi lại lần
     * nữa, đúng điều mà chú thích ở `ReviewController` đã lo từ đầu.
     *
     * Thư hỏng thì ghi log rồi đi tiếp: quyết định kiểm duyệt đã lưu xong và không phụ thuộc vào nó.
     */
    private function baoNguoiViet(Review $review): void
    {
        $email = $review->user?->email;

        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send(new ReviewModeratedMail($review->fresh(['tour:id,title,slug', 'user:id,name'])));
        } catch (Throwable $e) {
            Log::warning('Không gửi được thư báo kết quả kiểm duyệt đánh giá.', [
                'review_id' => $review->getKey(),
                'status' => $review->status->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Công ty trả lời một đánh giá.
     *
     * Chỉ trả lời được bài ĐÃ DUYỆT: trả lời một bài chưa duyệt là viết công khai bên dưới một
     * đoạn chữ mà người ngoài chưa đọc được, và nếu sau đó bài bị từ chối thì câu trả lời ấy nói
     * về một thứ không tồn tại.
     *
     * Gửi chuỗi rỗng để gỡ câu trả lời.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['present', 'nullable', 'string', 'max:1000'],
        ]);

        $review = Review::find($id);

        if (!$review) {
            return $this->error('Không tìm thấy đánh giá', 404);
        }

        if ($review->status !== ReviewStatus::Approved) {
            return $this->error(
                'Chỉ trả lời được đánh giá đã duyệt. Duyệt bài trước, rồi trả lời.',
                422,
            );
        }

        $noiDung = trim((string) ($data['reply'] ?? ''));

        $review->forceFill($noiDung === '' ? [
            'reply' => null,
            'replied_at' => null,
            'replied_by' => null,
        ] : [
            'reply' => $noiDung,
            'replied_at' => now(),
            'replied_by' => $request->user()->id,
        ])->save();

        return $this->success(
            $this->dong($review->fresh(['user:id,name,email', 'tour:id,title,slug', 'repliedBy:id,name'])),
            $noiDung === '' ? 'Đã gỡ câu trả lời.' : 'Đã đăng câu trả lời dưới đánh giá này.',
        );
    }

    /** @return array<string, mixed> */
    private function dong(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'moderation_note' => $review->moderation_note,
            'moderated_at' => $review->moderated_at?->toDateTimeString(),
            'moderated_by' => $review->moderatedBy?->name,
            'reply' => $review->reply,
            'replied_at' => $review->replied_at?->toDateTimeString(),
            'replied_by' => $review->repliedBy?->name,
            'created_at' => $review->created_at?->toDateTimeString(),
            'user' => $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
                'email' => $review->user->email,
            ] : null,
            'tour' => $review->tour ? [
                'id' => $review->tour->id,
                'title' => $review->tour->title,
                'slug' => $review->tour->slug,
            ] : null,
        ];
    }
}
