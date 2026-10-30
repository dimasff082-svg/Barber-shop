<?php

namespace App\Http\Controllers\Queue;

use App\Enums\BarberAvailability;
use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use App\Services\Queue\BarberAvailabilityService;
use App\Services\Queue\QueueWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffQueueController extends Controller
{
    public function callNext(Request $request, int $barber, QueueWorkflow $workflow): JsonResponse|RedirectResponse
    {
        return $this->result($request, $workflow->callNext($request->user(), $barber));
    }

    public function start(Request $request, string $ticket, QueueWorkflow $workflow): JsonResponse|RedirectResponse
    {
        return $this->result($request, $workflow->start($request->user(), $ticket));
    }

    public function complete(Request $request, string $ticket, QueueWorkflow $workflow): JsonResponse|RedirectResponse
    {
        return $this->result($request, $workflow->complete($request->user(), $ticket));
    }

    public function cancel(Request $request, string $ticket, QueueWorkflow $workflow): JsonResponse|RedirectResponse
    {
        $input = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->result($request, $workflow->cancelByAdmin($request->user(), $ticket, $input['reason']));
    }

    public function availability(Request $request, int $barber, BarberAvailabilityService $service): JsonResponse|RedirectResponse
    {
        $input = $request->validate(['availability' => ['required', Rule::enum(BarberAvailability::class)]]);
        $model = $service->change($request->user(), $barber, $input['availability']);

        if ($request->hasHeader('X-Inertia')) {
            return back()->with('success', 'Ketersediaan barber diperbarui.');
        }

        return response()->json(['barber_id' => $model->id, 'availability' => $model->availability_status->value]);
    }

    public function missed(Request $request, string $ticket, QueueWorkflow $workflow): JsonResponse|RedirectResponse
    {
        return $this->result($request, $workflow->markMissed($request->user(), $ticket));
    }

    private function result(Request $request, ?QueueTicket $ticket): JsonResponse|RedirectResponse
    {
        if ($request->hasHeader('X-Inertia')) {
            return back()->with('success', $ticket === null ? 'Belum ada antrean yang siap dipanggil.' : 'Antrean berhasil diperbarui.');
        }

        return response()->json(['ticket' => $ticket ? [
            'id' => $ticket->id, 'queue_number' => $ticket->queue_number, 'status' => $ticket->status->value,
        ] : null]);
    }
}
