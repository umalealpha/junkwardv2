<?php

namespace Modules\Inventory\Http\Livewire;
use AlphaDirect\Models\Audits;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Modules\Inventory\Entities\Incentive;

class ActivitylogTable extends DataTableComponent
{
	public $model = \App\Models\Audits::class;

    // public function builder()
    // {
    //     $data= Audits::query()->join('customer',function($j){
	// 		$j->on('customer.id', '=', 'audits.user_id');
	// 	});
	// 	return $data;
    // }
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

	public function columns(): array
    {
        return [
            Column::make('Id','id')->sortable()->searchable(),
            Column::make('Caused By','id')
            ->format(function($value,$row) {
                if($row->user_data == !null){
                    return $row->user_data->firstName.' '.$row->user_data->lastName;
                }
                else{
                    return 'System';
                }
            })->sortable()->searchable(),
			Column::make('Activity Note','tags')->sortable()->searchable(),
            Column::make('Old Values', 'old_values')
            ->format(function($old_values) {
                    return $json_string = json_encode(json_decode($old_values), JSON_PRETTY_PRINT);
            })->sortable()->searchable(),
            Column::make('New Values', 'new_values')
            ->format(function($new_values) {
                    return $json_string = json_encode(json_decode($new_values), JSON_PRETTY_PRINT);
            })->sortable()->searchable(),
            Column::make('Activity Type','event')->sortable()->searchable(),
            Column::make('IP Address', 'ip_address')->sortable()->searchable(),
            Column::make('Created At','created_at')->sortable()->format(function($value) {
				return '<strong>'.\Carbon\Carbon::parse($value)->format('d-m-Y H:i').'</strong>';
				})
			->html(),
		];
	}

    public function builder(): Builder
    {
        $searchTerm = "Inventory";
        $searchTerm1 = "store";
        $searchTerm2 = "warehouse";
        $searchTerm3 = "partners";
        $searchTerm4 = "stores";
        $searchTerm5 = "inventories";

        return Audits::query()->with('user_data')
        ->where('auditable_type', $searchTerm)
        ->orWhere('auditable_type', $searchTerm1)
        ->orWhere('auditable_type', $searchTerm2)
        ->orWhere('auditable_type', $searchTerm3)
        ->orWhere('auditable_type', $searchTerm4)
        ->orWhere('auditable_type', $searchTerm5)
        ->orderBy('id', 'desc');

        // return Audits::query()->with(['user'])
        // ->where('auditable_type', 'LIKE', "%{$searchTerm}%")
        // ->orWhere('auditable_type', 'LIKE', "%{$searchTerm1}%")
        // ->orWhere('auditable_type', 'LIKE', "%{$searchTerm2}%")
        // ->orWhere('auditable_type', 'LIKE', "%{$searchTerm3}%")
        // ->orWhere('auditable_type', 'LIKE', "%{$searchTerm4}%")
        // ->orWhere('auditable_type', 'LIKE', "%{$searchTerm5}%")
        // ->orderBy('id', 'desc');
    }

    // public function setTableRowClass($row): ? string
	// {
	// 	if($row->user_id == null){
	// 		return "bg-danger text-white";
	// 	}
	// 	return null;
	// }

}
