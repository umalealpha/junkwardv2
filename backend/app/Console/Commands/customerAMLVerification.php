<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use Illuminate\Console\Command;
use Log;

class customerAMLVerification extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customerAMLVerification:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch customer details and send data on metamap api for aml verification ';

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
        $customers = Customer::join('customer_profile', 'customer_profile.customer_id', 'customer.id')
            ->where('customer.is_aml_verification_done', 0)
            ->get([
                'customer.id as customer_id',
                'customer.firstName',
                'customer.lastName',
                'customer.email',
                'customer_profile.dob'
            ]);

        if ($customers->isEmpty()) {
            $this->info('No customers found for AML verification.');
            return 0;
        }

        foreach ($customers as $customer) {
            $this->verifyCustomerAML($customer);
        }

        return 0;
    }

    private function verifyCustomerAML($customer)
    {
        try {
            $curl = curl_init();

            $payload = json_encode([
                "entityType" => "person",
                "monitor" => false,
                "exactMatch" => false,
                "fuzzinessThreshold" => 50,
                "name" => trim($customer->firstName . ' ' . $customer->lastName),
                "birthYear" => (int) date('Y', strtotime($customer->dob)),
                "metadata" => [
                    "user-defined-1" => $customer->customer_id,
                    "user-defined-2" => $customer->email
                ]
            ]);

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.getmati.com/safety/v1/checks/comply-advantage",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    "accept: application/json",
                    "authorization: Bearer " . $this->getAccessToken(),
                    "content-type: application/json"
                ],
            ]);

            $response = curl_exec($curl);
            if ($response === false) {
                throw new \Exception(curl_error($curl));
            }

            curl_close($curl);
            $responseData = json_decode($response, true);

            $customer->aml_verification_response = json_encode($responseData);
            $customer->is_aml_verification_done = 1;
            $customer->save();

            // Log::info("AML verification completed for Customer ID: {$customer->id}");
        } catch (\Exception $e) {
            Log::error("AML Verification failed for Customer ID {$customer->id}: " . $e->getMessage());
        }
    }

    private function getAccessToken()
    {
        try {
            $curlToken = curl_init();

            curl_setopt_array($curlToken, [
                CURLOPT_URL => "https://api.getmati.com/oauth",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => [
                    "Authorization: Basic NjExMjZjZmIzODNmZjgwMDFiMjdhNGJmOlBGN1VUWFZZMkdOUkZRQUs1QUE0VDRaWDFOS0VVSDgy",
                    "Content-Type: application/x-www-form-urlencoded",
                    "Accept: application/json"
                ],
            ]);

            $response = curl_exec($curlToken);
            if ($response === false) {
                throw new \Exception(curl_error($curlToken));
            }

            $fetchToken = json_decode($response, true);
            curl_close($curlToken);

            return $fetchToken['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error("Error retrieving MetaMap access token: " . $e->getMessage());
            return null;
        }
    }
}
