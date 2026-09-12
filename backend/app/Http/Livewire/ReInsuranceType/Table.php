<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceType;

use Livewire\Component;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\ReinsuranceType;
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
            Column::make('Id','id')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                    return '
					<btton  class="btn btn-bg-light btn-color-info w-100">
						'.$value.'
					</btton>
				';
                })
                ->html(),

            Column::make('Type Code','type_code')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Type Name','type_name')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Status','status')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                    return $row->getStatus();
                })
                ->html(),

            Column::make('Created Date','created_at')
                ->sortable()
                ->format(function($value, $row) {
                    return \Carbon\Carbon::parse($value)->format('d/m/Y');
               })->html(),

            Column::make('Action','id')
                ->format(function($value,$row){
                    return view('v2.tables.route-action', [
                        'id' => $row->id,
                        'action'=>$this->actionButton($row)
                    ]);
                })->html(),
        ];
    }

    //table query
    public function builder(): Builder
    {
        return ReinsuranceType::select('id','type_code','type_name','status','created_at')->orderBy('id','desc');
    }


    protected function actionButton($d){
        return [
            'edit'		=>[
                'href'	=>route('reinsurance-type.edit',$d->id)
            ],
            'delete' => [
                // 'fun' => 'deleteRuleGroup',
                // 'arg' => $d->id
                'arg' => "'deleteRecord',$d->id",
                'onClick' => "deleteRow",
            ]
        ];
    }

    public function deleteRecord($id)
    {
        if (ReinsuranceType::find($id)->delete()){
            DB::commit();
                $customer = Customer::where('id', auth()->user()->id)->first();
                activity('Re-Insurance Type')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Type Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        dd('here');
        if (ReinsuranceType::find($id)->delete()){
            session()->flash('success', "Re-Insurance type has been successfully deleted.");
            return;
        }
        session()->flash('success', "Re-Insurance type been has successfully deleted.");
        return;
    }


}
