<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Claim;
use AlphaDirect\ClaimAssessment;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\RepairCenter;
use Illuminate\Support\Facades\Auth;
use Mail;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\ClaimQuote;
use AlphaDirect\ClaimQuoteDetail;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\Supplier;
use AlphaDirect\Vehicle;
use Illuminate\Http\Request;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\ClaimsController;
use AlphaDirect\User;
use AlphaDirect\Role;
use AlphaDirect\Notifications;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Helper;
use AlphaDirect\GlassClaim;
use AlphaDirect\OTP;
use Hash;
use Illuminate\Support\Facades\Storage;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Validator;
use Session;
use DB;
use Exception;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;

class SuppliersController extends Controller
{
    /**
     * Show a list of all suppliers.
     *
     * @return View admin suppliers index page
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('supplier-list')){
            return view('admin.supplier.index');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
        // Show the page
        //
    }

    /*
     * Pass data through ajax call for datatable
     */
    /**
     * @return mixed
     */
    public function data()
    {
        $supplier = Supplier::where('customer_selected', 0)->get(['id','supplierName','supplierType' ,'telephone','email','supplierLocation', 'created_at']);

        return DataTables::of($supplier)

            ->editColumn('created_at',function($region) {
                return $region->created_at->diffForHumans();
            })
            ->addColumn('actions',function($supplier) {
                $actions ='';
                if(Auth::user()->hasPermissionTo('supplier-edit')) {
                    $actions .= '<a href="' . route('admin.supplier.edit', $supplier->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';

                }else{
                    $actions .= '<a href="' . route('admin.supplier.edit', $supplier->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if(Auth::user()->hasPermissionTo('supplier-delete')) {
                    $actions .= '<a href="" value="'.$supplier->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                        <i class="la la-trash"></i>
                    </a>';
                }

                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    /**
     * Show a page to create for supplier.
     *
     * @return View
     */
    public function create(){
        if(Auth::user()->hasPermissionTo('supplier-create')){
            $supplierTypes = Lookup::where('key', 'supplier_type')->get(array('id','value'));

            return view('admin.supplier.create',compact('supplierTypes'));
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Method to store suppliers data from create page.
     * param: Request $request
     * @return View
     */
    public function store(Request $request){

        $supplier = new Supplier();
        $supplier->supplierName = $request->get('sname');
        $supplier->supplierType = $request->get('stype');
        $supplier->vat_no = $request->get('vat');
        $supplier->telephone = $request->get('snumber');
        $supplier->email = $request->get('semail');
        $supplier->supplierLocation = $request->get('slocation');
        $supplier->account_no = $request->get('account_no');
        $supplier->address = $request->get('address');

        if($supplier->save()) {
            activity('Supplier')
                ->performedOn($supplier)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Supplier Created');
            // Redirect to the home page with success menu

            return Redirect::route('admin.supplier.index')->with('success', 'Supplier Created Successfully');
        }else{
            return Redirect::route('admin.supplier.index')->with('error', 'Something Went Wrong');
        }

    }

    /**
     * Show a page to edit specified supplier.
     * param: supplier ID
     * @return View
     */
    public function edit( $id){
        $supplier = Supplier::find($id);
        $supplierTypes = Lookup::where('key', 'supplier_type')->get(array('id','value'));
        if(Auth::user()->hasPermissionTo('supplier-edit')){
            return view('admin.supplier.edit',compact('supplier','supplierTypes'));
        }
        elseif(Auth::user()->hasPermissionTo('supplier-list')){
            return view('admin.supplier.view',compact('supplier','supplierTypes'));

        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }


    /**
     * Method to update suppliers data from edit page
     * param: Request $request , supplier ID
     * @return View
     */
    public function update($id, Request $request){
        $supplier = Supplier::where('id', $id)->first();
        $supplier->supplierName = $request->get('sname');
        $supplier->supplierType = $request->get('stype');
        $supplier->vat_no = $request->get('vat');
        $supplier->telephone = $request->get('snumber');
        $supplier->email = $request->get('semail');
        $supplier->supplierLocation = $request->get('slocation');
        $supplier->account_no = $request->get('account_no');
        $supplier->vat_no = $request->get('vat');


        if($supplier->save()) {
            activity('Supplier')
                ->performedOn($supplier)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Supplier Updated');
            // Redirect to the home page with success menu
            return Redirect::route('admin.supplier.index')->with('success', 'Supplier Updated Successfully');
        }else{
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to return modal body for confirm-delete.
     * param: Request $request
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $check = Supplier::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the Supplier ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
    }

    /**
     * method deletes specific supplier.
     * param: supplier id
     * @return view: vendor index page(listing)
     */
    public function destroy($id){
        try{
            activity('Supplier')
                ->performedOn(Supplier::where('id',$id)->first())
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Supplier Deleted');
            $supplier = Supplier::where('id',$id)->delete();

            return Redirect::route('admin.supplier.index')->with('success', 'Supplier Deleted Successfully');

        }catch(Exception $e){
            return Redirect::route('admin.supplier.index')->with('error', 'Something Went Wrong');
        }

    }

    public function quote($quote_id){

        $quote = ClaimQuote::where('id', $quote_id)->first(array('policy_id','claim_id'));
        if($quote == NULL)
            return Redirect::back()->with('error', 'Quote Id is not available. Please check !');
        $vehicle = Vehicle::where('policy_id', $quote->policy_id)->first(array('make', 'model'));
        $claim_id = $quote->claim_id;
        $repair_center_id = NULL;
        return view('admin.supplier.quoteRequest', compact('vehicle', 'quote_id', 'claim_id', 'repair_center_id'));
    }

    public function cellphoneQuote($id,$repaircenter_id){
        $quote_id = NULL;
        $claim_id = $id;
        $repair_center_id = $repaircenter_id;
        $quote_no = ClaimQuote::orderBy('id','desc')->first(array('id'));
        $quote = Claim::where('id', $id)->first(array('policy_id'));
        $vehicle = PolicyCellPhone::where('policy_id', $quote->policy_id)->first(array('cell_phone_make as make', 'cell_phone_model as model'));
        return view('admin.supplier.cellphone_quoteRequest', compact('vehicle','quote_no','quote_id','repair_center_id','claim_id'));
    }

    /**
     * method generates unique user id.
     * param:
     * @return
     */
    public function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring( 0, 0xffff ), Helper::gen_ustring( 0, 0xffff ),

            // 16 bits for "time_mid"
            Helper::gen_ustring( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            Helper::gen_ustring( 0, 0xffff ), Helper::gen_ustring( 0, 0xffff ), Helper::gen_ustring( 0, 0xffff )
        );
    }


    /**
     * method to view submitetd quote.
     * param: $quote_id
     * @return Quote blade
     */
    public function quoteView($quote_id){
        $quote = ClaimQuote::where('id', $quote_id)->first(array('policy_id','total','vat','notes','select_reason','claim_id','supplier_id', 'addn_file'));
        $claim = Claim::where('id', $quote->claim_id)->first(array('id','policy_id','claim_type'));
        $quotedetails = ClaimQuoteDetail::where('quote_id', $quote_id)->get(array('quote_id','item','description','rate','quantity'));
        if ($claim->claim_type == 'Cellphone'){
            $vehicle = PolicyCellPhone::where('policy_id', $quote->policy_id)->first(array('cell_phone_make as make', 'cell_phone_model as model'));
        }
        else{
            $vehicle = Vehicle::where('policy_id', $quote->policy_id)->first(array('make', 'model'));
        }
        return view('admin.supplier.quoteView', compact('vehicle','claim','quote_id','quotedetails','quote'));
    }

    public function uploadInvoice($claim_id, $quote_id = NULL){

        $claim = Claim::where('id', $claim_id)->first(array('supplier_id', 'policy_id','claim_type'));

        if ($claim->claim_type == 'Cellphone'){
            $vehicle = PolicyCellPhone::where('policy_id', $claim->policy_id)->first(array('cell_phone_make as make', 'cell_phone_model as model'));
        }
        else{
            $vehicle = Vehicle::where('policy_id', $claim->policy_id)->first(array('make', 'model'));
        }

        if($quote_id != NULL)
            $quote = ClaimQuote::where('id', $quote_id)->first(array('invoice', 'resent_invoice_notes'));
        else
            $quote = NULL;

        return view('admin.supplier.uploadInvoice', compact('vehicle','claim_id','claim', 'quote_id', 'quote'));
    }

    /**
     * method to store invoice data.
     * param: Request $request
     * @return JSON response
     */
    public function invoiceSubmit(Request $request){
        $claim = Claim::where('id', $request->get('claim_id'))->first();

        if($claim->claim_type == 'Accident' || $claim->claim_type == 'BUSINESSINTERRUPTION' || $claim->claim_type == 'BUSINESSALLRISKS' || $claim->claim_type == 'THEFT' || $claim->claim_type == 'WORKERSCOMPENSATION' || $claim->claim_type == 'FIDELITYGUARANTEE')
        {
            $quote = ClaimQuote::where('id', $request->get('quote_id'))->first();
            if ($request->hasFile('invoice')) {
                $file = $request->file('invoice');
                $name = $this->gen_uuid().$file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file),'public');
                $quote->invoice = $filePath;
            }
            $quote->invoice_notes = $request->get('note');
            $quote->save();
        } else {
            if ($request->hasFile('invoice')) {
                $file = $request->file('invoice');
                $name = $this->gen_uuid().$file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file),'public');
                $claim->invoice = $filePath;
            }
            $claim->note = $request->get('note');
            $claim->save();

            $repaired_img = ClaimCellphone::where('claim_id', $request->claim_id)->first();
            if ($request->hasFile('after_repair_front')) {
                $file = $request->file('after_repair_front');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_front = $filePath;
            }
            if ($request->hasFile('after_repair_back')) {
                $file = $request->file('after_repair_back');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_back = $filePath;
            }
            if ($request->hasFile('after_repair_left')) {
                $file = $request->file('after_repair_left');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_left = $filePath;
            }
            if ($request->hasFile('after_repair_right')) {
                $file = $request->file('after_repair_right');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_right = $filePath;
            }
            if ($request->hasFile('after_repair_top')) {
                $file = $request->file('after_repair_top');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_top = $filePath;
            }
            if ($request->hasFile('after_repair_bottom')) {
                $file = $request->file('after_repair_bottom');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/'.$claim->supplier_id.'/'.'Claims'.'/'.$request->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $repaired_img->after_repair_bottom = $filePath;
            }
            $repaired_img->save();
        }

        if($claim->save()){
            $user = User::where('id', $claim->created_by)->first(array('id', 'email'));
            if($user!= NULL){
                $data = new \stdClass();
                $data->user_id = $user->id;
                $data->hook = 'salvage_yard';
                $data->attachment = NULL;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
               // Mail::to($user->email)->send(new MailTemplate($data));
            }
        }

        return response()->json(['status'=>'success']);
    }

    /**
     * method to submit quote data.
     * param: Request $request
     * @return View Thank you page
     */
    public function quoteSubmit(Request $request){
        $quote = ClaimQuote::where('id', $request->get('quote_id'))->first();
        if ($quote == null){
            $quote = ClaimQuote::where('claim_id', $request->get('claim_id'))->where('supplier_id', $request->get('repair_center_id'))->first();
        }

        if ($quote == null){
            $quote = new ClaimQuote();
            $claims = Claim::where('id', $request->claim_id)->first(array('id','policy_id'));
            $quote->policy_id = $claims->policy_id;
            $quote->claim_id = $request->claim_id;
            $quote->supplier_id = $request->repair_center_id;
        }
            $quote->total = str_replace(",", "", $request->get('total'));
            $quote->vat = $request->get('vat');
            $quote->notes = $request->get('note');
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $name = $this->gen_uuid().$file->getClientOriginalName();
                $filePath = 'MIS/'.$quote->supplier_id.'/'.'Claims'.'/'.$quote->claim_id.'/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file),'public');
                $quote->addn_file = $filePath;
            }
            $saved = $quote->save();

            if($saved){

                ClaimQuoteDetail::where('quote_id', $quote->id)->delete();

                $items =$request->get('item');
                $description = $request->get('description');
                $rate = $request->get('rate');
                $qty =  $request->get('qty');

                for ($i = 0; $i < count($request->item); $i++) {
                    $detail = new ClaimQuoteDetail();
                    $detail->quote_id = $quote->id;
                    $detail->item = $request->item[$i];
                    $detail->description = $request->description[$i];
                    $detail->rate = $request->rate[$i];
                    $detail->quantity = $request->qty[$i];
                    $detail->save();
                }

                $agent_id = Claim::where('id',$quote->claim_id)->first(array('agent_id', 'policy_id'));
                if($agent_id && $agent_id->agent_id != null) {
                    $user = User::where('id', $agent_id->agent_id)->first(array('email'));
                    $data = new \stdClass();
                    $data->user_id = $agent_id->agent_id;
                    $data->claim_id = $quote->claim_id;
                    $data->policy_id = $agent_id->policy_id;
                    $data->hook = 'claim_quote_submit';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
                  //  Mail::to($user->email)->send(new MailTemplate($data));
                }
            }

            activity('Quote')
            ->performedOn($quote)
            ->causedBy(RepairCenter::where('id', $request->repair_center_id)->first())
            ->log('Quote Submitted');

        return view("alphaFe.thankyou_cellphone");
    }


}
