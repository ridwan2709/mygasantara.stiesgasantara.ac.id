<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FonnteService
{
    public function send(string $phone, string $message): void
    {
        $token = config('services.fonnte.token');

        if (! $token) {
            throw new RuntimeException('Token Fonnte belum dikonfigurasi.');
        }

        $target = $this->normalizePhone($phone);

        if ($target === '') {
            throw new RuntimeException('Nomor WhatsApp mahasiswa belum tersedia.');
        }

        $response = Http::asForm()
            ->withHeaders(['Authorization' => $token])
            ->timeout(30)
            ->post(config('services.fonnte.url', 'https://api.fonnte.com/send'), [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Fonnte gagal mengirim pesan: '.$response->body());
        }

        $payload = $response->json();

        if (isset($payload['status']) && $payload['status'] === false) {
            throw new RuntimeException((string) ($payload['reason'] ?? 'Fonnte menolak pengiriman pesan.'));
        }
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', trim($phone)) ?? '';

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        if (str_starts_with($phone, '62')) {
            return $phone;
        }

        return $phone;
    }
}
