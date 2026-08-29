<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bậc phí hủy đếm bằng NGÀY, và mỗi bản chính sách có mốc bắt đầu hiệu lực.
 *
 * ## Vì sao đổi giờ thành ngày
 *
 * Bảng phí được viết ra bằng ngày và được đọc ra bằng ngày: hợp đồng ghi "hủy trước 15 ngày hoàn
 * 90%", khách hỏi "còn mấy ngày nữa", điều hành trả lời bằng ngày. Chỉ có ô nhập là bắt gõ 360.
 *
 * Người nhập phải tự nhân 24 mỗi lần, và nhân sai thì không có gì chặn: 300 là một con số hợp lệ,
 * nó chỉ có nghĩa là 12,5 ngày - một mốc không ai định đặt ra. Cột đếm bằng giờ cho phép diễn đạt
 * những thứ nghiệp vụ không dùng tới, và đó là chỗ lỗi chui vào.
 *
 * Đổi cột chứ không chỉ đổi ô nhập: để nguyên cột giờ mà giao diện nhân 24 thì cột ấy chỉ còn chứa
 * bội số của 24, tức một cột rộng hơn thứ nó được phép đựng. Sớm muộn có người ghi thẳng vào cơ sở
 * dữ liệu một giá trị lẻ, và bảng phí có một bậc mà màn hình không hiển thị đúng.
 *
 * Chuyển giá trị cũ bằng phép chia lấy nguyên. Năm bậc mặc định đều là bội số của 24 (360, 192,
 * 96, 48, 0) nên không mất gì.
 *
 * ## Vì sao thêm effective_from
 *
 * Trước đây bản nào mang cờ `is_default` là bản đang áp dụng. Cờ ấy không diễn đạt được câu
 * "bảng phí mới có hiệu lực từ 0h ngày 1/10" - mà đó lại là cách một công ty thật đổi điều khoản:
 * công bố trước, áp sau, để khách đang cân nhắc biết mình đặt trước hay sau mốc.
 *
 * Cờ chỉ đúng tại thời điểm ai đó bật nó lên. Mốc thời gian thì tự đúng, kể cả lúc không có ai
 * chạy dòng lệnh nào - nên `is_default` bị bỏ hẳn thay vì để cạnh mốc mới. Hai thứ cùng trả lời
 * một câu hỏi là kiểu lỗi dự án này đã gặp nhiều lần: sửa một bên, quên bên kia.
 *
 * Bản đang áp dụng từ đây là **bản có `effective_from` gần nhất mà chưa vượt quá hiện tại**.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bọc trong hasColumn để chạy lại được sau một lần hỏng giữa chừng: MySQL không quay ngược
        // được lệnh DDL, nên lần chạy trước có thể đã kịp thêm hai cột này rồi mới dừng.
        if (!Schema::hasColumn('cancellation_policy_rules', 'min_days_before')) {
            Schema::table('cancellation_policy_rules', function (Blueprint $table) {
                $table->unsignedSmallInteger('min_days_before')->default(0)->after('cancellation_policy_id');
                $table->unsignedSmallInteger('max_days_before')->nullable()->after('min_days_before');
            });
        }

        /*
         * Phép chia làm bằng PHP chứ không bằng SQL.
         *
         * Hai engine chia khác nhau: SQLite cắt phần lẻ, MySQL làm tròn khi ghi vào cột nguyên.
         * Một bậc 47 giờ sẽ thành 1 ngày ở máy này và 2 ngày ở máy kia. `FLOOR()` bắc cầu được cho
         * MySQL nhưng SQLite ở đây không có hàm đó.
         *
         * `intdiv` cắt phần lẻ ở cả hai, và bảng này có vài dòng nên vòng lặp không đáng kể.
         */
        foreach (DB::table('cancellation_policy_rules')->get() as $bac) {
            DB::table('cancellation_policy_rules')->where('id', $bac->id)->update([
                'min_days_before' => intdiv((int) $bac->min_hours_before, 24),
                'max_days_before' => $bac->max_hours_before === null
                    ? null
                    : intdiv((int) $bac->max_hours_before, 24),
            ]);
        }

        /*
         * Dựng chỉ mục mới TRƯỚC rồi mới bỏ chỉ mục cũ.
         *
         * `idx_policy_rules_lookup` bắt đầu bằng `cancellation_policy_id`, và MySQL dùng luôn nó
         * để đỡ khóa ngoại trên cột ấy. Bỏ trước khi có cái thay thế thì MySQL từ chối:
         * "Cannot drop index: needed in a foreign key constraint". SQLite không kiểm điều này nên
         * thứ tự ngược lại vẫn chạy lọt ở máy phát triển và chỉ hỏng ở máy chạy MySQL.
         *
         * Chỉ mục mới mang tên khác vì hai chỉ mục không trùng tên được, mà lúc này cả hai cùng
         * tồn tại. Tên mới cũng nói đúng nội dung hơn.
         */
        Schema::table('cancellation_policy_rules', function (Blueprint $table) {
            $table->index(['cancellation_policy_id', 'min_days_before'], 'idx_policy_rules_days');
        });

        Schema::table('cancellation_policy_rules', function (Blueprint $table) {
            $table->dropIndex('idx_policy_rules_lookup');
            $table->dropColumn(['min_hours_before', 'max_hours_before']);
        });

        Schema::table('cancellation_policies', function (Blueprint $table) {
            $table->dateTime('effective_from')->nullable()->after('description');
        });

        /*
         * Bản đang có sẵn coi như đã có hiệu lực từ lúc nó được tạo.
         *
         * COALESCE vì bước ngay sau đây bắt cột này NOT NULL: một hàng lỡ thiếu `created_at` sẽ
         * làm cả lệnh đổi kiểu hỏng, và hỏng ở giữa một migration MySQL thì không quay ngược được.
         */
        DB::table('cancellation_policies')->update([
            'effective_from' => DB::raw("COALESCE(created_at, '2020-01-01 00:00:00')"),
        ]);

        Schema::table('cancellation_policies', function (Blueprint $table) {
            $table->dateTime('effective_from')->nullable(false)->change();
            $table->dropIndex(['is_default']);
            $table->dropColumn('is_default');
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_policies', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('description');
            $table->dropIndex(['effective_from']);
            $table->dropColumn('effective_from');
            $table->index('is_default');
        });

        Schema::table('cancellation_policy_rules', function (Blueprint $table) {
            $table->unsignedInteger('min_hours_before')->default(0)->after('cancellation_policy_id');
            $table->unsignedInteger('max_hours_before')->nullable()->after('min_hours_before');
        });

        foreach (DB::table('cancellation_policy_rules')->get() as $bac) {
            DB::table('cancellation_policy_rules')->where('id', $bac->id)->update([
                'min_hours_before' => (int) $bac->min_days_before * 24,
                'max_hours_before' => $bac->max_days_before === null
                    ? null
                    : (int) $bac->max_days_before * 24,
            ]);
        }

        // Cùng lý do như ở up(): chỉ mục đỡ khóa ngoại phải có cái thay thế trước khi bị bỏ.
        Schema::table('cancellation_policy_rules', function (Blueprint $table) {
            $table->index(['cancellation_policy_id', 'min_hours_before'], 'idx_policy_rules_lookup');
        });

        Schema::table('cancellation_policy_rules', function (Blueprint $table) {
            $table->dropIndex('idx_policy_rules_days');
            $table->dropColumn(['min_days_before', 'max_days_before']);
        });
    }
};
