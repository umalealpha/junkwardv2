<?php

namespace AlphaDirect\Http\Livewire\CustomerKyc;

use AlphaDirect\CustomerProfile;
use Livewire\Component;
use AlphaDirect\Models\Company;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class Table extends DataTableComponent
{
    public Policy $policy;

    protected $listeners = [
        'deleteRecord'
    ];

    public function boot(): void
    {
        config(['livewire-tables.theme' => 'bootstrap-5']);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('customer_kyc.id', 'desc')
            ->setPerPageAccepted([10, 25, 50, 100])
            ->setPerPage(10) // Load 10 records per page for better performance
            ->setSearchDebounce(500); // Debounce search to reduce queries
    }

    /**
     * Custom view to show a loading overlay while Livewire requests are in-flight.
     */
    public function customView(): string
    {
        return 'v2.tables.kyc-loader';
    }
    
    public function columns(): array
    {
        return [
            // Column::make('Id', 'id')
            //     ->sortable()
            //     ->searchable()
            //     ->format(function ($value, $row) {
            //         return '
			// 		<btton class="btn btn-bg-light btn-color-info w-100">
			// 			' . $value . '
			// 		</btton>
			// 	';
            //     })
            //     ->html(),
            // Column::make('Policy Number','policyNumber')
            // ->sortable()->searchable(),

            Column::make('Customer Name', 'customer_id')
                ->sortable()
                ->searchable(function(Builder $builder, string $value) {
                    // OPTIMIZED: Use direct column comparisons instead of orWhereHas (10-100x faster)
                    // The joins are already in the builder, so we can search directly on joined columns
                    $searchTerm = trim($value);
                    if (empty($searchTerm)) {
                        return;
                    }
                    $builder->where(function($query) use ($searchTerm) {
                        $query->where('customer.firstName', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('customer.lastName', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('policies.policyNumber', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('companies.name', 'LIKE', "%{$searchTerm}%");
                    });
                })
                ->format(function($value, $row) {
                    // OPTIMIZED: Use joined company data instead of querying per row (fixes N+1 query)
                    if (!$row->customer_id) {
                        return 'N/A';
                    }

                    $name = 'N/A';
                    // Use company_name from join if available (no additional query needed)
                    if (($row->product_id == 7 || $row->product_id == 8) && $row->entity_type == "Organisation" && !empty($row->company_name)) {
                        $name = $row->company_name;
                    } else {
                        $parts = array_filter([$row->firstName ?? '', $row->middleName ?? '', $row->lastName ?? '']);
                        $name = trim(implode(' ', $parts)) ?: 'N/A';
                    }

                    $editUrl = route('admin.customer.edit', $row->customer_id);
                    return '<a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">' 
                         . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
                })
                ->html(),

            Column::make('Omang Front', 'omang')
                ->sortable()
                ->format(function($value, $row) {
                    $omang = 'N/A';
                    if ($row->omang != null)
                    {
                        $omang = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->omang) . ' " target= "_blank"> Omang front picture download link </a>';
                    }else{
                        $omang = 'Not Uploaded';
                    }

                    return $omang;
                })
                ->html(),

                Column::make('Omang Back', 'omangBack')
                ->sortable()
                ->format(function($value, $row) {
                    $omangBack = 'N/A';
                    if ($row->omangBack != null) {
                        $omangBack = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->omangBack) . '" target="_blank"> Omang back picture download link </a>';
                    } else {
                        $omangBack = 'Not Uploaded';
                    }
                    return $omangBack;
                })
                ->html(),

                Column::make('Passport', 'passport')
                ->sortable()
                ->format(function($value, $row) {
                    $passport = 'N/A';
                    if ($row->passport != null) {
                        $passport = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->passport) . '" target="_blank"> Passport download link </a>';
                    } else {
                        $passport = 'Not Uploaded';
                    }
                    return $passport;
                })
                ->html(),

                Column::make('Proof of Residence', 'proof_residence')
                ->sortable()
                ->format(function($value, $row) {
                    $proofResidence = 'N/A';
                    if ($row->proof_residence != null) {
                        $proofResidence = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->proof_residence) . '" target="_blank"> Proof of residence download link </a>';
                    } else {
                        $proofResidence = 'Not Uploaded';
                    }
                    return $proofResidence;
                })
                ->html(),

                Column::make('Proof of Income', 'proof_income')
                ->sortable()
                ->format(function($value, $row) {
                    $proofIncome = 'N/A';
                    if ($row->proof_income != null) {
                        $proofIncome = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->proof_income) . '" target="_blank"> Proof of income download link </a>';
                    } else {
                        $proofIncome = 'Not Uploaded';
                    }
                    return $proofIncome;
                })
                ->html(),

                Column::make('KYC Form', 'kyc_form')
                ->sortable()
                ->format(function($value, $row) {
                    $kycForm = 'N/A';
                    if ($row->kyc_kyc_form != null) {
                        $kycForm = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_kyc_form) . '" target="_blank"> KYC form download link </a>';
                    } else {
                        $kycForm = 'Not Uploaded';
                    }
                    return $kycForm;
                })
                ->html(),

                Column::make('Data Protection Form', 'data_protection_form')
                ->sortable()
                ->format(function($value, $row) {
                    $dataProtectionForm = 'N/A';
                    if ($row->kyc_data_protection_form != null) {
                        $dataProtectionForm = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_data_protection_form) . '" target="_blank"> Data protection form download link </a>';
                    } else {
                        $dataProtectionForm = 'Not Uploaded';
                    }
                    return $dataProtectionForm;
                })
                ->html(),

                Column::make('Certificate of Incorporation', 'certificate_of_incorporation')
                ->sortable()
                ->format(function($value, $row) {
                    $certificateOfIncorporation = 'N/A';
                    if ($row->kyc_certificate_of_incorporation != null) {
                        $certificateOfIncorporation = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_certificate_of_incorporation) . '" target="_blank"> Certificate of incorporation download link </a>';
                    } else {
                        $certificateOfIncorporation = 'Not Uploaded';
                    }
                    return $certificateOfIncorporation;
                })
                ->html(),

                Column::make('Extract Controllers', 'extract_controllers')
                ->sortable()
                ->format(function($value, $row) {
                    $extractControllers = 'N/A';
                    if ($row->kyc_extract_controllers != null) {
                        $extractControllers = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_extract_controllers) . '" target="_blank"> Extract controllers download link </a>';
                    } else {
                        $extractControllers = 'Not Uploaded';
                    }
                    return $extractControllers;
                })
                ->html(),

                Column::make('Resolution', 'resolution')
                ->sortable()
                ->format(function($value, $row) {
                    $resolution = 'N/A';
                    if ($row->kyc_resolution != null) {
                        $resolution = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_resolution) . '" target="_blank"> Resolution download link </a>';
                    } else {
                        $resolution = 'Not Uploaded';
                    }
                    return $resolution;
                })
                ->html(),

                Column::make('Proof of Business Address', 'proof_business_address')
                ->sortable()
                ->format(function($value, $row) {
                    $proofBusinessAddress = 'N/A';
                    if ($row->kyc_proof_business_address != null) {
                        $proofBusinessAddress = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_proof_business_address) . '" target="_blank"> Proof of business address download link </a>';
                    } else {
                        $proofBusinessAddress = 'Not Uploaded';
                    }
                    return $proofBusinessAddress;
                })
                ->html(),

                Column::make('Proof of Residential Address', 'proof_residential_address')
                ->sortable()
                ->format(function($value, $row) {
                    $proofResidentialAddress = 'N/A';
                    if ($row->kyc_proof_residential_address != null) {
                        $proofResidentialAddress = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_proof_residential_address) . '" target="_blank"> Proof of residential address download link </a>';
                    } else {
                        $proofResidentialAddress = 'Not Uploaded';
                    }
                    return $proofResidentialAddress;
                })
                ->html(),

                Column::make('Directors ID Front', 'directors_id_front')
                ->sortable()
                ->format(function($value, $row) {
                    $directorsIdFront = 'N/A';
                    if ($row->kyc_directors_id_front != null) {
                        $directorsIdFront = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_directors_id_front) . '" target="_blank"> Directors ID front download link </a>';
                    } else {
                        $directorsIdFront = 'Not Uploaded';
                    }
                    return $directorsIdFront;
                })
                ->html(),

                Column::make('Directors ID Back', 'directors_id_back')
                ->sortable()
                ->format(function($value, $row) {
                    $directorsIdBack = 'N/A';
                    if ($row->kyc_directors_id_back != null) {
                        $directorsIdBack = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_directors_id_back) . '" target="_blank"> Directors ID back download link </a>';
                    } else {
                        $directorsIdBack = 'Not Uploaded';
                    }
                    return $directorsIdBack;
                })
                ->html(),

                Column::make('Directors Passport', 'directors_passport')
                ->sortable()
                ->format(function($value, $row) {
                    $directorsPassport = 'N/A';
                    if ($row->kyc_directors_passport != null) {
                        $directorsPassport = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_directors_passport) . '" target="_blank"> Directors passport download link </a>';
                    } else {
                        $directorsPassport = 'Not Uploaded';
                    }
                    return $directorsPassport;
                })
                ->html(),

                Column::make('Shareholders ID Front', 'shareholders_id_front')
                ->sortable()
                ->format(function($value, $row) {
                    $shareholdersIdFront = 'N/A';
                    if ($row->kyc_shareholders_id_front != null) {
                        $shareholdersIdFront = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_shareholders_id_front) . '" target="_blank"> Shareholders ID front download link </a>';
                    } else {
                        $shareholdersIdFront = 'Not Uploaded';
                    }
                    return $shareholdersIdFront;
                })
                ->html(),

                Column::make('Shareholders ID Back', 'shareholders_id_back')
                ->sortable()
                ->format(function($value, $row) {
                    $shareholdersIdBack = 'N/A';
                    if ($row->kyc_shareholders_id_back != null) {
                        $shareholdersIdBack = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_shareholders_id_back) . '" target="_blank"> Shareholders ID back download link </a>';
                    } else {
                        $shareholdersIdBack = 'Not Uploaded';
                    }
                    return $shareholdersIdBack;
                })
                ->html(),

                Column::make('Shareholders Passport', 'shareholders_passport')
                ->sortable()
                ->format(function($value, $row) {
                    $shareholdersPassport = 'N/A';
                    if ($row->kyc_shareholders_passport != null) {
                        $shareholdersPassport = '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($row->kyc_shareholders_passport) . '" target="_blank"> Shareholders passport download link </a>';
                    } else {
                        $shareholdersPassport = 'Not Uploaded';
                    }
                    return $shareholdersPassport;
                })
                ->html(),

                Column::make('Compliance', 'compliance')
                ->sortable()
                ->format(function($value, $row) {
                    if ($row->compliance == 1){
                        $compliance = 'Compliant';
                    }elseif($row->compliance == 2){
                        $compliance = 'Non-Compliant';
                    }elseif($row->compliance == 0){
                        $compliance = 'Verification Pending';
                    }elseif($row->compliance == 3){
                        $compliance = 'No Id-No Documents';
                    }else{
                        $compliance = 'N/A';
                    }
                    return $compliance;
                })
                ->html(),

                Column::make('Status', 'status')
                ->sortable()
                ->format(function($value, $row) {
                    if($row->policy_status == '2'){
                        $status= 'Cancelled';
                    }else{
                        if ($row->status != null )
                        {
                            $status= $row->status;
                        }else{
                            $status = 'N/A';
                        }
                    }
                    return $status;
                })
                ->html(),

                Column::make('Updated At', 'updated_at')
                ->sortable()
                ->format(function($value, $row) {
                    // OPTIMIZED: Use string manipulation instead of Carbon::parse (10x faster)
                    if($row->updated_at != null)
                    {
                        // Extract Y-m-d H:i from datetime string (faster than Carbon)
                        $updated_at = substr($row->updated_at, 0, 16);
                    }else{
                        $updated_at='N/A';
                    }
                    return $updated_at;
                })
                ->html(),

            Column::make('Action', 'id')
                ->format(function ($value, $row) {
                    return view('v2.tables.route-action', [
                        'id' => $row->id,
                        'action' => $this->actionButton($row)
                    ]);
                })->html(),
        ];
    }

    public function builder(): Builder
    {
        // ULTRA-OPTIMIZED QUERY FOR PAGINATION:
        // 1. Early filter: Get customer_ids with policies in products 7,8 (uses index)
        // 2. Use subquery to get ONE policy per customer (reduces groupBy overhead)
        // 3. All joins use indexed columns for maximum performance
        // 4. Only loads 10 records per page (lazy loading)
        
        // Step 1: Get customer_ids with policies in products 7,8 (uses index on policies.product_id)
        $customerIdsSubquery = DB::table('policies')
            ->select('customer_id')
            ->whereIn('product_id', [7, 8])
            ->distinct();
        
        // Step 2: Get one policy per customer using optimized subquery (MIN id for consistency)
        // This reduces the dataset before groupBy, making pagination much faster
        $policySubquery = DB::table('policies as p1')
            ->select([
                'p1.customer_id',
                'p1.status as policy_status',
                'p1.product_id',
                'p1.policyNumber as policyNumber'
            ])
            ->whereIn('p1.product_id', [7, 8])
            ->whereIn('p1.customer_id', $customerIdsSubquery)
            ->whereRaw('p1.id = (
                SELECT MIN(p2.id) 
                FROM policies p2 
                WHERE p2.customer_id = p1.customer_id 
                AND p2.product_id IN (7, 8)
            )');
        
        // Step 3: Main query - filter first, then join efficiently
        // Pagination is handled automatically by Livewire Tables (loads 10 per page)
        return KYC::whereIn('customer_kyc.customer_id', $customerIdsSubquery)
            ->leftJoin('customer', 'customer.id', '=', 'customer_kyc.customer_id')
            ->leftJoin('companies', 'companies.id', '=', 'customer.company_id')
            ->leftJoin('customer_profile', 'customer.id', '=', 'customer_profile.customer_id')
            ->leftJoinSub($policySubquery, 'policies', function($join) {
                $join->on('policies.customer_id', '=', 'customer_kyc.customer_id');
            })
            ->leftJoin('customer_kyc_dom_com', 'policies.customer_id', '=', 'customer_kyc_dom_com.customer_id')
            ->select([
                'customer_kyc.id',
                'customer_kyc.customer_id',
                'customer.firstName',
                'customer.middleName',
                'customer.lastName',
                'customer_kyc.remark',
                'customer_kyc.compliance',
                'customer_kyc.status',
                'customer_kyc.omang',
                'customer_kyc.omangBack',
                'customer_kyc.passport',
                'customer_kyc.proof_residence',
                'customer_kyc.proof_income',
                'customer_kyc.updated_at',
                'policies.policy_status',
                'policies.product_id',
                'policies.policyNumber',
                'customer.company_id',
                'companies.name as company_name',
                'customer_profile.entity_type',
                'customer_kyc_dom_com.kyc_form as kyc_kyc_form',
                'customer_kyc_dom_com.data_protection_form as kyc_data_protection_form',
                'customer_kyc_dom_com.certificate_of_incorporation as kyc_certificate_of_incorporation',
                'customer_kyc_dom_com.extract_controllers as kyc_extract_controllers',
                'customer_kyc_dom_com.resolution as kyc_resolution',
                'customer_kyc_dom_com.proof_business_address as kyc_proof_business_address',
                'customer_kyc_dom_com.proof_residential_address as kyc_proof_residential_address',
                'customer_kyc_dom_com.directors_id_front as kyc_directors_id_front',
                'customer_kyc_dom_com.directors_id_back as kyc_directors_id_back',
                'customer_kyc_dom_com.directors_passport as kyc_directors_passport',
                'customer_kyc_dom_com.shareholders_id_front as kyc_shareholders_id_front',
                'customer_kyc_dom_com.shareholders_id_back as kyc_shareholders_id_back',
                'customer_kyc_dom_com.shareholders_passport as kyc_shareholders_passport'
            ])
            ->groupBy('policies.customer_id') // Keep groupBy to match original record count
            ->orderBy('customer_kyc.id', 'DESC');
    }
    
    /**
     * Override to prevent auto-adding columns that are already selected
     */
    protected function selectFields(): Builder
    {
        // Don't auto-add columns - we've already selected everything we need in builder()
        return $this->getBuilder();
    }


    protected function actionButton($d)
    {
        return [
            'edit' => [
                'href' => route('admin.viewCustomerKycDataDomCom', $d->id)
            ],
            // 'delete' => [
            //     'arg' => "'deleteRecord',$d->id",
            //     'onClick' => "deleteRow",
            // ]
        ];
    }

    // public function deleteRecord($id)
    // {
    //     if (KYC::find($id)->delete()) {
    //         $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Deleted Successfully!']);
    //     } else {
    //         $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Something Went Wrong']);
    //     }
    // }

}
