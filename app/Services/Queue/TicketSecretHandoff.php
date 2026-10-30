<?php

namespace App\Services\Queue;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class TicketSecretHandoff
{
    public function store(Request $request, string $id, string $token): void
    {
        // Database-backed sessions must not persist the raw recovery secret.
        $request->session()->put('queue.handoff.'.$id, [
            'ciphertext' => Crypt::encryptString($token), 'expires' => now()->addMinutes(5)->timestamp,
        ]);
    }

    public function read(Request $request, string $id): ?string
    {
        $value = $request->session()->get('queue.handoff.'.$id);
        if (! is_array($value) || ! is_int($value['expires'] ?? null) || $value['expires'] <= now()->timestamp
            || ! is_string($value['ciphertext'] ?? null)) {
            $this->acknowledge($request, $id);

            return null;
        }
        try {
            return Crypt::decryptString($value['ciphertext']);
        } catch (DecryptException) {
            $this->acknowledge($request, $id);

            return null;
        }
    }

    public function acknowledge(Request $request, string $id): void
    {
        $request->session()->forget('queue.handoff.'.$id);
    }
}
