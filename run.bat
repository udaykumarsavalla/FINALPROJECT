@echo off
title CarePulse AI - Server Launcher
echo =======================================================
echo CarePulse AI - Intelligent Telehealth Platform
echo =======================================================
echo.
echo Starting Services if needed...

rem Start MariaDB on port 3307 if not listening
netstat -ano | findstr :3307 | findstr LISTENING >nul
if errorlevel 1 (
    echo Starting MariaDB on port 3307...
    start "" /B C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
    timeout /t 2 /nobreak >nul
)

rem Start Apache on port 80 if not listening
netstat -ano | findstr :80 | findstr LISTENING >nul
if errorlevel 1 (
    echo Starting Apache on port 80...
    start "" /B C:\xampp\apache\bin\httpd.exe
    timeout /t 2 /nobreak >nul
)

echo.
echo =======================================================
echo CarePulse AI is LIVE!
echo Main Project URL: http://localhost/carepulse-ai/
echo =======================================================
echo.
echo Launching your browser...
start http://localhost/carepulse-ai/
echo.
pause
