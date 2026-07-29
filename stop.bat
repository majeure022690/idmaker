@echo off
setlocal

set "PORT=8000"

echo Stopping ID Maker server on port %PORT%...
for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":%PORT% " ^| findstr "LISTENING"') do (
    taskkill /PID %%p /F >nul 2>&1
)

echo Done. MySQL was left running ^(it's shared USBWebserver infrastructure --
echo stop it from the USBWebserver control panel/tray icon if you want it down too^).
pause
