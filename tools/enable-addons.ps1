# Turnkey enablement for the Forkiva add-on modules (Hr / Delivery / Accounting).
#
# PREREQUISITE: copy the add-on module folders from your CodeCanyon purchase
# package into Modules\Hr, Modules\Delivery, Modules\Accounting (each must
# contain a module.json). This script enables whichever of them are present.
#
# Usage:  powershell -ExecutionPolicy Bypass -File tools\enable-addons.ps1
$ErrorActionPreference = 'Stop'
Set-Location D:\codea\forkiva\Forkiva

$php = 'D:\codea\forkiva\tools\php84\php.exe'
$ini = 'D:\codea\forkiva\tools\php84\php.ini'
# Make composer use the same PHP 8.4 runtime as artisan.
$env:PATH = 'D:\codea\forkiva\tools\php84;' + $env:PATH

$wanted = @('Hr', 'Delivery', 'Accounting')
$present = @()
$missing = @()
foreach ($m in $wanted) {
    if (Test-Path "Modules\$m\module.json") { $present += $m } else { $missing += $m }
}

Write-Output '=== ADD-ON MODULE STATUS ==='
Write-Output ('Present: ' + ($present -join ', '))
Write-Output ('Missing: ' + ($missing -join ', '))
if ($present.Count -eq 0) {
    Write-Output 'No add-on module folders found. Copy them from your CodeCanyon package into Modules\ first.'
    exit 1
}

Write-Output '=== COMPOSER AUTOLOAD ==='
composer dump-autoload

Write-Output '=== ENABLE MODULES ==='
& $php '-c' $ini 'artisan' 'module:enable' @present

Write-Output '=== FORKIVA UPGRADE (migrate + sync permissions/roles + clear caches) ==='
& $php '-c' $ini 'artisan' 'forkiva:upgrade' '--force'

Write-Output '=== CHECK MODULE SOURCES ==='
yarn check:modules

Write-Output '=== BUILD FRONTEND ==='
yarn build

Write-Output '=== VERIFY ==='
& $php '-c' $ini 'artisan' 'module:list'
Write-Output '=== ADD-ON ENABLEMENT DONE ==='
Write-Output ('Enabled: ' + ($present -join ', '))
if ($missing.Count -gt 0) {
    Write-Output ('Still missing (copy from package to enable later): ' + ($missing -join ', '))
}