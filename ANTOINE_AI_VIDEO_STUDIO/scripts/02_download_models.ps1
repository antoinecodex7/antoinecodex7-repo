# Стъпка 3: изтегляне на моделите - по един набор наведнъж.
#   .\02_download_models.ps1 -Set wan              (Wan 2.2 TI2V 5B)
#   .\02_download_models.ps1 -Set ltx              (LTX-Video 2B 0.9.8 distilled)
#   .\02_download_models.ps1 -Set wan21_fallback   (САМО при доказан проблем с Wan 2.2)
#   добави -DryRun, за да видиш само плана, размерите и лицензите.
param(
    [Parameter(Mandatory = $true)][ValidateSet('wan', 'ltx', 'wan21_fallback')][string]$Set,
    [switch]$DryRun
)
. "$PSScriptRoot\common.ps1"
if (-not (Test-PortableInstalled)) { throw 'Първо пусни 01_install_comfyui.ps1' }
$stamp = Get-Stamp
Start-Transcript -Path (Join-Path $LogsDir "download_$($Set)_$stamp.log") | Out-Null

# Други ComfyUI инсталации (от 00_check_system) - използват се САМО за четене, за да не се дублират файлове.
$also = @()
$existing = Join-Path $LogsDir 'existing_comfyui.json'
if (Test-Path $existing) {
    $also = @(Get-Content $existing -Raw -Encoding UTF8 | ConvertFrom-Json | ForEach-Object { $_.models } | Where-Object { $_ })
}
$pyArgs = @('-s', (Join-Path $ToolsDir 'download_models.py'), '--comfy-dir', $ComfyDir, '--set', $Set)
if ($also.Count) { $pyArgs += '--also-search'; $pyArgs += $also }
if ($DryRun) { $pyArgs += '--dry-run' }
& $PythonExe @pyArgs
$code = $LASTEXITCODE
Stop-Transcript | Out-Null
exit $code
