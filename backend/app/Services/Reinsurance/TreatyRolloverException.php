<?php

namespace AlphaDirect\Services\Reinsurance;

/**
 * Domain-specific exception thrown by TreatyRolloverService. Carrying its
 * own class so controllers can turn it into a 422 without being confused
 * with a generic RuntimeException.
 */
class TreatyRolloverException extends \RuntimeException
{
}
