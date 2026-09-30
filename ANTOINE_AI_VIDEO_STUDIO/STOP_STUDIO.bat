@echo off
rem Safely stop the studio ComfyUI server only
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\stop_studio.ps1" 
pause
