<?php

namespace AlphaDirect\Mail;

use AlphaDirect\Models\FacSlip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * The facultative slip, out to the reinsurer or broker, with the PDF attached.
 *
 * This is a contractual communication to a third party, so it is only ever sent
 * by an explicit permissioned action (or by deliberately enabling
 * fac.slips.auto_send) — never as a side effect of generating the document.
 */
class FacSlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FacSlip $slip,
        public Collection $lines,
        private string $pdfBytes
    ) {
    }

    public function build()
    {
        return $this->from(
                config('fac.from.address', 'reinsurance@alphadirect.co.bw'),
                config('fac.from.name', 'Alpha Direct Reinsurance')
            )
            ->subject(sprintf(
                'Facultative slip %s — %s (%s)',
                $this->slip->slip_no,
                $this->slip->insured_name ?: 'insured',
                $this->slip->policy_number ?: 'policy'
            ))
            ->view('Reinsurance.mail.fac-slip')
            ->attachData(
                $this->pdfBytes,
                "FAC-Slip-{$this->slip->slip_no}-v{$this->slip->version}.pdf",
                ['mime' => 'application/pdf']
            );
    }
}
