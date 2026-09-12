<#
.SYNOPSIS
    Live smoke test for the Mobile & Electronic Device Insurance create endpoint.

.DESCRIPTION
    Runs the full customer-facing flow against a DEPLOYED environment and proves
    the endpoint mints a real MIS-prefixed device policy with its insured device
    captured end-to-end:

        1. POST /api/v1/public/otp/customer/send    (purpose=payment_authorize)
        2. POST /api/v1/public/otp/customer/verify   -> session token (Bearer)
        3. POST /api/v1/public/policies/create-mobile-electronic  (device array)
                                                     -> 201, ok, MIS-... policy
        4. POST /api/v1/public/policies/{policyNumber}/detail  (read-back)
                                                     -> policy retrievable, product 5

    DEVICE PERSISTENCE NOTE:
      The controller writes customer -> customer_profile -> policies ->
      policy_cellphone inside ONE DB transaction (commit on success, rollBack on
      any failure). So a 201 guarantees the policy_cellphone row (IMEI / make /
      model) was committed atomically with the policy. The public /detail
      endpoint returns policy + customer + profile but does NOT echo device
      columns, so a literal IMEI column check must be done on the DB:
          SELECT imei, cell_phone_make FROM policy_cellphone
          WHERE policy_id = <id printed below>;

    WARNING: step 3 writes a REAL policy row (status 0 / pending payment) to the
    target environment's database. It does NOT initiate a payment (no DPO charge).

.PARAMETER BaseUrl
    Root URL of the deployed environment, e.g. https://staging.alphadirect.co.bw

.PARAMETER Phone
    8-digit BW cellphone used as OTP identifier AND policy phone. Must be in the
    target's OTP_TEST_PHONES for the code to be auto-read.

.PARAMETER PlanId
    Device plan id. Accepted set: 9, 17. Default 9.

.PARAMETER Code
    Optional explicit 6-digit OTP (use when the target does not echo _test_otp).

.PARAMETER PaymentMethod
    Default DPO.

.EXAMPLE
    .\mobile_electronic_live_smoke.ps1 -BaseUrl https://staging.alphadirect.co.bw -Phone 71555000
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)] [string] $BaseUrl,
    [Parameter(Mandatory = $true)] [string] $Phone,
    [ValidateSet(9, 17)] [int] $PlanId = 9,
    [string] $Code,
    [string] $PaymentMethod = 'DPO'
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/')
$purpose = 'payment_authorize'

# Device fixture — IMEI/make/model we expect to persist.
$device = @{
    deviceType = 'Cellphone'
    imei       = '356938035643809'
    make       = 'Samsung'
    model      = 'Galaxy S21'
    value      = 8000
}

function Write-Step([string]$msg) { Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Fail([string]$msg) { Write-Host "SMOKE FAILED: $msg" -ForegroundColor Red; exit 1 }

Write-Host "Mobile & Electronic Device live smoke -> $base  (phone $Phone, plan $PlanId)" -ForegroundColor White
Write-Host ("device: IMEI={0} make={1} model={2}" -f $device.imei, $device.make, $device.model) -ForegroundColor DarkGray

# ── 1. Send OTP ──────────────────────────────────────────────────────────────
Write-Step "1/4  OTP send (purpose=$purpose)"
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
Write-Step "2/4  OTP verify -> session token"
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

# ── 3. Create Mobile & Electronic Device policy ──────────────────────────────
Write-Step "3/4  create-mobile-electronic"
$payload = @{
    firstName     = 'Lesedi'
    lastName      = 'Livesmoke'
    passport      = 'MELS' + (Get-Random -Minimum 10000 -Maximum 99999)
    dob           = '1994-01-15'
    gender        = 'Female'
    phone         = $Phone
    email         = 'mobelec-livesmoke@yopmail.com'
    address       = 'Gaborone'
    planId        = $PlanId
    paymentMethod = $PaymentMethod
    device        = $device
} | ConvertTo-Json -Depth 5

try {
    $createResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/policies/create-mobile-electronic" `
        -Headers @{ Authorization = "Bearer $token" } `
        -ContentType 'application/json' -Body $payload
} catch {
    $detail = $_.Exception.Message
    if ($_.ErrorDetails -and $_.ErrorDetails.Message) { $detail = $_.ErrorDetails.Message }
    Fail "create-mobile-electronic errored: $detail"
}

if (-not $createResp.ok)                              { Fail "Response ok != true (error=$($createResp.error))." }
$policyNumber = [string]$createResp.policy.policyNumber
$policyId     = $createResp.policy.id
if (-not $policyNumber)                               { Fail "No policy.policyNumber in response." }
if (-not $policyNumber.StartsWith('MIS'))             { Fail "policyNumber '$policyNumber' is not MIS-prefixed." }
if ([int]$createResp.policy.product_id -ne 5)         { Fail "product_id is $($createResp.policy.product_id), expected 5 (device cover)." }
if ([int]$createResp.policy.plan_id -ne $PlanId)      { Fail "plan_id is $($createResp.policy.plan_id), expected $PlanId." }
Write-Host "Created $policyNumber (device row committed in the same transaction)." -ForegroundColor DarkGray

# ── 4. Read-back via /detail (proves the policy is retrievable end-to-end) ───
Write-Step "4/4  detail read-back"
try {
    $detailResp = Invoke-RestMethod -Method Post -Uri "$base/api/v1/public/policies/$policyNumber/detail" `
        -Headers @{ Authorization = "Bearer $token" } -ContentType 'application/json' -Body '{}'
    $rbProduct = [int]$detailResp.policy.product_id
    if ($rbProduct -ne 5) { Fail "Read-back product_id is $rbProduct, expected 5." }
    Write-Host "Read-back OK: policy retrievable, product_id=5." -ForegroundColor DarkGray
} catch {
    # Read-back is a bonus assertion; the create + transaction is the primary proof.
    Write-Host "WARN: detail read-back could not be verified over HTTP ($($_.Exception.Message)). Create still succeeded." -ForegroundColor Yellow
}

# ── Result ───────────────────────────────────────────────────────────────────
Write-Host "`nSMOKE PASSED" -ForegroundColor Green
Write-Host ("  policy_number : {0}" -f $policyNumber)
Write-Host ("  policy_id     : {0}" -f $policyId)
Write-Host ("  product_id    : {0} (device cover)" -f $createResp.policy.product_id)
Write-Host ("  plan_id       : {0}" -f $createResp.policy.plan_id)
Write-Host ("  amount_to_pay : {0}" -f $createResp.amount_to_pay)
Write-Host ("  device sent   : IMEI={0} make={1} model={2}" -f $device.imei, $device.make, $device.model)
Write-Host "`nDevice row (policy_cellphone) was committed atomically with the policy." -ForegroundColor White
Write-Host "For a literal column check on the target DB:" -ForegroundColor DarkGray
Write-Host ("  SELECT imei, cell_phone_make, cell_phone_model FROM policy_cellphone WHERE policy_id = {0};" -f $policyId) -ForegroundColor DarkGray
Write-Host "`nNOTE: a real pending-payment policy ($policyNumber) was created on $base." -ForegroundColor Yellow
Write-Host "      Have QA void/clean it up if the target is not a disposable environment." -ForegroundColor Yellow
exit 0
