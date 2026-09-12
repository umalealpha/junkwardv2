<?php

/**
 * Global helper functions used across Blade templates and controllers.
 * Registered via composer.json autoload.files.
 */

if (!function_exists('formatNumericValue')) {
    /**
     * Format a numeric value with commas and 2 decimal places: 1,234.56
     * Used in PAR/EAR/CAR engineering PDF templates.
     */
    function formatNumericValue($value): string
    {
        if ($value === null || $value === '') return '0.00';
        $num = is_numeric(str_replace(',', '', (string) $value))
            ? (float) str_replace(',', '', (string) $value)
            : 0;
        return number_format($num, 2, '.', ',');
    }
}

if (!function_exists('parseNumericValue')) {
    /**
     * Parse a formatted string back to float: "1,234.56" → 1234.56
     * Used in PDF templates for summing item values.
     */
    function parseNumericValue($value): float
    {
        if ($value === null || $value === '') return 0.0;
        return (float) str_replace(',', '', (string) $value);
    }
}

if (!function_exists('fmtDate')) {
    /**
     * Safe date formatter for Blade templates.
     *
     * Carbon::parse(null) returns year -1 ("30/11/-0001") which is what shows up
     * on the generated policy document whenever a date field is empty. This
     * helper returns the $fallback string instead.
     *
     * Accepts strings, Carbon instances, DateTime, and nulls.
     */
    function fmtDate($value, string $format = 'd/m/Y', string $fallback = '-'): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return $fallback;
        }
        try {
            $c = \Carbon\Carbon::parse($value);
            // Guard against epoch/null-ish parsed dates
            if ($c->year < 1900) return $fallback;
            return $c->format($format);
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('yes_no')) {
    /**
     * Render a boolean-ish flag as a user-friendly Yes / No.
     *
     * The is_renewable / is_project_specific columns are stored inconsistently
     * across records: some hold "Yes"/"No" (the dropdown forms), others hold
     * 1/0 (tinyint). Echoing them raw showed bare "1"/"0" to users. This maps
     * truthy → Yes, falsy → No, and leaves unset values blank so an
     * un-captured field isn't asserted as "No".
     *
     *   1, "1", true, "Yes", "y"  → "Yes"
     *   0, "0", false, "No", "n"  → "No"
     *   null, ""                  → "" (blank — not captured)
     *   anything else             → the original value, untouched
     */
    function yes_no($value, string $blank = ''): string
    {
        if ($value === null || $value === '') return $blank;
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        $s = strtolower(trim((string) $value));
        if (in_array($s, ['1', 'yes', 'true', 'y'], true))  return 'Yes';
        if (in_array($s, ['0', 'no', 'false', 'n'], true))  return 'No';
        return (string) $value;
    }
}
