<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1', 'max:32767'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'slug' => ['prohibited'], 'image_path' => ['prohibited'],
        ];
    }
}
