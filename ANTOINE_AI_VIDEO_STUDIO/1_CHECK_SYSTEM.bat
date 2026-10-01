@echo off
rem Read-only system check
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\00_check_system.ps1" 
pause
