<?php

namespace App\Rules;

use App\Services\SubdomainValidationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SubdomainAvailable implements ValidationRule
{
    public function __construct(
        protected ?string $ignoreType = null,
        protected ?int $ignoreId = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $result = SubdomainValidationService::checkAvailability((string) $value, $this->ignoreType, $this->ignoreId);
        if (! $result['available']) {
            $fail($result['message']);
        }
    }
}
