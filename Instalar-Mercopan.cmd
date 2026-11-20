@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\instalar.ps1"
set "resultado=%errorlevel%"
pause
exit /b %resultado%
