<#
  Runs the integration scenarios on a real WordPress (WordPress Playground CLI, WASM, no Docker).

  Usage:  powershell -File tests/integration/run-integration.ps1 [-Modes irt,irr,nowc,upgrade,upgrade-manual] [-Php 7.4]

  Modes:
    irt             WooCommerce with IRT currency
    irr             WooCommerce with IRR currency
    nowc            WordPress without WooCommerce
    upgrade         install v1.0.0 (built from its git tag), configure and price products,
                    then replace it with the freshly built zip of this tree and check nothing changed
    upgrade-manual  the same, for a site that used the manual rate mode
#>
param([string[]]$Modes = @('nowc', 'irt', 'irr', 'upgrade', 'upgrade-manual'), [string]$Php = '8.3', [string]$PreviousTag = 'v1.0.0')
$Modes = $Modes | ForEach-Object { $_ -split ',' }

$ErrorActionPreference = 'Continue'
$plugin = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$work = Join-Path $env:TEMP 'one-navasan-integration'
New-Item -ItemType Directory -Force $work | Out-Null
$failed = $false

# Builds the installable zip of a source tree with its own tools/build-zip.php and extracts it.
function Expand-PluginBuild([string]$SourceDir, [string]$Target) {
    if (Test-Path $Target) { Remove-Item $Target -Recurse -Force }
    New-Item -ItemType Directory -Force $Target | Out-Null
    $dist = Join-Path $Target 'dist'
    php (Join-Path $SourceDir 'tools\build-zip.php') $dist | Out-Null
    $zip = Get-ChildItem $dist -Filter *.zip | Select-Object -First 1
    Expand-Archive $zip.FullName -DestinationPath (Join-Path $Target 'unzipped') -Force
    return (Join-Path $Target 'unzipped\navasan-api-wordpress-plugin')
}

$copyPhp = @'
function one_cp($s,$d){ @mkdir($d,0777,true); foreach(scandir($s) as $f){ if($f==='.'||$f==='..'){continue;} if(is_dir("$s/$f")){one_cp("$s/$f","$d/$f");}else{copy("$s/$f","$d/$f");} } }
function one_rm($d){ if(!is_dir($d)){return;} foreach(scandir($d) as $f){ if($f==='.'||$f==='..'){continue;} if(is_dir("$d/$f")){one_rm("$d/$f");}else{unlink("$d/$f");} } rmdir($d); }
'@

foreach ($mode in $Modes) {
    $modeDir = Join-Path $work "mode-$mode"
    if (Test-Path $modeDir) { Remove-Item $modeDir -Recurse -Force }
    New-Item -ItemType Directory -Force $modeDir | Out-Null
    [IO.File]::WriteAllText((Join-Path $modeDir 'mode.txt'), $mode)

    $mounts = @()
    $steps = @()
    if ($mode -like 'upgrade*') {
        # Previous version: exactly what a release of that tag contains.
        $prevSrc = Join-Path $work 'prev-src'
        if (Test-Path $prevSrc) { Remove-Item $prevSrc -Recurse -Force }
        New-Item -ItemType Directory -Force $prevSrc | Out-Null
        $prevZip = Join-Path $work 'prev-src.zip'
        git -C $plugin archive --format=zip -o $prevZip $PreviousTag
        Expand-Archive $prevZip -DestinationPath $prevSrc -Force
        $prevDir = Expand-PluginBuild $prevSrc (Join-Path $work 'prev-build')
        # New version: the zip built from the current working tree.
        $newDir = Expand-PluginBuild $plugin (Join-Path $work 'new-build')

        $mounts = @('--mount-dir', "$prevDir", '/one-prev', '--mount-dir', "$newDir", '/one-new', '--mount-dir', (Join-Path $plugin 'tests\integration'), '/one-tests', '--mount-dir', "$modeDir", '/one-mode')
        $dest = '/wordpress/wp-content/plugins/navasan-api-wordpress-plugin'
        $steps += @{ step = 'installPlugin'; pluginData = @{ resource = 'wordpress.org/plugins'; slug = 'woocommerce' }; options = @{ activate = $true } }
        $steps += @{ step = 'runPHP'; code = "<?php $copyPhp one_cp('/one-prev','$dest'); echo 'prev installed';" }
        $steps += @{ step = 'runPHP'; code = "<?php require '/one-tests/one-navasan-upgrade-stage1.php';" }
        $steps += @{ step = 'runPHP'; code = "<?php $copyPhp one_rm('$dest'); one_cp('/one-new','$dest'); echo 'new installed over old';" }
        $steps += @{ step = 'runPHP'; code = "<?php require '/one-tests/one-navasan-upgrade-stage2.php';" }
    } else {
        $mounts = @('--mount-dir', "$plugin", '/wordpress/wp-content/plugins/navasan-api-wordpress-plugin', '--mount-dir', "$modeDir", '/one-mode')
        if ($mode -ne 'nowc') {
            $steps += @{ step = 'installPlugin'; pluginData = @{ resource = 'wordpress.org/plugins'; slug = 'woocommerce' }; options = @{ activate = $true } }
        }
        $steps += @{ step = 'runPHP'; code = "<?php require '/wordpress/wp-content/plugins/navasan-api-wordpress-plugin/tests/integration/one-navasan-scenario.php';" }
    }
    $bp = @{ preferredVersions = @{ php = $Php; wp = 'latest' }; steps = $steps } | ConvertTo-Json -Depth 8
    $bpFile = Join-Path $work "blueprint-$mode.json"
    [IO.File]::WriteAllText($bpFile, $bp)

    Write-Host "=== Running mode: $mode (PHP $Php) ===" -ForegroundColor Cyan
    $out = npx --yes '@wp-playground/cli@latest' run-blueprint --blueprint "$bpFile" @mounts 2>&1 | Out-String
    $log = Join-Path $work "result-$mode.log"
    foreach ($name in @('stage1.log', 'result.log')) {
        $f = Join-Path $modeDir $name
        if (Test-Path $f) { $out = $out + "`n" + [IO.File]::ReadAllText($f) }
    }
    [IO.File]::WriteAllText($log, $out)
    $lines = $out -split "`r?`n" | Where-Object { $_ -match '^(PASS|FAIL|RESULT|STAGE1 RESULT|== )|Fatal|Warning|Notice|Deprecated|Error' }
    $lines | ForEach-Object { Write-Host $_ }
    if ($out -notmatch 'RESULT: \d+ pass, 0 fail' -or $out -cmatch '(?m)^FAIL ') { $failed = $true }
    if ($mode -like 'upgrade*' -and $out -notmatch 'STAGE1 RESULT: \d+ pass, 0 fail') { $failed = $true }
}
if ($failed) { Write-Host 'INTEGRATION: FAILURES' -ForegroundColor Red; exit 1 }
Write-Host 'INTEGRATION: ALL PASSED' -ForegroundColor Green
