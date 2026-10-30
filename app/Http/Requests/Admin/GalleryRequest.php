<?php

namespace App\Http\Requests\Admin;

use App\Models\GalleryImage;
use Illuminate\Foundation\Http\FormRequest;

class GalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'alt' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'image' => [$this->route('gallery') instanceof GalleryImage ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'image_path' => ['prohibited'],
        ];
    }
}
