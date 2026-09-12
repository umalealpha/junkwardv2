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
Route::group(['middleware' => ['web','auth'],'prefix' => 'inventory'], function () {
	Route::get('/', 'InventoryController@index')->name('inventory');
	Route::prefix('warehouse')->group(function () {
		Route::get('/',function(){
			return view('inventory::datatable',[
				'tablename'=>"ware-house-table",
				'breadcum'=>"header.warehouse",
			]);
		})->name('inventory.warehouse');
		
		Route::get('create',function(){
			return view('inventory::datatable',[
				'tablename'=>"warehouse-form",
				'breadcum'=>"header.warehouse",
			]);
		})->name('inventory.warehouse.create');
		
		Route::get('edit/{id}','InventoryController@warehouseEdit')
		->name('inventory.warehouse.edit');
		
		Route::get('/stock/{id?}',function(){
			return view('inventory::datatable',[
				'tablename'=>"wirehouse-stock",
				'breadcum'=>"header.wirehouse-stock",
			]);
		})->name('inventory.warehouse.stock');
		
		Route::get('/stock-reduce',function(){
			return view('inventory::datatable',[
				'tablename'=>"wirehouse-stock-reduce",
				'breadcum'=>"header.wirehouse-stock",
			]);
		})->name('inventory.warehouse.stock-reduce');
	});
	Route::prefix('stores')->group(function () {
		Route::get('/',function(){
			return view('inventory::datatable',[
				'tablename'=>"stores-table",
				'breadcum'=>"header.stores",
			]);
		})->name('inventory.stores');
		Route::get('/create',function(){
			return view('inventory::datatable',[
				'tablename'=>"stores-form",
				'breadcum'=>"header.stores",
			]);
		})->name('inventory.stores.create');

		Route::get('edit/{id}','InventoryController@storesEdit')
			->name('inventory.stores.edit');

        Route::get('/removeStock/{id}','InventoryController@storesRemoveStock')
        ->name('inventory.stores.removestorestock');

		Route::get('/stock/{id?}',function(){
			return view('inventory::datatable',[
				'tablename'=>"store-stock",
				'breadcum'=>"header.stores",
			]);
		})->name('inventory.stores.stock');
		
		Route::get('partner',function(){
			return view('inventory::datatable',[
				'tablename'=>"stores-partner-table",
				'breadcum'=>"header.partner",
			]);
		})->name('inventory.store.patner');
		
		Route::get('/stock-reduce',function(){
			return view('inventory::datatable',[
				'tablename'=>"stores-stock-reduce",
				'breadcum'=>"header.stores-stock",
			]);
		})->name('inventory.stores.stock-reduce');
		
	});
    Route::prefix('activitylog')->group(function () {
		Route::get('/',function(){
			return view('inventory::datatable',[
				'tablename'=>"activitylog-table",
				'breadcum'=>"header.activity-log",
			]);
		})->name('inventory.activitylog');
    });
});
