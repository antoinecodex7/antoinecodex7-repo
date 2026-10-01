# Стъпка 2: инсталиране на официалния ComfyUI Windows Portable (NVIDIA) в студио папката.
# - Не пипа глобален Python, системни драйвери, PATH, firewall или други приложения.
# - Ако portable вече съществува, НЕ го презаписва - само проверява версиите и GPU.
param([switch]$SkipGpuTest)
. "$PSScriptRoot\common.ps1"
Assert-StudioLocation
$stamp = Get-Stamp
Start-Transcript -Path (Join-Path $LogsDir "install_$stamp.log") | Out-Null
$info = [ordered]@{ time = (Get-Date).ToString('s') }

if (Test-PortableInstalled) {
    Write-Step "ComfyUI portable вече е в $PortableDir - не се презаписва"
    $info.install = 'existing'
} else {
    if (Test-Path $PortableDir) {
        throw "$PortableDir съществува, но не прилича на завършена инсталация. Провери го ръчно - скриптът не изтрива нищо."
    }
    Write-Step 'Търсене на последния официален release (github.com/Comfy-Org/ComfyUI)'
    $rel = Invoke-RestMethod -Uri 'https://api.github.com/repos/Comfy-Org/ComfyUI/releases/latest' -Headers @{ 'User-Agent' = 'antoine-ai-video-studio' }
    $asset = $rel.assets | Where-Object { $_.name -eq 'ComfyUI_windows_portable_nvidia.7z' } | Select-Object -First 1
    if (-not $asset) { throw "В release $($rel.tag_name) няма ComfyUI_windows_portable_nvidia.7z" }
    $info.release_tag = $rel.tag_name
    $info.asset = $asset.name
    $info.asset_url = $asset.browser_download_url
    $info.asset_size = $asset.size
    $info.asset_digest = $asset.digest
    Write-Host "Release $($rel.tag_name), $($asset.name), $([math]::Round($asset.size/1GB,2)) GB, digest $($asset.digest)"

    $free = (Get-PSDrive ((Split-Path -Qualifier $StudioRoot).TrimEnd(':'))).Free
    if ($free -lt ($asset.size * 4 + 5GB)) { throw "Недостатъчно място за архива и разархивирането ($([math]::Round($free/1GB,1)) GB свободни)" }

    $archive = Join-Path $DownloadsDir $asset.name
    if ((Test-Path $archive) -and ((Get-Item $archive).Length -eq $asset.size)) {
        Write-Host 'Архивът вече е изтеглен.'
    } else {
        Write-Step 'Изтегляне (curl.exe с възобновяване)'
        & curl.exe -L --fail --retry 3 -C - -o $archive $asset.browser_download_url
        if ($LASTEXITCODE -ne 0) { throw "curl.exe завърши с код $LASTEXITCODE" }
    }
    if ((Get-Item $archive).Length -ne $asset.size) { throw 'Размерът на архива не съвпада с release-а' }
    if ($asset.digest -and $asset.digest.StartsWith('sha256:')) {
        Write-Host 'Проверка SHA256 ...'
        $h = (Get-FileHash $archive -Algorithm SHA256).Hash.ToLower()
        if ($h -ne $asset.digest.Substring(7).ToLower()) { throw "SHA256 не съвпада: $h" }
        $info.sha256_verified = $true
        Write-Host 'SHA256 OK'
    } else {
        $info.sha256_verified = $false
        Write-Host 'GitHub не дава digest за този asset - проверен е само размерът.' -ForegroundColor Yellow
    }
    Unblock-File $archive

    Write-Step 'Разархивиране'
    $tmp = Join-Path $DownloadsDir "extract_$stamp"
    New-Item -ItemType Directory $tmp | Out-Null
    $sevenZip = @("$env:ProgramFiles\7-Zip\7z.exe", "${env:ProgramFiles(x86)}\7-Zip\7z.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
    if ($sevenZip) { & $sevenZip x $archive "-o$tmp" -y | Out-Host }
    else { & tar.exe -xf $archive -C $tmp }
    $extracted = Get-ChildItem $tmp -Directory | Select-Object -First 1
    if ($LASTEXITCODE -ne 0 -or -not $extracted) {
        throw "Разархивирането не успя. Разархивирай ръчно $archive (Explorer или 7-Zip) така, че да се получи $PortableDir, и пусни скрипта отново."
    }
    Move-Item $extracted.FullName $PortableDir
    Remove-Item $tmp -Recurse -Force   # само нашата временна празна папка
    $info.install = 'new'
}

Write-Step 'Версии'
$ver = & $PythonExe -s -c "import sys,torch,importlib.metadata as m;print(sys.version.split()[0]);print(torch.__version__);print(torch.version.cuda);print(m.version('comfyui-frontend-package'))"
$cv = Select-String -Path (Join-Path $ComfyDir 'comfyui_version.py') -Pattern '__version__\s*=\s*"(.+)"' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$info.python = $ver[0]; $info.torch = $ver[1]; $info.torch_cuda = $ver[2]; $info.frontend = $ver[3]; $info.comfyui = $cv
$info.source = 'https://github.com/Comfy-Org/ComfyUI/releases (ComfyUI_windows_portable_nvidia.7z)'
$info | Format-List

Write-Step 'Папки и workflows'
# Именуваните workflows се виждат в менюто Workflows на ComfyUI (user\default\workflows).
$wfTarget = Join-Path $UserDir 'default\workflows'
New-Item -ItemType Directory -Force $wfTarget | Out-Null
Get-ChildItem (Join-Path $WorkflowsDir 'ui') -Filter *.json | ForEach-Object {
    $dst = Join-Path $wfTarget $_.Name
    if (-not (Test-Path $dst)) { Copy-Item $_.FullName $dst; Write-Host "добавен workflow $($_.Name)" }
    elseif ((Get-FileHash $dst).Hash -ne (Get-FileHash $_.FullName).Hash) { Write-Host "ПРОПУСНАТ (има твоя променена версия): $dst" -ForegroundColor Yellow }
}

if (-not $SkipGpuTest) {
    Write-Step 'Реален GPU тест (matmul fp32/fp16/bf16, conv3d, attention)'
    & $PythonExe -s (Join-Path $ToolsDir 'gpu_test.py')
    $info.gpu_test_exit = $LASTEXITCODE
    if ($LASTEXITCODE -ne 0) { Write-Host 'GPU ТЕСТЪТ Е НЕУСПЕШЕН - виж logs\gpu_test_*.json. Не продължавай с моделите преди да се изясни.' -ForegroundColor Red }
}
$info | ConvertTo-Json | Set-Content (Join-Path $LogsDir 'install_info.json') -Encoding UTF8
Stop-Transcript | Out-Null
