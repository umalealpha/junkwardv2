<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\PolicyTerm;
use Illuminate\Console\Command;

class RemoveDuplicatePolicyTermsEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'removeDuplicatePolicyTermsEntries:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $policies = PolicyTerm::groupBy('term_start_date','term_end_date','premium')->havingRaw('count(*) > 1')->get();
    }
}
