<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Models\Tour;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\TourAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Chatbot tư vấn tour.
 *
 * Không đòi đăng nhập: phần lớn người hỏi tư vấn là người đang cân nhắc mua, chưa
 * có tài khoản. Đổi lại tuyến này có hạn mức riêng, vì mỗi lượt hỏi là tiền thật.
 *
 * Client chỉ gửi conversation_token; lịch sử đọc từ cơ sở dữ liệu.
 */
class ChatController extends Controller
{
    public function store(Request $request, OpenAiClient $client, TourAdvisor $advisor): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:' . (int) config('ai.chat.max_question_length', 1000)],
            'conversation_token' => ['nullable', 'uuid'],
        ], [
            'message.required' => 'Bạn nhập câu hỏi giúp mình nhé.',
            'message.max' => 'Câu hỏi hơi dài, bạn rút ngắn lại giúp mình nhé.',
        ]);

        // Nhánh riêng cho "chưa khai khóa": để rơi xuống OpenAiClient thì khách nhận
        // câu "trợ lý đang bận" và người vận hành đi chờ, trong khi việc cần làm là
        // điền một dòng vào .env.
        if (!$client->isConfigured()) {
            return $this->error('Trợ lý ảo chưa được bật trên máy chủ này.', 503);
        }

        $token = $this->conversationToken($request, $validated['conversation_token'] ?? null);

        $history = AiChatMessage::query()
            ->forConversation($token)
            ->get(['role', 'content'])
            ->map(fn (AiChatMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();

        try {
            $result = $advisor->answer($validated['message'], $history);
        } catch (AiUnavailableException $e) {
            return $this->error($e->getMessage(), 503);
        }

        $userId = $request->user()?->getAuthIdentifier();

        AiChatMessage::query()->create([
            'conversation_token' => $token,
            'user_id' => $userId,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        AiChatMessage::query()->create([
            'conversation_token' => $token,
            'user_id' => $userId,
            'role' => 'assistant',
            'content' => $result['answer'],
            'tool_calls' => $result['tools_used'],
            'prompt_tokens' => $result['usage']['prompt_tokens'],
            'completion_tokens' => $result['usage']['completion_tokens'],
        ]);

        return $this->success([
            'conversation_token' => $token,
            'answer' => $result['answer'],
            'tours' => $this->tourCards($result['tour_ids']),
        ], 'Trả lời thành công');
    }

    /**
     * Hội thoại của người đã đăng nhập không cho người khác nối tiếp: mã bị lộ thì
     * người cầm được nó nối vào đúng ngữ cảnh cũ, trong đó có thể có tên, số điện
     * thoại hay mã đơn. Mở hội thoại mới thay vì báo lỗi — khách không làm gì sai.
     */
    private function conversationToken(Request $request, ?string $requested): string
    {
        if (!$requested) {
            return (string) Str::uuid();
        }

        $owner = AiChatMessage::query()
            ->where('conversation_token', $requested)
            ->whereNotNull('user_id')
            ->value('user_id');

        if ($owner && (int) $owner !== (int) $request->user()?->getAuthIdentifier()) {
            return (string) Str::uuid();
        }

        return $requested;
    }

    /**
     * @param  array<int, int>  $tourIds
     * @return array<int, array<string, mixed>>
     */
    private function tourCards(array $tourIds): array
    {
        if ($tourIds === []) {
            return [];
        }

        $tours = Tour::query()
            ->whereKey($tourIds)
            ->whereIn('status', ['active', 'full'])
            ->withAvg('approvedReviews as rating', 'rating')
            ->get(['id', 'slug', 'title', 'thumbnail', 'adult_price', 'number_of_days', 'number_of_nights', 'status']);

        // Giữ thứ tự model đã nhắc tới: tour nó gợi ý đầu tiên là tour nó cho là hợp nhất.
        return collect($tourIds)
            ->map(fn (int $id) => $tours->firstWhere('id', $id))
            ->filter()
            ->map(fn (Tour $tour) => [
                'id' => (int) $tour->id,
                'slug' => (string) $tour->slug,
                'title' => (string) $tour->title,
                'thumbnail' => $tour->thumbnail,
                'adult_price' => (float) $tour->adult_price,
                'number_of_days' => (int) $tour->number_of_days,
                'number_of_nights' => (int) $tour->number_of_nights,
                'rating' => $tour->rating !== null ? round((float) $tour->rating, 1) : null,
            ])
            ->values()
            ->all();
    }
}
