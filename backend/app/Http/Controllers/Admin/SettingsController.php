<?php
namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    //shows settings index page
    public function settings()
    {
//        if (Auth::user()->hasPermissionTo('setting-list'))
//        {
            return view('admin.settings.index');
//        }
//        else
//        {
//            return Redirect::back()
//                ->with('error', 'Sorry! You do not have permission to access this page!');
//        }

    }
}

