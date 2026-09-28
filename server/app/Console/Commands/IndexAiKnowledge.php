<?php

namespace App\Console\Commands;

use App\Exceptions\AiUnavailableException;
use App\Services\Ai\KnowledgeIndexer;
use App\Services\Ai\OpenAiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Dựng lại kho tri thức của chatbot.
 *
 * Tăng dần: đoạn nào không đổi nội dung thì không mã hóa lại, nên chạy theo lịch
 * mỗi giờ gần như không tốn gì. `--force` cần khi đổi model hoặc số chiều vector.
 */
#[Signature('ai:index {--force : Mã hóa lại toàn bộ, kể cả đoạn không đổi nội dung}')]
#[Description('Dựng lại kho tri thức cho chatbot tư vấn tour')]
class IndexAiKnowledge extends Command
{
    public function handle(KnowledgeIndexer $indexer, OpenAiClient $client): int
    {
        // Chưa bật chatbot mà coi đây là lỗi thì mỗi ngày có hai mươi tư dòng đỏ
        // trong nhật ký cho một tính năng không ai định dùng.
        if (!$client->isConfigured()) {
            $this->line('Chưa khai OPENAI_API_KEY, bỏ qua việc dựng kho tri thức.');

            return self::SUCCESS;
        }

        try {
            $stats = $indexer->rebuild((bool) $this->option('force'));
        } catch (AiUnavailableException $e) {
            $this->error('Không dựng được kho tri thức: ' . $e->getMessage());
            $this->line('Xem storage/logs/laravel.log để biết nguyên nhân thật (thiếu khóa, hết hạn mức, mạng).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Kho tri thức: %d đoạn (%d mã hóa mới, %d giữ nguyên, %d đoạn cũ đã gỡ).',
            $stats['total'],
            $stats['embedded'],
            $stats['unchanged'],
            $stats['removed'],
        ));

        return self::SUCCESS;
    }
}
