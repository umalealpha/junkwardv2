<?php

namespace AlphaDirect\Http\Livewire\SubCoverage;

use AlphaDirect\Models\CoverageMaster;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectFilter;
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
    // public function configure(): void
    // {
    //     $this->setPrimaryKey('id');
    //     $this->setFilterLayout('slide-down');

    //     SelectFilter::make('Active')
    //     ->setFilterPillTitle('User Status')
    //     ->setFilterPillValues([
    //         '1' => 'Active',
    //         '0' => 'Inactive',
    //     ])
    //     ->options([
    //         '' => 'All',
    //         '1' => 'Yes',
    //         '0' => 'No',
    //     ]);
    // }

    // public function filters(): array
    // {
    //     return [
    //         // SelectFilter::make('Sub Coverage Name')
    //         // ->options(CoverageMaster::select('s_CoverageCode','s_CoverageCode')->mainCoverageOnly()->get()->pluck('s_CoverageCode','s_CoverageCode')->toArray())
    //         // ->filter(function(Builder $query, string $value) {
    //         //     $query->where('s_ParentCoverageCode',$value);
    //         // }),

    //         SelectFilter::make('Coverage Name')
    //         ->options(['All'  => 'All']+CoverageMaster::select('s_CoverageCode','id')->get()->pluck('s_CoverageCode','id')->toArray())
    //         ->filter(function(Builder $query, string $value) {
    //             $query->where('s_ParentCoverageCode',$value);
    //         }),


    //         // MultiSelectFilter::make('tb_cvgpccoverages')
    //         // ->options(
    //         //     CoverageMaster::query()
    //         //         ->orderBy('s_CoverageCode')
    //         //         ->get()
    //         //         ->keyBy('id')
    //         //         ->map(fn($tag) => $tag->name)
    //         //         ->toArray()
    //         // ),
	// 	];
    // }

    public function filters(): array
    {
        return [
            SelectFilter::make('Coverage Name')
            ->options(CoverageMaster::select('s_CoverageName','id')->mainCoverageOnly()->orderBy('n_DisplaySequence','desc')->get()->pluck('s_CoverageName','id')->toArray())
            ->filter(function(Builder $query, string $value) {
                $query->where('id',$value);
            }),
		];
    }

    public function columns(): array
    {
        return [
            Column::make('Sr. No.','id')
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

            Column::make('Coverage Name','s_CoverageName')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                return $row->s_CoverageName .' - '.  $row->s_CoverageCode ;
            })->html(),

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
        return CoverageMaster::select('id','s_CoverageName','s_CoverageCode','n_RateSequence','s_RatingMethod','d_EffectiveDt','d_ExpirationDt','s_ParentCoverageCode','s_CoverageCode')
        ->where('s_CoverageGroupCode','MAIN')->where('s_UsageType','PARENT')->orderBy('n_DisplaySequence','desc');
        // return CoverageMaster::select('id','s_CoverageName','s_RatingMethod','d_EffectiveDt','d_ExpirationDt','s_ParentCoverageCode','s_CoverageCode')
        // ->whereNotNull('s_ParentCoverageCode')->where('s_UsageType', 'CHILD')->orderBy('id','desc');
    }

    // protected function actionButton($d){
    //     return [
    //         'edit'		=>[
    //             'href'	=>route('sub-coverage.edit',\Crypt::encrypt($d->s_CoverageCode))
    //         ],
    //         'delete' => [
    //             'arg' => "'deleteRecord',$d->id",
    //             'onClick' => "deleteRow",
    //         ]
    //     ];
    // }

    protected function actionButton($d){
        $action = [];
        if(Auth::user()->hasPermissionTo('edit_sub_coverages')){
            $action['edit'] = [
                'href'	=>route('sub-coverage.edit',\Crypt::encrypt($d->s_CoverageCode)),
            ];
        }
        if(Auth::user()->hasPermissionTo('delete_sub_coverages')){
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
            activity('Sub Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Sub Coverage Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

}
