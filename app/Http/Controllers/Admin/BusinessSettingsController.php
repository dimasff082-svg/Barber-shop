<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BusinessSettingsRequest;
use App\Models\BusinessSetting;
use App\Services\Queue\PhoneNormalizer;
use App\Services\StaffAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    public function edit(): Response
    {
        $defaults = ['business_name' => 'BARBERFLOW', 'address' => '', 'whatsapp' => '', 'map_url' => '', 'queue_registration_open' => false, 'daily_queue_capacity' => 50, 'grace_minutes' => 5];
        foreach (BusinessSetting::query()->whereIn('key', array_keys($defaults))->get() as $setting) {
            $defaults[$setting->key] = $setting->value;
        }

        return Inertia::render('admin/settings', ['settings' => $defaults, 'timezone' => config('barberflow.timezone')]);
    }

    public function update(BusinessSettingsRequest $request, StaffAudit $audit): RedirectResponse
    {
        $values = $request->validated();
        // Optional contacts use JSON empty strings, never SQL NULL in the required value column.
        $values['whatsapp'] = empty($values['whatsapp']) ? '' : app(PhoneNormalizer::class)->normalize($values['whatsapp']);
        $values['address'] = $values['address'] ?? '';
        $values['map_url'] = $values['map_url'] ?? '';
        $values['queue_registration_open'] = $request->boolean('queue_registration_open');
        $values['daily_queue_capacity'] = $request->integer('daily_queue_capacity');
        $values['grace_minutes'] = $request->integer('grace_minutes');
        DB::transaction(function () use ($request, $values, $audit): void {
            foreach ($values as $key => $value) {
                BusinessSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
            $audit->record($request->user(), 'business_settings.updated', 'business_settings', null, ['fields' => array_keys($values), 'queue_registration_open' => $values['queue_registration_open']]);
        }, 5);

        return back()->with('success', 'Pengaturan usaha disimpan. Tiket yang sudah terbit tetap berlaku sesuai aturan.');
    }
}
