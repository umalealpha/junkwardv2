<?php

namespace AlphaDirect\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Sanctions / prohibited-jurisdiction guard for the free-text Country and
 * Nationality KYC fields on the policy-create endpoints.
 *
 * Blocks Iran, Myanmar (Burma) and North Korea (DPRK) — mirrors the
 * start-fe-react client-side check (StartPolicy.tsx isSanctionedCountry) so the
 * control cannot be bypassed by calling the API directly. Matched leniently
 * (case-insensitive, punctuation-stripped) including common aliases, while
 * never catching South Korea / Republic of Korea.
 *
 * Nullable-friendly: empty values pass (the field itself may be optional); only
 * a value that resolves to a sanctioned jurisdiction fails.
 */
class NotSanctionedCountry implements Rule
{
    public function passes($attribute, $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $v = strtolower((string) $value);
        $v = preg_replace('/[^a-z ]+/', ' ', $v);
        $v = trim(preg_replace('/\s+/', ' ', $v));

        if ($v === '') {
            return true;
        }

        if (str_contains($v, 'iran')) {
            return false; // Iran / Islamic Republic of Iran / Iranian
        }
        if (str_contains($v, 'myanmar') || str_contains($v, 'burma')) {
            return false;
        }
        // North Korea only — must never catch South Korea / Republic of Korea.
        if (str_contains($v, 'dprk') || str_contains($v, 'north korea')) {
            return false;
        }
        if (str_contains($v, 'korea') && (str_contains($v, 'north') || str_contains($v, 'democratic people'))) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'The :attribute is a prohibited jurisdiction (Iran, Myanmar or North Korea) and cannot be onboarded.';
    }
}
