<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Accounts;
use AlphaDirect\Agency;
use AlphaDirect\AgentLogins;
use AlphaDirect\Branch;
use AlphaDirect\Claim;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Stores;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use Auth;
use Carbon\Carbon;
use DB;
use Http\Client\Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Redirect;

class AgentController extends Controller
{
    use AuthenticatesUsers;

    public function agentLogin(Request $request)
    {
        $credentials = array(
            'email' => $request->email,
            'password' => $request->password,
        );

        if (Auth::attempt($credentials)) {

            $user = Auth::user();

            $user->setAttribute('store_id', $request->storeId);

            //Store agent login time and store
            $data = AgentLogins::where('agent_id',$user->id)
                ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('today')
                    ->format('Y-m-d')  , Carbon::parse('today')
                    ->format('Y-m-d') ])
                    ->orderBy('id','DESC')
                    ->first();
            if($data == null){
                $data = new AgentLogins();
            }

            $data->agent_id = $user->id;
            $data->store_id = $request->storeId;
            $data->last_activity = Carbon::now()->toDateTimeString();
            $data->save();

            if($request->email != null){
                $agentData = User::where('email',$request->email)->first(array('id','firstName','lastName'));
                $agentPic = UserProfile::where('user_id',$agentData->id)->first(array('profile_photo'));
            } else {
                $agentData = null;
                $agentPic = null;
            }
            if(Auth::user()->hasRole('Manager')){
                $role = 'Manager';
            }else{
                $role = null;
            }
            $alphaResponse = response()->json(['user' => $user,'agentData' => $agentData,'agentPic' => $agentPic,'role' =>$role ], 200);

            return $alphaResponse;
        } else {

            return response()->json(['title' => 'Agent Account', 'Description' => 'Agent account does not exist'], 401);
        }
    }

    public function displayTshologoAgentLogins()
    {
        return view('admin.agentAppUploads.display_tsosologo_agent_login');
    }

    public function logTshosoloAgentLogin(Request $request)
    {
        try {
            //code...
            $agent_id = $request->agent_id;
            $action = $request->action;
            $store_id = $request->storeId;
            $latitude = $request->latitude;
            $longitude = $request->longitude;

            if ($action == 'login') {
                $current_time = Carbon::now()->toDateTimeString();

                $query = DB::table('tsosologo_agent_login')->insert([
                    'agent_id' => $agent_id, 'action' => $action, 'login_time' => $current_time, 'store_id' => $store_id, 'latitude' => $latitude,
                    'longitude' => $longitude, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()]);
            }
            if ($action == 'logout') {
                $logout_time = Carbon::now()->toDateTimeString();
                $query = DB::table('tsosologo_agent_login')->insert(['agent_id' => $agent_id, 'action' => $action, 'logout_time' => $logout_time, 'store_id' => $store_id, 'logout_time' => $logout_time, 'latitude' => $latitude,
                    'longitude' => $longitude, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()]);
            }

            if ($query) {
                return response('success', 200);
            }
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
        
    }
    public function tsosologoAgentLogout(Request $request)
    {
        try {
            $this->logTshosoloAgentLogin($request);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }

    public function data()
    {
        try {

            $query = DB::table('tsosologo_agent_login')->get();
            return DataTables::of($query)
                ->editColumn('last_activity', function ($query) {
                    if ($query->last_activity != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $query->last_activity)->format('Y-m-d H:i') ;
                    }
                })
                ->editColumn('agent_id', function ($query) {
                    $user = User::findOrFail($query->agent_id);
                    return $user->firstName . ' ' . $user->lastName;
                })
                ->editColumn('store_id', function ($query) {
                    $store = Branch::findOrFail($query->store_id);
                    return $store->name;

                })
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }

    public function agentLogins(){
        try{
            return view('Agents.viewLogins');
        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function agentLoginData()
    {
        $data = AgentLogins::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->orderBy('id','DESC')
            ->get(array('id','agent_id','store_id','created_at','last_activity'));

        return DataTables::of($data)
            ->addColumn('agentID',function($data) {
                $agent = User::where('id',$data->agent_id)->first(array('firstName','lastName'));
                if($agent)
                    return $agent->firstName.' '.$agent->lastName;
                else
                    return 'N/A';
            })
            ->addColumn('store_id',function($data) {
                $store = Stores::where('id',$data->store_id)->first(array('name'));
                if($store)
                    return $store->name;
                else
                    return 'N/A';
            })
            ->addColumn('last_activity',function($data) {
               if($data->last_activity){
                   return Carbon::parse($data->last_activity)->diffForHumans();
               }else{
                   return '-';
               }
            })
            ->rawColumns(['agentID','store_id','last_activity'])
            ->make(true);
    }

    public function agentPieChart(Request $request){
        try {
            $user_id = $request->id;
            if (isset($user_id)) {
                   $usercheck = User::where('id',$user_id)->where('active',1)->first();
                if(isset($usercheck) && $usercheck != null ){
                    $agenciescheck = Agency::where('id',$usercheck->agency_id)->where('status',1)->first();
                    if(isset($agenciescheck) && $agenciescheck != null && $agenciescheck->name == "Wallet Assist (Rosslyn and Ross Pty Ltd)" ){
                           return $this->agentPieChartwallet($request);
                    }

                    
                }
                $count = [];
                $date = [];

                $act_top_user_count = [];
                $act_days = [];

                $from_date =  $request->from_date;
                $to_date = $request->to_date;

                // Today's Statistics
              //  $totalActiveAgent = Policy::where('agent_id',$user_id)->groupBy('agent_id')->whereBetween('created_at' , [$from_date,$to_date])->count();
              //  $totalActiveStore = Policy::groupBy('storeID')->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalPolicy = Policy::whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalActivedPolicy = Policy::where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalDeactivatedPolicy = Policy::where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalCancelledPolicy = Policy::where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();


                $totalActiveStore = DB::select(" SELECT COUNT(DISTINCT policies.storeID) as totalActiveStore FROM policies
                                        WHERE storeID != '' AND  created_at between '$from_date' and '$to_date'");

                $totalActiveAgent = DB::select(" SELECT COUNT(DISTINCT agent_id) as totalActiveAgent FROM policies
                                    JOIN users ON policies.agent_id = users.id
                                    WHERE agent_id != '' AND policies.created_at between '$from_date' and '$to_date'");

                // Today's Agents
                $toDate  = Carbon::parse('today')->format('Y-m-d');
                $agencies = DB::select("SELECT
                                        users.id AS agent_id,
                                        count(policies.id) as policy_count,
                                        SUM(policies.status = 1) AS activated_policy_count,
                                        SUM(policies.status = 0) AS deactivated_policy_count,
                                        SUM(policies.status = 2) AS cancelled_policy_count,
                                        CONCAT(users.firstName,' ',users.lastName) agent_name
                                        FROM policies
                                        JOIN users ON policies.agent_id = users.id
                                        WHERE policies.created_at like '%$toDate%'
                                        -- WHERE policies.created_at between '$from_date' and '$to_date'
                                        group by policies.agent_id order by policy_count ASC");


                // Today's Stores
                $stores = DB::select("SELECT
                                    count(policies.id) as policy_count,
                                    stores.name as store_name
                                    FROM policies
                                    JOIN stores ON policies.storeID = stores.id
                                    WHERE policies.created_at like '%$toDate%'
                                    group by policies.storeID order by policy_count ASC");

                // week month
                $weekmonth = policy::where('agent_id',$user_id);
                $weekmonth->whereBetween('created_at' , [$from_date,$to_date]);

                if (isset($request->display) && !empty($request->display) ) {
                    $display = $request->display;
                    if($display=='Week'){
                        $weekmonth->groupBy(DB::raw("WEEK(created_at)"));
                    }elseif($weekmonth=='Month'){
                        $weekmonth->groupBy(DB::raw("MONTH(created_at)"));
                    }elseif($display=='Day'){
                        $weekmonth->groupBy(DB::raw("DAY(created_at)"));
                    }
                }else{
                    $weekmonth->groupBy(DB::raw("DATE(created_at)"));
                }
                $weekmonth->orderBy('created_at', 'asc');
                $weekmonth->select('created_at','id');

                if (isset($request->display) && !empty($request->display)){
                    if( $request->display=='Week'){
                        $weekmonth->addSelect(
                            DB::raw("(Count(id)) as total_id"),
                            DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                        );
                    }elseif($request->display=='Month'){
                        $weekmonth->addSelect(
                            DB::raw("(Count(id)) as total_id"),
                            DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                        );
                    }elseif($display=='Day'){
                        $weekmonth->addSelect(
                            DB::raw("(Count(id)) as total_id"),
                            DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                        );
                    }
                }else{
                    $weekmonth->addSelect(
                        DB::raw("(Count(id)) as total_id"),
                        DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                    );
                }
                $weekmonth->get();

                $count =  $weekmonth->pluck('total_id')->toArray();
                $date =  $weekmonth->pluck('date' )->toArray();
                $sales_value = array_sum($count);
                $products = Product::where('status', 1)->get(array('id','name'));

                $cards = array();
                foreach($products as $product)
                {
                    $cards[$product->id]['name'] = $product->name;
                    $cards[$product->id]['id'] = $product->id;
                    $cards[$product->id]['total'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $cards[$product->id]['activated'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $cards[$product->id]['deactivated'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $cards[$product->id]['cancelled'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                }

                //ADI
                $ADIpolicy = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $ADIActivated = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->where('status', 1)->count();
                $ADIDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->where('status', 0)->count();
                $ADICancelled = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->where('status', 2)->count();

                // Motor com
                $motorComPolicy = Policy::where('agent_id',$user_id)->where('product_id', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $motorComActivated = Policy::where('agent_id',$user_id)->where('product_id', 2)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $motorComDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 2)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $motorComCancelled = Policy::where('agent_id',$user_id)->where('product_id', 2)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Legal
                $LegalPolicy = Policy::where('agent_id',$user_id)->where('product_id', 3)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $LegalActivated = Policy::where('agent_id',$user_id)->where('product_id', 3)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $LegalDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 3)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $LegalCancelled = Policy::where('agent_id',$user_id)->where('product_id', 3)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Cellphone
                $cellphonePolicy = Policy::where('agent_id',$user_id)->where('product_id', 4)->count();
                $cellphoneActivated = Policy::where('agent_id',$user_id)->where('product_id', 4)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $cellphoneDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 4)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $cellphoneCancelled = Policy::where('agent_id',$user_id)->where('product_id', 4)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Third Party
                $thirdPartyPolicy = Policy::where('agent_id',$user_id)->where('product_id', 5)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $thirdPartyActivated = Policy::where('agent_id',$user_id)->where('product_id', 5)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $thirdPartyDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 5)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $thirdPartyCancelled = Policy::where('agent_id',$user_id)->where('product_id', 5)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Trye & Rims
                $tryeRimsPolicy = Policy::where('agent_id',$user_id)->where('product_id', 6)->count();
                $tryeRimsActivated = Policy::where('agent_id',$user_id)->where('product_id', 6)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $tryeRimsDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 6)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $tryeRimsCancelled = Policy::where('agent_id',$user_id)->where('product_id', 6)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // pie chart 1
                $policyActivated = Policy::where('agent_id',$user_id)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $policyDeactivated = Policy::where('agent_id',$user_id)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $policyCancelled = Policy::where('agent_id',$user_id)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // pie chart 2
                $product1 = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product2 = Policy::where('agent_id',$user_id)->where('product_id', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product3 = Policy::where('agent_id',$user_id)->where('product_id', 3)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product4 = Policy::where('agent_id',$user_id)->where('product_id', 4)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product5 = Policy::where('agent_id',$user_id)->where('product_id', 5)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product6 = Policy::where('agent_id',$user_id)->where('product_id', 6)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // pie chart 3
                $policy =  Policy::where('agent_id',$user_id)->pluck('customer_id')->toArray();
                if(count($policy) > 0){
                    $kycCompliance =  KYC::whereIn('customer_id',$policy)->where('compliance', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $kycUploaded =  KYC::whereIn('customer_id',$policy)->where('compliance', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $kycNotUploaded =  KYC::whereIn('customer_id',$policy)->where('compliance', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                }else{
                    $kycCompliance = 0;
                    $kycUploaded = 0;
                    $kycNotUploaded = 0;
                }

                //Top performance

                $getTopAgents = DB::select("SELECT users.id as agent_id,
                                COUNT(DISTINCT policies.id) as policy_count,
                                SUM(policies.status = 1) AS activated_policy_count,
                                CONCAT(users.firstName,' ',users.lastName) agent_name,
                                user_profile.profile_photo
                                FROM policies
                                JOIN users ON policies.agent_id = users.id
                                JOIN user_profile ON policies.agent_id = user_profile.user_id
                                WHERE agent_id != '' AND policies.created_at between '$from_date' and '$to_date'
                                group by policies.agent_id order by activated_policy_count DESC LIMIT 10");


                //Activity Chart
                if($getTopAgents != NULL)
                {
                    $activity_data = Policy::whereBetween('created_at' , [$from_date,$to_date])->groupBy(DB::raw("DATE(created_at)"))
                    ->orderBy('created_at', 'asc')->select('created_at','id')->addSelect(
                        DB::raw("(Count(id)) as totalid"),
                        DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                    )->where('agent_id',$getTopAgents[0]->agent_id)->get();

                    $act_top_user_count =  $activity_data->pluck('totalid')->toArray();
                    $act_days =  $activity_data->pluck('date')->toArray();
                }

                $activity_login_data = Policy::whereBetween('created_at' , [$from_date,$to_date])->groupBy(DB::raw("DATE(created_at)"))
                ->orderBy('created_at', 'asc')->select('created_at','id')->addSelect(
                    DB::raw("(Count(id)) as totalid"),
                    DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                )->where('agent_id', $user_id)->get();
                $act_login_user_count =  $activity_login_data->pluck('totalid')->toArray();
                if(count($act_days) == 0)
                {
                    $act_days =  $activity_login_data->pluck('date')->toArray();
                }

                return response()->json(['message' => 'Data Present','cards' => $cards,'act_days' => $act_days,'act_top_user_count' => $act_top_user_count,'getLoginUser' => $act_login_user_count,'getTopAgents' => $getTopAgents,
                'count' => $count,'date' => $date,'sales_value' => $sales_value,
                'totalActiveAgent' => $totalActiveAgent,'totalActiveStore' => $totalActiveStore,
                'totalPolicy' => $totalPolicy,'totalActivedPolicy' => $totalActivedPolicy,'totalDeactivatedPolicy' => $totalDeactivatedPolicy,'totalCancelledPolicy' => $totalCancelledPolicy,
                'stores' => $stores,'agencies' => $agencies,
                'ADIpolicy' => $ADIpolicy,'motorComPolicy' => $motorComPolicy,'LegalPolicy' => $LegalPolicy,
                'cellphonePolicy' => $cellphonePolicy,'thirdPartyPolicy' => $thirdPartyPolicy,'tryeRimsPolicy' => $tryeRimsPolicy,
                'ADIActivated' => $ADIActivated,'ADIDeactivated' => $ADIDeactivated,'ADICancelled' => $ADICancelled,
                'motorComActivated' => $motorComActivated, 'motorComDeactivated' => $motorComDeactivated, 'motorComCancelled' => $motorComCancelled,
                'LegalActivated' => $LegalActivated, 'LegalDeactivated' => $LegalDeactivated, 'LegalCancelled' => $LegalCancelled,
                'cellphoneActivated' => $cellphoneActivated, 'cellphoneDeactivated' => $cellphoneDeactivated, 'cellphoneCancelled' => $cellphoneCancelled,
                'thirdPartyActivated' => $thirdPartyActivated, 'thirdPartyDeactivated' => $thirdPartyDeactivated, 'thirdPartyCancelled' => $thirdPartyCancelled,
                'tryeRimsActivated' => $tryeRimsActivated, 'tryeRimsDeactivated' => $tryeRimsDeactivated, 'tryeRimsCancelled' => $tryeRimsCancelled,
                'policyActivated' => $policyActivated, 'policyDeactivated' => $policyDeactivated, 'policyCancelled' => $policyCancelled,
                'product1' => $product1, 'product2' => $product2, 'product3' => $product3,'product4' => $product4, 'product5' => $product5,'product6' => $product6,
                'kycCompliance' => $kycCompliance, 'kycUploaded' => $kycUploaded, 'kycNotUploaded' => $kycNotUploaded], 200);
            } else {
                return response()->json(['message' => 'Something went wrong'], 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }
        public function agentPieChartwallet($request){
        try {
            $user_id = $request->id;
            if (isset($user_id)) {
                $count = [];
                $date = [];

                $act_top_user_count = [];
                $act_days = [];
                $usercheck = User::where('id',$user_id)->where('active',1)->first();
                //$agenciescheck = Agency::where('id',$usercheck->agency_id)->where('status',1)->first();
                        

                    
             
                $from_date =  $request->from_date;
                $to_date = $request->to_date;

                // Today's Statistics
              //  $totalActiveAgent = Policy::where('agent_id',$user_id)->groupBy('agent_id')->whereBetween('created_at' , [$from_date,$to_date])->count();
              //  $totalActiveStore = Policy::groupBy('storeID')->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalPolicy = Policy::where('agency_id',$usercheck->agency_id)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalActivedPolicy = Policy::where('agency_id',$usercheck->agency_id)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalDeactivatedPolicy = Policy::where('agency_id',$usercheck->agency_id)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $totalCancelledPolicy = Policy::where('agency_id',$usercheck->agency_id)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();


                $totalActiveStore = DB::select(" SELECT COUNT(DISTINCT policies.storeID) as totalActiveStore FROM policies
                                        WHERE storeID != '' AND policies.agency_id = $usercheck->agency_id AND  created_at between '$from_date' and '$to_date'");

                $totalActiveAgent = DB::select(" SELECT COUNT(DISTINCT agent_id) as totalActiveAgent FROM policies
                                    JOIN users ON policies.agent_id = users.id
                                    WHERE agent_id != '' AND policies.agency_id = $usercheck->agency_id  AND policies.created_at between '$from_date' and '$to_date'");

                // Today's Agents
                $toDate  = Carbon::parse('today')->format('Y-m-d');
                $agencies = DB::select("SELECT
                                        users.id AS agent_id,
                                        count(policies.id) as policy_count,
                                        SUM(policies.status = 1) AS activated_policy_count,
                                        SUM(policies.status = 0) AS deactivated_policy_count,
                                        SUM(policies.status = 2) AS cancelled_policy_count,
                                        CONCAT(users.firstName,' ',users.lastName) agent_name
                                        FROM policies
                                        JOIN users ON policies.agent_id = users.id
                                        WHERE policies.created_at like '%$toDate%'
                                        AND policies.agency_id = $usercheck->agency_id
                                        -- WHERE policies.created_at between '$from_date' and '$to_date'
                                        group by policies.agent_id order by policy_count ASC");


                // Today's Stores
                $stores = DB::select("SELECT
                                    count(policies.id) as policy_count,
                                    stores.name as store_name
                                    FROM policies
                                    JOIN stores ON policies.storeID = stores.id
                                    WHERE policies.created_at like '%$toDate%'
                                    AND policies.agency_id = $usercheck->agency_id
                                    group by policies.storeID order by policy_count ASC");

                // week month
                $weekmonth = policy::where('agent_id',$user_id);
                $weekmonth->whereBetween('created_at' , [$from_date,$to_date]);

                if (isset($request->display) && !empty($request->display) ) {
                    $display = $request->display;
                    if($display=='Week'){
                        $weekmonth->groupBy(DB::raw("WEEK(created_at)"));
                    }elseif($weekmonth=='Month'){
                        $weekmonth->groupBy(DB::raw("MONTH(created_at)"));
                    }elseif($display=='Day'){
                        $weekmonth->groupBy(DB::raw("DAY(created_at)"));
                    }
                }else{
                    $weekmonth->groupBy(DB::raw("DATE(created_at)"));
                }
                $weekmonth->orderBy('created_at', 'asc');
                $weekmonth->select('created_at','id');

                if (isset($request->display) && !empty($request->display)){
                    if( $request->display=='Week'){
                        $weekmonth->addSelect(
                            DB::raw("(Count(id)) as total_id"),
                            DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                        );
                    }elseif($request->display=='Month'){
                        $weekmonth->addSelect(
                            DB::raw("(Count(id)) as total_id"),
                            DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                        );
                    }elseif($display=='Day'){
                        $weekmonth->addSelect(
                            DB::raw("(Count(id)) as total_id"),
                            DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                        );
                    }
                }else{
                    $weekmonth->addSelect(
                        DB::raw("(Count(id)) as total_id"),
                        DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                    );
                }
                $weekmonth->get();

                $count =  $weekmonth->pluck('total_id')->toArray();
                $date =  $weekmonth->pluck('date' )->toArray();
                $sales_value = array_sum($count);
                $products = Product::where('status', 1)->get(array('id','name'));

                $cards = array();
                foreach($products as $product)
                {
                    $cards[$product->id]['name'] = $product->name;
                    $cards[$product->id]['id'] = $product->id;
                    $cards[$product->id]['total'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $cards[$product->id]['activated'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $cards[$product->id]['deactivated'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $cards[$product->id]['cancelled'] = Policy::where('agent_id',$user_id)->where('product_id', $product->id)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                }

                //ADI
                $ADIpolicy = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $ADIActivated = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->where('status', 1)->count();
                $ADIDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->where('status', 0)->count();
                $ADICancelled = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->where('status', 2)->count();

                // Motor com
                $motorComPolicy = Policy::where('agent_id',$user_id)->where('product_id', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $motorComActivated = Policy::where('agent_id',$user_id)->where('product_id', 2)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $motorComDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 2)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $motorComCancelled = Policy::where('agent_id',$user_id)->where('product_id', 2)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Legal
                $LegalPolicy = Policy::where('agent_id',$user_id)->where('product_id', 3)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $LegalActivated = Policy::where('agent_id',$user_id)->where('product_id', 3)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $LegalDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 3)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $LegalCancelled = Policy::where('agent_id',$user_id)->where('product_id', 3)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Cellphone
                $cellphonePolicy = Policy::where('agent_id',$user_id)->where('product_id', 4)->count();
                $cellphoneActivated = Policy::where('agent_id',$user_id)->where('product_id', 4)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $cellphoneDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 4)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $cellphoneCancelled = Policy::where('agent_id',$user_id)->where('product_id', 4)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Third Party
                $thirdPartyPolicy = Policy::where('agent_id',$user_id)->where('product_id', 5)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $thirdPartyActivated = Policy::where('agent_id',$user_id)->where('product_id', 5)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $thirdPartyDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 5)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $thirdPartyCancelled = Policy::where('agent_id',$user_id)->where('product_id', 5)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // Trye & Rims
                $tryeRimsPolicy = Policy::where('agent_id',$user_id)->where('product_id', 6)->count();
                $tryeRimsActivated = Policy::where('agent_id',$user_id)->where('product_id', 6)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $tryeRimsDeactivated = Policy::where('agent_id',$user_id)->where('product_id', 6)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $tryeRimsCancelled = Policy::where('agent_id',$user_id)->where('product_id', 6)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // pie chart 1
                $policyActivated = Policy::where('agent_id',$user_id)->where('status', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $policyDeactivated = Policy::where('agent_id',$user_id)->where('status', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $policyCancelled = Policy::where('agent_id',$user_id)->where('status', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // pie chart 2
                $product1 = Policy::where('agent_id',$user_id)->where('product_id', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product2 = Policy::where('agent_id',$user_id)->where('product_id', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product3 = Policy::where('agent_id',$user_id)->where('product_id', 3)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product4 = Policy::where('agent_id',$user_id)->where('product_id', 4)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product5 = Policy::where('agent_id',$user_id)->where('product_id', 5)->whereBetween('created_at' , [$from_date,$to_date])->count();
                $product6 = Policy::where('agent_id',$user_id)->where('product_id', 6)->whereBetween('created_at' , [$from_date,$to_date])->count();

                // pie chart 3
                $policy =  Policy::where('agent_id',$user_id)->pluck('customer_id')->toArray();
                if(count($policy) > 0){
                    $kycCompliance =  KYC::whereIn('customer_id',$policy)->where('compliance', 1)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $kycUploaded =  KYC::whereIn('customer_id',$policy)->where('compliance', 0)->whereBetween('created_at' , [$from_date,$to_date])->count();
                    $kycNotUploaded =  KYC::whereIn('customer_id',$policy)->where('compliance', 2)->whereBetween('created_at' , [$from_date,$to_date])->count();
                }else{
                    $kycCompliance = 0;
                    $kycUploaded = 0;
                    $kycNotUploaded = 0;
                }

                //Top performance

                $getTopAgents = DB::select("SELECT users.id as agent_id,
                                COUNT(DISTINCT policies.id) as policy_count,
                                SUM(policies.status = 1) AS activated_policy_count,
                                CONCAT(users.firstName,' ',users.lastName) agent_name,
                                user_profile.profile_photo
                                FROM policies
                                JOIN users ON policies.agent_id = users.id
                                JOIN user_profile ON policies.agent_id = user_profile.user_id
                                WHERE agent_id != '' AND policies.created_at between '$from_date' and '$to_date'
                                AND policies.agency_id = $usercheck->agency_id
                                group by policies.agent_id order by activated_policy_count DESC LIMIT 10");


                //Activity Chart
                if($getTopAgents != NULL)
                {
                    $activity_data = Policy::whereBetween('created_at' , [$from_date,$to_date])->groupBy(DB::raw("DATE(created_at)"))
                    ->orderBy('created_at', 'asc')->select('created_at','id')->addSelect(
                        DB::raw("(Count(id)) as totalid"),
                        DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                    )->where('agent_id',$getTopAgents[0]->agent_id)->get();

                    $act_top_user_count =  $activity_data->pluck('totalid')->toArray();
                    $act_days =  $activity_data->pluck('date')->toArray();
                }

                $activity_login_data = Policy::whereBetween('created_at' , [$from_date,$to_date])->groupBy(DB::raw("DATE(created_at)"))
                ->orderBy('created_at', 'asc')->select('created_at','id')->addSelect(
                    DB::raw("(Count(id)) as totalid"),
                    DB::raw("(DATE_FORMAT(created_at,'%d/%m')) as date")
                )->where('agent_id', $user_id)->get();
                $act_login_user_count =  $activity_login_data->pluck('totalid')->toArray();
                if(count($act_days) == 0)
                {
                    $act_days =  $activity_login_data->pluck('date')->toArray();
                }

                return response()->json(['message' => 'Data Present','cards' => $cards,'act_days' => $act_days,'act_top_user_count' => $act_top_user_count,'getLoginUser' => $act_login_user_count,'getTopAgents' => $getTopAgents,
                'count' => $count,'date' => $date,'sales_value' => $sales_value,
                'totalActiveAgent' => $totalActiveAgent,'totalActiveStore' => $totalActiveStore,
                'totalPolicy' => $totalPolicy,'totalActivedPolicy' => $totalActivedPolicy,'totalDeactivatedPolicy' => $totalDeactivatedPolicy,'totalCancelledPolicy' => $totalCancelledPolicy,
                'stores' => $stores,'agencies' => $agencies,
                'ADIpolicy' => $ADIpolicy,'motorComPolicy' => $motorComPolicy,'LegalPolicy' => $LegalPolicy,
                'cellphonePolicy' => $cellphonePolicy,'thirdPartyPolicy' => $thirdPartyPolicy,'tryeRimsPolicy' => $tryeRimsPolicy,
                'ADIActivated' => $ADIActivated,'ADIDeactivated' => $ADIDeactivated,'ADICancelled' => $ADICancelled,
                'motorComActivated' => $motorComActivated, 'motorComDeactivated' => $motorComDeactivated, 'motorComCancelled' => $motorComCancelled,
                'LegalActivated' => $LegalActivated, 'LegalDeactivated' => $LegalDeactivated, 'LegalCancelled' => $LegalCancelled,
                'cellphoneActivated' => $cellphoneActivated, 'cellphoneDeactivated' => $cellphoneDeactivated, 'cellphoneCancelled' => $cellphoneCancelled,
                'thirdPartyActivated' => $thirdPartyActivated, 'thirdPartyDeactivated' => $thirdPartyDeactivated, 'thirdPartyCancelled' => $thirdPartyCancelled,
                'tryeRimsActivated' => $tryeRimsActivated, 'tryeRimsDeactivated' => $tryeRimsDeactivated, 'tryeRimsCancelled' => $tryeRimsCancelled,
                'policyActivated' => $policyActivated, 'policyDeactivated' => $policyDeactivated, 'policyCancelled' => $policyCancelled,
                'product1' => $product1, 'product2' => $product2, 'product3' => $product3,'product4' => $product4, 'product5' => $product5,'product6' => $product6,
                'kycCompliance' => $kycCompliance, 'kycUploaded' => $kycUploaded, 'kycNotUploaded' => $kycNotUploaded], 200);
            } else {
                return response()->json(['message' => 'Something went wrong'], 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

}
