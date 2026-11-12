param(
    [Parameter(Mandatory = $true)][string]$ProjectDir,
    [Parameter(Mandatory = $true)][string]$Server,
    [Parameter(Mandatory = $true)][string]$Key,
    [Parameter(Mandatory = $true)][string]$RemoteBase,
    [Parameter(Mandatory = $true)][string]$RemoteState,
    [Parameter(Mandatory = $true)][string]$InitialCommit
)

$ErrorActionPreference = 'Stop'

$head = (& git -C $ProjectDir rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $head -notmatch '^[0-9a-f]{40}$') {
    throw 'Could not read the current Git commit.'
}

$stateCommand = "if [ -f '$RemoteState' ]; then cat '$RemoteState'; else printf '%s\n' '$InitialCommit'; fi"
$baseOutput = & ssh -i $Key -o BatchMode=yes $Server $stateCommand
if ($LASTEXITCODE -ne 0) {
    throw 'Could not read the last deployed commit from hosting.'
}
$base = ($baseOutput | Out-String).Trim()
if ($base -notmatch '^[0-9a-f]{40}$') {
    throw "Invalid deployed commit marker returned by hosting: $base"
}

$changes = @(& git -C $ProjectDir diff --name-status --no-renames $base $head)
if ($LASTEXITCODE -ne 0) {
    throw "Could not compare deployed commit $base with local commit $head."
}

$skip = '^(config/config\.php|config/config\.production\.example\.php|logs/|uploads/|tools/|\.env|\.gitignore$|push-project\.bat$|deploy-live\.py$|deploy-remote\.py$|install\.php$|README\.md$|DEPLOY\.md$|.*\.sql$|.*\.zip$)'
$uploaded = 0
$removed = 0

foreach ($line in $changes) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    $parts = $line -split "`t", 2
    if ($parts.Count -ne 2) { throw "Unexpected git diff entry: $line" }
    $status = $parts[0]
    $relative = $parts[1].Replace('\', '/')
    if ($relative -notmatch '^[A-Za-z0-9_./-]+$' -or $relative -match '(^/|(^|/)\.\.(/|$))') {
        throw "Refusing unsafe project path: $relative"
    }
    if ($relative -match $skip) { continue }

    $remote = $RemoteBase.TrimEnd('/') + '/' + $relative
    if ($status -eq 'D') {
        Write-Host "Removing $relative"
        & ssh -i $Key -o BatchMode=yes $Server "rm -f -- '$remote'"
        if ($LASTEXITCODE -ne 0) { throw "Remote delete failed: $relative" }
        $removed++
        continue
    }

    $local = Join-Path $ProjectDir ($relative.Replace('/', '\'))
    if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
        throw "Changed file is missing locally: $relative"
    }
    $parent = $remote.Substring(0, $remote.LastIndexOf('/'))
    Write-Host "Uploading $relative"
    & ssh -i $Key -o BatchMode=yes $Server "mkdir -p -- '$parent'"
    if ($LASTEXITCODE -ne 0) { throw "Could not create remote folder for: $relative" }
    & scp -O -i $Key -o BatchMode=yes $local "${Server}:$remote"
    if ($LASTEXITCODE -ne 0) { throw "SCP upload failed: $relative" }
    $uploaded++
}

$markerCommand = "printf '%s\n' '$head' > '$RemoteState.tmp' && mv '$RemoteState.tmp' '$RemoteState'"
& ssh -i $Key -o BatchMode=yes $Server $markerCommand
if ($LASTEXITCODE -ne 0) {
    throw 'Files transferred, but the hosting deploy marker could not be updated.'
}

Write-Host "DEPLOY_OK: $uploaded file(s) uploaded, $removed file(s) removed; commit $head"
