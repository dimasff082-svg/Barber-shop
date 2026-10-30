<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryRequest;
use App\Models\GalleryImage;
use App\Services\Admin\CatalogImages;
use App\Services\PublicCatalog;
use App\Services\StaffAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        $this->authorizeAdmin($request);
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($request->string('search')->toString());
        $images = GalleryImage::query()->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')->orderBy('id')->paginate(15)->withQueryString()->through(fn (GalleryImage $image) => [
                'id' => $image->id, 'title' => $image->title, 'alt' => $image->alt, 'category' => $image->category,
                'sort_order' => $image->sort_order, 'is_active' => $image->is_active, 'image_url' => $catalog->imageUrl($image->image_path),
                'update_url' => route('admin.gallery.update', $image), 'archive_url' => route('admin.gallery.archive', $image),
            ]);

        return Inertia::render('admin/gallery', ['images' => $images, 'filters' => ['search' => $search], 'store_url' => route('admin.gallery.store')]);
    }

    public function store(GalleryRequest $request, CatalogImages $images): RedirectResponse
    {
        $images->save($request, 'gallery', function (?string $path) use ($request): void {
            $data = $request->safe()->only(['title', 'alt', 'category', 'sort_order', 'is_active']);
            $image = GalleryImage::query()->create([...$data, 'image_path' => $path]);
            app(StaffAudit::class)->record($request->user(), 'gallery.created', 'gallery', $image->id, ['fields' => array_keys($data)]);
        });

        return to_route('admin.gallery.index')->with('success', 'Foto galeri berhasil ditambahkan.');
    }

    public function update(GalleryRequest $request, GalleryImage $gallery, CatalogImages $images): RedirectResponse
    {
        $images->save($request, 'gallery', function (?string $path) use ($request, $gallery): void {
            $locked = GalleryImage::query()->whereKey($gallery->id)->lockForUpdate()->firstOrFail();
            $data = $request->safe()->only(['title', 'alt', 'category', 'sort_order', 'is_active']);
            $locked->fill($data);
            if ($path !== null) {
                $locked->image_path = $path;
            }
            $locked->save();
            app(StaffAudit::class)->record($request->user(), 'gallery.updated', 'gallery', $locked->id, ['fields' => array_keys($data)]);
        });

        return to_route('admin.gallery.index')->with('success', 'Foto galeri diperbarui.');
    }

    public function archive(Request $request, GalleryImage $gallery): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::transaction(function () use ($request, $gallery): void {
            $locked = GalleryImage::query()->whereKey($gallery->id)->lockForUpdate()->firstOrFail();
            $locked->update(['is_active' => false]);
            app(StaffAudit::class)->record($request->user(), 'gallery.archived', 'gallery', $locked->id);
        }, 5);

        return to_route('admin.gallery.index')->with('success', 'Foto disembunyikan dari galeri publik. File tetap disimpan.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->canAccessStaffArea() && $request->user()->isAdmin(), 403);
    }
}
