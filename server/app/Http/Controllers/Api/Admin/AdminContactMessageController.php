<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hộp thư liên hệ, và danh sách đăng ký nhận bản tin.
 *
 * Hai thứ này ở chung một controller vì chúng là hai đầu của cùng một chuyện: những người đã để
 * lại địa chỉ nhưng chưa mua gì. Trước đây cả hai đều là ngõ cụt — form liên hệ không tồn tại, và
 * bảng `newsletter_subscribers` nhận email từ trang chủ nhưng không màn hình nào đọc nó.
 */
class AdminContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([ContactMessage::CHUA_XU_LY, ContactMessage::DA_XU_LY])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = ContactMessage::query()
            ->with('handledBy:id,name')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // Chưa xử lý lên đầu bất kể bộ lọc: đây là màn hình để làm việc, không phải để đọc lại.
            ->orderByRaw('case when status = ? then 0 else 1 end', [ContactMessage::CHUA_XU_LY])
            ->latest()
            ->paginate($filters['per_page'] ?? 20);

        $messages->getCollection()->transform(fn (ContactMessage $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone,
            'subject' => $m->subject,
            'message' => $m->message,
            'status' => $m->status,
            'handled_at' => $m->handled_at?->toDateTimeString(),
            'handled_by' => $m->handledBy?->name,
            'handling_note' => $m->handling_note,
            'created_at' => $m->created_at?->toDateTimeString(),
        ]);

        return $this->success($messages->toArray() + [
            'new_count' => ContactMessage::query()->where('status', ContactMessage::CHUA_XU_LY)->count(),
        ], 'Lấy hộp thư liên hệ thành công');
    }

    /** Đánh dấu đã xử lý, hoặc mở lại nếu bấm nhầm. */
    public function toggleHandled(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $message = ContactMessage::query()->find($id);

        if (!$message) {
            return $this->error('Không tìm thấy lời nhắn', 404);
        }

        $daXuLy = $message->status === ContactMessage::DA_XU_LY;

        $message->forceFill($daXuLy ? [
            'status' => ContactMessage::CHUA_XU_LY,
            'handled_at' => null,
            'handled_by' => null,
            'handling_note' => null,
        ] : [
            'status' => ContactMessage::DA_XU_LY,
            'handled_at' => now(),
            'handled_by' => $request->user()->id,
            'handling_note' => $data['note'] ?? null,
        ])->save();

        return $this->success(
            ['id' => $message->id, 'status' => $message->status],
            $daXuLy ? 'Đã mở lại lời nhắn.' : 'Đã đánh dấu đã xử lý.',
        );
    }

    // --- Bản tin ---------------------------------------------------------------------------

    public function subscribers(Request $request): JsonResponse
    {
        $subscribers = NewsletterSubscriber::query()
            ->latest('id')
            ->paginate($request->integer('per_page') ?: 50);

        return $this->success($subscribers->toArray(), 'Lấy danh sách đăng ký nhận tin thành công');
    }

    /**
     * Xuất danh sách email ra CSV.
     *
     * Hệ thống này KHÔNG gửi bản tin — dựng một công cụ gửi thư hàng loạt là một dự án riêng, với
     * quản lý mẫu thư, theo dõi tỷ lệ mở và cơ chế hủy đăng ký theo luật. Việc đúng của nó là giao
     * danh sách cho công cụ đã làm sẵn những thứ đó.
     *
     * Có tệp này thì ô "đăng ký nhận bản tin" ở trang chủ mới thôi là một nút không dẫn tới đâu.
     */
    public function exportSubscribers(): StreamedResponse
    {
        $tenTep = 'nguoi-dang-ky-nhan-tin-' . now()->format('Y-m-d') . '.csv';

        return Response::streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM cho Excel bản tiếng Việt đọc đúng dấu. Thiếu nó thì mở ra thấy chữ vỡ, và người
            // nhận tệp sẽ nghĩ dữ liệu hỏng chứ không nghĩ tới phần mềm đọc.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Email', 'Ngày đăng ký']);

            NewsletterSubscriber::query()
                ->orderBy('id')
                ->chunk(500, function ($nhom) use ($out) {
                    foreach ($nhom as $nguoi) {
                        fputcsv($out, [$nguoi->email, $nguoi->created_at?->format('d/m/Y H:i')]);
                    }
                });

            fclose($out);
        }, $tenTep, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
