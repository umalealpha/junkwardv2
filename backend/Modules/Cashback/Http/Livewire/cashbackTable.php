<?php

namespace Modules\Cashback\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Modules\Cashback\Entities\Cashback;

use Livewire\Component;

class CashbackTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

	 public function columns(): array
    {
        return [
			Column::make('Cashback Type','cashback_type')
			->sortable()
			->searchable()
			->format(function($value) {
				return config('cashback.cashback_type.'.$value)	?? $value;
			}),
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
			Column::make('Cashback Value','cashback_value')
			->sortable()
			->searchable()
			->format(function($value, $row) {
				if($row->payment_type=='per'){
					return $value." % ";
				}
                else{
                    return "P ".$value;
                }
			}),
			Column::make('Action','id')
			->format(function($value, $row) {
				$btn='<div class="row">';
                if(auth()->user()->can('cashback-edit') || auth()->user()->hasRole(['Super Admin','Stores'])){
					$btn .= '<div class="col-sm-2">
					<a href="'.route('cashback.settings.edit',\Crypt::encrypt($value)).'"  class="btn btn-sm btn-clean btn-icon btn-icon-md">
								<i class="la la-edit"></i>
							</a>
					</div>';
				}
                if(auth()->user()->can('cashback-delete') || auth()->user()->hasRole(['Super Admin'])){
					$btn .=  '<div class="col-sm-2" x-data="{ confirmDelete:false }">
						<a x-show="!confirmDelete" x-on:click="confirmDelete=true" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete"><i class="fa fa-trash"></i></a>

						<button x-show="confirmDelete" x-on:click="confirmDelete=false" wire:click="delete('.$row->id .')" class="btn btn-success btn-xs"><i class="fa fa-check"></i></button>

						<button x-show="confirmDelete" x-on:click="confirmDelete=false" class="btn btn-danger btn-xs"><i class="fa fa-ban"></i></button>
					</div>';
				}
				return $btn."</div>";
			}),
		];
	}

    public function builder(): Builder
	// public function query(): Builder
    {
        // dd(Cashback::with(['product'])->first());
        return Cashback::with(['product']);
    }

	public function delete($id){
		Cashback::find($id)->delete();
		$this->dispatchBrowserEvent('alert', [
			'type' => 'success',
			'message' => 'Cashback Deleted Successfully!'
		]);
	}
}
