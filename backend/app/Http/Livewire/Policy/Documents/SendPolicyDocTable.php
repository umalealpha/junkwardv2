<?php

namespace AlphaDirect\Http\Livewire\Policy\Documents;

use AlphaDirect\Ledger;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use AlphaDirect\sentPolicyDocuments;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class SendPolicyDocTable extends DataTableComponent
{
    public Policy $policy;
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
            Column::make('ID','id')
			->sortable(),
            Column::make('Policy Number','policyNumber')
			->sortable(),
            Column::make('Email','email')
			->sortable(),
            Column::make('Email Type','doc')
			->sortable(),
            Column::make('Documents','documents')
			->sortable(),
            Column::make('Sent By','sentBy')
			->sortable(),
            Column::make('Sent on','created_at')
            ->sortable()
            ->format(function($value, $row) {
                    return \Carbon\Carbon::parse($value)->format('d/m/Y');
            })->html(),
        ];
    }

    public function builder(): Builder
    {
        return sentPolicyDocuments::where('policyNumber', $this->policy->policyNumber);
    }
}
