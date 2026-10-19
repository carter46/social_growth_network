<?php

namespace App\Http\Requests;

use App\Support\UniqueUserRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
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
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
