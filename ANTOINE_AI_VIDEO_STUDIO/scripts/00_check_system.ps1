# Стъпка 1: САМО ЧЕТЕНЕ - проверка на машината. Нищо не се инсталира и не се променя.
. "$PSScriptRoot\common.ps1"
$ErrorActionPreference = 'Continue'
$stamp = Get-Stamp
$txt = Join-Path $LogsDir "system_check_$stamp.txt"
$report = [ordered]@{ time = (Get-Date).ToString('s'); studio_root = $StudioRoot }
Start-Transcript -Path $txt | Out-Null

Write-Step 'Операционна система, CPU, RAM'
$os  = Get-CimInstance Win32_OperatingSystem
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
$cs  = Get-CimInstance Win32_ComputerSystem
$report.os  = "$($os.Caption) $($os.Version) build $($os.BuildNumber)"
$report.cpu = "$($cpu.Name.Trim()) - $($cpu.NumberOfCores) ядра / $($cpu.NumberOfLogicalProcessors) нишки"
$report.ram_total_gb = [math]::Round($cs.TotalPhysicalMemory / 1GB, 1)
$report.ram_free_gb  = [math]::Round($os.FreePhysicalMemory * 1KB / 1GB, 1)
$report.pagefile = (Get-CimInstance Win32_PageFileUsage | ForEach-Object { "$($_.Name) $($_.AllocatedBaseSize) MB" }) -join '; '
[pscustomobject]@{ OS = $report.os; CPU = $report.cpu; RAM_total_GB = $report.ram_total_gb; RAM_free_GB = $report.ram_free_gb; Pagefile = $report.pagefile } | Format-List

Write-Step 'NVIDIA GPU и драйвер'
$nvsmi = Get-Command nvidia-smi -ErrorAction SilentlyContinue
if ($nvsmi) {
    $q = & nvidia-smi --query-gpu=name,driver_version,memory.total,memory.used,memory.free,compute_cap,temperature.gpu,utilization.gpu --format=csv,noheader,nounits
    $f = ($q | Select-Object -First 1).Split(',') | ForEach-Object { $_.Trim() }
    $report.gpu = [ordered]@{ name = $f[0]; driver = $f[1]; vram_total_mib = [int]$f[2]; vram_used_mib = [int]$f[3];
                              vram_free_mib = [int]$f[4]; compute_cap = $f[5]; temp_c = $f[6]; util_pct = $f[7] }
    [pscustomobject]$report.gpu | Format-List
    $hdr = (& nvidia-smi) -join "`n"
    if ($hdr -match 'CUDA Version:\s*([\d\.]+)') { $report.gpu.driver_max_cuda = $Matches[1] }
    Write-Host $hdr
    $drvMajor = [int]($f[1].Split('.')[0])
    # ComfyUI portable (nvidia) идва с PyTorch cu130 -> нужен е драйвер от клона R580 или по-нов.
    $report.gpu.driver_ok_for_cu130 = ($drvMajor -ge 580)
    if (-not $report.gpu.driver_ok_for_cu130) {
        Write-Host "ВНИМАНИЕ: драйвер $($f[1]) е по-стар от R580, нужен за PyTorch CUDA 13.0. Обновяване на драйвера изисква ТВОЕ одобрение - скриптовете НЕ го правят." -ForegroundColor Yellow
    }
    $report.gpu_processes = & nvidia-smi --query-compute-apps=pid,process_name,used_memory --format=csv,noheader
} else {
    $report.gpu = 'nvidia-smi не е намерен'
    Write-Host 'nvidia-smi не е намерен - NVIDIA драйверът не е инсталиран или не е в PATH.' -ForegroundColor Red
}

Write-Step 'Дисково пространство'
$drive = (Split-Path -Qualifier $StudioRoot)
$disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='$drive'"
$report.disk = [ordered]@{ drive = $drive; free_gb = [math]::Round($disk.FreeSpace / 1GB, 1); total_gb = [math]::Round($disk.Size / 1GB, 1) }
[pscustomobject]$report.disk | Format-List
# ~2 GB архив + ~7 GB разархивиран portable + Wan ~17 GB + LTX ~16 GB + 20 GB резерв за кеш/изходи
$report.disk.needed_estimate_gb = 62
if ($report.disk.free_gb -lt 62) { Write-Host "ВНИМАНИЕ: препоръчително е поне ~62 GB свободни на $drive" -ForegroundColor Yellow }

Write-Step 'Съществуващи ComfyUI инсталации (само четене)'
$found = @()
$candidates = @()
if (Test-Path 'C:\AI') { $candidates += Get-ChildItem 'C:\AI' -Directory -ErrorAction SilentlyContinue | ForEach-Object { $_.FullName } }
$candidates += @("$env:USERPROFILE\ComfyUI", "$env:USERPROFILE\Documents\ComfyUI", "$env:USERPROFILE\Desktop",
                 "$env:LOCALAPPDATA\Programs\ComfyUI", "$env:LOCALAPPDATA\Programs\@comfyorgcomfyui-electron", 'C:\ComfyUI', 'D:\ComfyUI', 'D:\AI')
foreach ($c in ($candidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -Unique)) {
    Get-ChildItem $c -Recurse -Depth 3 -Filter 'comfyui_version.py' -ErrorAction SilentlyContinue | ForEach-Object {
        $dir = $_.DirectoryName
        if ($dir -notlike "$StudioRoot*") {
            $models = Join-Path $dir 'models'
            $found += [ordered]@{ path = $dir; models = $(if (Test-Path $models) { $models } else { $null }) }
        }
    }
}
$deskCfg = "$env:APPDATA\ComfyUI\config.json"
if (Test-Path $deskCfg) {
    try {
        $cfg = Get-Content $deskCfg -Raw | ConvertFrom-Json
        if ($cfg.basePath -and (Test-Path (Join-Path $cfg.basePath 'models'))) {
            $found += [ordered]@{ path = "$($cfg.basePath) (ComfyUI Desktop)"; models = (Join-Path $cfg.basePath 'models') }
        }
    } catch { }
}
$report.existing_comfyui = $found
if ($found.Count) { $found | ForEach-Object { Write-Host " - $($_.path)  models: $($_.models)" } } else { Write-Host 'Не са намерени други ComfyUI инсталации.' }
$found | ConvertTo-Json -Depth 4 | Set-Content (Join-Path $LogsDir 'existing_comfyui.json') -Encoding UTF8

Write-Step 'Заети портове (слушащи)'
$listen = Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Sort-Object LocalPort -Unique |
    ForEach-Object { $p = Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue
                     [pscustomobject]@{ Port = $_.LocalPort; Address = $_.LocalAddress; Process = $p.ProcessName; PID = $_.OwningProcess } }
$interesting = $listen | Where-Object { $_.Port -in 3000, 5000, 7860, 8000, 8080, 8188, 8189, 8190, 11434 -or ($_.Port -ge 8188 -and $_.Port -le 8299) }
$interesting | Format-Table -AutoSize
$report.listening_ports = $listen
$report.suggested_port = Get-FreePort
Write-Host "Първи свободен порт за студиото: $($report.suggested_port)"

Write-Step 'Ollama (само четене)'
if (Get-Command ollama -ErrorAction SilentlyContinue) {
    $report.ollama_version = (& ollama --version) -join ' '
    $report.ollama_loaded  = (& ollama ps) -join "`n"
    Write-Host $report.ollama_version
    Write-Host $report.ollama_loaded
} else { Write-Host 'ollama CLI не е в PATH' }

Write-Step 'Docker и WSL (само четене)'
if (Get-Command docker -ErrorAction SilentlyContinue) {
    $report.docker_running = (& docker ps --format '{{.Names}}  {{.Image}}  {{.Ports}}' 2>&1) -join "`n"
    Write-Host $report.docker_running
}
if (Get-Command wsl.exe -ErrorAction SilentlyContinue) {
    $report.wsl = ((& wsl.exe -l -v 2>&1) -join "`n") -replace "`0", ''
    Write-Host $report.wsl
}

Write-Step 'Глобален Python (само информация - НЕ се използва)'
$report.global_python = (Get-Command python -All -ErrorAction SilentlyContinue | ForEach-Object { $_.Source }) -join '; '
Write-Host $report.global_python

Write-Step 'Съществуващо съдържание на студио папката'
$report.studio_existing = Get-ChildItem $StudioRoot -Force | ForEach-Object { $_.Name }
Write-Host ($report.studio_existing -join ', ')

$json = Join-Path $LogsDir "system_check_$stamp.json"
$report | ConvertTo-Json -Depth 6 | Set-Content $json -Encoding UTF8
Stop-Transcript | Out-Null
Write-Host ''
Write-Host "Отчет: $txt" -ForegroundColor Green
Write-Host "JSON:  $json" -ForegroundColor Green
