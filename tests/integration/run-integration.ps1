<#
  Runs the integration scenario on a real WordPress (WordPress Playground CLI, WASM, no Docker).

  Usage:  powershell -File tests/integration/run-integration.ps1 [-Modes irt,irr,nowc] [-Php 7.4]

  Modes:
    irt   WooCommerce with IRT currency
    irr   WooCommerce with IRR currency
    nowc  WordPress without WooCommerce
#>
param([string[]]$Modes = @('nowc', 'irt', 'irr'), [string]$Php = '8.3')
$Modes = $Modes | ForEach-Object { $_ -split ',' }

$ErrorActionPreference = 'Continue'
$plugin = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$work = Join-Path $env:TEMP 'one-navasan-integration'
New-Item -ItemType Directory -Force $work | Out-Null
$failed = $false

foreach ($mode in $Modes) {
    $modeDir = Join-Path $work "mode-$mode"
    New-Item -ItemType Directory -Force $modeDir | Out-Null
    Remove-Item (Join-Path $modeDir 'result.log') -ErrorAction SilentlyContinue
    [IO.File]::WriteAllText((Join-Path $modeDir 'mode.txt'), $mode)

    $steps = @()
    if ($mode -ne 'nowc') {
        $steps += @{ step = 'installPlugin'; pluginData = @{ resource = 'wordpress.org/plugins'; slug = 'woocommerce' }; options = @{ activate = $true } }
    }
    $code = "<?php require '/wordpress/wp-content/plugins/navasan-api-wordpress-plugin/tests/integration/one-navasan-scenario.php';"
    $steps += @{ step = 'runPHP'; code = $code }
    $bp = @{ preferredVersions = @{ php = $Php; wp = 'latest' }; steps = $steps } | ConvertTo-Json -Depth 8
    $bpFile = Join-Path $work "blueprint-$mode.json"
    [IO.File]::WriteAllText($bpFile, $bp)

    Write-Host "=== Running mode: $mode ===" -ForegroundColor Cyan
    $out = npx --yes '@wp-playground/cli@latest' run-blueprint --blueprint "$bpFile" `
        --mount-dir "$plugin" /wordpress/wp-content/plugins/navasan-api-wordpress-plugin `
        --mount-dir "$modeDir" /one-mode 2>&1 | Out-String
    $log = Join-Path $work "result-$mode.log"
    $resultFile = Join-Path $modeDir 'result.log'
    if (Test-Path $resultFile) { $out = $out + "`n" + [IO.File]::ReadAllText($resultFile) }
    [IO.File]::WriteAllText($log, $out)
    $lines = $out -split "`r?`n" | Where-Object { $_ -match '^(PASS|FAIL|RESULT|== MODE)|Fatal|Warning|Notice|Deprecated|Error' }
    $lines | ForEach-Object { Write-Host $_ }
    if ($out -notmatch 'RESULT: \d+ pass, 0 fail') { $failed = $true }
}
if ($failed) { Write-Host 'INTEGRATION: FAILURES' -ForegroundColor Red; exit 1 }
Write-Host 'INTEGRATION: ALL PASSED' -ForegroundColor Green
