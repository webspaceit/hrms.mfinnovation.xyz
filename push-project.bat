@echo off
setlocal EnableExtensions
cd /d "%~dp0"
if errorlevel 1 goto failed

set "PROJECT_DIR=%~dp0"
set "SSH_KEY=%USERPROFILE%\.ssh\id_ed25519_mf"
set "SSH_USER=mfinnovation.xyz@ssh.gb.stackcp.com"
set "REMOTE_BASE=/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz"
set "REMOTE_ARCHIVE=/tmp/hrms-deploy-%RANDOM%.zip"
set "DEPLOY_ZIP=%TEMP%\hrms-deploy-%RANDOM%.zip"

if not exist "%SSH_KEY%" (
    echo SSH key not found: %SSH_KEY%
    goto failed
)

echo === HRMS: GitHub push and SSH deploy ===
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
echo Building production archive...
php tools\build_scp_package.php "%DEPLOY_ZIP%"
if errorlevel 1 goto failed

echo.
echo Uploading archive with legacy SCP over SSH...
scp -O -i "%SSH_KEY%" -o BatchMode=yes "%DEPLOY_ZIP%" %SSH_USER%:%REMOTE_ARCHIVE%
if errorlevel 1 goto scp_failed

echo.
echo Extracting files into the verified hosting root...
ssh -i "%SSH_KEY%" -o BatchMode=yes %SSH_USER% "python3 - %REMOTE_ARCHIVE%" < "%~dp0deploy-remote.py"
if errorlevel 1 goto deploy_failed

del "%DEPLOY_ZIP%" 2>nul
echo.
echo GitHub push and hosting deployment completed successfully.
goto end

:scp_failed
del "%DEPLOY_ZIP%" 2>nul
echo.
echo SCP upload failed. GitHub push completed, but hosting was not updated.
goto failed

:deploy_failed
del "%DEPLOY_ZIP%" 2>nul
echo.
echo Remote extraction failed. GitHub push completed; check server output.
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
