<?php
/** Build a production ZIP for push-project.bat without local/private data. */
declare(strict_types=1);

$root = dirname(__DIR__);
$output = $argv[1] ?? '';
if ($output === '') {
    fwrite(STDERR, "Usage: php tools/build_scp_package.php <output.zip>\n");
    exit(2);
}

$process = proc_open(
    ['git', '-C', $root, 'ls-files', '-z'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not list tracked project files.\n");
    exit(2);
}
fclose($pipes[0]);
$listed = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0) {
    fwrite(STDERR, $errors ?: "Git file listing failed.\n");
    exit(2);
}

$skip = static function (string $file): bool {
    return $file === 'config/config.php'
        || $file === 'config/config.production.example.php'
        || $file === '.gitignore'
        || $file === 'install.php'
        || $file === 'README.md'
        || $file === 'DEPLOY.md'
        || $file === 'push-project.bat'
        || $file === 'deploy-live.py'
        || $file === 'deploy-remote.py'
        || str_starts_with($file, 'logs/')
        || str_starts_with($file, 'uploads/')
        || str_starts_with($file, 'tools/')
        || preg_match('/(^|\/)\.env(?:\.|$)/i', $file) === 1
        || preg_match('/\.(?:sql|zip)$/i', $file) === 1;
};

$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create deployment archive: {$output}\n");
    exit(2);
}

$count = 0;
foreach (explode("\0", (string)$listed) as $relative) {
    if ($relative === '' || $skip($relative)) {
        continue;
    }
    $relative = str_replace('\\', '/', $relative);
    if (str_starts_with($relative, '/') || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
        fwrite(STDERR, "Unsafe project path skipped: {$relative}\n");
        continue;
    }
    $local = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($local)) {
        continue; // Deleted files are not uploaded; the package never deletes live data.
    }
    if (!$zip->addFile($local, $relative)) {
        fwrite(STDERR, "Could not add {$relative} to archive.\n");
        $zip->close();
        exit(2);
    }
    $count++;
}

if (!$zip->close() || $count === 0) {
    fwrite(STDERR, "Deployment archive is empty or could not be finalized.\n");
    exit(2);
}

echo "Packaged {$count} application files.\n";
