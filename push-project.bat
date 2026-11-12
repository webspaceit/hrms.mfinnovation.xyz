@echo off
setlocal

REM ===== Configuration =====
set "PROJECT_DIR=E:\wamp64\www\oop-rms"
set "SSH_KEY=C:\Users\sizub\.ssh\id_ed25519_mf"
set "REMOTE_BASE=/home/sites/27b/0/0b72e9e422/hrms.mfinnovation.xyz"
set "SSH_USER=mfinnovation.xyz@ssh.gb.stackcp.com"
set "TEMPDIR=%TEMP%"

cd /d "%PROJECT_DIR%" || (echo Cannot cd to project dir & pause & exit /b 1)

REM ===== Commit message =====
set "MSG="
set /p MSG="Commit message (blank = auto): "
if "%MSG%"=="" set "MSG=Update %date% %time%"

REM ===== Git: stage, commit, push =====
git add -A
git commit -m "%MSG%" 2>nul
git push origin main
if errorlevel 1 (echo Git push failed & pause & exit /b 1)

REM ===== Generate SFTP command file via PowerShell =====
set "SFTPCMD=%TEMPDIR%\push_sftp.txt"
powershell -NoProfile -Command ^
  "$files = git diff-tree --no-commit-id --name-only -r HEAD; " ^
  "$cmds = @(); " ^
  "foreach ($f in $files) { " ^
  "  if ([string]::IsNullOrWhiteSpace($f)) { continue }; " ^
  "  if ($f -match '^(config/config\.php|logs/|uploads/|\.git|oop-rms-deploy|_deploy-stage|push-project\.bat|deploy-live\.py|.*\.bat$)') { continue }; " ^
  "  $local = '%PROJECT_DIR%\' + ($f -replace '/','\'); " ^
  "  $remote = '%REMOTE_BASE%/' + $f; " ^
  "  $parent = $f -replace '/[^/]*$',''; " ^
  "  if ($parent -ne $f -and $parent -ne '') { $cmds += ('-mkdir %REMOTE_BASE%/' + $parent) }; " ^
  "  $cmds += ('put \"' + $local + '\" \"' + $remote + '\"'); " ^
  "}; " ^
  "$cmds += 'bye'; " ^
  "$cmds | Out-File -Encoding ASCII '%SFTPCMD%'"

REM ===== Upload via SFTP =====
sftp -b "%SFTPCMD%" -i "%SSH_KEY%" -o StrictHostKeyChecking=no %SSH_USER%
if errorlevel 1 echo SFTP upload had errors.

del "%SFTPCMD%" 2>nul

echo.
echo ===== Push complete =====
pause