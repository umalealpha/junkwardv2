<?php

namespace AlphaDirect\Http\Livewire\Policy\EarnedPremium;

use AlphaDirect\EarnPremium;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\PolicyReinsurance;
use AlphaDirect\Models\TbEarnedpremiumDaypremiummaster;

class Table extends DataTableComponent
{

    public $policy;
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
                Column::make('DATE','d_StartDate')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                        return \Carbon\Carbon::parse($value)->format('d/m/Y');
                })->html(),

                Column::make('TRANS. TYPE','s_TransactionType')
                        ->sortable()
                        ->searchable()
                        ->html(),

                Column::make('# DAYS','n_NoOfDays')
                        ->sortable()
                        ->searchable()
                        ->html(),

                Column::make('WRIT. PREM.','n_WrittenPremium')
                        ->sortable()
                        ->searchable()
                        ->html(),

                Column::make('DAY PREM.','n_DayPremium')
                        ->sortable()
                        ->searchable()
                        ->html(),
        ];
    }


    public function builder(): Builder
    {
        return TbEarnedpremiumDaypremiummaster::select('policy_id','policyNumber','s_TransactionType','n_WrittenPremium','action_id','d_StartDate','n_NoOfDays','n_DayPremium')->where('policy_id', $this->policy->id)->orderByRaw("d_StartDate ASC, policy_id DESC");
    }


}
