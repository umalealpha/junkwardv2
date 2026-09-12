<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth gate on the legacy V1 admin panel mounted under
 * /admin/* on the V2 backend domain (graphite-v2-be.alphadirect.co.bw).
 *
 * Why this exists
 * ───────────────
 * The V2 backend codebase started as a fork of legacy graphiteBWV8 and
 * still serves the entire V1 admin panel (Blade views, controllers,
 * dashboards) under /admin/* on web.php. Prathap's UAT (26 May 2026,
 * §2.2 of the Consolidated UAT Report) flagged that any authenticated
 * V2 frontend user — including standard agents and brokers — could
 * paste the BE URL and land directly on /admin/dashboard with full
 * V1 Super-Admin privileges:
 *
 *   "the V2 backend domain auto-redirects to the V1 Super-Admin panel
 *    and shares the same authentication cookie as the V2 frontend …
 *    This is a CRITICAL data-protection and DPA-001 breach."
 *
 * PR #701 (26 May 2026) added Spatie role:Super Admin|Manager|Admin
 * middleware on three of the /admin/* route groups — that closed the
 * "any logged-in user can browse it" hole, but the V1 admin panel
 * itself remains mounted and renderable. The cutover requirement is:
 *
 *   "If the V1 admin panel is no longer required, disable it entirely
 *    before cutover."  — Prathap §2.2, Required Action
 *
 * What this middleware does
 * ─────────────────────────
 * Reads the env flag V1_ADMIN_PANEL_ENABLED. When the flag is anything
 * other than truthy ('1' / 'true' / 'on' / 'yes'), every request that
 * reaches this middleware is aborted with 404 — same response code as
 * a non-existent path, so the legacy panel is undiscoverable to anyone
 * scanning the BE domain.
 *
 * Default behaviour: DISABLED. The legacy panel is locked behind an
 * explicit env opt-in. Production V2 must never set this flag. Local
 * developers and staging ops who genuinely need the legacy admin
 * temporarily can flip it on with care.
 *
 * Defense in depth: this middleware runs BEFORE auth + role checks
 * already in place. Even if Spatie's role gate had a regression
 * tomorrow, the legacy panel would still 404 on V2's BE domain.
 *
 * UAT 2026-05-27 (closes the second half of Prathap §2.2).
 */
class BlockV1AdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->legacyAdminEnabled()) {
            abort(404);
        }
        return $next($request);
    }

    /**
     * Read V1_ADMIN_PANEL_ENABLED with strict truthy semantics. Any
     * value other than '1' / 'true' / 'on' / 'yes' (case-insensitive)
     * is treated as disabled — including unset, empty string, and
     * the literal strings 'false' / '0' / 'off' / 'no'.
     *
     * This is stricter than Laravel's env() default casting because we
     * are gating an admin panel — anything ambiguous defaults to
     * locked-down.
     */
    private function legacyAdminEnabled(): bool
    {
        $raw = env('V1_ADMIN_PANEL_ENABLED');
        if ($raw === null || $raw === '') return false;
        if (is_bool($raw)) return $raw;
        $lc = strtolower(trim((string) $raw));
        return in_array($lc, ['1', 'true', 'on', 'yes'], true);
    }
}
