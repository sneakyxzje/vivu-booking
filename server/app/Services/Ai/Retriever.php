<?php

namespace App\Services\Ai;

use App\Models\KnowledgeChunk;

/**
 * Tìm những đoạn tri thức liên quan tới câu hỏi.
 *
 * Trộn hai phép so khớp vì chúng hỏng ở hai chỗ khác nhau: vector bắt được ý
 * ("tắm biển thư giãn" ra tour biển đảo) nhưng mờ với tên riêng — Fansipan, Tràng
 * An, Côn Đảo nằm rất gần nhau trong không gian vector; so khớp từ khóa thì ngược lại.
 *
 * Tính bằng PHP vì MySQL 8 không có kiểu vector, và vài trăm đoạn thì nạp hết lên
 * so sánh chỉ mất vài chục mili-giây. Tới vài nghìn đoạn thì thay lớp này.
 */
class Retriever
{
    /** Hư từ xuất hiện trong gần như mọi câu hỏi nên không phân biệt được gì. */
    private const STOPWORDS = [
        'của', 'và', 'cho', 'với', 'thì', 'là', 'có', 'không', 'được', 'các', 'những', 'một',
        'này', 'kia', 'đó', 'nào', 'gì', 'ạ', 'ơi', 'tôi', 'mình', 'em', 'anh', 'chị', 'bạn',
        'muốn', 'cần', 'xin', 'hỏi', 'cho', 'về', 'ở', 'từ', 'đến', 'tới', 'khi', 'nếu', 'mà',
        'như', 'sao', 'bao', 'nhiêu', 'vậy', 'ah', 'à', 'nhé', 'nha', 'ok',
    ];

    public function __construct(private readonly OpenAiClient $client)
    {
    }

    /**
     * @return array<int, array{title: string, content: string, score: float, source_type: string, source_id: int|null}>
     */
    public function retrieve(string $question, ?int $topK = null): array
    {
        $topK = $topK ?? (int) config('ai.retrieval.top_k', 6);
        $dimensions = (int) config('ai.openai.embedding_dimensions');

        // Chỉ lấy vector cùng số chiều với cấu hình hiện tại. So một vector 1536
        // chiều với một vector 768 chiều thì phép tính vẫn chạy, chỉ là vô nghĩa và
        // không có lỗi nào để lần ra.
        $chunks = KnowledgeChunk::query()
            ->whereNotNull('embedding')
            ->where('dimensions', $dimensions)
            ->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        // Mã hóa câu hỏi sau khi biết kho có gì: kho rỗng thì không trả tiền cho lời gọi này.
        $questionVector = $this->client->embed([$question])[0] ?? [];

        if ($questionVector === []) {
            return [];
        }

        $questionTokens = $this->tokenize($question);
        $lexicalWeight = (float) config('ai.retrieval.lexical_weight', 0.35);
        $minScore = (float) config('ai.retrieval.min_score', 0.15);

        $scored = [];

        foreach ($chunks as $chunk) {
            $semantic = $this->cosine($questionVector, $chunk->vector());
            $lexical = $this->lexical($questionTokens, $chunk->title . ' ' . $chunk->content);
            $score = (1 - $lexicalWeight) * $semantic + $lexicalWeight * $lexical;

            if ($score < $minScore) {
                continue;
            }

            $scored[] = [
                'title' => (string) $chunk->title,
                'content' => (string) $chunk->content,
                'score' => round($score, 4),
                'source_type' => (string) $chunk->source_type,
                'source_id' => $chunk->source_id !== null ? (int) $chunk->source_id : null,
            ];
        }

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $topK));
    }

    /**
     * Độ tương đồng cosine, kẹp về khoảng 0 đến 1.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        if ($a === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value * $value;
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return max(0.0, $dot / (sqrt($normA) * sqrt($normB)));
    }

    /**
     * Tỷ lệ từ khóa của câu hỏi có mặt trong đoạn.
     *
     * @param  array<int, string>  $questionTokens
     */
    private function lexical(array $questionTokens, string $text): float
    {
        if ($questionTokens === []) {
            return 0.0;
        }

        $haystack = ' ' . implode(' ', $this->tokenize($text)) . ' ';
        $hits = 0;

        foreach ($questionTokens as $token) {
            if (str_contains($haystack, " {$token} ")) {
                $hits++;
            }
        }

        return $hits / count($questionTokens);
    }

    /** @return array<int, string> */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        $tokens = array_filter(
            explode(' ', $text),
            fn (string $token) => mb_strlen($token) >= 2 && !in_array($token, self::STOPWORDS, true),
        );

        return array_values(array_unique($tokens));
    }
}
