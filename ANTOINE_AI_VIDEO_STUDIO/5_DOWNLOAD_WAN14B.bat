@echo off
rem Download Wan 2.2 A14B fp8 (T2V + I2V, ~57 GB) from the official Comfy-Org repository
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\02_download_models.ps1" -Set wan14b_t2v
if errorlevel 1 goto end
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\02_download_models.ps1" -Set wan14b_i2v
:end
pause
