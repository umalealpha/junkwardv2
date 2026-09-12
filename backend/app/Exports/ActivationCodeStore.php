<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\Activation;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use AlphaDirect\Vendor;
use AlphaDirect\Branch;
use Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class ActivationCodeStore implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    protected $noofcodes;

    private $headings = [
        'Serial Code',
        'Activation Code',
        'Product Name',
        'Plan Name',
        'Type Name',
        'Vendor Name',
        'Branch Name',
        'Rack No.',
        'Trial Periods',
        'Trial Coverage',
        'City',
        'State',
        'Country',
        'Status',
    ];


    function __construct($noofcodes) {

        $this->noofcodes = $noofcodes;
    }

    public function map($id): array
    {
        $codes = Activation::orderBy('id', 'desc')->take($this->noofcodes)->get();
        $item = Activation::orderBy('id', 'desc')->take($this->noofcodes)->first();

        $product = Product::where('id', $item->product_id)->first(array('name'));
        if ($product != null) {
            $product_name = $product->name;
        } else {
            $product_name = null;
        }
        $plan = Productplan::where('id', $item->product_plan_id)->first(array('name'));
        if ($plan != null) {
            $plan_name = $plan->name;
        } else {
            $plan_name = null;
        }
        $type = ProductType::where('id', $item->product_type_id)->first(array('name'));
        if ($type != null) {
            $type_name = $type->name;
        } else {
            $type_name = null;
        }
        $vendor = Vendor::where('id', $item->vendor)->first(array('name'));
        if ($vendor != null) {
            $vendor_name = $vendor->name;
        } else {
            $vendor_name = null;
        }
        $branch = Branch::where('id', $item->branch)->first(array('name'));
        if ($type != null) {
            $branch_name = $branch->name;
        } else {
            $branch_name = null;
        }
        if ($item->status == 0) {
            $status = 'Deactivated';
        } else {
            $status = 'Activated';
        }
        foreach ($codes as $item2) {

        $serial_code = $item2->serial_code;
        $activation_code = $item2->activation_code;
        $rack_no = $item2->rack_no;
        $trial_periods = $item2->trial_periods;
        $trial_coverage = $item2->trial_coverage;
        $city = $item2->city;
        $state = $item2->state;
        $country = $item2->country;

        $x[] =  [
            $serial_code,
            $activation_code,
            $product_name,
            $plan_name,
            $type_name,
            $vendor_name,
            $branch_name,
            $rack_no,
            $trial_periods,
            $trial_coverage,
            $city,
            $state,
            $country,
            $status,
        ];
         }
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $codes = Activation::orderBy('id', 'desc')->take(1)->get();
        $codes = $codes->sortBy('id');

        return $codes;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
