<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A RealPay debit-order mandate — the authority to collect against a policy.
 *
 * Lifecycle (docs/REALPAY_MANDATE_IMPLEMENTATION.md §2):
 *
 *   pending ─► registered ─► redirected ─► authenticated ─► active
 *      │           │             │              │
 *      │           │             │              └─► cancelled
 *      │           │             └─► abandoned
 *      │           └─► rejected
 *      └─► failed
 *
 * `redirected` and `authenticated` are DebiCheck/Express waypoints and stay
 * unused until RealPay supplies the hosted-eMandate pack (spec §5). The RealPay
 * flow this codebase runs today has no customer-authentication event, so it goes
 * registered ─► active directly: the contract is registered with RealPay, and
 * the first instalment coming back 'S' is the proof it was a live authority.
 * Both paths are permitted below; `pending ─► active` is not, because a mandate
 * with no registered contract cannot have collected anything.
 *
 * Transitions live here rather than as string literals across a service, because
 * scattered literals are how a mandate ends up back in `pending` after it went
 * `active`. Illegal transitions throw.
 */
class RealpayMandate extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $auditTimestamps = true;

    protected $table = 'realpay_mandates';
    protected $guarded = ['id'];

    protected $casts = [
        'provider_payload'              => 'array',
        'first_collection_date'         => 'date',
        'redirected_at'                 => 'datetime',
        'authenticated_at'              => 'datetime',
        'first_collection_succeeded_at' => 'datetime',
        'cancelled_at'                  => 'datetime',
        'emandate_url_expires_at'       => 'datetime',
    ];

    public const PENDING       = 'pending';
    public const REGISTERED    = 'registered';
    public const REDIRECTED    = 'redirected';
    public const AUTHENTICATED = 'authenticated';
    public const ACTIVE        = 'active';
    public const REJECTED      = 'rejected';
    public const ABANDONED     = 'abandoned';
    public const CANCELLED     = 'cancelled';
    public const FAILED        = 'failed';

    /** Mandates in these states still have a live journey attached. */
    public const NON_TERMINAL = [
        self::PENDING,
        self::REGISTERED,
        self::REDIRECTED,
        self::AUTHENTICATED,
        self::ACTIVE,
    ];

    /**
     * States that mean "RealPay is, or may still be, collecting against this".
     * A policy holding one of these must not be given a second contract.
     */
    public const COLLECTABLE = [
        self::REGISTERED,
        self::REDIRECTED,
        self::AUTHENTICATED,
        self::ACTIVE,
    ];

    private const ALLOWED = [
        self::PENDING       => [self::REGISTERED, self::FAILED, self::CANCELLED],
        // registered → active covers the non-DebiCheck flow that runs today.
        self::REGISTERED    => [self::REDIRECTED, self::AUTHENTICATED, self::ACTIVE, self::REJECTED, self::FAILED, self::CANCELLED],
        self::REDIRECTED    => [self::AUTHENTICATED, self::ACTIVE, self::REJECTED, self::ABANDONED, self::CANCELLED],
        self::AUTHENTICATED => [self::ACTIVE, self::CANCELLED],
        self::ACTIVE        => [self::CANCELLED],
        // Terminal states below: nothing may move out of them. A new attempt
        // gets a new row with a new contract number.
        self::REJECTED      => [],
        self::ABANDONED     => [],
        self::CANCELLED     => [],
        self::FAILED        => [],
    ];

    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function events()
    {
        return $this->hasMany(RealpayMandateEvent::class, 'mandate_id');
    }

    /** True when this mandate is one a second contract must not be created against. */
    public function isCollectable(): bool
    {
        return in_array($this->status, self::COLLECTABLE, true);
    }

    public function isTerminal(): bool
    {
        return ! in_array($this->status, self::NON_TERMINAL, true);
    }

    public function canMoveTo(string $next): bool
    {
        return $this->status === $next
            || in_array($next, self::ALLOWED[$this->status] ?? [], true);
    }

    /**
     * Move the mandate to $next, persisting $attributes with it.
     *
     * Re-entering the current state is a silent no-op, which is what makes a
     * replayed webhook harmless. Anything else illegal throws — a mandate
     * silently regressing is worse than a logged failure.
     *
     * @throws \DomainException
     */
    public function moveTo(string $next, array $attributes = []): void
    {
        if ($this->status === $next) {
            // Idempotent. Fill only attributes that are still unset, so a
            // replayed webhook cannot move a timestamp that the first delivery
            // already stamped.
            foreach ($attributes as $key => $value) {
                if ($this->getAttribute($key) === null) {
                    $this->setAttribute($key, $value);
                }
            }
            if ($this->isDirty()) {
                $this->save();
            }
            return;
        }

        if (! in_array($next, self::ALLOWED[$this->status] ?? [], true)) {
            throw new \DomainException(
                "Illegal mandate transition {$this->status} -> {$next} (mandate {$this->id})"
            );
        }

        $this->fill($attributes);
        $this->status = $next;
        $this->save();
    }

    /**
     * Record a failed attempt without changing state — used when RealPay errors
     * on something that is not terminal for the mandate (e.g. a failed
     * collection against an otherwise live authority).
     */
    public function recordAttemptFailure(string $error): void
    {
        $this->attempts = (int) $this->attempts + 1;
        $this->last_error = $error;
        $this->save();
    }
}
