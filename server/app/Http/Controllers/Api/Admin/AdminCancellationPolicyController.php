<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CancellationPolicy;
use App\Services\CancellationPolicyService;
use App\Support\GioVietNam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * B05 - Chính sách hủy. **Một bảng phí duy nhất, áp cho toàn bộ tour.**
 *
 * Trước đây màn này là danh sách: tạo được nhiều chính sách, mỗi tour chọn một cái. Bỏ đi vì
 * hai lý do, và lý do thứ hai mới là lý do thật:
 *
 *   1. Công ty lữ hành cỡ này bán một dòng sản phẩm, không có lý do nghiệp vụ để tour Hạ Long
 *      và tour Sapa hoàn tiền theo hai bảng khác nhau.
 *   2. Nhiều chính sách sinh ra câu hỏi "cái nào áp cho đơn nào" ở mọi màn hình chạm tới tiền -
 *      một câu hỏi không ai được lợi khi phải trả lời.
 *
 * Còn giữ, và cố ý giữ: **đơn vẫn chép chính sách vào chính nó lúc đặt**
 * (`bookings.cancellation_policy_id`). Chỉ có một chính sách nên bản chép luôn giống bản gốc -
 * cho tới ngày ai đó sửa bảng phí. Từ giây đó, đơn đã bán vẫn giữ điều khoản khách đã đồng ý,
 * còn đơn mới theo bảng mới. Không có bản chép thì sửa một con số là hồi tố lên toàn bộ lịch sử.
 *
 * Nên controller này chỉ còn hai việc: đọc bảng phí, và ghi bảng phí. Không tạo, không xóa.
 */
class AdminCancellationPolicyController extends Controller
{
    /**
     * Bảng phí mới nhất, kể cả bản đã hẹn mà chưa tới giờ.
     *
     * Trả bản mới nhất chứ không phải bản đang chạy: người vừa hẹn một bảng phí cho tháng sau, mở
     * lại màn hình mà thấy bảng cũ thì sẽ tưởng thao tác của mình không ăn. Bản chưa tới giờ được
     * đánh dấu bằng chính `effective_from` của nó, và màn hình nói ra điều đó.
     *
     * Tự dựng từ `CancellationPolicyService::DEFAULT_RULES` nếu cơ sở dữ liệu chưa có gì, thay vì
     * trả về rỗng. Màn hình chính sách mà mở ra trống trơn thì người dùng không biết hệ thống
     * đang áp mức nào - trong khi thực tế lớp dịch vụ vẫn có bảng phí mặc định viết trong mã.
     */
    public function index(): JsonResponse
    {
        return $this->success($this->layHoacTao()->load('rules'), 'Lấy chính sách hủy thành công');
    }

    /**
     * Ghi bảng phí mới.
     *
     * **Tạo một bản ghi MỚI rồi chuyển cờ mặc định sang nó**, chứ không sửa đè lên bản cũ. Đây là
     * điểm mấu chốt của cả nhóm và nó không hiển nhiên:
     *
     * Đơn đã đặt trỏ tới bản ghi chính sách bằng khóa ngoại. Sửa đè lên bản ghi ấy thì mọi đơn
     * đang trỏ vào nó lập tức đổi điều khoản theo - tức là thương lượng lại hợp đồng đã ký bằng
     * một lần bấm Lưu. Việc chép `cancellation_policy_id` vào đơn lúc đặt chỉ có nghĩa khi bản
     * được chép không bị viết lại.
     *
     * Nên mỗi lần sửa là một phiên bản. Bản cũ ở lại cho đơn cũ, bản mới áp cho đơn từ giờ trở đi.
     * Bảng dày lên theo số lần sửa, và đó là cái giá đúng: đó là lịch sử điều khoản đã bán.
     *
     * **Mốc hiệu lực do người sửa đặt.** Chọn ngay bây giờ thì bản mới áp lập tức; chọn 0h ngày
     * mùng 1 thì nó nằm im tới đúng lúc đó. Không có cờ nào phải bật, không có tác vụ nền nào phải
     * chạy - `CancellationPolicy::dangApDung()` so mốc với hiện tại mỗi lần được hỏi.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'effective_from' => ['required', 'date'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.min_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'rules.*.max_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'rules.*.refund_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'rules.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'effective_from.required' => 'Phải chọn thời điểm bảng phí này bắt đầu áp dụng.',
            'rules.required' => 'Chính sách hủy phải có ít nhất một bậc phí.',
            'rules.*.min_days_before.max' => 'Mốc ngày không vượt quá 365.',
            'rules.*.refund_percent.max' => 'Mức hoàn không vượt quá 100 phần trăm.',
        ]);

        // Chuỗi từ ô datetime-local là giờ treo tường, không kèm múi giờ - đúng thứ cột này lưu.
        $hieuLuc = Carbon::parse($validated['effective_from']);

        if ($loi = $this->validateHieuLuc($hieuLuc)) {
            return $this->error($loi, 422);
        }

        if ($loi = $this->validateRules($validated['rules'])) {
            return $this->error($loi, 422);
        }

        $moi = DB::transaction(function () use ($validated, $hieuLuc) {
            /*
             * Không đụng tới bản cũ. Chúng ở lại nguyên vẹn cho đơn đã trỏ vào, và bản nào đang
             * áp dụng thì mốc thời gian tự quyết - không còn cờ nào để hạ.
             */
            $moi = CancellationPolicy::query()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'effective_from' => $hieuLuc,
            ]);

            foreach ($validated['rules'] as $rule) {
                $moi->rules()->create([
                    'min_days_before' => (int) $rule['min_days_before'],
                    'max_days_before' => isset($rule['max_days_before']) ? (int) $rule['max_days_before'] : null,
                    'refund_percent' => (int) $rule['refund_percent'],
                    'note' => $rule['note'] ?? null,
                ]);
            }

            return $moi;
        });

        return $this->success(
            $moi->load('rules'),
            $moi->daCoHieuLuc()
                ? 'Đã cập nhật chính sách hủy. Đơn đã đặt trước đó giữ nguyên điều khoản cũ.'
                : sprintf(
                    'Đã hẹn chính sách mới, áp dụng từ %s. Từ giờ tới lúc đó vẫn chạy bảng phí cũ.',
                    $hieuLuc->format('H:i d/m/Y'),
                ),
        );
    }

    /** Bản mới nhất, kể cả bản đã hẹn. Chưa có bản nào thì dựng bản mặc định. */
    private function layHoacTao(): CancellationPolicy
    {
        if ($policy = CancellationPolicy::moiNhat()) {
            return $policy;
        }

        return DB::transaction(function () {
            $policy = CancellationPolicy::query()->create([
                'name' => 'Chính sách hủy tiêu chuẩn',
                'description' => 'Phí hủy tăng dần khi càng sát ngày khởi hành, vì chi phí đã cam '
                    . 'kết với nhà cung cấp càng khó hủy.',
                // Bản dựng tự động phải áp dụng ngay, nếu không hệ thống chạy không chính sách nào.
                'effective_from' => GioVietNam::bayGio(),
            ]);

            foreach (CancellationPolicyService::DEFAULT_RULES as $rule) {
                $policy->rules()->create($rule);
            }

            return $policy;
        });
    }

    /**
     * Mốc hiệu lực không được đặt vào quá khứ.
     *
     * Đặt lùi thì trên giấy tờ bảng phí mới trông như đã áp dụng từ hôm qua, trong khi các đơn đặt
     * hôm qua đã chép bản cũ vào chính chúng và không đổi. Không hồi tố được là điều đúng, nhưng
     * để người dùng gõ một mốc rồi hệ thống ngầm hiểu theo cách khác thì là nói dối họ.
     *
     * Nới một phút cho lệch đồng hồ giữa trình duyệt và máy chủ, để người chọn đúng "bây giờ"
     * không bị chặn oan.
     */
    private function validateHieuLuc(Carbon $hieuLuc): ?string
    {
        if ($hieuLuc->lessThan(GioVietNam::bayGio()->subMinute())) {
            return 'Không đặt được mốc áp dụng vào quá khứ. Đơn đã đặt trước đó giữ nguyên điều '
                . 'khoản cũ nên đặt lùi cũng không đổi được gì; chọn thời điểm hiện tại nếu muốn '
                . 'áp dụng ngay.';
        }

        return null;
    }

    /**
     * Hai ràng buộc không diễn đạt được bằng luật validate vì phải so các phần tử với nhau.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function validateRules(array $rules): ?string
    {
        foreach ($rules as $index => $rule) {
            $min = (int) $rule['min_days_before'];
            $max = $rule['max_days_before'] ?? null;

            if ($max !== null && (int) $max <= $min) {
                return sprintf('Bậc thứ %d: mốc trên phải lớn hơn mốc dưới.', $index + 1);
            }
        }

        // Phải có bậc phủ mốc 0 ngày, nếu không thì hủy sát ngày đi sẽ không rơi vào bậc nào
        // và hệ thống lặng lẽ hoàn 0 phần trăm mà không có căn cứ nào ghi ra.
        $coBacTuKhong = collect($rules)->contains(
            fn ($rule) => (int) $rule['min_days_before'] === 0
        );

        if (!$coBacTuKhong) {
            return 'Phải có một bậc bắt đầu từ 0 ngày để phủ trường hợp hủy sát ngày khởi hành.';
        }

        return null;
    }
}
