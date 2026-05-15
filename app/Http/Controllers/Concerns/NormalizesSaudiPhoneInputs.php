<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SaudiPhone;
use Illuminate\Http\Request;

trait NormalizesSaudiPhoneInputs
{
    /**
     * Normalize selected request fields to canonical Saudi mobile format.
     */
    protected function normalizeSaudiPhoneInputs(Request $request, array $fields): void
    {
        $payload = [];

        foreach ($fields as $field) {
            if (! $request->exists($field)) {
                continue;
            }

            $normalized = SaudiPhone::normalizeMobile($request->input($field));
            if ($normalized !== null) {
                $payload[$field] = $normalized;
            }
        }

        if ($payload !== []) {
            $request->merge($payload);
        }
    }
}

