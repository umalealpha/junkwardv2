<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class XssSanitization
{
    /**
     * Free-text fields that legitimately contain angle brackets / ampersands —
     * motor excess clauses ("Additional Excess (Underage Driver < 25 years)"),
     * notes, memoranda and warranty text. strip_tags() silently deletes
     * everything from a "<" onward, so it was eating these inputs. They are
     * always output-escaped (Blade {{ }} on the PDFs, React text nodes on the
     * UI), so storing the raw text is safe from stored-XSS. Keys are matched
     * case-insensitively at any nesting depth.
     *
     * @var string[]
     */
    private const SKIP_KEYS = [
        'note', 'notes',
        'memoranda_warranty', 'cash_warranty',
        'burglar_warranty', 'burglar_alarm_warranty',
        'endorsements', 'benefits_note', 'stated_benefits',
        'property_business_being',
        // Motor (DOM/COM) excess clauses arrive as excesses.*.excesses — the
        // inner free-text key is `excesses` ("Additional Excess (Underage
        // Driver < 25 years)"). Without this the "< 25 years" text was being
        // truncated at the "<". `extention_text_value` is the extension's
        // free-text value (limit/clause wording) and carries angle brackets too.
        'excesses', 'extention_text_value',
        // Credentials are hashed, never rendered, so strip_tags() buys nothing
        // here and actively corrupts them: POST /v1/auth/login runs WITHOUT
        // this middleware, but every admin set-password route (users.store,
        // users.update, users.resetPassword) runs WITH it. So a password like
        // "Pass<word123" was hashed as "Pass" on the way in and compared raw on
        // the way back — the staff member could never log in with the password
        // they were given, and the truncation was silent because the
        // confirmation field got stripped identically.
        'password', 'password_confirmation', 'current_password',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $input = $request->all();

        $this->sanitize($input);

        $request->merge($input);

        return $next($request);

    }

    /**
     * Recursively strip_tags() every string value EXCEPT whitelisted free-text
     * keys, which are left verbatim. A key-aware walk is required because
     * array_walk_recursive only exposes values, not their keys.
     */
    private function sanitize(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->sanitize($value);
            } elseif (is_string($value)
                && !in_array(strtolower((string) $key), self::SKIP_KEYS, true)) {
                $value = strip_tags($value);
            }
        }
        unset($value);
    }
}
