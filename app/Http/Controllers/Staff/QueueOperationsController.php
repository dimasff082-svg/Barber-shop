<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\QueueFilterRequest;
use App\Services\Staff\OperationsReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueOperationsController extends Controller
{
    public function index(QueueFilterRequest $request, OperationsReadModel $read): Response
    {
        return Inertia::render('staff/queues', $read->listing($request->user(), $request));
    }

    public function history(QueueFilterRequest $request, OperationsReadModel $read): Response
    {
        return Inertia::render('staff/queues', $read->listing($request->user(), $request, true));
    }

    public function show(Request $request, string $ticket, OperationsReadModel $read): Response
    {
        $model = $read->scoped($request->user())->whereKey($ticket)->firstOrFail();

        return Inertia::render('staff/queue-detail', ['ticket' => $read->ticket($model, $request->user()),
            'events' => $read->events($model), 'is_admin' => $request->user()->isAdmin(), 'timezone' => (string) config('barberflow.timezone')]);
    }
}
