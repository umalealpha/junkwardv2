<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\VcsTransactionDummyData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Log;

class ImportUploadedCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $file;
    public $key;
    public $escapeheader;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $file, int $key, array $escapeheader)
    {
        $this->file = $file;
        $this->key = $key;
        $this->escapeheader = $escapeheader;
        // dd($this->file,$this->key);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('VCS csv started uploading data in db.....'.$this->file.'----'.$this->key);

        $fileData = array_map('str_getcsv',file($this->file));

        if($this->key == 0) {
            // $header = array_slice($fileData,0,1);
            $fileData = array_slice($fileData,1);

        //     foreach($header[0] as $key=>$value){
        //         $lowerHeader    = strtolower($value);
        //         $escapedItems   = preg_replace("/[^a-z]/", "", $lowerHeader);
        //         $noSpaceString  = preg_replace("/\s+/", "", $escapedItems);
        //         array_push($this->escapeheader,$noSpaceString);
        //     }

        }

        foreach ($fileData as $item) {
            $data = array_combine($this->escapeheader,$item);

            $resultDescpArray = ['Payment Done', 'Auth Declined'];
            $statusArray = ['Settlement', 'Auth  Rejected'];

            if (in_array($data['resultdescription'], $resultDescpArray)) {

                if (isset($data['accountholder'])) {
                    $data['name'] = $data['accountholder'];
                }

                if (isset($data['transactiondate'])) {
                    $data['settlementdate'] = $data['transactiondate'];

                    if (str_contains($data['transactiondate'], '/')){
                        $data['transactiondate'] = date_create_from_format('d/m/Y H:i:s', $data['transactiondate']);
                    }
                }

                if (isset($data['settlementdate'])) {
                    if (str_contains($data['settlementdate'], ' AM') || str_contains($data['settlementdate'], ' PM')) {
                        $replace = array(' AM',' PM');
                        $data['settlementdate'] = str_replace($replace, '', $data['settlementdate']);
                    }

                    if (str_contains($data['settlementdate'], '/')){
                        $data['settlementdate'] = date_create_from_format('d/m/Y H:i:s', $data['settlementdate']);
                    }

                }

                $add = VcsTransactionDummyData::updateOrCreate([
                    'reference' => isset($data['reference']) ? $data['reference'] : null,
                ], [
                    "reference" => isset($data['reference']) ? $data['reference'] : null,
                    "originalreference" => isset($data['originalreference']) ? $data['originalreference'] : null,
                    "name" => isset($data['name']) ? $data['name'] : null,
                    "goods" => isset($data['goods']) ? $data['goods'] : null,
                    "amount" => isset($data['amount']) ? $data['amount'] : null,
                    "bp" => isset($data['bp']) ? $data['bp'] : null,
                    "code" => isset($data['code']) ? $data['code'] : null,
                    "response" => isset($data['response']) ? $data['response'] : null,
                    "settlementdate" => isset($data['settlementdate']) ? Carbon::parse($data['settlementdate'])->format('Y-m-d H:i:s') : null,
                    "transactiondate" => isset($data['transactiondate']) ? Carbon::parse($data['transactiondate'])->format('Y-m-d H:i:s') : null,
                    "settlementreference" => isset($data['settlementreference']) ? $data['settlementreference'] : null,
                    "interface" => isset($data['interface']) ? $data['interface'] : null,
                    "status" => isset($data['resultdescription']) ? $data['resultdescription'] : null,
                ]);

            }

        }

        unlink($this->file);

        sleep(1);
        Log::info('VCS csv data uploaded done....'.$this->file.'----'.$this->key);
    }
}
