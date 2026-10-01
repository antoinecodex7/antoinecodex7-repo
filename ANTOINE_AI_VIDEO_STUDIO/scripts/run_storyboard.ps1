# AI агент за сценарий: генерира сцените една по една, проверява ги и ги слепва в един MP4.
#   .\run_storyboard.ps1 -Storyboard storyboards\video1_mechtata.json -Quality draft|final [-Only 03_namira_saita,05_zaedno]
param(
    [string]$Storyboard = 'storyboards\video1_mechtata.json',
    [ValidateSet('draft', 'final')][string]$Quality = 'draft',
    [string[]]$Only = @(),
    [string]$Resume = ''
)
. "$PSScriptRoot\common.ps1"
if (-not (Test-PortableInstalled)) { throw 'Първо пусни 2_INSTALL_COMFYUI.bat' }
$Only = @($Only | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ })
$sbPath = if ([System.IO.Path]::IsPathRooted($Storyboard)) { $Storyboard } else { Join-Path $StudioRoot $Storyboard }

$s = Get-RunningStudio
if (-not $s) {
    Write-Step 'Стартиране на ComfyUI в отделен прозорец'
    Start-Process powershell.exe -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $PSScriptRoot 'start_studio.ps1'), '-NoBrowser')
    for ($i = 0; $i -lt 180 -and -not $s; $i++) { Start-Sleep 2; $s = Get-RunningStudio }
    if (-not $s) { throw 'ComfyUI не стартира - виж logs\comfyui_*.log' }
}
Write-Host "ComfyUI: $($s.url)  (прогресът се вижда и там)" -ForegroundColor Green
$a = @('-s', (Join-Path $ToolsDir 'run_storyboard.py'), '--port', $s.port, '--storyboard', $sbPath, '--quality', $Quality)
if ($Only.Count) { $a += '--only'; $a += $Only }
if ($Resume) { $a += @('--resume', $Resume) }
if ($PreviewLocks) { $a += '--preview-locks' }
& $PythonExe @a
$last = Get-ChildItem (Join-Path $OutputsDir 'storyboards') -Directory -ErrorAction SilentlyContinue | Sort-Object LastWriteTime | Select-Object -Last 1
if ($last) { Write-Host "Резултат: $($last.FullName)" -ForegroundColor Green; Start-Process explorer.exe $last.FullName }
