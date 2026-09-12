<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MenuChecking
{

	protected $except = [
        'MenuMaster',
		'AddMenuMaster',
		'dashboard',
		'menuMasterEdit'
    ];
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
		$allowed= false;
		if(auth()->user() && !in_array(request()->route()->getName(),$this->except)){
            $routeName = request()->route()->getName();
            // Cache MenuMaster lookups for 10 minutes — busted when menu is saved
            $menu = \Cache::remember('menu_' . $routeName, 600, function () use ($routeName) {
                return \App\Models\MenuMaster::where('menu_url', '=', $routeName)->first();
            });
			if(!$menu){
				abort(404);
			}
			#role Checking
			if($menu->availbale_for_roles!=""){
				if(is_array(json_decode($menu->availbale_for_roles))){
					$allowedRole=json_decode($menu->availbale_for_roles);
					if(auth()->user()->hasRole($allowedRole)){
						$allowed=true;
					}
				}
			}
			#Direct Permmision Checking Without Role
			if($menu->availbale_for_permission!=""){
				if(is_array(json_decode($menu->availbale_for_permission))){
					$allowedPerm=json_decode($menu->availbale_for_permission);
					if(auth()->user()->hasAnyDirectPermission($allowedPerm)){
						$allowed=true;
					}
				}
			}
		}
		if(in_array(request()->route()->getName(),$this->except)){
			$allowed=true;
		}
		if($allowed==true){
			return $next($request);
		}else{
			abort(403);
		}
    }
}
