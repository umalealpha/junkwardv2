@echo off
REM PyEngine runner for Windows scheduled tasks
REM Usage: run.bat [job_key] [--email] [--fix]
REM   run.bat written_premium --email
REM   run.bat ageing
REM   run.bat anomaly --fix

SET PYTHON=C:\Users\ADMIN\AppData\Local\Programs\Python\Python312\python.exe
SET CWD=%~dp0..

cd /d "%CWD%"

IF "%1"=="" (
    %PYTHON% -m pyengine.engine
) ELSE (
    %PYTHON% -m pyengine.engine --job %*
)
