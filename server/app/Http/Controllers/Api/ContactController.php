<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Form liên hệ ở trang `/contact`.
 *
 * Không cần đăng nhập: phần lớn người viết vào đây là người CHƯA đặt gì và đang cân nhắc. Bắt họ
 * tạo tài khoản để hỏi một câu là cách chắc chắn nhất để không nhận được câu hỏi nào.
 */
class ContactController extends Controller
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'message.min' => 'Nội dung cần ít nhất 10 ký tự để chúng tôi hiểu bạn cần gì.',
        ]);

        $tinNhan = ContactMessage::query()->create($data + [
            'status' => ContactMessage::CHUA_XU_LY,
        ]);

        /*
         * Báo cho điều hành ngay.
         *
         * Không có thông báo thì hộp thư này chỉ được đọc khi ai đó nhớ ra mà mở nó — và một câu
         * hỏi trước khi mua để ba ngày không trả lời thì người hỏi đã đặt chỗ ở nơi khác.
         *
         * `Notifier` tự nuốt lỗi: hỏng đường thông báo không được làm mất lời nhắn vừa lưu.
         */
        $this->notifier->toiDieuHanh(
            'contact_message',
            'Có lời nhắn mới từ trang liên hệ',
            sprintf('%s (%s): %s', $tinNhan->name, $tinNhan->email, mb_strimwidth($tinNhan->message, 0, 120, '...')),
            '/admin/contact-messages',
        );

        return $this->success(null, 'Đã gửi lời nhắn. Chúng tôi sẽ liên hệ lại trong 24 giờ làm việc.', 201);
    }
}
