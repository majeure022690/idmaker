@echo off
setlocal

cd /d "%~dp0"
set "USBWS=%~dp0..\.."
set "PHP_EXE=%USBWS%\php\php.exe"
set "MYSQLD_EXE=%USBWS%\mysql\bin\mysqld_usbwv8.exe"
set "MYSQL_INI=%USBWS%\mysql\my.ini"
set "PORT=8000"

if not exist "%PHP_EXE%" (
    echo Could not find PHP at "%PHP_EXE%".
    echo This script expects to live at usbwebserver\root\id_maker.
    pause
    exit /b 1
)

if not exist "public\build\manifest.json" (
    echo Frontend assets are not built yet ^(public\build is missing^).
    echo Run "npm run build" from this folder once ^(needs Node.js^), then run start.bat again.
    pause
    exit /b 1
)

echo Checking MySQL...
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1',3306)).Close(); exit 0 } catch { exit 1 }" >nul 2>&1
if errorlevel 1 (
    echo Starting MySQL...
    start "ID Maker - MySQL" /min "%MYSQLD_EXE%" --defaults-file="%MYSQL_INI%"
    echo Waiting for MySQL to accept connections...
    for /l %%i in (1,1,15) do (
        powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1',3306)).Close(); exit 0 } catch { exit 1 }" >nul 2>&1
        if not errorlevel 1 goto mysql_ready
        timeout /t 1 /nobreak >nul
    )
    echo MySQL did not come up in time. Check the "ID Maker - MySQL" window for errors.
    pause
    exit /b 1
) else (
    echo MySQL already running.
)
:mysql_ready

echo Starting ID Maker server on http://127.0.0.1:%PORT%/ ...
start "ID Maker - Server" /min "%PHP_EXE%" artisan serve --host=127.0.0.1 --port=%PORT%

timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:%PORT%/"

echo.
echo ID Maker is running at http://127.0.0.1:%PORT%/
echo Keep the "ID Maker - Server" window open ^(it's minimized to the taskbar^).
echo Run stop.bat to shut it down.
echo.
pause
