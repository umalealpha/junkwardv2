<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Daily SLA management digest email. Data assembled by SlaMetricsService and
 * rendered by the Mail.SlaDigest view.
 */
class SlaDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $date = optional($this->data['generated_at'] ?? null);

        return $this->from(
                config('help_desk.from_email', 'insurance@alphadirect.co.bw'),
                config('help_desk.from_name', 'Alpha Direct Help Desk'),
            )
            ->subject('[SLA Digest] Help Desk — ' . ($date ? $date->format('d M Y') : ''))
            ->view('Mail.SlaDigest', ['d' => $this->data]);
    }
}
