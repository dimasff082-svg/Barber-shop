<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\GalleryImage;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Services\Queue\QueueRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PublicCatalog
{
    public function __construct(private QueueRules $rules) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        $now = $this->rules->businessNow();
        $services = Service::query()->where('is_active', true)->orderBy('id')->get();
        $barbers = Barber::query()->where('is_active', true)
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true)->where('role', UserRole::Barber->value))
            ->with('user')->orderBy('id')->get();
        $hours = BusinessHour::query()->where('weekday', $now->dayOfWeek)->first();
        $open = $this->rules->setting('queue_registration_open', false) === true
            && $hours !== null && ! $hours->is_closed && $hours->starts_at !== null && $hours->ends_at !== null
            && $now->format('H:i:s') >= $hours->starts_at && $now->format('H:i:s') < $hours->ends_at
            && ! BusinessClosure::query()->whereDate('date', $now->toDateString())->exists();
        $firstService = $services->first();
        $counts = QueueTicket::query()->whereDate('queue_date', $now->toDateString())
            ->whereIn('status', [QueueStatus::WaitingCheckin->value, QueueStatus::CheckedIn->value, QueueStatus::Called->value])
            ->selectRaw('barber_id, count(*) as waiting_count')->groupBy('barber_id')->pluck('waiting_count', 'barber_id');
        $schedules = BarberSchedule::query()->where('is_active', true)->orderBy('weekday')->get()->groupBy('barber_id');
        $publicBarbers = $barbers->map(function (Barber $barber) use ($firstService, $now, $counts, $schedules): array {
            $reason = 'Layanan belum tersedia.';
            if ($firstService !== null) {
                try {
                    // Availability shown in the form uses the same authoritative rules as creation.
                    $this->rules->assertCanRegister($barber, $firstService, $now);
                    $reason = null;
                } catch (ValidationException $exception) {
                    $reason = collect($exception->errors())->flatten()->first() ?? 'Pendaftaran belum tersedia.';
                }
            }

            return [
                'id' => $barber->id, 'name' => $barber->name, 'specialization' => $barber->specialization,
                'bio' => $barber->bio, 'photo' => $this->imageUrl($barber->photo),
                'availability' => $barber->availability_status->value,
                'waiting_count' => (int) $counts->get($barber->id, 0),
                'registration' => ['allowed' => $reason === null, 'reason' => $reason],
                'schedules' => ($schedules->get($barber->id) ?? collect())->map(fn (BarberSchedule $schedule) => [
                    'weekday' => $schedule->weekday, 'starts_at' => substr($schedule->starts_at, 0, 5), 'ends_at' => substr($schedule->ends_at, 0, 5),
                ])->values()->all(),
            ];
        })->all();
        $open = $open && $services->isNotEmpty() && collect($publicBarbers)->contains(fn ($barber) => $barber['registration']['allowed']);

        return [
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id, 'name' => $service->name, 'slug' => $service->slug,
                'description' => $service->description, 'price' => $service->price,
                'duration_minutes' => $service->estimated_duration_minutes, 'image' => $this->imageUrl($service->image_path),
            ])->all(),
            'barbers' => $publicBarbers,
            'business' => $this->business(),
            'registration' => [
                'open' => $open,
                'message' => $open ? 'Pendaftaran antrean hari ini dibuka.' : 'Pendaftaran antrean belum tersedia. Silakan periksa jam buka dan ketersediaan barber.',
                'date' => $now->toDateString(), 'timezone' => (string) config('barberflow.timezone'), 'grace_minutes' => $this->rules->graceMinutes(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function gallery(): array
    {
        $images = GalleryImage::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (GalleryImage $image) => ['title' => $image->title, 'src' => $this->imageUrl($image->image_path), 'alt' => $image->alt])
            ->filter(fn ($image) => $image['src'] !== null)->values()->all();
        if ($images !== []) {
            return ['images' => $images, 'illustrative' => false];
        }

        return ['images' => [
            ['title' => 'Fade', 'src' => '/images/gallery-fade.webp', 'alt' => 'Inspirasi potongan fade dengan gradasi sisi rambut.'],
            ['title' => 'Classic', 'src' => '/images/gallery-classic.webp', 'alt' => 'Inspirasi potongan klasik dengan rambut tersisir rapi.'],
            ['title' => 'Textured', 'src' => '/images/gallery-textured.webp', 'alt' => 'Inspirasi rambut pendek dengan tekstur natural.'],
        ], 'illustrative' => true];
    }

    public function imageUrl(?string $path): ?string
    {
        if ($path === null || str_contains($path, '..')) {
            return null;
        }
        $path = ltrim($path, '/');
        if (! preg_match('~\A(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:webp|avif|png|jpe?g)\z~i', $path)) {
            return null;
        }

        return '/'.(str_starts_with($path, 'images/') || str_starts_with($path, 'storage/') ? $path : 'storage/'.$path);
    }

    /** @return array<string, mixed> */
    private function business(): array
    {
        $settings = BusinessSetting::query()->whereIn('key', ['business_name', 'address', 'whatsapp', 'map_url'])->get()->keyBy('key');
        $text = function (string $key) use ($settings): ?string {
            $value = $settings->get($key)?->value;

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };
        $phone = $text('whatsapp');
        $phone = $phone === null ? null : preg_replace('/[\s()+.-]/', '', $phone);
        if ($phone !== null && str_starts_with($phone, '08')) {
            $phone = '62'.substr($phone, 1);
        }
        $map = $text('map_url');

        return [
            'name' => $text('business_name') ?? 'BARBERFLOW', 'address' => $text('address'),
            'whatsapp_url' => $phone !== null && preg_match('/\A628[0-9]{7,11}\z/', $phone) ? 'https://wa.me/'.$phone : null,
            'map_url' => $map !== null && filter_var($map, FILTER_VALIDATE_URL) && str_starts_with($map, 'https://') ? $map : null,
            'hours' => BusinessHour::query()->orderBy('weekday')->get()->map(fn (BusinessHour $hour) => [
                'weekday' => $hour->weekday, 'starts_at' => $hour->starts_at === null ? null : substr($hour->starts_at, 0, 5),
                'ends_at' => $hour->ends_at === null ? null : substr($hour->ends_at, 0, 5), 'is_closed' => $hour->is_closed,
            ])->all(),
        ];
    }
}
