<?php

namespace App\Http\Requests\Staff;

use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'status' => ['nullable', Rule::enum(QueueStatus::class)],
            'source' => ['nullable', Rule::enum(QueueSource::class)],
        ];
    }
}
