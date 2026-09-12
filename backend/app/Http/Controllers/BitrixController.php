<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Carbon\Carbon;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use AlphaDirect\Role;
use AlphaDirect\UserRole;
use AlphaDirect\Department;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Spatie\Activitylog\Models\Activity;
use GuzzleHttp\Client;
use Validator;
use Session;
use Auth;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Storage;
use function GuzzleHttp\json_decode;

class BitrixController extends Controller
{
    public function getUsers()
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', env('GUZZLE_URL_USERS'));
         $Users = json_decode($response->getBody());
                
         foreach($Users->result as $User){
            $UserRow = new User();
            $UserRow->bitrixId = $User->ID;
            $User->ACTIVE = $User->ACTIVE == '' ? '0' : $User->ACTIVE;
            $UserRow->active = $User->ACTIVE;
            $UserRow->firstName = $User->NAME;
            $UserRow->lastName = $User->LAST_NAME;
            $UserRow->email = $User->EMAIL;
             
            $object = $UserRow::updateOrCreate(
                [   'bitrixId'=> $UserRow->bitrixId ],['firstName'=>$UserRow->firstName,'lastName'=>$UserRow->lastName,
                    'email'=> $UserRow->email, 'bitrixId'=> $UserRow->bitrixId,'active' => $UserRow->active
                ]
            );

             $UserRow->assignRole("Agent");

            $UserProfileRow = new UserProfile();
            $UserProfileRow->user_id = $object->id;
            $departmentStr = implode($User->UF_DEPARTMENT,",");
            $UserProfileRow->department_id = $departmentStr;
            $UserProfileRow->dob =   date('Y-m-d', strtotime($User->PERSONAL_BIRTHDAY)); 
            $UserProfileRow->gender = $User->PERSONAL_GENDER;
            $UserProfileRow->address = $User->PERSONAL_STREET.','.$User->PERSONAL_CITY.','.$User->PERSONAL_STATE.','.$User->PERSONAL_ZIP;
            $UserProfileRow->cellphone = $User->PERSONAL_PHONE;
            $UserProfileRow->profile_photo = $User->PERSONAL_PHOTO;
            $UserProfileRow->work_position = $User->WORK_POSITION;
            $UserProfileRow->uf_department = $departmentStr;
            $UserProfileRow->uf_interest = $User->UF_INTERESTS;
            $UserProfileRow->uf_skills = $User->UF_SKILLS;
            $UserProfileRow->work_phone = str_replace('+267','',$User->WORK_PHONE);
            $UserProfileRow->work_phone_inner = $User->UF_PHONE_INNER;
           
            $UserProfileRow::updateOrCreate(
                ['user_id'=> $UserProfileRow->user_id ],
                [
                    'department_id'=>$UserProfileRow->department_id,
                    'dob'=> $UserProfileRow->dob, 
                    'gender'=> $UserProfileRow->gender,
                    'address'=>$UserProfileRow->address,
                    'cellphone'=> $UserProfileRow->cellphone, 
                    'profile_photo'=> $UserProfileRow->profile_photo,
                    'work_position'=>$UserProfileRow->work_position,
                    'department_id'=> $UserProfileRow->uf_department, 
                    'uf_department'=> $UserProfileRow->uf_department, 
                    'uf_interest'=> $UserProfileRow->uf_interest,
                    'uf_skills'=> $UserProfileRow->uf_skills,
                    'work_position'=>$UserProfileRow->work_position,
                    'work_phone'=> $UserProfileRow->work_phone, 
                    'work_phone_inner'=> $UserProfileRow->work_phone_inner  
                   
                    ]
            );
            
         }
         die('done'); 
    }

    public function getDepartments()
    {

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', env('GUZZLE_URL_DEPTS'));
         $Departments = json_decode($response->getBody());
                
         foreach($Departments->result as $Department){
            $DepartmentRow = new Department();
            $DepartmentRow->bitrixId = $Department->ID;
            $DepartmentRow->name = $Department->NAME;
            if (isset($Department->PARENT))
            $DepartmentRow->parent = $Department->PARENT != '' ? $Department->PARENT : '';
            if (isset($Department->UF_HEAD))
            $DepartmentRow->uf_head = $Department->UF_HEAD != '' ? $Department->UF_HEAD : '';
            $DepartmentRow::updateOrCreate(
                ['bitrixId'=> $DepartmentRow->bitrixId ],['name'=>$DepartmentRow->name,'parent'=> $DepartmentRow->parent, 'uf_head'=> $DepartmentRow->uf_head ]
            );
            
         }
         die('done');
        return ;

   
    }
}
