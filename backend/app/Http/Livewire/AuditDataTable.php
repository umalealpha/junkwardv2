<?php

namespace AlphaDirect\Http\Livewire;

use Livewire\Component;
use AlphaDirect\Ledger;
use AlphaDirect\Models\Audits;
use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Spatie\Activitylog\Models\Activity;
use AlphaDirect\Activity as AlphaDirectActivity;


class AuditDataTable  extends DataTableComponent
{
    public Policy $policy;
    public $policydetails;

    // public function render()
    // {
    //     return view('v2.livewire.audit-data-table');
    // }
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
            Column::make('Old Value','old_values')
			->sortable()->searchable()
            ->format(function($value, $row) {
                return $json_string = json_encode(json_decode($row->old_values), JSON_PRETTY_PRINT);
            })->html(),   
            Column::make('New Value','new_values')
			->sortable()
            ->format(function($value, $row) {
                return $json_string = json_encode(json_decode($row->new_values), JSON_PRETTY_PRINT);
            })->html(),           
            Column::make('Subject Type','auditable_type')
			->sortable(),
            Column::make('Action','tags')
			->sortable(),
            Column::make('Action By','user_id')
            ->sortable()
            ->format(function($value, $row) {
                if (isset($row->user_id)) {
                    $name = User::where('id',$row->user_id)->first(['firstName','lastName']);
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
        
        $activity = Audits::orderBy('created_at', 'desc')
        ->where(function ($query) {
            $query->Where('policy_id',$this->policy->id);
        });
        return $activity;
    }

}
