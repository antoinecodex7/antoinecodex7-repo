# Стартира студиото на localhost на първия свободен порт (от 8188 нагоре) и отваря браузъра.
param([switch]$NoBrowser, [int]$Port = 0)
. "$PSScriptRoot\common.ps1"
if (-not (Test-PortableInstalled)) { throw 'ComfyUI не е инсталиран. Пусни 2_INSTALL_COMFYUI.bat' }
$running = Get-RunningStudio
if ($running) {
    Write-Host "Студиото вече работи: $($running.url)" -ForegroundColor Green
    if (-not $NoBrowser) { Start-Process $running.url }
    return
}
if ($Port -eq 0) { $Port = Get-FreePort } elseif (Test-PortInUse $Port) { throw "Порт $Port е зает" }
$host.UI.RawUI.WindowTitle = "ANTOINE AI VIDEO STUDIO - http://127.0.0.1:$Port (Ctrl+C = спиране)"
$launchArgs = @('-s', (Join-Path $ToolsDir 'launch.py'), '--port', $Port, '--comfy-dir', $ComfyDir)
if ($NoBrowser) { $launchArgs += '--no-browser' }
& $PythonExe @launchArgs
