<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class QrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:100'], 'is_active' => ['required', 'boolean'], 'code' => ['missing'], 'target_path' => ['missing']];
    }
}
