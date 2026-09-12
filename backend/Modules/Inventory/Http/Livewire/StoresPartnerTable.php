<?php

namespace Modules\Inventory\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Modules\Inventory\Entities\Partners;


class StoresPartnerTable extends DataTableComponent
{

	public bool $columnSelect = true;
    // public string $defaultSortColumn = 'id';
	// public string $defaultSortDirection = 'desc';
    public bool $reorderEnabled = true;
	public bool $viewingModal = false;
	public $partner;

	protected $rules = [
		'partner.name' => '',
		'partner.status' => '',
	];

	public $columnSearch = [

	];
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }
	public function bulkActions(): array
	{
		return [
			'activate'   => __('Activate'),
			'deactivate' => __('Deactivate'),
		];
	}

    public function columns(): array
    {
        return [
			Column::make('Partner Name','name')
                ->sortable()
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
				->asHtml(),
			Column::make('Created At','created_at')
				->sortable()
				->format(function($value) {
					return '<strong>'.\Carbon\Carbon::parse($value)->format('d-m-Y H:i').'</strong>';
				})
				->asHtml(),
			Column::make('Action','id')
			->format(function($value) {
				$btn='<div class="row">';
				if(auth()->user()->can('store-partner-edit') || auth()->user()->hasRole(['Super Admin','Stores'])){
					$btn .= '<div class="col-sm-3">
					<a wire:click="edit('.$value.')" class="btn btn-sm btn-clean btn-icon btn-icon-md" >
							<i class="la la-edit"></i>
						</a>
					</div>';
				}
				if(auth()->user()->can('store-partner-delete') || auth()->user()->hasRole(['Super Admin','Stores'])){
					$btn .=  '<div class="col-sm-7" x-data="{ confirmDelete:false }">
						<a x-show="!confirmDelete" x-on:click="confirmDelete=true" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete"><i class="fa fa-trash"></i></a>

						<button x-show="confirmDelete" x-on:click="confirmDelete=false" wire:click="delete('.$value .')" class="btn btn-success btn-xs"><i class="fa fa-check"></i></button>

						<button x-show="confirmDelete" x-on:click="confirmDelete=false" class="btn btn-danger btn-xs"><i class="fa fa-ban"></i></button>
					</div>';
				}
				return $btn."</div>";
			})
			->asHtml(),
        ];
    }

	public function filters(): array
    {
        return [
			'active'   => Filter::make('Active')
				->select([
					''  => 'Any',
					'1' => 'Yes',
					'0' => 'No',
				])
		];

    }
	public function query(): Builder
    {
        return Partners::when($this->getFilter('active'), fn ($query, $active) => $query->where('status', '=',  $active));
    }

	public function setTableRowClass($row): ? string
	{
		if($row->status==0){
			return "bg-danger text-white";
		}
		return null;
	}

	public function delete($id){
		Partners::find($id)->delete();
		$this->dispatchBrowserEvent('alert', [
			'type' => 'success',
			'message' => 'Delete Successfully!'
		]);
	}


	public function activate(){
		Partners::whereIn('id',$this->selectedKeys)->update(['status'=>1]);
		$this->dispatchBrowserEvent('alert', [
			'type' => 'success',
			'message' => 'Selected Partner are marked as Active.'
		]);
	}

	public function deactivate(){
		Partners::whereIn('id',$this->selectedKeys)->update(['status'=>0]);
		$this->dispatchBrowserEvent('alert', [
			'type' => 'success',
			'message' => 'Selected Partner are marked as DeActive.'
		]);
	}

	public function edit($id){
		$this->partner= \Modules\Inventory\Entities\Partners::findOrFail($id);
		$this->dispatchBrowserEvent('openPartnerMode');
	}


	public function modalsView(): string
	{
		return 'inventory::partner';
	}


	#save partner
	public function savePartner(){
		$this->validate(
		[
			'partner.name' => 'required|unique:store_partners,name,'.$this->partner->id,
			'partner.status' => 'required'
		]);
		\DB::transaction(function () {
			$this->partner->save();
			$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Partner Updated Successfully!']);
		}, 5);
		$this->dispatchBrowserEvent('partnerAdded');
	}
}
