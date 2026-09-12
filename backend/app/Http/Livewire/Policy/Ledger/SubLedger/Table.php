<?php

namespace AlphaDirect\Http\Livewire\Policy\Ledger\SubLedger;

use AlphaDirect\Models\SubledgerArchive;
use AlphaDirect\Policy;
use AlphaDirect\SubLedger;
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
            Column::make('SYSTEM DATE','accounting_date')
			->sortable(),
            Column::make('TRANS TYPE','trans_type')
			->sortable(),
            Column::make('TRANS REF','trans_ref')
			->sortable(),
            Column::make('ACCOUNT NAME','account_name')
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
        ];
    }

    public function builder(): Builder
    {
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        // $subledger_graphite = SubLedger::where('policy_id', $this->policy_id)->whereNotNull('policy_id');
        // $subledger_archive = SubledgerArchive::where('policy_id', $this->policy_id)->get();
        // $merged = $subledger_archive->merge($subledger_graphite);
        // $ledger = $merged->all();
        $ledgerData = SubLedger::where('policy_id', $this->policy->id)
        ->where('action_id', $this->dataShowForActionId)
        // ->where('term_id', $this->termId)
        ->where(function ($query) {
            $query->whereNotNull('policy_id');
        });
        return $ledgerData;
    }

}
