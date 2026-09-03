<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CloudinaryService
{
    public function uploadImage(UploadedFile $file, string $folder = 'vivu-booking/tours'): string
    {
        $credentials = $this->credentials();
        $timestamp = time();
        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        $signature = sha1(urldecode(http_build_query($paramsToSign)) . $credentials['secret']);

        $request = app()->environment('local')
            ? Http::withoutVerifying()
            : Http::withOptions([]);

        try {
            $response = $request
                /*
                 * Ngân sách thời gian rộng hơn mặc định, và thử lại vài lần.
                 *
                 * Mười giây mặc định của Laravel là ngân sách cho CẢ bước bắt tay TLS, không riêng
                 * bước mở kết nối. Trên đường truyền chập chờn — Wi-Fi hội trường, mạng khách sạn,
                 * điện thoại phát 4G — nó hết giờ trước khi kết nối kịp dựng xong, và người dùng
                 * nhận được một lỗi cURL thô sau khi đã điền xong cả biểu mẫu tour.
                 *
                 * Ảnh đã nằm sẵn trong bộ nhớ nên gửi lại không tốn gì thêm. Bắt người ta nhập lại
                 * toàn bộ thông tin tour vì một lần rớt gói tin thì tốn thật.
                 */
                ->connectTimeout(30)
                ->timeout(60)
                ->retry(3, 1500, throw: false)
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )->post("https://api.cloudinary.com/v1_1/{$credentials['cloud_name']}/image/upload", [
                    'api_key' => $credentials['api_key'],
                    'folder' => $folder,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]);
        } catch (ConnectionException $e) {
            /*
             * Tách "không nối được" khỏi "nối được nhưng bị từ chối".
             *
             * Hai chuyện này đòi hai người khác nhau xử lý, mà trước đây chúng ném ra cùng một câu.
             * Lỗi mạng thì lọt nguyên văn thông báo cURL lên màn hình, còn người đọc nó lại đi lục
             * `CLOUDINARY_URL` — một cấu hình chưa từng được gửi đi, vì kết nối còn chưa dựng xong.
             */
            throw new RuntimeException(
                'Không kết nối được tới Cloudinary. Đây là lỗi đường mạng của máy chủ, không phải '
                    . 'sai cấu hình CLOUDINARY_URL — hãy kiểm tra kết nối rồi thử lại.',
                0,
                $e,
            );
        }

        if (!$response->successful()) {
            // Kèm mã và lời từ chối của Cloudinary. Câu "không upload được" trơ trọi buộc người sửa
            // phải bật lại log rồi dựng lại đúng tình huống mới biết mình sai chữ ký hay quá dung lượng.
            throw new RuntimeException(sprintf(
                'Cloudinary từ chối ảnh (HTTP %d): %s',
                $response->status(),
                (string) $response->json('error.message', Str::limit($response->body(), 200)),
            ));
        }

        $secureUrl = $response->json('secure_url');

        if (!$secureUrl) {
            throw new RuntimeException('Cloudinary không trả về URL ảnh.');
        }

        return $secureUrl;
    }

    /**
     * Đọc qua `config()`, không gọi thẳng `env()`.
     *
     * `env()` chỉ còn đọc được khi tệp `.env` được nạp. Trên máy chạy thật người ta chạy
     * `php artisan config:cache` để tăng tốc, và từ giây đó **mọi lời gọi `env()` ngoài tệp config
     * đều trả về null** — Laravel không nạp `.env` nữa. Kết quả là mọi lượt tải ảnh ném
     * "Thiếu cấu hình CLOUDINARY_URL" dù biến môi trường vẫn nằm nguyên đó, và lỗi chỉ xuất hiện
     * sau khi tối ưu cấu hình chứ không xuất hiện lúc phát triển.
     */
    private function credentials(): array
    {
        $url = config('services.cloudinary.url');

        if (!$url) {
            throw new RuntimeException('Thiếu cấu hình CLOUDINARY_URL.');
        }

        $parts = parse_url($url);
        $credentials = [
            'cloud_name' => $parts['host'] ?? '',
            'api_key' => $parts['user'] ?? '',
            'secret' => $parts['pass'] ?? '',
        ];

        if (!$credentials['cloud_name'] || !$credentials['api_key'] || !$credentials['secret']) {
            throw new RuntimeException('CLOUDINARY_URL phải có dạng cloudinary://api_key:api_secret@cloud_name.');
        }

        return $credentials;
    }
}
