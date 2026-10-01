# Копира пакета (скриптове, tools, workflows, inputs, документация) в целевата папка.
# Нищо съществуващо не се презаписва: при различно съдържание новият файл се записва като *.kit_new
# и се показва в отчета - ти решаваш кой да остане.
param([string]$Target = 'C:\AI\ANTOINE_AI_VIDEO_STUDIO')
$ErrorActionPreference = 'Stop'
$src = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if ((Resolve-Path $src).Path.TrimEnd('\') -ieq $Target.TrimEnd('\')) { Write-Host "Пакетът вече е в $Target."; return }
if (Test-Path $Target) {
    Write-Host "$Target вече съществува. Съдържание:" -ForegroundColor Yellow
    Get-ChildItem $Target -Force | Format-Table Mode, LastWriteTime, Length, Name -AutoSize
} else { New-Item -ItemType Directory -Path $Target | Out-Null }

$added = 0; $same = 0; $conflicts = @()
Get-ChildItem $src -Recurse -File | Where-Object { $_.FullName -notmatch '\\(logs|outputs|downloads|ComfyUI_windows_portable)\\' } | ForEach-Object {
    $rel = $_.FullName.Substring($src.Length).TrimStart('\')
    $dst = Join-Path $Target $rel
    $dir = Split-Path $dst
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    if (-not (Test-Path $dst)) { Copy-Item $_.FullName $dst; $added++ }
    elseif ((Get-FileHash $dst).Hash -eq (Get-FileHash $_.FullName).Hash) { $same++ }
    else { Copy-Item $_.FullName "$dst.kit_new"; $conflicts += $rel }
}
foreach ($d in 'logs', 'outputs', 'inputs', 'workflows\api', 'workflows\ui', 'user', 'downloads') {
    $p = Join-Path $Target $d; if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
}
Write-Host "Добавени: $added, еднакви: $same, конфликти: $($conflicts.Count)" -ForegroundColor Green
if ($conflicts.Count) {
    Write-Host 'Тези файлове вече съществуват с ДРУГО съдържание - оставени са непокътнати, новата версия е *.kit_new:' -ForegroundColor Yellow
    $conflicts | ForEach-Object { Write-Host "  $_" }
}
Write-Host "Следваща стъпка: $Target\1_CHECK_SYSTEM.bat"
