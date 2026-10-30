<?php

namespace App\Services\Queue;

use Illuminate\Validation\ValidationException;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s().-]/', '', trim($phone)) ?? '';
        if (str_starts_with($phone, '08')) {
            $phone = '+62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '628')) {
            $phone = '+'.$phone;
        }

        if (! preg_match('/^\+628[0-9]{7,11}$/D', $phone)) {
            throw ValidationException::withMessages(['phone' => 'Gunakan nomor HP Indonesia yang valid, misalnya 081234567890.']);
        }

        return $phone;
    }
}
