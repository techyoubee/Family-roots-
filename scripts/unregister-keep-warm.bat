@echo off
rem Remove the local Family Roots keep-warm task. Safe to run more than once.
set TASK=FamilyRootsKeepWarm
schtasks /Query /TN "%TASK%" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
  echo Task "%TASK%" is not registered; nothing to do.
  exit /b 0
)
schtasks /Delete /TN "%TASK%" /F
if %ERRORLEVEL% EQU 0 echo Removed "%TASK%".
