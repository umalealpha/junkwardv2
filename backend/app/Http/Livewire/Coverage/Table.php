<?php

namespace AlphaDirect\Http\Livewire\Coverage;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
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
            SelectFilter::make('Coverage Name')
            ->options(CoverageMaster::select('s_CoverageName','id')->mainCoverageOnly()->orderBy('id','desc')->get()->pluck('s_CoverageName','id')->toArray())
            ->filter(function(Builder $query, string $value) {
                $query->where('id',$value);
            }),
		];
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

            // Column::make('Coverage Name','s_CoverageName','s_CoverageCode')
            //     ->sortable()
            //     ->searchable()
            //     ->html(),

            Column::make('Coverage Name','s_CoverageName')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                return $row->s_CoverageName .' - '.  $row->s_CoverageCode ;
            })->html(),

            // Column::make('Screen Name','s_ScreenName')
            // ->sortable()
            // ->searchable()
            // ->html(),

            Column::make('Rate','n_RateSequence')
            ->sortable()
            ->searchable()
            ->html(),

            Column::make('Rating Method','s_RatingMethod')
            ->sortable()
            ->searchable()
            ->html(),

            Column::make('Effective From','d_EffectiveDt')
                ->sortable()
                ->searchable()
                ->format(function($value, $row) {
                    return \Carbon\Carbon::parse($value)->format('d/m/Y');
               })->html(),

            Column::make('Effective To','d_ExpirationDt')
            ->sortable()
            ->searchable()
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
        return CoverageMaster::select('id','s_CoverageName','s_CoverageCode','n_RateSequence','s_RatingMethod','d_EffectiveDt','d_ExpirationDt')
        ->where('s_CoverageGroupCode','MAIN')->where('s_UsageType','PARENT')->orderBy('id','desc');
    }

    // protected function actionButton($d){
    //     return [
    //         'edit'		=>[
    //             'href'	=>route('coverage.edit',$d->id)
    //         ],
    //         'delete' => [
    //             'arg' => "'deleteRecord',$d->id",
    //             'onClick' => "deleteRow",
    //         ]
    //     ];
    // }

    protected function actionButton($d){
        $action = [];
        if(Auth::user()->hasPermissionTo('edit_coverages')){
            $action['edit'] = [
                'href'	=>route('coverage.edit',$d->id),
            ];
        }
        if(Auth::user()->hasPermissionTo('delete_coverages')){
            $action['delete'] = [
                'arg' => "'deleteRecord',$d->id",
                'onClick' => "deleteRow",
            ];
        }
        return $action;
    }

    public function deleteRecord($id)
    {
        if (CoverageMaster::find($id)->delete()){
             DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Coverage Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        if (CoverageMaster::find($id)->delete()){
            session()->flash('success', "Coverage Deletes Successfully");
            return;
        }
        session()->flash('success', "Coverage Deletes Successfully");
        return;
    }
}
