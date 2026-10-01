# Стъпка 4: реалните тестове - последователно, никога паралелно.
#   по подразбиране: smoke (без модел) -> Wan T2V 480p -> Wan I2V 480p -> LTX T2V 480p -> тест за спиране
#   -Include720p : след успешен Wan T2V 480p пуска и един Wan T2V 720p
#   -Tests wan_t2v,wan_i2v : само избрани тестове
param(
    [string[]]$Tests = @('smoke_no_model', 'wan_t2v', 'wan_i2v', 'ltx_t2v'),
    [switch]$Include720p,
    [switch]$SkipInterruptTest,
    [string]$Image = ''
)
. "$PSScriptRoot\common.ps1"
# от .bat (-File) списъкът идва като един низ 'a,b' -> разделяме го
$Tests = @($Tests | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ })
if (-not (Test-PortableInstalled)) { throw 'Първо пусни 2_INSTALL_COMFYUI.bat' }

# --- Ollama: показваме заетата памет; разтоварване САМО с твое потвърждение, моделите НЕ се изтриват.
if (Get-Command ollama -ErrorAction SilentlyContinue) {
    $ps = & ollama ps
    $loaded = @($ps | Select-Object -Skip 1 | Where-Object { $_.Trim() } | ForEach-Object { ($_ -split '\s+')[0] })
    if ($loaded.Count) {
        Write-Host 'Ollama държи заредени модели (заемат VRAM/RAM):' -ForegroundColor Yellow
        $ps | Out-Host
        Write-Host 'Разтоварването освобождава паметта, без да изтрива модела (ollama stop). Ако в момента ползваш Ollama/Open WebUI, отговори N.'
        $ans = Read-Host 'Да разтоваря ли тези модели от паметта? (y/N)'
        if ($ans -match '^(y|yes|д|да)$') {
            foreach ($m in $loaded) { & ollama stop $m; Write-Host "разтоварен: $m" }
        } else { Write-Host 'Оставям Ollama както е - тестовете ще ползват останалата памет (ComfyUI прави offload при нужда).' }
    }
}
if (Get-Command nvidia-smi -ErrorAction SilentlyContinue) {
    & nvidia-smi --query-gpu=memory.used,memory.free,memory.total --format=csv
}

# --- стартиране на сървъра, ако не работи (в отделен видим прозорец)
$s = Get-RunningStudio
if (-not $s) {
    Write-Step 'Стартиране на ComfyUI в отделен прозорец'
    Start-Process powershell.exe -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $PSScriptRoot 'start_studio.ps1'))
    for ($i = 0; $i -lt 180 -and -not $s; $i++) { Start-Sleep 2; $s = Get-RunningStudio }
    if (-not $s) { throw 'ComfyUI не стартира до 6 минути - виж logs\comfyui_*.log' }
}
Write-Host "ComfyUI: $($s.url)" -ForegroundColor Green

$runner = Join-Path $ToolsDir 'run_tests.py'
$argsList = @('-s', $runner, '--port', $s.port, '--tests') + $Tests
if (-not $SkipInterruptTest) { $argsList += '--interrupt-test' }
if ($Image) { $argsList += @('--image', $Image) }
& $PythonExe @argsList
$code = $LASTEXITCODE

if ($Include720p) {
    $last = Get-ChildItem $LogsDir -Filter 'test_results_*.json' | Sort-Object LastWriteTime | Select-Object -Last 1
    $r = Get-Content $last.FullName -Raw -Encoding UTF8 | ConvertFrom-Json
    $wan = $r.results | Where-Object { $_.test -eq 'wan_t2v' -and $_.status -eq 'success' }
    if ($wan) {
        Write-Step 'Wan 2.2 T2V 720p (1280x704, 121 кадъра)'
        & $PythonExe -s $runner --port $s.port --tests wan_t2v_720p
    } else { Write-Host '720p тестът е пропуснат: Wan T2V 480p не е успешен в последното пускане.' -ForegroundColor Yellow }
}
Write-Host ''
Write-Host "Резултати: $(Join-Path $StudioRoot 'TEST_RESULTS.md')" -ForegroundColor Green
Write-Host 'Отвори MP4 файловете и contact sheet PNG-тата и провери визуално качеството и движението.'
exit $code
