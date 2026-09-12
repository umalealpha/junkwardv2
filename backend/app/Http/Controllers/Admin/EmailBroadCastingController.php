<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Hook;
use AlphaDirect\Models\SMSEmailLog;
use AlphaDirect\Models\SMSEmailLogs;
use AlphaDirect\TemplateFields;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use AlphaDirect\User;
use Redirect;


class EmailBroadCastingController extends Controller
{
    /**
     * Show a list of all email Broad Casting
     *
     * @return View email Broad Casting index page
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('email-template-list'))
        {
            return view("admin.emailBroadCasting.index");
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function emaillogs(){
        if(Auth::user()->hasPermissionTo('email-template-list'))
        {
            return view("admin.emailBroadCasting.delievered_email");
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function delieveredEmails(){
        $data = SMSEmailLogs::where('type','Email')->orderBy('id','desc')->get();
        return DataTables::of($data)
            ->addColumn('actions',function($data)
            {
                $actions = '';
                if($data->content != null) {
                    $actions .= '<a href="' . route('admin.emailBroadCasting.getEmailDetails', $data->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" target="_blank" title="View Details">
                                <i class="la la-eye"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function getEmailDetails($id)
    {
        $data = SMSEmailLogs::where('id', $id)->first();
        if ($data->content != null) {
            $content = unserialize($data->content, ['allowed_classes' => false]);
            return view("admin.emailBroadCasting.email_detail", compact('content'));
        }else{
            return Redirect::back()->with('error', 'No content found!');
        }
    }



    /**
     * Show a page to email Broad Casting create
     *
     * @return View email Broad Casting create page
     */
    public function create()
    {
        if(Auth::user()->hasPermissionTo('email-template-create'))
        {
            $template_fields = TemplateFields::get(array('id','tablename','table_name','field','field_name'));
            $hooks = Hook::get(array('slug','name'));
            return view("admin.emailBroadCasting.create",compact('template_fields','hooks'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }


    /*
    * Pass data through ajax call
    */
    /**
     * @return mixed
     */
    public function data()
    {
        $emailBroadcasting = emailBroadcasting::get(['id','name','hook_slug', 'text','created_at']);
        return DataTables::of($emailBroadcasting)
            ->addColumn('hook_name',function($emailBroadcasting)
            {
                $hook = Hook::where('slug',$emailBroadcasting->hook_slug)->first(array('name'));
                return $hook?$hook->name:'';
            })

            ->addColumn('actions',function($emailBroadcasting)
            {
                $actions = '';
                if(Auth::user()->hasPermissionTo('email-template-edit'))
                {
                    $actions .= '<a href="' . route('admin.emailBroadCasting.edit', $emailBroadcasting->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.emailBroadCasting.edit', $emailBroadcasting->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if(Auth::user()->hasPermissionTo('email-template-delete'))
                {
                    $actions.= '<a href="" value="'.$emailBroadcasting->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions','text'])
            ->make(true);
    }

    /**
     * method to store accounts data from email Broad Casting page.
     *
     * @return View email Broad Casting index page
     */
    public function store(Request $request)
    {
        $emailBroadCast = new EmailBroadcasting();
        $emailBroadCast->name = $request->get('name');
        $emailBroadCast->slug = $request->get('name');
        $emailBroadCast->text = $request->get('summary-ckeditor');
        $emailBroadCast->hook_slug = $request->get('hook');
        $emailBroadCast->subject = $request->get('subject');
        if($emailBroadCast->save())
        {
            activity('Email Template')
                ->performedOn($emailBroadCast)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Email Template Created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.emailBroadCasting.index')->with('success', 'New email template Created Successfully');
        }
        else
        {
            return Redirect::route('admin.emailBroadCasting.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * Show a page to edit specific email Broad Casting
     * param: email Broad Casting id ($id)
     * @return View email Broad Casting view page
     */
    public function edit($id)
    {
        $emailBroadCast = emailBroadcasting::where('id',$id)->first();
            $template_fields = TemplateFields::get(array('id','tablename','table_name','field','field_name'));
            $hooks = Hook::get(array('slug','name'));
        if(Auth::user()->hasPermissionTo('email-template-edit'))
        {
            return view('admin.emailBroadCasting.edit',compact('emailBroadCast','template_fields','hooks'));
        }
        elseif(Auth::user()->hasPermissionTo('email-template-list'))
        {
            return view('admin.emailBroadCasting.view',compact('emailBroadCast','template_fields','hooks'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update accounts data from edit page.
     *param: email Broad Casting id($id)
     * @return email Broad Casting index page
     */
    public function update($id, Request $request)
    {
        $emailBroadCast = EmailBroadcasting::where('id', $id)->first();
        $emailBroadCast->name = $request->get('name');
        $emailBroadCast->slug = $request->get('name');
        $emailBroadCast->text = $request->get('summary-ckeditor');
        $emailBroadCast->hook_slug = $request->get('hook');
        $emailBroadCast->subject = $request->get('subject');
        if($emailBroadCast->save())
        {
            // Redirect to the home page with success menu
            activity('Email Template')
                ->performedOn($emailBroadCast)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Email Template Updated');
            return Redirect::route('admin.emailBroadCasting.index')->with('success', 'Email template updated successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to impliment ckeupload
     */
    public function ckeupload()
    {
        $upload_dir = array(
            'img' => public_path().'/uploads/',
        );

        $imgset = array(
            'minwidth' => 10,
            'minheight' => 10,
            'type' => array('bmp', 'gif', 'jpg', 'jpeg', 'png'),
        );

        // If 0, will OVERWRITE the existing file
        define('RENAME_F', 1);

        // init
        $re = array(
            'uploaded' => false,
        );
        if (isset($_FILES['upload']) && strlen($_FILES['upload']['name']) > 1)
        {
            define('F_NAME', preg_replace('/\.(.+?)$/i', '', basename($_FILES['upload']['name'])));  //get filename without extension

            // get protocol and host name to send the absolute image path to CKEditor
            $site = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';
            $sepext = explode('.', strtolower($_FILES['upload']['name']));
            $type = end($sepext);    // gets extension
            $upload_dir = in_array($type, $imgset['type']) ? $upload_dir['img'] : $upload_dir['audio'];

            //checkings for image or audio
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
                if (RENAME_F == 1 && file_exists($p . $fn . $ex)) return setFName($p, F_NAME . '_' . ($i + 1), $ex, ($i + 1));
                else return $fn . $ex;
            }

            $f_name = setFName($upload_dir, F_NAME, ".$type", 0);
            $uploadpath = $upload_dir . $f_name;  // full file path

            if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadpath))
            {

                    //$CKEditorFuncNum = getUrlParam['CKEditorFuncNum'];
                    $url = url('/').'/uploads/questions/' . $f_name;
                    $msg = F_NAME . '.' . $type . ' successfully uploaded: \\n- Size: ' . number_format($_FILES['upload']['size'] / 1024, 2, '.', '') . ' KB';

                    $re['uploaded'] = true;
                    $re['url'] = $url;
                    $re['fileName'] = $f_name;

                    @header('Content-type: text/html; charset=utf-8');
                    $re = json_encode($re);
                    echo $re;
                    //print_r($re);
            }
                else $re = 'alert("Unable to upload the file")';

        }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View json
     */
    public function getModalDelete(Request $request)
    {
        $check = emailBroadcasting::where('id', $request->get('id'))->count();
        $body = 'Are you sure you want to delete the  email template ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
    }

    /**
     *deletes specific email Broad Casting accounts
     *param: email Broad Casting id ($id)
     * @return email Broad Casting index page
     */
    public function destroy($id)
    {
        try {
            activity('Email Template')
                ->performedOn(emailBroadcasting::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()->user()->id)->first())
                ->log('Email Template Deleted');
            emailBroadcasting::where('id', $id)->delete();
            return Redirect::route('admin.emailBroadCasting.index')->with('success', 'Email template Deleted Successfully');

        }
        catch(TeacherNotFoundException $e)
        {
            return Redirect::route('admin.emailBroadCasting.index')->with('error', 'Something Went Wrong');
        }

    }

//    public function storeMailgunResp(){
//        try{
//            $log = new SMSEmailLogs();
//            $log->to_email = $data['email'];
//            $log->customer_id = (isset($data['customer_id']) && $data->customer_id != null) ? isset($data->customer_id) : null;
//            $log->type = $data['EMail'];
//            $log->hook = $data['hook'];
//            $log->attachments = serialize($data->attachment);
//            $log->status = 'Sent';
//            $log->save();
//        }catch(){
//
//        }
//    }

}
