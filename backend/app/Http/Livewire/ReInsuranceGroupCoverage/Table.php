<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceGroupCoverage;

use Livewire\Component;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\ReinsuranceType;
use AlphaDirect\Models\ReinsuranceGroup;
use Carbon\Carbon;
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

            Column::make('Product','product_id')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                    if (!empty($row->product) && !empty($row->product->name)) {
                       $name = $row->product->name;
                    }
                    return $name ?? null;
                }),

            Column::make('Group Code','group_code')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Group Name','group_name')
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
                    // return $value;
                    return Carbon::parse($value)->format('d/m/Y');
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
        return ReinsuranceGroup::select('id','group_name','product_id','group_code','status','created_at')->orderBy('id','desc');
    }

    protected function actionButton($d){
        return [
            'edit'=>[
                    'href'	=>route('reinsurance-group-coverage.edit',$d->id)
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
        if (ReinsuranceGroup::find($id)->delete()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Group Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Group Coverage Deleted - '.$id);
                
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        if (ReinsuranceGroup::find($id)->delete()){
            session()->flash('success', "Re-Insurance group coverage has been successfully deleted.");
            return;
        }
        session()->flash('success', "Re-Insurance group coverage been has successfully deleted.");
        return;
    }
}
