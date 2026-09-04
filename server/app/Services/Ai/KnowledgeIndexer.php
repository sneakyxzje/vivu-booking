<?php

namespace App\Services\Ai;

use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\KnowledgeChunk;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Services\BookingTransferService;
use App\Services\CancellationPolicyService;

/**
 * Dựng kho tri thức: dữ liệu tour và chính sách công ty thành những đoạn có vector.
 *
 * Mỗi đoạn phải tự đứng được — model chỉ nhìn thấy các đoạn được chọn, không biết
 * cái nào đứng cạnh cái nào — nên tên tour lặp lại ở đầu mọi đoạn.
 */
class KnowledgeIndexer
{
    /** Số đoạn gửi đi mã hóa mỗi lô. */
    private const BATCH = 64;

    public function __construct(private readonly OpenAiClient $client)
    {
    }

    /**
     * Dựng lại kho, chỉ mã hóa đoạn có nội dung thay đổi.
     *
     * `$force` cần khi đổi model hoặc số chiều vector: nội dung không đổi nhưng
     * vector cũ không dùng chung được với vector mới.
     *
     * @return array{total: int, embedded: int, unchanged: int, removed: int}
     */
    public function rebuild(bool $force = false): array
    {
        $documents = $this->documents();
        $dimensions = (int) config('ai.openai.embedding_dimensions');

        $existing = KnowledgeChunk::query()->get()->keyBy('chunk_key');

        /** @var array<int, KnowledgeChunk> $pending */
        $pending = [];
        $unchanged = 0;

        foreach ($documents as $document) {
            $hash = hash('sha256', $document['content']);
            $chunk = $existing->get($document['key']) ?? new KnowledgeChunk(['chunk_key' => $document['key']]);

            $conNguyen = !$force
                && $chunk->exists
                && $chunk->content_hash === $hash
                && $chunk->embedding
                && (int) $chunk->dimensions === $dimensions;

            $chunk->fill([
                'source_type' => $document['source_type'],
                'source_id' => $document['source_id'],
                'title' => $document['title'],
                'content' => $document['content'],
                'content_hash' => $hash,
            ]);

            $chunk->save();

            if ($conNguyen) {
                $unchanged++;

                continue;
            }

            $pending[] = $chunk;
        }

        // Tour ngừng bán thì đoạn của nó không còn trong danh sách vừa dựng.
        $keys = array_column($documents, 'key');
        $removed = KnowledgeChunk::query()->whereNotIn('chunk_key', $keys)->delete();

        $this->embed($pending, $dimensions);

        return [
            'total' => count($documents),
            'embedded' => count($pending),
            'unchanged' => $unchanged,
            'removed' => (int) $removed,
        ];
    }

    /**
     * Toàn bộ tri thức dưới dạng văn bản, trước khi mã hóa.
     *
     * @return array<int, array{key: string, source_type: string, source_id: int|null, title: string, content: string}>
     */
    public function documents(): array
    {
        return [
            ...$this->tourDocuments(),
            ...$this->policyDocuments(),
        ];
    }

    /**
     * @return array<int, array{key: string, source_type: string, source_id: int|null, title: string, content: string}>
     */
    private function tourDocuments(): array
    {
        // Đúng tập tour trang khách đang bán. Tour sân thử nghiệm bị loại: ngày giờ
        // ở đó bị tua tới lui, không phải hàng bán cho khách.
        $tours = Tour::query()
            ->with(['categories', 'services', 'itineraries.checkpoints'])
            ->whereIn('status', ['active', 'full'])
            ->where('is_sandbox', false)
            ->orderBy('id')
            ->get();

        $documents = [];

        foreach ($tours as $tour) {
            $documents[] = [
                'key' => "tour:{$tour->id}:tong-quan",
                'source_type' => 'tour',
                'source_id' => (int) $tour->id,
                'title' => (string) $tour->title,
                'content' => $this->tourOverview($tour),
            ];

            // Chia theo ngày: câu hỏi của khách thường rơi vào đúng một ngày, còn
            // một đoạn cả nghìn chữ thì phần liên quan bị phần không liên quan làm loãng.
            foreach ($tour->itineraries->sortBy('day_number') as $itinerary) {
                $documents[] = [
                    'key' => "tour:{$tour->id}:ngay-{$itinerary->day_number}",
                    'source_type' => 'tour',
                    'source_id' => (int) $tour->id,
                    'title' => "{$tour->title} — ngày {$itinerary->day_number}",
                    'content' => $this->itineraryDay($tour, $itinerary),
                ];
            }
        }

        return $documents;
    }

    private function tourOverview(Tour $tour): string
    {
        $categories = $tour->categories->pluck('name')->implode(', ');
        $services = $tour->services->pluck('name')->implode(', ');

        return $this->lines([
            "Tour: {$tour->title}",
            "Đường dẫn trên web: /tours/{$tour->slug}",
            $this->line('Hành trình', $this->route($tour->start_location, $tour->end_location)),
            $this->line('Thời lượng', "{$tour->number_of_days} ngày {$tour->number_of_nights} đêm"),
            $this->line('Loại hình', $categories),
            $this->line('Phương tiện', $tour->vehicle_info),
            $this->line('Điểm đón khách', $tour->pickup_location),
            $this->line('Giá mỗi khách', $this->priceLine($tour)),
            $this->line('Dịch vụ đã bao gồm', $services),
            $this->line('Giới thiệu', $this->plain($tour->description)),
        ]);
    }

    private function itineraryDay(Tour $tour, TourItinerary $itinerary): string
    {
        $checkpoints = $itinerary->checkpoints
            ->sortBy('sequence')
            ->map(fn ($point) => trim((string) $point->name . ($point->description ? " ({$this->plain($point->description)})" : '')))
            ->filter()
            ->implode('; ');

        return $this->lines([
            "Lịch trình tour {$tour->title} — ngày {$itinerary->day_number}: {$itinerary->title}",
            $this->line('Chặng', $this->route($itinerary->start_point, $itinerary->end_point)),
            $this->line('Đi qua', $this->plain($itinerary->route_points)),
            $this->line('Điểm dừng nghỉ', $this->plain($itinerary->rest_stops)),
            $this->line('Điểm tham quan', $checkpoints),
            $this->line('Chi tiết', $this->plain($itinerary->content)),
        ]);
    }

    /**
     * Chính sách và quy trình. Mọi con số đọc từ cấu hình và từ bảng phí đang áp
     * dụng — chép tay vào đây là dựng bản thứ hai của một luật.
     *
     * @return array<int, array{key: string, source_type: string, source_id: int|null, title: string, content: string}>
     */
    private function policyDocuments(): array
    {
        $depositPercent = max(1, min(100, (int) config('booking.deposit_percent', 50)));
        $balanceDueDays = (int) config('booking.balance_due_days', 10);
        $holdMinutes = (int) config('booking.payment_ttl_minutes', 10);
        $deadlineDays = (int) config('booking.booking_deadline_days', 3);
        $transferFee = (float) config('booking.transfer_fee', 200_000);
        $transferNotice = (int) config('booking.transfer_notice_days', 0);

        $documents = [];

        $documents[] = [
            'key' => 'policy:dat-tour-va-thanh-toan',
            'source_type' => 'policy',
            'source_id' => null,
            'title' => 'Quy trình đặt tour và thanh toán',
            'content' => $this->lines([
                'Chính sách công ty: quy trình đặt tour và thanh toán.',
                'Đặt tour không cần tài khoản. Sau khi đặt, khách nhận một mã tra cứu để xem đơn, khai danh sách hành khách và theo dõi thanh toán.',
                "Đơn mới đặt được giữ chỗ {$holdMinutes} phút để thanh toán. Quá {$holdMinutes} phút mà chưa trả tiền thì đơn tự hủy và chỗ được trả lại cho khách khác.",
                'Thanh toán trực tuyến qua cổng VNPay.',
                "Khách đặt cọc {$depositPercent}% giá trị đơn để giữ chỗ, phần còn lại trả chậm nhất {$balanceDueDays} ngày trước ngày khởi hành. Hệ thống gửi thư nhắc trước hạn; quá hạn mà chưa trả nốt thì đơn bị hủy và khoản đã cọc xử lý theo bảng phí hủy.",
                "Hạn chốt danh sách khách mặc định là {$deadlineDays} ngày trước khởi hành. Sau hạn đó chuyến ngừng nhận đặt chỗ mới và khách không tự sửa được danh sách hành khách nữa.",
                'Khách khai danh sách hành khách sau khi đặt, qua liên kết riêng theo mã tra cứu, hạn cuối là hạn chốt danh sách của chuyến.',
                'Giá tính theo từng loại khách: người lớn, trẻ em và em bé có ba mức khác nhau. Mỗi em bé phải đi kèm ít nhất một người lớn trong cùng đơn.',
                'Đoàn từ 5 người trở lên gửi yêu cầu báo giá riêng thay vì đặt trực tiếp: chọn chuyến, ước tính số người, và điều hành sẽ báo giá kèm hạn hiệu lực. Yêu cầu báo giá chưa chiếm chỗ và chưa phải trả tiền.',
            ]),
        ];

        $documents[] = [
            'key' => 'policy:huy-va-hoan-tien',
            'source_type' => 'policy',
            'source_id' => null,
            'title' => 'Chính sách hủy và hoàn tiền',
            'content' => $this->lines([
                'Chính sách công ty: hủy tour và hoàn tiền.',
                'Bảng phí hủy tính theo số ngày báo trước, so với ngày khởi hành. Phần trăm dưới đây là phần khách được hoàn lại trên giá trị đơn:',
                ...$this->refundRuleLines(),
                'Đơn chưa thanh toán thì khách tự hủy được ngay.',
                'Đơn đã thanh toán thì khách gửi yêu cầu hủy và điều hành duyệt, không tự hủy được; khách xem trước được mức hoàn dự kiến trước khi gửi yêu cầu, và rút lại yêu cầu nếu đổi ý.',
                'Công ty hủy chuyến thì hoàn 100% số tiền đã thu, không áp bảng phí hủy. Bảng phí chỉ áp khi khách đổi ý.',
                'Khách vắng mặt lúc khởi hành được tính như hủy sát ngày: không hoàn tiền.',
                $transferNotice > 0
                    ? "Khách xin đổi chuyến chậm nhất {$transferNotice} ngày trước khởi hành."
                    : 'Khách xin đổi chuyến được tới tận hạn chốt danh sách của chuyến.',
                sprintf(
                    'Lần đổi chuyến đầu tiên miễn phí. Từ lần thứ hai thu phí đổi lịch %sđ mỗi đơn. Công ty chủ động đổi thì không thu phí.',
                    number_format($transferFee, 0, ',', '.'),
                ),
                sprintf('Số lần đổi miễn phí: %d.', BookingTransferService::FREE_TRANSFERS),
            ]),
        ];

        $documents[] = [
            'key' => 'policy:lien-he',
            'source_type' => 'policy',
            'source_id' => null,
            'title' => 'Thông tin liên hệ công ty',
            'content' => $this->lines([
                'Thông tin liên hệ của công ty lữ hành.',
                $this->line('Tên công ty', (string) config('company.name')),
                $this->line('Địa chỉ', (string) config('company.address')),
                $this->line('Tổng đài', (string) config('company.phone')),
                $this->line('Email', (string) config('company.email')),
                $this->line('Giấy phép lữ hành nội địa', (string) config('company.license_no')),
                'Khách cần người thật hỗ trợ thì gọi tổng đài hoặc gửi biểu mẫu liên hệ trên website.',
            ]),
        ];

        return $documents;
    }

    /**
     * Ưu tiên bảng trong cơ sở dữ liệu rồi mới tới bảng viết trong mã — đúng thứ tự
     * CancellationPolicyService dùng khi tính tiền thật.
     *
     * @return array<int, string>
     */
    private function refundRuleLines(): array
    {
        $policy = CancellationPolicy::dangApDung();

        if ($policy && $policy->rules->isNotEmpty()) {
            return $policy->rules
                ->map(fn (CancellationPolicyRule $rule) => "- {$rule->windowLabel()}: hoàn {$rule->refund_percent}%"
                    . ($rule->note ? " ({$rule->note})" : ''))
                ->all();
        }

        return array_map(
            fn (array $rule) => '- ' . (new CancellationPolicyRule($rule))->windowLabel() . ": hoàn {$rule['refund_percent']}%",
            CancellationPolicyService::DEFAULT_RULES,
        );
    }

    private function priceLine(Tour $tour): string
    {
        $parts = [];

        foreach ([
            'người lớn' => $tour->adult_price,
            'trẻ em' => $tour->child_price,
            'em bé' => $tour->infant_price,
        ] as $label => $price) {
            if ($price === null) {
                continue;
            }

            $parts[] = $label . ' ' . number_format((float) $price, 0, ',', '.') . 'đ';
        }

        return implode('; ', $parts);
    }

    /** @param  array<int, KnowledgeChunk>  $chunks */
    private function embed(array $chunks, int $dimensions): void
    {
        foreach (array_chunk($chunks, self::BATCH) as $batch) {
            $vectors = $this->client->embed(array_map(
                fn (KnowledgeChunk $chunk) => $chunk->title . "\n" . $chunk->content,
                $batch,
            ));

            foreach ($batch as $i => $chunk) {
                $chunk->forceFill([
                    'embedding' => KnowledgeChunk::packEmbedding($vectors[$i]),
                    'dimensions' => count($vectors[$i]) ?: $dimensions,
                    'indexed_at' => now(),
                ])->save();
            }
        }
    }

    /** @param  array<int, string>  $lines */
    private function lines(array $lines): string
    {
        return implode("\n", array_values(array_filter(array_map('trim', $lines))));
    }

    private function line(string $label, ?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : "{$label}: {$value}";
    }

    /**
     * "A đến B", vẫn đọc được khi thiếu một đầu.
     *
     * Không dùng `trim($chuoi, ' đến')`: mặt nạ ký tự của trim làm việc trên từng
     * byte nên nó cắt vào giữa một ký tự có dấu.
     */
    private function route(?string $from, ?string $to): string
    {
        $parts = array_values(array_filter([trim((string) $from), trim((string) $to)]));

        return implode(' đến ', $parts);
    }

    private function plain(?string $value): string
    {
        $text = strip_tags((string) $value);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
