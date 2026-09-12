<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use AlphaDirect\Activation;
use AlphaDirect\Helper;
use AlphaDirect\Product;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Exports\ActivationCodeStore;
use AlphaDirect\Mail\ActivationCodeMail;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;


class ActivationCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public  $data;
    public $timeout = 600;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(\AlphaDirect\Events\ActivationCode $data)
    {

        $request = $data->data;
        $email = $request['email'];
        $phoneNumber = $request['cellphone'];
        $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = Helper::gen_ustring(10000000, 99999999);
        }
        for ($i = 1; $i <= $request['noofcodes']; $i++) {
            $activation = new Activation();
            if ($latest_code != null) {
                $var = base_convert($serial_code, 36, 10);
                $var++;
                
                while (preg_match('~[0-9]+~', strtoupper(base_convert($var, 10, 36)))) {
                    $var++;
                }
                $serial_code = strtoupper(base_convert($var, 10, 36));
            }
            $activation->group_id = $group_id;
            $activation->serial_code = $serial_code;
            $check = Activation::where('activation_code', $activation_code)->count();
            while ($check > 0) {
                $activation_code = Helper::gen_ustring(10000000, 99999999);
                $check = Activation::where('activation_code', $activation_code)->count();
            }
            $activation->activation_code = $activation_code;
            $activation->vendor = $request['vendor'];
            $activation->branch = $request['branch'];
            $activation->rack_no = $request['rack_no'];
            $activation->trial_periods = $request['trial_periods'];
            $activation->trial_coverage = $request['trial_coverage'];
            $activation->country = $request['country'];
            $activation->city = $request['city'];
            $activation->state = $request['state'];
            $activation->product_type_id = $request['product_type'];
            $activation->product_id = $request['product'];
            $activation->premium_type_id = Product::where('id', $request['product'])->first(array('premium_type_id'))->premium_type_id;
            $activation->product_plan_id = $request['plan'];
            $activation->email = $email;
            $activation->cellphone = $phoneNumber;

            $activation->status = 0;
            $saved = $activation->save();
            
            $latest_code = 1;
        }
    if ($saved) {
           
            $date = \Carbon\Carbon::now()->timestamp;
            $filePath = 'activation_codes-'.$date.'.xls';
            $exportData =  Excel::store(new ActivationCodeStore($request['noofcodes']), $filePath,'s3');
            
            $new = Activation::where('id', $activation->id)->first();
            $new->download = $filePath;
           
            $new->save();
            $url = \AlphaDirect\Helper::getCloudFrontURL($filePath);

       if($email){
            $email = $email;
            $mail = new \stdClass();
            $mail->email = $email;
            $mail->hook = 'activation_code';
            $mail->link =  \AlphaDirect\Helper::getCloudFrontURL($filePath);
            $mail->totalcodes = $request['noofcodes'];
            $mail->attachment = null;
          //  $mail->msg = "Your " .$request['noofcodes']." Activation Code Created Successfully";
           // $to =  Mail::to($email)->send(new ActivationCodeMail($mail));
            $emailTemplate = EmailBroadcasting::where('hook_slug', $mail->hook)->first(array('subject'));
            $markdown = new MailTemplate($mail);
            $html = $markdown->render('Mail.mailTemplate',['data'=>$mail]);
            event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,null,['hook' => $mail->hook]));

            }
        if($phoneNumber){
            $phoneNumber = $phoneNumber;
            $code = $request['noofcodes'];
            $url = \AlphaDirect\Helper::getCloudFrontURL($filePath);
            $sms = new SmsMessaging();
            $sms->sendActivationCodeLink($phoneNumber,$url,$code);
         }
         
        
        return '';
        }else{
             return "";
        }
       
    }
}
