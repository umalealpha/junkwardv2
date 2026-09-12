<?php

namespace Modules\Incentive\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Modules\Incentive\Entities\IncentiveAgent;
use Livewire\Component;
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
			Column::make('Agent','agent.firstName')
			->sortable(function(Builder $query, $direction) {
				return $query->with(['agent' => function ($q) use($direction){
					$q->orderBy('firstName',$direction);
				}]);
			})->format(function($value,$column, $row) {
				//dd($row);
				// return $row->agent->name;
                return $value;
			})
			->searchable(),

			Column::make('Policy Number','policy')
			->sortable()
			->searchable(),

            Column::make('Premium','premium')
			->sortable()
			->searchable()
            ->format(function($val, $column, $row) {
                return "P ". $val;
            })->html(),

			Column::make('Product','product.name'),
			Column::make('Plan','plan.name'),

			Column::make('Incentive Type','incentive_type')
			->sortable()
			->searchable()
			->format(function($value) {
				return config('incentive.incentive_type.'.$value)	?? $value;
			})->html(),

            Column::make('Payment Type','payment_type')
			->sortable()
			->searchable()
			 ->format(function($value, $column, $row) {
                 if($value == 'fixed'){
			 		return " Fixed";
			 	}
                 return " Percentage (%)";
			})->html(),

            Column::make('Incentive Value','incentive_value')
			->sortable()
			->searchable()
			->format(function($value) {
				return config('incentive.incentive_value.'.$value)	?? $value;
			})->html(),

			// Column::make('Incentive Value','incentiveType.incentive_value')
			//  ->sortable(function(Builder $query, $direction) {
			//  	return $query->with(['incentiveType' => function ($q) use($direction){
			//  		$q->orderBy('incentive_value',$direction);
			//  	}]);
			// })->searchable()
			//  ->format(function($value, $column, $row) {
            //     return $value;
			//  })->asHtml(),

            // Column::make('Payout (%/Fixed)','incentiveType.payment_type')
			//  ->sortable(function(Builder $query, $direction) {
		 	// return $query->with(['incentiveType' => function ($q) use($direction){
			//  		$q->orderBy('payment_type',$direction);
			//  	}]);
			//  })->searchable()
            //  ->format(function($value, $column, $row) {
            //      if($value == 'fixed'){
			//  		return " Fixed";
			//  	}
            //      return " Percentage (%)";
            //  })->asHtml(),

            Column::make('Amount','amount')
			->sortable()
			->searchable()
            ->format(function($val, $column, $row) {
                return "P ".$val;
            })->html(),

            Column::make('Status', 'status')
            ->format(function($value) {
                if($value==1){
                    return '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px;padding: 9px;width: 80px;">Success</span>';
                }else{
                    return '<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px;padding: 9px;width: 80px;">Failed</span>';
                }
            })
            ->sortable()
            ->html(),
		];
	}

	public function builder(): Builder
    {
    //    return IncentiveAgent::with(['agent','incentiveType','policyDetails'])
	// 	->when($this->getFilter('fromdate'), fn ($query, $fromdate) => $query->where('incentive_agent.created_at','>=',$fromdate))
	// 	->when($this->getFilter('todate'), fn ($query, $todate) => $query->where('incentive_agent.created_at','<=',$todate))
	// 	->when($this->getFilter('type'), fn($query, $type) => $query->where('incentive_agent.payment_type', $type))->orderBy('id', 'desc');

    return IncentiveAgent::with(['agent','incentiveType','policyDetails'])
		->when($this->getAppliedFilterWithValue('fromdate'), fn ($query, $fromdate) => $query->where('incentive_agent.created_at','>=',$fromdate))
		->when($this->getAppliedFilterWithValue('todate'), fn ($query, $todate) => $query->where('incentive_agent.created_at','<=',$todate))
		->when($this->getAppliedFilterWithValue('type'), fn($query, $type) => $query->where('incentive_agent.payment_type', $type))->orderBy('id', 'desc');
	}

	public function filters(): array
	{
		return [
                SelectFilter::make('Incentive Type')
                ->options([
                    ''      => 'Any',
                    'per'   => 'Percentage',
                    'fixed' => 'Fixed',
                ])
                ->filter(function(Builder $query, string $value) {
                    $query->where('type',$value);
                }),

			'fromdate' => DateFilter::make('From Date')
            ->config([
                'min' => now()->subYear()->format('Y-m-d'),
                'max' => now()->format('Y-m-d')
            ]),

			'todate' => DateFilter::make('To Date')
            ->config([
                'min' => now()->subYear()->format('Y-m-d'),
                'max' => now()->format('Y-m-d')
            ]),

                // SelectFilter::make('From Date')
                // ->options([
                //     'min' => now()->subYear()->format('Y-m-d'),
                //     'max' => now()->format('Y-m-d')
                // ])
                // ->filter(function(Builder $query, string $value) {
                //     $query->where('fromdate',$value);
                // }),

                // SelectFilter::make('To Date')
                // ->options([
                //     'min' => now()->subYear()->format('Y-m-d'),
                //     'max' => now()->format('Y-m-d')
                // ])
                // ->filter(function(Builder $query, string $value) {
                //     $query->where('todate',$value);
                // }),

                // 'type' => Filter::make('Incentive Type')
                // ->select([
                // 	'' => 'Any',
                // 	'per'=> 'Percentage',
                // 	'fixed' => 'Fixed',
                // ]),

		];
	}

}
