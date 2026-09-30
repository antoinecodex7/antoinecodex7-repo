@echo off
rem Interrupt the running generation (server keeps running)
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\stop_task.ps1" 
pause
