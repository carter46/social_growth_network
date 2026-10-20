<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use App\Support\UniqueUserRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $countries = array_keys((array) config('payout_methods.countries', []));

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $user = $this->user();
                    if ($user && trim((string) $value) !== trim((string) $user->name) && $user->nameLockedByPayoutAccount()) {
                        $fail(__('Your name is locked because payout accounts must be in your name. Contact support to change it.'));
                    }
                },
            ],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                UniqueUserRules::username($this->user()->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                UniqueUserRules::email($this->user()->id),
            ],
            'phone' => PhoneNumber::e164Rules(false),
            'country' => ['nullable', 'string', 'size:2', Rule::in($countries !== [] ? $countries : ['US'])],
            'address_line1' => ['nullable', 'string', 'max:160'],
            'address_city' => ['nullable', 'string', 'max:80'],
            'address_state' => ['nullable', 'string', 'max:80'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
