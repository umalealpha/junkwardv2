<?php

namespace AlphaDirect\Support;

/**
 * Canonical tokens for `motor.type_of_cover` / `motor.type_of_cover_main`.
 *
 * Every consumer of this column matches it against exact strings — the
 * quote sheet (v2-quote-sheet.blade.php "Summary Of Vehicles"), the policy
 * schedule, the coverage export and the legacy Livewire coverage screen all
 * compare against 'Comprehensive' / 'third_party_only' / 'Third_fire_and_theft'.
 *
 * The V2 wizard's vehicle grid used to POST the human label instead
 * ("Third party only", "Third party, fire and theft"), so those rows:
 *   - printed a BLANK "Type of Cover" cell on the quotation (no ladder branch
 *     matched), and
 *   - fell through every `type_of_cover != "third_party_only"` gate, i.e. a
 *     third-party vehicle was rendered with the comprehensive sub-coverage
 *     blocks.
 *
 * normalize() folds any label/alias onto the canonical token and is applied
 * on the write path. An unrecognised value passes through untouched so we
 * never silently rewrite data we don't understand.
 */
class MotorCoverType
{
    public const COMPREHENSIVE           = 'Comprehensive';
    public const THIRD_PARTY_ONLY        = 'third_party_only';
    public const THIRD_PARTY_FIRE_THEFT  = 'Third_fire_and_theft';
    public const THIRD_PARTY_FIRE        = 'Third_fire';

    /**
     * Alias key => canonical token. Keys are the value reduced to lowercase
     * alphanumerics, so the canonical tokens, the UI labels and the export
     * labels ("Third party fire and theft", no comma) all land on the same key.
     */
    private const ALIASES = [
        'comprehensive'          => self::COMPREHENSIVE,
        'thirdpartyonly'         => self::THIRD_PARTY_ONLY,
        'tponly'                 => self::THIRD_PARTY_ONLY,
        'thirdpartyfireandtheft' => self::THIRD_PARTY_FIRE_THEFT,
        'thirdfireandtheft'      => self::THIRD_PARTY_FIRE_THEFT,
        'thirdpartyfiretheft'    => self::THIRD_PARTY_FIRE_THEFT,
        'thirdpartyandfire'      => self::THIRD_PARTY_FIRE,
        'thirdfire'              => self::THIRD_PARTY_FIRE,
    ];

    /** Canonical token => display label (matches the blade ladder wording). */
    private const LABELS = [
        self::COMPREHENSIVE          => 'Comprehensive',
        self::THIRD_PARTY_ONLY       => 'Third party only',
        self::THIRD_PARTY_FIRE_THEFT => 'Third party, fire and theft',
        self::THIRD_PARTY_FIRE       => 'Third party and fire',
    ];

    /**
     * Fold a stored/posted value onto its canonical token.
     * Returns the input unchanged when it isn't a value we recognise.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $value));

        return self::ALIASES[$key] ?? $value;
    }

    /** True when the value is already stored in canonical form. */
    public static function isCanonical(?string $value): bool
    {
        return $value !== null && $value !== '' && isset(self::LABELS[$value]);
    }

    /** Human label for a stored value ('' when empty/unknown). */
    public static function label(?string $value): string
    {
        $canonical = self::normalize($value);

        return self::LABELS[$canonical] ?? (string) $canonical;
    }
}
