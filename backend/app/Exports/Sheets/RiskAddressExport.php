<?php

namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Http\Traits\Excel\ExportTrait;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class RiskAddressExport implements FromQuery,WithHeadings,WithTitle,WithColumnWidths,WithMapping,WithEvents
{
    public Policy $policy;
    public $termId;
    public $actionId;

    public function __construct($policy, $termId = null, $actionId = null)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function query()
    {
        $query = RiskAddress::where('policy_id', $this->policy->id)
            ->with('company:id,name')
            ->select('company_id', 'address_name', 'physical_address');

        // Endorsements do NOT replicate risk_address rows (replication is commented
        // out in PolicyAction::newPolicyActionEndorse). So the latest action_id may
        // have zero risk addresses. We fall back to the most-recent action that DOES
        // have addresses — identical to the fallback used in editData().
        if ($this->actionId !== null) {
            $hasForAction = RiskAddress::where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->exists();

            if ($hasForAction) {
                $query->where('action_id', $this->actionId);
            } else {
                // Fall back: latest action_id that has addresses for this policy
                $fallbackActionId = RiskAddress::where('policy_id', $this->policy->id)
                    ->whereNotNull('action_id')
                    ->max('action_id');

                if ($fallbackActionId) {
                    $query->where('action_id', $fallbackActionId);
                }
                // else: no action filter — return all risk addresses for the policy
            }
        } elseif ($this->termId !== null) {
            $query->where('term_id', $this->termId);
        }

        return $query;
    }

    public function headings(): array
    {
        return ['Associated Company','Address','Physical Address'];
    }
    public function map($riskAddress): array
    {
        if (!$riskAddress) {
            return ['', '', ''];
        }
        
        return [
            $riskAddress->company->name ?? '',
            $riskAddress->address_name ?? '',
            $riskAddress->physical_address ?? ''
        ];
    }

    public function title(): string
    {
        return "Risk Address";
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 30,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $maxLength = 100;
                $companies = $this->getAllSubCompanies();
                for ($i=1; $i < $maxLength; $i++){
                    ExportTrait::generateDropDown($event,'A'.$i,$companies);
                }
            },
        ];
    }
    public function getAllSubCompanies(){
        return Company::with('subCompanies:name,id')->find($this->policy->profile->company_id)?->subCompanies()?->activated()->get()->pluck('name','id')->toArray() ?? [];
    }
}
