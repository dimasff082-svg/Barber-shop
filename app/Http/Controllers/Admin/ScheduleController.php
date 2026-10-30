<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BarberScheduleRequest;
use App\Http\Requests\Admin\BusinessClosureRequest;
use App\Http\Requests\Admin\BusinessHoursRequest;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Services\StaffAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $hours = BusinessHour::query()->get()->keyBy('weekday');
        $schedules = BarberSchedule::query()->get()->groupBy('barber_id');

        return Inertia::render('admin/schedules', [
            'timezone' => config('barberflow.timezone'),
            'hours' => collect(range(0, 6))->map(function (int $day) use ($hours): array {
                $hour = $hours->get($day);

                return ['weekday' => $day, 'starts_at' => $hour?->starts_at === null ? '' : substr($hour->starts_at, 0, 5), 'ends_at' => $hour?->ends_at === null ? '' : substr($hour->ends_at, 0, 5), 'is_closed' => $hour->is_closed ?? true];
            })->all(),
            'barbers' => Barber::query()->where('is_active', true)
                ->whereHas('user', fn (Builder $query) => $query->where('is_active', true)->where('role', UserRole::Barber->value))
                ->orderBy('name')->get()->map(function (Barber $barber) use ($schedules): array {
                    $rows = ($schedules->get($barber->id) ?? collect())->keyBy('weekday');

                    return ['id' => $barber->id, 'name' => $barber->name, 'schedules' => collect(range(0, 6))->map(function (int $day) use ($rows): array {
                        $row = $rows->get($day);

                        return ['weekday' => $day, 'starts_at' => $row === null ? '09:00' : substr($row->starts_at, 0, 5), 'ends_at' => $row === null ? '17:00' : substr($row->ends_at, 0, 5), 'is_active' => $row->is_active ?? false];
                    })->all()];
                })->all(),
            'closures' => BusinessClosure::query()->orderByDesc('date')->paginate(10)->withQueryString()->through(fn (BusinessClosure $closure) => ['id' => $closure->id, 'date' => $closure->date->toDateString(), 'reason' => $closure->reason]),
        ]);
    }

    public function hours(BusinessHoursRequest $request, StaffAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            foreach ($request->validated('hours') as $hour) {
                BusinessHour::query()->updateOrCreate(['weekday' => $hour['weekday']], ['starts_at' => $hour['is_closed'] ? null : $hour['starts_at'], 'ends_at' => $hour['is_closed'] ? null : $hour['ends_at'], 'is_closed' => (bool) $hour['is_closed']]);
            }
            $audit->record($request->user(), 'business_hours.updated', 'business_hours', null, ['weekdays' => 7]);
        }, 5);

        return back()->with('success', 'Jam operasional disimpan.');
    }

    public function barber(BarberScheduleRequest $request, Barber $barber, StaffAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $barber, $audit): void {
            $locked = Barber::query()->whereKey($barber->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active || ! $locked->user->canAccessStaffArea() || ! $locked->user->isBarber()) {
                throw ValidationException::withMessages(['barber' => 'Aktifkan profil dan akun barber sebelum mengubah jadwal.']);
            }
            foreach ($request->validated('schedules') as $schedule) {
                BarberSchedule::query()->updateOrCreate(['barber_id' => $locked->id, 'weekday' => $schedule['weekday']], ['starts_at' => $schedule['starts_at'], 'ends_at' => $schedule['ends_at'], 'is_active' => (bool) $schedule['is_active']]);
            }
            $audit->record($request->user(), 'barber_schedule.updated', 'barber', $locked->id, ['weekdays' => 7]);
        }, 5);

        return back()->with('success', 'Jadwal barber disimpan.');
    }

    public function storeClosure(BusinessClosureRequest $request, StaffAudit $audit): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $audit): void {
                $closure = BusinessClosure::query()->create($request->validated());
                $audit->record($request->user(), 'business_closure.created', 'business_closure', $closure->id, ['date' => $closure->date->toDateString()]);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'Tanggal tersebut sudah memiliki pengaturan libur.']);
        }

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function destroyClosure(Request $request, BusinessClosure $closure, StaffAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $closure, $audit): void {
            $audit->record($request->user(), 'business_closure.removed', 'business_closure', $closure->id, ['date' => $closure->date->toDateString()]);
            $closure->delete();
        }, 5);

        return back()->with('success', 'Tanggal libur dihapus; audit perubahan tetap tersimpan.');
    }
}
