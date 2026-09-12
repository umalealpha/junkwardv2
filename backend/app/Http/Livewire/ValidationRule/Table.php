<?php

namespace AlphaDirect\Http\Livewire\ValidationRule;

use AlphaDirect\Models\ValidationRuleMaster;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
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

    public function filters(): array
    {
        return [

            SelectFilter::make('status')
                ->options([
                    ''  => 'Any',
                    'ACTIVE' => 'Activated',
                    'INACTIVE' => 'Inactivated',
                ])
                ->filter(function(Builder $query, string $value) {
                    $query->where('status',$value);
                }),
        ];

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
            Column::make('Rule Description','s_Description')
                ->sortable()
                ->searchable()
                ->html(),
                // ->html()->addAttributes(['style' => 'width:30%']),
            Column::make('Rule Apply On','s_RuleApplyOn')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Rule Start Date','d_EffectiveDateFrom')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Rule End Date','d_EffectiveDateTo')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Status','s_RuleStatus')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Action','n_PrValidationRuleMaster_PK')
                ->format(function($value,$row){
                    return view('v2.tables.route-action', [
                        'id' => $row->n_PrValidationRuleMaster_PK,
                        'action'=>$this->actionButton($row)
                    ]);
                })->html(),
        ];
    }

    public function builder(): Builder
    {
        return ValidationRuleMaster::select('*')
            ->when($this->getAppliedFilterWithValue('status'),
                fn($query, $status) => $query->where('tb_prvalidationrulemasters.s_RuleStatus', $status)
            );
    }

    protected function actionButton($d){
        return [
            'edit'		=>[
                'href'	=>route('validationrule.edit',$d->n_PrValidationRuleMaster_PK)
            ],
            'delete' => [
                // 'fun' => 'deleteRule',
                // 'arg' => $d->n_PrValidationRuleMaster_PK
                'arg' => "'deleteRecord',$d->n_PrValidationRuleMaster_PK",
                'onClick' => "deleteRow",
            ]
        ];
    }

    public function deleteRecord($id)
    {
        if (ValidationRuleMaster::find($id)->delete()){
             DB::commit();
                $customer = Customer::where('id', auth()->user()->id)->first();
                activity('Validation Rule')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Validation Rule Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRule($id)
    {
        if (ValidationRuleMaster::find($id)->delete()){
            session()->flash('success', "Validation Rule Deletes Successfully");
            return;
        }
        session()->flash('success', "Validation Rule Deletes Successfully");
        return;
    }
}
