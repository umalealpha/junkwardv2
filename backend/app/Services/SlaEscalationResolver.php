<?php

namespace AlphaDirect\Services;

/**
 * Resolves who an SLA escalation goes to. v1 is a single level read from
 * config (Pramod + Lakshmi). The level-keyed shape is future-ready for
 * Level 1 → Team Lead, Level 2 → Manager, Level 3 → Director without changing
 * callers.
 *
 * @return array<int, array{name:string, email:string}>
 */
class SlaEscalationResolver
{
    /** Recipients for a given escalation level. */
    public function recipientsForLevel(int $level = 1): array
    {
        // Future: per-level config / DB table. For now every level resolves to
        // the configured escalation recipients.
        $recipients = (array) config('help_desk.sla.escalation_recipients', []);

        return array_values(array_filter($recipients, fn ($r) => !empty($r['email'] ?? null)));
    }
}
