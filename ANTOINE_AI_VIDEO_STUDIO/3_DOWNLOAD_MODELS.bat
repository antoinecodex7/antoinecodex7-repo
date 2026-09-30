@echo off
rem Download model weights: first Wan 2.2 TI2V 5B, then LTX-Video 2B 0.9.8 distilled
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\02_download_models.ps1" -Set wan
if errorlevel 1 goto end
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\02_download_models.ps1" -Set ltx
:end
pause
