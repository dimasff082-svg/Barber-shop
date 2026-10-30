<?php

namespace App\Http\Controllers\Queue;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\QueueTicket;
use App\Services\PublicCatalog;
use App\Services\Queue\QueueRules;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QueuePageController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        return Inertia::render('queue/index', $this->formProps($request, $catalog));
    }

    public function location(Request $request, string $code, PublicCatalog $catalog): Response|HttpResponse
    {
        $qr = QrCode::query()->where('code', $code)->where('is_active', true)->first();
        if ($qr === null) {
            return Inertia::render('queue/invalid-qr', ['message' => 'QR tidak valid atau sudah tidak aktif.'])->toResponse($request)->setStatusCode(404);
        }

        return Inertia::render('queue/location', $this->formProps($request, $catalog, $qr));
    }

    public function show(string $ticket, QueueRules $rules): Response
    {
        // Same wrapper for valid-looking IDs, regardless of existence. Private data is fetched only after authorization.
        Inertia::encryptHistory();

        return Inertia::render('queue/ticket', [
            'ticket_id' => $ticket,
            'urls' => [
                'status' => route('queue.status', $ticket), 'access' => route('queue.access', $ticket),
                'secret' => route('queue.secret', $ticket), 'secret_ack' => route('queue.secret.ack', $ticket),
                'check_in' => route('queue.check-in', $ticket), 'cancel' => route('queue.cancel', $ticket),
            ],
            'timezone' => (string) config('barberflow.timezone'), 'grace_minutes' => $rules->graceMinutes(),
        ]);
    }

    /** @return array<string, mixed> */
    private function formProps(Request $request, PublicCatalog $catalog, ?QrCode $qr = null): array
    {
        $grants = $request->session()->get('queue.grants', []);
        $recent = [];
        if (is_array($grants)) {
            foreach (QueueTicket::query()->whereIn('id', array_slice(array_keys($grants), -5))->orderByDesc('created_at')->get() as $ticket) {
                if (is_string($grants[$ticket->id] ?? null) && hash_equals($ticket->token_hash, $grants[$ticket->id])) {
                    $recent[] = ['id' => $ticket->id, 'url' => route('queue.show', $ticket->id)];
                }
            }
        }

        return [
            ...$catalog->data(),
            'qr' => $qr === null ? null : ['code' => $qr->code, 'label' => $qr->label],
            'store_url' => $qr === null ? route('queue.store') : route('queue.onsite.store', $qr->code),
            'selected_service' => $request->integer('service') ?: null,
            'selected_barber' => $request->integer('barber') ?: null,
            'recent_tickets' => $recent,
        ];
    }
}
