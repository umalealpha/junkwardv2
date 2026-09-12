<?php

namespace Modules\Incentive\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

use Modules\Incentive\Entities\Incentive;

use Livewire\Component;

class IncentiveTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

	 public function columns(): array
    {
        return [
			Column::make('Incentive Type','incentive_type')
			->sortable()
			->searchable()
			->format(function($value) {
				return config('incentive.incentive_type.'.$value)	?? $value;
			})->html(),
			Column::make('Product Name','product.name')
			->sortable(function(Builder $query, $direction) {
				return $query->with(['product' => function ($q) use($direction){
					$q->orderBy('name',$direction);
				}]);
			})
			->searchable(),
			Column::make('Plan Name','plan.name')
			->sortable(function(Builder $query, $direction) {
				return $query->with(['plan' => function ($q) use($direction){
					$q->orderBy('name',$direction);
				}]);
			})
			->searchable(),

            Column::make('payment_type','payment_type')
			->sortable()
			->searchable(),

			Column::make('Incentive Value','incentive_value')
			->sortable()
			->searchable()
			->format(function($value, $column, $row) {
				if($column->payment_type=='per'){
					return $value." % ";
				}
                else{
                    return "P ".$value;
                }
			})
			->html(),
            Column::make('Status','status')
			->sortable()
			->searchable()
			->format(function($value, $column, $row) {
                if($value ==1){
                    return '<svg style="color: rgb(52,211,153); width: 1.25rem; height: 1.25rem; margin-right: .375rem;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>';
                }else{
                    return "<span ><i class='fas fa-times-circle'></i></span>";
                }
			})
			->html(),
			Column::make('Action','id')
			->format(function($value, $row) {

				$btn='<div class="row">';

                if(auth()->user()->can('incentive-edit') || auth()->user()->hasRole(['Super Admin','Stores'])){
					$btn .= '<div class="col-sm-2">
					<a href="'.route('incentive.settings.edit',\Crypt::encrypt($value)).'"  class="btn btn-sm btn-clean btn-icon btn-icon-md">
								<i class="la la-edit"></i>
							</a>
					</div>';
				}
                if(auth()->user()->can('incentive-delete') || auth()->user()->hasRole(['Super Admin'])){
					$btn .=  '<div class="col-sm-2" x-data="{ confirmDelete:false }">
						<a x-show="!confirmDelete" x-on:click="confirmDelete=true" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete"><i class="fa fa-trash"></i></a>

						<button x-show="confirmDelete" x-on:click="confirmDelete=false" wire:click="delete('.$row->id .')" class="btn btn-success btn-xs"><i class="fa fa-check"></i></button>

						<button x-show="confirmDelete" x-on:click="confirmDelete=false" class="btn btn-danger btn-xs"><i class="fa fa-ban"></i></button>
					</div>';
				}

				return $btn."</div>";
			})
			->html(),
		];
	}

	public function builder(): Builder
    {
        return Incentive::with(['product']);
    }

	public function delete($id){
		Incentive::find($id)->delete();
		$this->dispatchBrowserEvent('alert', [
			'type' => 'success',
			'message' => 'Incentive Deleted Successfully!'
		]);
	}
}
