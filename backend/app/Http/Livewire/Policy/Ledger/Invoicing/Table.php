<?php

namespace AlphaDirect\Http\Livewire\Policy\Ledger\Invoicing;

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

    protected $listeners = [
        'deleteRecord'
    ];

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
            Column::make('INVOICE DT.','accounting_date')
			->sortable(),

            Column::make('INVOICE NO.','invoice_no')
			->sortable()
            ->format(function($value, $row) {
                return '<a href="' . route('admin.policy.getInvoice', $row->id) . '" target="_blank"> ' . $row->invoice_no . '</a>';
            })->html(),

            Column::make('PREMIUM','premium')
            ->sortable()
            ->format(function($value, $row) {
                 return number_format((float)($row->premium), 2, '.', ',');
            })->html(),

            Column::make('OTHER CHARGES','other_charges')
			->sortable()
            ->format(function($value, $row) {
                return number_format((float)($row->other_charges), 2, '.', ',');
            })->html(),

            Column::make('DUE AMOUNT','due_amount')
			->sortable()
            ->format(function($value, $row) {
                return number_format((float)($row->due_amount), 2, '.', ',');
            })->html(),

            Column::make('BALANCE','balance')
			->sortable()
            ->format(function($value, $row) {
                return number_format((float)($row->balance), 2, '.', ',');
            })->html(),

            Column::make('PMTS/ADJUST','pmts_adjust')
			->sortable()
            ->format(function($value, $row) {
                return number_format((float)($row->pmts_adjust), 2, '.', ',');
            })->html(),

            Column::make('INVOICE AMT.','invoice_amount')
			->sortable()
            ->format(function($value, $row) {
                return number_format((float)($row->invoice_amount), 2, '.', ',');
            })->html(),

            Column::make('DUE DATE','due_date')
			->sortable(),
            Column::make('STATUS','status')
			->sortable(),
            Column::make('Action','id')
			->format(function($value,$row){
            //    dd($value,$row);
				return view('v2.tables.route-action', [
					'id' => $row->id,
					'action'=>$this->actionButton($row)
				]);
			})->html(),
        ];
    }

    public function builder(): Builder
    {
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        // $ledger_archive = LedgerArchive::where('policy_id', $id)->whereNotNull('invoice_file')->whereNotNull('invoice_no');
        // $merged = $ledger_archive->merge($ledger_graphite);
        // $ledger = $merged->all();

        $ledgerData = Ledger::where('policy_id', $this->policy->id)
        ->where('action_id', $this->dataShowForActionId)
        // ->where('term_id', $this->termId)
        ->where(function ($query) {
            $query->whereNotNull('invoice_file')->orWhereNotNull('invoice_no');
        });
        return $ledgerData;
    }

    protected function actionButton($d){
        $action = [
            'CR'		=>[
                'href'	=>url('admin/policy/creditNoteView/'.$d->id),'target'=>'_blank',
            ],
        ];
        if($d->status != 'Reversed'){
            $action['delete'] = [
                'arg' => "'deleteRecord',$d->id",
                'onClick' => "deleteRow",
            ];
        }
        return $action;
    }

    public function deleteRecord($id)
    {
        if (Ledger::find($id)->delete()){
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }
}
