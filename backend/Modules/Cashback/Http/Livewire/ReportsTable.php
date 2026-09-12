<?php

namespace Modules\Cashback\Http\Livewire;

use AlphaDirect\Customer;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Livewire\Component;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;

class ReportsTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

	public function columns(): array
    {
        return [
			Column::make('Customer','firstName')
			->sortable()->format(function($value,$row) {
				return $row->firstName.' '.$row->lastName;
			})
			->searchable(),

			// Column::make('No. of Cashbacks','customer_count'),
            Column::make('No. of Cashbacks','id')
            ->format(function($value, $row) {
                return "P ". count($row->customer_cashback);
            }),

            Column::make('Total Amount','id as a_id')
            ->format(function($value, $row) {
                return "P ". $row->customer_cashback->sum('reward_points');
            }),
		];
	}

    public function builder(): Builder
    {
        // return Customer::leftJoin('customer_cashback', 'customer_cashback.customer_id', 'customer.id')
		// ->selectRaw('customer.firstName, customer.lastName, count(customer_cashback.id) as customer_count, sum(customer_cashback.reward_points) as amount')
		// ->where('customer_cashback.status', '=', 1)
		// ->when($this->getAppliedFilterWithValue('fromdate'), fn ($query, $fromdate) => $query->where('customer_cashback.reward_date','>=',$fromdate))
	 	// ->when($this->getAppliedFilterWithValue('todate'), fn ($query, $todate) => $query->where('customer_cashback.reward_date','<=',$todate))
		// ->groupBy('customer.id');

        return Customer::with(['customer_cashback' => function ($q) {
            $q->Activated();
          }])
        ->when($this->getAppliedFilterWithValue('customer_cashback.fromdate'), fn ($query, $fromdate) => $query->where('customer_cashback.reward_date','>=',$fromdate))
        ->when($this->getAppliedFilterWithValue('customer_cashback.todate'), fn ($query, $todate) => $query->where('customer_cashback.reward_date','<=',$todate))
        ->groupBy('customer.id');
    }


	public function filters(): array
	{
        return [
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
		];
	}

}
