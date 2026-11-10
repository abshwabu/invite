<?php

namespace App\Rules;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnstileRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! TurnstileService::isConfigured()) {
            return;
        }

        if (! TurnstileService::verify($value, request()->ip())) {
            $fail(__('Security verification (CAPTCHA) failed. Please try again.'));
        }
    }
}
