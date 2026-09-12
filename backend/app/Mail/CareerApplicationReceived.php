<?php

namespace AlphaDirect\Mail;

use AlphaDirect\Models\WebsiteLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Notifies HR that a career application (job application or talent-pool
 * profile) was submitted on the marketing website. The CV and credential
 * files uploaded with the lead are attached, capped at 15MB combined so
 * the message never exceeds the mail provider's size limit — anything
 * over the cap stays viewable on the admin Leads page.
 */
class CareerApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    private const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    public WebsiteLead $lead;

    public function __construct(WebsiteLead $lead)
    {
        $this->lead = $lead;
    }

    public function build()
    {
        $mail = $this
            ->subject('Alpha Direct — New Career Application #' . $this->lead->id . ' (' . ($this->lead->product ?: 'Careers') . ')')
            ->view('emails.career-application-received', ['lead' => $this->lead]);

        $attached = 0;
        foreach ($this->fileRefs() as $ref) {
            $path = 'website-leads/' . $ref['file'];
            if (!Storage::exists($path)) {
                continue;
            }
            $size = Storage::size($path);
            if ($attached + $size > self::MAX_ATTACHMENT_BYTES) {
                continue;
            }
            $attached += $size;
            $mail->attachData(Storage::get($path), $ref['original']);
        }

        return $mail;
    }

    /** @return array<int, array{file: string, original: string}> */
    private function fileRefs(): array
    {
        $details = $this->lead->details ?? [];
        $refs = [];
        $push = function ($ref) use (&$refs) {
            if (is_array($ref) && !empty($ref['file']) && is_string($ref['file'])) {
                $refs[] = [
                    'file' => $ref['file'],
                    'original' => (string) ($ref['original'] ?? $ref['file']),
                ];
            }
        };
        $push($details['resume_file'] ?? null);
        foreach ((array) ($details['credential_files'] ?? []) as $ref) {
            $push($ref);
        }

        return $refs;
    }
}
