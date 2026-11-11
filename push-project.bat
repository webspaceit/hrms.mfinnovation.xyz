@echo off
setlocal
cd /d "%~dp0"
if errorlevel 1 goto failed

set "KEY=%USERPROFILE%\.ssh\id_ed25519_mf"
set "SERVER=mfinnovation.xyz@ssh.gb.stackcp.com"

if not exist "%KEY%" (
    echo SSH key not found: %KEY%
    goto failed
)
if not exist "%~dp0deploy-live.py" (
    echo Deploy script not found: deploy-live.py
    goto failed
)

echo === HRMS: push and deploy ===
git status --short
git add -A
if errorlevel 1 goto failed

git diff --cached --quiet
if errorlevel 1 goto commit
goto push

:commit
git commit -m "Update project"
if errorlevel 1 goto failed

:push
git push origin main
if errorlevel 1 goto failed

ssh -i "%KEY%" -o BatchMode=yes %SERVER% "python3" < "%~dp0deploy-live.py"
if errorlevel 1 (
    echo.
    echo Server deploy failed. The GitHub push has already completed.
    goto failed
)

echo.
echo Git push and server deploy completed successfully.
goto end

:failed
echo.
echo Push or deploy failed. Review the message above.

:end
echo.
pause
