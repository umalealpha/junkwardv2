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

// Route::prefix('cashback')->group(function() {
//     Route::get('/', 'CashbackController@index');
// });

Route::group(['middleware' => ['web','auth'],'prefix' => 'cashback'], function () {
	Route::get('/', 'CashbackController@index')->name('cashback');
	Route::get('/customer',function(){
		return view('cashback::datatable',[
			'tablename'=>"agents-table",
			'breadcrum'=>"header.customer",
		]);
	})->name('cashback.customer');
	Route::get('/report',function(){
		return view('cashback::datatable',[
			'tablename'=>"reports-table",
			'breadcrum'=>"header.agents",
		]);
	})->name('cashback.report');
	Route::group(['prefix' => 'settings'], function () {

        Route::get('/',function(){
			return view('cashback::datatable',[
				'tablename'=>"cashback-table",
				'breadcrum'=>"header.cashback",
			]);
		})->name('cashback.settings');

		Route::get('/create',function(){
			return view('cashback::datatable',[
				'tablename'=>"customercashback-form",
				'breadcrum'=>"header.cashback",
			]);
		})->name('cashback.settings.create');

        Route::get('edit/{id}','CashbackController@edit')
			->name('cashback.settings.edit');
	});

    Route::get('test','CashbackController@Testvent')->name('test');
});
