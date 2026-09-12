<?php

namespace AlphaDirect\Http\Livewire\Policy\Documents;
use AlphaDirect\Models\GetPolicyDocuments;
use AlphaDirect\Policy;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Illuminate\Database\Eloquent\Builder;
class PolicyDocTable extends DataTableComponent
{
    public Policy $policy;
    public function boot(): void
    {
        config(['livewire-tables.theme' => 'bootstrap-5']);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }
    public function columns(): array
    {
        return [
            Column::make('ID','id')
			->sortable(),
            Column::make('Policy Number','policyNumber')
			->sortable(),
            Column::make('Term','term_id')
			->sortable(),
            Column::make('Documents','doc_path')
			->sortable(),
            Column::make('File name','file_name')
			->sortable(),
            Column::make('Added by','added_by')
			->sortable(),
            Column::make('Created At','created_at')
			->sortable(),
            Column::make('Created At','created_at')
            ->sortable()
            ->format(function($value, $row) {
                    return \Carbon\Carbon::parse($value)->format('d/m/Y');
            })->html(),
        ];
    }

    public function builder(): Builder
    {
        return  GetPolicyDocuments::where('policyNumber', $this->policy->policyNumber);
    }
}
