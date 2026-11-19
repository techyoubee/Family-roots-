@echo off
rem ---------------------------------------------------------------------------
rem Family Roots - register the LOCAL keep-warm scheduled task.
rem
rem Runs scripts/keep-warm.php every 3 minutes so Apache/PHP/MySQL pages stay
rem resident on this memory-constrained Windows box. This is a dev-only
rem workaround, not an application change and not for production hosts.
rem
rem Right-click ^-> "Run as administrator" if registration is denied.
rem To undo:   scripts\unregister-keep-warm.bat
rem ---------------------------------------------------------------------------
setlocal
set TASK=FamilyRootsKeepWarm
set PHP=C:\AppServ\php7\php.exe
set SCRIPT=%~dp0keep-warm.php

if not exist "%PHP%" (
  echo PHP not found at %PHP%
  exit /b 1
)
if not exist "%SCRIPT%" (
  echo keep-warm.php not found at %SCRIPT%
  exit /b 1
)

schtasks /Delete /TN "%TASK%" /F >nul 2>&1

schtasks /Create /TN "%TASK%" /TR "\"%PHP%\" \"%SCRIPT%\"" /SC MINUTE /MO 3 /F
if %ERRORLEVEL% NEQ 0 (
  echo.
  echo Registration failed. Re-run this file as Administrator.
  exit /b %ERRORLEVEL%
)

echo.
echo Registered "%TASK%" every 3 minutes.
echo Run it now:   schtasks /Run /TN "%TASK%"
echo Check status: schtasks /Query /TN "%TASK%" /FO LIST
echo Remove it:    scripts\unregister-keep-warm.bat
endlocal
