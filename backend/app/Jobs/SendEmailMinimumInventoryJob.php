<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use AlphaDirect\Mail\MinimumStoreInventoryEmail;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailMinimumInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = new MinimumStoreInventoryEmail($this->data);
        Mail::from('kkatolkar@alphadirect.co.bw')->to('nbarot@theriskco.com')->send($email);
        //$sms_status = InfobipSms::send('+267' . 72720011, 'Alpha Direct,Your are running low inventories for store');
        $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . 72720011, 'Alpha Direct,Your are running low inventories for store'));
    }
}
