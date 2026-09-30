# Общи настройки и помощни функции. Зарежда се с: . "$PSScriptRoot\common.ps1"
# Съвместим с Windows PowerShell 5.1 и PowerShell 7.
$ErrorActionPreference = 'Stop'
$StudioRoot   = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$PortableDir  = Join-Path $StudioRoot 'ComfyUI_windows_portable'
$ComfyDir     = Join-Path $PortableDir 'ComfyUI'
$PythonExe    = Join-Path $PortableDir 'python_embeded\python.exe'
$LogsDir      = Join-Path $StudioRoot 'logs'
$InputsDir    = Join-Path $StudioRoot 'inputs'
$OutputsDir   = Join-Path $StudioRoot 'outputs'
$WorkflowsDir = Join-Path $StudioRoot 'workflows'
$UserDir      = Join-Path $StudioRoot 'user'
$DownloadsDir = Join-Path $StudioRoot 'downloads'
$ToolsDir     = Join-Path $StudioRoot 'tools'
$PidFile      = Join-Path $LogsDir 'comfyui_server.json'
$ListenHost   = '127.0.0.1'   # само localhost - никога 0.0.0.0

foreach ($d in @($LogsDir, $InputsDir, $OutputsDir, $UserDir, $DownloadsDir)) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Path $d | Out-Null }
}

# Python изход на кирилица в конзолата без UnicodeEncodeError
$env:PYTHONUTF8 = '1'
$env:PYTHONIOENCODING = 'utf-8'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

function Get-Stamp { Get-Date -Format 'yyyyMMdd_HHmmss' }

function Write-Step([string]$Text) {
    Write-Host ''
    Write-Host "=== $Text" -ForegroundColor Cyan
}

function Test-PortableInstalled {
    return (Test-Path $PythonExe) -and (Test-Path (Join-Path $ComfyDir 'main.py'))
}

function Test-PortInUse([int]$Port) {
    try {
        $c = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
        return [bool]$c
    } catch {
        $l = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $Port)
        try { $l.Start(); $l.Stop(); return $false } catch { return $true }
    }
}

function Get-FreePort([int]$Start = 8188, [int]$End = 8299) {
    for ($p = $Start; $p -le $End; $p++) { if (-not (Test-PortInUse $p)) { return $p } }
    throw "Няма свободен порт в диапазона $Start-$End"
}

function Get-RunningStudio {
    # Връща @{pid; port; url} само ако НАШИЯТ ComfyUI процес работи и отговаря.
    if (-not (Test-Path $PidFile)) { return $null }
    $info = Get-Content $PidFile -Raw -Encoding UTF8 | ConvertFrom-Json
    $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$($info.pid)" -ErrorAction SilentlyContinue
    if (-not $proc -or ($proc.CommandLine -notlike "*$ComfyDir*main.py*")) { return $null }
    try {
        Invoke-RestMethod -Uri "http://$($ListenHost):$($info.port)/system_stats" -TimeoutSec 5 | Out-Null
        return $info
    } catch { return $null }
}
