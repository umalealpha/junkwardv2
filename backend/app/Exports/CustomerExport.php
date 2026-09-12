<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Helpers\PiiMask;
use AlphaDirect\KYC;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Stores;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class CustomerExport implements WithHeadings,WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;
    private $headings = [
        'Customer ID',
        'Customer Name',
        'Date of birth',
        'Omang',
        'Passport',
        'Marital Status',
        'Address',
        'Cellphone',
        'Gender',
        'Created at',
    ];

    public function map($id): array
    {
        $customer = Customer::where('id',$id)->first();
        $profile = CustomerProfile::where('customer_id',$id)->first();

        if($customer && $customer->id)
            $id = $customer->id;
        else
            $id = 'NA';

        if($customer && $customer->firstName)
            $fname = $customer->firstName;
        else
            $fname = 'N/A';

        if($customer && $customer->lastName)
            $lname = $customer->lastName;
        else
            $lname = 'N/A';

        if($customer && $customer->email)
            $email = $customer->email;
        else
            $email = 'N/A';

        if($customer && $customer->cellphone)
            $cell = $customer->cellphone;
        else
            $cell = 'N/A';

        if($profile && $profile->omang)
            $omang = $profile->omang;
        else
            $omang = 'N/A';

        if($profile && $profile->passport)
            $passport = $profile->passport;
        else
            $passport = 'N/A';

        if($profile && $profile->gender)
            $gender = $profile->gender;
        else
            $gender = 'N/A';

        if($profile && $profile->dob)
            $dob = $profile->dob;
        else
            $dob = 'N/A';

        if($profile && $profile->maritalstatus)
            $maritalstatus = $profile->maritalstatus;
        else
            $maritalstatus = 'N/A';

        if($profile && $profile->address)
            $address = $profile->address;
        else
            $address = 'N/A';

        return [
            $id,
            $fname.' '.$lname,
            PiiMask::ifHidden($dob),
            PiiMask::ifId($omang),
            PiiMask::ifId($passport),
            $maritalstatus,
            PiiMask::ifHidden($address),
            $cell,
            $gender,
            $customer->created_at

        ];
    }

    public function collection()
    {
        $query = Customer::orderBy('id','DESC')->get();
        return $query->pluck('id');
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
