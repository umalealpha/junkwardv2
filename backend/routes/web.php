<?php

use AlphaDirect\Http\Controllers\DevPortalController;
use AlphaDirect\Http\Middleware\DevPortalGate;
use AlphaDirect\Http\Controllers\ADGroupPolicyFileController;
use AlphaDirect\Http\Controllers\HrController;
use Illuminate\Support\Facades\Route;
use AlphaDirect\Http\Controllers\Auth\LoginController;
use AlphaDirect\Http\Controllers\Admin\UserController;
use AlphaDirect\Http\Controllers\AdminController;
use AlphaDirect\Http\Controllers\Admin\ActivityLogController;
use AlphaDirect\Http\Controllers\Admin\AgencyController;
use AlphaDirect\Http\Controllers\ConfigController;
use AlphaDirect\Http\Controllers\LeadsController;
use AlphaDirect\Http\Controllers\QuoteController;
use AlphaDirect\Http\Controllers\VehicleController;
use AlphaDirect\Http\Controllers\Admin\Suppliers\SupplierController;
use AlphaDirect\Http\Controllers\TransactionController;
use AlphaDirect\Http\Controllers\TreatyController;
use AlphaDirect\Http\Controllers\UploadController;
use AlphaDirect\Http\Controllers\RiskTypeController;
use AlphaDirect\Http\Controllers\ReconciliationController;
use AlphaDirect\Http\Controllers\PrintController;
use AlphaDirect\Http\Controllers\BitrixAgentController;
use AlphaDirect\Http\Controllers\Admin\CustomerInspection;
use AlphaDirect\Http\Controllers\ChatbotController;
use AlphaDirect\Http\Controllers\ClaimQuestionsController;
use AlphaDirect\Http\Controllers\ClaimQuestionValuesController;
use AlphaDirect\Http\Controllers\Admin\ActivationController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\BranchesController;
use AlphaDirect\Http\Controllers\Admin\BillingController;
use AlphaDirect\Http\Controllers\Admin\ClaimReportController;
use AlphaDirect\Http\Controllers\Admin\ClaimsController;
use AlphaDirect\Http\Controllers\Admin\CommissionReportController;
use AlphaDirect\Http\Controllers\Admin\CommissionController;
use AlphaDirect\Http\Controllers\Admin\ReviewController;
use AlphaDirect\Http\Controllers\Admin\EmailBroadCastingController;
use AlphaDirect\Http\Controllers\PolicyFileController;
use AlphaDirect\Http\Controllers\Admin\AccountsController;
use AlphaDirect\Http\Controllers\Admin\EmailController;
use AlphaDirect\Http\Controllers\Admin\FactorMainController;
use AlphaDirect\Http\Controllers\Admin\FactorSubTypeController;
use AlphaDirect\Http\Controllers\Admin\MasterController;
use AlphaDirect\Http\Controllers\Admin\PaymentVendorController;
use AlphaDirect\Http\Controllers\Admin\PolicyTemplateController;
use AlphaDirect\Http\Controllers\Admin\ProductPlanController;
use AlphaDirect\Http\Controllers\Admin\ProductTypeController;
use AlphaDirect\Http\Controllers\Admin\ReinsuranceTreatyController;
use AlphaDirect\Http\Controllers\Admin\ReinsuranceTypeController;
use AlphaDirect\Http\Controllers\Admin\ReinsuranceGroupCovController;
use AlphaDirect\Http\Controllers\Admin\CoveragesController;
use AlphaDirect\Http\Controllers\Admin\SpecifiedCoveragesItemsController;
use AlphaDirect\Http\Controllers\Admin\SubCoveragesController;
use AlphaDirect\Http\Controllers\Admin\RegionController;
use AlphaDirect\Http\Controllers\Admin\ReratingController;
use AlphaDirect\Http\Controllers\Admin\RolesController;
use AlphaDirect\Http\Controllers\Admin\MenuMasterController;
use AlphaDirect\Http\Controllers\Admin\CustomerKycController;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\ReportController;
use AlphaDirect\Http\Controllers\Admin\ReinsuranceFormulaController;
use AlphaDirect\Http\Controllers\Admin\RulesController;
use AlphaDirect\Http\Controllers\Admin\TransactionReportController;
use AlphaDirect\Http\Controllers\Admin\PolicyReportController;
use AlphaDirect\Http\Controllers\Admin\ScheduleController;
use AlphaDirect\Http\Controllers\Admin\ProductController;
use AlphaDirect\Http\Controllers\Admin\SettingsController;
use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use AlphaDirect\Http\Controllers\Admin\VaultController;
use AlphaDirect\Http\Controllers\Admin\SmsController;
use AlphaDirect\Http\Controllers\Admin\SmsLogsController;
use AlphaDirect\Http\Controllers\Admin\SubLedgerController;
use AlphaDirect\Http\Controllers\Admin\VcsEventLogController;
use AlphaDirect\Http\Controllers\Admin\VendorsController;
use AlphaDirect\Http\Controllers\Admin\RepairController;
use AlphaDirect\Http\Controllers\Admin\FailedTransactionController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Http\Controllers\OrganizationDocumentController;
use AlphaDirect\Http\Controllers\CoverageController;
use AlphaDirect\Http\Controllers\PolicyQuestionsController;
use AlphaDirect\Http\Controllers\AgentController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Http\Controllers\PaymentUrlsController;
use AlphaDirect\Http\Controllers\Agents\Claims\ClaimsAgentController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Admin\KycFieldsController;
use AlphaDirect\Http\Controllers\Admin\KycComplianceController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Controllers\RenewController;
use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Http\Controllers\WhatsAppController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use AlphaDirect\Http\Controllers\Admin\PolicySonaliController;
use AlphaDirect\Http\Controllers\CancelPolicyRequestController;
use \AlphaDirect\Http\Controllers\Admin\RewardTierController;
use AlphaDirect\Http\Controllers\CustomerRewardController;
use AlphaDirect\Http\Controllers\EmployerGroupController;
use AlphaDirect\Http\Controllers\KycUploadController;
use AlphaDirect\Http\Controllers\PricingController;
use AlphaDirect\Http\Controllers\Admin\ReinsuranceController;
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


Route::get('/testSalesReport', function () {
    return view('admin.notes.generateMonthlySalesReport');
});

Route::get('/testSales', [PolicyController::class,'testSalesReport'])->name('testSales');

Route::get('/', function () {
    return view('auth.login');
});
Route::get('/reset-password', function () {
    return view('auth.passwords.expired');
});

Route::get('clear_cache', function () {

    \Artisan::call('optimize:clear');
    \Artisan::call('view:clear');
    \Artisan::call('cache:clear');
    \Artisan::call('config:clear');

    return back()->with('success','Cache is cleared and optimised');

});


Route::get('/error', function () {
    return view('error');
});

Route::get('/proceed', function () {
	return view('proceed');
})->name('proceed');

// ─── Claimant self-service claim-status page (Claims Tracker -> Graphite,
// Phase 2). Public, mobile-first, brand-styled. Replaces the tracker's
// track.html. The page itself renders no claim data server-side — it only
// hosts the JS that calls the OTP-gated /api/public/v1/claim-tracking/* API.
// Gated by the `claimant_tracking` runtime flag (default OFF): when off the
// page 404s so the feature is invisible until armed. The optional {token}
// segment lets a pre-issued claim link deep-link straight into the flow.
// ─── Claimant claim-form page. Public, no password: the token in the URL is
// the credential, which is the whole point — a claimant completing their own
// claim form should not be made to create an account. The page renders no claim
// data server-side; it only hosts the JS that calls the token-scoped
// /api/public/v1/claim-form/* endpoints. 404s when the feature is off, so it is
// invisible until armed.
Route::get('/claim-form/{token}', function ($token) {
    if (!\AlphaDirect\Services\Claims\ClaimFormDispatchService::enabled()) {
        abort(404);
    }
    return view('claim_forms.public', ['token' => $token]);
})->name('claim_form_public');

Route::get('/claim-status/{token?}', function ($token = null) {
    if (!\AlphaDirect\Services\ClaimTrackingService::isEnabled()) {
        abort(404);
    }
    return view('claim_tracking.status', ['token' => $token]);
})->name('claim_status');

Route::get('/activate', [PolicyController::class,'createTemp'])->name('tempActivation');

Route::any('/vcsAuth', [PaymentController::class, 'handleAuth']);

Route::any('/getBranches', [AlphaDirect\Http\Controllers\Payment\RealPay\RealpayController::class, 'getBranches']);
Route::any('/getBanks', [AlphaDirect\Http\Controllers\Payment\RealPay\RealpayController::class, 'getBanks']);




Route::group(['prefix' => 'admin', 'middleware' => ['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer']], function () {

    // ─── API Error Log Explorer ─────────────────────────────────
    Route::prefix('api-error-log')->name('admin.api-error-log.')->group(function () {
        Route::get('/',                  [\AlphaDirect\Http\Controllers\Admin\ApiErrorLogController::class, 'index'])      ->name('index');
        Route::get('/export',            [\AlphaDirect\Http\Controllers\Admin\ApiErrorLogController::class, 'export'])     ->name('export');
        Route::get('/test',              [\AlphaDirect\Http\Controllers\Admin\ApiErrorLogController::class, 'testTool'])   ->name('test');
        Route::post('/test/run',         [\AlphaDirect\Http\Controllers\Admin\ApiErrorLogController::class, 'testRun'])    ->name('test.run');
        Route::get('/{id}',              [\AlphaDirect\Http\Controllers\Admin\ApiErrorLogController::class, 'show'])       ->name('show');
        Route::post('/{id}/investigate', [\AlphaDirect\Http\Controllers\Admin\ApiErrorLogController::class, 'investigate'])->name('investigate');
    });

    // ─── Payment Intelligence (Reconciliation) ─────────────────
    Route::prefix('payment-intelligence')->name('admin.payment-intelligence.')->group(function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'dashboard'])->name('dashboard');
        Route::get('/anomalies', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'anomalies'])->name('anomalies');
        Route::get('/anomalies/data', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'anomaliesData'])->name('anomalies.data');
        Route::get('/check/{type}', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'checkByType'])->name('check');
        Route::post('/trigger-run', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'triggerRun'])->name('trigger');
        Route::get('/export', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'export'])->name('export');
        Route::post('/anomalies/{id}/update-status', [\AlphaDirect\Http\Controllers\Admin\PaymentIntelligenceController::class, 'updateStatus'])->name('anomalies.updateStatus');
    });

    Route::get('reports/policy-cancelled-report', [\AlphaDirect\Http\Controllers\PolicyCancelledReportController::class,'policyCanceledReport'])->name('policy_canceled_report');
    Route::get('reports/policy-cancelled-report/data', [\AlphaDirect\Http\Controllers\PolicyCancelledReportController::class,'policyCanceledReportData'])->name('policy_canceled_report_data');

    Route::get('/bundle/edit', [AlphaDirect\Http\Controllers\WarehouseController::class, 'bundleEdit'])->name('warehouses.bundle.edit');
    Route::post('/bundle/update', [AlphaDirect\Http\Controllers\WarehouseController::class, 'bundleUpdate'])->name('warehouses.bundle.update');
    Route::get('warehouses/data', [AlphaDirect\Http\Controllers\WarehouseController::class, 'data'])->name('warehouses.data');
    Route::get('warehouses/unique-warehouse', [AlphaDirect\Http\Controllers\WarehouseController::class, 'checkName'])->name('warehouses.checkName');
    Route::resource('warehouses', 'WarehouseController');

    // Re-KYC Public Routes
    Route::prefix('rekyc')->name('rekyc.')->group(function () {
        Route::get('/upload-documents/{token}', function($token) {
            return view('rekyc.upload-documents', compact('token'));
        })->name('upload-documents');
        Route::post('/upload-documents/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'uploadDocuments'])->name('process-upload');
        Route::get('/complete/{token}', function($token) {
            return view('rekyc.complete', compact('token'));
        })->name('complete');
    });
    Route::prefix('deduplication-checks')->group(function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'index'])->name('admin.deduplication-checks.index');
        Route::get('/{id}', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'show'])->name('admin.deduplication-checks.show');
        Route::post('/datatable', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'getDataTableData'])->name('admin.deduplication-checks.datatable');
        Route::post('/generate-links', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'generateLinks'])->name('admin.deduplication-checks.generate-links');
        Route::put('/{id}/verification', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'updateVerificationStatus'])->name('admin.deduplication-checks.update-verification');
        Route::post('/resend-notification', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'resendNotification'])->name('admin.deduplication-checks.resend-notification');
    });

    // Duplicate Customers Admin Routes
    Route::prefix('duplicate-customers')->name('admin.duplicate-customers.')->group(function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'index'])->name('index');
        Route::get('/data', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'data'])->name('data');
        Route::get('/export', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'export'])->name('export');
        Route::get('/{id}', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'update'])->name('update');
        Route::delete('/{id}', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/update-status', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'updateStatus'])->name('update-status');
        Route::post('/bulk-update-status', [\AlphaDirect\Http\Controllers\Admin\DuplicateCustomerController::class, 'bulkUpdateStatus'])->name('bulk-update-status');
    });

    // AD Group KYC Public Routes
    Route::prefix('ad-group-kyc')->name('ad-group-kyc.')->group(function () {
        Route::get('/verify/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'showVerifyPage'])->name('verify');
        Route::post('/verify/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'processVerification'])->name('verify.submit');
        Route::get('/complete/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'showCompletePage'])->name('complete');
        Route::get('/customer-data/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'getCustomerData'])->name('customer-data');
    });

    // Re-KYC Admin Routes
    Route::prefix('rekyc')->name('admin.rekyc.')->group(function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'index'])->name('index');

        // Campaign routes
        Route::get('/campaign/{id}', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'showCampaign'])->name('campaign.show');
        Route::get('/campaign/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'editCampaign'])->name('campaign.edit');
        Route::put('/campaign/{id}', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'updateCampaign'])->name('campaign.update');
        Route::post('/campaign', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'storeCampaign'])->name('campaign.store');

        // Link routes
        Route::get('/link/{id}', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'showLink'])->name('link.show');

        // Document routes
        Route::get('/document/{documentId}/download', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'downloadDocumentWeb'])->name('document.download');

        // Notification routes
        Route::post('/resend-notification', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'resendNotification'])->name('resend-notification');
        Route::post('/bulk-notifications', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'sendBulkNotifications'])->name('bulk-notifications');
        Route::post('/send-reminders', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'sendReminders'])->name('send-reminders');
        Route::post('/send-escalations', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'sendEscalations'])->name('send-escalations');

        // Export routes
        Route::get('/export', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'exportCampaignData'])->name('export');

        // AJAX routes
        Route::get('/campaign/links', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'getCampaignLinks'])->name('campaign.links');
        Route::get('/campaign/{id}/links-data', [\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class, 'getCampaignLinksData'])->name('campaign.links.data');
    });

    // AD Group KYC Admin Routes
    Route::prefix('ad-group-kyc')->name('admin.ad-group-kyc.')->group(function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'index'])->name('index');
        Route::get('/test', function() { return 'AD Group KYC Test Route'; })->name('test');

        // Campaign routes
        Route::get('/campaign/{id}', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'showCampaign'])->name('campaign.show');
        Route::get('/campaign/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'editCampaign'])->name('campaign.edit');
        Route::put('/campaign/{id}', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'updateCampaign'])->name('campaign.update');
        Route::post('/campaign', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'storeCampaign'])->name('campaign.store');

        // Link routes
        Route::post('/campaign/{id}/generate-links', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'generateLinks'])->name('campaign.generate-links');
        Route::post('/campaign/{id}/send-links', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'sendLinks'])->name('campaign.send-links');
        Route::get('/link/{id}', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycLinkController::class, 'getLinkDetails'])->name('link.details');

        // Notification routes
        Route::post('/resend-notification', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'resendNotification'])->name('resend-notification');
        Route::post('/bulk-notifications', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'sendBulkNotifications'])->name('bulk-notifications');
        Route::post('/send-reminders', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'sendReminders'])->name('send-reminders');
        Route::post('/send-escalations', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'sendEscalations'])->name('send-escalations');

        // Export routes
        Route::get('/export', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'exportCampaignData'])->name('export');

        // AJAX routes
        Route::get('/campaign/{id}/links', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycAdminController::class, 'getCampaignLinksData'])->name('campaign.links');
        Route::get('/campaign/{id}/links-data', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycLinkController::class, 'getCampaignLinks'])->name('campaign.links-data');
        Route::post('/link/{id}/resend', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycLinkController::class, 'resendLink'])->name('link.resend');
        Route::get('/campaign/{id}/links-by-status', [\AlphaDirect\Http\Controllers\Admin\AdGroupKycLinkController::class, 'getLinksByStatus'])->name('campaign.links-by-status');
    });

    // DeduplicationChecks Admin Routes
    Route::prefix('deduplication')->name('admin.deduplication.')->group(function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'index'])->name('index');
        Route::get('/data', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'getData'])->name('data');
        Route::get('/test-data', function() {
            return response()->json([
                'draw' => 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ]);
        })->name('test-data');
        Route::get('/{id}', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'update'])->name('update');
        Route::post('/{id}/resend-notification', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'resendNotification'])->name('resend-notification');
        Route::get('/export/data', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'export'])->name('export');
    });
});

// Test route for AD Group KYC
Route::get('/test-ad-group-kyc', function() { 
    return 'AD Group KYC Test Route - Routes are working!'; 
})->name('test.ad-group-kyc');

Route::group(['middleware' => ['web']], function () {
    #dpo
    // Route::any('saveonlinepayment/{policy_number}/{amount}/{status}', [\AlphaDirect\Http\Controllers\DpoPaymentController::class,'saveOnlinePayment']);

    
    #Temp Activation
    Route::get('/activate', [PolicyController::class, 'createTemp'])->name('tempActivation');
    Route::get('thankyou', [PolicyController::class, 'getThankYouPage'])->name('thankyou');
    Route::post('ActivatePolicy', [PolicyController::class, 'ActivatePolicy'])->name('ActivatePolicy');
    Route::post('CancelDocumentUpload', [PolicyController::class, 'CancelDocumentUpload'])->name('CancelDocumentUpload');
    Route::get('premiumCalculate', [PolicyController::class, 'premiumCalculate'])->name('premiumCalculate');

    # End Temp Activation

    Route::get('supplierUploadQuoteView/{quote_id}', [AlphaDirect\Http\Controllers\Suppliers\SuppliersController::class,'uploadQuoteView'])->name('supplierViewQuote');
    Route::post('supplierUpload', [AlphaDirect\Http\Controllers\Suppliers\SuppliersController::class,'uploadQuote'])->name('supplierUpload');
    Route::post('submitQuote', [AlphaDirect\Http\Controllers\Suppliers\SuppliersController::class,'submitQuote'])->name('submitQuote');
    Route::get('assessorView/{id}', [ClaimsController::class,'assessorView'])->name('assessorView');
    // Route::post('assessorUpload2/{id}', [ClaimsController::class,'assessorView']);
    Route::post('assessorUpload2/{id}', [ClaimsController::class,'assessorUpload2']);

    // Route::prefix('policy')->group(function () {
    //     Route::get('/', [\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'index'])->name('policy');
    //     Route::get('create/{id?}',[\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'policyCreate'])->name('policyCreate');
    //     Route::post('store/{id?}',[\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'savePolicy'])->name('policyStore');
    //     Route::get('edit/{id}',[\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'policyEdit'])->name('policyEdit');
    // });

    // Livewire PolicyController disabled - class doesn't exist (API-only mode)
    // Route::group(['prefix' => 'policy'], function () {
    //     Route::get('/', [\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'index'])->name('policy');
    //     Route::get('create/{id?}',[\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'policyCreate'])->name('policyCreate');
    //     Route::post('store/{id?}',[\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'savePolicy'])->name('policyStore');
    //     Route::get('edit/{id}',[\AlphaDirect\Http\Controllers\Livewire\PolicyController::class, 'policyEdit'])->name('policyEdit');
    // });

    // Route::group(['prefix' => 'policy'], function () {
    //     Route::get('/', [\App\Http\Controllers\Admin\PolicyController::class, 'data'])->name('policy');
	// 	Route::get('create',function(){return view('livewire.claim');})->name('policyCreate');
	// });

});

Route::post('updatePolicy', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class,'updatePolicyInfo'])->name('updatePolicy');

//DB Vehicle Route
Route::post('/getCarMakes', [VehicleController::class, 'getMakes'])->name('getCarMakesModel');
Route::post('/getCarModel', [VehicleController::class, 'getCarModel'])->name('getCarModel');
Route::post('/getTrueCarModel', [VehicleController::class, 'getTrueCarModel'])->name('getTrueCarModel');
Route::post('/getCarCylinder', [VehicleController::class, 'getCarCylinder'])->name('getCarCylinder');
Route::post('/getCarCapacity', [VehicleController::class, 'getCarCapacity'])->name('getCarCapacity');
Route::post('/getMotorItems', [VehicleController::class, 'getMotorItems'])->name('getMotorItems');

/*Permission based routes*/

//Customer Management
Route::get('/allCustomers', [AlphaDirect\Http\Controllers\Management\CustomerManagement::class, 'getAllCustomers'])->name('allCustomers')->middleware('auth');
Route::get('/getConvertedValue/{val}', [AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getConvertedValue'])->name('getConvertedValue');

//APP ROUTES

Route::post('resetPassword', [\AlphaDirect\Http\Controllers\UserController::class,'passwordReset']);

/*PRINTOUT ROUTES */
Route::group(['middleware' => 'auth'], function () {
    Route::post('/printReceipt', [PrintController::class, 'printReceipt'])->name('printReciept');
    Route::post('/printInvoice', [PrintController::class, 'printInvoice'])->name('printInvoice');
});

Route::get('agentApp', [\AlphaDirect\Http\Controllers\USSD\ussd::class,'downloadLatestApkBuild']);

/*UPLOADING ROUTES */
//Document Uploads

Route::post('/uploadOmang', [UploadController::class, 'uploadOmang'])->name('uploadOmang');
Route::post('/uploadDriversLicense', [UploadController::class, 'uploadDriversLicense'])->name('uploadDriversLicense');
Route::post('/uploadBluebook', [UploadController::class, 'uploadBluebook'])->name('uploadBlueBook');
Route::post('/uploadResidence', [UploadController::class, 'uploadProofOfResidence'])->name('uploadProofOfResidence');
Route::post('/uploadIncome', [UploadController::class, 'uploadProofOfIncome'])->name('uploadProofOfIncome');
Route::post('/uploadFile', [UploadController::class, 'uploadFile'])->name('uploadFile');

//Vehicle Photos
Route::post('/uploadFrontImage', [UploadController::class, 'uploadFrontImage'])->name('uploadFrontImage');
Route::post('/uploadBackImage', [UploadController::class, 'uploadBackImage'])->name('uploadBackImage');
Route::post('/uploadLeftImage', [UploadController::class, 'uploadLeftImage'])->name('uploadLeftImage');
Route::post('/uploadRightImage', [UploadController::class, 'uploadRightImage'])->name('uploadRightImage');

/*END OF UPLOADING ROUTES*/

// ─── Microsoft SSO (multi-domain: alphadirect, unicoin, theriskco) ───────
Route::get('/auth/microsoft', [\AlphaDirect\Http\Controllers\Auth\MicrosoftSsoController::class, 'redirect'])->name('auth.microsoft');
Route::get('/auth/microsoft/callback', [\AlphaDirect\Http\Controllers\Auth\MicrosoftSsoController::class, 'callback'])->name('auth.microsoft.callback');

Route::post('/0000000000', [LoginController::class, 'signin'])->name('Sign-In');
Route::post('/emailsignin', [LoginController::class, 'authenticateViaEmail'])->name('email.signin');
Route::get('firstTimeLogin', function () {
    return view('auth.first_time_login');
})->name('firstTimeLogin');

//Route::post('/login', [UserController::class, 'login'])->name('login');

Route::get('/customerLogin', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'login'])->name('customerLogin');
Route::post('/authenticateCustomer', [AlphaDirect\Http\Controllers\CustomerLoginController::class, 'credentials'])->name('authenticateCustomer');

//DB Vehicle Route
// Route::post('/getCarMakes', [VehicleController::class, 'getMakes'])->name('getCarModel');
// Route::post('/getCarModel', [VehicleController::class, 'getCarModel'])->name('getCarModel');
// Route::post('/getTrueCarModel', [VehicleController::class, 'getTrueCarModel'])->name('getTrueCarModel');
// Route::post('/getCarCylinder', [VehicleController::class, 'getCarCylinder'])->name('getCarCylinder');
// Route::post('/getCarCapacity', [VehicleController::class, 'getCarCapacity'])->name('getCarCapacity');
// Route::post('/getMotorItems', [VehicleController::class, 'getMotorItems'])->name('getMotorItems');

/*Permission based routes*/

//Customer Management
Route::get('/allCustomers', [AlphaDirect\Http\Controllers\Management\CustomerManagement::class, 'getAllCustomers'])->name('allCustomers')->middleware('auth');
Route::get('/getConvertedValue/{val}', [AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getConvertedValue'])->name('getConvertedValue');

//APP ROUTES

Route::post('resetPassword', [\AlphaDirect\Http\Controllers\UserController::class,'passwordReset']);

/*PRINTOUT ROUTES */
Route::group(['middleware' => 'auth'], function () {
    Route::post('/printReceipt', [PrintController::class, 'printReceipt'])->name('printReciept');
    Route::post('/printInvoice', [PrintController::class, 'printInvoice'])->name('printInvoice');
});


Route::get('terms_conditions', [ConfigController::class,'getTermsConditions'])->name('terms_conditions');
Route::get('apk-version', [ConfigController::class,'setApkVersion'])->name('apk-version');
Route::any('update-apk-version', [ConfigController::class,'updateApkVersion'])->name('update-apk-version');
Route::get('terms_conditions_data', [ConfigController::class,'terms_conditions_data'])->name('terms_conditions_data');
Route::get('addTermsConditions', [ConfigController::class,'addTermsConditions'])->name('addTermsConditions');
Route::any('storeTermsConditions', [ConfigController::class,'storeTermsConditions'])->name('storeTermsConditions');
Route::get('device-make-model', [ConfigController::class,'deviceMakeModel'])->name('device_make_model');
Route::get('device-make-model/create', [ConfigController::class,'deviceMakeModelCreate']);
Route::get('device-make-model/addNewDeviceMake', [ConfigController::class,'addNewDeviceMake']);
Route::post('device-make-model/store', [ConfigController::class,'deviceMakeModelStore'])->name('device_make_model_store');
Route::post('device-make-model/addNewDeviceStore', [ConfigController::class,'deviceMakeModelAddNewDeviceStore'])->name('device_make_model_add_new_device_store');
Route::get('deviceMakeModelData', [ConfigController::class,'deviceMakeModelData'])->name('device_make_model_data');
Route::get('device-make-model/edit/{id}', [ConfigController::class,'deviceMakeModelEdit']);
Route::put('update_device/{id}', [ConfigController::class,'deviceMakeModelUpdate'])->name('device_make_model_update');
Route::post('confirm-delete', [ConfigController::class,'getDeviceMakeModelDelete'])->name('device_make_model.confirm-delete');
Route::get('device_make_model/delete/{id}', [ConfigController::class,'destroyMakeModel'])->name('device_make_model.delete');
Route::get('deviceMakeModelExport', [ConfigController::class,'deviceMakeModelExport'])->name('device_make_model_export');

Route::get('vehicle-make-model', [ConfigController::class,'vehicleMakeModel'])->name('vehicle_make_model');
Route::get('vehicle-make-model/create', [ConfigController::class,'vehicleMakeModelCreate']);
Route::get('vehicle-make-model/addNewVehicleMake', [ConfigController::class,'addNewVehicleMake']);
Route::post('vehicle-make-model/store', [ConfigController::class,'vehicleMakeModelStore'])->name('vehicle_make_model_store');
Route::post('vehicle-make-model/addNewVehicleStore', [ConfigController::class,'vehicleMakeModelAddNewVehicleStore'])->name('vehicle_make_model_add_new_vehicle_store');
Route::get('vehicleMakeModelData', [ConfigController::class,'vehicleMakeModelData'])->name('vehicle_make_model_data');
Route::get('vehicle-make-model/edit/{id}', [ConfigController::class,'vehicleMakeModelEdit']);
Route::put('update_vehicle/{id}', [ConfigController::class,'vehicleMakeModelUpdate'])->name('vehicle_make_model_update');
Route::post('vehicle-confirm-delete', [ConfigController::class,'getVehicleMakeModelDelete'])->name('vehicle_make_model.confirm-delete');
Route::get('vehicle_make_model/delete/{id}', [ConfigController::class,'destroyVehicleMakeModel'])->name('vehicle_make_model.delete');
Route::get('vehicleMakeModelExport', [ConfigController::class,'vehicleMakeModelExport'])->name('vehicle_make_model_export');

Route::any('addEmailForRealPay', [ConfigController::class,'addEmailForRealPay'])->name('addEmailForRealPay');
Route::any('storeEmails', [ConfigController::class,'storeEmails'])->name('storeEmails');
Route::any('emailList', [ConfigController::class,'emailList'])->name('emailList');
Route::any('emailData', [ConfigController::class,'emailData'])->name('emailData');
Route::any('confirmRemove/{id}', [ConfigController::class,'destroy'])->name('confirmRemove');
Route::any('emailconfirm-delete', [ConfigController::class,'confirmRemoveEmail'])->name('emailconfirm-delete');
Route::any('confirm-cancel', [ConfigController::class,'confirmCancel'])->name('confirm-cancel');
Route::any('getStates', [ConfigController::class,'getStates'])->name('getStates');
Route::any('getCities', [ConfigController::class,'getCities'])->name('getCities');

Route::get('agentApp', [\AlphaDirect\Http\Controllers\USSD\ussd::class,'downloadLatestApkBuild']);

Route::group(['prefix' => 'admin', 'middleware' => ['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer']], function () {

    Route::get('/dashboard', [AdminController::class,'index'])->name('admin-dashboard');
    Route::any('/getSMSData', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'getSMSData'])->name('getSMSData');
    Route::any('/uploadSMSData', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'uploadSMSData'])->name('uploadSMSData');
    Route::any('/getCSV', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'getCSV'])->name('getCSV');
    Route::any('/uploadCSV', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'uploadCSV'])->name('uploadCSV');
    Route::any('/add-transaction', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'logTransactionPage'])->name('add-transaction');
    Route::any('/addCustomerTransaction', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'addCustomerTransactionOrange'])->name('addCustomerTransaction');
    Route::any('/orangeTransactionExport', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class,'orangeTransactionExport'])->name('transactionExport');
    Route::get('/fileUpload', [AdminController::class,'fileUpload'])->name('fileUpload');
    Route::get('/setSalesTarget', [AdminController::class,'setSalesTarget'])->name('setSalesTarget');
    Route::post('/storeSalesTarget', [AdminController::class,'storeSalesTarget'])->name('storeSalesTarget');

    Route::get('/checkActivationCode', [ActivationController::class, 'checkActivationCode'])->name('activation.checkActivationCode');
    Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index')->name('logs');

    Route::get('/policies', [AdminController::class, 'policies'])->name('policies');
    Route::get('/policydata', [AdminController::class, 'policydata'])->name('policydata');

    Route::get('/addCompany', [AdminController::class, 'addCompanyView'])->name('addCompany');
    Route::get('/addTreaty', [AlphaDirect\Http\Controllers\Admin\Reinsurance\ReinsuranceController::class, 'addTreatyView'])->name('addTreatyView');
    Route::get('/addNewTreaty', [AlphaDirect\Http\Controllers\Admin\Reinsurance\ReinsuranceController::class, 'viewAddReinsurance'])->name('addNewTreaty');
    Route::get('/admin-edit/{id}', [AdminController::class, 'editPolicyView'])->name('admin-editPolicy');
    Route::get('/makePolicy', [AdminController::class, 'makePolicy'])->name('admin-makePolicy');
    Route::get('/kamlesh', [AdminController::class, 'makePolicyKamlesh']);

    Route::get('/allPolicyPlans', [AdminController::class, 'getAllPolicyPlans'])->name('admin-getAllPolicyPlans');
    Route::get('/addPolicyPlan', [AdminController::class, 'addPolicyView'])->name('admin-addPolicyPlan');

    //Customer management

    Route::get('/viewCustomers', [AdminController::class, 'viewAllCustomers'])->name('customersView');
    Route::get('/viewCustomer/{id}', [AlphaDirect\Http\Controllers\Management\CustomerManagement::class, 'viewCustomerDetails'])->name('viewCustomerDetails');
    Route::get('/editCustomer/{id}', [AlphaDirect\Http\Controllers\Management\CustomerManagement::class, 'editCustomerDetails'])->name('EditCustomerDetails');
    Route::post('/updateEdit', [AlphaDirect\Http\Controllers\Management\CustomerManagement::class, 'saveCustomerEdits'])->name('updateCustomer');

    //Route::get('/editCustomer/{id}', [AdminController::class,'viewAllCustomers')->name('customersEdit');

    //Email and SMS Broadcasting
    Route::get('/viewEmailBroad', [AlphaDirect\Http\Controllers\Admin\Broadcasting\BroadcastingController::class,'viewEmailBroadcasting'])->name('EmailBroadView');

    Route::get('/viewSMSBroad', [AlphaDirect\Http\Controllers\Admin\Broadcasting\BroadcastingController::class,'viewSMSBroadcasting'])->name('SMSBroadView');

    //Policy Management
    Route::get('/policyList', [AdminController::class, 'listPolicies'])->name('admin-policyList');
    Route::post('/policyList', [AlphaDirect\Http\Controllers\Management\PolicyManagement::class, 'editPolicyDetails'])->name('admin-updatePolicy');

    //Policy Plan Management
    Route::get('/PolicyManagement', [AdminController::class, 'viewPolicyManagement'])->name('admin-policyManagement');
    Route::get('/activatePolicyPlan/{id}', [AdminController::class, 'activatePolicyPlan'])->name('admin-activatePlan');
    Route::get('/deactivatePolicyPlan/{id}', [AdminController::class, 'deactivatePolicyPlan'])->name('admin-deactivatePlan');
    Route::get('/deletePlan/{id}', [AdminController::class, 'deletePlan'])->name('admin-deletePlan');

    //Staff Management
    Route::get('/addStaff', [AdminController::class, 'addstaff'])->name('addStaff');

    Route::post('/searchPolicies', [AdminController::class, 'searchPolicies'])->name('searchPolicies');
    Route::post('/createPolicyPlan', [AdminController::class, 'createPolicyPlan'])->name('admin-createPolicyPlan');

    Route::get('/EditClients', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'editClients'])->name('EditClients');
    Route::get('/AllClients', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'viewAllClients'])->name('AllClients');
    Route::get('/AllBanks', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'viewAllBanks'])->name('AllBanks');
    Route::get('/AllContracts', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'viewAllClientContracts'])->name('AllContracts');
    Route::get('/ClientsContracts', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'viewClientSpecificContract'])->name('ClientsContracts');
    Route::get('/AllTransactions', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'viewAllTransactions'])->name('AllTransactions');
    Route::post('/BankStore', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'storeBanks'])->name('BankStore');
    Route::get('/financialInterest', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterest'])->name('admin.financialInterest');
    Route::post('/financialInterest/data', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'data'])->name('admin.financialInterest.data');
    Route::get('/financialInterest/edit/{id}', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterestEdit'])->name('admin.financialInterest.edit');
    Route::get('/financialInterest/create', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterestCreate']);
    Route::post('/financialInterest/store', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterestStore'])->name('admin.financialInterest.store');
    Route::post('/financialInterest/update/{id}', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterestUpdate'])->name('admin.financialInterest.update');
    Route::post('/financialInterest/update/{id}', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterestUpdate'])->name('admin.financialInterest.update');
    Route::get('/financialInterest/delete/{id}', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'financialInterestDelete'])->name('admin.financialInterest.delete');
    Route::post('/financialInterest/confirm-delete', [\AlphaDirect\Http\Controllers\Admin\Banking\BankDetails::class,'getModalDelete'])->name('admin.financialInterest.confirm-delete');


    Route::post('/scratchUpdate', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class,'updatePolicy'])->name('scratchUpdate');//->name('updatePolicy');

    Route::get('/alphaPDF', function () {

        $pdf = PDF::loadView('PDF.AlphaPDF');
        return $pdf->download('AlphaDirect-Policy.pdf');
    })->name('admin-generatePDF');

    Route::get('/allPolicies', [AdminController::class, 'getAllPolicies'])->name('allPolicies');
    Route::get('/allStaffMembers', [AdminController::class, 'getAllStaffMembers'])->name('allStaffmembers');

    Route::get('/viewPolicy/{id}', [AdminController::class, 'viewPolicyDetails'])->name('viewPolicyDetails');
    Route::get('/kamleshDetail/{id}', [AdminController::class, 'kamleshDetails']);

    Route::get('/CreatePolicies', [AdminController::class, 'createPolicy'])->name('admin-createPolicy');

    Route::get('/Claims', [AdminController::class, 'viewClaims'])->name('admin-allClaims');

    Route::post('/scratch', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class,'registerUser'])->name('savePolicy');

    Route::get('/addNewSupplier', [AlphaDirect\Http\Controllers\Admin\Suppliers\SupplierController::class, 'viewAddSupplier'])->name('addNewSupplier');
    Route::get('/addSupplier', [AlphaDirect\Http\Controllers\Admin\Suppliers\SupplierController::class, 'addSupplierView'])->name('addSupplier');
    Route::post('/saveSupplier', [AlphaDirect\Http\Controllers\Admin\Suppliers\SupplierController::class, 'store'])->name('saveSupplier');
    Route::get('/allSuppliers', [AlphaDirect\Http\Controllers\Admin\Suppliers\SupplierController::class, 'getAllSuppliers'])->name('allSuppliers');

    Route::get('/allCompanies', [AlphaDirect\Http\Controllers\Admin\Companies\CompanyController::class, 'getAllCompanies'])->name('allCompanies');
    Route::get('/addCompanies', [AlphaDirect\Http\Controllers\Admin\Companies\CompanyController::class, 'viewAddCompany'])->name('addCompanies');
    Route::post('/saveCompany', [AlphaDirect\Http\Controllers\Admin\Companies\CompanyController::class, 'store'])->name('saveCompany');

    Route::get('/allTreaties', [TreatyController::class, 'getAllTreaties'])->name('allTreaties');

    Route::post('register', [\AlphaDirect\Http\Controllers\UserController::class,'register'])->name('saveEmployee');

    Route::post('/activateUserPolicy', [AdminController::class, 'activateUserPolicy'])->name('activateUserPolicy');
    Route::post('/deactivateUserPolicy', [AdminController::class, 'deactivateUserPolicy'])->name('deactivatePolicy');

    Route::post('/scratch', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class,'adminActivatePolicy'])->name('admin-savePolicy');

    Route::post('/saveTreaty', [TreatyController::class, 'saveTreaty'])->name('saveTreaty');

    Route::post('/updatePolicy/{id}', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class,'updatePolicy'])->name('editPolicy');


});

//Arihant

//Supplier Quote Request
Route::get('quote/{quote_id}', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class,'quote'])->name('supplier.quote');
Route::get('cellphoneQuote/{id}/{repaircenter_id}', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class,'cellphoneQuote'])->name('supplier.cellphoneQuote');
Route::post('quoteSubmit',[\AlphaDirect\Http\Controllers\Admin\SuppliersController::class,'quoteSubmit'])->name('supplier.quoteSubmit');

Route::get('uploadInvoice/{claim_id}/{quote_id?}', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class,'uploadInvoice'])->name('supplier.uploadInvoice');
Route::any('invoiceSubmit', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class,'invoiceSubmit'])->name('supplier.invoiceSubmit');

/*ADMIN ROUTES */
//protect the dashboard with auth middleware Tumisang

Route::group(['prefix' => 'admin', 'namespace' => 'Admin', 'as' => 'admin.', 'middleware' => ['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer']], function () {

    Route::get('/companyname/create', [\AlphaDirect\Http\Controllers\CompanyNameController::class, 'create'])->name('companyname.create');
    Route::get('/companyname', [\AlphaDirect\Http\Controllers\CompanyNameController::class, 'index'])->name('companyname.index');
    Route::get('/companyname/data', [\AlphaDirect\Http\Controllers\CompanyNameController::class, 'companynamedata'])->name('companyname.data');
    Route::post('/companyname/store', [\AlphaDirect\Http\Controllers\CompanyNameController::class, 'store'])->name('companyname.store');
    Route::get('/companyname/edit/{id}', [\AlphaDirect\Http\Controllers\CompanyNameController::class, 'edit'])->name('companyname.edit');
    Route::put('/companyname/update/{id}', [\AlphaDirect\Http\Controllers\CompanyNameController::class, 'update'])->name('companyname.update');
    Route::post('/companyname/confirm-delete', [\AlphaDirect\Http\Controllers\CompanyNameController::class,'companynameModelDelete'])->name('companyname.confirm-delete');
    Route::any('/companyname/delete/{id}', [\AlphaDirect\Http\Controllers\CompanyNameController::class,'delete'])->name('companyname.destroy');

	#Menu Settings
	Route::get('menu-master',function(){
		return view('admin.defult-page',['page'=>'admin.menu-master','breadcrum'=>'menu-master']);
	});

    Route::get('customerKyc', [CustomerKycController::class, 'agentKycAppUpload'])->name('customerKyc');
    Route::get('customerKycData', [CustomerKycController::class, 'kycData'])->name('customerKycData');
    
    // Employer Group KYC Routes
    Route::get('employerGroupKyc', [\AlphaDirect\Http\Controllers\Admin\EmployerGroupKycController::class, 'index'])->name('employerGroupKyc');
    Route::get('employerGroupKycData', [\AlphaDirect\Http\Controllers\Admin\EmployerGroupKycController::class, 'kycData'])->name('employerGroupKyc.data');
    Route::get('employerGroupKyc/view/{id}', [\AlphaDirect\Http\Controllers\Admin\EmployerGroupKycController::class, 'view'])->name('employerGroupKyc.view');
    Route::get('employerGroupKyc/verify/{id}', [\AlphaDirect\Http\Controllers\Admin\EmployerGroupKycController::class, 'verify'])->name('employerGroupKyc.verify');
    Route::get('sanctioned-customers', [CustomerKycController::class, 'getSanctionedCustomers'])->name('sanctioned-customers');
    Route::post('kyc/run-opensanctions/{customerId}', [CustomerKycController::class, 'runOpenSanctionsCheck'])->name('kyc.run-opensanctions');
    Route::get('viewCustomerKycData/{id}', [CustomerKycController::class, 'verify'])->name('viewCustomerKycData');
    Route::get('viewCustomerKycDataDomCom/{id}', [CustomerKycController::class, 'verifyDomCom'])->name('viewCustomerKycDataDomCom');
    Route::get('archiveKYC/{id}', [CustomerKycController::class, 'archiveKYC'])->name('archiveKYC');
    Route::get('verifyCustomerKycData/{id}', [CustomerKycController::class, 'view'])->name('verifyCustomerKycData');
    Route::any('updateCustomerKYCData/{id}', [CustomerKycController::class, 'updateCustomerKYCData'])->name('updateCustomerKYCData');
    Route::get('customerKycActivityLog/{id}', [CustomerKycController::class, 'customerKycActivityLog'])->name('customerKycActivityLog');
    Route::any('getKycActivityLogData/{id}', [CustomerKycController::class, 'getKycActivityLogData'])->name('getKycActivityLogData');
    Route::get('customerKycActivityLogRecordes/{id}',[CustomerKycController::class,'customerKycActivityLogRecordes'])->name('customerKycActivityLogRecordes');
    Route::any('getcustomerKycActivityLogRecordes/{id}',[CustomerKycController::class,'getcustomerKycActivityLogRecordes'])->name('getcustomerKycActivityLogRecordes');
    Route::get('VehiclePreinspectionActivityLogRecordes/{id}',[CustomerKycController::class,'VehiclePreinspectionActivityLogRecordes'])->name('VehiclePreinspectionActivityLogRecordes');
    Route::any('getVehiclePreinspectionActivityLogRecordes/{id}',[CustomerKycController::class,'getVehiclePreinspectionActivityLogRecordes'])->name('getVehiclePreinspectionActivityLogRecordes');
    Route::get('customerKycDeleteRecordes/{id}',[CustomerKycController::class,'customerKycDeleteRecordes'])->name('customerKycDeleteRecordes');
    Route::post('delete/{id}', [CustomerKycController::class,'destroy'])->name('customerKyc.delete');

    Route::get('customerVehicleInspection', [CustomerInspection::class, 'agentInspectionAppUpload'])->name('customerVehicleInspection');
    Route::get('CellphoneDeleteRecordes/{id}', [CustomerInspection::class, 'CellphoneDeleteRecordes'])->name('CellphoneDeleteRecordes');
    Route::get('customerVehicleDeleteRecordes/{id}', [CustomerInspection::class, 'customerVehicleDeleteRecordes'])->name('customerVehicleDeleteRecordes');
    Route::get('deviceData', [CustomerInspection::class, 'deviceData'])->name('deviceData');
    Route::any('sendemailurllink', [CustomerInspection::class, 'sendemailurllink'])->name('sendemailurllink');
    Route::get('customerInspectionData', [CustomerInspection::class, 'inspectionData'])->name('customerInspectionData');
    Route::get('deviceInspectionData', [CustomerInspection::class, 'deviceInspectionData'])->name('deviceInspectionData');
    Route::any('customerInspection/edit/{id}', [CustomerInspection::class, 'verifyedit'])->name('customerInspection.edit');
    Route::any('customerInspection/view/{id}', [CustomerInspection::class, 'verify'])->name('customerInspection.view');
    Route::any('deviceInspection/view/{id}', [CustomerInspection::class, 'viewDeviceData'])->name('deviceInspection.view');
    Route::any('deviceInspection/edit/{id}', [CustomerInspection::class, 'editDeviceData'])->name('deviceInspection.edit');
    Route::any('customerInspection/verify/{id}', [CustomerInspection::class, 'view'])->name('customerInspection.verify');
    Route::any('customerInspection/update', [CustomerInspection::class, 'update'])->name('customerInspection.update');
    Route::any('deviceInspection/updateDeviceStatus/{id}', [CustomerInspection::class, 'updateDeviceStatus'])->name('deviceInspection.updateDeviceStatus');

    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class,'settings'])->name('settings');
    });

    // AI Configuration (redirects to Vault > AI category)
    Route::prefix('ai-config')->group(function () {
        Route::get('/',       [AiConfigController::class, 'index'])->name('admin.ai-config.index');
        Route::post('/',      [AiConfigController::class, 'update'])->name('admin.ai-config.update');
        Route::post('/test',  [AiConfigController::class, 'testConnection'])->name('admin.ai-config.test');
    });

    // Credentials Vault
    Route::prefix('vault')->group(function () {
        Route::get('/',                [VaultController::class, 'index'])->name('vault.index');
        Route::post('/unlock',         [VaultController::class, 'unlock'])->name('vault.unlock');
        Route::post('/lock',           [VaultController::class, 'lock'])->name('vault.lock');
        Route::post('/set-pin',        [VaultController::class, 'setPin'])->name('vault.set-pin');
        Route::post('/change-pin',     [VaultController::class, 'changePin'])->name('vault.change-pin');
        Route::post('/save',           [VaultController::class, 'save'])->name('vault.save');
        Route::post('/get-credential', [VaultController::class, 'getCredential'])->name('vault.get-credential');
    });

    // SSO: Generate one-time token and redirect to React portal
    Route::get('/sso/redirect', function () {
        $user = auth()->user();
        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\Cache::put("sso_token:{$token}", $user->id, 60);
        $reactUrl = config('services.react_portal.url', 'http://localhost:3000');
        return redirect("{$reactUrl}/sso?token={$token}");
    })->name('sso.redirect');

//    /*Menumaster Management*/
//    Route::get('admin/menumaster', function () {
//        return view('admin.menumaster.index');
//    });

    /*Menumaster Management*/
    Route::group(['prefix' => 'menumaster'], function () {
        Route::get('/data', [MenuMasterController::class, 'data'])->name('menumaster.data');
        Route::get('/', [MenuMasterController::class, 'index'])->name('menumaster');
        Route::resource('menumaster', 'MenuMasterController');
    });

    /*Roles Management*/
    Route::group(['prefix' => 'roles'], function () {
        Route::get('/roles2', [RolesController::class, 'roles'])->name('roles.roles');
        Route::post('/rolesStoreUpdate', [RolesController::class, 'rolesStoreUpdate'])->name('roles.rolesStoreUpdate');
         Route::post('/roleUnderRolesData', [RolesController::class, 'roleUnderRolesData'])->name('roles.roleUnderRolesData');
        Route::get('/data', [RolesController::class, 'data'])->name('roles.data');
        Route::get('roles-permisions', [RolesController::class, 'getRolePermisions']);
        Route::get('/', [RolesController::class, 'index'])->name('activation');
        Route::post('confirm-delete', [RolesController::class, 'getModalDelete'])->name('roles.confirm-delete');
        Route::resource('roles', 'RolesController');
    });

    /*Notifications Managment*/
    Route::group(['prefix' => 'notification'], function () {
        Route::post('GetNotification', [\AlphaDirect\Http\Controllers\Admin\NotificationController::class, 'GetNotification'])->name('notification.GetNotification');
        Route::post('data', [\AlphaDirect\Http\Controllers\Admin\NotificationController::class, 'data'])->name('notification.data');
    });
    Route::resource('notification', '\AlphaDirect\Http\Controllers\Admin\NotificationController');

    # Reinsurance Type Management
    Route::group(['prefix' => 'reinsuranceType'], function () {
        Route::get('data', [ReinsuranceTypeController::class, 'data'])->name('reinsuranceType.data');
        Route::get('/', [ReinsuranceTypeController::class, 'index'])->name('reinsuranceType');
        Route::post('confirm-delete', [ReinsuranceTypeController::class, 'getModalDelete'])->name('reinsuranceType.confirm-delete');
        Route::get('{reinsuranceType}/delete', [ReinsuranceTypeController::class, 'destroy'])->name('reinsuranceType.delete');
    });
    Route::resource('reinsuranceType', 'ReinsuranceTypeController');

    # Reinsurance Treaty Management
    Route::group(['prefix' => 'reinsuranceTreaty'], function () {
        Route::get('data', [ReinsuranceTreatyController::class, 'data'])->name('reinsuranceTreaty.data');
        Route::get('/', [ReinsuranceTreatyController::class, 'index'])->name('reinsuranceTreaty');
        Route::post('confirm-delete', [ReinsuranceTreatyController::class, 'getModalDelete'])->name('reinsuranceTreaty.confirm-delete');
        Route::get('{reinsuranceTreaty}/delete', [ReinsuranceTreatyController::class, 'destroy'])->name('reinsuranceTreaty.delete');
        Route::get('{reinsuranceTreaty}/delete', [ReinsuranceTreatyController::class, 'destroy'])->name('reinsuranceTreaty.delete');

        Route::get('newreinsuranceTreaty', [ReinsuranceTreatyController::class, 'new_index'])->name('newreinsuranceTreaty');
        Route::get('createTreaty', [ReinsuranceTreatyController::class, 'createTreaty'])->name('createTreaty');
    });
    Route::resource('reinsuranceTreaty', 'ReinsuranceTreatyController');


    # Coverages
    Route::group(['prefix' => 'coverages'], function () {
        Route::get('data', [CoveragesController::class, 'data'])->name('coverages.data');
        Route::get('/', [CoveragesController::class, 'index'])->name('coverages');
        Route::post('confirm-delete', [CoveragesController::class, 'getModalDelete'])->name('coverages.confirm-delete');
        Route::get('{coverages}/delete', [CoveragesController::class, 'destroy'])->name('coverages.delete');
    });
    Route::resource('coverages', 'CoveragesController');


    # Sub Coverages
    Route::group(['prefix' => 'subCoverages'], function () {
        Route::get('data', [SubCoveragesController::class, 'data'])->name('subCoverages.data');
        Route::get('/', [SubCoveragesController::class, 'index'])->name('subCoverages');
        Route::post('confirm-delete', [SubCoveragesController::class, 'getModalDelete'])->name('subCoverages.confirm-delete');
        Route::get('{subCoverages}/delete', [SubCoveragesController::class, 'destroy'])->name('subCoverages.delete');
    });
    Route::resource('subCoverages', 'SubCoveragesController');


    # Specified Coverages Items
    Route::group(['prefix' => 'specifiedCoveragesItems'], function () {
        Route::get('data', [SpecifiedCoveragesItemsController::class, 'data'])->name('specifiedCoveragesItems.data');
        Route::get('/', [SpecifiedCoveragesItemsController::class, 'index'])->name('specifiedCoveragesItems');
        Route::post('confirm-delete', [SpecifiedCoveragesItemsController::class, 'getModalDelete'])->name('specifiedCoveragesItems.confirm-delete');
        Route::get('{specifiedCoveragesItems}/delete', [SpecifiedCoveragesItemsController::class, 'destroy'])->name('specifiedCoveragesItems.delete');
    });
    Route::resource('specifiedCoveragesItems', 'SpecifiedCoveragesItemsController');


    Route::group(['prefix' => 'reinsuranceFormula'], function () {
        Route::get('data', [ReinsuranceFormulaController::class, 'data'])->name('reinsuranceFormula.data');
        Route::get('/', [ReinsuranceFormulaController::class, 'index'])->name('reinsuranceFormula');
        Route::post('confirm-delete', [ReinsuranceFormulaController::class, 'getModalDelete'])->name('reinsuranceFormula.confirm-delete');
        Route::get('{reinsuranceFormula}/delete', [ReinsuranceFormulaController::class, 'destroy'])->name('reinsuranceFormula.delete');
        Route::post('group', [ReinsuranceFormulaController::class, 'getGroup'])->name('reinsuranceFormula.group');
    });
    Route::resource('reinsuranceFormula', 'ReinsuranceFormulaController');
    # Reinsurance Group Coverage Management
    Route::group(['prefix' => 'reinsuranceGroupCoverage'], function () {
        Route::get('data', [ReinsuranceGroupCovController::class, 'data'])->name('reinsuranceGroupCoverage.data');
        Route::get('/', [ReinsuranceGroupCovController::class, 'index'])->name('reinsuranceGroupCoverage');
        Route::post('confirm-delete', [ReinsuranceGroupCovController::class, 'getModalDelete'])->name('reinsuranceGroupCoverage.confirm-delete');
        Route::get('{reinsuranceGroupCoverage}/delete', [ReinsuranceGroupCovController::class, 'destroy'])->name('reinsuranceGroupCoverage.delete');
        Route::post('product_coverage', [ReinsuranceGroupCovController::class, 'getProductCoverages'])->name('reinsuranceGroupCoverage.product_coverage');
    });

    Route::resource('reinsuranceGroupCoverage', 'ReinsuranceGroupCovController');

    /*Rules Managment*/
    Route::group(['prefix' => 'rules'], function () {

        Route::resource('rules', 'RulesController');
        Route::get('/data', [RulesController::class, 'data'])->name('rules.data');
        Route::post('show', [RulesController::class, 'show']);//->name('rules.show');
        Route::any('edit/{id}', [RulesController::class, 'edit']);//->name('rules.edit');
        Route::post('update/{id}', [RulesController::class, 'update']);//->name('rules.update');
        Route::post('confirm-delete', [RulesController::class, 'getModalDelete'])->name('rules.confirm-delete');
        Route::get('delete/{id}', [RulesController::class, 'destroy'])->name('rules.delete');
        Route::post('getTransSubtype', [RulesController::class, 'getTransSubtype'])->name('rules.getTransSubtype');
    });

    //Route::resource('rules', 'RulesController');

    # Claims Management
    Route::group(['prefix' => 'claims'], function () {
        Route::get('/',  [ClaimsController::class,'index'])->name('claims');
        Route::get('data',  [ClaimsController::class,'data'])->name('claims.data');
        Route::get('getclaimsActivityLogRecordes/{policy_id}',  [ClaimsController::class,'getclaimsActivityLogRecordes'])->name('getclaimsActivityLogRecordes');
        Route::get('getClaimComplaintLogs/{claim_id}',  [ClaimsController::class,'getClaimComplaintLogs'])->name('getClaimComplaintLogs');
        // Route::get('update',  [ClaimsController::class,'update']);//->name('claims.update');
        Route::post('update/{id}',  [ClaimsController::class,'update'])->name('claims.update');
        Route::patch('update/{id}',  [ClaimsController::class,'update'])->name('claims.update');
        Route::post('statusUpdate/{claim_id}',  [ClaimsController::class,'statusUpdate'])->name('claims.statusUpdate');
        Route::post('newClaimStatusUpdate/{claim_id}',  [ClaimsController::class,'newClaimStatusUpdate'])->name('claims.newClaimStatusUpdate');
        Route::get('{claim}/approved',  [ClaimsController::class,'approved'])->name('claims.approved');
        Route::get('{claim}/rejects',  [ClaimsController::class,'rejects'])->name('claims.rejects');
        Route::get('{quoteId}/supplierInvoice',  [ClaimsController::class,'supplierInvoice'])->name('claims.supplierInvoice');
        Route::any('/acceptQuote/{quote_id}',  [ClaimsController::class,'acceptQuote'])->name('claims.acceptQuote');
        Route::post('invoiceUpload/{claim}',  [ClaimsController::class,'invoiceUpload']);
        Route::post('poUpload/{claim}',  [ClaimsController::class,'poUpload']);
        Route::post('storeComplaint/{claim}',  [ClaimsController::class,'storeComplaint']);
        Route::post('assessorUpload/{claim}',  [ClaimsController::class,'assessorUpload']);
        Route::post('accidentSupplier/{claim}',  [ClaimsController::class,'accidentSupplier']);
        Route::get('{claim}/accidentSupplierInvoice',  [ClaimsController::class,'accidentSupplierInvoice']);
        Route::post('attachmentUpload/{claim}',  [ClaimsController::class,'attachmentUpload'])->name('claims.attachmentUpload');
        Route::post('storeReserve/{claim}',  [ClaimsController::class,'storeReserve'])->name('claims.storeReserve');
        Route::get('attachmentData/{id}',  [ClaimsController::class,'attachmentData'])->name('claims.attachmentData');
        Route::post('confirm-delete', [ClaimsController::class,'getModalDelete'])->name('claims.confirm-delete');
        Route::get('{id}/delete', [ClaimsController::class,'destroy'])->name('claims.delete');
        Route::get('{id}/beneficiaryNameDelete',  [ClaimsController::class,'beneficiaryNameDelete'])->name('claims.beneficiaryNameDelete');
        Route::get('activity/{id}',  [ClaimsController::class,'ClaimsActivity'])->name('claims.activity');
        Route::get('coverageData/{id}',  [ClaimsController::class,'coverageData'])->name('claims.coverageData');
        Route::post('getCustomerData',  [ClaimsController::class,'getCustomerData'])->name('claims.getCustomerData');
        Route::post('getOtherPartyData',  [ClaimsController::class,'getOtherPartyData'])->name('claims.getOtherPartyData');
        Route::post('checkSumAssured',  [ClaimsController::class,'checkSumAssured'])->name('claims.checkSumAssured');
        Route::post('checkMakeModel',  [ClaimsController::class,'checkMakeModel'])->name('claims.checkMakeModel');
        Route::post('get_oldMakeModel',  [ClaimsController::class,'get_oldMakeModel'])->name('claims.get_oldMakeModel');
        Route::post('getpreviousModel',  [ClaimsController::class,'getpreviousModel'])->name('claims.getpreviousModel');
        Route::post('checkreserve_amount',  [ClaimsController::class,'checkreserve_amount'])->name('claims.checkreserve_amount');
        Route::post('getModelValues',  [ClaimsController::class,'getModelValues'])->name('claims.getModelValues');
        Route::post('checkAttorney',  [ClaimsController::class,'checkAttorney'])->name('claims.checkAttorney');
        Route::get('claimView/{id}',  [ClaimsController::class,'claimView'])->name('claims.claimView');
        Route::post('closeClaimStore/{claim}', [ClaimsController::class,'closeClaimStore']);
        Route::post('reopenStoreClaim/{claim}', [ClaimsController::class,'reopenStoreClaim']);
        Route::post('voidPayment',  [ClaimsController::class,'voidPayment'])->name('claims.voidPayment');
        Route::get('/getReserveDeatils/{id}', [ClaimsController::class, 'getReserveDeatils'])->name('claims.getReserveDeatils');
        Route::get('/getVoidPaymentInfo/{id}', [ClaimsController::class, 'getVoidPaymentInfo'])->name('claims.getVoidPaymentInfo');
    });
    Route::resource('claims', 'ClaimsController');


    Route::group(['prefix' => 'quotes'], function () {
//        Route::any('setting', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getSetting'])->name('quotes.setting');
//        Route::any('storeSetting', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'storeSetting'])->name('quotes.storeSetting');
    });
//    Route::any('bundledsetting', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'BundledgetSetting'])->name('BundledgetSetting');
//    Route::post('bundledstoreSetting', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'bundledproductsstoreSetting'])->name('bundledproducts.storeSetting');
//    Route::resource('quotes', 'AlphaDirect\Http\Controllers\Admin\QuoteController');
    Route::any('agentPinSetting', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'agentPinSetting'])->name('agentPinSetting');
    Route::post('agentpinstatusstoreSetting', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'agentpinstoreSetting'])->name('agentpin.storeSetting');

    # Month Rate Settings
    Route::get('month-rate-settings', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getMonthRateSetting'])->name('month-rate-settings');
    Route::post('month-rate-settings', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'storeMonthRateSetting'])->name('store-month-rate-setting');
    Route::post('initialize-default-month-rates', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'initializeDefaultMonthRates'])->name('initialize-default-month-rates');

    # Policy Discount Eligibility
Route::get('policy-discount-eligibility', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getPolicyDiscountEligibility'])->name('policy-discount-eligibility');
Route::get('policy-discount-eligibility/data', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getPolicyDiscountEligibilityData'])->name('policy-discount-eligibility.data');

# Apply Policy Discount
Route::post('apply-policy-discount', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'applyPolicyDiscount'])->name('apply-policy-discount');

# Applied Discounts List
Route::get('applied-discounts-list', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getAppliedDiscountsList'])->name('applied-discounts-list');
Route::get('applied-discounts-list/data', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getAppliedDiscountsData'])->name('applied-discounts-list.data');

    # Activation Management
    Route::group(['prefix' => 'activation'], function () {
        Route::get('data', [ActivationController::class, 'data'])->name('activation.data');
        Route::get('activatedCodeList', [ActivationController::class, 'activatedCodeList'])->name('activation.activatedCodeList');
        Route::get('CheckCode', [ActivationController::class, 'CheckCode'])->name('activation.CheckCode');
        Route::get('{group_id}/codeListData', [ActivationController::class, 'codeListData'])->name('activation.codeListData');
        Route::get('/', [ActivationController::class, 'index'])->name('activation');
        Route::post('confirm-delete', [ActivationController::class, 'getModalDelete'])->name('activation.confirm-delete');
        Route::get('{activation}/delete', [ActivationController::class, 'destroy'])->name('activation.delete');
        Route::get('{group_id}/list', [ActivationController::class, 'codeList'])->name('activation.list');
    });
    Route::resource('activation', 'ActivationController');

    # Customer Management
    Route::group(['prefix' => 'customer'], function () {
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'data'])->name('customer.data');
        Route::get('block_list_data', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'block_list_data'])->name('customer.block_list_data');
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'index'])->name('customer');
        Route::get('customer_search', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'allcustomersearch'])->name('customer_search');
        Route::get('customer_search_data', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'customersearchdata'])->name('customer.customer_search_data');

        Route::get('change_customer_policy', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'changecustomerpolicy'])->name('change_customer_policy');
        Route::post('change_customer_policy_store', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'changecustomerpolicystore'])->name('customer.chenge_customer_policy_store');
        Route::post('change_customer_policy_store_conform', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'changecustomerpolicystoreconform'])->name('customer.chenge_customer_policy_store_conform');
        Route::post('customer/detail', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'customerdetail'])->name('customer.detail');
        Route::post('getlist', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'customerdetaillist'])->name('customer.getlist');
        Route::post('policy/detail', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'policydetail'])->name('policy.detail');
        Route::get('black_list', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'black_list'])->name('customer.black_list');
        Route::get('/export', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'export'])->name('customer.export');
        Route::get('/edit/{id}', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'edit']);//->name('customer.edit');
        Route::get('/add_to_black_list', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'add_to_black_list'])->name('customer.add_to_black_list');
        Route::post('/add_to_block_list_update', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'add_to_block_list_update'])->name('customer.add_to_block_list_update');
        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'getModalDelete'])->name('customer.confirm-delete');
        Route::get('{customer}/delete', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'destroy'])->name('customer.delete');
        // Legacy Blade approval writers. Dead in production (block.v1_admin
        // on the enclosing group) but carry the same approver gate as the V2
        // API so flipping V1_ADMIN_PANEL_ENABLED can never reopen KYC
        // approval to the broad customer-kyc-edit holders.
        Route::any('verifyKYCInfo', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'verifyKYCInfo'])->name('customer.verifyKYCInfo')->middleware('permission:customer-kyc-approve');
        Route::get('fetchMatiData/{id}', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'fetchMatiData'])->name('customer.fetchMatiData');
        Route::post('updateMatiData/{id}', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'updateMatiData'])->name('customer.updateMatiData');
        Route::any('verifyKYCInfoForDomCom', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'verifyKYCInfoForDomCom'])->name('customer.verifyKYCInfoForDomCom')->middleware('permission:customer-kyc-approve');

    });

    Route::resource('customer', 'CustomerController');

    # Customer Feedback Option Management
    Route::group(['prefix' => 'customerFeedback'], function () {
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackController::class,'data'])->name('customerFeedback.data');
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackController::class,'index'])->name('customerFeedback');
        Route::any('create', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackController::class,'create']);//->name('customerFeedback.create');
        Route::any('/edit/{id}', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackController::class,'edit']);//->name('customerFeedback.edit');
        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackController::class,'getModalDelete'])->name('customerFeedback.confirm-delete');
        Route::get('{customer}/delete', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackController::class,'destroy'])->name('customerFeedback.delete');
    });
    Route::resource('customerFeedback', 'CustomerFeedbackController');

    # Customer Rewards Management
    Route::group(['prefix' => 'customer-rewards'], function () {
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class,'data'])->name('customer-rewards.data');
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class,'index'])->name('customer-rewards.index');
        Route::get('/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class,'edit'])->name('customer-rewards.edit');
        Route::put('/{id}/update', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class,'update'])->name('customer-rewards.update');
        Route::get('/{customerId}/benefits', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class,'getCustomerBenefits'])->name('customer-rewards.benefits');
    });

    # Document Management
    Route::group(['prefix' => 'documents'], function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'index'])->name('documents');
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'data'])->name('documents.data');
        Route::get('create', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'create'])->name('documents.create');
        Route::any('edit/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'edit']);//->name('documents.edit');
        Route::any('updateDoc/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'updateDoc'])->name('documents.updateDoc');
        Route::any('sendPolicyDocument/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'sendPolicyDocument'])->name('documents.sendPolicyDocument');
        Route::any('generatePolicyDocument/{id}',[\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'generatePolicyDocument'])->name('documents.generatePolicyDocument');
        Route::any('generatePolicyDocumentTerms/{id}/{term?}/{renew?}',[\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'generatePolicyDocumentTerms'])->name('documents.generatePolicyDocumentTerms');
        Route::any('generateCoverNote/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'generateStoreCoverNote'])->name('documents.generateCoverNote');
        Route::any('generateV2CancelNote/{policyId}/{termId}/{actionId}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'generateV2PolicyCancelNote'])->name('documents.generateV2CancelNote');
        Route::any('accountStatement/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'accountStatement'])->name('documents.accountStatement');
        Route::any('accountStatementDomCom/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'accountStatementDomCom'])->name('documents.accountStatementDomCom');
        Route::any('accountNewStatement/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'accountNewStatement'])->name('documents.accountNewStatement');

        Route::any('test_adi', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class,'test_adi'])->name('documents.test_adi');
    });
    Route::resource('documents', 'DocumentController');

    # Discount Surcharge Management
    Route::group(['prefix' => 'discountsurcharge'], function () {
        Route::any('policy', [\AlphaDirect\Http\Controllers\Admin\DiscountSurchargeController::class, 'policyDiscountSurcharge'])->name('discountsurcharge.policy');
        Route::any('policyRenewal', [\AlphaDirect\Http\Controllers\Admin\DiscountSurchargeController::class, 'policyRenewalDiscountSurcharge'])->name('policyRenewalDiscountSurcharge.policyRenewal');

    });

    # Schedule Management
    Route::group(['prefix' => 'schedule'], function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('schedule');
        Route::get('data', [ScheduleController::class, 'data'])->name('schedule.data');
        Route::get('create', [ScheduleController::class, 'create'])->name('schedule.create');
        Route::any('edit/{id}', [ScheduleController::class, 'edit']);//->name('schedule.edit');
        //Route::any('updateDoc/{id}', 'ScheduleController@updateDoc')->name('documents.updateDoc');
    });
    Route::resource('schedule', 'ScheduleController');

    # Policy Management
    Route::group(['prefix' => 'policy'], function () {
        Route::any('/testAgentReportData', [PolicyController::class,'testAgentReport'])->name('policy.testAgentReportData');
        Route::get('data', [PolicyController::class,'data'])->name('policy.data');
        Route::get('linkedPolicyData', [PolicyController::class,'linkedPolicyData'])->name('policy.linkedPolicyData');
        Route::get('PaymentUpdateContractData/{id}', [PolicyController::class,'PaymentUpdateContractData'])->name('policy.PaymentUpdateContractData');
        Route::any('generateAndSendRenewalLink/{id}', [PolicyController::class,'generateAndSendRenewalLink'])->name('policy.generateAndSendRenewalLink');
        Route::any('confirm-archive', [PolicyController::class,'confirmArchive'])->name('policy.confirm-archive');
        Route::any('otpVerification', [PolicyController::class,'otpVerification'])->name('policy.otpVerification');
        Route::any('feedback', [PolicyController::class,'feedback'])->name('policy.feedback');
        Route::any('premiumUpdateHistory/{policyNumber}', [PolicyController::class,'premiumUpdateHistory'])->name('policy.premiumUpdateHistory');
        Route::any('acceptReratedPremium/{id}', [PolicyController::class,'acceptNewRate'])->name('policy.acceptReratedPremium');
        Route::any('rerate_billing/{id}', [PolicyController::class,'rerateBilling'])->name('policy.rerate_billing');
        Route::any('getRateDetails/{id}', [PolicyController::class,'getRateDetails'])->name('policy.getRateDetails');
        Route::any('getPremiumUpdateHistory/{policyNumber}', [PolicyController::class,'getPremiumUpdateHistory'])->name('policy.getPremiumUpdateHistory');
        Route::any('getCashPaymentLoggedData/{policyNumber}', [PolicyController::class,'getCashPaymentLoggedData'])->name('policy.getCashPaymentLoggedData');
        Route::any('getPremiumLogsDataDetails/{policyNumber}', [PolicyController::class,'getPremiumLogsDataDetails'])->name('policy.getPremiumLogsDataDetails');

        Route::get('quotationPdf/{policyId}/{termId}/{actionId}', [PolicyController::class,'quotationPdf'])->name('policy.quotationPdf');
        Route::get('v2_quotationPdf/{policyId}/{termId}/{actionId}', [PolicyController::class,'v2_quotationPdf'])->name('policy.v2_quotationPdf');
        Route::get('v2_quotationPdf/{policyId}/{termId}/{actionId}', [PolicyController::class,'v2_quotationPdf_new'])->name('policy.v2_quotationPdf_new');
        Route::get('v2_quotationPdfEngineering/{policyId}/{termId}/{actionId}/{flag}', [PolicyController::class,'v2_quotationPdfEngineering'])->name('policy.v2_quotationPdfEngineering');
        Route::get('v2_quotationPdfSpecialistProduct/{policyId}/{termId}/{actionId}/{flag}', [PolicyController::class,'v2_quotationPdfSpecialistProduct'])->name('policy.v2_quotationPdfSpecialistProduct');
        Route::get('v2_quotationPdfProfessionalIndemnity/{policyId}/{termId}/{actionId}', [PolicyController::class,'v2_quotationPdfProfessionalIndemnity'])->name('policy.v2_quotationPdfProfessionalIndemnity');
        // web.php
        Route::get('reinsurance-slip/{policyId}/{actionId}/{groupId}', [ReinsuranceController::class, 'reinsuranceSlip'])->name('policy.reinsuranceSlip');
        Route::get('reinsurance-document/{policyId}/{actionId}/{groupId}', [ReinsuranceController::class, 'reinsuranceDocument'])->name('policy.reinsuranceDocument');
        Route::get('reinsurance/add', [ReinsuranceController::class, 'add'])->name('reinsurance.add');
        // Route::post('/reinsurance-slip', [ReinsuranceController::class, 'store'])
        // ->name('policy.storeReinsuranceSlip');

        // Route::get('/reinsurance-slip/pdf/{id}', [ReinsuranceController::class, 'generateReinsuranceSlip'])
        // ->name('admin.policy.generateReinsuranceSlip');

        
        Route::get('v2_quotationSinglePdf/{policyId}/{termId}/{actionId}/{riskAddress}', [PolicyController::class,'v2_quotationSinglePdf'])->name('policy.v2_quotationSinglePdf');
        Route::get('download-quote-pdf/{pdfJobId}', [PolicyController::class,'downloadQuotePdf'])->name('policy.downloadQuotePdf');
        Route::get('coverageRateSheet/{policyId}/{termId}/{actionId}', [PolicyController::class,'coverageRateSheet'])->name('policy.coverageRateSheet');
        Route::get('policy_schedule/{policyId}/{termId}/{actionId}', [PolicyController::class,'policy_schedule'])->name('policy.policy_schedule');
        Route::get('v2_policy_schedule/{policyId}/{termId}/{actionId}', [PolicyController::class,'v2_policy_schedule'])->name('policy.v2_policy_schedule');
        Route::get('policy_schedule_wordings/{policyId}/{termId}/{actionId}', [PolicyController::class,'policy_schedule_wordings'])->name('policy.policy_schedule_wordings');
        Route::get('coverageRulesValitionCheck/{policyId}/{termId}/{actionId}', [PolicyController::class,'coverageRulesValidationCheck'])->name('policy.coverageRulesValitionCheck');

        Route::post('addDiscountSurcharge/{identity_id}', [PolicyController::class,'addDiscountSurcharge'])->name('admin.policy.addDiscountSurcharge');
        Route::get('archivedData', [PolicyController::class,'archivedData'])->name('policy.archivedData');
        Route::get('archived-data', [PolicyController::class,'archivedData'])->name('policy.archived-data');
        Route::any('sendPolicyMailForTest', [PolicyController::class,'sendPolicyMailForTest'])->name('policy.sendPolicyMailForTest');
        Route::any('rpGetBranches', [PolicyController::class,'getRealPayBranches'])->name('policy.rpGetBranches');
        Route::get('getclaimsdata/{id}', [PolicyController::class,'getclaimsdata'])->name('policy.getclaimsdata');
        Route::any('storeCashPayment', [PolicyController::class,'storeCashPayment'])->name('policy.storeCashPayment');
        Route::get('activity/{id}', [PolicyController::class,'activity'])->name('policy.activity');
        Route::get('ledger/{id}', [PolicyController::class,'ledger'])->name('policy.ledger');
        Route::get('/', [PolicyController::class,'index']);//->name('policy');
        Route::any('addOfflinePaymentPolicyRenewal', [PolicyController::class,'addOfflinePaymentPolicyRenewal'])->name('policy.addOfflinePaymentPolicyRenewal');

        Route::get('getPolicyTermsData/{id}', [PolicyController::class,'getPolicyTermsData'])->name('policy.getPolicyTermsData');
        Route::get('policyTermsView/{id}', [PolicyController::class,'policyTermsView'])->name('policy.policyTermsView');
        Route::get('getPolicyReinstateData/{id}', [PolicyController::class,'getPolicyReinstateData'])->name('policy.getPolicyReinstateData');
        Route::get('policyTermsEdit/{id}', [PolicyController::class,'policyTermsEdit'])->name('policy.policyTermsEdit');
        Route::get('policyTermDelete/{id}', [PolicyController::class,'policyTermDelete'])->name('policy.policyTermDelete');
        Route::post('policyTermUpdateData', [PolicyController::class,'policyTermUpdateData'])->name('policy.policyTermUpdateData');
        Route::post('changeVehicleModel', [PolicyController::class,'changeVehicleModel'])->name('policy.changeVehicleModel');

        Route::get('/viewArchivedPolicies', [PolicyController::class,'viewArchivedPolicies'])->name('policy.viewArchivedPolicies');
        Route::any('show/{id}', [PolicyController::class,'show']);//->name('policy.show');
        Route::post('attachmentUpload/{id}',[PolicyController::class,'attachmentUpload'])->name('policy.attachmentUpload');
        Route::get('attachmentData/{id}',[PolicyController::class,'attachmentData'])->name('policy.attachmentData');
        Route::post('confirm-delete',[PolicyController::class,'getModalDelete'])->name('policy.confirm-delete');
        Route::any('/removeAttachment',[PolicyController::class,'removeAttachment'])->name('policy.removeAttachment');
        Route::get('{policy}/delete', [PolicyController::class,'destroy'])->name('policy.delete');
        Route::post('attachment-confirm-delete', [PolicyController::class,'getModalAttachmentDelete'])->name('policy.attachment-confirm-delete');
        Route::get('{id}/attachment_delete', [PolicyController::class,'destroy_attachment'])->name('policy.attachment_delete');

        Route::post('invoice-confirm-delete', [PolicyController::class,'getModalInvoiceDelete'])->name('policy.invoice-confirm-delete');
        Route::get('{id}/invoice_delete', [PolicyController::class,'destroyInvoice'])->name('policy.invoice_delete');

        Route::get('discountSurchargePolicyTable/{id}',[PolicyController::class,'discountSurchargePolicyTable'])->name('policy.discountSurchargePolicyTable');
        Route::post('discount-surcharge-policy-confirm-delete', [PolicyController::class,'getModaldiscountSurchargePolicyDelete'])->name('policy.discount-surcharge-policy-confirm-delete');
        Route::get('{id}/discount_surcharge_policy_delete', [PolicyController::class,'destroy_discount_surcharge_policy'])->name('policy.discount_surcharge_policy_delete');

        Route::get('policyEarnedPremium/{id}',[PolicyController::class,'policyEarnedPremium'])->name('policy.policyEarnedPremium');

        Route::get('smsEmailData/{id}',[PolicyController::class,'smsEmailData'])->name('policy.smsEmailData');
        Route::get('{id}/sms_email_log_delete', [PolicyController::class,'destroy_sms_email_log'])->name('policy.sms_email_log_delete');
        Route::post('sms-email-log-confirm-delete', [PolicyController::class,'getModalsmsEmailLogDelete'])->name('policy.sms-email-log-confirm-delete');

        Route::post('product_factors', [PolicyController::class,'getProductFactors'])->name('policy.product_factors');
        Route::post('calculatePremium', [PolicyController::class,'calculatePremium'])->name('policy.calculatePremium');
        Route::post('discountSurcharge', [PolicyController::class,'policyDiscountSurcharge'])->name('policy.discountSurcharge');

        Route::get('processClaim/{id}/{type?}', [PolicyController::class,'processClaim'])->name('policy.processClaim');
        Route::post('storeClaim', [PolicyController::class,'storeClaim'])->name('policy.storeClaim');
        Route::any('addDiscountSurcharge/{id}', [PolicyController::class,'addDiscountSurchargePolicy'])->name('policy.addDiscountSurcharge');
        Route::any('applyCustomRate/{id}', [PolicyController::class,'addCustomRatePolicy'])->name('policy.applyCustomRate');
        Route::any('customPolicyDiscountSurchargeAPI', [PolicyController::class,'customPolicyDiscountSurchargeAPI'])->name('policy.customPolicyDiscountSurchargeAPI');
        Route::any('customPolicyDiscountSurcharge', [PolicyController::class,'customPolicyDiscountSurcharge'])->name('policy.customPolicyDiscountSurcharge');
        Route::get('addOfflinePayment', [PolicyController::class,'addOfflinePaymentPolicy'])->name('policy.addOfflinePayment');
        Route::post('processRenewalPolicy', [PolicyController::class,'processRenewalPolicy'])->name('policy.processRenewalPolicy');
        // Route::post('addOfflinePayment', [PolicyController::class,'addOfflinePaymentPolicy'])->name('policy.addOfflinePayment');
        Route::any('createNewTerms/{policy_id}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'createNewTermsView'])->name('policy.createNewTerms');
        Route::post('addTermsToPoliciesWithButton', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'addTermsToPoliciesWithButton'])->name('policy.addTermsToPoliciesWithButton');

        Route::any('renew/{id}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'renewPolicy'])->name('policy.renew');
        Route::any('renew-policy', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'policyRenewalOperations'])->name('policy.renew_policy');
        Route::any('ledger-invoice-policy', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'LedgerInvoiceDelete'])->name('policy.ledger_invoice_delete');
        Route::any('transaction-log-delete', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'TransactionLogDelete'])->name('policy.transaction_log_delete');
        Route::any('transaction-log-delete2', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'TransactionLogDelete2'])->name('policy.transaction_log_delete2');

        Route::any('Reinstate/{id}/{name}',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'ReinstatePolicy'])->name('policy.reinstate');
        Route::any('Reinstate_areas/{id}',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'Reinstate_areas'])->name('policy.reinstate_areas');
        Route::get('reinstate_accidentally/{id}/{name}',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'reinstateAccidentally'])->name('policy.reinstate_accidentally');
        Route::any('reinstateSetting',[PolicyController::class,'reinstateSetting'])->name('reinstate.setting');
        //Route::get('setReinstate',[\AlphaDirect\Http\Controllers\Admin\PolicyController\PolicyController::class,'setReinstate'])->name('policy.setreinstate');
        Route::post('setReinstate',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'setReinstate'])->name('policy.setreinstate');
        Route::post('reinstantPaymentMethod',[PolicyController::class, 'reinstantPaymentMethod'])->name('policy.reinstantPaymentMethod');
        Route::post('addCashPaymentPolicyReinstate',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'addCashPaymentPolicyReinstate'])->name('policy.addCashPaymentPolicyReinstate');
        Route::post('addRealPayPaymentPolicyReinstate',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'addRealPayPaymentPolicyReinstate'])->name('policy.addRealPayPaymentPolicyReinstate');
        Route::get('policyLifeCycle/{id}',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'policyLifeCycleData'])->name('policy.policyLifeCycle');
        Route::get('policyCancelledAccidently/{id}',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'policyCancelledAccidently'])->name('policy.policyCancelledAccidently');

        Route::post('savekyccustomer', [PolicyController::class,'savekyccustomer'])->name('policy.savekyccustomer');

        Route::post('savekyccustomerDomgComg', [PolicyController::class,'savekyccustomerDomgComg'])->name('policy.savekyccustomerDomgComg');

        Route::delete('/image/delete', [PolicyController::class, 'deleteKycDocument'])->name('image.delete');


        Route::any('add_invoice/{id}',[PolicyController::class,'add_invoice'])->name('policy.add_invoice');
        Route::any('add_invoice_com_dom/{id}',[PolicyController::class,'add_invoice_com_dom'])->name('policy.add_invoice_com_dom');
        Route::any('add_reinsurance_calculations/{id}',[PolicyController::class,'add_reinsurance_calculations'])->name('policy.add_reinsurance_calculations');

        Route::any('action/{id}/{status}', [PolicyController::class,'action'])->name('policy.action');
        Route::get('activate-policy/{id}/{status}', [PolicyController::class,'actionToActivate'])->name('policy.activate-policy');
        Route::get('deletePolicyMotorItem/{policyMotorItemId}', [PolicyController::class,'deletePolicyMotorItem']);
        Route::post('checkUserPassport', [PolicyController::class,'checkUserPassport'])->name('policy.checkUserPassport');
        Route::post('checkUserVehicle', [PolicyController::class,'checkUserVehicle'])->name('policy.checkUserVehicle');
        Route::post('checkProductType', [PolicyController::class,'checkProductType'])->name('policy.checkProductType');
        Route::post('generateActivationCode', [PolicyController::class,'generateActivationCode'])->name('policy.generateActivationCode');
        Route::post('checkUserOmang', [PolicyController::class,'checkUserOmang'])->name('policy.checkUserOmang');
        Route::post('checkMakeModel', [PolicyController::class,'checkMakeModel'])->name('policy.checkMakeModel');
        Route::post('calculatePerDayPremiumRatingID', [PolicyController::class,'calculatePerDayPremiumRatingID'])->name('policy.calculatePerDayPremiumRatingID');
        Route::post('checkActivation', [PolicyController::class,'checkActivation'])->name('policy.checkActivation');
        Route::post('updateBanking', [PolicyController::class,'updateBanking'])->name('policy.updateBanking');
        Route::post('specifiedItemDelete', [PolicyController::class,'specifiedItemDelete'])->name('policy.specifiedItemDelete');
        Route::get('subLedgerIndex', [PolicyController::class,'subLedgerIndex'])->name('policy.subLedgerIndex');
        Route::get('subLedgerData', [PolicyController::class,'subLedgerData']);//->name('policy.subLedgerData');
        Route::get('paymentURLdata/{id}', [PolicyController::class,'paymentURLdata'])->name('policy.paymentURLdata');
        Route::any('urlPaymentForm', [PolicyController::class,'urlPaymentForm'])->name('policy.urlPaymentForm');
        Route::get('view/{id}', [PolicyController::class,'view'])->name('policy.view');
        Route::get('policyView/{id}', [PolicyController::class,'policyView'])->name('policy.policyView');
        Route::post('update/{id}', [PolicyController::class, 'update'])->name('policy.update');
        Route::get('sendSMSData', [PolicyController::class,'sendSMSData'])->name('policy.sendSMSData');
        Route::post('changePaymentStatus', [PolicyController::class,'changePaymentStatus'])->name('policy.changePaymentStatus');
        Route::post('checkreserve_amount', [PolicyController::class,'checkreserve_amount'])->name('policy.checkreserve_amount');
        Route::post('deleteBeneficiary', [PolicyController::class,'deleteBeneficiary'])->name('policy.deleteBeneficiary');
        Route::get('{beneficiary_id}/removeBeneficiary', [PolicyController::class,'removeBeneficiary'])->name('policy.removeBeneficiary');
        Route::get('executeRules', [PolicyController::class,'executeRules'])->name('policy.executeRules');
        Route::get('refreshDate', [PolicyController::class,'refreshDate'])->name('policy.refreshDate');
        Route::get('refreshEntries', [PolicyController::class,'refreshEntries'])->name('policy.refreshEntries');
        Route::any('/getModalData', [PolicyController::class,'getModalData'])->name('policy.getModalData');
        Route::any('/verifyOTP', [PolicyController::class,'verifyOTP'])->name('policy.verifyOTP');
        Route::any('/archive/{id}', [PolicyController::class,'archivePolicyData'])->name('policy.archive');
        Route::any('/restore/{id}', [PolicyController::class,'restorePolicyData'])->name('policy.restore');
        Route::get('earned_premiumsView/{id}', [PolicyController::class,'earned_premiumsView'])->name('policy.earned_premiumsView');
        Route::post('createRealpayPayment', [PolicyController::class,'createRealpayPaymentView'])->name('policy.createRealpayPayment');
        Route::get('DocumentDeleteData/{id}', [PolicyController::class,'DocumentDeleteData'])->name('policy.DocumentDeleteData');
        # Policy Ledger Data Management
        Route::get('ledgerAccountView/{id}', [PolicyController::class,'ledgerAccountView'])->name('policy.ledgerAccountView');
        Route::get('recievableData/{id}', [PolicyController::class,'recievableData'])->name('policy.recievableData');
        Route::get('invoicingData/{id}', [PolicyController::class,'invoicingData'])->name('policy.invoicingData');
        Route::get('subLedgerData/{id}', [PolicyController::class,'subLedgerData'])->name('policy.subLedgerData');
        Route::get('transactionLogs/{id}', [PolicyController::class,'transactionLogs'])->name('policy.transactionLogs');
        Route::get('paymentTransactions/{id}', [PolicyController::class,'paymentTransactions'])->name('policy.paymentTransactions');
        Route::any('sentDocuments/{id}', [PolicyController::class,'getsentPolicyDocuments'])->name('policy.sentDocuments');
        Route::any('sentDocumentsonwhatsapp/{id}', [PolicyController::class,'sentDocumentsonwhatsapp'])->name('policy.sentDocumentsonwhatsapp');
        Route::any('policyDocuments/{id}', [PolicyController::class,'getPolicyDocuments'])->name('policy.getDocuments');
        Route::any('sendPolicyDocument/{id}', [PolicyController::class,'sendPolicyDocument'])->name('policy.sendPolicyDocument');
        Route::any('reSendPolicyDocument/{id}', [PolicyController::class,'reSendPolicyDocument'])->name('policy.reSendPolicyDocument');
        Route::any('regeneratePolicyDocument/{id}', [PolicyController::class,'regeneratePolicyDocument'])->name('policy.regeneratePolicyDocument');
        Route::any('regenerateNewPolicyDocument/{policyId}/{termId}/{actionId}', [PolicyController::class,'regenerateNewPolicyDocument'])->name('policy.regenerateNewPolicyDocument');
        Route::any('regenerateNewPolicyCancellationNote/{policyId}/{termId}/{actionId}', [PolicyController::class,'regenerateNewPolicyCancellationNote'])->name('policy.regenerateNewPolicyCancellationNote');
        Route::any('downloadPolicyDocs/{id}', [PolicyController::class,'downloadPolicyDocs'])->name('policy.downloadPolicyDocs');
        Route::any('generatePolicySmsEmailLogPdf/{id}', [PolicyController::class,'generatePolicySmsEmailLogPdf'])->name('policy.generatePolicySmsEmailLogPdf');
        Route::post('cancelDPODuplicateTransactions',[PolicyController::class,'cancelDPODuplicateTransactions'])->name('policy.cancelDPODuplicateTransactions');

        Route::any('regenerateInformationDocument/{id}', [PolicyController::class,'regenerateInformationDocument'])->name('policy.regenerateInformationDocument');
        Route::post('uploadKycImages/{policy_id}', [PolicyController::class,'uploadKycImages'])->name('policy.uploadKycImages');
        Route::post('uploadVehicleImages/{policy_id}', [PolicyController::class,'uploadVehicleImages'])->name('policy.uploadVehicleImages');
        Route::post('updatePolicyNotes/{policy_id}', [PolicyController::class,'updatePolicyNotes'])->name('policy.updatePolicyNotes');
        Route::any('getInvoice/{id}', [PolicyController::class,'getInvoice'])->name('policy.getInvoice');
        Route::get('getInvoiceforDomCom/{id}', [PolicyController::class,'getInvoiceforDomCom'])->name('policy.getInvoiceforDomCom');
        Route::any('updateDevices', [PolicyController::class,'updateDevices'])->name('policy.updateDevices');
        Route::post('agentUpdate',[PolicyController::class,'agentUpdate'])->name('policy.agentUpdate');
        Route::post('make-refund',[PolicyController::class,'makeRefund'])->name('policy.makeRefund');
        // Route::get('creditNote/{id}',[PolicyController::class,'getCreditNote'])->name('creadit-note');//sanket sir
        // Route::any('submitToIssue/{id}',[PolicyController::class,'submitToIssue'])->name('policy.submitToIssue');
        // Route::any('submitToApproval/{id}',[PolicyController::class,'submitToApproval'])->name('policy.submitToApproval');
        // Route::any('submitToReject/{id}',[PolicyController::class,'submitToReject'])->name('policy.submitToReject');
        // Route::any('inApproval/{id}',[PolicyController::class,'inApproval'])->name('policy.inApproval');
        Route::get('creditNoteView/{id}',[PolicyController::class,'creditNoteView'])->name('policy.creditNoteView');
        Route::get('creditSendNoteMail/{id}',[PolicyController::class,'creditSendNoteMail'])->name('policy.creditSendNoteMail');
        Route::any('check',[PolicyController::class,'checkPolicy'])->name('policy.check');
        Route::post('sendMatiVerificationLink/{customer_id}/{type}',[PolicyController::class,'sendMatiVerificationLink'])->name('policy.sendMatiVerificationLink');
        Route::post('cancelledPolicySmsEmail/{customer_id}/{type}',[PolicyController::class,'cancelledPolicySmsEmail'])->name('policy.cancelledPolicySmsEmail');
        Route::post('fetchMetaData',[PolicyController::class,'fetchMetaData'])->name('policy.fetchMetaData');
        Route::post('makePaymentNow',[DpoPaymentController::class,'makePaymentNow'])->name('policy.makePaymentNowDpo');
        Route::post('suspendPaymentDpo',[DpoPaymentController::class,'suspendPaymentDpo'])->name('policy.suspendPaymentDpo');
        Route::post('suspendPaymentDpoAll',[DpoPaymentController::class,'suspendPaymentDpoAll'])->name('policy.suspendPaymentDpoAll');
        Route::get('pay-with-dpo',[DpoPaymentController::class,'payWithDpo'])->name('policy.payWithDpo');
        Route::get('fetch-dpo-transactions',[DpoPaymentController::class,'fetchDpoTransactions'])->name('policy.fetchDpoTransactions');
        Route::post('pay-with-dpo-store',[DpoPaymentController::class,'payWithDpoStore'])->name('policy.payWithDpoStore');
        Route::post('get-dpo-transactions',[DpoPaymentController::class,'getDpoTransactions'])->name('policy.getDpoTransactions');
        Route::post('add-schedule-transaction',[DpoPaymentController::class,'addScheduleTransaction'])->name('policy.addScheduleTransaction');
        Route::post('update-billing-date-schedule-transaction',[PolicyController::class,'updateBillingDateScheduleTransaction'])->name('policy.updateBillingDateScheduleTransaction');
        Route::post('update-premium-schedule-transaction',[PolicyController::class,'updatePremiumScheduleTransaction'])->name('policy.updatePremiumScheduleTransaction');
        Route::post('makeOrangePaymentNow',[PaymentController::class,'OrangePaymentNoworangeMandateExcute'])->name('policy.makeOrangePaymentNow');
        Route::post('removeOrangePayment',[PaymentController::class,'removeOrangePayment'])->name('policy.removeOrangePayment');
        Route::get('dpo-transactions-import',[DpoPaymentController::class,'dpoTransactionImport'])->name('policy.dpoTransactionsExcel');
        Route::post('get-dpo-transactions-import',[DpoPaymentController::class,'getDpoTransactionImport'])->name('policy.getDpoTransactionImport');
        Route::get('send-mail-to-blocked-customer',[\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'sendMailToBlockedCustomer'])->name('policy.sendMailToBlockedCustomer');
        Route::post('convertPolicyFrequency',  [PolicyController::class, 'convertPolicyFrequency'])->name('policy.convertPolicyFrequency');
        Route::post('updateRealpayAllInstallmentData',[PolicyController::class,'updateRealpayAllInstallmentData'])->name('policy.updateRealpayAllInstallmentData');
        Route::post('updateRealpayInstallment',[PolicyController::class,'updateRealpayInstallment'])->name('policy.updateRealpayInstallment');
        Route::post('addNewRealpayInstallment',[PolicyController::class,'addNewRealpayInstallment'])->name('policy.addNewRealpayInstallment');
        Route::get('realpay-transactions-import',[RealPayController::class,'realpayTransactionsExcel'])->name('policy.realpayTransactionsExcel');
        Route::post('get-realpay-transactions-import',[RealPayController::class,'getRealpayTransactionImport'])->name('policy.getRealpayTransactionImport');

        Route::get('excel-import',[ExcelImportController::class,'excelImport'])->name('policy.excelImport');
        Route::get('CreditNoteAndInvioce',[ExcelImportController::class,'CreditNoteAndInvioce'])->name('policy.CreditNoteAndInvioce');
        Route::post('get-excel-import',[ExcelImportController::class,'getExcelImport'])->name('policy.getExcelImport');
        Route::post('CreditNoteAndInvioceGen',[ExcelImportController::class,'CreditNoteAndInvioceGen'])->name('policy.CreditNoteAndInvioceGen');

        Route::get('excel-import-policy-activate',[ExcelImportController::class,'excelImportPolicyActivation'])->name('policy.excelImportPolicyActivation');
        Route::post('get-excel-import-policy-activate',[ExcelImportController::class,'getExcelImportPolicyActivation'])->name('policy.getExcelImportPolicyActivation');
        Route::get('excel-import-dpo-refund',[ExcelImportController::class,'excelImportDPORefund'])->name('policy.excelImportDPORefund');
        Route::post('get-excel-import-dpo-refund',[ExcelImportController::class,'getExcelImportDpoRefund'])->name('policy.getExcelImportDpoRefund');
        Route::get('excel_n_genius_add_transaction',[ExcelImportController::class,'excel_n_genius_add_transaction'])->name('policy.excel_n_genius_add_transaction');
        Route::post('get_excel_n_genius_add_transaction',[ExcelImportController::class,'get_excel_n_genius_add_transaction'])->name('policy.get_excel_n_genius_add_transaction');

        Route::get('excel-import-policy-cancel',[ExcelImportController::class,'excelImportPolicyCancellation'])->name('policy.excelImportPolicyCancellation');
        Route::post('get-excel-import-policy-cancel',[ExcelImportController::class,'getExcelImportPolicyCancellation'])->name('policy.getExcelImportPolicyCancellation');

        Route::get('excel-expired-policies',[ExcelImportController::class,'excelExpiredPolicies'])->name('policy.excelExpiredPolicies');
        Route::post('get-excel-expired-policies',[ExcelImportController::class,'getExcelExpiredPolicies'])->name('policy.getExcelExpiredPolicies');
        Route::get('excel-create-policies',[PolicyFileController::class,'excelPoliciesCreate'])->name('policy.excelPoliciesCreate');
        Route::post('excel-upload-policies',[PolicyFileController::class,'upload'])->name('policy.excelPoliciesUpload');
        Route::get('excel-policy-cancel',[PolicyFileController::class,'excelImportPolicyCancellation'])->name('policy.excelPolicyCancellation');
        Route::post('get-excel-policy-cancel',[PolicyFileController::class,'getExcelImportPolicyCancellation'])->name('policy.getExcelPolicyCancellation');

        Route::get('excel-policy-create-cancel-report',[PolicyFileController::class,'policyCreateCancelReport'])->name('policy.policyCreateCancelReport');
        Route::get('bonupolicy',[PolicyFileController::class,'bonupolicy'])->name('policy.bonupolicy');
        Route::get('bonupolicydata',[PolicyFileController::class,'bonupolicydata'])->name('policy.bonupolicydata');
        Route::get('excel-policy-create-cancel-report-data',[PolicyFileController::class,'policyCreateCancelReportData'])->name('policy.policyCreateCancelReportData');
        Route::get('policy-activate-cancel-report',[ExcelImportController::class,'policyActivateCancelReport'])->name('policy.policyActivateCancelReport');
        Route::get('policy-activate-cancel-report-data',[ExcelImportController::class,'policyActivateCancelReportData'])->name('policy.policyActivateCancelReportData');
        Route::get('PolicyVatChange',[ExcelImportController::class,'PolicyVatChange'])->name('policy.PolicyVatChange');
    });
    Route::resource('policy', 'PolicyController');

    Route::get('policyViewSonali/{id}', [PolicySonaliController::class,'policyViewSonali'])->name('policyViewSonali');
    Route::get('ledgerAccountViewSonali/{id}', [PolicySonaliController::class,'ledgerAccountViewSonali'])->name('ledgerAccountViewSonali');
    Route::get('recievableDataSonali/{id}', [PolicySonaliController::class,'recievableDataSonali'])->name('policy.recievableDataSonali');
    Route::get('invoicingDataSonali/{id}', [PolicySonaliController::class,'invoicingDataSonali'])->name('policy.invoicingDataSonali');
    Route::get('subLedgerDataSonali/{id}', [PolicySonaliController::class,'subLedgerDataSonali'])->name('policy.subLedgerDataSonali');
    Route::any('getInvoiceSonali/{id}', [PolicySonaliController::class,'getInvoiceSonali'])->name('getInvoiceSonali');
    Route::get('creditNoteViewSonali/{id}',[PolicySonaliController::class,'creditNoteView'])->name('policy.creditNoteViewSonali');
    Route::any('creditNoteStatementSonali/{id}/{ledger}', [PolicySonaliController::class, 'creditNoteStatementSonali'])->name('admin.policy.creditNoteStatementSonali');

    Route::get('policyPaymentStatus', [PolicyController::class, 'policyPaymentStatus'])->name('policy_payment_status');
    Route::get('policyPaymentStatus/data', [PolicyController::class, 'policyPaymentStatusData'])->name('policy_payment_status.data');
    Route::get('payamentStatusDumpData', [PolicyController::class, 'payamentStatusDumpData'])->name('policy_payment_status.payamentStatusDumpData');
    Route::get('payamentStatusDumpDataExport', [PolicyController::class, 'payamentStatusDumpDataExport']);
    //payment reminder sms log route

    Route::get('policyReminderSmsLog', [\AlphaDirect\Http\Controllers\Admin\PaymentReminderSmsLogController::class,'index'])->name('policyReminderSmsLog');
    Route::get('policyReminderSmsLog/data', [\AlphaDirect\Http\Controllers\Admin\PaymentReminderSmsLogController::class,'data'])->name('policyReminderSmsLog.data');

    //route for send sms button

    Route::get('sendPolicyPaymentSms', [PolicyController::class, 'sendPolicyPaymentSms'])->name('sendPolicyPaymentSms');
    Route::group(['prefix' => 'subLedger'], function () {
        Route::get('subLedgerIndex', [SubLedgerController::class, 'subLedgerIndex'])->name('subLedger.subLedgerIndex');
        Route::get('subLedgerData', [SubLedgerController::class , 'subLedgerData'])->name('subLedger.subLedgerData');
    });
    Route::resource('subLedger', 'SubLedgerController');

    # Department Management
    Route::group(['prefix' => 'department'], function () {
//        Route::get('data', [DepartmentController::class, 'data'])->name('department.data');
        Route::get('create', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'create'])->name('department.create');
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'index'])->name('department');
        Route::any('show/{id}', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'show']);//->name('department.show');
        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'getModalDelete'])->name('department.confirm-delete');
        Route::get('{productPlan}/delete', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'destroy'])->name('department.delete');
        Route::post('store', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'store']);//->name('department.store');
        Route::get('edit', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'edit']);//->name('department.edit');
        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'getModalDelete'])->name('department.confirm-delete');
        Route::get('data',[\AlphaDirect\Http\Controllers\Admin\DepartmentController::class, 'data'])->name('department.data');
    });
    Route::resource('department', 'DepartmentController');

    Route::group(['prefix' => 'customerCashback'], function () {
           Route::get('setting',[\AlphaDirect\Http\Controllers\Admin\CustomerController::class,'setting_percentage'])->name('customerCashback.setting');
           Route::post('settingPercentage',[\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'settingPercentage'])->name('customerCashback.settingpercentage');
           Route::get('customerCashback_data',[\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'customerCashback_data'])->name('customerCashback.data');
           Route::get('/',[\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'customerCashback'])->name('cutomerCashback');
    });



    # Reports Management
    Route::group(['prefix' => 'report'], function () {
        Route::get('/', [ReportController::class, 'index'])->name('report');
        Route::get('Cronreport', [ReportController::class, 'CronReport'])->name('CronReport');
        Route::get('orangeTransactions', [ReportController::class, 'getOrangeTransactions'])->name('orangeTransactions');
        Route::get('getOrangeTransactionReport', [ReportController::class, 'getOrangeTransactionReport'])->name('getOrangeTransactionReport');
        Route::get('orangeTransactionsData', [ReportController::class, 'getOrangeTransactionsData'])->name('report.orangeTransactionsData');
        Route::get('orangeReport', [ReportController::class, 'orangeReport'])->name('report.orange');
        Route::get('orangeReportData', [ReportController::class, 'orangeReportData'])->name('report.orangeReportData');

        Route::get('motor-comprehensive-report', [ReportController::class, 'getVehicleReport'])->name('report.motor-comprehensive-report');
        Route::get('data', [ReportController::class, 'data'])->name('report.data');
        Route::get('allPolicyData', [ReportController::class, 'allPolicyData'])->name('report.allPolicyData');
        Route::get('CronReportdata', [ReportController::class, 'CronReportdata'])->name('report.CronReportdata');
        Route::get('allPolicyVehicleData', [ReportController::class, 'allPolicyVehicleData'])->name('report.allPolicyVehicleData');
        Route::get('policyVehicleData', [ReportController::class, 'policyVehicleData'])->name('report.policyVehicleData');
        Route::get('allPolicyDataJson', [ReportController::class, 'allPolicyDataJson'])->name('report.allPolicyDataJson');
        Route::get('realpayData', [ReportController::class, 'realpayTransactionData'])->name('report.realpayData');
        Route::get('realpayTransactionExport', [ReportController::class, 'realpayTransactionExport'])->name('report.realpayTransactionExport');
        Route::get('user-excel-report', [UserController::class,'userExcelReport']);
        Route::get('allReportUserDataJson', [UserController::class,'allReportUserDataJson']);
        Route::get('dataReport', [UserController::class,'dataReport']);
        Route::get('detailed-age-analysis', [ReportController::class, 'detailedAgeAnalysis']);
        Route::get('detailed-age-analysis-data', [ReportController::class, 'detailedAgeAnalysisData']);
        Route::get('detailed-age-analysis-data-export', [ReportController::class, 'detailedAgeAnalysisDataExport']);
        Route::get('summary-age-analysis', [ReportController::class, 'summaryAgeAnalysis']);
        Route::get('summary-age-analysis-data', [ReportController::class, 'summaryAgeAnalysisData']);
        Route::get('summary-age-analysis-data-export', [ReportController::class, 'summaryAgeAnalysisDataExport']);
        Route::get('summary-age-analysis-report-dump', [ReportController::class, 'summaryAgeAnalystReportDump']);

        Route::get('reInsuranceRiskProfile', [ReportController::class, 'reInsuranceRiskProfile']);
        Route::post('reInsuranceRiskProfileData', [ReportController::class, 'reInsuranceRiskProfileData'])->name('report.reInsuranceRiskProfileData');
        Route::get('reInsuranceRiskProfileExport', [ReportController::class, 'reInsuranceRiskProfileExport'])->name('report.reInsuranceRiskProfileExport');
        Route::get('reInsuranceClaimsProfile', [ReportController::class, 'reInsuranceClaimsProfile']);
        Route::post('reInsuranceClaimsProfileData', [ReportController::class, 'reInsuranceClaimsProfileData'])->name('report.reInsuranceClaimsProfile');
        Route::get('reInsuranceClaimsprofileExport', [ReportController::class, 'reInsuranceClaimsprofileExport']);
        Route::get('earned-premium-with-unearned', [ReportController::class, 'earnedPremiumReport']);
        Route::post('earnedPremiumReportData', [ReportController::class, 'earnedPremiumReportData'])->name('report.earnedPremiumReportData');
        Route::get('earnedPremiumReportExport', [ReportController::class, 'earnedPremiumReportExport']);

        Route::get('policy-status-report', [ReportController::class, 'policyStatusReport']);
        Route::post('getPolicyStatusReport', [ReportController::class, 'getPolicyStatusReport'])->name('report.getPolicyStatusReport');
        Route::get('policyStatusReportExport', [ReportController::class, 'policyStatusReportExport'])->name('report.policyStatusReportExport');

        Route::get('policy-activated-today-report', [ReportController::class, 'policyActivatedTodayReport']);
        Route::get('getPolicyActivatedTodayReport', [ReportController::class, 'getPolicyActivatedTodayReport'])->name('report.getPolicyActivatedTodayReport');

        Route::get('written-premium-report', [ReportController::class, 'writtenPremiumReport']);
        Route::post('writtenPremiumReportData', [ReportController::class, 'writtenPremiumReportData'])->name('report.written_premium_report');
        Route::get('writtenPremiumReportExport', [ReportController::class, 'writtenPremiumReportExport']);
        Route::get('book-of-business-report', [ReportController::class, 'bookOfBusinessReport']);
        Route::get('bookOfBusinessReportExport', [ReportController::class, 'bookOfBusinessReportExport'])->name('report.bookOfBusinessReportExport');
        Route::post('bookOfBusinessReportData', [ReportController::class, 'bookOfBusinessReportData'])->name('report.bookOfBusinessReportData');
        Route::get('transaction-summary-product-report', [ReportController::class, 'transactionSummaryProductReport']);
        Route::get('transactionSummaryProductReportExport', [ReportController::class, 'transactionSummaryProductReportExport'])->name('report.transactionSummaryProductReportExport');
        Route::post('transactionSummaryProductReportData', [ReportController::class, 'transactionSummaryProductReportData'])->name('report.transaction_summary');
        Route::get('transaction-report-by-booking-date', [ReportController::class, 'transactionReportByBookingDate']);
        Route::get('transactionReportByBookingDateExport', [ReportController::class, 'transactionReportByBookingDateExport'])->name('report.transactionReportByBookingDateExport');
        Route::post('gettransactionReportByBookingDate', [ReportController::class, 'gettransactionReportByBookingData'])->name('report.gettransactionReportByBookingData');
        Route::get('transactionReportByProduct', [ReportController::class, 'transactionReportByProduct'])->name('report.transactionfech');
        Route::post('transaction-report-by-product', [ReportController::class, 'transactionReportByProductdata']);
        Route::get('transactionReportByProductExport', [ReportController::class, 'transactionReportByProductExport'])->name('report.transactionReportByProductExport');
        Route::get('reinsuranceRiskProfilesSummary', [ReportController::class, 'reinsuranceRiskProfilesSummary'])->name('report.reinsuranceRiskProfilesSummary');
        Route::get('reinsuranceRiskProfilesSummaryExport', [ReportController::class, 'reinsuranceRiskProfilesSummaryExport']);
        Route::get('reinsuranceRiskSummaryPDF', [ReportController::class, 'reinsuranceRiskSummaryPDF']);
        Route::get('agentReport',[ReportController::class, 'agentReport']);
        Route::get('agentReportExport', [ReportController::class, 'agentReportExport'])->name('report.agentReportExport');
        Route::post('agentReportData', [ReportController::class, 'agentReportData'])->name('report.agent_report');
        Route::get('anniversaryDateReport', [ReportController::class, 'anniversaryDateReport']);
        Route::get('anniversaryDateReportExport', [ReportController::class, 'anniversaryDateReportExport'])->name('report.anniversaryDateReportExport');
        Route::post('anniversaryDateReportData', [ReportController::class, 'anniversaryDateReportData'])->name('report.anniversary_date_report');
        Route::get('claimAsOnDate', [ReportController::class, 'claimAsOnDate']);
        Route::get('claimAsOnDateDataExport', [ReportController::class, 'claimAsOnDateDataExport']);
        Route::post('claimAsOnDateData', [ReportController::class, 'claimAsOnDateData'])->name('report.claim_as_on_date');
        Route::get('claimBordereaux', [ReportController::class, 'claimBordereaux']);
        Route::get('claimBordereauxExport', [ReportController::class, 'claimBordereauxExport'])->name('report.claimBordereauxExport');
        Route::post('getClaimBordereaux', [ReportController::class, 'getClaimBordereaux'])->name('report.getClaimBordereaux');
        Route::get('claimBordereauxOutstandingPayment', [ReportController::class, 'claimBordereauxOutstandingPayment']);
        Route::post('getClaimBordereauxOutstandingPayment', [ReportController::class, 'getClaimBordereauxOutstandingPayment'])->name('report.getClaimBordereauxOutstandingPayment');
        Route::get('claimBordereauxOutstandingPaymentExport', [ReportController::class, 'claimBordereauxOutstandingPaymentExport'])->name('report.claimBordereauxOutstandingPaymentExport');
        Route::get('claimCoverageAllocationReport', [ReportController::class, 'claimCoverageAllocationReport']);
        Route::post('getClaimCoverageAllocationReport', [ReportController::class, 'getClaimCoverageAllocationReport'])->name('report.getClaimCoverageAllocationReport');
        Route::get('claimCoverageAllocationExport', [ReportController::class, 'claimCoverageAllocationExport'])->name('report.claimCoverageAllocationExport');
    });

    # Reports Management
    Route::group(['prefix' => 'policy-report'], function () {
        Route::get('/', [PolicyReportController::class, 'index'])->name('policy-report');
        Route::get('data', [PolicyReportController::class, 'data'])->name('policy-report.data');
        Route::get('allPolicyData', [ReportController::class, 'allPolicyData'])->name('policy-report.allPolicyData');
        Route::get('allPolicyDataJson', [PolicyReportController::class, 'allPolicyDataJson'])->name('policy-report.allPolicyDataJson');
    });

    Route::group(['prefix' => 'transaction-report'], function () {
        Route::get('/', [TransactionReportController::class, 'index'])->name('transaction-report');
        Route::get('/ngpaymentreport', [TransactionReportController::class, 'NGPaymentReport'])->name('ngtransaction-report');
        Route::get('data', [TransactionReportController::class, 'data'])->name('transaction-report.data');
        Route::get('ngdata', [TransactionReportController::class, 'ngdata'])->name('transaction-report.ngdata');
        Route::get('alltransactionData', [TransactionReportController::class, 'alltransactionData'])->name('transaction-report.alltransactionData');
        Route::get('alltransactionDataJson', [TransactionReportController::class, 'alltransactionDataJson'])->name('transaction-report.alltransactionDataJson');
        Route::get('Realpay', [TransactionReportController::class, 'realpayTransaction'])->name('transaction-report.realpay');
    });

    Route::group(['prefix' => 'report'], function () {
        Route::get('failed-transaction-report', [FailedTransactionController::class, 'index'])->name('failed-transaction-report');
        Route::get('allfailedtransactionData', [FailedTransactionController::class, 'data'])->name('report.allfailedtransactionData');
    });

    Route::group(['prefix' => 'vcs-event-log'], function () {
        Route::get('/', [VcsEventLogController::class, 'index'])->name('vcs-event-log');
        Route::get('data', [VcsEventLogController::class, 'data'])->name('vcs-event-log.data');
    });
    Route::resource('report', 'ReportController');

    Route::group(['prefix' => 'claimReport'], function () {
        Route::get('/', [ClaimReportController::class, 'index'])->name('claimReport');
        Route::get('data', [ClaimReportController::class, 'data'])->name('claimReport.data');
        Route::get('allDataJson', [ClaimReportController::class, 'allDataJson'])->name('claimReport.allDataJson');
    });
    Route::resource('claimReport', 'ClaimReportController');

    # Supplier Management
    Route::group(['prefix' => 'supplier'], function () {
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'data'])->name('supplier.data');
        Route::get('create', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'create'])->name('supplier.create');
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'index'])->name('supplier');
        Route::any('show/{id}', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'show']);//->name('supplier.show');
        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'getModalDelete'])->name('supplier.confirm-delete');
        Route::get('{productPlan}/delete', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'destroy'])->name('supplier.delete');
        Route::post('store', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'store']);//->name('supplier.store');
        Route::get('edit', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'edit']);//->name('supplier.edit');
        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'getModalDelete'])->name('supplier.confirm-delete');
        Route::get('quoteView/{quote_id}', [\AlphaDirect\Http\Controllers\Admin\SuppliersController::class, 'quoteView'])->name('supplier.quoteView');
    });
    Route::resource('supplier', SuppliersController::class);

    # Activity Log Management
    Route::group(['prefix' => 'activityLog'], function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('activityLog');
        Route::get('data', [ActivityLogController::class, 'data'])->name('activityLog.data');
    });

    Route::group(['prefix' => 'whatsAppLog'], function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'index'])->name('whatsAppLog');
        Route::get('data', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'data'])->name('whatsAppLog.data');
    });
    Route::resource('activityLog', 'ActivityLogController');

    #product Type Management
    Route::group(['prefix' => 'productType'], function () {
        Route::get('data', [ProductTypeController::class, 'data'])->name('productType.data');
        Route::get('create', [ProductTypeController::class, 'create'])->name('productType.create');
        Route::get('/', [ProductTypeController::class, 'index'])->name('productType');
        Route::any('show/{id}', [ProductTypeController::class, 'show']);//->name('productType.show');
        Route::post('confirm-delete', [ProductTypeController::class, 'getModalDelete'])->name('productType.confirm-delete');
        Route::get('{productPlan}/delete', [ProductTypeController::class, 'destroy'])->name('productType.delete');
        Route::post('store', [ProductTypeController::class, 'store']);//->name('productType.store');
        Route::get('edit', [ProductTypeController::class, 'edit']);//->name('productType.edit');
        Route::post('confirm-delete', [ProductTypeController::class, 'getModalDelete'])->name('productType.confirm-delete');
    });
    Route::resource('productType', 'ProductTypeController');

    # product Plan Management
    Route::group(['prefix' => 'productPlan'], function () {
        Route::get('data', [ProductPlanController::class, 'data'])->name('productPlan.data');
        Route::get('create', [ProductPlanController::class, 'create'])->name('productPlan.create');
        Route::get('/', [ProductPlanController::class, 'index'])->name('productPlan');
        Route::any('show/{id}', [ProductPlanController::class, 'show']);//->name('productPlan.show');
        Route::post('confirm-delete', [ProductPlanController::class, 'getModalDelete'])->name('productPlan.confirm-delete');
        Route::get('{productPlan}/delete', [ProductPlanController::class, 'destroy'])->name('productPlan.delete');
        Route::post('store', [ProductPlanController::class, 'store']);//->name('productPlan.store');
        Route::get('edit', [ProductPlanController::class, 'edit']);//->name('productPlan.edit');
        Route::post('checkSumAssured', [ProductPlanController::class, 'checkSumAssured'])->name('productPlan.checkSumAssured');
    });
    Route::resource('productPlan', 'ProductPlanController');

    # Product Management
    Route::group(['prefix' => 'product'], function () {

        Route::get('data', [ProductController::class, 'data'])->name('product.data');
        Route::get('/', [ProductController::class, 'index'])->name('product');
        Route::post('confirm-delete', [ProductController::class, 'getModalDelete'])->name('product.confirm-delete');
        Route::get('{product}/delete', [ProductController::class, 'destroy'])->name('product.delete');
        Route::get('{id}/formula', [ProductController::class, 'formula'])->name('product.formula');
        Route::post('{id}/formula', [ProductController::class, 'formulaStore'])->name('product.formula_store');
        Route::post('regionLicense', [ProductController::class, 'getRegionLicense'])->name('product.regionLicense');
        Route::post('checkSumAssured', [ProductController::class, 'checkSumAssured'])->name('product.checkSumAssured');
        Route::post('productPlans', [ProductController::class, 'productPlans'])->name('product.productPlans');
        Route::get('{id}/kyc', [ProductController::class, 'kyc'])->name('product.kyc');
    });
    Route::resource('product', 'ProductController');

    # Product factor Main Management
    Route::group(['prefix' => 'factorMain'], function () {
        Route::get('data', [FactorMainController::class, 'data'])->name('factorMain.data');
        Route::get('/', [FactorMainController::class, 'index'])->name('factorMain');
        Route::post('confirm-delete', [FactorMainController::class, 'getModalDelete'])->name('factorMain.confirm-delete');
        Route::get('{factorMain}/delete', [FactorMainController::class, 'destroy'])->name('factorMain.delete');
        Route::get('{id}/factorValueDelete', [FactorMainController::class, 'factorValueDelete'])->name('factorMain.factorValueDelete');
    });
    Route::resource('factorMain', 'FactorMainController');

    # Product Factor Value Management
    Route::group(['prefix' => 'factorValue'], function () {
        Route::get('data', [FactorSubTypeController::class, 'data'])->name('factorValue.data');
        Route::get('/', [FactorSubTypeController::class, 'index'])->name('factorValue');
        Route::post('confirm-delete', [FactorSubTypeController::class, 'getModalDelete'])->name('factorValue.confirm-delete');
        Route::get('{factorValue}/delete', [FactorSubTypeController::class, 'destroy'])->name('factorValue.delete');
    });
    Route::resource('factorValue', 'FactorSubTypeController');

    # Master data Management
    Route::group(['prefix' => 'masterData'], function () {
        Route::get('data', [MasterController::class, 'data'])->name('masterData.data');
        Route::get('/', [MasterController::class, 'index'])->name('masterData');
        Route::post('confirm-delete', [MasterController::class, 'getModalDelete'])->name('masterData.confirm-delete');
        Route::get('{masterData}/delete', [MasterController::class, 'destroy'])->name('masterData.delete');
    });
    Route::resource('masterData', 'MasterController');


    /*Agencies Management*/

    Route::group(['prefix' => 'agency'], function () {
        Route::any('/', [AgencyController::class, 'index'])->name('agency');
        Route::get('data', [AgencyController::class, 'data'])->name('agency.data');
        Route::any('updateData', [AgencyController::class, 'update'])->name('agency.updateData');
    });
    Route::resource('agency', 'AgencyController');


    # User Management
    Route::group(['prefix' => 'user'], function () {
        Route::get('data', [UserController::class, 'data'])->name('user.data');
        Route::get('hardResetUserRoles', [UserController::class, 'hardResetUserRoles'])->name('user.hardResetUserRoles');
        Route::any('generatePassword', [UserController::class, 'generatePassword'])->name('user.generatePassword');
        Route::get('allUserDataJson', [UserController::class, 'allUserDataJson'])->name('user.allUserDataJson');
        Route::get('/', [UserController::class, 'index'])->name('user');
        Route::get('PinIndex', [UserController::class, 'userPinIndex'])->name('user.userPinIndex');
        Route::get('allUserDataPin', [UserController::class, 'allUserDataPin'])->name('user.allUserDataPin');
        Route::get('PinEdit/{id}', [UserController::class, 'userPinEdit'])->name('user.userPinEdit');
        Route::post('PinUpdate', [UserController::class, 'userPinUpdate'])->name('user.userPinUpdate');
        Route::post('confirm-delete', [UserController::class, 'getModalDelete'])->name('user.confirm-delete');
        Route::post('confirm-suspend', [UserController::class, 'getModalSuspendAccount'])->name('user.confirm-suspend');
        Route::get('{user}/delete', [UserController::class, 'destroy'])->name('user.delete');

        Route::get('{user}/suspend', [UserController::class, 'suspend'])->name('user.suspend');
        Route::get('assignRole', [UserController::class, 'assignRole'])->name('user.assignRole');
        Route::get('removeSecondRole', [UserController::class, 'removeSecondRole'])->name('user.removeSecondRole');
        Route::get('{user}/suspend', [UserController::class, 'suspend'])->name('user.suspend');
        Route::get('refreshRoles', [UserController::class, 'refreshRoles'])->name('user.refreshRoles');
        Route::post('checkEmail', [UserController::class, 'checkEmail'])->name('user.checkEmail');
        Route::any('export', [UserController::class, 'export'])->name('user.export');
    });
    Route::resource('user', 'UserController');

    # Region Management
    Route::group(['prefix' => 'region'], function () {
        Route::get('data', [RegionController::class, 'data'])->name('region.data');
        Route::get('/', [RegionController::class, 'index'])->name('region');
        Route::any('show/{id}', [RegionController::class, 'show']);//->name('region.show');
        Route::post('confirm-delete', [RegionController::class, 'getModalDelete'])->name('region.confirm-delete');
        Route::get('{region}/delete', [RegionController::class, 'destroy'])->name('region.delete');
        Route::get('{id}/regionLicenseDelete', [RegionController::class, 'regionLicenseDelete'])->name('region.regionLicenseDelete');
    });
    Route::resource('region', 'RegionController');

    # Email Broadcasting Management
    Route::group(['prefix' => 'emailBroadCasting'], function () {
        Route::get('/', [EmailBroadCastingController::class, 'index'])->name('emailBroadCasting');
        Route::get('data', [EmailBroadCastingController::class, 'data'])->name('emailBroadCasting.data');
        Route::get('create', [EmailBroadCastingController::class, 'create'])->name('emailBroadCasting.create');
        Route::post('confirm-delete', [EmailBroadCastingController::class, 'getModalDelete'])->name('emailBroadCasting.confirm-delete');
        Route::get('{emailBroadCasting}/delete', [EmailBroadCastingController::class, 'destroy'])->name('emailBroadCasting.delete');
        Route::get('compose', [EmailBroadCastingController::class, 'ckeupload'])->name('emailBroadCasting.ckeupload');
        Route::post('confirm-delete', [EmailBroadCastingController::class, 'getModalDelete'])->name('emailBroadCasting.confirm-delete');
        Route::get('{product}/delete', [EmailBroadCastingController::class, 'destroy']);//->name('emailBroadCasting.delete');
        Route::get('emailLogs', [EmailBroadCastingController::class, 'emailLogs'])->name('emailBroadCasting.emailLogs');
        Route::get('delieveredEmails', [EmailBroadCastingController::class, 'delieveredEmails'])->name('emailBroadCasting.delieveredEmails');
        Route::any('getEmailDetails/{id}', [EmailBroadCastingController::class, 'getEmailDetails'])->name('emailBroadCasting.getEmailDetails');
    });
    Route::resource('emailBroadCasting', 'EmailBroadCastingController');

    # SMS Broadcasting Management
    Route::group(['prefix' => 'sms'], function () {
        Route::get('data', [SmsController::class, 'data'])->name('sms.data');
        Route::get('/', [SmsController::class, 'index'])->name('sms');
        Route::any('show/{id}', [SmsController::class, 'show']);//->name('sms.show');
        Route::post('confirm-delete', [SmsController::class, 'getModalDelete'])->name('sms.confirm-delete');
        Route::get('{sms}/delete', [SmsController::class, 'destroy'])->name('sms.delete');
        Route::get('compose', [SmsController::class, 'ckeupload'])->name('sms.ckeupload');
    });
    Route::resource('sms', 'SmsController');
    Route::group(['prefix' => 'smsControl'], function () {

        Route::get('data', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControldata'])->name('smsControl.data');
        Route::get('/', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControl'])->name('smsControl');
        Route::get('create', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControlCreate'])->name('smsControl.create');
        Route::get('edit/{id}', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControlEdit'])->name('smsControl.edit');
        Route::post('store', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControlstore'])->name('smsControl.store');
       // Route::get('show/{id}', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'show'])->name('sms.show');
        Route::post('confirm-delete', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControlgetModalDelete'])->name('smsControl.confirm-delete');
        Route::get('{id}/delete', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'smsControldestroy'])->name('smsControl.delete');
      //  Route::get('compose', [AlphaDirect\Http\Controllers\Admin\SmsController::class, 'ckeupload'])->name('sms.ckeupload');
    });
    # Policy template Management
    Route::group(['prefix' => 'policyTemplate'], function () {
        Route::get('data', [PolicyTemplateController::class, 'data'])->name('policyTemplate.data');
        Route::get('/', [PolicyTemplateController::class, 'index'])->name('policyTemplate');
        Route::any('show/{id}', [PolicyTemplateController::class, 'show']);//->name('policyTemplate.show');
        Route::post('confirm-delete', [PolicyTemplateController::class, 'getModalDelete'])->name('policyTemplate.confirm-delete');
        Route::get('{policyTemplate}/delete', [PolicyTemplateController::class, 'destroy'])->name('policyTemplate.delete');
        Route::get('compose', [PolicyTemplateController::class, 'ckeupload'])->name('policyTemplate.ckeupload');
    });
    Route::resource('policyTemplate', 'PolicyTemplateController');

    # Vendor Management
    Route::group(['prefix' => 'vendor'], function () {
        Route::get('data', [VendorsController::class, 'data'])->name('vendor.data');
        Route::get('create', [VendorsController::class, 'create'])->name('vendor.create');
        Route::get('/', [VendorsController::class, 'index'])->name('vendor');
        Route::any('show/{id}', [VendorsController::class, 'show']);//->name('vendor.show');
        Route::post('confirm-delete', [VendorsController::class, 'getModalDelete'])->name('vendor.confirm-delete');
        Route::get('{productPlan}/delete', [VendorsController::class, 'destroy'])->name('vendor.delete');
        Route::post('store', [VendorsController::class, 'store']);//->name('vendor.store');
        Route::get('edit', [VendorsController::class, 'edit']);//->name('vendor.edit');
    });
    Route::resource('vendor', 'VendorsController');

    # PaymentVendor Management
    Route::group(['prefix' => 'paymentVendor'], function () {
        Route::get('paymentVendorsData', [PaymentVendorController::class, 'getAllVendors'])->name('paymentvendor.list');
        Route::get('data', [PaymentVendorController::class, 'data'])->name('paymentvendor.data');
        Route::get('create', [PaymentVendorController::class, 'create'])->name('paymentvendor.create');
        Route::get('/', [PaymentVendorController::class, 'index'])->name('paymentVendorIndex');
        Route::any('show/{id}', [PaymentVendorController::class, 'show']);//->name('branch.show');
        Route::get('{productPlan}/delete', [PaymentVendorController::class, 'destroy'])->name('branch.delete');
        Route::get('edit/{id}', [PaymentVendorController::class, 'edit']);//->name('paymentVendor.edit');
        Route::post('confirm-delete', [PaymentVendorController::class, 'getModalDelete'])->name('branch.confirm-delete');
        Route::post('update/{id}', [PaymentVendorController::class, 'update'])->name('paymentVendors.update');
        Route::post('store', [PaymentVendorController::class, 'store']);//->name('paymentVendor.store');
    });
    Route::resource('paymentVendor', 'PaymentVendorController');

    # Accounts Management
    Route::group(['prefix' => 'accounts'], function () {
        Route::get('data', [AccountsController::class, 'data'])->name('accounts.data');
        Route::get('/', [AccountsController::class, 'index'])->name('accounts');
        Route::get('edit/{id}', [AccountsController::class, 'edit']);//->name('accounts.edit');
        Route::post('confirm-delete', [AccountsController::class, 'getModalDelete'])->name('accounts.confirm-delete');
        Route::get('{accounts}/delete', [AccountsController::class, 'destroy'])->name('accounts.delete');
    });
    Route::resource('accounts', 'AccountsController');

    # Commission Management
    Route::group(['prefix' => 'commission'], function () {

        Route::get('data', [CommissionController::class, 'data'])->name('commission.data');
        Route::get('/', [CommissionController::class, 'index'])->name('commission');
        Route::get('edit/{id}', [CommissionController::class, 'edit']);//->name('commission.edit');
        Route::post('confirm-delete', [CommissionController::class, 'getModalDelete'])->name('commission.confirm-delete');
        Route::get('{commission}/delete', [CommissionController::class, 'destroy'])->name('commission.delete');
    });
    Route::resource('commission', 'CommissionController');

    Route::group(['prefix' => 'commissionReports'], function () {

        Route::get('reportsdata', [CommissionReportController::class, 'data'])->name('commissionReports.data');
        Route::get('/', [CommissionReportController::class, 'index'])->name('commissionReports');
        Route::get('commissionReport', [CommissionReportController::class, 'commissionReport'])->name('commissionReports');
        Route::get('data', [CommissionReportController::class, 'reportData'])->name('commissionReports.reportData');
        // Route::get('CommissionReportExport', [CommissionReportController::class, 'CommissionReportExport')->name('commissionReports.CommissionReportExport');

        // Route::get('edit/{id}', [CommissionReportController::class, 'edit')->name('commissionReports.edit');
        // Route::post('confirm-delete', [CommissionReportController::class, 'getModalDelete')->name('commissionReports.confirm-delete');
        // Route::get('{commissionReports}/delete', [CommissionReportController::class, 'destroy')->name('commissionReports.delete');
    });
    Route::resource('commissionReports', 'CommissionReportController');

    # Re-rating Management
    Route::group(['prefix' => 're-rating'], function () {

        Route::get('data', [ReratingController::class, 'data'])->name('re-rating.data');
        Route::get('/', [ReratingController::class, 'index'])->name('re-rating');
        Route::get('edit/{id}', [ReratingController::class, 'edit'])->name('re-rating.edit');
        Route::get('create', [ReratingController::class, 'create'])->name('re-rating.create');
        Route::any('store', [ReratingController::class, 'store'])->name('re-rating.store');
        Route::any('update/{id}', [ReratingController::class, 'update'])->name('re-rating.update');
        Route::post('confirm-delete', [AccountsController::class, 'getModalDelete'])->name('re-rating.confirm-delete');
        Route::get('{accounts}/delete', [AccountsController::class, 'destroy'])->name('re-rating.delete');
    });
    Route::resource('accounts', 'AccountsController');

    # Realpay Management
    Route::group(['prefix' => 'realpay'], function () {
        Route::get('realpay-new-request',  [RealPayController::class, 'newContractRequests'])->name('realpay-new-request');
        Route::any('/',  [RealPayController::class, 'index'])->name('realpay');
        Route::get('event-log',  [RealPayController::class, 'eventLogs'])->name('event-log');
        Route::get('getClient',  [RealPayController::class, 'getClient'])->name('getClient');
        Route::any('fetchClient',  [RealPayController::class, 'fetchClient'])->name('fetchClient');
        Route::any('fetchCustomer',  [RealPayController::class, 'fetchCustomer'])->name('fetchCustomer');
        Route::any('fetchCustomerDetails',  [RealPayController::class, 'fetchCustomerDetails'])->name('fetchCustomerDetails');
        Route::any('updateClient',  [RealPayController::class, 'updateClient'])->name('updateClient');
        Route::get('realpay-addClient',  [RealPayController::class, 'addClient'])->name('realpay-addClient');
        Route::get('editContract',  [RealPayController::class, 'editContract'])->name('editContract');
        Route::any('updateContract',  [RealPayController::class, 'updateContract'])->name('updateContract');
        Route::any('getLogData',  [RealPayController::class, 'getLogData'])->name('getLogData');
        Route::any('contractUpdateLogs',  [RealPayController::class, 'contractUpdateLogs'])->name('contractUpdateLogs');
        Route::any('editInstalment',  [RealPayController::class, 'editInstalment'])->name('editInstalment');
        Route::any('getInstalmentData',  [RealPayController::class, 'getInstalmentData'])->name('getInstalmentData');
        Route::any('updateInstalmentData',  [RealPayController::class, 'updateInstalmentData'])->name('updateInstalmentData');
        Route::any('logUpdateDataRealpay',  [RealPayController::class, 'logUpdateDataRealpay'])->name('logUpdateDataRealpay');
        Route::get('contractDetails',  [RealPayController::class, 'getContractDetails'])->name('contractDetails');
        Route::get('getCustomerCancelContract',  [RealPayController::class, 'getCustomerCancelContract'])->name('getCustomerCancelContract');
        Route::post('changePreminumFrequencyPolicy',  [RealPayController::class, 'changePreminumFrequencyPolicy'])->name('changePreminumFrequencyPolicy');
        Route::get('clientContractList/{id}',  [RealPayController::class, 'getClientContractList'])->name('clientContractList');
        Route::post('updateRealpayClientNumber',  [RealPayController::class, 'updateRealpayClientNumber'])->name('updateRealpayClientNumber');

        Route::get('realpayBankListView',  [RealPayController::class, 'realpayBankListView'])->name('realpayBankListView');
        Route::get('fetchRealpayBankList',  [RealPayController::class, 'fetchRealpayBankList'])->name('fetchRealpayBankList');
        Route::post('seletRealpayBank',  [RealPayController::class, 'seletRealpayBank'])->name('seletRealpayBank');
        Route::get('get-Realpay-Transactions-View',  [RealPayController::class, 'getRealpayTransactionsView'])->name('get-Realpay-Transactions-View');
        Route::post('fetchRealpayTransactions',  [RealPayController::class, 'fetchRealpayTransactions'])->name('fetchRealpayTransactions');

        Route::get('get_client_policy_number_view',  [RealPayController::class, 'getClientPolicyNumberView'])->name('get_client_policy_number_view');

        Route::post('createNewContract',  [RealPayController::class, 'cancelOldcreateNewContractView'])->name('createNewContract');
        Route::any('/cancelcreateNewContract',  [RealPayController::class, 'cancelOldcreateNewContract'])->name('cancelcreateNewContract');
        Route::any('fetchDetails',  [RealPayController::class, 'fetchRealpayContractDetails'])->name('fetchDetails');
        Route::get('/addInstalment/{id}',  [RealPayController::class, 'addInstalmentManually'])->name('addInstalment');
        Route::any('storeNewInstalment/{id}',  [RealPayController::class, 'storeNewInstalment'])->name('storeNewInstalment');
        Route::any('storeNewClient',  [RealPayController::class, 'storeNewClient'])->name('storeNewClient');
        // Route::any('updateStatus/{data}/{id}/{reason?}/{amount?}',  [RealPayController::class, 'updateInstalmentStatusRealpay'])->name('updateStatus');
        Route::any('updateStatus/{ref}/{Status}',  [RealPayController::class, 'updateInstalmentStatusRealpay'])->name('updateStatus');
        Route::any('updateStatusForInstantProduct/{ref}/{Status}',  [RealPayController::class, 'updateInstalmentStatusForInstantProduct'])->name('updateStatusForInstantProduct');

        Route::get('request-data',  [RealPayController::class, 'requestData'])->name('realpay.request-data');
        Route::get('event-data',  [RealPayController::class, 'eventData'])->name('realpay.event-data');
        Route::get('web-hook',  [RealPayController::class, 'webHookData'])->name('realpay.web-hook');
        Route::get('addContract',  [RealPayController::class, 'addContract'])->name('realpay.addContract');
        Route::get('realPayReportForWebHook',  [RealPayController::class, 'realPayReportForWebHook'])->name('realpay.realPayReportForWebHook');
        Route::get('cancel-data',  [RealPayController::class, 'cancelData'])->name('realpay.cancel-data');
        Route::get('realpay-cancel-request',  [RealPayController::class, 'cancelContractRequests'])->name('realpay-cancel-request');
        Route::get('cancelRealpayContract/{id}', [AccountsController::class, 'cancelRealpayContract'])->name('cancelRealpayContract');
        Route::any('create-client/{id}',  [RealPayController::class, 'createClient'])->name('create-client');
        Route::any('add-contract/{id}',  [RealPayController::class, 'addClientContract'])->name('add-contract');
        Route::any('addContractManually',  [RealPayController::class, 'addContractManually'])->name('addContractManually');
        Route::any('view-installments/{id}',  [RealPayController::class, 'viewInstallments'])->name('view-installments');
        Route::any('view-error/{id}',  [RealPayController::class, 'viewError'])->name('view-error');
        Route::any('getContractInstallments/{id}',  [RealPayController::class, 'getContractInstallments'])->name('getContractInstallments');
        Route::any('getUpdatedContractInstallments/{id}',  [RealPayController::class, 'getUpdatedContractInstallments'])->name('getUpdatedContractInstallments');
        Route::any('updateInstallmentInfo',  [RealPayController::class, 'updateInstallment'])->name('updateInstallmentInfo');
        Route::any('getCustomer',  [RealPayController::class, 'getCustomer'])->name('getCustomer');
        Route::any('addReratingPaymentRealpay',  [RealPayController::class, 'addReratingPaymentRealpay'])->name('addReratingPaymentRealpay');
        Route::post('updateClientContractNumber',  [RealPayController::class, 'updateClientContractNumber'])->name('updateClientContractNumber');
        Route::any('getClientContractDetails',  [RealPayController::class, 'getClientContractDetails'])->name('getClientContractDetails');
        Route::any('getContractInfo',  [RealPayController::class, 'getContractInfo'])->name('getContractInfo');
        Route::any('cancelContract/{id}',  [RealPayController::class, 'cancelClientContract'])->name('cancelContract');
        Route::any('cancelContractForInsProd/{id}',  [RealPayController::class, 'cancelClientContractInstantProduct'])->name('cancelContractForInsProd');

    });
    Route::resource('/realpay', 'RealPayController');


    # Branches Management
    Route::group(['prefix' => 'branch'], function () {
        Route::get('data', [BranchesController::class, 'data'])->name('branch.data');
        Route::get('create', [BranchesController::class, 'create'])->name('branch.create');
        Route::get('/', [BranchesController::class, 'index'])->name('branch');
        Route::any('show/{id}', [BranchesController::class, 'show']);//->name('branch.show');
        Route::post('confirm-delete', [BranchesController::class, 'getModalDelete']);//->name('branch.confirm-delete');
        Route::get('{productPlan}/delete', [BranchesController::class, 'destroy']);//->name('branch.delete');
        Route::post('store', [BranchesController::class, 'store']);//->name('branch.store');
        Route::get('edit', [BranchesController::class, 'edit']);//->name('branch.edit');
    });
    Route::resource('branch', 'BranchesController');

    # Stores Management
    Route::group(['prefix' => 'store'], function () {
        Route::get('/', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'index'])
		->name('store');
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'data'])
		->name('store.data');

        Route::get('/bundle/edit', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'bundleEdit'])->name('store.bundle.edit');
        Route::post('/bundle/update', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'bundleUpdate'])->name('store.bundle.update');

        Route::get('create', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'create'])->name('store.create');
        Route::post('store', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'store'])->name('store.store');
        Route::get('delete/{id}', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'destroy']);

        Route::get('show/{store}', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'show'])->name('stores.show');

        Route::post('confirm-delete', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'getModalDelete'])->name('store.confirm-delete');
        // Route::get('{productPlan}/delete', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'destroy')->name('store.delete');

        Route::any('edit/{id}', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'edit'])->name('store.edit');
        Route::any('/updateStore/{id}', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'updateStore'])->name('store.updateStore');
        Route::get('addPartner', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'addstoresPartner'])->name('store.addPartner');
        Route::any('storePartner', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'storePartner'])->name('store.storePartner');
    });

    # RepairCenter Management
    Route::group(['prefix' => 'repairCenters'], function () {
        Route::get('/', [RepairController::class, 'index'])->name('repairCenters');
        Route::get('create', [RepairController::class, 'create'])->name('repairCenters.create');
        Route::any('edit/{id}', [RepairController::class, 'edit']);//->name('repairCenters.edit');
        Route::post('Centers', [RepairController::class, 'store']);//->name('repairCenters.store');
        Route::get('data', [RepairController::class, 'data'])->name('repairCenters.data');
        Route::any('/updateStore/{id}', [RepairController::class, 'updateStore'])->name('repairCenters.updateStore');
    });
    Route::resource('repairCenters', 'RepairController');

// Renew Policy
    Route::group(['prefix' => 'renewalPolicy'], function () {
        Route::get('/',[\AlphaDirect\Http\Controllers\Admin\RenewpolicyController::class,'index'])->name('renewalPolicy');
        Route::get('data',[\AlphaDirect\Http\Controllers\Admin\RenewpolicyController::class,'data'])->name('renewalPolicy.data');
        Route::get('/export',[\AlphaDirect\Http\Controllers\Admin\RenewpolicyController::class,'export'])->name('renewal.export');
    });
    Route::resource('renewalPolicy', 'RenewpolicyController');

    Route::group(['prefix' => 'reconsilation'], function () {
        Route::get('/dpoExport',[\AlphaDirect\Http\Controllers\Admin\ReconsilationController::class,'dpoExport'])->name('reconsilation.dpoExport');
        Route::get('/realpayExport',[\AlphaDirect\Http\Controllers\Admin\ReconsilationController::class,'realpayExport'])->name('reconsilation.realpayExport');
        Route::get('/vcsExport',[\AlphaDirect\Http\Controllers\Admin\ReconsilationController::class,'vcsExport'])->name('reconsilation.vcsExport');
    });
    Route::resource('reconsilation', 'ReconsilationController');

    # kycFields
    Route::group(['prefix' => 'KycFields'], function () {
        Route::get('/', [KycFieldsController::class,'index'])->name('KycFields');
        Route::get('create', [KycFieldsController::class,'create'])->name('KycFields.create');
        Route::any('edit/{id}', [KycFieldsController::class,'edit']);//->name('KycFields.edit');
        Route::post('store', [KycFieldsController::class,'store']);//->name('KycFields.store');
        Route::get('data', [KycFieldsController::class,'data'])->name('KycFields.data');
        Route::any('/update/{id}', [KycFieldsController::class,'update']);//->name('KycFields.update');
        Route::post('confirm-delete', [KycFieldsController::class,'getModalDelete'])->name('KycFields.confirm-delete');
        Route::get('{id}/delete', [KycFieldsController::class,'destroy'])->name('KycFields.delete');
    });
    Route::resource('KycFields', 'KycFieldsController');

    # kycCompliance
    Route::group(['prefix' => 'KycCompliance'], function () {
        Route::get('/', [KycComplianceController::class,'index'])->name('kycCompliance');
        Route::get('create', [KycComplianceController::class,'create'])->name('KycCompliance.create');
        Route::any('edit/{id}', [KycComplianceController::class,'edit']);//->name('kycCompliance.edit');
        Route::post('store', [KycComplianceController::class,'store']);//->name('kycCompliance.store');
        Route::get('data', [KycComplianceController::class,'data'])->name('kycCompliance.data');
        Route::any('/update/{id}', [KycComplianceController::class,'update']);//->name('kycCompliance.update');
        Route::post('confirm-delete', [KycComplianceController::class,'getModalDelete'])->name('kycCompliance.confirm-delete');
        Route::get('{id}/delete', [KycComplianceController::class,'destroy'])->name('kycCompliance.delete');

        Route::post('enableMati', [KycComplianceController::class,'enableMati'])->name('kycCompliance.enableMati');
    });
    Route::resource('kycCompliance', 'KycComplianceController');

    # Billin Date Management
    Route::group(['prefix' => 'billing'], function () {
        Route::get('data', [BillingController::class, 'data'])->name('billing.data');
        Route::get('/create', [BillingController::class, 'create'])->name('billing.create');
        Route::get('/', [BillingController::class, 'index'])->name('billing');
        Route::post('/store', [BillingController::class, 'store'])->name('billing.store');
        Route::post('/update', [BillingController::class, 'update'])->name('billing.update');
        Route::any('/edit/{id}', [BillingController::class, 'edit'])->name('billing.edit');
        Route::any('/getProductPlans', [BillingController::class, 'getProductPlans'])->name('billing.getProductPlans');
    });
    Route::resource('branch', 'BranchesController');

    # Sms Logs Controller
    Route::prefix('smsLogs')->group(function () {
        Route::get('/', [SmsLogsController::class, 'index'])->name('smsLogs.index');
        Route::get('data', [SmsLogsController::class, 'data'])->name('smsLogs.data');
        Route::post('store', [SmsLogsController::class, 'store']);//->name('smsLogs.store');
    });
    Route::resource('smsLogs', 'SmsLogsController');

    Route::group(['prefix' => 'review'], function () {
        Route::get('/', [ReviewController::class, 'index'])->name('review.index');
        Route::get('/data', [ReviewController::class, 'data'])->name('review.data');
        Route::get('/createQuestion', [ReviewController::class, 'createQuestion'])->name('review.question.create');
        Route::get('/edit/{id}', [ReviewController::class, 'editQuestion'])->name('review.question.edit');
        Route::post('confirm-delete', [ReviewController::class, 'getModalDeleteQuestion'])->name('review.question.confirm-delete');
        Route::get('/delete/{id}', [ReviewController::class, 'destroy'])->name('review.question.delete');
        Route::post('/storeQuestion', [ReviewController::class, 'storeQuestion'])->name('review.question.store');
        Route::post('/updateQuestion/{id}', [ReviewController::class, 'updateQuestion'])->name('review.question.updateQuestion');
        Route::get('/createAnswer', [ReviewController::class, 'createAnswer'])->name('review.answer.create');
        Route::post('/storeAnswer', [ReviewController::class, 'storeAnswer'])->name('review.answer.store');
        // Route::get('/edit/{id}', 'reviewQuestionsController@editAnswer')->name('review.answers.edit');

    });
    Route::resource('review', 'ReviewController');
});

Route::group(['prefix' => 'customers', 'middleware' => 'auth'], function () {
    Route::get('/setPassword', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'setPasswordView'])->name('customerSetPassword');
    Route::get('/otpVerification', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'showOtp'])->name('otpVerication');
    Route::get('/dashboard', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'index'])->name('MyDashboard');
    Route::get('/portalclaims', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'ViewCliams'])->name('MyClaims');
    Route::get('/portalProfile', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'myProfileView'])->name('MyProfile');
    Route::get('/portalpolices', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'ViewPolicies'])->name('MyPolicies');
    Route::get('/portalinstallments', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'ViewInstallemts'])->name('MyInstallments');
    Route::get('/portaltransactions', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'ViewTransactions'])->name('MyTransactions');
    Route::get('/allCustomerPolicies', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'getUserPolicies'])->name('customerPolicies');
    Route::get('/policyDetail/{id}', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'viewPolicyDetail'])->name('customerPolicyDetail');
    Route::post('/customerSetPassword', [AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'userSetNewPassword'])->name('customer-setNewPassword');
});

Route::group(['prefix' => 'agents', 'middleware' => 'auth'], function () {
    Route::get('/dashboard', [AgentController::class, 'index'])->name('agent-dashboard');
    Route::get('/policies', [AgentController::class, 'getAllPolicies'])->name('agent-policies');
    Route::get('/addPolicy', [AgentController::class, 'addView'])->name('agent-addPolicy');
    Route::get('/viewAllCustomers', [AgentController::class, 'viewAllCustomers'])->name('agent-customersView');
    Route::get('/viewCustomer/{id}', [AgentController::class, 'viewCustomerDetails'])->name('agent-viewCustomerDetails');
    Route::get('/CreatePolicies', [AgentController::class, 'createPolicy'])->name('createPolicy');
    Route::get('/makePolicy', [AgentController::class, 'makePolicy'])->name('makePolicy');
    Route::get('/UBI', [AgentController::class, 'UBI'])->name('agent-UBI');
    Route::get('/agent-edit/{id}', [AgentController::class, 'editPolicyView'])->name('agent-editPolicy');
    Route::get('data', [AgentController::class, 'data'])->name('agent.data');
    Route::get('logins', [AgentController::class, 'agentLogins'])->name('agents.logins');
    Route::get('login-data', [AgentController::class, 'agentLoginData'])->name('agents.loginData');

    /*CLAIMS AGENT ROUTES*/
    Route::get('/Claims', [ClaimsAgentController::class, 'viewClaims'])->name('agent-allClaims');
    Route::get('/allAgentClaims', [ClaimsAgentController::class, 'getAllAgentClaims'])->name('allAgentClaims');
    Route::get('/processClaim/{id}', [ClaimsAgentController::class, 'processClaim'])->name('processClaim');
    Route::get('/agentMakeClaim/{licensePlate}', [ClaimsAgentController::class, 'createClaim'])->name('intiateClaim');
    Route::post('/authorizeClaim', [ClaimsAgentController::class, 'authorizeClaim'])->name('authorizeClaim');
    Route::post('/handleClaim', [ClaimsAgentController::class, 'handleClaim'])->name('ClaimForm');

    Route::post('/uploadIncidentFront', [UploadController::class, 'uploadIncidentPhotoFront'])->name('glassIncidentPhotoFront');
    Route::post('/uploadIncidentBack', [UploadController::class, 'uploadIncidentPhotoBack'])->name('glassIncidentPhotoBack');
    Route::post('/uploadIncidentRight', [UploadController::class, 'uploadIncidentPhotoRight'])->name('glassIncidentPhotoRight');
    Route::post('/uploadIncidentLeft', [UploadController::class, 'uploadIncidentPhotoLeft'])->name('glassIncidentPhotoLeft');

    Route::get('/sendQuote/{licensePlate}/supplierEmail/{supplierEmail}/{claimId}', [ClaimsAgentController::class, 'sendQuote'])->name('sendQuote');
    Route::get('/sendToAll/{licensePlate}', [ClaimsAgentController::class, 'sendAllSuppliers'])->name('sendToAll');

    //Route::get('/acceptQuote/{cellphone}/supplierEmail/{supplierEmail}', [ClaimsAgentController::class,'acceptQuote')->name('acceptQuote');

    Route::post('/scanDocuments', [AgentController::class, 'documentOCR'])->name('documentScanner');
    Route::post('/scratch', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class,'agentActivatePolicy'])->name('agent-savePolicy');

    Route::post('/activateUserPolicy', [AdminController::class,'activateUserPolicy'])->name('agent-activateUserPolicy');

    Route::get('/alphaPDF', function () {

        $pdf = PDF::loadView('PDF.AlphaPDF');
        return $pdf->download('AlphaDirect-Policy.pdf');
    })->name('generatePDF');

    Route::get('/allPolicies', [AgentController::class, 'getAllPolicies'])->name('agent-allPolicies');
    Route::get('/viewPolicy/{id}', [AgentController::class, 'viewPolicyDetails'])->name('agent-viewPolicyDetails');
    Route::get('displayTshologoAgentLogins', [AgentController::class, 'displayTshologoAgentLogins'])->name('displayTshologoAgentLogins');
});

#AgentProfiles,Tumisang Mogotsi

Route::group(['prefix' => 'bitrix_agent', 'middleware' => 'auth'], function () {
    Route::get('/index', [BitrixAgentController::class, 'index'])->name('bitrixAgent.index');
    Route::get('/create', [BitrixAgentController::class, 'create'])->name('bitrixAgent.create');
    Route::post('/updateBitrixAgent', [BitrixAgentController::class, 'updateBitrixAgent'])->name('bitrixAgent.updateBitrixAgent');
    Route::get('/data', [BitrixAgentController::class, 'data'])->name('bitrixAgent.data');
    Route::post('/store', [BitrixAgentController::class, 'store'])->name('bitrixAgent.store');
});
#Risks, Coverages,Tumisang Mogotsi
Route::group(['prefix' => 'risks', 'middleware' => 'auth'], function () {
    Route::get('data', [RiskTypeController::class, 'data'])->name('risk.data');
    Route::post('/save', [RiskTypeController::class, 'store'])->name('risk.save');
    Route::get('/show', [RiskTypeController::class, 'index'])->name('risk.display');
    Route::get('/create', [RiskTypeController::class, 'create'])->name('risk.create');
    Route::get('/edit/{id}', [RiskTypeController::class, 'edit'])->name('risk.edit');
    Route::get('/delete/{id}', [RiskTypeController::class, 'destroy'])->name('risk.delete');
    Route::post('/update/{id}', [RiskTypeController::class, 'update'])->name('risk.update');
    Route::post('confirm-delete', [RiskTypeController::class, 'getModalDelete'])->name('risk.confirm-delete');
    // Route::post('/edit')->name('');
});
#Coverages,Tumisang Mogotsi
Route::group(['prefix' => 'coverage', 'middleware' => 'auth'], function () {
    Route::get('data', [CoverageController::class, 'data'])->name('coverage.data');
    Route::post('/getRiskLimit', [CoverageController::class, 'getRiskLimit'])->name('risklimit.data');
    Route::post('/save', [CoverageController::class, 'store'])->name('coverage.save');
    Route::get('/show', [CoverageController::class, 'index'])->name('coverage.display');
    Route::get('/create', [CoverageController::class, 'create'])->name('coverage.create');
    Route::get('/edit/{id}', [CoverageController::class, 'edit']);//->name('coverage.edit');
    Route::get('/delete/{id}', [CoverageController::class, 'destroy'])->name('coverage.delete');
    Route::post('/update/{id}', [CoverageController::class, 'update'])->name('coverage.update');
    Route::post('confirm-delete', [CoverageController::class, 'getModalDelete'])->name('coverage.confirm-delete');
    Route::get('/checkRiskLimit', [CoverageController::class, 'checkRiskLimit'])->name('coverage.checkRiskLimit');
});

#Policy Questions,Tumisang Mogotsi policyQuestion

Route::group(['prefix' => 'policyQuestion', 'middleware' => 'auth'], function () {
    Route::get('data', [PolicyQuestionsController::class, 'data'])->name('policy.question.data');
    Route::get('/show', [PolicyQuestionsController::class, 'index'])->name('policy.question.display');
    Route::get('/create', [PolicyQuestionsController::class, 'create'])->name('policy.question.create');
    Route::get('/edit/{id}', [PolicyQuestionsController::class, 'edit'])->name('policy.question.edit');
    Route::post('/update/{id}', [PolicyQuestionsController::class, 'update'])->name('policy.question.update');
    Route::post('/save', [PolicyQuestionsController::class, 'store'])->name('policy.question.save');
    Route::post('confirm-delete', [PolicyQuestionsController::class, 'getModalDelete'])->name('policy.question.confirm-delete');
    Route::get('/delete/{id}', [PolicyQuestionsController::class, 'destroy'])->name('policy.question.delete');
    Route::get('{id}/factorValueDelete', [PolicyQuestionsController::class, 'factorValueDelete'])->name('policy.question.factorValueDelete');
});

#Claims Question, Tumisang Mogotsi claimsQuestion
Route::group(['prefix' => 'claimsQuestion', 'middleware' => 'auth'], function () {
    Route::get('data', [ClaimQuestionsController::class, 'data'])->name('claim.question.data');
    Route::get('/show', [ClaimQuestionsController::class, 'index'])->name('claim.question.display');
    Route::get('/create', [ClaimQuestionsController::class, 'create'])->name('claim.question.create');
    Route::get('/edit/{id}', [ClaimQuestionsController::class, 'edit'])->name('claim.question.edit');
    Route::post('/update/{id}', [ClaimQuestionsController::class, 'update'])->name('claim.question.update');
    Route::post('/save', [ClaimQuestionsController::class, 'store'])->name('claim.question.save');
    Route::post('confirm-delete', [ClaimQuestionsController::class, 'getModalDelete'])->name('claim.question.confirm-delete');
    Route::get('/delete/{id}', [ClaimQuestionsController::class, 'destroy'])->name('claim.question.delete');
    Route::get('{id}/factorValueDelete', [ClaimQuestionsController::class, 'factorValueDelete'])->name('claim.question.factorValueDelete');
});

#Leads,Tumisang Mogotsi leads
Route::group(['prefix' => 'leads', 'middleware' => 'auth'], function () {
    Route::get('data', [LeadsController::class, 'data'])->name('lead.data');
    Route::get('/', [LeadsController::class, 'index'])->name('lead.index');
    Route::get('/edit/{id}', [LeadsController::class, 'edit'])->name('lead.edit');
    Route::get('/create', [LeadsController::class, 'create'])->name('lead.create');
    Route::post('/save', [LeadsController::class, 'store'])->name('lead.save');
    Route::get('/delete/{id}', [LeadsController::class, 'destroy'])->name('lead.delete');
    Route::post('confirm-delete', [LeadsController::class, 'getModalDelete'])->name('lead.confirm-delete');
    Route::post('transDataStore', [LeadsController::class, 'dpoTransDataStore'])->name('lead.transDataStore');
    Route::post('realpayTransDataStore', [LeadsController::class, 'realpayTransDataStore'])->name('lead.realpayTransDataStore');
    Route::post('vcsTransDataStore', [LeadsController::class, 'vcsTransDataStore'])->name('lead.vcsTransDataStore');

});

#qoutes,Tumisang Mogotsi qoutes
Route::group(['prefix' => 'qoutes', 'middleware' => 'auth'], function () {
    Route::get('export', [QuoteController::class, 'export']);
    //Route::post('/download/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'downloadQuote'])->name('quote.download');
    Route::get('data', [QuoteController::class, 'data'])->name('quote.data');
    Route::any('/reject/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'rejectQuote'])->name('quote.reject');
    Route::any('/perDayPremium/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'perDayPremium'])->name('quote.perDayPremium');
    //Route::any('acceptReratedPremium/{quoteNumber}', 'Admin\QuoteController@acceptNewRate')->name('quote.acceptReratedPremium');
    Route::any('/calculatePerDayPremium', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'calculatePerDayPremium'])->name('quote.calculatePerDayPremium');
    Route::any('motorCompExport', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'motorcompexport'])->name('quote.motorcompexport');
    Route::get('/', [QuoteController::class, 'index'])->name('quote.index');
    Route::get('/edit/{id}', [QuoteController::class, 'edit'])->name('quote.edit');
    Route::post('/save', [QuoteController::class, 'store'])->name('quote.save');
    Route::get('/delete/{id}', [QuoteController::class, 'destroy'])->name('quote.delete');
    Route::post('confirm-delete', [QuoteController::class, 'getModalDelete'])->name('quote.confirm-delete');
    Route::any('/download/{id}/{download}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'downloadQuote'])->name('quote.download');
    Route::any('/updatePremium/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'updatePremium'])->name('quote.updatePremium');
    Route::any('/addDiscountSurcharge/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'addDiscountSurcharge'])->name('quote.addDiscountSurcharge');
    //Route::any('/rerate_billing/{id}', 'Admin\QuoteController@rerateBilling')->name('quote.rerate_billing');
    Route::any('/updatePremiumAjax', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'updatePremiumAjax'])->name('quote.updatePremiumAjax');
    Route::any('/updatePremiumRate', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'updatePremiumRate'])->name('quote.updatePremiumRate');
    Route::any('/viewHistory/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'viewUpdateHistory'])->name('quote.viewHistory');
    Route::any('/historyData/{id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'historyData'])->name('quote.historyData');
    Route::get('/historyDataUnissueRate/{ratings_id}', [AlphaDirect\Http\Controllers\Admin\QuoteController::class,'historyDataUnissueRate'])->name('quote.historyDataUnissueRate');
    Route::any('/rerate/{id}', [QuoteController::class, 'rerate'])->name('quote.rerate');
    Route::any('/getUpdatedPremium', [QuoteController::class, 'getUpdatedPremium'])->name('quote.getUpdatedPremium');
    Route::any('/reratePolicyPremium', [QuoteController::class, 'reratePolicyPremium'])->name('quote.reratePolicyPremium');
});

#OTP Tumisang Mogotsi 23/08/2019 otp
Route::group(['prefix' => 'otp', 'middleware' => 'auth'], function () {
    Route::get('/sendOTP/{phoneNumber}', [SmsMessaging::class,'sendOTP'])->name('otp.send');
    Route::get('/autheticateOTP/{phoneNumber}/{otpCode}', [SmsMessaging::class,'authenticateOTP'])->name('otp.authenticate');
});

#Organization Document Tumisang Mogotsi 24/08/2019 organizationdocument
Route::group(['prefix' => 'organizationdocument', 'middleware' => 'auth'], function () {
    Route::get('data', [OrganizationDocumentController::class, 'data'])->name('organizationdocument.data');
    Route::get('/create', [OrganizationDocumentController::class, 'create'])->name('organizationdocument.create');
    Route::get('/display', [OrganizationDocumentController::class, 'index'])->name('organizationdocument.index');
    Route::get('/edit/{id}', [OrganizationDocumentController::class, 'edit'])->name('organizationdocument.edit');
    Route::get('/delete/{id}', [OrganizationDocumentController::class, 'destroy'])->name('organizationdocument.delete');
    Route::post('/save', [OrganizationDocumentController::class, 'store'])->name('organizationdocument.store');
    Route::post('/update/{id}', [OrganizationDocumentController::class, 'update'])->name('organizationdocument.update');
    Route::post('confirm-delete', [OrganizationDocumentController::class, 'getModalDelete'])->name('organizationdocument.confirm-delete');
});

Route::namespace('Admin')->prefix('admin/customer-feedback')->name('customer-feedback.')->group(function () {
    Route::prefix('options')->name('options.')->group(function () {
        Route::get('table-data', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackOptionController::class,'tableData'])->name('table-data');
    });
    Route::resource('options', 'CustomerFeedbackOptionController');
});

Route::namespace('Admin')->prefix('admin/customer-feedback')->name('customer-feedback.')->group(function () {
    Route::prefix('suboptions')->name('suboptions.')->group(function () {
        Route::get('table-data', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackSubOptionController::class,'tableData'])->name('table-data');
        Route::get('data', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackSubOptionController::class,'data'])->name('data');
    });
    Route::resource('suboptions', 'CustomerFeedbackSubOptionController');
});

#Transaction re-do merge deleted Tumisang Mogotsi 27/08/2019 transaction
Route::group(['prefix' => 'transaction', 'middleware' => 'auth'], function () {
    Route::get('/index', [TransactionController::class, 'index'])->name('transaction.index');
    Route::get('data', [TransactionController::class, 'data'])->name('transaction.data');
});

#Transaction re-do merge deleted Tumisang Mogotsi 27/08/2019 transaction
Route::group(['prefix' => 'reconciliation', 'middleware' => 'auth'], function () {
    Route::get('/index', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::get('data', [ReconciliationController::class, 'data'])->name('reconciliation.data');
    Route::post('uploadCsv', [ReconciliationController::class, 'uploadCsv'])->name('ReconciliationController.uploadCsv');
});

# KABO SEDIRWA

Route::post('/rpGetBranches', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class,'getRealPayBranches'])->name('branches')->middleware('auth');

#PAYGATE ROUTES vcs

Route::group(['prefix' => 'vcs', 'middleware' => 'auth'], function () {
    Route::any('deleteTransactions', [PaymentController::class, 'deleteTransaction'])->name('deleteTransactions');
});

#END OF KABO SEDIRWA

Route::get('/loadPaymentForm/{id}', [\AlphaDirect\Http\Controllers\USSD\ussd::class,'loadPaymentForm'])->name('loadPaymentForm');

Route::post('/processPayment/{id}', [\AlphaDirect\Http\Controllers\USSD\ussd::class,'processPayment'])->name('processPayment');

Route::get('/getAllVendorsList', [\AlphaDirect\Http\Controllers\USSD\ussd::class,'getAllVendorsList'])->name('getAllVendorsList')->middleware('auth');

#Tumisang Mogotsi  3/10/2019
Route::group(['middleware' => ['auth']], function () {
    Route::post('sendPaymentUrlGraphite', [SmsMessaging::class, 'sendPaymentUrlGraphite'])->name('sendPaymentUrlGraphite');
    Route::post('resendPaymentUrlGraphite', [SmsMessaging::class, 'resendPaymentUrlGraphite'])->name('resendPaymentUrlGraphite');
    Route::get('smsPage', [SmsMessaging::class, 'index'])->name('smsPage');
    Route::post('sendBulkSms', [SmsMessaging::class, 'sendBulkSms'])->name('sendBulkSms');
    Route::post('testInfobibSMS', [SmsMessaging::class, 'testInfobibSMS'])->name('testInfobibSMS');
    Route::get('testPhpMail', [SmsMessaging::class, 'testPhpMail'])->name('sendMtestPhpMailail');
    Route::get('testMail', [SmsMessaging::class, 'testMail']);
    Route::get('genericServicePage', [SmsMessaging::class, 'genericServicePage'])->name('genericServicePage');
    Route::post('sendGenericSMS', [SmsMessaging::class, 'sendGenericSMS'])->name('sendGenericSMS');

    Route::get('getURL', [SmsMessaging::class, 'getURL']);

    Route::post('getModalEdit', [PolicyController::class,'getModalEdit'])->name('getModalEdit');
});

#public route
Route::get('graphitePaymentForm/{id}', [PolicyController::class, 'graphitePaymentForm']);
Route::post('processGraphitePayment/{id}', [PolicyController::class, 'processGraphitePayment'])->name('processGraphitePayment');
// Route::post('schedulePayments', [PolicyController::class, 'schedulePayments'])->name('schedulePayments');

Route::group(['prefix' => 'paymentUrl', 'middleware' => 'auth'], function () {
    Route::post('store', [PaymentUrlsController::class, 'store'])->name('paymentUrl.save');
    Route::get('delete/{id}', [PaymentUrlsController::class, 'delete'])->name('paymentUrl.delete');
});

#Tumisang Mogotsi 12/09/2019
Route::group(['middleware' => ['auth']], function () {
    Route::get('/checkActivationCodeData', [ActivationController::class, 'checkActivationCodeData'])->name('activation.checkActivationCodeData');
    Route::post('/updateFirstTimeAccount/{id}', [UserController::class, 'updateFirstTimeAccount'])->name('updateFirstTimeAccount');
    Route::post('/updateProfilePicture/{id}', [UserController::class, 'updateProfilePicture'])->name('updateProfilePicture');
    Route::post('/updatePassword/{id}', [UserController::class, 'updatePassword'])->name('updatePassword');

    Route::get('user/userSales', [UserController::class, 'userSales'])->name('user.userSales');
    Route::get('userPolicySalesData', [UserController::class, 'userPolicySalesData'])->name('user.userPolicySalesData');
});

Route::any('getTransactionByDateRange', [PaymentController::class, 'GetTransactionByDateRange'])->name('vcsTransactiondata');
Route::any('GetVCSTransLog', [PaymentController::class, 'GetVCSTransLog'])->name('GetVCSTransLog');
Route::get('saveCSVToDB', [\AlphaDirect\Http\Controllers\CSVController::class,'saveCSVToDB'])->name('saveCSVToDB');
Route::get('transFormview', [\AlphaDirect\Http\Controllers\LeadsController::class,'transFormview'])->name('transFormview');
Route::get('getTransFormview', [\AlphaDirect\Http\Controllers\LeadsController::class,'getTransFormview'])->name('getTransFormview');

Route::post('transFormview', [\AlphaDirect\Http\Controllers\LeadsController::class,'transFormview']);//->name('transFormview');
Route::any('/frontendpay/reset-password-first-time-user',[\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class,'resetPasswordFirstTimeUser'])->name('resetPasswordFirstTimeUser');
Route::get('admin/policy/get-schedule-transaction-logs-data-table/{id}', [AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'scheduleTransactionsData'])->name('admin.policy.scheduleTransactionsData');

// QA finding C3: this route posts a credit note straight to the ledger and was
// previously ungated (standalone, no middleware). It now requires auth + a role.
// Credit notes are an admin/FINANCE function, so the allow-list includes the
// finance/accounts roles that actually issue them (confirmed against the roles
// table) — otherwise Finance/Accounts, the real issuers, would be locked out.
Route::any('creditNoteStatement/{id}/{ledger}', [PolicyController::class, 'creditNoteStatement'])->middleware(['auth', 'role:Super Admin|Manager|Admin|developer|Finance|Accounts senior associate|Accounts executive to add cash payments|Debtors|Payables'])->name('admin.policy.creditNoteStatement');
Route::any('schedule', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'schedule'])->name('schedule');

Route::get('customerCashBack',[\AlphaDirect\Http\Controllers\Admin\PolicyController::class,'cashbackEvent'])->name('customerCashBack');

Route::get('transFormviewRealpay', [\AlphaDirect\Http\Controllers\LeadsController::class,'transFormviewRealpay'])->name('transFormviewRealpay');
Route::post('transFormviewRealpay', [\AlphaDirect\Http\Controllers\LeadsController::class,'transFormviewRealpay']);//->name('transFormviewRealpay');
Route::get('getTransFormviewRealpay', [\AlphaDirect\Http\Controllers\LeadsController::class,'getTransFormviewRealpay'])->name('getTransFormviewRealpay');

Route::get('transFormviewVcs', [\AlphaDirect\Http\Controllers\LeadsController::class,'transFormviewVcs'])->name('transFormviewVcs');
Route::post('storeTransFormVcs', [\AlphaDirect\Http\Controllers\LeadsController::class,'storeTransFormVcs'])->name('storeTransFormVcs');
Route::get('getTransFormviewVcs', [\AlphaDirect\Http\Controllers\LeadsController::class,'getTransFormviewVcs'])->name('getTransFormviewVcs');

#otp

Route::get('create-otp', [\AlphaDirect\Http\Controllers\Admin\OtpTempController::class, 'index'])->name('createOTP');
Route::post('save-otp', [\AlphaDirect\Http\Controllers\Admin\OtpTempController::class, 'saveOTP'])->name('saveOTP');

Route::get('admin/deactivepolicyactivate', [\AlphaDirect\Http\Controllers\Admin\OtpTempController::class, 'deactivepolicyactivate'])->name('admin.deactivepolicyactivate');
Route::post('admin/deactivepolicyactivatedata', [\AlphaDirect\Http\Controllers\Admin\OtpTempController::class, 'deactivepolicyactivatedata'])->name('admin.deactivepolicyactivatedata');
Route::post('admin/deactivepolicyupdate', [\AlphaDirect\Http\Controllers\Admin\OtpTempController::class, 'deactivepolicyupdate'])->name('admin.deactivepolicyupdate');
#flutterwave
Route::post('/flutter_pay', [\AlphaDirect\Http\Controllers\FlutterwavePaymentController::class, 'pay'])->name('flutterpay');
Route::any('/flutter_process', [\AlphaDirect\Http\Controllers\FlutterwavePaymentController::class, 'process'])->name('flutterprocess');
// Second (web) path to dpoPaymentRecord — must be gated too, or the api.php fix
// is moot. Requires an authenticated admin session (leaks payment request/
// response JSON by policy number otherwise).
Route::any('admin/policy/dpoPaymentRecord/{id}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'dpoPaymentRecord'])->middleware('auth')->name('admin.policy.dpoPaymentRecord');

Route::get('/admin/penddingReccuring', [\AlphaDirect\Http\Controllers\LeadsController::class,'penddingReccuring'])->name('admin.penddingReccuring');
Route::post('/admin/penddingReccuringUploadCSV', [\AlphaDirect\Http\Controllers\LeadsController::class,'penddingReccuringUploadCSV'])->name('admin.penddingReccuringUploadCSV');
Route::get('getPendingRecurringData', [\AlphaDirect\Http\Controllers\LeadsController::class,'getPendingRecurringData'])->name('getPendingRecurringData');
Route::post('GenerateReccuringToken', [\AlphaDirect\Http\Controllers\LeadsController::class,'GenerateReccuringToken'])->name('GenerateReccuringToken');
Route::post('ChargeReccuringToken', [\AlphaDirect\Http\Controllers\LeadsController::class,'ChargeReccuringToken'])->name('ChargeReccuringToken');
Route::get('/admin/pendingRecurringdatadelete', [\AlphaDirect\Http\Controllers\LeadsController::class,'pendingRecurringdatadelete'])->name('admin.pendingRecurringdatadelete');
#ngenius
Route::post('NgeniusRecurringGetdata', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusRecurringGetdata']);
Route::post('NgeniusRecurringDeletedata', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusRecurringDeletedata3']);
Route::get('/admin/cron/create', [\AlphaDirect\Http\Controllers\CronController::class, 'create'])->name('admin.cron.create');
Route::get('/admin/cron', [\AlphaDirect\Http\Controllers\CronController::class, 'index'])->name('admin.cron.index');
Route::get('/admin/cron/data', [\AlphaDirect\Http\Controllers\CronController::class, 'crondata'])->name('admin.cron.data');
Route::post('/admin/cron/store', [\AlphaDirect\Http\Controllers\CronController::class, 'store'])->name('admin.cron.store');
Route::get('/admin/cron/edit/{id}', [\AlphaDirect\Http\Controllers\CronController::class, 'edit'])->name('admin.cron.edit');
Route::post('/admin/cron/confirm-delete', [\AlphaDirect\Http\Controllers\CronController::class,'cronModelDelete'])->name('admin.cron.confirm-delete');
Route::any('/admin/cron/delete/{id}', [\AlphaDirect\Http\Controllers\CronController::class,'Delete'])->name('admin.cron.delete');
Route::get('/admin/cronkernel/create', [\AlphaDirect\Http\Controllers\CronController::class, 'kernelcreate'])->name('admin.cronkernel.create');
Route::get('/admin/cronkernel', [\AlphaDirect\Http\Controllers\CronController::class, 'kernelindex'])->name('admin.cronkernel.index');
Route::get('/admin/cronkernel/data', [\AlphaDirect\Http\Controllers\CronController::class, 'cronkerneldata'])->name('admin.cronkernel.data');
Route::post('/admin/cronkernel/store', [\AlphaDirect\Http\Controllers\CronController::class, 'kernelstore'])->name('admin.cronkernel.store');
Route::get('/admin/cronkernel/edit/{id}', [\AlphaDirect\Http\Controllers\CronController::class, 'kerneledit'])->name('admin.cronkernel.edit');
Route::post('/admin/cronkernel/confirm-delete', [\AlphaDirect\Http\Controllers\CronController::class,'cronkernelModelDelete'])->name('admin.cronkernel.confirm-delete');
Route::any('/admin/cronkernel/delete/{id}', [\AlphaDirect\Http\Controllers\CronController::class,'kernelDelete'])->name('admin.cronkernel.delete');

// Cron Cancellation Routes (Super Admin only)
Route::get('/admin/cron/cancel', [\AlphaDirect\Http\Controllers\CronController::class, 'cancelCronPage'])->name('admin.cron.cancel');
Route::post('/admin/cron/cancel', [\AlphaDirect\Http\Controllers\CronController::class, 'cancelCron'])->name('admin.cron.cancel.post');
// Report stakeholder email management
Route::get('/admin/cron/stakeholders', [\AlphaDirect\Http\Controllers\CronController::class, 'stakeholdersIndex'])->name('admin.cron.stakeholders');
Route::post('/admin/cron/stakeholders', [\AlphaDirect\Http\Controllers\CronController::class, 'stakeholdersStore'])->name('admin.cron.stakeholders.store');
Route::post('/admin/cron/stakeholders/{id}/toggle', [\AlphaDirect\Http\Controllers\CronController::class, 'stakeholdersToggle'])->name('admin.cron.stakeholders.toggle');
Route::delete('/admin/cron/stakeholders/{id}', [\AlphaDirect\Http\Controllers\CronController::class, 'stakeholdersDelete'])->name('admin.cron.stakeholders.delete');


Route::get('merge-pdf', [\AlphaDirect\Http\Controllers\Admin\PDFController::class, 'index']);
Route::post('merge-pdf', [\AlphaDirect\Http\Controllers\Admin\PDFController::class, 'store'])->name('merge.pdf.post');
Route::post('/getProductPlans', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getProductPlans'])->name('admin.ProductPlanDocs');

Route::any('/admin/whatsapp', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'whatsapp'])->name('whatsapp');
Route::post('/admin/whatsapp/send', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'sendMetaData'])->name('whatsapp.send');
Route::get('/privacy-policy', function () {
    return view('admin/whatsApp/privecy_policy');
});
#NewClaims
#Route::get('/admin/newclaims/{claimType}/create/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'create'])->name('admin.newclaims.create');
Route::post('/admin/newclaims/bi/store', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'store'])->name('admin.newclaims.store');
Route::get('/admin/newclaims', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'index'])->name('admin.newclaims.index');
Route::get('/admin/newclaims/data', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'data'])->name('admin.newclaims.data');
Route::get('/admin/newclaims/{claimType}/edit/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'edit'])->name('admin.newclaims.edit');
Route::get('/admin/newclaims/update', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'update'])->name('admin.newclaims.update');
Route::get('/admin/newclaims/{claimType}/create/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'create'])->name('admin.newclaims.create');
Route::post('/admin/newclaims/allRisk/store', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'store'])->name('admin.newclaims.store');
Route::post('/admin/newclaims/burglary/store', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'store'])->name('admin.newclaims.store');

Route::post('/admin/newclaims/confirm-archive', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'confirmArchive'])->name('admin.newclaims.confirm-archive');
Route::get('/admin/newclaims/archive/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'Archive'])->name('admin.newclaims.archive');
Route::get('/admin/newclaims/prosess/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'prosess'])->name('admin.newclaims.prosess');
Route::post('/admin/newclaims/prosess/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'claimprosess'])->name('admin.newclaims.prosess');
Route::get('/admin/newclaims/claimView/{id}', [\AlphaDirect\Http\Controllers\Admin\NewClaimController::class, 'claimView'])->name('admin.newclaims.claimView');
Route::get('/admin/cancelpolicyrequests', [\AlphaDirect\Http\Controllers\CancelPolicyRequestController::class,'index'])->name('admin.cancelpolicyrequests.index');
Route::get('/admin/cancelpolicyrequests/data', [\AlphaDirect\Http\Controllers\CancelPolicyRequestController::class,'cancelPolicyRequestData'])->name('admin.cancelpolicyrequests.data');
Route::get('/admin/cancelpolicyrequests/approved/{id}', [\AlphaDirect\Http\Controllers\CancelPolicyRequestController::class,'approved'])->name('cancelpolicyrequests.approved');
Route::get('/admin/cancelpolicyrequests/decline/{id}', [\AlphaDirect\Http\Controllers\CancelPolicyRequestController::class,'decline'])->name('cancelpolicyrequests.decline');

Route::get('paymclientContractList/{id}',  [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'getClientContractList'])->name('admin.paymclientContractList');
Route::get('getSchuduleList/{id}',  [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'getSchuduleList'])->name('admin.getSchuduleList');
Route::get('getUpdateSchuduleList/{id}',  [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'getUpdateSchuduleList'])->name('admin.getUpdateSchuduleList');
Route::post('cancelPaymentArrangement/{id}',  [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'cancelPaymentArrangement'])->name('admin.cancelPaymentArrangement');
Route::post('cancelScheduleTransaction/{id}',  [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'cancelScheduleTransaction'])->name('admin.cancelScheduleTransaction');

// Rewards CRUD routes
//Route::resource('admin/reward-tiers', RewardTierController::class);
//Route::resource('admin/benefits', \AlphaDirect\Http\Controllers\Admin\BenefitController::class);
//Route::resource('admin/customer-benefits', \AlphaDirect\Http\Controllers\Admin\CustomerBenefitController::class);

// Reward Tiers CRUD routes (explicit, not resource)
Route::get('admin/reward-tiers', [RewardTierController::class, 'index'])->name('reward-tiers.index');
Route::get('admin/reward-tiers/create', [RewardTierController::class, 'create'])->name('reward-tiers.create');
Route::post('admin/reward-tiers', [RewardTierController::class, 'store'])->name('reward-tiers.store');
Route::get('admin/reward-tiers/{id}', [RewardTierController::class, 'show'])->name('reward-tiers.show');
Route::get('admin/reward-tiers/{id}/edit', [RewardTierController::class, 'edit'])->name('reward-tiers.edit');
Route::put('admin/reward-tiers/{id}', [RewardTierController::class, 'update'])->name('reward-tiers.update');
Route::delete('admin/reward-tiers/{id}', [RewardTierController::class, 'destroy'])->name('reward-tiers.destroy');
Route::get('reward-tiers/expiry-days', [RewardTierController::class, 'getExpiryDays'])->name('reward-tiers.expiry-days');
Route::post('reward-tiers/expiry-days', [RewardTierController::class, 'updateExpiryDays'])->name('reward-tiers.update-expiry-days');

// Benefit CRUD routes (explicit, not resource)
Route::get('admin/benefits', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'index'])->name('benefits.index');
Route::get('admin/benefits/create', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'create'])->name('benefits.create');
Route::post('admin/benefits', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'store'])->name('benefits.store');
Route::get('admin/benefits/{id}', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'show'])->name('benefits.show');
Route::get('admin/benefits/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'edit'])->name('benefits.edit');
Route::put('admin/benefits/{id}', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'update'])->name('benefits.update');
Route::delete('admin/benefits/{id}', [\AlphaDirect\Http\Controllers\Admin\BenefitController::class, 'destroy'])->name('benefits.destroy');

// Customer Reward CRUD routes (explicit, not resource)
Route::get('admin/customer-rewards', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'index'])->name('customer-rewards.index');
Route::get('admin/customer-rewards/create', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'create'])->name('customer-rewards.create');
Route::post('admin/customer-rewards', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'store'])->name('customer-rewards.store');
Route::get('admin/customer-rewards/{id}', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'show'])->name('customer-rewards.show');
Route::get('admin/customer-rewards/{id}/edit', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class, 'edit'])->name('customer-rewards.edit');
Route::put('admin/customer-rewards/{id}', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class, 'update'])->name('customer-rewards.update');
Route::delete('admin/customer-rewards/{id}', [\AlphaDirect\Http\Controllers\Admin\CustomerRewardController::class, 'destroy'])->name('customer-rewards.destroy');

Route::post('admin/benefit-types/add', [ConfigController::class, 'addBenefitType'])->name('admin.benefit-types.add');

// Route::post('/kyc-chunk-upload', [KycUploadController::class, 'upload'])->name('kyc.chunk.upload');

// Route::get('/kyc-upload', function () {
//     return view('kyc_upload');
// })->name('kyc.upload.page');

Route::get('/kyc-upload', [KycUploadController::class, 'index'])->name('kyc.upload');
Route::get('/kyc-files', [KycUploadController::class, 'listFiles'])->name('kyc.files.list');


// Route::resource('pricings', PricingController::class);

// List (index)
Route::get('pricings', [PricingController::class, 'index'])->name('pricings.index');

// Show create form
Route::get('pricings/create', [PricingController::class, 'create'])->name('pricings.create');

// Store new record
Route::post('pricings', [PricingController::class, 'store'])->name('pricings.store');

// Show edit form
Route::get('pricings/{pricing}/edit', [PricingController::class, 'edit'])->name('pricings.edit');

// Update record
Route::put('pricings/{pricing}', [PricingController::class, 'update'])->name('pricings.update');

// Delete record
Route::delete('pricings/{pricing}', [PricingController::class, 'destroy'])->name('pricings.destroy');


// AD Group policy bulk create
Route::get('adGroupPolicy',[ADGroupPolicyFileController::class,'adGroupPolicy'])->name('admin.policy.adGroupPolicy');
Route::get('adGroupPolicyData',[ADGroupPolicyFileController::class,'adGroupPolicyData'])->name('admin.policy.adGroupPolicyData');
Route::get('adGroupPolicy/generateUserPoliciesPDF',[ADGroupPolicyFileController::class,'generateUserPoliciesPDF'])->name('admin.policy.generateUserPoliciesPDF');
Route::get('adGroupPolicy/getQuoteData',[ADGroupPolicyFileController::class,'getQuoteData'])->name('admin.policy.getQuoteData');
Route::post('adGroupPolicy/generateQuoteReport',[ADGroupPolicyFileController::class,'generateQuoteReport'])->name('admin.policy.generateQuoteReport');
Route::get('excel-create-policies',[ADGroupPolicyFileController::class,'excelPoliciesCreate'])->name('admin.policy.excelCreatePolicies');
Route::post('excel-upload-policies',[ADGroupPolicyFileController::class,'upload'])->name('admin.policy.excelADGroupPoliciesUpload');
Route::post('ad-group/process-uploads',[ADGroupPolicyFileController::class,'processAdGroupUploads'])->name('admin.policy.processAdGroupUploads');
Route::get('ad-group/pending-check',[ADGroupPolicyFileController::class,'adGroupPendingCheck'])->name('admin.policy.adGroupPendingCheck');
Route::get('ad-group/progress',[ADGroupPolicyFileController::class,'adGroupProgress'])->name('admin.policy.adGroupProgress');

// Legacy V1 employer-groups Blade surface. These sat at TOP LEVEL with no
// auth at all (V8 carry-over) — the send actions could create HR accounts
// and email credentials unauthenticated. Gated like the rest of the V1
// admin panel; the V2 React portal replaces them via /api/v1/employer-groups.
// Route::resource('employer-groups', EmployerGroupController::class);
Route::group(['middleware' => ['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer']], function () {
    Route::get('/employer-groups', [EmployerGroupController::class, 'index'])->name('admin.employer-groups.index');
    Route::get('/employer-groups-create', [EmployerGroupController::class, 'create'])->name('admin.employer-groups.create');
    Route::get('/employer-groups-show/{id}', [EmployerGroupController::class, 'show'])->name('admin.employer-groups.show');
    Route::get('/employer-groups-edit/{id}', [EmployerGroupController::class, 'edit'])->name('admin.employer-groups.edit');
    Route::delete('/employer-groups-destroy/{id}', [EmployerGroupController::class, 'destroy'])->name('admin.employer-groups.destroy');
    Route::post('/employer-groups-store', [EmployerGroupController::class, 'store'])->name('admin.employer-groups.store');
    Route::put('/employer-groups-update/{employerGroup}', [EmployerGroupController::class, 'update'])->name('admin.employer-groups.update');
    Route::post('/employer-groups-send-hr-credentials/{id}', [EmployerGroupController::class, 'sendHrCredentials'])->name('admin.employer-groups.send-hr-credentials');
    Route::post('/employer-groups-send-onboarding-email/{id}', [EmployerGroupController::class, 'sendOnboardingEmail'])->name('admin.employer-groups.send-onboarding-email');
});


// HR Portal Routes
Route::prefix('hr')->group(function () {
    Route::get('/login', [HrController::class, 'showLoginForm'])->name('hr.login');
    Route::post('/login', [HrController::class, 'login']);
    Route::post('/logout', [HrController::class, 'logout'])->name('hr.logout');
    Route::get('/set-password', [HrController::class, 'showSetPasswordForm'])->name('hr.set-password.show');
    Route::post('/set-password', [HrController::class, 'setPassword'])->name('hr.set-password.store');
    Route::get('/password-request', [HrController::class, 'showPasswordRequestForm'])->name('hr.password.request');
    Route::post('/password-email', [HrController::class, 'sendPasswordResetLink'])->name('hr.password.email');
    
    // HR AD Group Policy Routes
    Route::get('/ad-group-policy', [HrController::class, 'adGroupPolicy'])->name('hr.ad-group-policy')->middleware('auth:hr');
    Route::get('/ad-group-policy-data', [HrController::class, 'adGroupPolicyData'])->name('hr.ad-group-policy-data')->middleware('auth:hr');
    Route::get('/ad-group-policy/generate-user-policies-pdf', [HrController::class, 'generateUserPoliciesPDF'])->name('hr.generate-user-policies-pdf')->middleware('auth:hr');
    Route::get('/ad-group-policy/get-quote-data', [HrController::class, 'getQuoteData'])->name('hr.get-quote-data')->middleware('auth:hr');
    Route::post('/ad-group-policy/generate-quote-report', [HrController::class, 'generateQuoteReport'])->name('hr.generate-quote-report')->middleware('auth:hr');
    Route::get('/debug-hr-user', [HrController::class, 'debugHrUser'])->name('hr.debug-user')->middleware('auth:hr');
    
    // HR Policy Update Routes
    Route::get('/policy-update', [HrController::class, 'policyUpdate'])->name('hr.policy-update')->middleware('auth:hr');
    Route::get('/policy-update-data', [HrController::class, 'policyUpdateData'])->name('hr.policy-update-data')->middleware('auth:hr');
    Route::post('/policy-update-bulk', [HrController::class, 'policyUpdateBulk'])->name('hr.policy-update-bulk')->middleware('auth:hr');
    
    // Debug route for policy data
    Route::get('/debug-policy-data', function() {
        try {
            $hrUser = \App\Models\HrUser::where('email', 'trapeisseddifrau-3684@yopmail.com')->first();
            if (!$hrUser) {
                return response()->json(['error' => 'HR User not found']);
            }
            
            $employerGroup = $hrUser->employerGroup;
            if (!$employerGroup) {
                return response()->json(['error' => 'No employer group assigned']);
            }
            
            $policies = \App\Models\EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
                ->where('employer_group_policy.employer_group_id', $employerGroup->employer_group_id)
                ->where('policies.is_test_policy', 0)
                ->where('policies.product_id', 12)
                ->count();
                
            return response()->json([
                'hr_user' => $hrUser->email,
                'employer_group' => $employerGroup->employer_group_id,
                'policy_count' => $policies
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    });
});

// Webfleet Trips Routes
// UAT 2026-05-27 (Prathap §2.2): same defense-in-depth gate as the
// main admin route groups above + the role check missed by PR #701.
Route::prefix('admin')->middleware(['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer'])->group(function () {
    Route::get('/trips', [\AlphaDirect\Http\Controllers\Admin\TripController::class, 'index'])->name('admin.trips.index');
    Route::get('/trips/data', [\AlphaDirect\Http\Controllers\Admin\TripController::class, 'getData'])->name('admin.trips.data');
    Route::get('/trips/{id}', [\AlphaDirect\Http\Controllers\Admin\TripController::class, 'show'])->name('admin.trips.show');
});
Route::prefix('admin')->middleware(['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer'])->group(function () {
    Route::get('/ppk-rating-config', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'index'])->name('ppk-rating-config.index');
    Route::get('/ppk-rating-config/create', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'create'])->name('ppk-rating-config.create');
    Route::post('/ppk-rating-config', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'store'])->name('ppk-rating-config.store');
    Route::get('/ppk-rating-config/{ppkRatingConfig}', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'show'])->name('ppk-rating-config.show');
    Route::get('/ppk-rating-config/{ppkRatingConfig}/edit', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'edit'])->name('ppk-rating-config.edit');
    Route::put('/ppk-rating-config/{ppkRatingConfig}', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'update'])->name('ppk-rating-config.update');
    Route::delete('/ppk-rating-config/{ppkRatingConfig}', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'destroy'])->name('ppk-rating-config.destroy');
    Route::patch('/ppk-rating-config/{ppkRatingConfig}/activate', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'activate'])->name('ppk-rating-config.activate');
    Route::post('/ppk-rating-config/{ppkRatingConfig}/duplicate', [\AlphaDirect\Http\Controllers\PpkRatingConfigController::class, 'duplicate'])->name('ppk-rating-config.duplicate');
});

// Premium Calculator Routes
Route::prefix('admin')->middleware(['block.v1_admin', 'auth', 'role:Super Admin|Manager|Admin|developer'])->group(function () {
    Route::get('/premium-calculator', [\AlphaDirect\Http\Controllers\Admin\PremiumCalculatorController::class, 'index'])->name('admin.calculator.index');
    Route::post('/premium-calculator/calculate', [\AlphaDirect\Http\Controllers\Admin\PremiumCalculatorController::class, 'calculate'])->name('admin.calculator.calculate');
    Route::post('/premium-calculator/calculate-from-policy', [\AlphaDirect\Http\Controllers\Admin\PremiumCalculatorController::class, 'calculateFromPolicy'])->name('admin.calculator.calculate-from-policy');
    Route::post('/premium-calculator/trip-data', [\AlphaDirect\Http\Controllers\Admin\PremiumCalculatorController::class, 'getTripData'])->name('admin.calculator.trip-data');
    Route::post('/premium-calculator/policy-calculations', [\AlphaDirect\Http\Controllers\Admin\PremiumCalculatorController::class, 'getPolicyCalculations'])->name('admin.calculator.policy-calculations');
});

/*
 * Dev portal — /dev/*
 *
 * Gated by DevPortalGate: open in non-prod envs, or when
 * DEV_PORTAL_ENABLED=1, or for users with the 'developer' role.
 * Otherwise returns 404 (intentionally not "forbidden" — we don't
 * want to advertise the portal exists).
 */
Route::middleware([DevPortalGate::class])->prefix('dev')->group(function () {
    Route::get('/',               [DevPortalController::class, 'index'])->name('dev.index');
    Route::get('/endpoints',      [DevPortalController::class, 'endpoints'])->name('dev.endpoints');
    Route::get('/openapi',        [DevPortalController::class, 'openapi'])->name('dev.openapi');
    Route::get('/swagger',        [DevPortalController::class, 'swagger'])->name('dev.swagger');
    // GET (not POST) to keep it outside the CSRF guard — the endpoint only
    // returns a token for an already-authenticated web session, there's no
    // state change the CSRF middleware would protect.
    Route::get('/swagger-token',  [DevPortalController::class, 'swaggerToken'])->name('dev.swaggerToken');
    Route::get('/logs',           [DevPortalController::class, 'logs'])->name('dev.logs');
    Route::get('/docs',           [DevPortalController::class, 'docs'])->name('dev.docs');
    Route::get('/docs/{file}',    [DevPortalController::class, 'docs'])->name('dev.doc')->where('file', '[A-Za-z0-9_.\\-]+');
});



/*
|--------------------------------------------------------------------------
| Policy cover sheet — client document access via the printed QR
|--------------------------------------------------------------------------
|
| The one-page cover sheet we post to clients carries a QR pointing at
| /p/d/{token}. The token names a policy and grants nothing on its own: the
| client must pass the identity check (an OTP to the number ON THE POLICY, or
| an already-signed-in portal account that owns it) before the document is
| served. Deliberately NOT a public file link — the pack carries the insured's
| name and addresses, vehicle registrations, named drivers and beneficiary
| details.
|
| Server-rendered on purpose: the client arrives from a phone camera app on
| mobile data, so there is no bundle to download and no client-side routing.
|
| Every step, including every refusal, is written to
| policy_document_access_logs by CoverSheetAccessService.
|
| Throttles are per-step: the send endpoint is tightest because it costs an
| SMS, and verify is capped so a 6-digit code cannot be brute-forced from one
| address (PublicOtpService also locks the code after 5 wrong tries).
*/
Route::prefix('p/d')->name('coverSheet.')->group(function () {
    $ctrl  = \AlphaDirect\Http\Controllers\CoverSheet\PolicyDocumentAccessController::class;
    // "<lookup>.<secret>" — constrained per route rather than on the group,
    // because RouteRegistrar::group() returns void and cannot be chained.
    $shape = '[A-Za-z0-9._-]{10,140}';

    Route::get('{token}',             [$ctrl, 'show'])->middleware('throttle:30,1')->where('token', $shape)->name('show');
    Route::post('{token}/otp/send',   [$ctrl, 'sendOtp'])->middleware('throttle:6,1')->where('token', $shape)->name('otpSend');
    Route::post('{token}/otp/verify', [$ctrl, 'verifyOtp'])->middleware('throttle:20,1')->where('token', $shape)->name('otpVerify');
    Route::post('{token}/login',      [$ctrl, 'useLogin'])->middleware('throttle:20,1')->where('token', $shape)->name('login');
    Route::get('{token}/file',        [$ctrl, 'download'])->middleware('throttle:20,1')->where('token', $shape)->name('file');
});
