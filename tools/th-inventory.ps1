$mods = Get-ChildItem D:\codea\forkiva\Forkiva\Modules -Directory
foreach ($m in $mods) {
    $en = Join-Path $m.FullName 'lang\en'
    $th = Join-Path $m.FullName 'lang\th'
    if (Test-Path $en) {
        $enFiles = Get-ChildItem $en -Filter *.php
        $thFiles = if (Test-Path $th) { (Get-ChildItem $th -Filter *.php).Name } else { @() }
        $missing = $enFiles | Where-Object { $thFiles -notcontains $_.Name }
        $enKB = [math]::Round(($enFiles | Measure-Object Length -Sum).Sum / 1KB)
        $names = ($missing | ForEach-Object { $_.Name }) -join ','
        Write-Output ($m.Name + ' | en=' + $enFiles.Count + '/' + $enKB + 'KB | missing=' + $missing.Count + ' [' + $names + ']')
    }
}
Write-Output 'INVENTORY_DONE'