<?php

namespace AlphaDirect\Http\Livewire\Policy\Reinsurance;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\PolicyReinsurance;
use AlphaDirect\Services\Reinsurance\CessionSource;
use DB;
class Table extends DataTableComponent
{

    public $policy_id;
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
                // Column::make('Id','id')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //                 return '
                //                 <btton  class="btn btn-bg-light btn-color-info w-100">
                //                     '.$value.'
                //                 </btton>
                //             ';
                //         })
                //         ->html(),

                Column::make('Risk Id','address_name')
                        ->sortable()
                        ->searchable()
                        ->format(function($value, $row) {
                            return $value ?? null;
                        }),

                // Column::make('Product Name','product_id')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             if (!empty($row->product) && !empty($row->product->name)) {
                //                 $productName = $row->product->name;
                //             }
                //             return $productName ?? null;
                //         }),

                // Column::make('Group Name','group_code')
                //             ->sortable()
                //             ->searchable()
                //             ->format(function($value, $row) {
                //                 return $value ?? null;
                //             }),

                // Column::make('Formula Name','formula_id')
                //             ->sortable()
                //             ->searchable()
                //             ->format(function($value, $row) {
                //                 if (!empty($row->formula) && !empty($row->formula->formula_name)) {
                //                     $formulaName = $row->formula->formula_name;
                //                 }
                //                 return $formulaName ?? null;
                //             }),

                // Column::make('Coverage Name','coverage_id')
                //             ->sortable()
                //             ->searchable()
                //             ->format(function($value, $row) {
                //                 if (!empty($row->coverage) && !empty($row->coverage->s_CoverageCode)) {
                //                     $coverageName = $row->coverage->s_CoverageCode;
                //                 }
                //                 return $coverageName ?? null;
                //             }),

                // Column::make('Reinsurance Treaty','treaty_id')
                //             ->sortable()
                //             ->searchable()
                //             ->format(function($value, $row) {
                //                 if (!empty($row->treaty) && !empty($row->treaty->treaty_name)) {
                //                     $treatyName = $row->treaty->treaty_name;
                //                 }
                //                 return $treatyName ?? null;
                //             }),

                // Column::make('Total Sum Insured','totalSumInsured')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             return number_format((float)$value ?? "", 2, '.', ',');
                //         })
                //         ->html(),

                // Column::make('Total Premium','totalPremium')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             return number_format((float)$value ?? "", 2, '.', ',');
                //         })
                //         ->html(),

                // Column::make('Reinsurance Type','type_id')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             if (!empty($row->type) && !empty($row->type->type_name)) {
                //                 $reinsuranceName = $row->type->type_name;
                //             }
                //             return $reinsuranceName ?? null;
                //         })
                //         ->html(),

                // Column::make('NETRETENTION','NETRETENTION')
                // ->sortable()
                // ->searchable()
                // ->format(function($value, $row) {
                //     return number_format((float)$value ?? "", 2, '.', ',');
                // })
                // ->html(),
                // Column::make('QUOTASHARING','QUOTASHARING')
                // ->sortable()
                // ->searchable()
                // ->format(function($value, $row) {
                //     return number_format((float)$value ?? "", 2, '.', ',');
                // })
                // ->html(),
                // Column::make('SURPLUS','SURPLUS')
                // ->sortable()
                // ->searchable()
                // ->format(function($value, $row) {
                //     return number_format((float)$value ?? "", 2, '.', ',');
                // })
                // ->html(),
                // Column::make('FACULATATIVE','FACULATATIVE')
                // ->sortable()
                // ->searchable()
                // ->format(function($value, $row) {
                //     return number_format((float)$value ?? "", 2, '.', ',');
                // })
                // ->html(),

                // Column::make('Treaty SI','treatySI')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             return number_format((float)$value ?? "", 2, '.', ',');
                //         })
                //         ->html(),

                // Column::make('Treaty Percentage','treatyPercentage')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             return number_format((float)$value ?? "", 2, '.', ',');
                //         })
                //         ->html(),

                // Column::make('Treaty Premium','treatyPremium')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             return number_format((float)$value ?? "", 2, '.', ',');
                //         })
                //         ->html(),

                // Column::make('Used Formula','UsedFormula')
                //         ->sortable()
                //         ->searchable()
                //         ->html(),

                // Column::make('Added By','added_by')
                //         ->sortable()
                //         ->searchable()
                //         ->format(function($value, $row) {
                //             if (!empty($row->user) && !empty($row->user->firstName)) {
                //                 $userName = $row->user->firstName.' '.$row->user->lastName;
                //             }
                //             return $userName ?? null;
                //         }),

                // Column::make('Created Date','created_at')
                //         ->sortable()
                //         ->format(function($value, $row) {
                //              return \Carbon\Carbon::parse($value)->format('d/m/Y');
                //         }),

        ];
    }


    public function builder(): Builder
    {

        // THE QUERY THAT WAS HERE NOW LIVES IN CessionSource, MOVED NOT REWRITTEN.
        // It reached straight into policy_reinsurance, which meant this tab could
        // only ever show the legacy basis; the seam decides which basis is live
        // and this follows it. On the legacy basis the two are identical --
        // checked row by row against the original across all 41 policies that
        // carry cession rows, zero differences -- so nothing here changes until
        // the routing sign-off arrives and the flag moves.
        //
        // On the regulatory basis formula_name comes back null, because a
        // regulatory layer is produced by the treaty structure and not by a
        // formula. Blank is the honest answer; a plausible value would trace to
        // nothing.
        return app(CessionSource::class)->layerRowsForPolicy((int) $this->policy_id);
    }


}
