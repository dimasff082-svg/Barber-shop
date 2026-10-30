<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAuditEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['target_type' => ['nullable', 'string', 'max:100'], 'action' => ['nullable', 'string', 'max:100']]);
        $entries = StaffAuditEntry::query()->with('actor:id,name')
            ->when($filters['target_type'] ?? null, fn ($query, $type) => $query->where('target_type', $type))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (StaffAuditEntry $entry) => [
                'id' => $entry->id, 'actor' => $entry->actor->name, 'action' => $entry->action,
                'target_type' => $entry->target_type, 'target_id' => $entry->target_id,
                'metadata' => $entry->metadata, 'created_at' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/audit', [
            'entries' => $entries, 'filters' => ['target_type' => $filters['target_type'] ?? '', 'action' => $filters['action'] ?? ''],
            'timezone' => (string) config('barberflow.timezone'),
        ]);
    }
}
