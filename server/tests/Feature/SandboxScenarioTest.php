<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sandbox\SandboxScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Bộ kịch bản của sân thử.
 *
 * ## Bài kiểm này canh cái gì
 *
 * Mỗi kịch bản tự chấm từng bước của nó, nên thứ cần canh ở đây là **các dấu chấm ấy có đúng
 * không**: chạy hết mười hai kịch bản và đòi mọi bước đều đạt.
 *
 * Nếu một luật nghiệp vụ bị sửa lệch, kịch bản tương ứng sẽ có một bước không đạt và bài này đỏ —
 * kèm theo tên kịch bản, nên biết ngay chỗ hỏng. Đây là lớp canh rộng nhất trong cả bộ test: nó đi
 * qua dịch vụ chuyển chuyến, ghép chuyến, hủy chuyến, bảng phí, hai lệnh nền và sổ giao dịch trong
 * cùng một lượt.
 *
 * Và nó canh cả chính sân thử: một kịch bản dựng sai dữ liệu còn tệ hơn không có, vì người thử sẽ
 * ngồi tìm lỗi trong mã nghiệp vụ vốn đang đúng.
 */
class SandboxScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->seed(\Database\Seeders\CancellationPolicySeeder::class);

        User::create([
            'name' => 'Admin',
            'email' => 'admin-kb@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function runner(): SandboxScenarioRunner
    {
        return app(SandboxScenarioRunner::class);
    }

    /** @return array<int, array<int, string>> */
    public static function kichBanProvider(): array
    {
        return array_map(
            fn (array $kb) => [$kb['id'], $kb['ten']],
            SandboxScenarioRunner::danhMuc(),
        );
    }

    /** Mọi kịch bản phải chạy trọn và đạt ở mọi bước. */
    #[\PHPUnit\Framework\Attributes\DataProvider('kichBanProvider')]
    public function test_kich_ban_chay_dat(string $id, string $ten): void
    {
        $bienBan = $this->runner()->chay($id);

        $this->assertNotEmpty($bienBan['buoc'], "Kịch bản «{$ten}» không ghi bước nào.");

        foreach ($bienBan['buoc'] as $buoc) {
            $this->assertTrue(
                $buoc['dat'],
                sprintf(
                    "Kịch bản «%s» hỏng ở bước %d.\n  Làm gì: %s\n  Kỳ vọng: %s\n  Kết quả: %s",
                    $ten,
                    $buoc['thu_tu'],
                    $buoc['lam_gi'],
                    $buoc['ky_vong'],
                    $buoc['ket_qua'],
                ),
            );
        }

        $this->assertTrue($bienBan['dat']);
    }

    /** Chạy lại một kịch bản hai lần ra cùng kết quả — nó dọn dữ liệu lần trước rồi dựng lại. */
    public function test_chay_lai_van_dat(): void
    {
        $this->runner()->chay('coc_roi_bo_ngang');
        $lanHai = $this->runner()->chay('coc_roi_bo_ngang');

        $this->assertTrue($lanHai['dat'], 'Chạy lần hai phải ra đúng như lần đầu.');
    }

    /** Biên bản phải kèm sổ giao dịch có chiều tiền, vì đó là câu hỏi hay bị vặn nhất. */
    public function test_bien_ban_kem_so_giao_dich_co_chieu_tien(): void
    {
        $bienBan = $this->runner()->chay('coc_roi_tra_not');
        $don = $bienBan['don'][0];

        $this->assertCount(2, $don['so_giao_dich'], 'Cọc rồi trả nốt là hai dòng sổ.');

        foreach ($don['so_giao_dich'] as $dong) {
            $this->assertSame('+', $dong['chieu'], 'Cả hai đều là tiền vào.');
            $this->assertNotEmpty($dong['nhan']);
        }
    }

    /** Kịch bản không có thì báo lỗi đọc được, không im lặng trả về rỗng. */
    public function test_kich_ban_khong_ton_tai_thi_bao_loi(): void
    {
        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->runner()->chay('khong-co-that');
    }
}
