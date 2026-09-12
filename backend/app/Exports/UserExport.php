<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Agency;
use AlphaDirect\Stores;
use AlphaDirect\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Symfony\Component\HttpKernel\Profiler\Profile;

class UserExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    use Exportable;
    private $headings = [
        'ID',
        'First Name',
        'Last Name',
        'Email',
        'Commission',
        'Role',
        'Status'
    ];

    public function map($id): array
    {
        $user = User::where('id', $id)->first();

        $firstName = ($user->firstName != null) ? $user->firstName : '-';
        $lastName = ($user->lastName != null) ? $user->lastName : '-';

        $profile = Profile::where('user_id',$user->id)->first();

        $email = ($profile->email != null) ? $profile->email : '-';
        $cellphone = ($profile->cellphone != null) ? $profile->cellphone : '-';

        $agencyData = Agency::where('id',$user->agency_id)->first();
        $agencyName = ($agencyData->name != null) ? $agencyData->name : '-';




        return [


        ];
    }

    public function collection()
    {
        $query = User::get();
        return $query->pluck('id');
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
