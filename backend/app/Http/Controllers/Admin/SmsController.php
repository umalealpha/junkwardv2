<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Sms;
use AlphaDirect\TemplateFields;
use AlphaDirect\User;
use AlphaDirect\Hook;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Validator;
use Session;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;
use AlphaDirect\Models\SmsControls;

class SmsController extends Controller
{
    /**
     * Show a list of all sms broadcasts.
     *
     * @return View SMS broadcast index page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('sms-template-list'))
        {
            return view('admin.smsBroadCasting.index');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

        // Show the page

    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed json data
     */
    public function data()
    {
        $smsBroadcasting = Sms::get(array(
            'id',
            'name',
            'hook_slug',
            'text',
            'created_at',
            'updated_at'
        ));
        return DataTables::of($smsBroadcasting)
            ->addColumn('hook_name', function ($smsBroadcasting)
            {
                $hook = Hook::where('slug', $smsBroadcasting->hook_slug)
                    ->first(array(
                        'name'
                    ));

                return $hook ? $hook->name : '';

            })
            ->addColumn('actions', function ($smsBroadcasting)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('sms-template-edit'))
                {
                    $actions .= '<a href="' . route('admin.sms.edit', $smsBroadcasting->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.sms.edit', $smsBroadcasting->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('sms-template-delete'))
                {
                    $actions .= '<a href="" value="' . $smsBroadcasting->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })->rawColumns(['actions', 'text'])
            ->make(true);
    }

    /**
     * function to implement CKEUpload.
     *
     * @return JSON
     */
    public function ckeupload()
    {
        $upload_dir = array(
            'img' => public_path() . '/uploads/',
        );

        $imgset = array(
            'minwidth' => 10,
            'minheight' => 10,
            'type' => array(
                'bmp',
                'gif',
                'jpg',
                'jpeg',
                'png'
            ) ,
        );

        // If 0, will OVERWRITE the existing file
        define('RENAME_F', 1);

        // init
        $re = array(
            'uploaded' => false,
           );
        if (isset($_FILES['upload']) && strlen($_FILES['upload']['name']) > 1)
        {
            define('F_NAME', preg_replace('/\.(.+?)$/i', '', basename($_FILES['upload']['name']))); //get filename without extension
            // get protocol and host name to send the absolute image path to CKEditor
            $site = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';
            $sepext = explode('.', strtolower($_FILES['upload']['name']));
            $type = end($sepext); // gets extension
            $upload_dir = in_array($type, $imgset['type']) ? $upload_dir['img'] : $upload_dir['audio'];
            if (in_array($type, $imgset['type']))
            {
                if (isset($width) && isset($height))
                {
                    if ($width > $imgset['maxwidth'] || $height > $imgset['maxheight']) $re .= '\\n Width x Height = ' . $width . ' x ' . $height . ' \\n The maximum Width x Height must be: ' . $imgset['maxwidth'] . ' x ' . $imgset['maxheight'];
                    if ($width < $imgset['minwidth'] || $height < $imgset['minheight']) $re .= '\\n Width x Height = ' . $width . ' x ' . $height . '\\n The minimum Width x Height must be: ' . $imgset['minwidth'] . ' x ' . $imgset['minheight'];
                    if ($_FILES['upload']['size'] > $imgset['maxsize'] * 1000) $re .= '\\n Maximum file size must be: ' . $imgset['maxsize'] . ' KB.';
                }
            }
            else $re .= 'The file: ' . $_FILES['upload']['name'] . ' has not the allowed extension type.';
            function setFName($p, $fn, $ex, $i)
            {
                if (RENAME_F == 1 && file_exists($p . $fn . $ex)) return setFName($p, F_NAME . '_' . ($i + 1) , $ex, ($i + 1));
                else return $fn . $ex;
            }

            $f_name = setFName($upload_dir, F_NAME, ".$type", 0);
            $uploadpath = $upload_dir . $f_name; // full file path
            // If no errors, upload the image, else, output the errors

                if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadpath))
                {
                    $url = url('/') . '/uploads/questions/' . $f_name;
                    $msg = F_NAME . '.' . $type . ' successfully uploaded: \\n- Size: ' . number_format($_FILES['upload']['size'] / 1024, 2, '.', '') . ' KB';

                    $re['uploaded'] = true;
                    $re['url'] = $url;
                    $re['fileName'] = $f_name;

                    @header('Content-type: text/html; charset=utf-8');
                    $re = json_encode($re);
                    echo $re;

                }
                else $re = 'alert("Unable to upload the file")';

        }

    }

    /**
     *show create page for bew SMS broadcast
     *
     * @return sms broadcast create page
     */
    public function create()
    {
        if (Auth::user()->hasPermissionTo('sms-template-list'))
        {
            $templatefields = TemplateFields::get(array(
                'id',
                'tablename',
                'table_name',
                'field',
                'field_name'
            ));
            $hooks = Hook::get(array(
                'slug',
                'name'
            ));
            return view("admin.smsBroadCasting.create", compact('templatefields', 'hooks'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     *stores data for new sms broadcast from create page
     *
     * @return sms broadcast listing page
     */
    public function store(Request $request)
    { 
        

        $sms = new Sms();
        $sms->name = $request->get('name');
        $sms->slug = $request->get('name');
        $sms->text = $request->get('summary-ckeditor');
        $sms->hook_slug = $request->get('hook');
        $sms->which_day = $request->which_day;
        $sms->sms_limit = $request->sms_limit;
        if ($sms->save())
        {
            activity('Sms Template')
                ->performedOn($sms)->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Sms Template Created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.sms.index')
                ->with('success', 'New SMS template Created Successfully');
        }
        else
        {
            return Redirect::route('admin.sms.index')
                ->with('error', 'Something Went Wrong');
        }

    }

    /**
     * Show a page to edit specific sms broadcast.
     *
     * @return View sms broadcast edit page
     */
    public function edit($id)
    {

        $sms = Sms::where('id', $id)->first();
        $templatefields = TemplateFields::get(array(
            'id',
            'tablename',
            'table_name',
            'field',
            'field_name'
        ));
        $hooks = Hook::get(array(
            'slug',
            'name'
        ));
        if (Auth::user()->hasPermissionTo('sms-template-edit'))
        {
            return view('admin.smsBroadCasting.edit', compact('sms', 'templatefields', 'hooks'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('sms-template-list'))
        {
            return view('admin.smsBroadCasting.view', compact('sms', 'templatefields', 'hooks'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * updates data from sms broadcast edit page.
     *
     * @return View sms broadcast listing
     */
    public function update($id, Request $request)
    {
        $sms = Sms::where('id', $id)->first();
        $sms->name = $request->get('name');
        $sms->slug = $request->get('name');
        $sms->text = $request->get('summary-ckeditor');
        $sms->hook_slug = $request->get('hook');
        $sms->which_day = $request->which_day;
        $sms->sms_limit = $request->sms_limit;
        if ($sms->save())
        {
            // Redirect to the home page with success menu
            activity('Sms Template')
                ->performedOn($sms)->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Sms Template Updated');
            return Redirect::route('admin.sms.index')
                ->with('success', 'SMS template updated successfully');
        }
        else
        {
            return redirect()
                ->back()
                ->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $sms = Sms::where('id', $request->get('id'))
            ->count();
        // Check if we are not trying to delete ourselves
        $body = 'Are you sure you want to delete the  email template ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

    /**
     *deletes specific SMS Broadcast
     *param: smsbroadcast ID ($id)
     * @return user listing page
     */
    public function destroy($id)
    {
        try
        {

            activity('Sms Template')->performedOn(Sms::where('id', $id)->first())
                ->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Sms Template Deleted');
            Sms::where('id', $id)->delete();

            return Redirect::route('admin.sms.index')
                ->with('success', 'Sms template Deleted Successfully');

        }
        catch(TeacherNotFoundException $e)
        {
            return Redirect::route('admin.sms.index')->with('error', 'Something Went Wrong');
        }

    }

    public function getSMSDetails()
    {
    }
    public function smsControl()
    {
        if (auth::user()->hasPermissionTo('sms_control_list')){
            return view('admin.smsControl.index');
        }else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        } 
    }
     public function smsControlCreate()
    {
        if (auth::user()->hasPermissionTo('sms_control_add'))
        {
            return view('admin.smsControl.create');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        } 
    }
      public function smsControlEdit($id)
    {
        if (auth::user()->hasPermissionTo('sms_control_edit'))
        {
          $smsControl =  SmsControls::where('id',$id)->first();
            return view('admin.smsControl.edit',compact('smsControl'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        } 
    }
    public function smsControlStore(Request $request)
    {
        //dd( $request->all());
        if (auth::user()->hasPermissionTo('sms_control_add'))
        {
            if(isset($request->function_name) && $request->function_name != null){
                if(isset($request->id)){
                    $smsfunction =  SmsControls::where('id',$request->id)->first();
                }else{
                    $smsfunction = new SmsControls(); 
                }
                    
                    $smsfunction->function_name = $request->function_name;
                    $smsfunction->status = $request->status;
                    $smsfunction->save();
                return Redirect::route('admin.smsControl')->with('success','Sms control Function Created successfully');
            }else{
              return Redirect::back()->with('error','wrong input sms control Function name!');
            }
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        } 
    }
     public function smsControlData()
    {
        if (auth::user()->hasPermissionTo('sms_control_list')) {
      

               $cronkernel = SmsControls::all();
        
        
               return DataTables::of($cronkernel)
                  
                        
                       
                    ->editColumn('status', function ($cronkernel) {
                               if($cronkernel->status == 1){
                
                                   return "Activated";
                               }else{
                                   return 'Deactivated';
                               }
                           })

                   
                   ->addColumn('actions', function ($cronkernel) {
                       $actions = '';
                       if (auth::user()->can('sms_control_edit')) {
                           $actions .= '<a href="' . route('admin.smsControl.edit', $cronkernel->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                       <i class="la la-edit"></i>
                                   </a>';
                       } 
                       if (auth::user()->can('sms_control_delete')) {
                           $actions .= '<a href="" value="' . $cronkernel->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                       <i class="la la-trash"></i>
                                   </a>';
                       }
                       return $actions;
                   })
                   ->rawColumns(['actions','status'])
                   ->make(true);
          
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
       public function smsControlgetModalDelete(Request $request)
    {
        $sms = SmsControls::where('id', $request->get('id'))
            ->count();
        // Check if we are not trying to delete ourselves
        $body = 'Are you sure you want to delete this sms Control function ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }
     public function smsControldestroy($id)
    {
        try
        {
            if (auth::user()->hasPermissionTo('sms_control_delete')) {
            activity('sms Control')->performedOn(Sms::where('id', $id)->first())
                ->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('sms Control Deleted');
            SmsControls::where('id', $id)->delete();

            return Redirect::route('admin.smsControl')
                ->with('success', 'Sms Control Deleted Successfully');
            }else{
                return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
            } 
        }
        catch(TeacherNotFoundException $e)
        {
            return Redirect::route('admin.smsControl')->with('error', 'Something Went Wrong');
        }

    }
}

