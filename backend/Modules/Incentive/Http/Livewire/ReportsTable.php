<?php

namespace Modules\Incentive\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Modules\Incentive\Entities\IncentiveAgent;
use Livewire\Component;
use Modules\Incentive\Entities\Agents;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class ReportsTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

	public function columns(): array
    {
        return [
			Column::make('Agent','firstName')
			->sortable()->format(function($value,$column, $row) {
				return $column->firstName.' '.$column->lastName;
			})
			->searchable(),

            Column::make('No. of Incentives','id')
            ->format(function($value, $row) {
                return "P ". count($row->incentiveAgent);
            }),

			// Column::make('No. of Incentives','count'),

            Column::make('Total Amount','id as a_id')
            ->format(function($value, $row) {
                return "P ". $row->incentiveAgent->sum('amount');
            })->html(),
		];
	}

	public function builder(): Builder
    {
		// return Agents::leftJoin('incentive_agent', 'incentive_agent.agent_id', 'users.id')
		// ->selectRaw('users.firstName, users.lastName, count(incentive_agent.id) as count, sum(incentive_agent.amount) as amount')
		// ->whereNotNull('users.agency_id')->where('incentive_agent.status', '=', 1)
		// ->when($this->getAppliedFilterWithValue('fromdate'), fn ($query, $fromdate) => $query->where('incentive_agent.created_at','>=',$fromdate))
	 	// ->when($this->getAppliedFilterWithValue('todate'), fn ($query, $todate) => $query->where('incentive_agent.created_at','<=',$todate))
		// ->groupBy('users.id');

        return Agents::with(['incentiveAgent' => function ($q) {
            $q->Activated();
          }])
        ->whereNotNull('users.agency_id')
        ->when($this->getAppliedFilterWithValue('fromdate'), fn ($query, $fromdate) => $query->where('incentive_agent.created_at','>=',$fromdate))
        ->when($this->getAppliedFilterWithValue('todate'), fn ($query, $todate) => $query->where('incentive_agent.created_at','<=',$todate))
        ->groupBy('users.id');
	}

	public function filters(): array
	{
		return [
            // SelectFilter::make('From Date')
            // ->options([
            //     'min' => now()->subYear()->format('Y-m-d'),
            //     'max' => now()->format('Y-m-d')
            // ])
            // ->filter(function(Builder $query, string $value) {
            //     $query->where('fromdate',$value);
            // }),

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
		];
	}

}
