<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ReportRequest;
use App\Services\Queue\QueueRules;
use App\Services\Staff\OperationsReadModel;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(ReportRequest $request, OperationsReadModel $read, QueueRules $rules): Response
    {
        $today = $rules->businessNow();
        $from = $request->string('from')->toString() ?: $today->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: $today->toDateString();

        return Inertia::render('admin/reports', $read->report($from, $to));
    }
}
