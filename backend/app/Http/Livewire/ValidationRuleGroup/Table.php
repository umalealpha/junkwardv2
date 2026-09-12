<?php

namespace AlphaDirect\Http\Livewire\ValidationRuleGroup;

use AlphaDirect\Models\ValidationRuleGroupMaster;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Table extends DataTableComponent
{
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
            Column::make('Rule Code','s_RuleCode')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                    return '
					<btton class="btn btn-bg-light btn-color-info w-100">
						'.$value.'
					</btton>
				';
                })
                ->html(),
            Column::make('Rule Description','s_RuleDesc')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Product','product.name')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Action','n_PrValidationRuleGroupMasters_PK')
                ->format(function($value,$row){
                    return view('v2.tables.route-action', [
                        'id' => $row->n_PrValidationRuleGroupMasters_PK,
                        'action'=>$this->actionButton($row)
                    ]);
                })->html(),
        ];
    }

    public function builder(): Builder
    {
        return ValidationRuleGroupMaster::select('s_RuleCode','s_RuleDesc','n_PrValidationRuleGroupMasters_PK')
          ->with('product');
    }

    protected function actionButton($d){
        // dd($d->n_PrValidationRuleGroupMasters_PK);
        return [
            'edit'		=>[
                'href'	=>route('validationrulegroup.edit',$d->n_PrValidationRuleGroupMasters_PK)
            ],
            'delete' => [
                // 'fun' => 'deleteRuleGroup',
                // 'arg' => $d->n_PrValidationRuleGroupMasters_PK
                'arg' => "'deleteRecord',$d->n_PrValidationRuleGroupMasters_PK",
                'onClick' => "deleteRow",
            ]
        ];
    }


    public function deleteRecord($id)
    {
        if (ValidationRuleGroupMaster::find($id)->delete()){
            DB::commit();
                $customer = Customer::where('id', auth()->user()->id)->first();
                activity('Validation Rule Group')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Validation Rule Group Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        if (ValidationRuleGroupMaster::find($id)->delete()){
             
            session()->flash('success', "Validation Rule Group Deletes Successfully");
            return;
        }
        session()->flash('success', "Validation Rule Group Deletes Successfully");
        return;
    }
}
