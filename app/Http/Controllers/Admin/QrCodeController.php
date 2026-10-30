<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QrCodeRequest;
use App\Models\QrCode;
use App\Services\StaffAudit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/qr/index', ['codes' => QrCode::query()->latest('id')->paginate(10)->withQueryString()->through(fn (QrCode $qr) => [
            'id' => $qr->id, 'label' => $qr->label, 'is_active' => $qr->is_active,
            'portal_url' => route('queue.location', $qr->code), 'image_url' => route('admin.qr.image', $qr->id),
            'print_url' => route('admin.qr.print', $qr->id), 'download_url' => route('admin.qr.download', $qr->id),
        ])]);
    }

    public function store(QrCodeRequest $request, StaffAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $qr = QrCode::query()->create([...$request->validated(), 'code' => bin2hex(random_bytes(32)), 'is_active' => $request->boolean('is_active')]);
            $audit->record($request->user(), 'qr.created', 'qr_code', $qr->id, ['is_active' => $qr->is_active]);
        }, 5);

        return back()->with('success', 'QR lokasi dibuat.');
    }

    public function update(QrCodeRequest $request, QrCode $qr, StaffAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $qr, $audit): void {
            $qr = QrCode::query()->whereKey($qr->id)->lockForUpdate()->firstOrFail();
            $qr->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
            $audit->record($request->user(), 'qr.updated', 'qr_code', $qr->id, ['is_active' => $qr->is_active]);
        }, 5);

        return back()->with('success', 'QR lokasi diperbarui.');
    }

    public function print(QrCode $qr): View
    {
        return view('admin.qr-print', ['label' => $qr->label, 'isActive' => $qr->is_active, 'portalUrl' => route('queue.location', $qr->code), 'imageUrl' => route('admin.qr.image', $qr->id)]);
    }

    public function image(QrCode $qr): HttpResponse
    {
        return response($this->svg($qr), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(QrCode $qr): HttpResponse
    {
        return response($this->svg($qr), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="barberflow-qr-'.$qr->id.'.svg"', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function svg(QrCode $qr): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(400, 32), new SvgImageBackEnd));

        return $writer->writeString(route('queue.location', $qr->code));
    }
}
