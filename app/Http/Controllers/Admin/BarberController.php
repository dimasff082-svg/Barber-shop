<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BarberRequest;
use App\Models\Barber;
use App\Services\Admin\CatalogImages;
use App\Services\Admin\StaffDirectory;
use App\Services\PublicCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BarberController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        Gate::authorize('viewAny', Barber::class);
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($request->string('search')->toString());
        $barbers = Barber::query()->with('user')->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('id')->paginate(15)->withQueryString()->through(fn (Barber $barber) => [
                'id' => $barber->id, 'name' => $barber->name, 'email' => $barber->user->email,
                'queue_prefix' => $barber->queue_prefix, 'specialization' => $barber->specialization,
                'bio' => $barber->bio, 'is_active' => $barber->is_active && $barber->user->is_active,
                'accepts_queue' => $barber->accepts_queue, 'daily_capacity' => $barber->daily_capacity,
                'availability' => $barber->availability_status->value, 'image_url' => $catalog->imageUrl($barber->photo),
                'queue_locked' => $barber->tickets()->exists() || $barber->counters()->exists(),
                'update_url' => route('admin.barbers.update', $barber), 'archive_url' => route('admin.barbers.archive', $barber),
            ]);

        return Inertia::render('admin/barbers', ['barbers' => $barbers, 'filters' => ['search' => $search], 'store_url' => route('admin.barbers.store')]);
    }

    public function store(BarberRequest $request, CatalogImages $images, StaffDirectory $directory): RedirectResponse
    {
        Gate::authorize('create', Barber::class);
        $images->save($request, 'barbers', fn ($path) => $directory->save($request->user(), $request->validated(), null, $path));

        return to_route('admin.barbers.index')->with('success', 'Barber dan akun staff berhasil dibuat. Atur jadwal serta status kerja sebelum menerima antrean.');
    }

    public function update(BarberRequest $request, Barber $barber, CatalogImages $images, StaffDirectory $directory): RedirectResponse
    {
        Gate::authorize('update', $barber);
        $images->save($request, 'barbers', fn ($path) => $directory->save($request->user(), $request->validated(), $barber, $path));

        return to_route('admin.barbers.index')->with('success', 'Profil dan akun barber diperbarui. Tiket yang sudah diterbitkan tetap tersimpan.');
    }

    public function archive(Request $request, Barber $barber, StaffDirectory $directory): RedirectResponse
    {
        Gate::authorize('update', $barber);
        DB::transaction(fn () => $directory->archive($request->user(), $barber), 5);

        return to_route('admin.barbers.index')->with('success', 'Barber dan akun dinonaktifkan. Riwayat serta tiket yang belum diproses tetap tersimpan.');
    }
}
