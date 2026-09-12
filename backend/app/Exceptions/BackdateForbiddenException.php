<?php

namespace AlphaDirect\Exceptions;

use RuntimeException;

/**
 * Thrown by BackdateGovernanceService::enforce() when a claim date-edit is a
 * backdate that the governance rules disallow (before FY start, beyond the
 * cap, or no active grant). Caught by the controller and mapped to a 403.
 *
 * This is ONLY ever thrown when the `claims_backdate_governance` flag is on —
 * with the flag off, enforce() short-circuits to a no-op and never throws, so
 * the existing claim-edit path is unaffected.
 */
class BackdateForbiddenException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'BACKDATE_NOT_ALLOWED',
    ) {
        parent::__construct($message);
    }
}
