<?php

namespace AlphaDirect\Services\AlphaTransit;

use RuntimeException;

/**
 * A terminal rejection of an inbound Alpha Transit event — maps 1:1 onto the
 * rejection codes agreed in the Integration Reply §05 (invalid_product,
 * value_out_of_band, excluded_category, policy_not_found, malformed_payload).
 *
 * The ATC platform treats any 4xx (except 409) as terminal: the event lands
 * in its admin Integrations panel for manual review instead of retrying, so
 * these are only thrown for defects a retry can never fix.
 */
class AtcRejection extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode, // named to avoid Exception::$code
        string $message
    ) {
        parent::__construct($message);
    }
}
