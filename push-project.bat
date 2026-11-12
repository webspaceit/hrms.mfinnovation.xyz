@echo off
setlocal EnableExtensions
cd /d "%~dp0"
if errorlevel 1 goto failed

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
set "SSH_KEY=%USERPROFILE%\.ssh\id_ed25519_mf"
set "SSH_USER=mfinnovation.xyz@ssh.gb.stackcp.com"
set "REMOTE_BASE=/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz"
set "REMOTE_STATE=/home/sites/27b/0/0b72e9e422/.hrms-rms-last-deploy"
set "INITIAL_DEPLOYED_COMMIT=182289e95885bf2d13ff03ad5922782378944331"
set "DEPLOY_SCRIPT=%~dp0tools\deploy_changed_files.ps1"

if not exist "%SSH_KEY%" (
    echo SSH key not found: %SSH_KEY%
    goto failed
)
if not exist "%DEPLOY_SCRIPT%" (
    echo Incremental deploy helper not found: %DEPLOY_SCRIPT%
    goto failed
)

echo === HRMS: GitHub push and changed-file SSH deploy ===
echo Project: %PROJECT_DIR%
echo Server:  %REMOTE_BASE%
echo.
git status --short
if errorlevel 1 goto failed

set "MSG="
set /p "MSG=Commit message (blank = Update project): "
if not defined MSG set "MSG=Update project"

git add -A
if errorlevel 1 goto failed
REM Keep local database credentials out of GitHub.
git restore --staged -- config/config.php
if errorlevel 1 goto failed

git diff --cached --quiet
if errorlevel 2 goto failed
if errorlevel 1 goto commit
goto push

:commit
git commit -m "%MSG%"
if errorlevel 1 goto failed

:push
git push origin main
if errorlevel 1 goto failed

echo.
echo Uploading only files changed since the last successful deployment...
powershell -NoProfile -ExecutionPolicy Bypass -File "%DEPLOY_SCRIPT%" -ProjectDir "%PROJECT_DIR%" -Server "%SSH_USER%" -Key "%SSH_KEY%" -RemoteBase "%REMOTE_BASE%" -RemoteState "%REMOTE_STATE%" -InitialCommit "%INITIAL_DEPLOYED_COMMIT%"
if errorlevel 1 goto deploy_failed

echo.
echo GitHub push and changed-file hosting deployment completed successfully.
goto end

:deploy_failed
echo.
echo GitHub push completed, but hosting deployment failed. The deploy marker was not advanced; rerunning will retry pending files.
goto failed

:failed
echo.
echo Push or deployment failed. Review the message above.
set "RESULT=1"
goto finish

:end
set "RESULT=0"

:finish
echo.
pause
exit /b %RESULT%
