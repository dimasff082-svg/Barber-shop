<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BarberScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'size:7'],
            'schedules.*' => ['required', 'array:weekday,starts_at,ends_at,is_active'],
            'schedules.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i'],
            'schedules.*.is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->array('schedules') as $index => $schedule) {
                if ($schedule['starts_at'] >= $schedule['ends_at']) {
                    $validator->errors()->add('schedules.'.$index.'.ends_at', 'Jam selesai harus setelah jam mulai pada hari yang sama.');
                }
            }
        }];
    }
}
