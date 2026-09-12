<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class CheckInfoBipStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:infobip';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Info Bip Status ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
    	$cron = new CronStatus();
        $cron->name = "sms:infobip";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
		//fetch all 
		foreach(\DB::table('sms_infobip_log')->where('pull_status',0)->get() as $l){
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => 'http://zmm2w.api.infobip.com/sms/1/logs?messageId='.$l->message_id,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_HTTPHEADER => array(
					'Authorization: App '.env('INFOBIP_KEY'),
					'Content-Type: application/json',
					'Accept: application/json'
				),
			));

			$response = curl_exec($curl);
			if (!curl_errno($curl)) {
				$data = json_decode($response,true);
				\DB::table('sms_infobip_log')->where('message_id','=','33620415671705283785')->update([
					'status'		=>$data['results'][0]['status']['groupName'],
					'sentAt'		=>\Carbon\Carbon::parse($data['results'][0]['sentAt']),
					'doneAt'		=>\Carbon\Carbon::parse($data['results'][0]['doneAt']),
					'all_response'	=>json_encode($data),
					'pull_status'   =>($data['results'][0]['status']['groupName']!="PENDING")?1:0
				]);
			}
			curl_close($curl);
		}
		$cron->end = \Carbon\Carbon::now();
        $cron->save();
		return Command::SUCCESS;
    }
}
