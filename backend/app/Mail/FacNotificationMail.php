<?php

namespace AlphaDirect\Mail;

use AlphaDirect\Models\FacPlacement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * An internal FAC alert — premium landed, placement cancelled, warranty about to
 * lapse, warranty breached, settled.
 *
 * The wording comes from the service that raised the event, so the same sentence
 * appears in the email and on the placement's trail. One version of events.
 */
class FacNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $event,
        public string $summaryLine,
        public ?FacPlacement $placement = null,
        public array $payload = []
    ) {
    }

    public function build()
    {
        return $this->from(
                config('fac.from.address', 'reinsurance@alphadirect.co.bw'),
                config('fac.from.name', 'Alpha Direct Reinsurance')
            )
            ->subject($this->subjectFor())
            ->view('Reinsurance.mail.fac-notification');
    }

    private function subjectFor(): string
    {
        $ref = $this->placement?->fac_reference ? " — {$this->placement->fac_reference}" : '';

        return match ($this->event) {
            'client_paid'    => "FAC: client premium received{$ref} — settle the reinsurer",
            'cancelled'      => "FAC: DO NOT SETTLE — placement cancelled{$ref}",
            'ppw_warned'     => "FAC: premium payment warranty falls due soon{$ref}",
            'ppw_breached'   => "FAC: PREMIUM PAYMENT WARRANTY BREACHED{$ref}",
            'settled'        => "FAC: settled{$ref}",
            'auto_generated' => "FAC: draft placement raised for confirmation{$ref}",
            default          => "FAC register update{$ref}",
        };
    }
}
