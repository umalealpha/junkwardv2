<?php

namespace AlphaDirect\Http\Livewire\Extentions;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\Extention;
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

    public function filters(): array
    {
        return [
            SelectFilter::make('Coverage Name')
            ->options(CoverageMaster::select('s_CoverageName','id')->mainCoverageOnly()->orderBy('n_DisplaySequence','desc')->get()->pluck('s_CoverageName','id')->toArray())
            ->filter(function(Builder $query, string $value) {
               $query->where('s_ParentCoverageID',$value);
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

            Column::make('Extention Name','s_CoverageName')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                return $row->s_CoverageName?? '' .' - '.  $row->s_CoverageCode?? '' ;
            })->html(),

            Column::make('Coverage Name','s_ParentCoverageID')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
                $c_name = CoverageMaster::where('id',$row->s_ParentCoverageID)->first('s_CoverageName');
                return $c_name->s_CoverageName?? '';
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
        return Extention::select('id','s_ParentCoverageID','s_CoverageName','s_CoverageCode','s_RatingMethod','d_EffectiveDt','d_ExpirationDt','s_ParentCoverageCode','s_CoverageCode');

    }


    protected function actionButton($d){
        $action = [];
        if(Auth::user()->hasPermissionTo('edit_sub_coverages')){
            $action['edit'] = [
                'href'	=>route('extentions.edit',\Crypt::encrypt($d->s_CoverageCode)),
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
        if (Extention::find($id)->delete()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Extention')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Extention Deleted - '.$id);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

}
