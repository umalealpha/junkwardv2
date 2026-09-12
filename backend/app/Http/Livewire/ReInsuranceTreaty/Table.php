<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceTreaty;

use AlphaDirect\Models\ReinsuranceTreatyNew;
use AlphaDirect\Models\TreatyDetails;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceTreaty;
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

            Column::make('Treaty Name','treaty_name')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                if ($row->treaty_name){
                   return ucwords($row->treaty_name);
                }else{
                    return '--';
                }
			}),

            Column::make('Treaty Number','treaty_number')
            ->sortable()
            ->searchable()
            ->html(),

            // Column::make('Formula','id')
            // ->sortable()
            // ->searchable()
            // ->format(function($value, $row) {
            //     $formuladetails = TreatyDetails::select('formula_attached')->where('treaty_id',$row->id)->get()->pluck('formula_attached')->toArray();
            //     $formula = ReinsuranceFormula::wherein('id',$formuladetails)
            //     ->get();

            //     $geting = array();
            //     foreach ($formula as $old)
            //     {
            //         array_push($geting, $old->formula_name);
            //     }
            //     return implode(',', $geting) ? implode(',', $geting) : 'NA';
			// }),

            Column::make('Effective from','effective_from')
            ->html(),

            Column::make('Effective To','effective_to')
            ->html(),

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


    public function builder(): Builder
    {
        return ReinsuranceTreaty::select('id','treaty_name','treaty_number','effective_from','effective_to','status','created_at')
        ->orderBy('id','desc');
    }

    protected function actionButton($d){
        return [
            'edit'		=>[
                'href'	=>route('re-insurance-treaty.edit',$d->id)
            ],
            'delete' => [
             // fun' => 'deleteRuleGroup',
                'arg' => "'deleteRecord',$d->id",
                'onClick' => "deleteRow",
            ]
        ];
    }

    public function deleteRecord($id)
    {
        if (ReinsuranceTreaty::find($id)->delete()){
             DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Treaty')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Treaty Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        if (ReinsuranceTreaty::find($id)->delete()){
            session()->flash('success', "Deletes Successfully");
            return;
        }
        session()->flash('success', "Deletes Successfully");
        return;
    }
}
