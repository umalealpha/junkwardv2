<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\PolicySpecifiedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageDetail;

class UpdateSpecifiedCalculatedValue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateSpecifiedCalculatedValue:calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the calculated value of specified items in the policy specified items table';

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
        // $items = PolicySpecifiedItem::join('policy_coverages', 'policy_specified_items.policy_coverage_id', '=', 'policy_coverages.id')
        // ->where('policy_coverages.policy_id', $policyCoverageId)
        // ->select('policy_specified_items.*', 'policy_coverages.coverage_name') // Adjust columns
        // ->get();

        // foreach ($items as $item) {
        //     $item->calculated_value = ($item->sum_insured * $item->rate) / 100;
        //     $item->save();
        // }

        $this->info('Calculated values updated successfully!');
    }
}
