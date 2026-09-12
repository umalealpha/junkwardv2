# Render PDF pages to PNG using the PDF rasteriser built into Windows
# (Windows.Data.Pdf). No third-party tooling required.
param(
    [Parameter(Mandatory = $true)][string]$Pdf,
    [Parameter(Mandatory = $true)][string]$OutDir,
    [int]$First = 1,
    [int]$Last = 0,
    [int]$Width = 1700
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Runtime.WindowsRuntime

$asTaskGeneric = ([System.WindowsRuntimeSystemExtensions].GetMethods() |
    Where-Object {
        $_.Name -eq 'AsTask' -and
        $_.GetParameters().Count -eq 1 -and
        $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1'
    })[0]

function Await($op, $type) {
    $asTask = $asTaskGeneric.MakeGenericMethod($type)
    $t = $asTask.Invoke($null, @($op))
    $t.Wait(-1) | Out-Null
    $t.Result
}

# IAsyncAction needs its own AsTask overload, picked by reflection: PowerShell
# 5.1 cannot resolve it from the argument alone.
$asTaskAction = ([System.WindowsRuntimeSystemExtensions].GetMethods() |
    Where-Object {
        $_.Name -eq 'AsTask' -and
        $_.GetParameters().Count -eq 1 -and
        $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction'
    })[0]

function AwaitAction($action) {
    $t = $asTaskAction.Invoke($null, @($action))
    $t.Wait(-1) | Out-Null
}

[Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime]              | Out-Null
[Windows.Data.Pdf.PdfDocument, Windows.Data.Pdf, ContentType = WindowsRuntime]            | Out-Null
[Windows.Storage.Streams.InMemoryRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime] | Out-Null
[Windows.Storage.Streams.DataReader, Windows.Storage.Streams, ContentType = WindowsRuntime] | Out-Null

if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Force -Path $OutDir | Out-Null }

$file = Await ([Windows.Storage.StorageFile]::GetFileFromPathAsync((Resolve-Path $Pdf).Path)) ([Windows.Storage.StorageFile])
$doc  = Await ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])

$count = $doc.PageCount
if ($Last -le 0 -or $Last -gt $count) { $Last = $count }
Write-Output "pages in document: $count, rendering $First..$Last at width $Width"

for ($i = $First; $i -le $Last; $i++) {
    $page = $doc.GetPage($i - 1)
    $stream = New-Object Windows.Storage.Streams.InMemoryRandomAccessStream

    $opts = New-Object Windows.Data.Pdf.PdfPageRenderOptions
    $opts.DestinationWidth = $Width

    AwaitAction ($page.RenderToStreamAsync($stream, $opts))

    $size = [uint32]$stream.Size
    $stream.Seek(0)
    $reader = New-Object Windows.Storage.Streams.DataReader($stream.GetInputStreamAt(0))
    Await ($reader.LoadAsync($size)) ([uint32]) | Out-Null

    $bytes = New-Object byte[] $size
    $reader.ReadBytes($bytes)
    $reader.Dispose()

    $out = Join-Path $OutDir ("page{0:D3}.png" -f $i)
    [System.IO.File]::WriteAllBytes($out, $bytes)

    $page.Dispose()
    $stream.Dispose()
    Write-Output ("  page {0,3} -> {1} ({2} kB)" -f $i, (Split-Path $out -Leaf), [int]($size / 1024))
}

$doc = $null
Write-Output 'done'

