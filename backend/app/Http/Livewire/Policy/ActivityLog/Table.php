<?php

namespace AlphaDirect\Http\Livewire\Policy\ActivityLog;

use AlphaDirect\Activity as AlphaDirectActivity;
use AlphaDirect\Ledger;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Spatie\Activitylog\Models\Activity;

class Table extends DataTableComponent
{
    public Policy $policy;
    public $policydetails;

    public function boot(): void
    {
        config(['livewire-tables.theme' => 'bootstrap-5']);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make('Id','id')
			->sortable()->searchable(), 
            Column::make('Log Name','log_name')
			->sortable()->searchable(),          
            Column::make('Description','description')
			->sortable()
            ->format(function($value, $row) {
                if($row->description == 'Invoice has been created' || $row->description == 'Policy Reinsurance has been created')
                {
                    $propertiesData = AlphaDirectActivity::where('id',$row->id)->first(['properties']);
                    return $propertiesData->properties;
                }
                else
                {
                    return $row->description;  
                }
            })->html(),           
            Column::make('Subject Type','subject_type')
			->sortable(),
            Column::make('Action By','causer_id')
            ->sortable()
            ->format(function($value, $row) {
                if (isset($row->causer_id)) {
                    $name = User::where('id',$row->causer_id)->first(['firstName','lastName']);
                    return $name->firstName.' '.$name->lastName;
                } else {
                    return 'Not Found';
                }
            })->html(),

            Column::make('Created At','created_at')
            ->sortable()
            ->format(function($value, $row) {
                return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
            })->html(),

        ];
    }

    public function builder(): Builder
    {
        $activity = AlphaDirectActivity::orderBy('created_at', 'desc')
        ->where(function ($query) {
            $query->Where('subject_id',$this->policy->id);
        });
        return $activity;
    }
}
