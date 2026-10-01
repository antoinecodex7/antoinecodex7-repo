@echo off
rem AI agent: VIDEO 1 storyboard (6 scenes, Wan 2.2 5B image-to-video, auto-check, joined MP4)
echo 1 = DRAFT 480p  (~1.5 min per scene, ~10 min total)
echo 2 = FINAL 720p  (~4.5 min per scene, ~30 min total)
set /p Q=Choose 1 or 2: 
set QUAL=draft
if "%Q%"=="2" set QUAL=final
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\run_storyboard.ps1" -Storyboard storyboards\video1_mechtata.json -Quality %QUAL%
pause
