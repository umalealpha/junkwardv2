<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceFormula;

use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceType;
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
            Column::make('ID','id')
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

            Column::make('Product Name','product_id')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                $product = Product::where('id', $row->product_id)->first(array('name'));
               return $product ? $product->name : '';
            }),

            Column::make('Formula Name','formula_name')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                return $value ?? null;
            }),

            Column::make('Reinsurance Type','reinsurance_type_id')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                $reinsuranceType = ReinsuranceType::where('id', $row->reinsurance_type_id)->first(array('type_name'));
                return $reinsuranceType ? $reinsuranceType->type_name : '';
            }),

            Column::make('Type','type_id')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                $type = Lookup::where('id', $row->type_id)->first(array('value'));
                return $type ? $type->value : '';
            }),

            Column::make('Status','status')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                $status = '';
                if ($row->status != null) {
                    $status = '<span class="badge badge-success">Active</span>';
                }else{
                    $status = '<span class="badge badge-info">In-Active</span>';
                }
                return $status;
            })->html(),

            Column::make('Created Date','created_at')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            }),

           Column::make('Action','id')
           ->format(function($value,$row){
               return view('v2.tables.route-action', [
                   'id' => $row->id,
                   'action'=>$this->actionButton($row)
               ]);
           })->html(),
        ];
    }

    public function builder(): Builder
    {
        return ReinsuranceFormula::select('id','formula_name','formula_code','product_id','reinsurance_type_id','type_id','status','created_at')
        ->orderBy('id','desc');
    }

    protected function actionButton($d){
        return [
            'edit'		=>[
                'href'	=>route('re-insurance-formula.edit',$d->id)
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
        if (ReinsuranceFormula::find($id)->delete()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Formula Deleted')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Formula Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        if (ReinsuranceFormula::find($id)->delete()){
            session()->flash('success', "Deletes Successfully");
            return;
        }
        session()->flash('success', "Deletes Successfully");
        return;
    }
}
