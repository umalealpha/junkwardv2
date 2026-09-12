<?php

namespace Modules\Inventory\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Modules\Inventory\Entities\Stores;
use Modules\Inventory\Entities\Partners;
use \Modules\Inventory\Entities\WareHouse;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class StoresTable extends DataTableComponent
{

	public bool $columnSelect = true;
    // public string $defaultSortColumn = 'id';
	// public string $defaultSortDirection = 'desc';
    public bool $reorderEnabled = true;
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }
	public $columnSearch = [

	];

    public function columns(): array
    {
        return [

			Column::make('Name')
                ->sortable()
                ->searchable(),

            Column::make('Total Stock','id')
            ->format(function($value, $column, $row) {
                if(($count = $column->inventory()->sum('counter')) > 5){
                    return '<span class="badge badge-success">'.$count.'</span>';
                }else{
                    return '<span class="badge badge-warning">'.$count.'</span>';
                }
            })->html(),

            Column::make('Ware House','wireHouse.name')
            ->sortable(function(Builder $query, $direction) {
                return $query->with(['wireHouse' => function ($q) use($direction){
                    $q->orderBy('name',$direction);
                }]);
            })
            ->searchable(),

			Column::make('Partner', 'partner.name')
                ->sortable(function(Builder $query, $direction) {
					return $query->with(['partner' => function ($q) use($direction){
						$q->orderBy('name',$direction);
					}]);
				})
				->searchable(),

			Column::make('City', 'cities.name')
                ->sortable(function(Builder $query, $direction) {
					return $query->with(['cities' => function ($q) use($direction){
						$q->orderBy('name',$direction);
					}]);
				})
				->searchable(),
			Column::make('Status', 'status')
                ->sortable()
				->format(function($value, $column, $row) {
					if($value==1){
						return '<svg style="color: rgb(52,211,153); width: 1.25rem; height: 1.25rem; margin-right: .375rem;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
							</svg>';
					}else{
						return "<span ><i class='fas fa-times-circle'></i></span>";
					}
				})
				->html(),
			Column::make('Created At','created_at')
				->sortable()
				->format(function($value) {
					return '<strong>'.\Carbon\Carbon::parse($value)->format('d-m-Y H:i').'</strong>';
				})
				->html(),
			Column::make('Action','id')
			->format(function($value, $column, $row) {
				$btn='<div class="row">';
                if($column->status == 1){
                    if(auth()->user()->can('add-stock') || auth()->user()->hasRole(['Super Admin','Stores'])){
                        if($column->warehouses_id){
                            $btn .='<div class="col-sm-3">
                                <a href="'.route('inventory.stores.stock').'"  class="btn btn-sm btn-primary pull-right">
                                    Add Stock
                                </a>
                            </div>';
                        }
                    }

                    if(auth()->user()->can('add-stock') || auth()->user()->hasRole(['Super Admin','Stores'])){
                        if($column->warehouses_id){
                            $btn .='<div class="col-sm-3">
                                <a href="'.route('inventory.stores.removestorestock',\Crypt::encrypt($value)).'"  class="btn btn-sm btn-warning pull-right">
                                    Remove Stock
                                </a>
                            </div>';
                        }
                    }
                }
				if(auth()->user()->can('store-edit') || auth()->user()->hasRole(['Super Admin','Stores'])){
					$btn .= '<div class="col-sm-2">
					<a href="'.route('inventory.stores.edit',\Crypt::encrypt($value)).'"  class="btn btn-sm btn-clean btn-icon btn-icon-md">
								<i class="la la-edit"></i>
							</a>
					</div>';
				}
                #Delete functionality has been removed as per instruction
				// if(auth()->user()->can('store-delete') || auth()->user()->hasRole(['Super Admin','Stores'])){
				// 	$btn .=  '<div class="col-sm-6" x-data="{ confirmDelete:false }">
				// 		<a x-show="!confirmDelete" x-on:click="confirmDelete=true" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete"><i class="fa fa-trash"></i></a>

				// 		<button x-show="confirmDelete" x-on:click="confirmDelete=false" wire:click="delete('.$row->id .')" class="btn btn-success btn-xs"><i class="fa fa-check"></i></button>

				// 		<button x-show="confirmDelete" x-on:click="confirmDelete=false" class="btn btn-danger btn-xs"><i class="fa fa-ban"></i></button>
				// 	</div>';
				// }
				return $btn."</div>";
			})
			->html(),
        ];
    }

	public function filters(): array
    {
        return [

            SelectFilter::make('Wire House')
            ->options([
                ['' => ' - All - '] + WareHouse::whereStatus(1)->pluck('name','id')->toArray()
            ])
            ->filter(function(Builder $query, string $value) {
                $query->where('wirehouse',$value);
            }),

            SelectFilter::make('Partner')
            ->options([
                ['' => ' - All - '] + Partners::whereStatus(1)->pluck('name','id')->toArray()
            ])
            ->filter(function(Builder $query, string $value) {
                $query->where('partner',$value);
            }),

            SelectFilter::make('Active')
            ->options([
                ''  => 'Any',
                '1' => 'Yes',
                '0' => 'No',
            ])
            ->filter(function(Builder $query, string $value) {
                $query->where('active',$value);
            }),

			// 'wirehouse' => Filter::make('Wire House')
            //     ->select(['' => ' - All - '] + WareHouse::whereStatus(1)->pluck('name','id')->toArray()),
            // 'partner' => Filter::make('Partner')
            //     ->select(['' => ' - All - '] + Partners::whereStatus(1)->pluck('name','id')->toArray()),
            // 'active'   => Filter::make('Active')
			// 	->select([
			// 		''  => 'Any',
			// 		'1' => 'Yes',
			// 		'0' => 'No',
			// 	])
		];

    }
	public function builder(): Builder
    {
        return Stores::with(['partner','cities','wireHouse'])
		->when($this->getAppliedFilterWithValue('partner'), fn ($query, $partner_id) => $query->where('partner_id', '=',  $partner_id))
		->when($this->getAppliedFilterWithValue('wirehouse'), fn ($query, $warehouses_id) => $query->where('warehouses_id', '=',  $warehouses_id))
		->when($this->getAppliedFilterWithValue('active'), fn ($query, $active) => $query->where('status', '=',  $active));
    }

	public function setTableRowClass($row): ? string
	{
		if($row->status==0){
			return "bg-danger text-white";
		}
		return null;
	}

	public function delete($id){
		Stores::find($id)->delete();
		$this->dispatchBrowserEvent('alert', [
			'type' => 'success',
			'message' => 'Store Delete Successfully!'
		]);
	}
}
