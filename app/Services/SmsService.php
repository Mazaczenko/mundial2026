<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $normalized = $this->normalizePhone($phone);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.smsapi.token'),
            ])->asForm()->post('https://api.smsapi.pl/sms.do', [
                'to' => $normalized,
                'message' => $message,
                'from' => config('services.smsapi.sender', 'MUNDIAL26'),
                'format' => 'json',
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('SmsService: failed to send SMS', [
                'phone' => $normalized,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('SmsService: exception while sending SMS', [
                'phone' => $normalized,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function normalizePhone(string $phone): string
    {
        return ltrim($phone, '+');
    }
}
