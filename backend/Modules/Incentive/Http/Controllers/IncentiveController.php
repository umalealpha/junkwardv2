<?php

namespace Modules\Incentive\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IncentiveController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('incentive::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('incentive::create');
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
        return view('incentive::show');
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
		$incentive = \Modules\Incentive\Entities\Incentive::findOrFail(\Crypt::decrypt($id));
		return view('incentive::form-edit',[
			'tablename'=>"incentive-form",
			'breadcrum'=>"header.incentive",
			'data'=>$incentive
		]);
	}

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
}
