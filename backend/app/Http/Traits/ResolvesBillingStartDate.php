<?php

namespace AlphaDirect\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Resolves the customer-selected billing start date for instant policy
 * creation (Third Party Car, Legal, Hospital Cashback, Mobile/Electronic).
 *
 * The front-end sends `billingDate` (YYYY-MM-DD), constrained in the UI to a
 * future date no more than 45 days ahead. We re-validate the same bounds
 * server-side (never trust the client) and fall back to the historical
 * default of +1 month when the value is absent or out of range — so existing
 * callers that don't send a date keep their previous behaviour.
 */
trait ResolvesBillingStartDate
{
    /**
     * @return string Y-m-d billing start date.
     */
    protected function resolveBillingStartDate(Request $request): string
    {
        return $this->resolveBillingStartDateOrNull($request)
            ?? Carbon::now()->addMonth()->format('Y-m-d');
    }

    /**
     * Same bounds check, but returns null when the client sends no billing
     * date or one outside the future/≤45-day window. Used by callers (e.g.
     * the bundle staging path) that historically set no billingStartDate at
     * create and must stay additive — only writing one when explicitly chosen.
     *
     * @return string|null Y-m-d billing start date, or null.
     */
    protected function resolveBillingStartDateOrNull(Request $request): ?string
    {
        $raw = $request->input('billingDate');
        if (!$raw) {
            return null;
        }

        try {
            $date = Carbon::parse($raw)->startOfDay();
            $min  = Carbon::tomorrow();
            $max  = Carbon::today()->addDays(45);

            return ($date->gte($min) && $date->lte($max))
                ? $date->format('Y-m-d')
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
