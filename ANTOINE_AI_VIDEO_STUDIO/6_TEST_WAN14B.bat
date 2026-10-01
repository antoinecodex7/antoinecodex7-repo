@echo off
rem Real test: Wan 2.2 A14B text-to-video and image-to-video 480p (slow, quality mode)
set /p IMG=Input image file name in inputs (Enter = official example): 
if "%IMG%"=="" (
  powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\03_run_tests.ps1" -Tests wan14b_t2v,wan14b_i2v -SkipInterruptTest
) else (
  powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\03_run_tests.ps1" -Tests wan14b_t2v,wan14b_i2v -SkipInterruptTest -Image "%IMG%"
)
pause
