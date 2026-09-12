<?php

namespace Modules\Inventory\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Entities\Stores;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Modules\Inventory\Entities\WareHouse;

class WareHouseTable extends DataTableComponent
{

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }
    public function columns(): array
    {
        return [
			Column::make('Name','name')
                ->sortable()
                ->searchable(),
            Column::make('Total Stock','id')
                ->format(function($value,$row,$query) {
					if(($count = $row->inventory()->sum('counter')) > 5){
						return '<span class="badge badge-success">'.$count.'</span>';
					}else{
						return '<span class="badge badge-warning">'.$count.'</span>';
					}
				})->html(),
            Column::make('E-mail', 'email_id')
                ->sortable()
                ->searchable(),
            Column::make('Mobile', 'mobile')
                ->sortable(),
			Column::make('Status', 'status')
				->format(function($value) {
					if($value==1){
						return '<svg style="color: rgb(52,211,153); width: 1.25rem; height: 1.25rem; margin-right: .375rem;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
							</svg>';
					}else{
						return "<span ><i class='fas fa-times-circle'></i></span>";
					}
				})
                ->sortable()
				->html(),
			Column::make('Action', 'id')
				->format(function($value) {
					return '<div class="row">
						<div class="col-sm-3">
							<a href="'.route('inventory.warehouse.stock',$value).'"  class="btn btn-sm btn-primary pull-right">
								Add Stock
							</a>
						</div>
						<div class="col-sm-2">
							<a href="'.route('inventory.warehouse.edit',\Crypt::encrypt($value)).'"  class="btn btn-sm btn-clean btn-icon btn-icon-md ">
								<i class="la la-edit"></i>
							</a>
						</div>
						<div class="col-sm-7" x-data="{ confirmDelete:false }">
							<a x-show="!confirmDelete" x-on:click="confirmDelete=true" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete"><i class="fa fa-trash"></i></a>

							<button x-show="confirmDelete" x-on:click="confirmDelete=false" wire:click="delete('.$value.')" class="btn btn-success btn-xs"><i class="fa fa-check"></i></button>

							<button x-show="confirmDelete" x-on:click="confirmDelete=false" class="btn btn-danger btn-xs"><i class="fa fa-ban"></i></button>
						</div>

					</div>';
				})
				->html(),
        ];
    }

	public function builder(): Builder
    {
        return WareHouse::query();
        // return WareHouse::select('id','name','contact_person','status','email_id','mobile','status','deleted_at')
        // ->orderBy('id','desc');
    }

	public function delete($id){
            if($store = Stores::where('warehouses_id',$id)->exists()){
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'warning',
                    'message' => 'Can not be deleted Store(s) have been already assigned to this warehouse!'
                ]);
            }
            else{
                WareHouse::find($id)->delete();
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'success',
                    'message' => 'Warehouse deleted Successfully!'
                ]);
            }
	}

	public function setTableRowClass($row): ? string
	{
		if($row->status==0){
			return "bg-danger text-white";
		}
		return null;
	}
}
