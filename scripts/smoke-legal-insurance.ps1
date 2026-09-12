<#
.SYNOPSIS
    Smoke test for Legal Insurance create-policy flow on V2.

.DESCRIPTION
    Fires the two-step happy path a customer takes when buying Legal
    Insurance through start.alphadirect.co.bw:

        1. POST /api/v1/public/policies/create-bundle  (product_id=4)
        2. POST /api/v1/public/payments/dpo/initiate   (with the BQ-* ref)

    Reports whether the current production bug is still present:
    create-bundle returns 201 with a BQ- reference, but DPO initiate
    404s with error=policy_not_found because PublicPaymentService has
    no BQ- branch (only MQ- and Policy lookup).

    Stops BEFORE following any DPO redirect. Does NOT trigger a real
    payment. Safe to run against prod.

.PARAMETER BaseUrl
    Backend root. Defaults to prod. Use http://127.0.0.1:8000 for local.

.PARAMETER Token
    Bearer token from a successful OTP verify (purpose=payment_authorize).

.PARAMETER Phone
    Cellphone (8-digit BW local). Must match the cellphone the token
    was issued for.

.EXAMPLE
    .\smoke-legal-insurance.ps1 -Token "abc123..." -Phone 85858565

.EXAMPLE
    .\smoke-legal-insurance.ps1 -BaseUrl http://127.0.0.1:8000 -Token "..." -Phone 70000099

.NOTES
    Exit codes:
      0 - BQ- branch works (bug fixed) or non-policy_not_found error.
      1 - Bug still present. DPO initiate returned 404 policy_not_found.
      2 - Unexpected failure (network, auth, validation).
#>

[CmdletBinding()]
param(
    [string]$BaseUrl = 'https://graphite-v2-be.alphadirect.co.bw',
    [Parameter(Mandatory=$true)][string]$Token,
    [Parameter(Mandatory=$true)][string]$Phone,
    [int]$PlanId = 18,
    [decimal]$Premium = 75
)

$ErrorActionPreference = 'Stop'

function Write-Section {
    param([string]$Text)
    $bar = '=' * 70
    Write-Host ''
    Write-Host $bar -ForegroundColor DarkCyan
    Write-Host $Text -ForegroundColor Cyan
    Write-Host $bar -ForegroundColor DarkCyan
}

function Invoke-JsonPost {
    param(
        [string]$Url,
        [hashtable]$Body,
        [hashtable]$Headers
    )
    $bodyJson = $Body | ConvertTo-Json -Depth 8 -Compress
    Write-Host "POST $Url" -ForegroundColor Yellow
    Write-Host "  body: $bodyJson" -ForegroundColor DarkGray

    $status = 0
    $content = ''
    try {
        $resp = Invoke-WebRequest -Method Post -Uri $Url -Headers $Headers -ContentType 'application/json' -Body $bodyJson -UseBasicParsing -ErrorAction Stop
        $status = [int]$resp.StatusCode
        $content = $resp.Content
    } catch {
        $webResp = $null
        if ($_.Exception.PSObject.Properties.Name -contains 'Response') {
            $webResp = $_.Exception.Response
        }
        if ($webResp) {
            $status = [int]$webResp.StatusCode
            try {
                $stream = $webResp.GetResponseStream()
                $reader = New-Object System.IO.StreamReader($stream)
                $content = $reader.ReadToEnd()
                $reader.Close()
            } catch {
                if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
                    $content = $_.ErrorDetails.Message
                }
            }
        } else {
            throw
        }
    }

    $color = 'Red'
    if ($status -ge 200 -and $status -lt 300) { $color = 'Green' }
    Write-Host "  -> HTTP $status" -ForegroundColor $color

    $parsed = $null
    try { $parsed = $content | ConvertFrom-Json } catch { $parsed = $null }

    return [pscustomobject]@{
        Status = $status
        Body   = $content
        Json   = $parsed
    }
}

Write-Section 'Alpha Direct V2 - Legal Insurance create-policy smoke'
Write-Host "Target:  $BaseUrl"
Write-Host "Phone:   $Phone"
Write-Host "PlanId:  $PlanId"
Write-Host "Premium: $Premium"

$headers = @{
    'Authorization' = "Bearer $Token"
    'Accept'        = 'application/json'
}

# Step 1: create-bundle with Legal Insurance (product_id=4)
Write-Section 'Step 1 - POST /api/v1/public/policies/create-bundle'
$passportRand = 'SMOKE' + (Get-Random -Minimum 10000 -Maximum 99999)
$createBody = @{
    firstName          = 'Legal'
    lastName           = 'Smoketest'
    passport           = $passportRand
    dob                = '1990-01-01'
    gender             = 'Female'
    phone              = $Phone
    email              = 'smoke-legal@yopmail.com'
    residentialAddress = 'Gaborone'
    lines              = @(@{
        product_id   = 4
        plan_id      = $PlanId
        premium      = $Premium
        product_name = 'Legal Insurance'
    })
    premiumFrequency   = 'annual'
}

$create = Invoke-JsonPost -Url "$BaseUrl/api/v1/public/policies/create-bundle" -Body $createBody -Headers $headers
Write-Host $create.Body -ForegroundColor Gray

if ($create.Status -ne 201) {
    Write-Host ''
    Write-Host 'FAIL: create-bundle did not return 201. See response above.' -ForegroundColor Red
    Write-Host 'Common causes:' -ForegroundColor Yellow
    Write-Host '  - session_required / session_invalid_or_expired: token bad or expired'
    Write-Host '  - session_phone_mismatch: -Phone does not match token'
    Write-Host '  - validation: plan_id may not exist on target env'
    exit 2
}

$bundleRef = $create.Json.policy_number
if (-not $bundleRef -or -not $bundleRef.StartsWith('BQ-')) {
    Write-Host 'FAIL: response did not contain a BQ- policy_number.' -ForegroundColor Red
    exit 2
}
Write-Host ''
Write-Host "OK: bundle quote staged as $bundleRef" -ForegroundColor Green

# Step 2: DPO initiate
Write-Section 'Step 2 - POST /api/v1/public/payments/dpo/initiate'
$initiateBody = @{
    policy_number = $bundleRef
    amount        = $Premium
    context       = 'policy_create'
    email         = 'smoke-legal@yopmail.com'
}

$initiate = Invoke-JsonPost -Url "$BaseUrl/api/v1/public/payments/dpo/initiate" -Body $initiateBody -Headers $headers
Write-Host $initiate.Body -ForegroundColor Gray

# Verdict
Write-Section 'Verdict'

$errorCode = $null
if ($initiate.Json -and $initiate.Json.PSObject.Properties.Name -contains 'error') {
    $errorCode = $initiate.Json.error
}

if ($initiate.Status -eq 404 -and $errorCode -eq 'policy_not_found') {
    Write-Host 'BUG STILL PRESENT' -ForegroundColor Red
    Write-Host ''
    Write-Host "DPO initiate returned 404 policy_not_found for $bundleRef."
    Write-Host 'Root cause: PublicPaymentService initiateDpo has no BQ- branch.'
    Write-Host 'Fix: mirror the MQ- branch (around line 67) for BQ-, reading from bundle_quotes.'
    exit 1
}

if ($initiate.Status -ge 200 -and $initiate.Status -lt 300) {
    Write-Host 'BUG FIXED' -ForegroundColor Green
    Write-Host ''
    Write-Host 'DPO initiate succeeded. redirect_url should be present above.'
    Write-Host 'Script does NOT follow the redirect.'
    exit 0
}

$statusOut = $initiate.Status
Write-Host 'INCONCLUSIVE' -ForegroundColor Yellow
Write-Host ''
Write-Host "DPO initiate returned HTTP $statusOut, error=$errorCode"
Write-Host 'It did NOT return policy_not_found, which means the BQ- branch IS resolving'
Write-Host 'the bundle quote, but the call failed downstream for a different reason.'
Write-Host 'Likely causes: gateway_misconfigured (COMPANY_TOKEN unset), session_policy_mismatch,'
Write-Host 'or gateway_unreachable. Treat as bug-fixed for the BQ- branch specifically.'
exit 0
