<?php

namespace Tests\Feature;

use App\Models\AiChatMessage;
use App\Models\KnowledgeChunk;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\Ai\AdvisorTools;
use App\Services\Ai\KnowledgeIndexer;
use App\Services\Ai\Retriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Chatbot tư vấn tour. Không lời gọi mạng nào chạy thật — `preventStrayRequests`
 * làm đỏ nếu có đường nào lọt ra ngoài.
 *
 * Vector giả lập dựng từ một từ điển tám từ khóa: mỗi chiều đếm số lần một từ xuất
 * hiện. Không phải embedding thật, nhưng có đúng tính chất Retriever dựa vào —
 * hai đoạn cùng chủ đề thì gần nhau.
 */
class AiChatbotTest extends TestCase
{
    use RefreshDatabase;

    private const KEYWORDS = ['hạ long', 'du thuyền', 'phú quốc', 'lặn biển', 'hủy', 'hoàn', 'giá', 'trẻ em'];

    /** @var array<int, array<string, mixed>> Hàng đợi phản hồi cho /chat/completions. */
    private array $chatResponses = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.openai.key' => 'sk-test',
            'ai.openai.embedding_dimensions' => count(self::KEYWORDS),
        ]);

        Http::preventStrayRequests();

        Http::fake([
            '*/embeddings' => fn (Request $request) => Http::response($this->embeddingBody($request)),
            '*/chat/completions' => fn () => Http::response(
                array_shift($this->chatResponses) ?? $this->textBody('Mình chưa rõ ý bạn.'),
            ),
        ]);
    }

    // ─── Kho tri thức ───────────────────────────────────────────────────────

    public function test_kho_tri_thuc_chi_lay_tour_dang_ban(): void
    {
        $dangBan = Tour::factory()->create(['title' => 'Tour vịnh Hạ Long']);
        Tour::factory()->create(['title' => 'Tour sân thử', 'is_sandbox' => true]);
        Tour::factory()->create(['title' => 'Tour đã ngừng bán', 'status' => 'inactive']);

        app(KnowledgeIndexer::class)->rebuild();

        $titles = KnowledgeChunk::query()->where('source_type', 'tour')->pluck('title')->all();

        $this->assertSame(['Tour vịnh Hạ Long'], $titles);
        $this->assertSame($dangBan->id, (int) KnowledgeChunk::query()->where('source_type', 'tour')->value('source_id'));
    }

    public function test_moi_ngay_lich_trinh_la_mot_doan_tu_dung_duoc(): void
    {
        $tour = Tour::factory()->create(['title' => 'Tour vịnh Hạ Long']);

        TourItinerary::create([
            'tour_id' => $tour->id,
            'day_number' => 2,
            'title' => 'Du thuyền ngủ đêm trên vịnh',
            'content' => 'Đoàn lên du thuyền lúc 12h.',
        ]);

        app(KnowledgeIndexer::class)->rebuild();

        $chunk = KnowledgeChunk::query()->where('chunk_key', "tour:{$tour->id}:ngay-2")->firstOrFail();

        // Tên tour phải nằm trong chính nội dung đoạn: model không nhìn thấy đoạn nào
        // đứng cạnh đoạn nào, nên "ngày thứ hai lên du thuyền" một mình là vô danh.
        $this->assertStringContainsString('Tour vịnh Hạ Long', $chunk->content);
        $this->assertStringContainsString('Du thuyền ngủ đêm trên vịnh', $chunk->content);
    }

    /** Điều kiện để `ai:index` chạy được theo lịch mỗi giờ mà không sinh hóa đơn. */
    public function test_chay_lai_khong_ma_hoa_lai_doan_khong_doi(): void
    {
        Tour::factory()->create();

        $indexer = app(KnowledgeIndexer::class);

        $lanDau = $indexer->rebuild();
        $lanHai = $indexer->rebuild();

        $this->assertGreaterThan(0, $lanDau['embedded']);
        $this->assertSame(0, $lanHai['embedded']);
        $this->assertSame($lanDau['total'], $lanHai['unchanged']);
    }

    public function test_sua_tour_thi_ma_hoa_lai_dung_doan_do(): void
    {
        $tour = Tour::factory()->create();
        Tour::factory()->create();

        $indexer = app(KnowledgeIndexer::class);
        $indexer->rebuild();

        $tour->update(['description' => 'Mô tả vừa được điều hành viết lại.']);

        $this->assertSame(1, $indexer->rebuild()['embedded']);
    }

    public function test_tour_ngung_ban_thi_doan_cua_no_bi_go(): void
    {
        $tour = Tour::factory()->create();
        $indexer = app(KnowledgeIndexer::class);
        $indexer->rebuild();

        $this->assertSame(1, KnowledgeChunk::query()->where('source_id', $tour->id)->count());

        $tour->update(['status' => 'inactive']);
        $indexer->rebuild();

        $this->assertSame(0, KnowledgeChunk::query()->where('source_id', $tour->id)->count());
    }

    /** Chatbot phải nói theo bảng phí hệ thống đang trừ tiền, không phải bản chép tay. */
    public function test_chinh_sach_trong_kho_doc_tu_nguon_that(): void
    {
        app(KnowledgeIndexer::class)->rebuild();

        $chunk = KnowledgeChunk::query()->where('chunk_key', 'policy:huy-va-hoan-tien')->firstOrFail();

        $this->assertStringContainsString('hoàn 100%', $chunk->content);
        $this->assertStringContainsString(
            (string) config('booking.deposit_percent'),
            KnowledgeChunk::query()->where('chunk_key', 'policy:dat-tour-va-thanh-toan')->value('content'),
        );
    }

    // ─── Tìm kiếm ───────────────────────────────────────────────────────────

    public function test_tim_kiem_tra_ve_doan_lien_quan_nhat(): void
    {
        Tour::factory()->create(['title' => 'Tour vịnh Hạ Long', 'description' => 'Ngủ đêm trên du thuyền.']);
        Tour::factory()->create(['title' => 'Tour Phú Quốc', 'description' => 'Lặn biển ngắm san hô.']);

        app(KnowledgeIndexer::class)->rebuild();

        $ketQua = app(Retriever::class)->retrieve('cho mình hỏi tour lặn biển ở Phú Quốc');

        $this->assertNotEmpty($ketQua);
        $this->assertStringContainsString('Phú Quốc', $ketQua[0]['title']);
    }

    /**
     * Đổi model embedding mà chưa chạy lại ai:index thì kho còn lẫn vector cũ. So
     * hai vector khác số chiều vẫn ra một con số, chỉ là vô nghĩa và không có lỗi
     * nào để lần ra — nên phải bỏ qua chúng.
     */
    public function test_vector_lech_so_chieu_bi_bo_qua(): void
    {
        Tour::factory()->create(['title' => 'Tour vịnh Hạ Long']);
        app(KnowledgeIndexer::class)->rebuild();

        KnowledgeChunk::query()->update(['dimensions' => 4]);

        $this->assertSame([], app(Retriever::class)->retrieve('tour hạ long'));
    }

    // ─── Công cụ tra dữ liệu sống ───────────────────────────────────────────

    public function test_cong_cu_khong_liet_ke_dot_da_het_cho(): void
    {
        $tour = Tour::factory()->create();

        TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(20),
            'max_people' => 10,
            'booked_people' => 10,
        ]);

        $conCho = TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(30),
            'booking_deadline' => now()->addDays(27),
            'max_people' => 10,
            'booked_people' => 4,
        ]);

        $ketQua = app(AdvisorTools::class)->run('check_availability', ['tour' => $tour->slug]);

        $this->assertCount(1, $ketQua['departures']);
        $this->assertSame($conCho->start_date->format('Y-m-d'), $ketQua['departures'][0]['start_date']);
        $this->assertSame(6, $ketQua['departures'][0]['seats_left']);
    }

    public function test_cong_cu_tinh_hoan_tien_theo_bang_phi_that(): void
    {
        $ketQua = app(AdvisorTools::class)->run('estimate_refund', ['days_before' => 25, 'amount' => 4_000_000]);

        $this->assertSame(100, $ketQua['refund_percent']);
        $this->assertSame(4_000_000.0, (float) $ketQua['refund_amount']);

        $satNgay = app(AdvisorTools::class)->run('estimate_refund', ['days_before' => 1]);

        $this->assertSame(0, $satNgay['refund_percent']);
    }

    /** Tham số sai thành dữ liệu trả về, không thành ngoại lệ làm hỏng cả lượt trả lời. */
    public function test_cong_cu_tra_loi_bang_loi_khi_khong_tim_thay_tour(): void
    {
        $ketQua = app(AdvisorTools::class)->run('get_tour_detail', ['tour' => 'khong-co-tour-nao-ten-nay']);

        $this->assertArrayHasKey('error', $ketQua);
    }

    // ─── Điểm cuối chat ─────────────────────────────────────────────────────

    public function test_khach_vang_lai_hoi_duoc_va_hoi_thoai_duoc_luu(): void
    {
        Tour::factory()->create(['title' => 'Tour vịnh Hạ Long']);
        app(KnowledgeIndexer::class)->rebuild();

        $this->chatResponses = [$this->textBody('Bên mình có tour vịnh Hạ Long 3 ngày.')];

        $response = $this->postJson('/api/chat', ['message' => 'Có tour Hạ Long không?'])->assertOk();

        $token = $response->json('data.conversation_token');

        $this->assertNotEmpty($token);
        $this->assertSame('Bên mình có tour vịnh Hạ Long 3 ngày.', $response->json('data.answer'));
        $this->assertSame(2, AiChatMessage::query()->where('conversation_token', $token)->count());
    }

    public function test_goi_cong_cu_xong_tra_ve_the_tour(): void
    {
        $tour = Tour::factory()->create(['title' => 'Tour vịnh Hạ Long']);

        $this->chatResponses = [
            $this->toolCallBody('search_tours', ['keyword' => 'Hạ Long']),
            $this->textBody('Mình gợi ý tour vịnh Hạ Long, giá 1.000.000đ.'),
        ];

        $response = $this->postJson('/api/chat', ['message' => 'Tour Hạ Long giá bao nhiêu?'])->assertOk();

        $this->assertSame($tour->id, $response->json('data.tours.0.id'));
        $this->assertSame($tour->slug, $response->json('data.tours.0.slug'));

        $luot = AiChatMessage::query()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('search_tours', $luot->tool_calls[0]['name']);
    }

    /** "Chưa bật" và "đang bận" dẫn người vận hành đi hai hướng khác nhau. */
    public function test_chua_khai_khoa_thi_bao_chua_bat(): void
    {
        config(['ai.openai.key' => null]);

        $this->postJson('/api/chat', ['message' => 'Có tour nào không?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Trợ lý ảo chưa được bật trên máy chủ này.');
    }

    public function test_khong_noi_tiep_duoc_hoi_thoai_cua_nguoi_khac(): void
    {
        $user = User::factory()->create();
        $token = (string) Str::uuid();

        AiChatMessage::query()->create([
            'conversation_token' => $token,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Đơn của tôi mã VVB123, số điện thoại 0900000000.',
        ]);

        $this->chatResponses = [$this->textBody('Mình chưa có thông tin đó.')];

        $response = $this->postJson('/api/chat', [
            'message' => 'Nhắc lại giúp tôi thông tin vừa rồi',
            'conversation_token' => $token,
        ])->assertOk();

        $this->assertNotSame($token, $response->json('data.conversation_token'));
    }

    public function test_cau_hoi_rong_bi_chan(): void
    {
        $this->postJson('/api/chat', ['message' => ''])->assertStatus(422);

        Http::assertNothingSent();
    }

    // ─── Giả lập OpenAI ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function embeddingBody(Request $request): array
    {
        $inputs = (array) ($request->data()['input'] ?? []);

        $data = [];

        foreach (array_values($inputs) as $index => $text) {
            $lower = mb_strtolower((string) $text);

            $data[] = [
                'index' => $index,
                'embedding' => array_map(
                    fn (string $keyword) => (float) mb_substr_count($lower, $keyword),
                    self::KEYWORDS,
                ),
            ];
        }

        return ['data' => $data];
    }

    /** @return array<string, mixed> */
    private function textBody(string $text): array
    {
        return [
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function toolCallBody(string $name, array $arguments): array
    {
        return [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_test',
                        'type' => 'function',
                        'function' => ['name' => $name, 'arguments' => json_encode($arguments)],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
            'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 20],
        ];
    }
}
