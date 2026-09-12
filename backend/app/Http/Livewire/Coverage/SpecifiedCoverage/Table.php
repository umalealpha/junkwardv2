<?php

namespace AlphaDirect\Http\Livewire\Coverage\SpecifiedCoverage;

use AlphaDirect\Models\SpecifiedCoveragesItems;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\CoverageMaster;
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
            SelectFilter::make('Coverage Name')
            ->options(CoverageMaster::select('s_CoverageName','id')->mainCoverageOnly()->get()->pluck('s_CoverageName','id')->toArray())
            ->filter(function(Builder $query, string $value) {
                $query->where('coverage_id',$value);
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

            Column::make('Coverage','coverage.s_CoverageName')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Coverage Code','coverage.s_CoverageCode')
            ->sortable()
            ->searchable()
            ->html(),

            Column::make('Specified Name','specified_name')
                ->sortable()
                ->searchable()
                ->html(),
            Column::make('Rate','rate')
                ->sortable()
                ->searchable()
                ->html(),

            Column::make('Effective From','effective_from')
            ->sortable()
            ->format(function($value, $row) {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            })->html(),

            Column::make('Effective To','effective_to')
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
        return SpecifiedCoveragesItems::select('specified_coverage_items.id','specified_coverage_items.specified_code','specified_coverage_items.specified_name','specified_coverage_items.coverage_id','specified_coverage_items.rate','specified_coverage_items.effective_from','specified_coverage_items.effective_to','specified_coverage_items.added_by')
        ->with('coverage')->orderBy('specified_coverage_items.id','desc');

            // ->leftjoin('tb_cvgpccoverages','specified_coverage_items.coverage_id','tb_cvgpccoverages.id')->orderBy('id','desc');
            // ->when($this->getFilter('coverage_id'), fn ($query, $id) => $query->where('specified_coverage_items.coverage_id', $id));
    }

    protected function actionButton($d){
        return [
            'edit'		=>[
                'href'	=>route('specifiedcoverage.edit',$d->id)
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
        if (SpecifiedCoveragesItems::find($id)->delete()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Specified Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Specified Coverage Deleted - '.SpecifiedCoveragesItems::find($id)->specified_name);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

    public function deleteRuleGroup($id)
    {
        if (SpecifiedCoveragesItems::find($id)->delete()){
            session()->flash('success', "Specified Coverage Deletes Successfully");
            return;
        }
        session()->flash('error', "Something Went Wrong");
        return;
    }
}
