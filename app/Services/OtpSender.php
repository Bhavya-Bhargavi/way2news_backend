<?php

namespace App\Services;

use RuntimeException;

class OtpSender
{
    public function send(string $phone, string $code): void
    {
        if (config('services.infinito.enabled')) {
            throw new RuntimeException('Infinito SMS is enabled but its provider adapter is not configured.');
        }

        throw new RuntimeException('OTP test mode is disabled and no SMS provider is configured.');
    }
}