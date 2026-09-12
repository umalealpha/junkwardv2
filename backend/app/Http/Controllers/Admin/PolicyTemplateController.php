<?php
namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\PolicyTemplate;
use AlphaDirect\TemplateFields;
use AlphaDirect\User;
use AlphaDirect\Hook;
use Illuminate\Support\Facades\Auth;
use Validator;
use Session;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;

class PolicyTemplateController extends Controller
{
    /**
     * Show a list of all Policy Template
     *
     * @return View Policy Template index page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('policy-doc-template-list'))
        {
            // Show the page
            return view('admin.policyTemplates.index');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
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
        $policyTemplate = PolicyTemplate::get(array(
            'id',
            'name',
            'hook_slug',
            'text',
            'created_at',
            'updated_at'
        ));
        return DataTables::of($policyTemplate)
            ->addColumn('hook_name', function ($policyTemplate)
            {
                $hook = Hook::where('slug', $policyTemplate->hook_slug)
                    ->first(array(
                        'name'
                    ));

                return $hook ? $hook->name : '';
            })
            ->addColumn('actions', function ($policyTemplate)
            {
                $actions = '<a href="' . route('admin.policyTemplate.edit', $policyTemplate->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>
                            <a href="" value="' . $policyTemplate->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                return $actions;
            })
            ->rawColumns(['actions', 'text'])
            ->make(true);
    }

    /**
     * method to impliment ckeupload
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
     * Show a page to policy Templates create
     *
     * @return View policy Templates create page
     */
    public function create()
    {
        if (Auth::user()->hasPermissionTo('policy-doc-template-create'))
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
            return view("admin.policyTemplates.create", compact('templatefields', 'hooks'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store accounts data from policy Templates.
     *
     * @return View policy Templates index page
     */
    public function store(Request $request)
    {
        $policyTemplate = new PolicyTemplate();
        $policyTemplate->name = $request->get('name');
        $policyTemplate->slug = $request->get('name');
        $policyTemplate->text = $request->get('name');
        $policyTemplate->hook_slug = $request->get('hook');
        if ($policyTemplate->save())
        {
            activity('Policy Document Template')
                ->performedOn($policyTemplate)->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Policy Document Template Created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.policyTemplate.index')
                ->with('success', 'New Policy document template Created Successfully');
        }
        else
        {
            return Redirect::route('admin.policyTemplate.index')
                ->with('error', 'Something Went Wrong');
        }

    }

    /**
     * Show a page to edit specific policy Templates
     * param: policy Templates id ($id)
     * @return View policy Templates view page
     */
    public function edit($id)
    {
        if (Auth::user()->hasPermissionTo('policy-doc-template-edit'))
        {
            $policyTemplate = PolicyTemplate::where('id', $id)->first();
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
            return view('admin.policyTemplates.edit', compact('policyTemplate', 'templatefields', 'hooks'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update accounts data from edit page.
     *param: policy Templates id($id)
     * @return policy Templates index page
     */
    public function update($id, Request $request)
    {
        $policyTemplate = PolicyTemplate::where('id', $id)->first();
        $policyTemplate->name = $request->get('name');
        $policyTemplate->slug = $request->get('name');
        $policyTemplate->text = $request->get('summary-ckeditor');
        $policyTemplate->hook_slug = $request->get('hook');
        if ($policyTemplate->save())
        {
            // Redirect to the home page with success menu
            activity('Policy Document Template')
                ->performedOn($policyTemplate)->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Policy Document Template Updated');
            return Redirect::route('admin.policyTemplate.index')
                ->with('success', 'Policy document template updated successfully');
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
     * @return View json
     */
    public function getModalDelete(Request $request)
    {
        $policyTemplate = PolicyTemplate::where('id', $request->get('id'))
            ->count();
        // Check if we are not trying to delete ourselves
        $body = 'Are you sure you want to delete the  policy document template ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

    /**
     *deletes specific policy Templates accounts
     *param: policy Templates id ($id)
     * @return policy Templates index page
     */
    public function destroy($id)
    {
        try
        {

            activity('Policy Document Template')->performedOn(PolicyTemplate::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Policy Document Template Deleted');
            PolicyTemplate::where('id', $id)->delete();

            return Redirect::route('admin.policyTemplate.index')
                ->with('success', 'Policy document template Deleted Successfully');

        }
        catch(TeacherNotFoundException $e)
        {
            return Redirect::route('admin.policyTemplate.index')->with('error', 'Something Went Wrong');
        }

    }

}

