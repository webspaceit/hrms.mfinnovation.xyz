@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
if errorlevel 1 (
    echo Cannot open the project folder.
    pause
    exit /b 1
)

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
set "SSH_KEY=%USERPROFILE%\.ssh\id_ed25519_mf"
set "SSH_USER=mfinnovation.xyz@ssh.gb.stackcp.com"
set "REMOTE_BASE=/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz"
set "REMOTE_STATE=/home/sites/27b/0/0b72e9e422/.hrms-rms-last-deploy"
set "INITIAL_DEPLOYED_COMMIT=182289e95885bf2d13ff03ad5922782378944331"
set "DEPLOY_SCRIPT=%~dp0tools\deploy_changed_files.ps1"
set "MSG_FILE=%TEMP%\hrms-commit-msg.txt"

echo ============================================================
echo  HRMS - GitHub push + changed-file SSH deploy
echo ============================================================
echo  Project : %PROJECT_DIR%
echo  Server  : %REMOTE_BASE%
echo.

rem ---------------------------------------------------------- prerequisites
set "MISSING="
for %%T in (git.exe ssh.exe scp.exe) do (
    where %%T >nul 2>nul || set "MISSING=!MISSING! %%T"
)
if defined MISSING (
    echo Missing required command^(s^):!MISSING!
    echo Install Git for Windows with the OpenSSH client, then retry.
    goto failed
)
if not exist "%SSH_KEY%" (
    echo SSH key not found: %SSH_KEY%
    goto failed
)
if not exist "%DEPLOY_SCRIPT%" (
    echo Incremental deploy helper not found: %DEPLOY_SCRIPT%
    goto failed
)

rem ------------------------------------------------------------- repo state
git rev-parse --is-inside-work-tree >nul 2>nul
if errorlevel 1 (
    echo This folder is not a Git working tree.
    goto failed
)
for /f "delims=" %%B in ('git rev-parse --abbrev-ref HEAD') do set "BRANCH=%%B"
echo  Branch  : !BRANCH!
echo.
git status --short
if errorlevel 1 goto failed
echo.

set "MSG="
set /p "MSG=Commit message (blank = Update project): "
if not defined MSG set "MSG=Update project"
for /f "tokens=* delims= " %%M in ("!MSG!") do set "MSG=%%M"
if not defined MSG set "MSG=Update project"

git add -A
if errorlevel 1 goto failed

rem Keep local database credentials out of GitHub.
if exist "config\config.php" (
    git restore --staged -- config/config.php
    if errorlevel 1 goto failed
)

git diff --cached --quiet
if errorlevel 2 goto failed
if errorlevel 1 goto commit
echo Nothing new to commit.
goto push

:commit
rem Commit through a file so &, |, <, > and % inside the message are safe.
> "%MSG_FILE%" echo(!MSG!
git commit -F "%MSG_FILE%"
if errorlevel 1 (
    del "%MSG_FILE%" >nul 2>nul
    goto failed
)
del "%MSG_FILE%" >nul 2>nul
echo.

:push
echo [1/2] Pushing to GitHub...
git push origin HEAD
if errorlevel 1 (
    echo.
    echo GitHub push failed. If the remote already moved ahead, run:
    echo     git pull --rebase
    echo then start this script again.
    goto failed
)

echo.
echo [2/2] Uploading only files changed since the last successful deployment...
powershell -NoProfile -ExecutionPolicy Bypass -File "%DEPLOY_SCRIPT%" -ProjectDir "%PROJECT_DIR%" -Server "%SSH_USER%" -Key "%SSH_KEY%" -RemoteBase "%REMOTE_BASE%" -RemoteState "%REMOTE_STATE%" -InitialCommit "%INITIAL_DEPLOYED_COMMIT%"
if errorlevel 1 goto deploy_failed

echo.
echo ============================================================
echo  DONE - GitHub push and hosting deployment completed.
echo ============================================================
set "RESULT=0"
goto finish

:deploy_failed
echo.
echo ============================================================
echo  GitHub push OK, but hosting deployment FAILED.
echo  The deploy marker was not advanced, so running this script
echo  again retries exactly the pending files. Nothing is lost.
echo ============================================================
set "RESULT=1"
goto finish

:failed
echo.
echo ============================================================
echo  FAILED - nothing was deployed. Review the message above.
echo ============================================================
set "RESULT=1"

:finish
echo.
pause
exit /b %RESULT%
