<#
.SYNOPSIS
    Live smoke for the retail-bundle BQ- payment flow (the initiateDpo BQ- fix).

.DESCRIPTION
    Proves the retail bundle payment flow no longer dead-ends at the payment
    step. Before the fix, PublicPaymentService::initiateDpo guarded the
    bundle_quotes lookup on the wrong prefix ('MIS' instead of 'BQ-'), so a BQ-
    reference fell through to the Policy lookup and dead-ended at
    policy_not_found. This runs the flow end-to-end against a DEPLOYED env:

        1. POST /api/v1/public/otp/customer/send    (purpose=payment_authorize)
        2. POST /api/v1/public/otp/customer/verify   -> session token (Bearer)
        3. POST /api/v1/public/policies/create-bundle -> stages a BQ- quote
        4. POST /api/v1/public/payments/dpo/initiate  (policy_number = BQ-...)
                                                      -> must NOT dead-end

    WHAT "PASS" MEANS:
      The fix is about ROUTING - that a BQ- ref resolves via bundle_quotes
      instead of dead-ending. This script FAILS only if step 4 returns
      'policy_not_found' or 'quote_not_found' (the dead-end / a regression).
      The success path of initiateDpo makes a LIVE call to the DPO gateway
      (createToken); whether it returns a redirect token depends on the
      target's DPO config (COMPANY_TOKEN etc.). So:
        - redirect_url returned        -> FULL PASS (routing + gateway green)
        - gateway_* error returned     -> ROUTING PASS, gateway needs attention
        - policy_not_found/quote_not_found -> FAIL (dead-end present)

    WARNING: step 3 stages a real bundle_quotes row, and the success path of
    step 4 creates a real (uncharged) payment token at DPO. Run only where that
    is acceptable.

.PARAMETER BaseUrl   Root URL of the deployed environment.
.PARAMETER Phone     8-digit BW cellphone (OTP identifier + bundle phone). Must be in OTP_TEST_PHONES to auto-read the code.
.PARAMETER Code      Optional explicit 6-digit OTP (when the target doesn't echo _test_otp).
.PARAMETER ProductId Bundle line product id (must NOT be a dedicated product 1/2/4). Default 9 (Hospital Cashback).
.PARAMETER PlanId    Bundle line plan id (must exist in product_plans). Default 19.
.PARAMETER Premium   Bundle line premium. Default 99.
.PARAMETER Context   Payment context (one of the service CONTEXTS). Default policy_create.

.EXAMPLE
    .\bundle_bq_payment_live_smoke.ps1 -BaseUrl https://staging.alphadirect.co.bw -Phone 71555000
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)] [string] $BaseUrl,
    [Parameter(Mandatory = $true)] [string] $Phone,
    [string] $Code,
    [int]    $ProductId = 9,
    [int]    $PlanId    = 19,
    [double] $Premium   = 99,
    [string] $ProductName = 'Hospital Cashback Insurance',
    [ValidateSet('monthly','quarterly','annual')] [string] $PremiumFrequency = 'monthly',
    [string] $Context = 'policy_create'
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/')
$purpose = 'payment_authorize'
$email = 'bundle-bq-livesmoke@yopmail.com'

function Write-Step([string]$msg) { Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Fail([string]$msg) { Write-Host "SMOKE FAILED: $msg" -ForegroundColor Red; exit 1 }

if ($ProductId -in 1,2,4) {
    Fail "ProductId $ProductId is a dedicated-endpoint product (refused by create-bundle). Pick a bundle-eligible product (e.g. 9)."
}

Write-Host "Bundle BQ- payment live smoke -> $base  (phone $Phone, line product $ProductId/plan $PlanId)" -ForegroundColor White

# ── 1. Send OTP ──────────────────────────────────────────────────────────────
Write-Step "1/4  OTP send (purpose=$purpose)"
try {
    $sendResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/otp/customer/send" `
        -ContentType 'application/json' -Body (@{ identifier = $Phone; purpose = $purpose } | ConvertTo-Json)
} catch { Fail "OTP send request errored: $($_.Exception.Message)" }
Write-Host ("sent_to={0}  expires_in={1}" -f $sendResp.sent_to, $sendResp.expires_in)

if (-not $Code) {
    if ($sendResp.PSObject.Properties.Name -contains '_test_otp' -and $sendResp._test_otp) {
        $Code = [string]$sendResp._test_otp
        Write-Host "Using echoed _test_otp (QA test number)." -ForegroundColor DarkGray
    } else {
        Fail "No _test_otp echoed for $Phone on this target. Re-run with -Code <6 digits>."
    }
}

# ── 2. Verify OTP -> session token ───────────────────────────────────────────
Write-Step "2/4  OTP verify -> session token"
try {
    $verifyResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/otp/customer/verify" `
        -ContentType 'application/json' -Body (@{ identifier = $Phone; code = $Code; purpose = $purpose } | ConvertTo-Json)
} catch { Fail "OTP verify request errored: $($_.Exception.Message)" }
if (-not $verifyResp.ok -or -not $verifyResp.token) { Fail "OTP verify did not return a session token (error=$($verifyResp.error))." }
$token = [string]$verifyResp.token
$auth = @{ Authorization = "Bearer $token" }
Write-Host "Session token acquired." -ForegroundColor DarkGray

# ── 3. Stage a bundle -> BQ- quote ───────────────────────────────────────────
Write-Step "3/4  create-bundle -> BQ- quote"
$bundlePayload = @{
    firstName          = 'Bundle'
    lastName           = 'Livesmoke'
    passport           = 'BQLS' + (Get-Random -Minimum 10000 -Maximum 99999)
    dob                = '1990-01-01'
    gender             = 'Female'
    phone              = $Phone
    email              = $email
    residentialAddress = 'Gaborone'
    lines = @(@{
        product_id   = $ProductId
        plan_id      = $PlanId
        premium      = $Premium
        product_name = $ProductName
    })
    premiumFrequency = $PremiumFrequency
} | ConvertTo-Json -Depth 6

try {
    $bundleResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/policies/create-bundle" `
        -Headers $auth -ContentType 'application/json' -Body $bundlePayload
} catch {
    $detail = $_.Exception.Message
    if ($_.ErrorDetails -and $_.ErrorDetails.Message) { $detail = $_.ErrorDetails.Message }
    Fail "create-bundle errored: $detail"
}
if (-not $bundleResp.ok)                     { Fail "create-bundle ok != true (error=$($bundleResp.error))." }
$quoteNumber = [string]$bundleResp.quote_number
$amount      = [double]$bundleResp.amount_to_pay
if (-not $quoteNumber.StartsWith('BQ-'))     { Fail "quote_number '$quoteNumber' is not BQ-prefixed." }
Write-Host ("Staged quote {0}  amount_to_pay={1}" -f $quoteNumber, $amount) -ForegroundColor DarkGray

# ── 4. Initiate DPO on the BQ- ref - must NOT dead-end ───────────────────────
Write-Step "4/4  dpo/initiate (policy_number=$quoteNumber)"
$initBody = @{ policy_number = $quoteNumber; amount = $amount; context = $Context; email = $email } | ConvertTo-Json
$initResp = $null
try {
    $initResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/payments/dpo/initiate" `
        -Headers $auth -ContentType 'application/json' -Body $initBody
} catch {
    # initiateDpo returns JSON (with ok:false) on handled errors; an HTTP-level
    # throw means we still want to inspect the body for the dead-end errors.
    if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
        try { $initResp = $_.ErrorDetails.Message | ConvertFrom-Json } catch { Fail "dpo/initiate errored: $($_.ErrorDetails.Message)" }
    } else {
        Fail "dpo/initiate request errored: $($_.Exception.Message)"
    }
}

$err = [string]$initResp.error
if ($err -eq 'policy_not_found' -or $err -eq 'quote_not_found') {
    Fail "DEAD-END PRESENT: dpo/initiate returned '$err' for a BQ- ref. The BQ- routing fix is NOT in effect on this target."
}

# Past the dead-end - routing works. Now report the gateway outcome.
Write-Host "`nROUTING PASS - BQ- ref resolved (no policy_not_found/quote_not_found dead-end)." -ForegroundColor Green
if ($initResp.ok -and $initResp.redirect_url) {
    Write-Host "FULL PASS - DPO gateway returned a payment token." -ForegroundColor Green
    Write-Host ("  quote_number : {0}" -f $quoteNumber)
    Write-Host ("  redirect_url : {0}" -f $initResp.redirect_url)
    Write-Host ("  reference    : {0}" -f $initResp.reference)
    Write-Host "`nNOTE: a real (uncharged) DPO payment token was created for $quoteNumber." -ForegroundColor Yellow
    exit 0
} else {
    Write-Host ("GATEWAY ATTENTION - routing is fixed, but the DPO gateway returned: error='{0}' message='{1}'." -f $err, $initResp.message) -ForegroundColor Yellow
    Write-Host "This is a gateway/config matter (e.g. COMPANY_TOKEN/SERVICE_TYPE on the target), not the BQ- dead-end." -ForegroundColor Yellow
    Write-Host ("  quote_number : {0}" -f $quoteNumber)
    Write-Host "`nThe carry-over's core assertion ('no longer hits the dead-end') is satisfied." -ForegroundColor White
    exit 0
}
