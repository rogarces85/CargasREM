@echo off
rem ============================================================
rem WebCargaREM — validador REM Servicio de Salud Osorno
rem Worker de colas para Windows Task Scheduler (TEC-05 / T-009)
rem
rem SkipIfRunning: revisa %LOCK%; si el PID vivo es php.exe NO
rem arranca otro worker (evita solapamiento entre triggers).
rem ============================================================
setlocal enableextensions
set PROYECTO=C:\xampp\htdocs\www\CargasREM
set LOCK=%PROYECTO%\storage\rem\worker.pid
set LOGDIR=%PROYECTO%\storage\logs

cd /d %PROYECTO%

if not exist "%LOGDIR%" mkdir "%LOGDIR%"

if exist "%LOCK%" (
  for /f %%p in ('type "%LOCK%"') do (
    if not "%%p"=="" (
      tasklist /FI "PID eq %%p" 2>NUL | find /I "php.exe" >NUL
      if not errorlevel 1 (
        echo [%date% %time%] SKIP: worker activo PID %%p
        echo [%date% %time%] SKIP: worker activo PID %%p >> "%LOGDIR%\worker_poc.log"
        exit /b 0
      )
    )
  )
  rem PID huérfano: limpiar y arrancar de nuevo
  del "%LOCK%" >NUL 2>&1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
 "Set-Location '%PROYECTO%';" ^
 "$p = Start-Process php -ArgumentList 'artisan','queue:work','database','--sleep=1','--tries=3','--max-time=3600' -PassThru -WindowStyle Hidden;" ^
 "$p.Id | Set-Content '%LOCK%' -NoNewline;" ^
 "Write-Output ('WORKER INICIADO PID ' + $p.Id)"

echo [%date% %time%] INICIADO >> "%LOGDIR%\worker_poc.log"
endlocal
exit /b 0
