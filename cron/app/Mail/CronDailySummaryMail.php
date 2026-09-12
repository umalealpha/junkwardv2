<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CronDailySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $rows;
    public string $date;
    public int $total;
    public int $completed;
    public int $failed;

    public function __construct(array $rows, string $date)
    {
        $this->rows      = $rows;
        $this->date      = $date;
        $this->total     = count($rows);
        $this->completed = collect($rows)->filter(fn($r) => !empty($r->end))->count();
        $this->failed    = collect($rows)->filter(fn($r) => empty($r->end))->count();
    }

    public function build()
    {
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject("Daily Cron Report — {$this->date}")
            ->view('Mail.CronDailySummary');
    }
}
