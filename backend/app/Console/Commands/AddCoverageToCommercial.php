<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\ProductCoverage;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AddCoverageToCommercial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coverage:add-to-commercial 
                            {--name= : Coverage internal name}
                            {--screen-name= : Coverage display name}
                            {--description= : Coverage description}
                            {--product-ids=7,8 : Comma-separated product IDs}
                            {--rating-method=FIXED : Rating method}
                            {--display-sequence=10 : Display sequence}
                            {--has-vehicle=0 : Requires vehicle (0 or 1)}
                            {--has-member=0 : Requires member (0 or 1)}
                            {--has-device=0 : Requires device (0 or 1)}
                            {--display-to-user=1 : Display to user (0 or 1)}
                            {--effective-date= : Effective date (Y-m-d format, defaults to today)}
                            {--expiration-date= : Expiration date (Y-m-d format, defaults to 2099-12-31)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new coverage to commercial products (Product IDs: 7, 8)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->option('name');
        $screenName = $this->option('screen-name');
        $description = $this->option('description') ?? 'Commercial coverage';
        $productIds = explode(',', $this->option('product-ids'));
        $ratingMethod = $this->option('rating-method');
        $displaySequence = (int)$this->option('display-sequence');
        $hasVehicle = (int)$this->option('has-vehicle');
        $hasMember = (int)$this->option('has-member');
        $hasDevice = (int)$this->option('has-device');
        $displayToUser = (int)$this->option('display-to-user');
        
        $effectiveDate = $this->option('effective-date') 
            ? Carbon::createFromFormat('Y-m-d', $this->option('effective-date'))
            : Carbon::today();
            
        $expirationDate = $this->option('expiration-date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('expiration-date'))
            : Carbon::createFromFormat('Y-m-d', '2099-12-31');

        // Validate required fields
        if (!$name || !$screenName) {
            $this->error('Error: --name and --screen-name are required');
            $this->info('Usage: php artisan coverage:add-to-commercial --name="Coverage Name" --screen-name="Screen Name"');
            return 1;
        }

        // Check if coverage already exists
        $coverageCode = strtoupper(str_replace(' ', '', $screenName));
        $existing = CoverageMaster::where('s_CoverageCode', $coverageCode)
            ->orWhere('s_ScreenName', $screenName)
            ->orWhere('s_CoverageName', $name)
            ->first();

        if ($existing) {
            $this->error("Error: Coverage already exists with code: {$coverageCode}");
            $this->info("Existing coverage ID: {$existing->id}");
            return 1;
        }

        // Create coverage
        $this->info("Creating coverage: {$screenName}...");
        
        $coverage = new CoverageMaster();
        $coverage->s_CoverageName = $name;
        $coverage->s_ScreenName = $screenName;
        $coverage->s_CoverageCode = $coverageCode;
        $coverage->s_CoverageDesc = $description;
        $coverage->s_RatingMethod = $ratingMethod;
        $coverage->n_PrintSequence = $displaySequence;
        $coverage->n_DisplaySequence = $displaySequence;
        $coverage->n_RateSequence = $displaySequence;
        $coverage->s_DISPLAYTOUSER = $displayToUser;
        $coverage->has_vehicle = $hasVehicle;
        $coverage->has_member = $hasMember;
        $coverage->has_device = $hasDevice;
        $coverage->s_GroupRowType = 'COVERAGE';
        $coverage->s_UsageType = 'PARENT';
        $coverage->s_CoveragePart = 'PROPERTY';
        $coverage->s_CoverageSection = 'MAIN';
        $coverage->s_CoverageGroupCode = 'MAIN';
        $coverage->s_CoverageGroupName = 'Main';
        $coverage->s_ParentCoverageCode = null;
        $coverage->s_ParentCoverageID = null;
        $coverage->n_ParentCoverageForRate = null;
        $coverage->s_DefaultCovgCategoryCode = 'ENDCOVG';
        $coverage->s_AutoRenew = 'Y';
        $coverage->s_PermitDuplication = 'N';
        $coverage->s_CvgOccurrence = 'SINGLE';
        $coverage->d_EffectiveDt = $effectiveDate;
        $coverage->d_ExpirationDt = $expirationDate;

        if (!$coverage->save()) {
            $this->error('Error: Failed to create coverage');
            return 1;
        }

        $this->info("✓ Coverage created with ID: {$coverage->id}");

        // Link to products
        $linkedProducts = [];
        foreach ($productIds as $productId) {
            $productId = trim($productId);
            
            // Check if link already exists
            $existingLink = ProductCoverage::where('product_id', $productId)
                ->where('coverage_id', $coverage->id)
                ->first();

            if ($existingLink) {
                $this->warn("  Coverage already linked to Product ID {$productId}");
                continue;
            }

            $productCoverage = new ProductCoverage();
            $productCoverage->product_id = $productId;
            $productCoverage->coverage_id = $coverage->id;
            $productCoverage->name = $screenName;

            if ($productCoverage->save()) {
                $this->info("✓ Linked to Product ID: {$productId}");
                $linkedProducts[] = $productId;
            } else {
                $this->error("  Failed to link to Product ID: {$productId}");
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Summary ===');
        $this->info("Coverage ID: {$coverage->id}");
        $this->info("Coverage Code: {$coverageCode}");
        $this->info("Screen Name: {$screenName}");
        $this->info("Linked to Products: " . implode(', ', $linkedProducts));
        $this->newLine();
        $this->info('Coverage successfully added to commercial products!');
        $this->info('You may need to clear cache: php artisan cache:clear');

        return 0;
    }
}


