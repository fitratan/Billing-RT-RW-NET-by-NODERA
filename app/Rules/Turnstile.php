<?php

namespace App\Rules;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Turnstile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = app(TurnstileService::class);

        if (!$service->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Verifikasi keamanan CAPTCHA gagal atau telah kedaluwarsa. Silakan muat ulang dan coba lagi.');
        }
    }
}
