<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

        $response = $request->attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$credentials['cloud_name']}/image/upload", [
            'api_key' => $credentials['api_key'],
            'folder' => $folder,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Không thể upload ảnh lên Cloudinary.');
        }

        $secureUrl = $response->json('secure_url');

        if (!$secureUrl) {
            throw new RuntimeException('Cloudinary không trả về URL ảnh.');
        }

        return $secureUrl;
    }

    private function credentials(): array
    {
        $url = env('CLOUDINARY_URL');

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
