<?php

namespace Modules\Cashback\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Modules\Cashback\Entities\CustomerCashback;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
class AgentsTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

	public function columns(): array
    {
        return [
			Column::make('Sr. No.','id'),
			Column::make('Customer','customer.firstName')
			->sortable(function(Builder $query, $direction) {
				return $query->with(['customer' => function ($q) use($direction){
					$q->orderBy('firstName',$direction);
				}]);
			})->format(function($value,$row) {
                if(isset($row->customer) && isset($row->customer->firstName) && isset($row->customer->lastName)){
				    return ($row->customer->firstName.' '.$row->customer->lastName);
                }
			})
			->searchable(),
			Column::make('Policy Number','policy')
			->sortable()
			->searchable(),
			Column::make('Premium','premium')->format(function($val, $row) {
                return "P ".$val;
            }),
            Column::make('Reward Points','reward_points'),
            Column::make('Reward Date','reward_date'),
			Column::make('Product','product.name'),
			Column::make('Plan','plan.name'),
            // Column::make('Customer','policyDetails.premium'),
			Column::make('Cashback Type','cashback_type')
			->sortable()
			->searchable()
			->format(function($value) {
				return config('cashback.cashback_type.'.$value)	?? $value;
			}),
			// Column::make('Cashback Value','cashbackType.cashback_value')
			// ->sortable(function(Builder $query, $direction) {
			// 	return $query->with(['cashbackType' => function ($q) use($direction){
			// 		$q->orderBy('cashback_value',$direction);
			// 	}]);
			// })
			// ->searchable()
			// ->format(function($value, $column, $row) {
            //     return $value;
			// })->asHtml(),
            // Column::make('Payout (%/Fixed)','cashbackType.payment_type')
			// ->sortable()
			// ->searchable()
            // ->format(function($value, $column, $row) {
            //     if($value == 'fixed'){
			// 		return " Fixed";
			// 	}
            //     else{
            //         return " Percentage (%)";
            //     }
            // })->asHtml(),
            // Column::make('Amount','amount')
			// ->sortable()
			// ->searchable()
            // ->format(function($val, $column, $row) {
            //     return "P ".$val;
            // })->asHtml(),
            Column::make('Status', 'status')
            ->format(function($value) {
                if($value==1){
                    return '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px;padding: 9px;width: 80px;">Success</span>';
                }else{
                    return '<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px;padding: 9px;width: 80px;">Failed</span>';
                }
            })
            ->sortable(),
		];
	}

    public function builder(): Builder
    {
        return CustomerCashback::with(['customer','cashbackType','policyDetails'])
		->when($this->getAppliedFilterWithValue('fromdate'), fn ($query, $fromdate) => $query->where('customer_cashback.created_at','>=',$fromdate))
		->when($this->getAppliedFilterWithValue('todate'), fn ($query, $todate) => $query->where('customer_cashback.created_at','<=',$todate))
		->when($this->getAppliedFilterWithValue('type'), fn($query, $type) => $query->where('customer_cashback.payment_type', $type))->orderBy('id', 'desc');
    }


	// public function query(): Builder
    // {
    //    return CustomerCashback::with(['customer','cashbackType','policyDetails'])
	// 	->when($this->getFilter('fromdate'), fn ($query, $fromdate) => $query->where('customer_cashback.created_at','>=',$fromdate))
	// 	->when($this->getFilter('todate'), fn ($query, $todate) => $query->where('customer_cashback.created_at','<=',$todate))
	// 	->when($this->getFilter('type'), fn($query, $type) => $query->where('customer_cashback.payment_type', $type))->orderBy('id', 'desc');

	// }

	public function filters(): array
	{
		return [
			'type' => SelectFilter::make('Cashback Type')
			->options([
				'' => 'Any',
				'per'=> 'Percentage',
				'fixed' => 'Fixed',
			]),

            'fromdate' => DateFilter::make('From Date')
            ->config([
                'min' => now()->subYear()->format('Y-m-d'),
                'max' => now()->format('Y-m-d')
            ]),

            'todate' => DateFilter::make('To Date')
            ->config([
                'min' => now()->subYear()->format('Y-m-d'),
                'max' => now()->format('Y-m-d')
            ])

			// 'fromdate' => SelectFilter::make('From Date')
            // ->date([
            //     'min' => now()->subYear()->format('Y-m-d'),
            //     'max' => now()->format('Y-m-d')
            // ]),
			// 'todate' => SelectFilter::make('To Date')
            // ->date([
            //     'min' => now()->subYear()->format('Y-m-d'),
            //     'max' => now()->format('Y-m-d')
            // ]),
		];
	}

}
