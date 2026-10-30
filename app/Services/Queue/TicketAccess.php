<?php

namespace App\Services\Queue;

use App\Models\QueueTicket;
use Illuminate\Http\Request;

class TicketAccess
{
    public function ownerSecret(Request $request): string
    {
        $secret = $request->session()->get('queue.owner');
        if (! is_string($secret) || strlen($secret) !== 64) {
            $secret = bin2hex(random_bytes(32));
            $request->session()->put('queue.owner', $secret);
        }

        return $secret;
    }

    public function grant(Request $request, QueueTicket $ticket): void
    {
        $request->session()->put('queue.grants.'.$ticket->id, $ticket->token_hash);
    }

    public function authorize(Request $request, string $id, bool $bearerOnly = false): QueueTicket
    {
        $ticket = QueueTicket::query()->find($id);
        abort_unless($ticket instanceof QueueTicket, 404);

        $token = $request->bearerToken();
        $validBearer = is_string($token) && strlen($token) >= 32 && strlen($token) <= 128
            && hash_equals($ticket->token_hash, hash('sha256', $token));
        $grant = $request->session()->get('queue.grants.'.$id);
        $validSession = ! $bearerOnly && is_string($grant) && hash_equals($ticket->token_hash, $grant);
        abort_unless($validBearer || $validSession, 404);

        return $ticket;
    }
}
