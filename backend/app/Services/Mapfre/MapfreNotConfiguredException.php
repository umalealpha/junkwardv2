<?php

namespace AlphaDirect\Services\Mapfre;

/**
 * A required MAPFRE credential is absent from this environment's config.
 *
 * WHY THIS EXISTS
 * ---------------
 * Both MAPFRE clients used to raise a plain \RuntimeException for two very
 * different situations:
 *
 *   1. assertConfigured() — a credential is genuinely missing. Operational:
 *      somebody has to put it in the environment.
 *   2. The auth chain reached MAPFRE and MAPFRE said no, or the network did —
 *      "Cognito authentication failed (HTTP 401)", "eMiA token response missing
 *      access_token", "Cognito authentication error" (timeout / DNS), and three
 *      more. Seven of the nine throws are this kind.
 *
 * PublicTravelController::call() caught \RuntimeException and reported ALL of
 * them to the portal as `travel_not_configured` / "Travel Insurance is not
 * configured on this environment yet." So expired credentials, a revoked
 * client secret, a MAPFRE outage and a connect timeout all presented as a
 * configuration gap — sending whoever investigated to check env vars that were
 * present and correct all along.
 *
 * Extending \RuntimeException keeps every existing catch site behaving exactly
 * as before; it only lets the ones that care tell the two apart.
 */
class MapfreNotConfiguredException extends \RuntimeException
{
    /** The config key that was missing, without the `services.mapfre.` prefix. */
    public function __construct(public readonly string $configKey)
    {
        parent::__construct("MAPFRE integration not configured: missing services.mapfre.{$configKey}");
    }
}
