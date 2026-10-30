<?php

namespace App\Http\Requests\Admin;

use App\Models\BusinessClosure;
use Illuminate\Foundation\Http\FormRequest;

class BusinessClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['date' => ['bail', 'required', 'date_format:Y-m-d', function (string $attribute, mixed $value, \Closure $fail): void {
            if (BusinessClosure::query()->whereDate('date', $value)->exists()) {
                $fail('Tanggal tersebut sudah memiliki pengaturan libur.');
            }
        }], 'reason' => ['required', 'string', 'max:255']];
    }
}
