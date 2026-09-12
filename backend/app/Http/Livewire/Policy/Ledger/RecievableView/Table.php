<?php

namespace AlphaDirect\Http\Livewire\Policy\Ledger\RecievableView;

use AlphaDirect\Ledger;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class Table extends DataTableComponent
{
    public Policy $policy;
    public $actionId;
    public $termId;
    public $previousActionId;
    public $dataShowForActionId;

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
            Column::make('ACCOUNTING DT.','accounting_date')
			->sortable(),
            Column::make('TRANS TYPE','trans_type')
			->sortable(),
            Column::make('TRANS-SUB-TYPE','trans_sub_type')
			->sortable(),
            Column::make('TRANS REF','trans_ref')
			->sortable(),
            Column::make('EFF DATE','eff_date')
			->sortable(),

            Column::make('DEBIT','debit')
			->sortable()
            ->format(function($value, $row) {
                 return number_format((float)($row->debit), 2, '.', ',');
            })->html(),

            Column::make('CREDIT','credit')
			->sortable()
            ->format(function($value, $row) {
                 return number_format((float)($row->credit), 2, '.', ',');
            })->html(),

            Column::make('BALANCE','balance')
			->sortable()
            ->format(function($value, $row) {
                 return number_format((float)($row->balance), 2, '.', ',');
            })->html(),
        ];
    }

    public function builder(): Builder
    {
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        // $ledger_graphite = Ledger::where('policy_id', $this->policy_id)->whereNotNull('credit');
        // $ledger_archive = LedgerArchive::where('policy_id', $this->policy_id)->whereNotNull('credit');
        // $merged = $ledger_archive->merge($ledger_graphite);
        // $ledger = $merged->all();
        $ledgerData = Ledger::where('policy_id', $this->policy->id)
        ->where('action_id', $this->dataShowForActionId)
        // ->where('term_id', $this->termId)
        ->where(function ($query) {
            $query->whereNotNull('credit');
        });
        return $ledgerData;
    }
}
