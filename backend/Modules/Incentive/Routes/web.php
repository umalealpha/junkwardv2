<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::group(['middleware' => ['web','auth'],'prefix' => 'incentive'], function () {
	Route::get('/', 'IncentiveController@index')->name('incentive');
	Route::get('/agents',function(){
		return view('incentive::datatable',[
			'tablename'=>"agents-table",
			'breadcrum'=>"header.agents",
		]);
	})->name('incentive.agents');
	Route::get('/report',function(){
		return view('incentive::datatable',[
			'tablename'=>"reports-table",
			'breadcrum'=>"header.agents",
		]);
	})->name('incentive.report');
	Route::group(['prefix' => 'settings'], function () {
		Route::get('/',function(){
			return view('incentive::datatable',[
				'tablename'=>"incentive-table",
				'breadcrum'=>"header.incentive",
			]);
		})->name('incentive.settings');
		Route::get('/create',function(){
			return view('incentive::datatable',[
				'tablename'=>"incentive-form",
				'breadcrum'=>"header.incentive",
			]);
		})->name('incentive.settings.create');

        Route::get('edit/{id}','IncentiveController@edit')
			->name('incentive.settings.edit');
	});
});
