<?php

namespace Database\Seeders;

use App\Models\CancellationPolicy;
use App\Services\CancellationPolicyService;
use App\Support\GioVietNam;
use Illuminate\Database\Seeder;

/** Bảng bậc ngày cũ chỉ còn là dữ liệu lịch sử; tiền hoàn dùng hạn chốt từng chuyến. */
class CancellationPolicySeeder extends Seeder
{
    public function run(): void
    {
        CancellationPolicy::query()->firstOrCreate(
            ['name' => CancellationPolicyService::NAME],
            [
                'description' => 'Trước hạn chốt danh sách hoàn đủ số đã trả. Từ hạn chốt đến trước khởi hành giữ cọc 50% giá trị đơn, hoàn phần đã trả vượt cọc. Đã khởi hành không cho hủy.',
                'effective_from' => GioVietNam::bayGio()->subDay(),
            ],
        );
    }
}
