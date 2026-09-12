<?php


/*
|--------------------------------------------------------------------------
| Orange API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Orange API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "orange" middleware group. Now create something great!
|
*/

Route::any('orangeOrder', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMoneyOrderNoification']);
Route::any('OrangeMandate', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandates']);
Route::any('OrangeMupdate', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandateUpdate']);
Route::any('OrangeMdispute', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandateDispute']);
