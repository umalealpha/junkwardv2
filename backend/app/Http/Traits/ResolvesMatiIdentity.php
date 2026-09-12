<?php

namespace AlphaDirect\Http\Traits;

use AlphaDirect\Config;
use Illuminate\Http\Request;

/**
 * Resolves the value to persist in `customer.mati_identity` for the public
 * V2 create flows, mirroring the legacy FrontendPay/CustomerController logic
 * (~:893-920):
 *
 *   - a verified MetaMap identityId was posted  → store the sanitized id
 *   - none posted AND MATI globally disabled     → store 0 (verification bypassed)
 *   - none posted AND MATI enabled               → null (not yet verified)
 *
 * The frontend sends the id as the legacy form field `mati-identityId`.
 */
trait ResolvesMatiIdentity
{
    protected function resolveMatiIdentity(Request $request): int|string|null
    {
        $raw = $request->input('mati-identityId');
        if ($raw !== null && $raw !== '' && $raw !== 'null') {
            return htmlspecialchars(strip_tags((string) $raw));
        }
        return $this->matiEnabled() ? null : 0;
    }

    protected function matiEnabled(): bool
    {
        try {
            $cfg = Config::where('key', 'enable_mati')->first(['value']);
            return $cfg && (int) $cfg->value === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
