<?php

namespace App\Http\Requests\Admin;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class BarberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessStaffArea() === true && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'queue_prefix'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => $field === 'email' ? mb_strtolower(trim($value)) : ($field === 'queue_prefix' ? strtoupper(trim($value)) : trim($value))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $barber = $this->route('barber');
        $updating = $barber instanceof Barber;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($updating ? $barber->user_id : null)],
            'password' => [$updating ? 'nullable' : 'required', 'string', Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'queue_prefix' => ['required', 'string', 'regex:/\A[A-Z]{1,3}\z/', Rule::unique('barbers')->ignore($updating ? $barber->id : null)],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'accepts_queue' => ['required', 'boolean'],
            'daily_capacity' => ['nullable', 'integer', 'min:1', 'max:32767'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'role' => ['prohibited'], 'user_id' => ['prohibited'], 'photo' => ['prohibited'],
            'slug' => ['prohibited'], 'availability_status' => ['prohibited'], 'remember_token' => ['prohibited'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $email = $this->input('email');
            $barber = $this->route('barber');
            $query = User::query()->whereRaw('lower(email) = ?', [is_string($email) ? $email : '']);
            if ($barber instanceof Barber) {
                $query->whereKeyNot($barber->user_id);
            }
            if (is_string($email) && $query->exists()) {
                $validator->errors()->add('email', 'Email tersebut sudah digunakan oleh akun lain.');
            }
        }];
    }
}
