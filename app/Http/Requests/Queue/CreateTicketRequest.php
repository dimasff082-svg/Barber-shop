<?php

namespace App\Http\Requests\Queue;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('idempotency_key') && $this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'barber_id' => ['required', 'integer', 'exists:barbers,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'idempotency_key' => ['required', 'uuid'],
            'consent' => ['required', 'accepted'],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
            'queue_number' => ['prohibited'],
            'sequence_number' => ['prohibited'],
            'queue_date' => ['prohibited'],
            'checked_in_at' => ['prohibited'],
            'price_snapshot' => ['prohibited'],
            'duration_snapshot' => ['prohibited'],
            'token_hash' => ['prohibited'],
            'owner_hash' => ['prohibited'],
        ];
    }
}
