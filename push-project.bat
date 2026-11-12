@echo off
setlocal EnableExtensions
cd /d "%~dp0"
if errorlevel 1 goto failed

set "PROJECT_DIR=%~dp0"
set "SSH_KEY=%USERPROFILE%\.ssh\id_ed25519_mf"
set "REMOTE_BASE=/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz"
set "SSH_USER=mfinnovation.xyz@ssh.gb.stackcp.com"
set "SFTPCMD=%TEMP%\hrms_sftp_%RANDOM%.txt"

if not exist "%SSH_KEY%" (
    echo SSH key not found: %SSH_KEY%
    goto failed
)

echo === HRMS: GitHub push and SFTP deploy ===
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
REM Keep the machine-specific database config out of GitHub.
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
echo Preparing complete SFTP upload from tracked project files...
powershell -NoProfile -Command "$root = '%PROJECT_DIR%'; $base = '%REMOTE_BASE%'; $out = '%SFTPCMD%'; $skip = '^(config/config\.php|logs/|uploads/|tools/|\.env|\.gitignore$|push-project\.bat$|deploy-live\.py$|install\.php$|README\.md$|DEPLOY\.md$|.*\.sql$|.*\.zip$)'; $files = @(git -C $root ls-files | Where-Object { $_ -notmatch $skip -and (Test-Path -LiteralPath (Join-Path $root ($_ -replace '/','\')) -PathType Leaf) }); if ($LASTEXITCODE -ne 0) { exit 2 }; $dirs = @{}; $cmds = New-Object 'System.Collections.Generic.List[string]'; $q = [char]34; foreach ($f in $files) { $parts = $f -split '/'; $p = ''; for ($i = 0; $i -lt $parts.Length - 1; $i++) { $p = if ($p) { $p + '/' + $parts[$i] } else { $parts[$i] }; $dirs[$p] = $true } }; foreach ($d in ($dirs.Keys | Sort-Object { ($_ -split '/').Length })) { [void]$cmds.Add('-mkdir ' + $q + $base + '/' + $d + $q) }; foreach ($f in $files) { $local = Join-Path $root ($f -replace '/','\'); [void]$cmds.Add('put ' + $q + $local + $q + ' ' + $q + $base + '/' + $f + $q) }; [void]$cmds.Add('bye'); [IO.File]::WriteAllLines($out, $cmds, [Text.Encoding]::ASCII)"
if errorlevel 1 goto failed

echo Uploading app files to hosting...
sftp -b "%SFTPCMD%" -i "%SSH_KEY%" -o BatchMode=yes %SSH_USER%
if errorlevel 1 goto sftp_failed

del "%SFTPCMD%" 2>nul
echo.
echo GitHub push and hosting upload completed successfully.
goto end

:sftp_failed
del "%SFTPCMD%" 2>nul
echo.
echo SFTP upload failed. GitHub push completed, but hosting may be partially updated.
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
