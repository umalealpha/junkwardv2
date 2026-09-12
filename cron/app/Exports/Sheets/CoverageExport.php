<?php
namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\CoverageMaster;
use AlphaDirect\Http\Traits\Excel\ExportTrait;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\PolicyCoverage;
use AlphaDirect\RiskAddress;
use AlphaDirect\Vehicle;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class CoverageExport implements WithTitle,FromCollection,WithStyles,WithColumnWidths,WithEvents
{
    public $policy;
    public $termId;
    public $actionId;
    public $coverageName;
    public $mainCoverage = null;
    public $maxLength = 0;

    public function __construct($policy,$termId,$actionId,$coverageName)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
        $this->coverageName = $coverageName;
        $this->mainCoverage = CoverageMaster::MainCoverageCode($this->coverageName)->first();
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->coverageName ?? 'NA';
    }
    public function collection()
    {
        $headers = config('constants.excel.edit_policy.coverage.headers');
        $data = [
            $headers
        ];
        $coverages = CoverageMaster::select('s_ScreenName','id',\DB::raw("(SELECT coverage_value FROM policy_coverage WHERE coverage_id = tb_cvgpccoverages.id AND policy_id=".$this->policy->id.
            " AND policy_coverage.term_id='".($this->termId)."'".
            " AND policy_coverage.action_id=".$this->actionId." LIMIT 1)
        as coverage_value"))
            ->ParentCoverageCode($this->coverageName)->orderBy('id')
            ->get()->toArray();

        $entityData = PolicyCoverage::select('entity_id','entity_type')->HaveEntityOnly()->ForPolicy($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)
            ->where('main',$this->mainCoverage->s_CoverageCode)
            ->get();
//        dd($entityData,$this->getRiskAddress());

        $specifiedItems = PolicySpecifiedItem::select("specified_name","sum_insured")
            ->leftjoin("specified_coverage_items","specified_coverage_items.id","specified_coverage_id")
            ->Policy($this->policy->id)->Term($this->termId)->Action($this->actionId)
            ->where('specified_coverage_items.coverage_id',$this->mainCoverage->id)
            ->get()->toArray();

        $this->maxLength = (count($coverages) > count($specifiedItems)) ? count($coverages) : count($specifiedItems);

        for ($i=0; $i < $this->maxLength; $i++){
            $data[($i+1)] = config('constants.excel.edit_policy.coverage.default_value');
        }
        $riskAddresskey=0;
        $Companykey=0;
        $riskAddress = $this->getRiskAddress();
        $company = $this->getCompanies();
        foreach ($entityData as $entity){
            if ($entity->entity_type=="Risk Address"){
                $data[($riskAddresskey+1)][array_search('Risk Address', $headers)] = $riskAddress[$entity->entity_id] ?? '';
                $riskAddresskey++;
            }
            if ($entity->entity_type=="Company"){
                $data[($Companykey+1)][array_search('Associated Company', $headers)] = $company[$entity->entity_id] ?? '';
                $Companykey++;
            }
        }

        foreach ($coverages as $key => $coverage){
            $data[($key+1)][array_search('Coverage', $headers)] = $coverage['s_ScreenName'] ?? '';
            $data[($key+1)][array_search('Limit', $headers)] = $coverage['coverage_value'] ?? '';
        }
        foreach ($specifiedItems as $key => $specifiedItem){
            $data[($key+1)][array_search('Description of Items', $headers)] = $specifiedItem['specified_name'];
            $data[($key+1)][array_search('Sum Insured', $headers)] = $specifiedItem['sum_insured'];
        }
        return new Collection($data);
    }

    public function styles(Worksheet $sheet)
    {
        return config('constants.excel.edit_policy.coverage.style');
    }
    public function columnWidths(): array
    {
        return config('constants.excel.edit_policy.coverage.column_widths');
    }


    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $maxLength = $this->maxLength+2;

                $riskAddress = $this->getRiskAddress();
                $companies = $this->getCompanies();
//                $vehicle = $this->getallVehicles();

                for ($i=1; $i < $maxLength; $i++){
                    ExportTrait::generateDropDown($event,'A'.$i,$riskAddress);
                    ExportTrait::generateDropDown($event,'B'.$i,$companies);
//                    ExportTrait::generateDropDown($event,'C'.$i,$vehicle);
                }
            },
        ];
    }


    public function getRiskAddress(){
        return RiskAddress::select('id','address_name')->Policy($this->policy->id)->term($this->termId)->action($this->actionId)->get()->pluck('address_name','id')->toArray();
    }

    public function getCompanies(){
        return Company::with('subCompanies:name,id')->find($this->policy->profile->company_id)?->subCompanies()?->activated()->get()->pluck('name','id')->toArray() ?? [];
    }

//    public function getallVehicles(){
//        return Vehicle::select('vehiclePlate')->PolicyId($this->policy->id)->TermId($this->termId)->ActionId($this->actionId)->get()->pluck('vehiclePlate')->toArray() ?? [];
//    }
}
