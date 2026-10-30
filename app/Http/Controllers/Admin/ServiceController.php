<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Services\Admin\CatalogImages;
use App\Services\PublicCatalog;
use App\Services\StaffAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        abort_unless($request->user()?->canAccessStaffArea() && $request->user()->isAdmin(), 403);
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($request->string('search')->toString());
        $services = Service::query()->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('id')->paginate(15)->withQueryString()->through(fn (Service $service) => [
                'id' => $service->id, 'name' => $service->name, 'description' => $service->description,
                'price' => $service->price, 'estimated_duration_minutes' => $service->estimated_duration_minutes,
                'is_active' => $service->is_active, 'image_url' => $catalog->imageUrl($service->image_path),
                'update_url' => route('admin.services.update', $service), 'archive_url' => route('admin.services.archive', $service),
            ]);

        return Inertia::render('admin/services', ['services' => $services, 'filters' => ['search' => $search], 'store_url' => route('admin.services.store')]);
    }

    public function store(ServiceRequest $request, CatalogImages $images): RedirectResponse
    {
        Gate::authorize('create', Service::class);
        $images->save($request, 'services', function (?string $path) use ($request): void {
            $data = $request->safe()->only(['name', 'description', 'price', 'estimated_duration_minutes', 'is_active']);
            $service = Service::query()->create([...$data, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8)), 'image_path' => $path]);
            app(StaffAudit::class)->record($request->user(), 'service.created', 'service', $service->id, ['fields' => array_keys($data)]);
        });

        return to_route('admin.services.index')->with('success', 'Layanan berhasil ditambahkan.');
    }

    public function update(ServiceRequest $request, Service $service, CatalogImages $images): RedirectResponse
    {
        Gate::authorize('update', $service);
        $images->save($request, 'services', function (?string $path) use ($request, $service): void {
            $locked = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $data = $request->safe()->only(['name', 'description', 'price', 'estimated_duration_minutes', 'is_active']);
            $locked->fill($data);
            if ($path !== null || $request->boolean('remove_image')) {
                $locked->image_path = $path;
            }
            $locked->save();
            app(StaffAudit::class)->record($request->user(), 'service.updated', 'service', $locked->id, ['fields' => array_keys($data)]);
        });

        return to_route('admin.services.index')->with('success', 'Layanan diperbarui. Harga dan durasi tiket lama tetap menggunakan snapshot saat diterbitkan.');
    }

    public function archive(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('update', $service);
        DB::transaction(function () use ($request, $service): void {
            $locked = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $locked->update(['is_active' => false]);
            app(StaffAudit::class)->record($request->user(), 'service.archived', 'service', $locked->id);
        }, 5);

        return to_route('admin.services.index')->with('success', 'Layanan dinonaktifkan tanpa menghapus riwayat.');
    }
}
