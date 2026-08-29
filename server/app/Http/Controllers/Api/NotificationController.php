<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Hộp thông báo, dùng chung cho điều hành và hướng dẫn viên.
 *
 * Đọc từ bảng `notifications`, không đọc từ WebSocket. WebSocket chỉ là đường đẩy nhanh; **nguồn
 * sự thật vẫn là cơ sở dữ liệu**. Nhờ vậy tắt Reverb thì màn hình vẫn đầy đủ, chỉ chậm hơn.
 */
class NotificationController extends Controller
{
    /** Bao nhiêu thông báo trả về mỗi lần. Đủ để cuộn, không đủ để thành một trang tải nặng. */
    private const GIOI_HAN = 50;

    public function index(Request $request): JsonResponse
    {
        $ds = $request->user()
            ->notifications()
            ->latest()
            ->limit(self::GIOI_HAN)
            ->get()
            ->map(fn (DatabaseNotification $tb) => $this->dong($tb));

        return $this->success([
            'notifications' => $ds,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 'Lấy thông báo thành công');
    }

    /**
     * Chỉ số chưa đọc.
     *
     * Tách riêng khỏi `index` vì màn hình hỏi nó **định kỳ** khi không có WebSocket. Kéo cả danh
     * sách về mỗi lần chỉ để đếm là lãng phí, và đó lại đúng là lúc lãng phí dễ thấy nhất.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(
            ['unread_count' => $request->user()->unreadNotifications()->count()],
            'Lấy số thông báo chưa đọc thành công',
        );
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $tb = $request->user()->notifications()->whereKey($id)->first();

        if (!$tb) {
            return $this->error('Không tìm thấy thông báo', 404);
        }

        $tb->markAsRead();

        return $this->success($this->dong($tb->fresh()), 'Đã đánh dấu đã đọc.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(['unread_count' => 0], 'Đã đánh dấu tất cả là đã đọc.');
    }

    /** @return array<string, mixed> */
    private function dong(DatabaseNotification $tb): array
    {
        $data = $tb->data;

        return [
            'id' => $tb->id,
            'kind' => $data['kind'] ?? 'other',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? null,
            'read_at' => $tb->read_at?->toDateTimeString(),
            'created_at' => $tb->created_at?->toDateTimeString(),
        ];
    }
}
