@echo off
REM Starts the high-speed KPI server (IPv4 + IPv6 localhost:8000 + built-in 30-min auto sync in one process).
cd /d "%~dp0"
start "KGOLD KPI Server" cmd /k node serve-fast.mjs
timeout /t 2 >nul
start http://localhost:8000
