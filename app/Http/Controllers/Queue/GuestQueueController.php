<?php

namespace App\Http\Controllers\Queue;

use App\Enums\QueueSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\CreateTicketRequest;
use App\Models\QueueTicket;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueEstimate;
use App\Services\Queue\QueueWorkflow;
use App\Services\Queue\TicketAccess;
use App\Services\Queue\TicketSecretHandoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuestQueueController extends Controller
{
    public function __construct(private TicketAccess $access) {}

    public function store(CreateTicketRequest $request, CreateQueueTicket $creator): JsonResponse|RedirectResponse
    {
        return $this->create($request, $creator, QueueSource::Online);
    }

    public function storeOnsite(CreateTicketRequest $request, string $code, CreateQueueTicket $creator): JsonResponse|RedirectResponse
    {
        return $this->create($request, $creator, QueueSource::Onsite, $code);
    }

    public function exchange(Request $request, string $ticket): JsonResponse
    {
        $model = $this->access->authorize($request, $ticket, bearerOnly: true);
        $this->access->grant($request, $model);

        return response()->json(['ticket' => $this->details($model)]);
    }

    public function status(Request $request, string $ticket): JsonResponse
    {
        return response()->json(['ticket' => $this->details($this->access->authorize($request, $ticket))]);
    }

    public function secret(Request $request, string $ticket, TicketSecretHandoff $handoff): JsonResponse
    {
        $this->access->authorize($request, $ticket);

        return response()->json(['access_token' => $handoff->read($request, $ticket)]);
    }

    public function acknowledgeSecret(Request $request, string $ticket, TicketSecretHandoff $handoff): JsonResponse
    {
        $this->access->authorize($request, $ticket);
        $handoff->acknowledge($request, $ticket);

        return response()->json(['acknowledged' => true]);
    }

    public function checkIn(Request $request, string $ticket, QueueWorkflow $workflow): JsonResponse
    {
        $this->access->authorize($request, $ticket);
        $input = $request->validate(['qr_code' => ['required', 'string', 'max:64']]);
        $model = $workflow->checkIn($ticket, $input['qr_code']);

        return response()->json(['ticket' => $this->details($model)]);
    }

    public function cancel(Request $request, string $ticket, QueueWorkflow $workflow): JsonResponse
    {
        $this->access->authorize($request, $ticket);

        return response()->json(['ticket' => $this->details($workflow->cancelGuest($ticket))]);
    }

    private function create(CreateTicketRequest $request, CreateQueueTicket $creator, QueueSource $source, ?string $code = null): JsonResponse|RedirectResponse
    {
        $input = $request->validated();
        $input['source'] = $source;
        $input['qr_code'] = $code;
        $result = $creator->create($input, $this->access->ownerSecret($request));
        $this->access->grant($request, $result->ticket);

        if ($request->hasHeader('X-Inertia') || ! $request->expectsJson()) {
            if ($result->accessToken !== null) {
                app(TicketSecretHandoff::class)->store($request, $result->ticket->id, $result->accessToken);
            }

            return redirect()->route('queue.show', $result->ticket->id);
        }

        // Raw access token is returned once; never use a URL query or shared Inertia prop.
        return response()->json([
            'ticket' => $this->details($result->ticket),
            'access_token' => $result->accessToken,
            'created' => $result->created,
        ], $result->created ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function details(QueueTicket $ticket): array
    {
        $ticket->loadMissing(['barber', 'service']);

        return [
            'id' => $ticket->id,
            'queue_number' => $ticket->queue_number,
            'queue_date' => $ticket->queue_date->toDateString(),
            'status' => $ticket->status->value,
            'source' => $ticket->source->value,
            'customer_name' => $ticket->customer_name,
            'barber' => ['id' => $ticket->barber_id, 'name' => $ticket->barber->name],
            'service' => ['id' => $ticket->service_id, 'name' => $ticket->service->name],
            'price' => $ticket->price_snapshot,
            'duration_minutes' => $ticket->duration_snapshot,
            'grace_expires_at' => $ticket->grace_expires_at?->toIso8601String(),
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            'estimate' => app(QueueEstimate::class)->forTicket($ticket),
        ];
    }
}
