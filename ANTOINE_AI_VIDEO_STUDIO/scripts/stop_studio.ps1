# Безопасно спиране на НАШИЯ ComfyUI сървър: първо прекъсва задачата, после спира само процеса,
# чийто команден ред сочи към студиото. Други програми (Ollama, Docker, Open WebUI...) не се пипат.
. "$PSScriptRoot\common.ps1"
$s = Get-RunningStudio
if (-not $s) { Write-Host 'Студиото не работи.'; return }
try { Invoke-RestMethod -Method Post -Uri "http://$($ListenHost):$($s.port)/interrupt" -ContentType 'application/json' -Body '{}' | Out-Null } catch { }
Start-Sleep -Seconds 2
$proc = Get-CimInstance Win32_Process -Filter "ProcessId=$($s.pid)"
if ($proc -and $proc.CommandLine -like "*$ComfyDir*main.py*") {
    Stop-Process -Id $s.pid
    Write-Host "Спрян ComfyUI процес $($s.pid) (порт $($s.port))." -ForegroundColor Green
} else {
    Write-Host 'Процесът не отговаря на очаквания команден ред - нищо не е спряно.' -ForegroundColor Yellow
}
