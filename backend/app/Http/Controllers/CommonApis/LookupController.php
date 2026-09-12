<?php

namespace AlphaDirect\Http\Controllers\CommonApis;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Lookup endpoint so external clients (BizSure first) can populate
 * dropdowns from Graphite's lookup_data table instead of hardcoding
 * static lists that drift out of sync.
 *
 *   GET  /api/lookup/{key}   — read (mobile/agent/LiveQuote, unauthenticated today)
 *   POST /api/lookup/{key}   — write-back (api-key + throttled, allow-list of keys)
 *
 * Ported from graphiteBWV8 CommonApis/LookupController.
 */
class LookupController extends Controller
{
    /**
     * Lookup keys that accept write-back. Anything else gets 403.
     * Mirror of the BizSure-side WRITE_BACK_ENABLED_KEYS list.
     */
    private const WRITABLE_KEYS = [
        'risk_occupation',
    ];

    /**
     * GET /api/lookup/{key}
     *
     * Returns lookup_data rows for the given key as
     * [{ value: "...", label: "..." }, ...]. 404 if the key has no
     * rows so callers can distinguish "unseeded" from "empty response".
     *
     * label falls back to value for legacy rows where the label column
     * is null — keeps the wire format unchanged for existing keys.
     */
    public function getByKey(string $key)
    {
        $rows = Lookup::where('key', $key)->get(['value', 'label']);

        if ($rows->isEmpty()) {
            return response()->json([
                'error' => 'Unknown lookup key',
                'key'   => $key,
            ], 404);
        }

        $response = $rows->map(function ($row) {
            return [
                'value' => $row->value,
                'label' => $row->label !== null && $row->label !== '' ? $row->label : $row->value,
            ];
        })->values();

        return response()->json($response, 200);
    }

    /**
     * POST /api/lookup/{key}
     *
     * Append a customer-submitted option to a write-enabled lookup key.
     * Dedupes by case-insensitive label (trimmed) — duplicates return the
     * existing row's value with duplicate: true so BizSure can rewrite its
     * local store to the canonical slug.
     *
     * No UW gating: new entries appear immediately. UW edits / merges via
     * the existing Graphite admin UI on lookup_data.
     */
    public function storeByKey(Request $request, string $key)
    {
        if (! in_array($key, self::WRITABLE_KEYS, true)) {
            return response()->json([
                'success' => false,
                'error'   => "Lookup key '{$key}' is not writable.",
            ], 403);
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '' || mb_strlen($label) < 2 || mb_strlen($label) > 80) {
            return response()->json([
                'success' => false,
                'error'   => 'Label must be 2–80 chars.',
            ], 400);
        }

        $existing = Lookup::where('key', $key)
            ->where(function ($q) use ($label) {
                $q->whereRaw('LOWER(TRIM(label)) = ?', [mb_strtolower($label)])
                  ->orWhereRaw('LOWER(TRIM(value)) = ?', [mb_strtolower($label)]);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'success'   => true,
                'value'     => $existing->value,
                'label'     => $existing->label !== null && $existing->label !== ''
                    ? $existing->label
                    : $existing->value,
                'duplicate' => true,
            ], 200);
        }

        $clientValue = trim((string) $request->input('value', ''));
        $value = $clientValue !== '' ? $this->sanitiseSlug($clientValue) : $this->sanitiseSlug($label);

        if ($value === '') {
            return response()->json([
                'success' => false,
                'error'   => 'Label must contain alphanumeric characters.',
            ], 400);
        }

        $value = $this->ensureUniqueValue($key, $value);

        $row = new Lookup();
        $row->key          = $key;
        $row->value        = $value;
        $row->label        = $label;
        $row->submitted_at = Carbon::now();
        $row->submitted_by = $this->trimOrNull($request->input('submittedBy'), 160);
        $row->source       = 'bizsure';
        $row->save();

        return response()->json([
            'success' => true,
            'value'   => $row->value,
            'label'   => $row->label,
        ], 200);
    }

    /**
     * Lowercase, replace non-alphanumeric runs with underscore, trim
     * leading/trailing underscores, cap at 50 chars. Matches the
     * BizSure-side slug rule so the canonical value usually round-trips
     * unchanged.
     */
    private function sanitiseSlug(string $input): string
    {
        $slug = mb_strtolower($input);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim((string) $slug, '_');
        return mb_substr($slug, 0, 50);
    }

    /**
     * If $value already exists for $key (different label — label dedupe
     * didn't catch it), append _2, _3, ... until we land on an unused
     * slug. Rare path; dedupe-by-label handles the common case.
     */
    private function ensureUniqueValue(string $key, string $value): string
    {
        if (! Lookup::where('key', $key)->where('value', $value)->exists()) {
            return $value;
        }

        $base = mb_substr($value, 0, 46);
        for ($i = 2; $i <= 99; $i++) {
            $candidate = "{$base}_{$i}";
            if (! Lookup::where('key', $key)->where('value', $candidate)->exists()) {
                return $candidate;
            }
        }
        return mb_substr($value, 0, 40) . '_' . Str::random(6);
    }

    private function trimOrNull($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }
        return mb_substr($trimmed, 0, $max);
    }
}
