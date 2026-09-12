<?php

namespace Modules\Cashback\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CashbackController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('cashback::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('cashback::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('cashback::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */

    public function edit($id){
		abort_if(!auth()->user(), 403);
		request()->merge(['id'=>\Crypt::decrypt($id)]);
		request()->validate([
			'id'=>"required"
		]);
		$cashback = \Modules\Cashback\Entities\Cashback::findOrFail(\Crypt::decrypt($id));
		return view('cashback::form-edit',[
			'tablename'=>"customercashback-form",
			'breadcrum'=>"header.cashback",
			'data'=>$cashback
		]);
	}
    // public function edit($id)
    // {
    //     return view('cashback::edit');
    // }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    #for testing purposes only
    public function Testvent()
    {
        // $policy=[
        //     'plan_id'=>1,
        //     'product_id'=>1
        // ];
        $policy=\AlphaDirect\Policy::where('policyNumber','MIS2021003378')->first();
        $type=1;
        $status=1;
        event(new \Modules\Cashback\Events\CustomerCashbackEvent($policy,$type,$status));
    }
}
