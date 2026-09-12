<?php

namespace AlphaDirect\Http\Livewire\Company;

use AlphaDirect\Models\Company;
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
            Column::make('Name','name')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Parent Company','parent_id')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                    return $row->parentCompany->name ?? 'Main' ;
                })
                ->html(),

            Column::make('VAT Number','VAT_registration_number')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Company Registration Number','company_registration_number')
            ->sortable()
            ->searchable()
            ->html(),

            Column::make('status','status')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                return $row->getStatus();
            })
            ->html(),

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
        return Company::with('parentCompany')->orderBy('id','desc');
    }

    protected function actionButton($d){
        return [
            'edit'		=>[
                'href'	=>route('company.edit',$d->id)
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
        if (Company::find($id)->delete()){
           
            DB::commit();
                $customer = Customer::where('id', auth()->user()->id)->first();
                activity('Company Rule')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Company Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

}
