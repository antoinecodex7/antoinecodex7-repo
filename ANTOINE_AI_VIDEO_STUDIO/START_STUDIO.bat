@echo off
rem Start ComfyUI on 127.0.0.1 (free port from 8188), opens browser
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start_studio.ps1" 
pause
