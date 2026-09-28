<?php

namespace App\Services\Ai;

use App\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lớp mỏng nói chuyện với OpenAI: mã hóa chữ thành vector, và hỏi model.
 *
 * Mọi thất bại — khóa sai, hết hạn mức, mạng rớt, JSON thiếu trường — đều thành
 * AiUnavailableException. Nơi gọi không có gì làm khác nhau giữa chúng; nguyên
 * nhân thật ghi vào nhật ký.
 */
class OpenAiClient
{
    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $payload = [
            'model' => (string) config('ai.openai.embedding_model'),
            'input' => array_values($texts),
        ];

        $dimensions = (int) config('ai.openai.embedding_dimensions');

        if ($dimensions > 0) {
            $payload['dimensions'] = $dimensions;
        }

        $body = $this->post('/embeddings', $payload);

        $rows = $body['data'] ?? null;

        if (!is_array($rows) || count($rows) !== count($texts)) {
            $this->fail('Phản hồi mã hóa vector không đủ số phần tử', [
                'sent' => count($texts),
                'received' => is_array($rows) ? count($rows) : null,
            ]);
        }

        /*
         * Sắp lại theo `index` chứ không tin thứ tự mảng: một lô lệch thứ tự nghĩa
         * là vector của tour này bị gắn cho tour kia, kho vẫn dựng xong và không
         * có lỗi nào, chỉ mọi câu trả lời đều sai.
         */
        $vectors = [];

        foreach ($rows as $row) {
            $index = (int) ($row['index'] ?? -1);
            $vector = $row['embedding'] ?? null;

            if ($index < 0 || !is_array($vector)) {
                $this->fail('Phản hồi mã hóa vector thiếu trường', []);
            }

            $vectors[$index] = array_map('floatval', $vector);
        }

        ksort($vectors);

        return array_values($vectors);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{message: array<string, mixed>, finish_reason: string|null, usage: array<string, int>}
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => (string) config('ai.openai.chat_model'),
            'messages' => array_values($messages),
            // Tư vấn dựa trên dữ liệu có thật: cùng một câu hỏi về chính sách thì
            // hai khách phải nhận cùng một câu trả lời.
            'temperature' => 0.3,
            'max_completion_tokens' => (int) config('ai.chat.max_output_tokens', 900),
        ];

        if ($tools !== []) {
            $payload['tools'] = array_values($tools);
            $payload['tool_choice'] = 'auto';
        }

        $body = $this->post('/chat/completions', $payload);

        $choice = $body['choices'][0] ?? null;

        if (!is_array($choice) || !isset($choice['message'])) {
            $this->fail('Phản hồi hội thoại không có lượt trả lời nào', []);
        }

        return [
            'message' => $choice['message'],
            'finish_reason' => $choice['finish_reason'] ?? null,
            'usage' => [
                'prompt_tokens' => (int) ($body['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    public function isConfigured(): bool
    {
        return trim((string) config('ai.openai.key')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        if (!$this->isConfigured()) {
            $this->fail('Thiếu OPENAI_API_KEY', []);
        }

        try {
            $response = $this->request()->post($path, $payload);
        } catch (Throwable $e) {
            $this->fail('Không gọi được OpenAI: ' . $e->getMessage(), ['path' => $path]);
        }

        if ($response->failed()) {
            $this->fail('OpenAI trả về lỗi', [
                'path' => $path,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
        }

        $body = $response->json();

        if (!is_array($body)) {
            $this->fail('OpenAI trả về thứ không phải JSON', ['path' => $path]);
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai.openai.base_url'), '/'))
            ->withToken((string) config('ai.openai.key'))
            ->acceptJson()
            ->timeout((int) config('ai.openai.timeout', 60))
            // throw: false để lần hỏng cuối đi xuống nhánh failed() bên dưới — ở đó
            // có mã trạng thái và thân phản hồi để ghi nhật ký.
            ->retry(2, 500, throw: false);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return never
     */
    private function fail(string $reason, array $context): void
    {
        Log::error('[ai] ' . $reason, $context);

        throw new AiUnavailableException(
            'Trợ lý ảo đang bận, bạn thử lại sau ít phút hoặc gọi hotline giúp mình nhé.'
        );
    }
}
