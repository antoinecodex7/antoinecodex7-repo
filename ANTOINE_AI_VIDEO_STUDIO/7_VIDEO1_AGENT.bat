@echo off
rem AI agent: VIDEO 1 storyboard (6 scenes, Wan 2.2 5B image-to-video, text lock, auto-check, joined MP4)
echo 0 = PREVIEW: show crop and locked text boxes (no generation, seconds)
echo 1 = DRAFT 480p  (~1.5 min per scene, ~10 min total)
echo 2 = FINAL 720p  (~4.5 min per scene, ~30 min total)
set /p Q=Choose 0, 1 or 2: 
if "%Q%"=="0" (
  powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\run_storyboard.ps1" -Storyboard storyboards\video1_mechtata.json -Quality final -PreviewLocks
  goto end
)
set QUAL=draft
if "%Q%"=="2" set QUAL=final
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\run_storyboard.ps1" -Storyboard storyboards\video1_mechtata.json -Quality %QUAL%
:end
pause
