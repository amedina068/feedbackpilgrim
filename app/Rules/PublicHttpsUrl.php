<?php

namespace App\Rules;

use App\Support\WebhookUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(WebhookUrlGuard::class)->publicAddressFor($value) === null) {
            $fail('The :attribute must be an https URL on a public address.');
        }
    }
}
