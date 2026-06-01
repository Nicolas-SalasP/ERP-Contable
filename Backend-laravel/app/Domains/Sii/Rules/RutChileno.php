<?php

namespace App\Domains\Sii\Rules;

use App\Domains\Sii\Support\RutHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RutChileno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("El :attribute debe ser un texto valido.");

            return;
        }

        if (RutHelper::validar($value) === false) {
            $fail("El :attribute no es un RUT chileno valido.");
        }
    }
}
