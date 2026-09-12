<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;

class deleteRealpayInstallmentDebitOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteRealpayInstallment:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used ot delete the realpay installment according to policy ';

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
        return 0;
    }
}
