<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*' => ['required', 'array:weekday,starts_at,ends_at,is_closed'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.starts_at' => ['nullable', 'date_format:H:i'],
            'hours.*.ends_at' => ['nullable', 'date_format:H:i'],
            'hours.*.is_closed' => ['required', 'boolean'],
        ];
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->array('hours') as $index => $hour) {
                if (! $hour['is_closed'] && (empty($hour['starts_at']) || empty($hour['ends_at']) || $hour['starts_at'] >= $hour['ends_at'])) {
                    $validator->errors()->add('hours.'.$index.'.ends_at', 'Jam buka wajib memiliki jam selesai setelah jam mulai pada hari yang sama.');
                }
            }
        }];
    }
}
