# Спира текущата генерация (стандартния ComfyUI /interrupt). С -ClearQueue изчиства и чакащите задачи.
# Сървърът остава да работи; моделите не се изтриват.
param([switch]$ClearQueue)
. "$PSScriptRoot\common.ps1"
$s = Get-RunningStudio
if (-not $s) { Write-Host 'Студиото не работи.'; return }
$base = "http://$($ListenHost):$($s.port)"
if ($ClearQueue) {
    Invoke-RestMethod -Method Post -Uri "$base/queue" -ContentType 'application/json' -Body '{"clear": true}' | Out-Null
    Write-Host 'Опашката е изчистена.'
}
Invoke-RestMethod -Method Post -Uri "$base/interrupt" -ContentType 'application/json' -Body '{}' | Out-Null
Start-Sleep -Seconds 2
$q = Invoke-RestMethod -Uri "$base/queue"
Write-Host "Изпратен е сигнал за спиране. Изпълнява се: $($q.queue_running.Count), чакащи: $($q.queue_pending.Count)"
Write-Host 'Прекъсването настъпва в края на текущата стъпка на семплера (обикновено до няколко секунди).'
