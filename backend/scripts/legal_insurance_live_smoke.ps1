<#
.SYNOPSIS
    Live smoke test for the Legal Insurance V2 create-policy endpoint.

.DESCRIPTION
    Runs the full customer-facing flow against a DEPLOYED environment and
    proves the endpoint mints a real MIS-prefixed Legal policy end-to-end:

        1. POST /api/v1/public/otp/customer/send    (purpose=payment_authorize)
        2. POST /api/v1/public/otp/customer/verify   -> session token (Bearer)
        3. POST /api/v1/public/policies/create-legal-insurance
                                                     -> 201, ok, MIS-... policy

    WARNING: step 3 writes a REAL policy row (status 0 / pending payment) to the
    target environment's database. Only run it against an environment where that
    is acceptable. It does NOT initiate a payment (no DPO charge).

    The OTP code is read automatically from the `_test_otp` field that the API
    echoes back ONLY for numbers configured in OTP_TEST_PHONES (QA/dev). If the
    target doesn't echo it, pass the 6-digit code yourself with -Code.

.PARAMETER BaseUrl
    Root URL of the deployed environment, e.g. https://staging.alphadirect.co.bw
    (no trailing slash needed).

.PARAMETER Phone
    8-digit BW cellphone used as the OTP identifier AND the policy phone. Must be
    in the target's OTP_TEST_PHONES for the code to be auto-read.

.PARAMETER PlanId
    Legal plan id. Accepted set: 5, 6, 7, 18, 23. Default 5 (P49_Legal).

.PARAMETER Code
    Optional explicit 6-digit OTP (use when the target does not echo _test_otp).

.PARAMETER PaymentMethod
    Default DPO.

.EXAMPLE
    .\legal_insurance_live_smoke.ps1 -BaseUrl https://staging.alphadirect.co.bw -Phone 71555000

.EXAMPLE
    .\legal_insurance_live_smoke.ps1 -BaseUrl https://staging.alphadirect.co.bw -Phone 71555000 -Code 482913 -PlanId 18
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)] [string] $BaseUrl,
    [Parameter(Mandatory = $true)] [string] $Phone,
    [ValidateSet(5, 6, 7, 18, 23)] [int] $PlanId = 5,
    [string] $Code,
    [string] $PaymentMethod = 'DPO'
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/')
$purpose = 'payment_authorize'

function Write-Step([string]$msg) { Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Fail([string]$msg) { Write-Host "SMOKE FAILED: $msg" -ForegroundColor Red; exit 1 }

Write-Host "Legal Insurance live smoke -> $base  (phone $Phone, plan $PlanId)" -ForegroundColor White

# ── 1. Send OTP ──────────────────────────────────────────────────────────────
Write-Step "1/3  OTP send (purpose=$purpose)"
try {
    $sendResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/otp/customer/send" `
        -ContentType 'application/json' `
        -Body (@{ identifier = $Phone; purpose = $purpose } | ConvertTo-Json)
} catch {
    Fail "OTP send request errored: $($_.Exception.Message)"
}
Write-Host ("sent_to={0}  expires_in={1}" -f $sendResp.sent_to, $sendResp.expires_in)

if (-not $Code) {
    if ($sendResp.PSObject.Properties.Name -contains '_test_otp' -and $sendResp._test_otp) {
        $Code = [string]$sendResp._test_otp
        Write-Host "Using echoed _test_otp (QA test number)." -ForegroundColor DarkGray
    } else {
        Fail "No _test_otp echoed for $Phone on this target. Re-run with -Code <6 digits> (the SMS code)."
    }
}

# ── 2. Verify OTP -> session token ───────────────────────────────────────────
Write-Step "2/3  OTP verify -> session token"
try {
    $verifyResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/otp/customer/verify" `
        -ContentType 'application/json' `
        -Body (@{ identifier = $Phone; code = $Code; purpose = $purpose } | ConvertTo-Json)
} catch {
    Fail "OTP verify request errored: $($_.Exception.Message)"
}
if (-not $verifyResp.ok -or -not $verifyResp.token) {
    Fail "OTP verify did not return a session token (error=$($verifyResp.error))."
}
$token = [string]$verifyResp.token
Write-Host "Session token acquired." -ForegroundColor DarkGray

# ── 3. Create Legal Insurance policy ─────────────────────────────────────────
Write-Step "3/3  create-legal-insurance"
$payload = @{
    firstName     = 'Legal'
    lastName      = 'Livesmoke'
    passport      = 'LSMK' + (Get-Random -Minimum 10000 -Maximum 99999)
    dob           = '1990-01-01'
    gender        = 'Female'
    maritalStatus = 'married'
    phone         = $Phone
    email         = 'legal-livesmoke@yopmail.com'
    address       = 'Gaborone'
    planId        = $PlanId
    paymentMethod = $PaymentMethod
    spouse        = @{
        firstName = 'Spouse'
        lastName  = 'Livesmoke'
        dob       = '1992-03-15'
        gender    = 'Male'
        omang     = '123456789'
        cellphone = '70000100'
        email     = 'spouse-livesmoke@yopmail.com'
    }
} | ConvertTo-Json -Depth 5

try {
    $createResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/policies/create-legal-insurance" `
        -Headers @{ Authorization = "Bearer $token" } `
        -ContentType 'application/json' -Body $payload
} catch {
    $detail = $_.Exception.Message
    if ($_.ErrorDetails -and $_.ErrorDetails.Message) { $detail = $_.ErrorDetails.Message }
    Fail "create-legal-insurance errored: $detail"
}

# ── Assertions ───────────────────────────────────────────────────────────────
if (-not $createResp.ok)                              { Fail "Response ok != true (error=$($createResp.error))." }
$policyNumber = [string]$createResp.policy.policyNumber
if (-not $policyNumber)                               { Fail "No policy.policyNumber in response." }
if (-not $policyNumber.StartsWith('MIS'))             { Fail "policyNumber '$policyNumber' is not MIS-prefixed." }
if ([int]$createResp.policy.product_id -ne 4)         { Fail "product_id is $($createResp.policy.product_id), expected 4 (Legal)." }
if ([int]$createResp.policy.plan_id -ne $PlanId)      { Fail "plan_id is $($createResp.policy.plan_id), expected $PlanId." }

Write-Host "`nSMOKE PASSED" -ForegroundColor Green
Write-Host ("  policy_number : {0}" -f $policyNumber)
Write-Host ("  policy_id     : {0}" -f $createResp.policy.id)
Write-Host ("  product_id    : {0} (Legal)" -f $createResp.policy.product_id)
Write-Host ("  plan_id       : {0}" -f $createResp.policy.plan_id)
Write-Host ("  amount_to_pay : {0}" -f $createResp.amount_to_pay)
Write-Host "`nNOTE: a real pending-payment policy ($policyNumber) was created on $base." -ForegroundColor Yellow
Write-Host "      Have QA void/clean it up if the target is not a disposable environment." -ForegroundColor Yellow
exit 0
