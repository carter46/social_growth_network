<?php

namespace App\Http\Requests\Admin;

use App\Support\UniqueUserRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', UniqueUserRules::username()],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', UniqueUserRules::email()],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'email_verified' => ['sometimes', 'boolean'],
            'is_suspended' => ['sometimes', 'boolean'],
            'kyc_level' => ['nullable', 'integer', 'min:0', 'max:4'],
            'provision_wallet' => ['sometimes', 'boolean'],
        ];
    }
}
