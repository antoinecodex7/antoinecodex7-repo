@echo off
rem Real generation tests (sequential) + 720p Wan test
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\03_run_tests.ps1" -Include720p
pause
