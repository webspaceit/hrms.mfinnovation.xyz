param(
    [Parameter(Mandatory = $true)][string]$ProjectDir,
    [Parameter(Mandatory = $true)][string]$Server,
    [Parameter(Mandatory = $true)][string]$Key,
    [Parameter(Mandatory = $true)][string]$RemoteBase,
    [Parameter(Mandatory = $true)][string]$RemoteState,
    [Parameter(Mandatory = $true)][string]$InitialCommit,
    [int]$Attempts = 4,
    [int]$RetryDelaySeconds = 5
)

$ErrorActionPreference = 'Stop'

# ------------------------------------------------------------
# SSH can fail transiently: the shared hosting endpoint CNAMEs to
# a node (ssh-node-gb.hosting.stackcp.net) and that node, a local
# firewall, or the host's brute-force blocker can drop port 22 for
# a moment. Every remote call therefore retries with backoff, and
# a hard failure reports what was and was not changed instead of
# dying on the first packet loss.
# ------------------------------------------------------------

$SshOptions = @(
    '-o', 'BatchMode=yes',
    '-o', 'ConnectTimeout=15',
    '-o', 'ConnectionAttempts=1',
    '-o', 'ServerAliveInterval=15',
    '-o', 'ServerAliveCountMax=3',
    '-o', 'StrictHostKeyChecking=accept-new',
    '-4'
)

function Invoke-Remote {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string]$What,
        [int]$MaxAttempts = $Attempts
    )

    for ($i = 1; $i -le $MaxAttempts; $i++) {
        $output = & ssh -i $Key @SshOptions $Server $Command 2>&1
        if ($LASTEXITCODE -eq 0) {
            return [pscustomobject]@{ Ok = $true; Output = $output }
        }
        $err = (($output | Out-String).Trim())
        Write-Warning ("SSH attempt {0}/{1} failed for {2}: {3}" -f $i, $MaxAttempts, $What, $err)
        if ($i -lt $MaxAttempts) { Start-Sleep -Seconds ($RetryDelaySeconds * $i) }
    }

    return [pscustomobject]@{ Ok = $false; Output = $err }
}

function Send-File {
    param(
        [Parameter(Mandatory = $true)][string]$LocalPath,
        [Parameter(Mandatory = $true)][string]$RemotePath,
        [int]$MaxAttempts = $Attempts
    )

    for ($i = 1; $i -le $MaxAttempts; $i++) {
        $output = & scp -O -i $Key @SshOptions $LocalPath "${Server}:$RemotePath" 2>&1
        if ($LASTEXITCODE -eq 0) { return $true }
        $err = (($output | Out-String).Trim())
        Write-Warning ("SCP attempt {0}/{1} failed for {2}: {3}" -f $i, $MaxAttempts, $RemotePath, $err)
        if ($i -lt $MaxAttempts) { Start-Sleep -Seconds ($RetryDelaySeconds * $i) }
    }

    return $false
}

$head = (& git -C $ProjectDir rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $head -notmatch '^[0-9a-f]{40}$') {
    throw 'Could not read the current Git commit.'
}

# ---- preflight: is the host answering on port 22 at all? ----
$ping = Invoke-Remote -Command 'echo DEPLOY_LINK_OK' -What 'connectivity preflight' -MaxAttempts 2
if (-not $ping.Ok -or (($ping.Output | Out-String) -notmatch 'DEPLOY_LINK_OK')) {
    $detail = (($ping.Output | Out-String).Trim())
    throw ("Cannot reach {0} over SSH. Nothing was uploaded and the deploy marker still points at the previous commit. " +
        "Check the hosting firewall / IP allow-list, that port 22 is not blocked on this network, then rerun. Detail: {1}") -f $Server, $detail
}

$stateCommand = "if [ -f '$RemoteState' ]; then cat '$RemoteState'; else printf '%s\n' '$InitialCommit'; fi"
$baseResult = Invoke-Remote -Command $stateCommand -What 'read deploy marker'
if (-not $baseResult.Ok) {
    throw ("SSH is reachable, but the deployed-commit marker at {0} could not be read. Nothing was uploaded; the marker was not advanced. Detail: {1}") -f $RemoteState, (($baseResult.Output | Out-String).Trim())
}

$base = (($baseResult.Output | Out-String)).Trim()
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
$failed = @()

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
        $del = Invoke-Remote -Command "rm -f -- '$remote'" -What "delete $relative"
        if (-not $del.Ok) { $failed += "delete $relative"; continue }
        $removed++
        continue
    }

    $local = Join-Path $ProjectDir ($relative.Replace('/', '\'))
    if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
        throw "Changed file is missing locally: $relative"
    }

    $parent = $remote.Substring(0, $remote.LastIndexOf('/'))
    Write-Host "Uploading $relative"
    $mkdir = Invoke-Remote -Command "mkdir -p -- '$parent'" -What "prepare folder for $relative"
    if (-not $mkdir.Ok) { $failed += "mkdir for $relative"; continue }

    if (-not (Send-File -LocalPath $local -RemotePath $remote)) {
        $failed += $relative
        continue
    }
    $uploaded++
}

if ($failed.Count -gt 0) {
    # Do not advance the marker: the next run must retry exactly these files.
    throw ("Uploaded {0}, removed {1}, but {2} item(s) failed: {3}. The deploy marker was not advanced, so rerunning retries the pending files." -f $uploaded, $removed, $failed.Count, ($failed -join ', '))
}

$markerCommand = "printf '%s\n' '$head' > '$RemoteState.tmp' && mv '$RemoteState.tmp' '$RemoteState'"
$marker = Invoke-Remote -Command $markerCommand -What 'advance deploy marker'
if (-not $marker.Ok) {
    throw ("All {0} file(s) transferred, but the deploy marker could not be advanced. Rerunning with header {1} would re-upload the same files, which is safe." -f $uploaded, $head)
}

Write-Host "DEPLOY_OK: $uploaded file(s) uploaded, $removed file(s) removed; commit $head"
