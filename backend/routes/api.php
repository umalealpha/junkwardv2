<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use AlphaDirect\Http\Controllers\HealthInfoController;
use AlphaDirect\Http\Controllers\KycUploadController;
use AlphaDirect\Http\Controllers\PricingController;
use AlphaDirect\Http\Controllers\AdGroupKycController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::any('whatsapp/webhook', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'webhook']);
Route::post('register1', [\AlphaDirect\Http\Controllers\AuthController::class, 'register']);
Route::post('createToken', [\AlphaDirect\Http\Controllers\AuthController::class, 'login']);
// Orange Money — internal/admin actions: mint an Orange bearer token, execute/
// revoke live debit orders, read scheduled collections + payment records. The
// SPA never calls these and the cron service calls the controller methods
// directly (not over HTTP), so an authenticated session is required. These were
// previously unauthenticated (token minting + payment-record leak to anyone).
Route::middleware('auth:sanctum')->group(function () {
    Route::any('orangeAccess', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeAccess']);
    Route::any('orangeMandateExcute', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandateExcute']);
    Route::any('orangeMandateRevoke', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandateRevoke']);
    Route::get('/getScheduledTransactionForOrange', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getScheduledTransactionForOrange']);
    Route::get('/dpoPaymentRecord/{id}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'dpoPaymentRecord']);
});

// Orange Money — INBOUND VCS webhooks (Orange's servers POST here to notify us
// of debit-order / mandate changes; they mutate policy + payment state).
// DISABLED 2026-08-12: these were unauthenticated and forgeable (unauth policy
// activation + forged "Success" payments — flagged in the security handover and
// left open by #1843, since a session guard cannot cover a server-to-server
// callback). Orange Money (VCS mandates) is DORMANT: orange_transactions has 0
// rows, orangeMandate holds 13 rows all dated Dec-2023 with 0 active, and
// orange_mandate_json_log has 0 webhook hits — nothing legitimate has called
// these in ~2.5 years (only RealPay + DPO are live). Closing the routes removes
// the attack surface at zero functional cost.
// RE-ENABLE ONLY AFTER adding callback auth first (HMAC signature — mirror
// VerifySwiftlySignature — a shared-secret callback URL, or a source-IP
// allowlist) via an `orange.callback` middleware, when Orange is switched back on.
// Route::any('orangeMoneyOrderNoification', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMoneyOrderNoification']);
// Route::post('orangeMandates', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandates']);
// Route::any('orangeMandateUpdate', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandateUpdate']);
// Route::post('orangeMandateDispute', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'orangeMandateDispute']);
Route::get('/getSettingsAPI', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getSettingsAPI']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('', [\AlphaDirect\Http\Controllers\AuthController::class, 'user']);
    Route::post('destroyToken', [\AlphaDirect\Http\Controllers\AuthController::class, 'logout']);
    Route::any('/customer/{cellphone}/offers', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getCustomerByCellphoneOrange']);
    Route::any('/customerInfo', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getCustomerInfoByCellphoneOrange']);

});
//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::middleware('auth:api')->get('/user', [\AlphaDirect\Http\Controllers\UserController::class, 'showUser']);

Route::any('/getDetails', [\AlphaDirect\Http\Controllers\Api\PolicyController::class, 'getDetails']);
Route::any('/subLedger', [\AlphaDirect\Http\Controllers\Api\PolicyController::class, 'subLedger']);
Route::any('/signIn', [\AlphaDirect\Http\Controllers\Api\UserController::class, 'login']);
Route::any('/subLedgerEntries', [\AlphaDirect\Http\Controllers\Api\PolicyController::class, 'subLedgerEntries']);
Route::any('/customerDetails', [\AlphaDirect\Http\Controllers\Api\CustomerController::class, 'customerDetails']);
Route::any('validateUser', [\AlphaDirect\Http\Controllers\Api\UserController::class, 'validateUser']);
Route::any('updateOdooId', [\AlphaDirect\Http\Controllers\Api\PolicyController::class, 'updateOdooId']);
Route::any('/allCustomers', [\AlphaDirect\Http\Controllers\Api\CustomerController::class, 'allCustomers']);
Route::any('/checkSMS', [\AlphaDirect\Http\Controllers\Api\UserController::class, 'checkSMS']);
//Arihant
// Route::post('/getCoverCancelNote', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class,'getCoverCancelNote');
Route::post('/getPolicyDocument', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getPolicyDocument']);
Route::post('/getProducts', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getProducts']);
// KYC identity documents (Omang front/back, passport, licence, proof of income/
// residence) + the raw customer_kyc row. Were unauthenticated: base64_decode of
// customer_id is obfuscation, not access control, so IDs were enumerable and the
// national ID book harvestable. Require an authenticated session.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/getKycFiles', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getKycFiles'])->name('getKycFiles');
    Route::post('/startgetKycFiles', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'startgetKycFiles'])->name('startgetKycFiles');
});
Route::any('/generateLedger', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'generateLedger']);
Route::post('/frontendpay/find-claim', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'searchClaim']);
Route::post('/paymentTransactions', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'paymentTransactions']);
Route::any('/paymentTransactions', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'paymentTransactions']);
Route::post('/cancelPolicyFromAll', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'cancelPolicyFromAll']);

// REMOVED: unauthenticated duplicate of '/customer/{cellphone}/offers' (which is
// correctly gated by auth:sanctum above). This alias pointed at the same
// controller method with no auth, bypassing the guard and allowing bulk
// enumeration of the unpaid-policy book. The authenticated route is the only one
// the SPA uses; nothing calls '/debitors'.
// Route::any('/debitors/{cellphone}/offers', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getCustomerByCellphoneOrange']);
#Route::any('/creditorInfo', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getCustomerInfoByCellphoneOrange']);
Route::group(['middleware' => ['api']], function () {
    Route::post('/OriBillingStartDate',  [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'OriBillingStartDate']);

    Route::get('products', [\AlphaDirect\Http\Controllers\Admin\ProductController::class, 'getProducts']);
    Route::get('plans', [\AlphaDirect\Http\Controllers\Admin\ProductPlanController::class, 'getPlans']);
    Route::post('getpoliciesbycellphone', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getPoliciesByCellphone']);
    Route::post('getcustomerbycellphone', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getCustomerByCellphone']);


    Route::post('preinspections', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'preinspectionsEvent']);

    #dpo payment
    Route::post('findPolicyForOnlinePayment', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'findPolicyForOnlinePayment']);
    Route::post('findPolicyForOnlinePaymentMotorComp', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'findPolicyForOnlinePaymentMotorComp']);
    Route::any('saveonlinepayment', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'saveOnlinePayment']);
    Route::any('saveDpoOnesOffTransaction', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'saveDpoOnesOffTransaction']);
    Route::any('instantActivatePolicy', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'instantActivatePolicy']);
    Route::any('updateContract', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'updateContract']);
    Route::post('verifyPayment', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'verifyPayment']);
    Route::post('subscriptionToken', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'subscriptionToken']);
    Route::post('chargeTokenRecurrent', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'chargeTokenRecurrent']);
    Route::post('pullAccount', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'pullAccount']);
    Route::post('dpoPushNotification', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'dpoPushNotification']);
    Route::any('reratePolicyUpdate', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'reratePolicyUpdate']);
    Route::any('rerateFailedWithDpo', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'rerateFailedWithDpo']);
    Route::any('PolicyReinstateDeclined', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'PolicyReinstateDeclined']);
    Route::get('testingReccurent', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'testingReccurent']);
    Route::get('testingReccurentWithRecurrentDate', [\AlphaDirect\Http\Controllers\DpoPaymentController::class, 'testingReccurentWithRecurrentDate']);

    #Common APIs
    Route::any('/createPolicy', [\AlphaDirect\Http\Controllers\CommonApis\PolicyController::class, 'createPolicy']);

    # BizSure (external Rails client) — ported from graphiteBWV8 CommonApis namespace
    # Lookup GET is unauthenticated (read-only, no PII). All mutating BizSure routes
    # require the shared partner API key via VerifyApiKey middleware.
    Route::get('/lookup/{key}', [\AlphaDirect\Http\Controllers\CommonApis\LookupController::class, 'getByKey']);
    Route::get('/policies/lastId', [\AlphaDirect\Http\Controllers\CommonApis\PolicyController::class, 'lastId'])->middleware('VerifyApiKey');
    Route::post('/lookup/{key}', [\AlphaDirect\Http\Controllers\CommonApis\LookupController::class, 'storeByKey'])->middleware(['VerifyApiKey', 'throttle:30,1']);
    Route::post('/bizsure/createPolicy', [\AlphaDirect\Http\Controllers\CommonApis\BizSurePolicyController::class, 'createPolicy'])->middleware('VerifyApiKey');
    Route::get('/bizsure/policy-expiry', [\AlphaDirect\Http\Controllers\CommonApis\BizSurePolicyController::class, 'policyExpiry'])->middleware(['VerifyApiKey', 'throttle:120,1']);

    # MotoLink assessment auto-fill (Kago/Ditso 2026-06-27): claim_number -> client,
    # policy no, registration, date of loss, sum insured + excess. Read-only, api-key gated.
    Route::get('/frontendpay/assessment-lookup', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'assessmentLookup'])->middleware(['VerifyApiKey', 'throttle:60,1']);
    Route::post('/getProductFactors', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getProductFactors']);
    Route::post('/getChatbotProductFactors', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getProductFactors']);
    Route::post('/getCarModel', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkMakeModel']);
    Route::post('/calculatePremium', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'calculatePremium']);
    Route::post('/savePolicyNo', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'savePolicyNo']);
    Route::post('/savePolicyYes', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'savePolicyYes']);
    Route::post('/getpolicysummary', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getpolicysummary']);
    Route::get('/getpolicysummary', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getpolicysummary']);
    Route::post('/updatepolicy/{id}', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'updatepolicy']);

    # Risk Address Dropdowns
    Route::get('/risk-address/states', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getStates']);
    Route::get('/risk-address/states/{stateId}/cities', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getCitiesByState']);
    Route::get('/risk-address/construction-types', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getConstructionTypes']);
    Route::get('/risk-address/structure-types', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getStructureTypes']);
    Route::get('/risk-address/occupation-types', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getOccupationTypes']);
    Route::get('/risk-address/occupancy-types', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getOccupancyTypes']);
    Route::get('/risk-address/extensions', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getExtensions']);
    Route::get('/risk-address/usage-types', [\AlphaDirect\Http\Controllers\Api\RiskAddressController::class, 'getUsageTypes']);

    #Mobile App End points
    Route::any('/activatePolicyFirstTimeUserAlphaFe', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'activatePolicyFirstTimeUserAlphaFe']);
    Route::any('/activatePolicyFirstTimeUserAlphaFe2', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'activatePolicyFirstTimeUserAlphaFe2']);
    Route::post('/newPolicyRequest', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'newPolicyRequest']);
    Route::any('/whatsapp/getpolicybypolicynumber', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getpolicybypolicynumber']);
    Route::post('/getProductPlans', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getProductPlans']);
    Route::post('/getProductsMobile', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getProducts']);
    Route::post('/getProductsForStart', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getProductsForStart']);
    Route::post('/preInspectionPhotos', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'preInspectionPhotos']);
    Route::post('/customerPolicies', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getUserApplications']);
    Route::post('/generateActivationCodeAlphaFePay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'generateActivationCodeAlphaFePay']);
    Route::post('/retrieveActivationCodeAlphaFePay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'retrieveActivationCodeAlphaFePay']);
    Route::post('/getCustomerID', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerID']);
    Route::post('/realpay/customerCheck', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'realpayCustomerCheck']);
    Route::post('/checkPayEmailExists', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkPayEmailExists']);
    Route::post('/checkCustomerBankingAccountExists', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkCustomerBankingAccountExists']);
    Route::post('/getCustomerBank', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerBank']);
    Route::post('/getCustomerDocument', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerDocument']);
    Route::any('checkUpdateAvailable', [\AlphaDirect\Http\Controllers\ConfigController::class, 'getAPkVersionNumber']);
    Route::get('clearCache',  [AlphaDirect\Http\Controllers\ConfigController::class, 'clearCache']);
    Route::post('/addOption', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'addOption']);
    Route::post('/updatePayXwithRealPay', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'updatePayXwithRealPay'])->name('policy.updatePayXwithRealPay');
    Route::post('/getHibPremium',[\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class,'getHibPremium'])->name('getHibPremium');
    #WhatsappAPI /  end-points
    Route::group(['middleware' => ['VerifyApiKey']], function () {

        Route::any('/whatsapp/updateRPBankDetails', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'updateRealPayBankigDetails']);
        Route::any('/whatsapp/updateRPBillingDate', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'updateRealPayBillingDate']);
        Route::any('/whatsapp/splitRPBillingPremium', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'splitRealPayBillingPremium']);
        Route::any('/whatsapp/verifyAgent', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'checkAgentWhatsapp']);

        Route::post('/whatsapp/vehicleMake', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'vehicleMake']);
        Route::post('/whatsapp/getvehicleMake', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getvehicleMake']);
        Route::post('/whatsapp/login', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'login']);
        Route::post('/whatsapp/vehicleModel', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'vehicleModel']);
        Route::post('/whatsapp/createPolicyInstantInsurance', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'createPolicyInstantInsurance']);
        Route::post('/whatsapp/getCountries', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getCountries']);

        Route::post('/whatsapp/storeCustomerKycImages', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'storeCustomerKycImages']);
        Route::post('/whatsapp/createPolicy', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'createPolicy']);
        Route::post('/whatsapp/getpoliciesbycellphone', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getpoliciesbycellphone']);
        Route::post('/whatsapp/getclaimsbycellphone', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getclaimsbycellphone']);

        Route::post('/whatsapp/authenticateOTP', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'authenticateOTP']);
        Route::post('/whatsapp/getclaimbypolicynumber', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getclaimbypolicynumber']);
        Route::post('/whatsapp/saveClaim', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'saveClaim']);
        Route::post('/whatsapp/updatePolicy', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'whatsAppupdatePolicy']);
        Route::post('/whatsapp/cancelPolicy', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'cancelPolicy']);
        Route::post('/whatsapp/saveKyc', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'saveKyc']);
        Route::post('/whatsapp/profileUpdate', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'profileUpdate']);
        Route::post('/whatsapp/updateCardVcs', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'updateCardVcs']);
        Route::post('/whatsapp/getcutomerdetails', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'getcutomerdetails']);
        Route::post('/whatsapp/requestOTP', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'requestOTP']);
        Route::post('/whatsapp/beneficiaryUpdate', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'beneficiaryUpdate']);
        Route::post('/whatsapp/redoPayment', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'redoPayment']);
        Route::post('/whatsapp/createBeneficiary', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'storeBeneficiary']);
        Route::post('/whatsapp/lookupdata', [\AlphaDirect\Http\Controllers\Whatsapp\WhatsappAPIController::class, 'lookupdata']);
    });

    // Claims Tracker (claims.alphadirect.co.bw) -> Graphite V2 bridge.
    // Drop-in replacement for the V1 endpoint of the same path: Claims
    // Tracker only re-points GRAPHITE_API_BASE at the V2 backend. Gated by
    // VerifyClaimsTrackerApiKey (dedicated API_KEY_CLAIMS_TRACKER env, NOT
    // the shared API_KEY) so rotating its secret can't affect any other
    // VerifyApiKey consumer. Throttle: 60/min per IP (bursts in business hours).
    Route::post('/claims-tracker/create', [\AlphaDirect\Http\Controllers\Api\ClaimsTrackerController::class, 'store'])
        ->middleware(['VerifyClaimsTrackerApiKey', 'throttle:60,1']);

    // MotoLink (motolink.app) assessment cover lookup. READ-ONLY: returns
    // ONLY Sum Insured + the excess (First Amount Payable) schedule for a
    // claim/policy number — no customer name, ID, contact, or bank details.
    // Same dedicated api-key (VerifyClaimsTrackerApiKey) as the create route,
    // so MotoLink reuses the key it already holds. GET is allowed (and POST
    // for callers that prefer a body); throttle 120/min for assessment bursts.
    Route::match(['get', 'post'], '/claims-tracker/policy-cover', [\AlphaDirect\Http\Controllers\Api\ClaimsTrackerController::class, 'policyCover'])
        ->middleware(['VerifyClaimsTrackerApiKey', 'throttle:120,1']);

    // MotoLink (motolink.app) -> Graphite assessment PUSH (inbound webhook).
    // MotoLink POSTs one assessment event as JSON; we match the claim on
    // claim_number and mirror it into the dedicated motolink_* machine columns
    // (idempotent; never overwrites hand-typed data). Gated by a DEDICATED key
    // (VerifyMotolinkPushKey / MOTOLINK_PUSH_KEY) — separate from the read-only
    // cover-lookup key, so this WRITE endpoint's secret has its own blast
    // radius. Throttle 120/min for real-time event bursts.
    Route::post('/claims-tracker/assessment', [\AlphaDirect\Http\Controllers\Api\ClaimsTrackerController::class, 'assessment'])
        ->middleware(['VerifyMotolinkPushKey', 'throttle:120,1']);

    Route::any('/frontendpay/storeQuote', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'store']);

    #REALPAY
    Route::group(['middleware' => ['VerifyApiKey', 'XssSanitizer']], function () {
        /* Route::any('/realpay/updateInstallmentInfo', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class,'updateInstallment');*/
        #  Route::any('/frontendpay/storeQuote', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'store']);
    });

    Route::any('/frontendpay/getProRataPremium', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getProRataPremium']);
    Route::any('/frontendpay/calculatePerDayPremium', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'calculatePerDayPremium']);
    Route::any('/frontendpay/logRealpayPayment', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'logRealpayPayment']);

    // Route::post('/frontendpay/renewPolicyRealpayPayment', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'renewPolicyRealpayPayment']);

    Route::post('/frontendpay/calculateProrataPremium', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'calculateProrataPremium']);

    Route::get('clientContractList/{id}',  [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'getClientContractList']);

    Route::get('cancelContractForInsProd/{id}',  [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'cancelClientContractInstantProduct']);

    Route::group(['middleware' => ['XssSanitizer']], function () {
        #Client / customer portal end-points
        // Route::get('clientContractList/{id}',  [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'getClientContractList'])->name('clientContractList');
        // Route::any('updateClientNumber', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'updateClientNumber']);

        Route::any('/realpay/updateInstallmentInfo', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'updateInstallment'])->middleware('webhook.buffer:realpay');
        Route::any('/financialInterests', [\AlphaDirect\Http\Controllers\Admin\AccountsController::class, 'getFinancialInterests']);
        Route::any('/email/updateInfo', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'updateEmailInfo']);
        Route::any('/infobip/delivery-reports', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'updateSMSInfo'])->name('infobipResponse');
        Route::any('/email/getWebHook', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getWebHook']);
        Route::any('/realpay/procesRealPayPayment/{id}', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'procesRealPayPayment']);
        Route::any('/realpay/processRealPayContract/{id}', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'processRealPayContract']);
        Route::any('/realpay/logRealpayPayment', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'logRealpayManually']);
        Route::any('/orangemoney/addCustomerTransaction', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'addCustomerTransaction']);
        Route::any('/frontendpay/reratePolicyPremium', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'reratePolicyPremium']);
        Route::any('/frontendpay/newPolicyPremium', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'newPolicyPremium']);
        Route::any('/frontendpay/renewPolicyQuote', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'renewPolicyQuote']);
        Route::any('/frontendpay/setPoliyFrequency', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'setPoliyFrequency']);
        Route::any('/realpay/updateClientContractNumberApi', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'updateClientContractNumberApi']);
        Route::post('/getPreminumForRenewPolicy', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'getCalculatedPreminumForRenewPolicy']);
        Route::post('/getPreminumForFrontendRenewPolicy', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'getCalculatedPreminumForRenewPolicyAPI']);
        // Token-only public reads of a policy — rate-limited so the one-time link
        // token cannot be brute-forced. The controller also enforces expiry and
        // masks the bank/ID fields.
        Route::post('/getPolicyInformationRenew', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getPolicyInformationRenew'])->middleware('throttle:20,1');
        Route::post('/getPolicyInformationReinstate', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getPolicyInformationReinstate'])->middleware('throttle:20,1');
        Route::post('/getPolicyInformationReinstateArrears', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getPolicyInformationReinstateArrears'])->middleware('throttle:20,1');
        Route::post('/getPolicyInformationRerate', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getPolicyInformationRerate'])->middleware('throttle:20,1');
        Route::post('/logPaymentRenew', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'logPaymentRenew']);
        Route::post('/logPaymentReinstate', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'logPaymentReinstate']);
        Route::post('/getPreminumForRealpayRenew', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'getPreminumForRealpayRenewPolicy']);
        Route::any('/policyRenewPay', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'policyRenewPay']);
        Route::any('/policyRenewalPaymentFailed', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'policyRenewalPaymentFailed']);
        Route::post('/policyDiscountSurchargeAPI', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'policyDiscountSurchargeAPI']);
        Route::post('/customPolicyDiscountSurchargeAPI', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'customPolicyDiscountSurchargeAPI']);
        Route::any('/frontendpay/getClaimCountForCustomer', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getClaimCountForCustomer']);

        Route::get('/testAgentReport', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'testAgentReport']);
        Route::post('/getMotorComprehensivePolicyPremium', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getMotorComprehensivePolicyPremium']);

        Route::post('updateExpiredCardForVCS', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'updateExpiredCardForVCS']);
        Route::post('updateExpiredCardForDPO', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'updateExpiredCardForDPO']);


        Route::any('/policyRenewalPaymentDeclined', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'policyRenewalPaymentDeclined']);
        Route::any('/PolicyReinstateFailed', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'PolicyReinstateFailed']);
        Route::any('/PolicyReinstateDeclined', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'PolicyReinstateDeclined']);
        Route::post('/logRealpayPaymentForPolicyRenewal', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'addOfflinePaymentPolicyRenewal']);
        Route::post('/logCashPaymentForPolicyRenewal', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'addOfflinePaymentPolicyRenewal']);
        Route::any('/PolicyReinstate', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'PolicyReinstate']);
        Route::post('/transactionInfo', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'transactionInfo']);
        Route::any('/frontendpay/checkFailedPayments', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'failedPayments']);
        // CLOSED (security, CFO-approved 3-Sep-2026): this let ANY unauthenticated
        // caller overwrite a customer's bank account + billing by policy id (a
        // sequential integer) — debit-order corruption / takeover. No live caller
        // was found. Re-open only behind the Phase-1 customer-owner gate.
        // Route::any('/frontendpay/updateBankingInfo', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'updateBankingInfo']);
        Route::any('/frontendpay/getProducts', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getAllProducts']);
        Route::post('/frontendpay/login', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'Login'])->middleware('throttle:10,1');
        Route::post('/frontendpay/repairlogin', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'repairlogin'])->middleware('throttle:10,1');
        Route::post('/frontendpay/register', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/frontendpay/claimList', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getClaimsList']);
        Route::post('/frontendpay/repairClaimList', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getRepairClaims']);
        Route::post('/frontendpay/viewClaimData', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'viewClaimData']);
        Route::post('/frontendpay/claimData', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getClaimData']);
        Route::post('/frontendpay/vehicleMake', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'vehicleMake']);
        Route::post('/frontendpay/getCountries', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getCountries']);
        Route::post('/frontendpay/getStates', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getStates']);
        Route::post('/frontendpay/getCities', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getCities']);
        Route::any('/frontendpay/getStores', [\AlphaDirect\Http\Controllers\Admin\StoreController::class, 'getStores']);
        Route::post('/frontendpay/getinsuredData', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getinsuredData']);
        Route::post('/frontendpay/vehicleModel', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'vehicleModel']);
        Route::post('/frontendpay/policyList', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getPolicyList']);
        Route::post('/frontendpay/repairList', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getRepair']);
        Route::post('/frontendpay/vehicleList', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getvehicleList']);
        Route::post('/frontendpay/vehicleInfo', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getvehicleInfo']);
        Route::any('/frontendpay/updateClaim', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'updateClaim']);
        Route::any('/frontendpay/updateVehicleInfo', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'updateVehicleInfo']);
        Route::any('/frontendpay/checkVehiclePlate', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'checkVehicleExist']);
        Route::any('/frontendpay/checkCustomerExist', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'checkCustomerExist']);
        Route::any('/frontendpay/getCustomerData', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getCustomerData']);
        Route::any('/frontendpay/check_imei_number', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'check_imei_number']);
        Route::any('/frontendpay/storeClaim', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'addClaim']);
        Route::any('/frontendpay/beneficaries', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getBeneficaries']);
        Route::any('/frontendpay/customerProfile', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getCustomerData']);
        Route::any('/frontendpay/profileUpdate', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'profileUpdate']);
        Route::any('/frontendpay/updateBeneficiary', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'updateBeneficiary']);
        Route::any('/frontendpay/deleteBeneficiary', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'deleteBeneficiary']);
        Route::any('/frontendpay/passwordUpdate', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'passwordUpdate']);
        Route::any('/frontendpay/updateKyc', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'updateKyc']);
        Route::any('/frontendpay/kycDetails', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getKycDetails']);
        Route::any('/frontendpay/verifyImage', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'verifyImage']);
        Route::any('/frontendpay/getDataForKeyLossClaim', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getDataForKeyLossClaim']);
        Route::any('/frontendpay/getTTValue', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getTruTradeValue']);
        Route::any('/frontendpay/deviceBrands', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getDeviceBrands']);
        Route::any('/frontendpay/deviceBrandModels', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getDeviceBrandModels']);
        Route::any('/frontendpay/getCustomerCellPhoneNo', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getCellPhoneNumberUsingCustomerID']);
        Route::any('/frontendpay/getCustomerCellphoneByPolicyNumber', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getCustomerCellphoneByPolicyNumber']);
        Route::any('/frontendpay/getTTVehicleMakes', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getTruTradeVehicles']);
        Route::any('/frontendpay/getTTVehicleMakes2', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getTruTradeVehicles2']);
        Route::any('/frontendpay/getTTVehicleModels', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getTTVehicleModels']);
        Route::any('/frontendpay/getTTVehicleYear', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getTTVehicleYear']);
        Route::any('/frontendpay/getTTVehicleVariant', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getTTVehicleVariant']);
        Route::any('/frontendpay/getPolicyVehicle/{id}', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getPolicyVehicle']);
        Route::any('/frontendpay/actionAfterPolicyCreateFromQuote', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'actionAfterPolicyCreateFromQuote']);
        Route::any('/frontendpay/agentAppUploadKyc', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'agentAppUploadKyc']);
        Route::any('/frontendpay/verifyAgent', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'checkAgent']);
        Route::any('/frontendpay/verifyAgent2', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'checkAgent2']);
        Route::any('/generatePolicyDocument/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'generatePolicyDocument']);
        Route::any('/frontendpay/getQuoteDetails', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'getQuoteDetails']);
        Route::any('/Back-To-Quote/{id}', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'verify']);
        Route::any('/frontendpay/checkQuote', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'check']);
        Route::any('/frontendpay/verifyGeneratedQuote', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'verifyGeneratedQuote']);
        Route::any('/frontendpay/getCellphoneClaim', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getCellphoneClaim']); //for cellphone
        Route::post('/frontendpay/imageUploadApi', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'imageUploadApi']);
        Route::post('/frontendpay/renewPolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'renewPolicy']);
        Route::post('/frontendpay/life_insurance', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'lifeInsurance']);
        Route::post('/frontendpay/BundledRerate', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'BundledRerate']);


        //Route::any('/frontendpay/storeQuote', [\AlphaDirect\Http\Controllers\Admin\QuoteController::class,'store');
        Route::post('/frontendpay/createPolicyFromWhatsApp', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'createPolicyFromWhatsApp']);
        Route::post('/frontendpay/cancelRealPayContracts', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'cancelRealPayContract']);
        Route::any('/frontendpay/getInstallments', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getInstallments']);
        Route::any('/frontendpay/addAgentActivityAPI', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'addAgentActivityAPI']);
        //createPolicyFromWhatsApp
        // Route::any('generatePolicyDocument/{id}', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'generatePolicyDocument'])->name('documents.generatePolicyDocument');
        Route::post('/frontendpay/createPolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'createPolicy']);
        Route::post('/frontendpay/storeRealPayPayment', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'storeRealPayPayment']);
        Route::post('/frontendpay/storepaymPayment', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'storepaymPayment']);
        //    Route::post('/frontendpay/SendOtp', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class,'requestOTP');
        Route::post('/frontendpay/getOTP', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'requestOTP'])->middleware('throttle:20,1');
        Route::post('/frontendpay/resendOTP', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'resendOTP']);
        Route::post('/frontendpay/sendOTPPolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'sendOTPPolicy']);
        Route::post('/frontendpay/testMail', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'testMail']);
        Route::post('/frontendpay/authenticateOTP', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'authenticateOTP'])->middleware('throttle:10,1');
        Route::post('/frontendpay/createPolicy2', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'createPolicy2']);
        Route::post('/frontendpay/activationDetail', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'activationDetail']);
        Route::post('/frontendpay/getproductDetails', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'getproductDetails']);
        Route::post('/frontendpay/editPolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'editPolicy']);
        Route::post('/frontendpay/updatePolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'frontendPayupdatePolicy']);
        Route::any('/frontendpay/forgotPassword', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'forgotPassword'])->middleware('throttle:20,1');
        Route::get('/frontendpay/resetPassword/{id}', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'resetPassword'])->name('frontend.resetPassword');
        Route::post('/frontendpay/updatePassword', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'updatePassword']);
        // ForgotPassword for RepairCenter
        Route::any('/frontendpay/forgotPassword_repaircenter', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'forgotPassword_repaircenter']);
        Route::get('/frontendpay/resetPassword_repaircenter/{id}', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'resetPassword_repaircenter'])->name('frontend.resetPassword_repaircenter');
        Route::post('/frontendpay/updatePassword_repaircenter', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'updatePassword_repaircenter']);
        // Reset Password

        Route::post('/frontendpay/tqcreatePolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'tqcreatePolicy']);
        Route::any('/frontendpay/tqcheckVehiclePlate', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'tqcheckVehiclePlate']);
        Route::post('/frontendpay/getpolicyData', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'getpolicyData']);
        Route::post('/frontendpay/getcustVechilInfo', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'getcustVechilInfo']);

        Route::get('/frontendpay/resetPasswordFirstTimeUser/{id}', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'resetPasswordFirstTimeUser'])->name('frontend.resetPasswordFirstTimeUser');
        Route::post('/frontendpay/updatePasswordFirstTimeUser', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'updatePasswordFirstTimeUser']);
        // end

        Route::post('/frontendpay/generateActivationCode', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'generateActivationCode']);
        Route::post('/frontendpay/comprehensiveLead', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'comprehensiveLead']);
        //Route::post('/frontendpay/customerFeedback', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'customerFeedback']);
        Route::post('/frontendpay/customerFeedback', [\AlphaDirect\Http\Controllers\CancelPolicyRequestController::class,'store']);
        //Route::post('/frontendpay/customerFeedbackFromStart', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'customerFeedbackFromStart']);
        Route::post('/frontendpay/customerFeedbackFromStart', [\AlphaDirect\Http\Controllers\CancelPolicyRequestController::class,'store']);
        Route::post('/frontenpay/sendOtpByPolicy', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'sendOtpByPolicy']);
        Route::post('/checkEmail', [\AlphaDirect\Http\Controllers\Admin\AccountsController::class, 'checkMail']);
        Route::post('/frontendpay/imageUpload', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'imageUpload']);
        Route::post('/agentPieChart', [\AlphaDirect\Http\Controllers\AgentController::class, 'agentPieChart'])->name('agentPieChart');
    });

    // AD Group KYC - Save full payload
    Route::post('adgroupkyc/save-kyc', [AdGroupKycController::class, 'saveKycSubmission']);
    #end

    #start routes
    Route::any('/start/updateKyc', [\AlphaDirect\Http\Controllers\Start\StartController::class, 'updateKyc']);
    Route::any('/start/Kycdata', [\AlphaDirect\Http\Controllers\Start\StartController::class, 'Kycdata']);
    Route::any('/start/deleteVehiclePreinspection', [\AlphaDirect\Http\Controllers\Start\StartController::class, 'deleteVehiclePreinspection']);
    Route::any('/start/updatePreinspection', [\AlphaDirect\Http\Controllers\Start\StartController::class, 'updatePreinspection']);

    #odoo API routes
    Route::any('/createpartnerodoo', [\AlphaDirect\Http\Controllers\Api\CustomerController::class, 'createOdooPartnerGFS']);

    #end routes

    Route::post('/sendSmsOtp', [\AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'sendUserOtp'])->name('sendSmsOtp');

    Route::post('registerUser', [\AlphaDirect\Http\Controllers\PublicController::class, 'registerUser']);

    Route::post('createAccount', [\AlphaDirect\Http\Controllers\PublicController::class, 'createAccount']);

    Route::get('/viewPolicy/{id}', [\AlphaDirect\Http\Controllers\PublicController::class, 'viewPolicyDetails']);//->name('viewPolicyDetails');

    Route::get('userPolicies/{id}', [\AlphaDirect\Http\Controllers\UserController::class, 'getAllUserApplications']);

    Route::post('userClaims', [\AlphaDirect\Http\Controllers\UserController::class, 'getUserClaims']);

    Route::get('/allStaffMembers', [\AlphaDirect\Http\Controllers\PublicController::class, 'getAllStaffMembers']);//->name('allStaffmembers');

    Route::get('userNotice/{cellphone}', [\AlphaDirect\Http\Controllers\PublicController::class, 'sendCustomerSms']);
    Route::post('scratch', [\AlphaDirect\Http\Controllers\PublicController::class, 'activatePolicy']);
    Route::post('makepolicy', [\AlphaDirect\Http\Controllers\PublicController::class, 'activatePolicy']);
    Route::post('submitKYC', [\AlphaDirect\Http\Controllers\Customer\Microinsurance\ScratchController::class, 'saveKYC']);

    Route::post('searchPolicies', [\AlphaDirect\Http\Controllers\PublicController::class, 'searchPolicies']);//->name('searchPolicies');

    Route::post('createTreaty', [\AlphaDirect\Http\Controllers\PublicController::class, 'createTreaty']);

    Route::post('company', [\AlphaDirect\Http\Controllers\Admin\Companies\CompanyController::class, 'store']);
    //    Route::post('supplier', 'SupplierController@store');

    Route::get('/allCustomerPolicies/{id}', [\AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'getUserPolicies']);//->name('customerPolicies');

    Route::get('/notifications/new', [\AlphaDirect\Http\Controllers\UserController::class, 'notifications']);
    // Route::get('/allPolicies', [\AlphaDirect\Http\Controllers\AgentController::class,'getAllPolicies')->name('allPolicies');
    Route::get('/allPolicies', [\AlphaDirect\Http\Controllers\PublicController::class, 'getAllPolicies']);//->name('allPolicies');

    Route::get('/allClaims', [\AlphaDirect\Http\Controllers\Agents\Claims\ClaimsAgentController::class, 'getAllAgentClaims']);//->name('allAgentClaims');

    /*APP UPLOAD ROUTES */

    Route::post('uploadFront', [\AlphaDirect\Http\Controllers\UploadController::class, 'appUploadFrontImage']);
    Route::any('testcloudfront', [\AlphaDirect\Http\Controllers\UploadController::class, 'testcloudfront']);
    Route::post('uploadBack', [\AlphaDirect\Http\Controllers\UploadController::class, 'appUploadBackImage']);
    Route::post('uploadRight', [\AlphaDirect\Http\Controllers\UploadController::class, 'appUploadRightImage']);
    Route::post('uploadLeft', [\AlphaDirect\Http\Controllers\UploadController::class, 'appUploadLeftImage']);
    Route::post('uploadCarImages', [\AlphaDirect\Http\Controllers\UploadController::class, 'uploadCarImages']);
    Route::post('uploadKycImages', [\AlphaDirect\Http\Controllers\UploadController::class, 'uploadKycImages']);

    Route::post('uploadIncidentPhoto', [\AlphaDirect\Http\Controllers\UploadController::class, 'uploadGlassClaimIncidentPhoto']);

    //user api routes

    Route::post('register', [\AlphaDirect\Http\Controllers\UserController::class, 'register']);
    Route::post('login', [\AlphaDirect\Http\Controllers\UserController::class, 'login']);
    Route::post('supplier-email', [\AlphaDirect\Http\Controllers\UserController::class, 'sendMail']);
    Route::get('/test', [\AlphaDirect\Http\Controllers\Agents\Claims\ClaimsAgentController::class, 'testMail']);

    Route::post('/getAgentCarModel', [\AlphaDirect\Http\Controllers\AgentController::class, 'getAgentCarModel'])->name('getAgentCarModel');

    Route::get('/getMotorItems', [\AlphaDirect\Http\Controllers\VehicleController::class, 'getMotorItems']);//->name('getMotorItems');

    Route::post('/scratchUpdate', [\AlphaDirect\Http\Controllers\UserController::class, 'uploadCarImages'])->name('scratchUpdatePolicy');

    // claims

    Route::post('makeClaim', [\AlphaDirect\Http\Controllers\Customer\Claims\ClaimsController::class, 'createClaim']);

    //Vehicle make and model endpoints for app

    Route::get('/allVehicleMakes', [\AlphaDirect\Http\Controllers\VehicleController::class, 'getMakes']);
    Route::get('/allPolicyPlans', [\AlphaDirect\Http\Controllers\PublicController::class, 'getAllPolicyPlans']);

    Route::post('/allVehicleModels', [\AlphaDirect\Http\Controllers\VehicleController::class, 'allVehiclegetCarModel']);

    Route::post('/massEmail', [\AlphaDirect\Http\Controllers\EmailBroadcastingController::class, 'email']);

    Route::post('resetPassword', [\AlphaDirect\Http\Controllers\UserController::class, 'passwordReset']);
    Route::post('newPassword', [\AlphaDirect\Http\Controllers\UserController::class, 'setNewPassword']);
    Route::post('updatePassword', [\AlphaDirect\Http\Controllers\UserController::class, 'updatePassword']);

    #Policy update

    Route::post('updateBanking', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'updateBanking']);
    Route::post('store', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'store']);
    Route::post('generateActCodeApi', [\AlphaDirect\Http\Controllers\Admin\ActivationController::class, 'generateActCodeApi']);

    #Check User Omang

    Route::post('checkUserOmang', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkUserOmang'])->name('policy.checkUserOmang');

Route::any('sendPolicyDocumentOnWhatsApp/{id}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'sendPolicyDocumentOnWhatsApp']);
    #PAYGATE VCS
    Route::get('paymentVendorsData', [\AlphaDirect\Http\Controllers\Admin\PaymentVendorController::class, 'getAllVendors']);
    Route::post('vcsApp', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'graphiteVcsPayment']);
    Route::any('vcsAccepted', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vcsAccepeted']);
    Route::any('vcsDeclined', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vcsDeclined']);
    Route::any('vcsEdit', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'editTransaction']);
    Route::any('envTest', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vcsTestPayment']);
    Route::any('vcsEditTest', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'editTransactionTest']);
    Route::any('getTransactionList', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getTransactionList']);
    Route::any('getVcsTransactionListBydate', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getVcsTransactionListBydate']);
    Route::any('vcsEnvTest', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getPaymentEnv']);
    Route::any('vcsTransactionList', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'getTransactionList']);
    Route::any('vcsDelete', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'deleteTransaction']);
    Route::any('graphiteAccepted', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vscGraphiteAccepeted']);
    Route::any('graphiteDeclined', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vscGraphiteDeclined']);
    Route::any('tempGraphiteAccepted', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'tempGraphiteAccepeted']);
    Route::any('tempGraphiteDeclined', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'tempGraphiteDeclined']);
    Route::any('chatbotVcsAccepeted', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'chatbotVcsAccepeted']);
    Route::any('chatbotVcsDeclined', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'chatbotVcsDeclined']);
    Route::any('Paycancelled', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'Paycancelled']);
    Route::any('Paythankyou', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'Paythankyou']);
    Route::any('Paydeclined', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'Paydeclined']);

    #PAYGATE VCS: PAYMENT CONTROLLER FIX

    Route::any('vcsPayment', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'handlePayment']);
    Route::any('updateRecordsVCS', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'updateRecordsVCS']);
    Route::any('getVCScardDetails', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getVCScardDetails']);
    Route::any('updateCardVcs', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'updateCardVcs']);
    Route::any('VcsPaymentForPay', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'handlePaymentForPay']);
    Route::any('get_payment_status_details', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'get_payment_status_details']);
    Route::any('handlePaymentForStart', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'handlePaymentForStart']);
    Route::any('vcsActivateTrans', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'activateTransaction']);

    Route::any('vcsCheckStatus', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'checkPaymentStatus']);
    Route::any('GetVCSTransLog', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'GetVCSTransLog']);
    Route::any('vcsLeadsource', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getLeadSource']);
    Route::any('vcsAddTrans', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'addTransaction']);
    Route::any('vcsSuspend', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'suspendTransactionOnVCS']);
    Route::any('vcsUnsuspend', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'unsuspendTransactionOnVCS']);
    Route::any('vcsTransactionDetail', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getTransactionDetails']);
    Route::any('acceptedCallback', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'acceptedCallback']);
    Route::any('declinedCallback', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'declinedCallback']);
    Route::any('acceptedCallbackMonthlyVcs', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'acceptedCallbackMonthlyVcs']);
    Route::any('declinedCallbackMonthlyVcs', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'declinedCallbackMonthlyVcs']);
    Route::any('acceptedCallbackPay', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'acceptedCallbackPay']);
    Route::any('declinedCallbackPay', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'declinedCallbackPay']);
    Route::any('calculatePremiumtest', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'calculatePremium']);
    Route::any('getTransactionByReference', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getTransactionByReference']);
    Route::any('getTransactionBycardNumber', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'getTransactionByCardNumber']);
    Route::any('getTransactionByDateRange', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'GetTransactionByDateRange']);
    Route::any('testJoin', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'testJoin']);
    Route::any('restoreVCS', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'restoreVCS']);
    //Bitrix Routes
    Route::post('/getUsers', [\AlphaDirect\Http\Controllers\BitrixController::class, 'getUsers']);
    Route::post('/getDepartment', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'getDepartment']);
    #Flutterwave

    //    Route::any('flutterTestPay', 'Payment\Flutterwave\FlutterwavePayments@testHandlePayment');
    Route::any('flutterwaveAccepted', [\AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController::class, 'acceptedCallBack']);
    Route::any('flutterwaveDeclined', [\AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController::class, 'declinedCallBack']);

    #VCS:CHATBOT
    Route::any('graphiteChatbotAccepted', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vscGraphiteDeclined']);
    Route::any('graphiteChatbotDeclined', [\AlphaDirect\Http\Controllers\Payment\VCS\VcsController::class, 'vscGraphiteDeclined']);

    #Orange Payments
    Route::post('orangeMoneyInit', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'webPayIntiliazer']);
    Route::post('orangeCallBack/{policyNumber}/{amount}', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'orangeCallback']);
    Route::any('orangeAccepted/{policyNumber}', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'orangeMoneyAccepted']);
    Route::any('orangeDeclined/{policyNumber}', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'orangeMoneyDeclined']);
    Route::any('chatBotOrangeAccepted', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'chatbotOrangeMoneyAccepted']);
    Route::any('chatBotOrangeDeclined', [\AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController::class, 'chatbotOrangeMoneyDeclined']);

    #RealPay Endpoints
    Route::post('realPay', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class, 'getTransactionsReport']);
    Route::any('realpay-addClient', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class, 'addClientRealPay']);
    Route::any('realpay-addClientAlphaFePay', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class, 'addClientRealPayAlphaFePay']);
    Route::any('rpGetbanks', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class, 'getRealPayBanks']);
    Route::any('rpGetBranches', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class, 'getRealPayBranches']);
    Route::any('branchesLiveQuote', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getBranchesLiveQuote']);
    Route::any('getBanksRP', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealpayController::class, 'getBanks']);
    Route::any('getBankBranchesRP', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealpayController::class, 'getBankBranches']);
    Route::any('getInstallmentsRP', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealpayController::class, 'getInstallments']);
    Route::any('rpGetBanksForInstantProduct', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'rpGetBanksForInstantProduct']);
    Route::any('rpGetBranchesForInstantProduct', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'rpGetBranchesForInstantProduct']);
    Route::post('addRealpayPaymentForInstantProduct', [\AlphaDirect\Http\Controllers\Admin\RealPayController::class, 'addRealpayPaymentForInstantProduct']);
    Route::post('updateExpiredCardDetailsFromStart', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'updateExpiredCardDetailsFromStart']);
    Route::post('redoPaymentFromStart', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'redoPaymentFromStart']);

    #OTP
    Route::post('agentSendOtp', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'requestOTP']);
    Route::post('requestOTP', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'requestOTP']);
    Route::post('agentAuthenticateOtp', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'authenticateOTP']);
    Route::post('authenticateOTP', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'authenticateOTP']);
    Route::post('authenticate_otp', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'authenticateOTP']);
    Route::post('requestUpdatePasswordOTP', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'requestUpdatePasswordOTP']);

    #Config
    Route::any('envVariables', [\AlphaDirect\Http\Controllers\ConfigController::class, 'getEnvVariables']);
    Route::any('getTCs', [\AlphaDirect\Http\Controllers\ConfigController::class, 'fetchTermsConditions']);

    #Reconciliation Route
    Route::post('reconciliation', [\AlphaDirect\Http\Controllers\ReconciliationController::class, 'getVcsParsedData']);
    Route::post('dumpData', [\AlphaDirect\Http\Controllers\ReconciliationController::class, 'saveReportData']);

    #Mobile App
    Route::get('getAppVehicle', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getAppVehicle']);

    Route::get('insertPolicyLost', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'insertPolicyLost']);
    Route::post('setPassword', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'setPassword']);
    Route::post('saveQuote', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'storeQuoteApp']);
    Route::post('updatePasswordWithOTP', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updatePasswordWithOTP']);
    Route::post('checkPhoneNumber', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkPhoneNumber']);
    Route::post('updateMobileAppPassword', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updateMobileAppPassword']);
    Route::get('verifyActivationCode', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'verifyActivationCode']);
    Route::post('checkIdentity', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkIdentity']);
    Route::post('checkVehicle', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkVehicle']);
    Route::post('checkCustomer', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkCustomer']);
    Route::any('getCustomerCellphoneByPolicyNumber', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerCellphoneByPolicyNumber']);
    Route::any('getCustomerCellphoneByVehicleNumber', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerCellphoneByVehicleNumber']);
    Route::post('checkBluebook', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkBluebook']);
    Route::post('mobileAppLogin', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'mobileAppLogin']);
    Route::post('checkKYCCompliance', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'checkKYCCompliance']);
    Route::post('requestGlassClaim', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'requestGlassClaim']);
    Route::post('requestThirdPartyAccidentClaim', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'requestThirdPartyAccidentClaim']);
    Route::post('requestLifeClaim', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'requestLifeClaim']);
    Route::post('uploadImage', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'uploadImage']);
    Route::post('deleteCellphoneDevicesApi', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'deleteCellphoneDevicesApi']);
    Route::post('mobileAppErrorLog', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'insertErrorLogAPI']);
    Route::post('getQuoteData', [AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getQuoteData'])->name('getQuoteData');


    #Mobile App - Agent
    Route::get('verifyActivationCode', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'verifyActivationCode']);
    // Route::post('mobileAppLogin', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class,'mobileAppLogin');
    Route::post('agentactivatePolicyFirstTimeUser', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'activatePolicyFirstTimeUser']);
    Route::post('activatePolicyFirstTimeUserPay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'activatePolicyFirstTimeUserPay']);
    Route::post('appUserLifePolicy', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'appUserLifePolicy']);
    Route::post('agentAppUploadInspection', [\AlphaDirect\Http\Controllers\UploadController::class, 'agentAppUploadPreInspection']);
    Route::post('agentAppUploadKYC', [\AlphaDirect\Http\Controllers\UploadController::class, 'agentAppUploadKyc']);
    Route::post('customerUploadKyc', [\AlphaDirect\Http\Controllers\UploadController::class, 'customerUploadKyc']);
    Route::post('uploadCustomerKycDoc', [\AlphaDirect\Http\Controllers\UploadController::class, 'uploadCustomerKycDoc']);
    Route::post('getCustomerInfo', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerInfo']);
    Route::post('getAgentsPolicy', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getAgentPolicies']);
    Route::post('findPolicy', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'search']);
    Route::post('findPolicy2', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'searchTwo']);
    Route::post('findPolicyAlphaFePay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'findPolicyAlphaFePay']);
    Route::post('removeBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'removeBeneficiary']);
    Route::post('createBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'createBeneficiary']);
    Route::post('policyDetails', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getPolicyDetails']);
    Route::post('getPolicyTransactions', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getPolicyTransactions']);
    Route::post('updateCustomerDetails', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updatePolicyInformation']);
    Route::post('updatePolicyInformationPay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updatePolicyInformationPay']);
    Route::any('getStoreBranches', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getOutletStores']);
    Route::any('saveLead', [\AlphaDirect\Http\Controllers\LeadsController::class, 'store']);
    Route::any('getBillingDays', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getBillingDaysForProducts']);
    Route::any('getCustomerCellphoneById', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCustomerCellphoneById']);
    Route::any('getDocTye', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getDocTye']);
    Route::any('getLookupData', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getLookupData']);
    Route::any('isLive', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'isLive']);
    Route::post('uploadDeviceImages', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'uploadDeviceImages']);
    Route::post('getDevicesByPolicyNo', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getDevicesByPolicyNo']);
    Route::post('updateDevices', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updateDevices']);
    Route::post('removeDevices', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'removeDevices']);
    Route::post('createDevices', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'createDevices']);
    Route::post('getActivationCode', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getActivationCode']);
    #Logins
    Route::post('agentLogin', [\AlphaDirect\Http\Controllers\AgentController::class, 'agentLogin']);
    Route::post('logTshosoloAgentLogin', [\AlphaDirect\Http\Controllers\AgentController::class, 'logTshosoloAgentLogin']);

    #Beneficiary and Family CRUD
    Route::post('addPolicyFamilyMembers', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'addPolicyFamilyMembers']);
    Route::post('addPolicyBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'addPolicyBeneficiary']);
    Route::post('updateBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updateBeneficiary']);
    Route::post('updateFamilyMember', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updateFamilyMember']);
    Route::post('deleteBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'deleteBeneficiary']);
    Route::post('deleteFamilyMember', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'deleteFamilyMember']);
    Route::post('file-upload', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'fileupload']);

    Route::post('manageAccount', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'manageAccount']);

    Route::post('newDynamicPolicyRequest', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'newDynamicPolicyRequest']);
    Route::post('getProductFactors', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getProductFactors']);

    #Customer Portal requetsgrap
    Route::post('findPolicyNumber', [\AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'findPolicyNumber']);
    Route::post('verifyCustomerPortalOTP', [\AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'verifyCustomerPortalOTP']);
    Route::post('setCustomerPortalPassword', [\AlphaDirect\Http\Controllers\CustomerPortal\CustomerPortalController::class, 'setCustomerPortalPassword']);

    #USSD end points
    Route::any('getActivationCodeData', [\AlphaDirect\Http\Controllers\USSD\ussd::class]);
    Route::any('getActivationCodeDataPay', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'getActivationCodeDataPay']);
    Route::any('getActivationCodeDataPay2', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'getActivationCodeDataPay2']);
    Route::any('getActivationCodeDataUssd', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'getActivationCodeDataUssd']);
    Route::any('getUSSDProductFactors', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'getUSSDProductFactors']);

    Route::post('testCall', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'testCall']);
    Route::post('ussdPolicy', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'ussdPolicy']);
    Route::post('/processPayment/{id}', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'processPayment']);
    Route::get('/loadPaymentForm/{id}', [\AlphaDirect\Http\Controllers\USSD\ussd::class, 'loadPaymentForm']);

    #Alpha Fe
    //alphafe
    Route::get('getAlphaFeBanks', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getAlphaFeBanks']);
    Route::get('getStartProducts', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getStartProducts']);
    Route::get('startGenerateActivationCode', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'startGenerateActivationCode']);
    Route::get('getAlphaFeVendors', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getAlphaFeVendors']);
    Route::post('sendAlphaFeSMS', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'sendAlphaFeSMS']);

    //alphafe get products,plans. activation code,check if it exists,status
    Route::post('getAlphaFeProductFactorsPlans', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getAlphaFeProductFactorsPlans']);
    Route::post('checkIfAlphaFEActivationCodeExists', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'checkIfAlphaFEActivationCodeExists']);
    Route::post('getAlphaFEActivationCodeData', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getAlphaFEActivationCodeData']);
    Route::post('getAlphaFeActivationCodePlans', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getAlphaFeActivationCodePlans']);
    Route::post('checkAlphaFEActivationCodeStatus', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'checkAlphaFEActivationCodeStatus']);
    Route::post('getAlphaFeProductFactorsPlans', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getAlphaFeProductFactorsPlans']);

    Route::get('getCounries', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getCounries']);
    Route::any('/admin/getInstallments', [\AlphaDirect\Http\Controllers\Admin\AccountsController::class, 'getInstallments']);

    //alpha-fe save policy, confirm policy
    Route::post('saveStartPolicy', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'saveStartPolicy']);
    Route::post('confirmStartPolicy/{id}', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'confirmStartPolicy']);
    Route::post('getStartPolicySummary', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getStartPolicySummary']);

    Route::post('getPagePaymentFormData', [\AlphaDirect\Http\Controllers\AlphaFe\alphaFe::class, 'getPagePaymentFormData']);

    Route::post('/rpGetBranches', [\AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController::class, 'getRealPayBranches']);

    #chatbot
    Route::post('getActivation', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'getActivation']);
    Route::post('savePolicyChatbot', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'store']);
    Route::get('getPurpose', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'getPurposes']);
    Route::post('checkOmang', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'checkUserOmang']);
    Route::post('checkOmangExists', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'checkOmang']);
    Route::post('checkUserLifePolicy', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'checkUserLifePolicy']);
    Route::post('checkActivation', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'checkActivation']);
    Route::post('checkUserVehicle', [\AlphaDirect\Http\Controllers\ChatBot\ChatBotController::class, 'checkUserVehicle']);
    Route::post('checkVehiclePlate', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'checkVehicleExist']);

    #Graphite Payment

    Route::get('graphitePaymentForm/{id}/{amount}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'graphitePaymentForm']);
    Route::get('getPolicyBalance/{policyId}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getPolicyBalance']);
    Route::post('sendPaymentUrlGraphite', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'sendPaymentUrlGraphite']);

    Route::get('viewUrl', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'viewUrl']);

    #Activation Temp
    Route::post('checkUserPassport-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkUserPassport'])->name('policy.checkUserPassport');
    Route::post('checkUserVehicle-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkUserVehicle'])->name('policy.checkUserVehicle');
    Route::post('generateActivationCode-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'generateActivationCode'])->name('policy.generateActivationCode');
    Route::post('checkUserOmang-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkUserOmang'])->name('policy.checkUserOmangValid');
    Route::post('checkMakeModel-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkMakeModel'])->name('policy.checkMakeModel');
    Route::post('checkActivation-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkActivation'])->name('policy.checkActivation');
    Route::post('product_factors-Api', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getProductFactors'])->name('policy.product_factors');
    Route::post('activationTempStore', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'tempStore']);
    // mati
    Route::post('MatiVerificationLink/{type}', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'MatiVerificationLink']);

    Route::get('customerKycData', [\AlphaDirect\Http\Controllers\Admin\CustomerKycController::class, 'kycData'])->name('customerKycData');

    #Policy Wording
    Route::get('tc_agentApp', [\AlphaDirect\Http\Controllers\ConfigController::class, 'getWordings']);
    Route::get('/getLeadsReviewQuestions', [\AlphaDirect\Http\Controllers\Admin\ReviewController::class, 'getLeadsReviewQuestions'])->name('getLeadsReviewQuestions');
    Route::get('/getPolicyReviewQuestions', [\AlphaDirect\Http\Controllers\Admin\ReviewController::class, 'getPolicyReviewQuestions'])->name('getPolicyReviewQuestions');
});
Route::post('getbitrixAgents', [\AlphaDirect\Http\Controllers\BitrixAgentController::class, 'getbitrixAgents']);
Route::get('sendSMSData', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'sendSMSData']);
Route::post('testSMs', [\AlphaDirect\Http\Controllers\SmsMessaging::class, 'testInfobibSMS']);
Route::post('storeResponse', [\AlphaDirect\Http\Controllers\Admin\AccountsController::class, 'testEMail']);
Route::any('/start/deleteKyc', [\AlphaDirect\Http\Controllers\Start\StartController::class, 'deleteKyc']);
Route::any('vcsRedoPayment', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'redoPayment']);
Route::any('vcsRedoPaymentForStart', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'redoPaymentForStart']);
Route::any('vcsRedoPaymentMotorCompForStart', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'redoPaymentMotorCompForStart']);

Route::any('checkStatusForPolicy', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'checkStatusForPolicy']);

Route::any('checkPolicyStatus', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'checkPolicyStatus']);
Route::any('checkPolicyStatusMotorComp', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'checkPolicyStatusMotorComp']);
Route::any('vcsOnceOffForStart', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'vcsOnceOffForStart']);
Route::any('updateContractVCS', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'updateContractVCS']);
Route::any('vcsRedoPaymentForQuote', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'redoPaymentForLiveQuote']);
Route::any('redoPaymentForMobileAppComprehensive', [\AlphaDirect\Http\Controllers\Payment\VCS\PaymentController::class, 'redoPaymentForMobileAppComprehensive']);

Route::post('getmati-webhook', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'getmatiWebhook']);
Route::post('verifyKycAdv', [\AlphaDirect\Http\Controllers\Admin\CustomerController::class, 'verifyKycAdv']);
//  customer feedback option
Route::get('/customerFeedback/options', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackOptionController::class, 'data']);
Route::get('/customerFeedback/sub-options', [\AlphaDirect\Http\Controllers\Admin\CustomerFeedbackSubOptionController::class, 'data']);

Route::post('/testCronDocument', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'testCronDocument']);
Route::any('/getPolicyDocuments', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'getPolicyDocuments']);
Route::any('/policyWithoutBeneficiary', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'policyWithoutBeneficiary']);
Route::any('/policyWithPaymentFailed', [\AlphaDirect\Http\Controllers\Admin\DocumentController::class, 'policyWithPaymentFailed']);
Route::any('getToken', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getToken']);
Route::any('getInsRP', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'getInstalmentsRealpay']);
Route::post('/getGlobalOtp', [\AlphaDirect\Http\Controllers\Admin\OtpTempController::class, 'getGlobalOtp'])->name('getGlobalOtp');

Route::get('/frontendpay/vehicleInfo2', [\AlphaDirect\Http\Controllers\FrontendPay\ClaimController::class, 'getvehicleInfo2']);
Route::post('/policy/customer', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class, 'getCustomer']);
Route::post('createSchPayForOrange', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'createSchPayForOrange'])->name('createSchPayForOrange');

Route::prefix('v2')->group(function () {
    Route::any('/activatePolicyFirstTimeUserAlphaFe', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'activatePolicyFirstTimeUserAlphaFe']);
    Route::post('/newPolicyRequest', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'newPolicyRequest']);
    Route::post('/getProductPlans', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getProductPlans']);
    Route::post('/getProductsMobile', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getProducts']);
    Route::post('/getProductsForStart', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getProductsForStart']);
    Route::post('/preInspectionPhotos', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'preInspectionPhotos']);
    Route::post('/customerPolicies', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getUserApplications']);
    Route::post('/generateActivationCodeAlphaFePay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'generateActivationCodeAlphaFePay']);
    Route::post('/retrieveActivationCodeAlphaFePay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'retrieveActivationCodeAlphaFePay']);
    Route::post('/getCustomerID', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCustomerID']);
    Route::post('/getCustomerDocument', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCustomerDocument']);
    Route::post('/addOption', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'addOption']);

    Route::get('getCounries', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCounries']);

    #Mobile App
    Route::get('getAppVehicle', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getAppVehicle']);

    Route::get('insertPolicyLost', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'insertPolicyLost']);
    Route::post('setPassword', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'setPassword']);
    Route::post('saveQuote', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'storeQuoteApp']);
    Route::post('updatePasswordWithOTP', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updatePasswordWithOTP']);
    Route::post('checkPhoneNumber', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'checkPhoneNumber']);
    Route::post('updateMobileAppPassword', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updateMobileAppPassword']);
    Route::get('verifyActivationCode', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'verifyActivationCode']);
    Route::post('checkIdentity', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'checkIdentity']);
    Route::post('checkVehicle', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'checkVehicle']);
    Route::post('checkCustomer', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'checkCustomer']);
    Route::any('getCustomerCellphoneByPolicyNumber', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCustomerCellphoneByPolicyNumber']);
    Route::any('getCustomerCellphoneByVehicleNumber', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCustomerCellphoneByVehicleNumber']);
    Route::post('checkBluebook', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'checkBluebook']);
    Route::post('mobileAppLogin', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'mobileAppLogin']);
    Route::post('checkKYCCompliance', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'checkKYCCompliance']);
    Route::post('requestGlassClaim', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'requestGlassClaim']);
    Route::post('requestThirdPartyAccidentClaim', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'requestThirdPartyAccidentClaim']);
    Route::post('requestLifeClaim', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'requestLifeClaim']);
    Route::post('uploadImage', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'uploadImage']);

    #Mobile App - Agent
    Route::get('verifyActivationCode', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'verifyActivationCode']);
    Route::post('agentactivatePolicyFirstTimeUser', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'activatePolicyFirstTimeUser']);
    Route::post('activatePolicyFirstTimeUserPay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'activatePolicyFirstTimeUserPay']);
    Route::post('appUserLifePolicy', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'appUserLifePolicy']);
    Route::post('getCustomerInfo', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCustomerInfo']);
    Route::post('getAgentsPolicy', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getAgentPolicies']);
    Route::post('findPolicy', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'search']);
    Route::post('findPolicyAlphaFePay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'findPolicyAlphaFePay']);
    Route::post('removeBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'removeBeneficiary']);
    Route::post('createBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'createBeneficiary']);
    Route::post('policyDetails', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getPolicyDetails']);
    Route::post('getPolicyTransactions', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getPolicyTransactions']);
    Route::post('updateCustomerDetails', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updatePolicyInformation']);
    Route::post('updatePolicyInformationPay', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updatePolicyInformationPay']);
    Route::any('getStoreBranches', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getOutletStores']);
    Route::any('getBillingDays', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getBillingDaysForProducts']);
    Route::any('getCustomerCellphoneById', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getCustomerCellphoneById']);
    Route::any('getDocTye', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getDocTye']);
    Route::any('getLookupData', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getLookupData']);
    Route::any('isLive', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'isLive']);
    Route::post('uploadDeviceImages', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'uploadDeviceImages']);
    Route::post('getDevicesByPolicyNo', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getDevicesByPolicyNo']);
    Route::post('updateDevices', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updateDevices']);
    Route::post('removeDevices', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'removeDevices']);
    Route::post('createDevices', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'createDevices']);
    Route::post('getActivationCode', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getActivationCode']);

    #Beneficiary and Family CRUD
    Route::post('addPolicyFamilyMembers', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'addPolicyFamilyMembers']);
    Route::post('addPolicyBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'addPolicyBeneficiary']);
    Route::post('updateBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updateBeneficiary']);
    Route::post('updateFamilyMember', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'updateFamilyMember']);
    Route::post('deleteBeneficiary', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'deleteBeneficiary']);
    Route::post('deleteFamilyMember', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'deleteFamilyMember']);
    Route::post('file-upload', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'fileupload']);

    Route::post('manageAccount', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'manageAccount']);

    Route::post('newDynamicPolicyRequest', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'newDynamicPolicyRequest']);
    Route::post('getProductFactors', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppV2Controller::class, 'getProductFactors']);


});

#flutterwave
Route::post('flutter_pay', [\AlphaDirect\Http\Controllers\FlutterwavePaymentController::class, 'pay']);
Route::any('flutter_process', [\AlphaDirect\Http\Controllers\FlutterwavePaymentController::class, 'process']);

#N-Genius Payment Gateway
Route::post('NgeniusPayment', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusPayment']);
Route::post('RequestAccessToken', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'RequestAccessToken']);
Route::any('NgeniusResponse', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusResponse']);
Route::any('NgeniusResponseRecurringDirect', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusResponseRecurringDirect']);
Route::any('NgeniusCancelResponse', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusCancelResponse']);
Route::any('NgeniusRecurring', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusRecurring']);
Route::post('NgeniusRecurringGetdata', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusRecurringGetdata']);
//Route::any('NgeniusRecurringDeletedata', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusRecurringDeletedata']);
//Route::post('NgeniusRecurringDeletedata3', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'NgeniusRecurringDeletedata3']);
Route::any('ngeniuswebhook', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'ngeniuswebhook']);

Route::any('whatsapp/sendMessage1', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'sendMessage1']);

#pin

Route::any('agentPinstatus', [AlphaDirect\Http\Controllers\Admin\QuoteController::class, 'agentPinstatus'])->name('agentPinstatus');

Route::post('renewMotorCompExpiredPolicy', [\AlphaDirect\Http\Controllers\Admin\PolicyController::class, 'renewMotorCompExpiredPolicy']);
// Upgrade Policy
Route::post('AdiToAdiGold', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class,'upgradeAdiToAgiGold']);
Route::post('TpToTpGold', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class,'upgradeTpToTpGold']);
// Route::get('RegisterAgent', [\AlphaDirect\Http\Controllers\ArjunIosApiCrontroller::class,'RegisterAgent']); // Disabled: controller doesn't exist
Route::post('registerAgent', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class,'registerAgentsApi']);
Route::post('/sales', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class, 'apiSalePolicy']);
Route::post('/paymentConfirmation', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class, 'apiPaymentConfirmation']);
Route::post('/recurringPremiumPayment', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class, 'apiRecurringPremiumPayment']);
Route::post('/kyc-status', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class, 'kycStatusApi']);
Route::post('register', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class,'registerCustomerFromApi']);
// Route::get('RegisterCustomer', [\AlphaDirect\Http\Controllers\ArjunIosApiCrontroller::class,'RegisterCustomer']); // Disabled: controller doesn't exist
Route::get('SalePolicy', [\AlphaDirect\Http\Controllers\LlmApiCrontroller::class,'SalePolicy']);
Route::get('ngenius_get_trxn', [\AlphaDirect\Http\Controllers\NgeniusPaymentController::class, 'ngenius_get_trxn']);
Route::post('cellphoneupgrade', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class,'cellphoneUpgrade']);
Route::post('testzz', [\AlphaDirect\Http\Controllers\FrontendPay\CustomerController::class,'test']);
Route::get('whatsapp/send', [\AlphaDirect\Http\Controllers\WhatsAppController::class, 'sendMetaData']);
Route::get('generateInstallments/{date}/{in}/{amount}', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'generateInstallments']);
Route::get('generatePayload/{policyId}', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'generatePayload']);
Route::get('createAdHocPayment/{policyId}', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'createAdHocPayment']);
Route::get('/merchantBranches', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'merchantBranches']);
Route::get('/paym8Banks', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'paym8Banks']);
Route::get('fetchPayM8Installments/{id}/{policyNumber}', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'fetchPayM8Installments']);
Route::get('paymGetBanks', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'getBankList']);
Route::post('PaymredoPaymentFromStart', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'redoPaymentFromStart']);
Route::any('/paym/webhook', [\AlphaDirect\Http\Controllers\PayM8Controller::class, 'webhook']);


Route::post('/healthinfo', [HealthInfoController::class, 'store']);

# Hospital Cashback Product
Route::post('deleteCoapplicant', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'deleteCoapplicant']);
Route::post('getHospitalCashbackPremium', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'getHospitalCashbackPremium']);
// Route::post('updateCoapplicant', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'updateCoapplicant']);
// Route::post('addPolicyCoapplicant', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'addPolicyCoapplicant']);
Route::post('/savePolicyCoapplicants', [\AlphaDirect\Http\Controllers\MobileApp\MobileAppController::class, 'savePolicyCoapplicants']);

// Admin
Route::get('/admin/rewards/{reward}/info', [\AlphaDirect\Http\Controllers\Admin\RewardController::class, 'info']);
Route::post('/admin/rewards/{reward}/claim', [\AlphaDirect\Http\Controllers\Admin\RewardController::class, 'claim']);
Route::get('/admin/rewards/analytics', [\AlphaDirect\Http\Controllers\Admin\RewardController::class, 'analytics']);

// Customer
Route::prefix('customers/{customer}')->group(function () {
    Route::get('rewards/all', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'all']);
    Route::get('rewards/active', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'active']);
    Route::get('rewards/inactive', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'inactive']);
    Route::get('rewards/history', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'history']);
});

// Rewards and Tier API endpoints
Route::get('customer/{customer}/rewards', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'rewards']);
Route::get('customer/{customer}/tier', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'tier']);
Route::get('customer/{customer}/points-summary', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'pointsSummary']);
Route::post('customer/{customerId}/benefit/{benefitId}/claims', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'claims']);
Route::post('customer/{customerId}/benefit/{benefitId}/add-to-cart', [\AlphaDirect\Http\Controllers\CustomerRewardController::class, 'addToCart']);
Route::get('getallwording', [\AlphaDirect\Http\Controllers\Frontend\FrontendController::class, 'getAllWording']);


Route::post('/calculate-premium', [PricingController::class, 'calculatePremium']);
Route::post('/kyc-chunk-upload', [KycUploadController::class, 'upload']);

# Bank Statement Upload API endpoints
Route::prefix('bank-statement')->group(function () {
    // Access and verification endpoints
    Route::get('/access/{token}', [\AlphaDirect\Http\Controllers\BankStatementController::class, 'accessLink']);
    Route::post('/send-otp', [\AlphaDirect\Http\Controllers\BankStatementController::class, 'sendOtp']);
    Route::post('/verify-otp/{token}', [\AlphaDirect\Http\Controllers\BankStatementController::class, 'verifyOtp']);
    
    // Upload requirements and processing
    Route::get('/upload-requirements/{token}', [\AlphaDirect\Http\Controllers\BankStatementController::class, 'getUploadRequirements']);
    Route::post('/upload/{token}', [\AlphaDirect\Http\Controllers\BankStatementController::class, 'uploadBankStatement']);
    
    // Completion status
    Route::get('/completion-status/{token}', [\AlphaDirect\Http\Controllers\BankStatementController::class, 'getCompletionStatus']);
    
    // Admin management endpoints
    Route::prefix('admin')->group(function () {
        Route::post('/generate-links', [\AlphaDirect\Http\Controllers\BankStatementLinkController::class, 'generateLinks']);
        Route::get('/stats', [\AlphaDirect\Http\Controllers\BankStatementLinkController::class, 'getStats']);
        Route::get('/requests', [\AlphaDirect\Http\Controllers\BankStatementLinkController::class, 'getRequests']);
        Route::put('/requests/{id}/verification', [\AlphaDirect\Http\Controllers\BankStatementLinkController::class, 'updateVerificationStatus']);
    });
});

# Bank Statement Upload Frontend Routes
Route::get('/bankstatement/access/{token}', function($token) {
    return view('bank-statement.upload', compact('token'));
});

# Bank Statement Upload Admin Routes
Route::prefix('admin/deduplication-checks')->group(function () {
    Route::get('/', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'index'])->name('admin.deduplication-checks.index');
    Route::get('/{id}', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'show'])->name('admin.deduplication-checks.show');
    Route::post('/datatable', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'getDataTableData'])->name('admin.deduplication-checks.datatable');
    Route::post('/generate-links', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'generateLinks'])->name('admin.deduplication-checks.generate-links');
    Route::put('/{id}/verification', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'updateVerificationStatus'])->name('admin.deduplication-checks.update-verification');
    Route::post('/resend-notification', [\AlphaDirect\Http\Controllers\Admin\DeduplicationChecksAdminController::class, 'resendNotification'])->name('admin.deduplication-checks.resend-notification');
});

# Re-KYC Service API endpoints
Route::prefix('rekyc')->group(function () {
    // Campaign management
    Route::post('/campaigns', [\AlphaDirect\Http\Controllers\RekycController::class, 'createCampaign']);
    Route::get('/campaigns/{id}/stats', [\AlphaDirect\Http\Controllers\RekycController::class, 'getCampaignStats']);
    
    // Link generation and management
    Route::post('/links/generate', [\AlphaDirect\Http\Controllers\RekycController::class, 'generateLinks']);
    
    // Customer access endpoints
    Route::get('/access/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'accessLink']);
    Route::any('send-otp', [\AlphaDirect\Http\Controllers\RekycController::class, 'sendOtp']);

    Route::post('/verify-otp/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'verifyOtp']);
    Route::post('/submit/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'submitRekycData']);
    Route::get('customer-data/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'getCustomerData']);
    Route::get('required-documents/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'getRequiredDocuments']);
    Route::post('upload-documents/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'uploadDocuments']);
    Route::post('save-kyc/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'saveKyc']);
    Route::post('skip-documents/{token}', [\AlphaDirect\Http\Controllers\RekycController::class, 'skipDocuments']);
    
    
    // Audit and compliance endpoints
    Route::prefix('audit')->group(function () {
        Route::get('/link/{linkId}', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'getLinkAuditTrail']);
        Route::get('/customer/{customerId}', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'getCustomerAuditTrail']);
        Route::get('/security-events', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'getSecurityEvents']);
        Route::get('/compliance-violations', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'getComplianceViolations']);
        Route::get('/compliance-report', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'generateComplianceReport']);
        Route::get('/statistics', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'getAuditStatistics']);
        Route::post('/archive', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'archiveOldLogs']);
        Route::post('/export', [\AlphaDirect\Http\Controllers\RekycAuditController::class, 'exportAuditData']);
    });
    
    // Notification endpoints
    Route::prefix('notifications')->group(function () {
        Route::post('/send/{linkId}', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'sendLink']);
        Route::post('/bulk-send', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'sendBulkNotifications']);
        Route::post('/reminders', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'sendReminders']);
        Route::post('/escalations', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'sendEscalations']);
        Route::get('/statistics', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'getStatistics']);
        Route::post('/test', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'testNotification']);
        Route::post('/completion/{linkId}', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'sendCompletionNotification']);
        Route::get('/history/{linkId}', [\AlphaDirect\Http\Controllers\RekycNotificationController::class, 'getNotificationHistory']);
    });
    
    // Document endpoints
    Route::prefix('documents')->group(function () {
        Route::post('/upload', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'uploadDocument']);
        Route::get('/link/{linkId}', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'getDocuments']);
        Route::get('/{documentId}', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'getDocument']);
        Route::post('/{documentId}/verify', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'verifyDocument']);
        Route::delete('/{documentId}', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'deleteDocument']);
        Route::post('/{documentId}/validate-ocr', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'validateOCRData']);
        Route::get('/statistics', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'getStatistics']);
        Route::get('/{documentId}/download', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'downloadDocument']);
        Route::get('/{documentId}/thumbnail', [\AlphaDirect\Http\Controllers\RekycDocumentController::class, 'getThumbnail']);
    });
    
    // Compliance endpoints
    Route::prefix('compliance')->group(function () {
        Route::get('/dashboard', [\AlphaDirect\Http\Controllers\RekycComplianceController::class, 'getDashboardOverview']);
        Route::get('/campaign-metrics', [\AlphaDirect\Http\Controllers\RekycComplianceController::class, 'getCampaignMetrics']);
        Route::get('/violations', [\AlphaDirect\Http\Controllers\RekycComplianceController::class, 'getComplianceViolations']);
        Route::get('/data-protection', [\AlphaDirect\Http\Controllers\RekycComplianceController::class, 'getDataProtectionMetrics']);
        Route::post('/report', [\AlphaDirect\Http\Controllers\RekycComplianceController::class, 'generateComplianceReport']);
        Route::get('/audit-trail', [\AlphaDirect\Http\Controllers\RekycComplianceController::class, 'getAuditTrail']);
    });
    
    // DPA 2018 compliance endpoints
    Route::prefix('dpa')->group(function () {
        Route::post('/data-subject-request', [\AlphaDirect\Http\Controllers\RekycDpaController::class, 'processDataSubjectRequest']);
        Route::get('/rights', [\AlphaDirect\Http\Controllers\RekycDpaController::class, 'getDataSubjectRights']);
        Route::get('/privacy-notice', [\AlphaDirect\Http\Controllers\RekycDpaController::class, 'getPrivacyNotice']);
        Route::get('/consent-management', [\AlphaDirect\Http\Controllers\RekycDpaController::class, 'getConsentManagement']);
        Route::post('/withdraw-consent', [\AlphaDirect\Http\Controllers\RekycDpaController::class, 'withdrawConsent']);
    });
});

// AD Group KYC API Routes
Route::prefix('adgroupkyc')->group(function () {
    // Campaign management
    Route::post('/campaigns', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'createCampaign']);
    Route::get('/campaigns/{id}/stats', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'getCampaignStats']);
    
    // Link generation and management
    Route::post('/links/generate', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'generateLinks']);
    
    // Customer access endpoints
    Route::get('/access/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'accessLink']);
    Route::any('send-otp', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'sendOtp']);

    Route::post('/verify-otp/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'verifyOtp']);
    Route::post('/submit/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'submitAdGroupKycData']);
    Route::get('customer-data/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'getCustomerData']);
    Route::get('required-documents/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'getRequiredDocuments']);
    Route::post('upload-documents/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'uploadDocuments']);
    Route::post('save-kyc/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'saveKyc']);
    Route::post('skip-documents/{token}', [\AlphaDirect\Http\Controllers\AdGroupKycController::class, 'skipDocuments']);
    
    // Audit and compliance endpoints (disabled — AdGroupKycAuditService not yet implemented)
    // Route::prefix('audit')->group(function () {
    //     Route::get('/link/{linkId}', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'getLinkAuditTrail']);
    //     Route::get('/customer/{customerId}', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'getCustomerAuditTrail']);
    //     Route::get('/security-events', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'getSecurityEvents']);
    //     Route::get('/compliance-violations', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'getComplianceViolations']);
    //     Route::get('/compliance-report', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'generateComplianceReport']);
    //     Route::get('/statistics', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'getAuditStatistics']);
    //     Route::post('/archive', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'archiveOldLogs']);
    //     Route::post('/export', [\AlphaDirect\Http\Controllers\AdGroupKycAuditController::class, 'exportAuditData']);
    // });
    
    // Notification endpoints
    Route::prefix('notifications')->group(function () {
        Route::post('/send/{linkId}', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'sendLink']);
        Route::post('/bulk-send', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'sendBulkNotifications']);
        Route::post('/reminders', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'sendReminders']);
        Route::post('/escalations', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'sendEscalations']);
        Route::get('/statistics', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'getStatistics']);
        Route::post('/test', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'testNotification']);
        Route::post('/completion/{linkId}', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'sendCompletionNotification']);
        Route::get('/history/{linkId}', [\AlphaDirect\Http\Controllers\AdGroupKycNotificationController::class, 'getNotificationHistory']);
    });
    
    // Document endpoints
    Route::prefix('documents')->group(function () {
        Route::post('/upload', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'uploadDocument']);
        Route::get('/link/{linkId}', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'getDocuments']);
        Route::get('/{documentId}', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'getDocument']);
        Route::post('/{documentId}/verify', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'verifyDocument']);
        Route::delete('/{documentId}', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'deleteDocument']);
        Route::post('/{documentId}/validate-ocr', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'validateOCRData']);
        Route::get('/statistics', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'getStatistics']);
        Route::get('/{documentId}/download', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'downloadDocument']);
        Route::get('/{documentId}/thumbnail', [\AlphaDirect\Http\Controllers\AdGroupKycDocumentController::class, 'getThumbnail']);
    });
    
    // Compliance endpoints
    Route::prefix('compliance')->group(function () {
        Route::get('/dashboard', [\AlphaDirect\Http\Controllers\AdGroupKycComplianceController::class, 'getDashboardOverview']);
        Route::get('/campaign-metrics', [\AlphaDirect\Http\Controllers\AdGroupKycComplianceController::class, 'getCampaignMetrics']);
        Route::get('/violations', [\AlphaDirect\Http\Controllers\AdGroupKycComplianceController::class, 'getComplianceViolations']);
        Route::get('/data-protection', [\AlphaDirect\Http\Controllers\AdGroupKycComplianceController::class, 'getDataProtectionMetrics']);
        Route::post('/report', [\AlphaDirect\Http\Controllers\AdGroupKycComplianceController::class, 'generateComplianceReport']);
        Route::get('/audit-trail', [\AlphaDirect\Http\Controllers\AdGroupKycComplianceController::class, 'getAuditTrail']);
    });
    
    // DPA 2018 compliance endpoints
    Route::prefix('dpa')->group(function () {
        Route::post('/data-subject-request', [\AlphaDirect\Http\Controllers\AdGroupKycDpaController::class, 'processDataSubjectRequest']);
        Route::get('/rights', [\AlphaDirect\Http\Controllers\AdGroupKycDpaController::class, 'getDataSubjectRights']);
        Route::get('/privacy-notice', [\AlphaDirect\Http\Controllers\AdGroupKycDpaController::class, 'getPrivacyNotice']);
        Route::get('/consent-management', [\AlphaDirect\Http\Controllers\AdGroupKycDpaController::class, 'getConsentManagement']);
        Route::post('/withdraw-consent', [\AlphaDirect\Http\Controllers\AdGroupKycDpaController::class, 'withdrawConsent']);
    });
});

# Bundle Product API endpoints
Route::get('bundled-products/getProducts', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'getProducts']);
#Route::get('bundled-products/{id}', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'getProductById']);
Route::get('bundled-products/type/{type}', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'getProductsByType']);
Route::post('bundled-products/validate', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'validateBundledProducts']);
Route::post('bundled-products/validate-request', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'validateBundleProductRequest']);
Route::post('bundled-products/validate-premiums', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'validateBundledProductPremiums']);
Route::post('bundled-products/validate-premiums-with-discounts', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'validateBundledProductPremiumsWithDiscounts']);
Route::post('bundled-products/create', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'createBundledPolicy'])->middleware('throttle:5,1');

# Bundle Settings API endpoints
Route::get('bundled-products/settings', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'getBundleSettings']);
Route::get('bundled-products/discount/{productCount}', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'calculateBundleDiscount']);



Route::post('/opensanctions/match', [PricingController::class, 'match']);
# Company Names API endpoints
Route::get('bundled-products/company-names', [\AlphaDirect\Http\Controllers\Api\BundleProductController::class, 'getActiveCompanyNames']);

# Deduplication Check API endpoints
Route::prefix('deduplication')->group(function () {
    Route::post('/create', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'create']);
    Route::get('/access/{token}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'access']);
    Route::post('/send-otp/{token}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'sendOtp']);
    Route::post('/verify-otp/{token}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'verifyOtp']);
    Route::post('/upload-bank-statement/{token}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'uploadBankStatement']);
    Route::get('/status/{token}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'getStatus']);
    
    # Admin endpoints
    Route::get('/admin/list', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'index']);
    Route::post('/admin/approve/{id}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'approveVerification']);
    Route::post('/admin/reject/{id}', [\AlphaDirect\Http\Controllers\Api\DeduplicationController::class, 'rejectVerification']);
});

# Employer Group Applications API endpoints (for structured data)
Route::prefix('employer-group-applications')->group(function () {
    Route::post('/', [\AlphaDirect\Http\Controllers\Api\EmployerGroupApiController::class, 'store']);
    Route::get('/', [\AlphaDirect\Http\Controllers\Api\EmployerGroupApiController::class, 'index']);
    Route::get('/{id}', [\AlphaDirect\Http\Controllers\Api\EmployerGroupApiController::class, 'show']);
});

# Employer Group API endpoints
Route::prefix('employer-group')->group(function () {
    Route::post('/send-otp', [\AlphaDirect\Http\Controllers\Api\EmployerGroupApiController::class, 'sendOtp']);
    Route::post('/verify-otp', [\AlphaDirect\Http\Controllers\Api\EmployerGroupApiController::class, 'verifyOtp']);
});
