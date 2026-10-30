<?php

namespace App\Http\Requests\Admin;

use App\Services\Queue\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class BusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['bail', 'nullable', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    app(PhoneNormalizer::class)->normalize($value);
                } catch (ValidationException) {
                    $fail('Gunakan nomor WhatsApp Indonesia yang valid.');
                }
            }],
            'map_url' => ['nullable', 'string', 'max:2000', 'url:https'],
            'queue_registration_open' => ['required', 'boolean'],
            'daily_queue_capacity' => ['required', 'integer', 'between:1,32767'],
            'grace_minutes' => ['required', 'integer', 'between:1,120'],
            'timezone' => ['missing'],
        ];
    }
}
