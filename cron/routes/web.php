<?php

use Illuminate\Support\Facades\Route;

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

// ELB Health check
Route::get('/health', [\AlphaDirect\Http\Controllers\DashboardController::class, 'health']);

// Cron Dashboard — graphite-v2-cron.alphadirect.co.bw
Route::get('/', [\AlphaDirect\Http\Controllers\DashboardController::class, 'index']);
Route::get('/jobs', [\AlphaDirect\Http\Controllers\DashboardController::class, 'jobs']);
Route::post('/jobs/{jobKey}/trigger', [\AlphaDirect\Http\Controllers\DashboardController::class, 'trigger']);
Route::get('/reports/download/{filename}', [\AlphaDirect\Http\Controllers\DashboardController::class, 'download']);
Route::get('/logs/{name}', [\AlphaDirect\Http\Controllers\DashboardController::class, 'log']);

Route::get('logsViewerData', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

// ── Backend-only log tail API ───────────────────────────────────────
// Called server-to-server by graphite-v2-be → /api/v1/cron-logs/tail.
// Auth via shared secret header X-Cron-Log-Token (CRON_LOG_TOKEN env).
// Never exposed to end-users directly — backend gates by admin role first.
Route::get('api/logs/tail',       [\AlphaDirect\Http\Controllers\LogTailController::class, 'tail']);
Route::get('api/logs/cron-names', [\AlphaDirect\Http\Controllers\LogTailController::class, 'cronNames']);
