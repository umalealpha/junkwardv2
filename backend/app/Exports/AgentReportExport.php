<?php

namespace AlphaDirect\Exports;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AgentReportExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
                                'Agency Name ',
                                'Account ID',
                                'PhoneNo.',
                                'FaxNo',
                                'Principal Name',
                                'Principal Email',
                                'Product',
                                'UnderWriter',
                                'ServiceRep',
                                'UserName',
                                'Admin_ID',
                                'AgentName',
                                'Agent Email',
                                'UserStatus',
                                'User Type',
                                's_AddressLine1',
                                'AddressLine2',
                                'City',
                                'Country ',
                                'Postal Code',
                                'Auth. Code',
                                's_LicenseNo',
    ];

    protected $filter;

    function __construct($filter) {

        $this->filter = $filter;
    }

    public function map($id): array
    {
        $userProfile = UserProfile::where('id',$id)->first();
        if($userProfile->id !=null){
            $agency_name = '-';
        }
        else{
            $agency_name = 'N/A';
        }
        if($userProfile->id !=null){
            $account_id = '-';
        }
        else{
            $account_id = 'N/A';
        }
        if($userProfile->id !=null){
            $cellphone = $userProfile->cellphone;
        }
        else{
            $cellphone = 'N/A';
        }
        if($userProfile->id !=null){
            $fax_no = '-';
        }
        else{
            $fax_no = 'N/A';
        }
        if($userProfile->id !=null){
            $user = User::where('id',$userProfile->user_id)->first();
            $principal_name = $user->firstName;
        }
        else{
            $principal_name = 'N/A';
        }
        if($userProfile->id !=null){
            $user = User::where('id',$userProfile->user_id)->first();
            $principal_email = $user->email;
        }
        else{
            $principal_email = 'N/A';
        }
        if($userProfile->id !=null){
            $product = '-';
        }
        else{
            $product = 'N/A';
        }
        if($userProfile->id !=null){
            $underwriter = '-';
        }
        else{
            $underwriter = 'N/A';
        }
        if($userProfile->id !=null){
            $service_rep = '-';
        }
        else{
            $service_rep = 'N/A';
        }
        if($userProfile->id !=null){
            $user = User::where('id',$userProfile->user_id)->first();
            $user_name = $user->email;
        }
        else{
            $user_name = 'N/A';
        }
        if($userProfile->id !=null){
            $user_id = $userProfile->user_id;
        }
        else{
            $user_id = 'N/A';
        }
        if($userProfile->id !=null){
            $agent_name = '-';
        }
        else{
            $agent_name = 'N/A';
        }
        if($userProfile->id !=null){
            $agent_email = '-';
        }
        else{
            $agent_email = 'N/A';
        }
        if($userProfile->id !=null){
            $user = User::where('id',$userProfile->user_id)->first();
            if($user->active == 1){
                $user_status = 'Active';
            }
            else{
                $user_status = 'N/A';
            }
        }
        else{

        }
        if($userProfile->id !=null){
            $user_type = '-';
        }
        else{
            $user_type = 'N/A';
        }
        if($userProfile->id !=null){
            $s_address_line1 = $userProfile->address;
        }
        else{
            $s_address_line1 = 'N/A';
        }
        if($userProfile->id !=null){
            $address_line2 = '-';
        }
        else{
            $address_line2 = 'N/A';
        }
        if($userProfile->id !=null){
            $city = '-';
        }
        else{
            $city = 'N/A';
        }
        if($userProfile->id !=null){
            $county = '-';
        }
        else{
            $county = 'N/A';
        }
        if($userProfile->id !=null){
            $postal_code = '-';
        }
        else{
            $postal_code = 'N/A';
        }
        if($userProfile->id !=null){
            $auth_code = '-';
        }
        else{
            $auth_code = 'N/A';
        }
        if($userProfile->id !=null){
            $s_license_no = '-';
        }
        else{
            $s_license_no = 'N/A';
        }
        return [
            $agency_name,
            $account_id,
            $cellphone,
            $fax_no,
            $principal_name,
            $principal_email,
            $product,
            $underwriter,
            $service_rep,
            $user_name,
            $user_id,
            $agent_name,
            $agent_email,
            $user_status,
            $user_type,
            $s_address_line1,
            $address_line2,
            $city,
            $county,
            $postal_code,
            $auth_code,
            $s_license_no,
        ];
    }


    public function collection()
    {

        $query = UserProfile::orderBy('created_at', 'DESC');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        $query = $query->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
