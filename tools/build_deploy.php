<?php
/**
 * tools/build_deploy.php — package the app for shared hosting.
 *
 * Copies the working tree into a staging folder, drops everything that
 * must not go to a live server, writes a production config/config.php,
 * then zips the result. Run it from anywhere:
 *
 *   php tools/build_deploy.php
 *   php tools/build_deploy.php --base-url=/rms/
 *   php tools/build_deploy.php --base-url=/ --db-user=acct_rms --db-pass=secret
 *
 * Without the --db-* options the generated config keeps YOUR_DB_USER
 * style placeholders, so the uploader only has to fill in four values.
 *
 * Deliberately NOT included:
 *   .git/ .kilo/ .commandcode/      VCS + AI/editor tooling metadata
 *   .gitignore                       VCS metadata
 *   logs/*                           dev error log
 *   uploads/* (files)                dev tenants' documents and signatures
 *   tools/                           this script
 *   uploads.zip, *.zip               stray archives
 *   service_charge_multi_month_upgrade.sql
 *                                    superseded by db_alter_upgrade.sql
 *   config/config.php                regenerated as a production config
 *   config/config.production.example.php
 *                                    build-time template, not used at runtime
 *
 * Always review the staging folder (printed at the end) before uploading.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/** Written into config/ and logs/ so neither folder can ever be listed or downloaded. */
const DENY_HTACCESS = <<<'HTACCESS'
# Deny all direct web access to this folder.
#
# config/ holds the database password; logs/ holds error.log, which can
# reveal file paths, SQL fragments and session details. Both are only ever
# touched by PHP, never requested from a browser.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>

HTACCESS;

// ------------------------------------------------------------
// Options
// ------------------------------------------------------------
$opt = [
    'base-url' => '/',
    'db-host'  => null,   // null -> keep the placeholder
    'db-user'  => null,
    'db-pass'  => null,
    'db-name'  => null,
    'out'      => $root . '/oop-rms-deploy.zip',
    'no-zip'   => false,
];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--no-zip') {
        $opt['no-zip'] = true;
        continue;
    }
    if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$k, $v] = explode('=', substr($arg, 2), 2);
        if (array_key_exists($k, $opt)) {
            $opt[$k] = $v;
        } else {
            warn("unknown option --$k (ignored)");
        }
        continue;
    }
    warn("ignoring unexpected argument: $arg");
}

// Normalise BASE_URL to exactly one leading and one trailing slash.
$baseUrl = '/' . trim((string)$opt['base-url'], '/') . '/';
$baseUrl = rtrim($baseUrl, '/') . '/';
$opt['base-url'] = $baseUrl;

$stage = $root . '/_deploy-stage/rms';

// ------------------------------------------------------------
// Clean the staging folder
// ------------------------------------------------------------
rrmdir($root . '/_deploy-stage');
mkdir($stage, 0755, true);

// ------------------------------------------------------------
// Copy the app
// ------------------------------------------------------------
$skipDirs = [
    '.git', '.kilo', '.commandcode', '.github', 'node_modules', 'tools', '_deploy-stage',
    // Uploaded documents are per-tenant data, not application code.
    'uploads',
    // Dev logs.
    'logs',
];
$skipFiles = [
    '.gitignore', '.gitattributes',
    'uploads.zip',
    // The build output must not embed itself.
    basename($opt['out']),
    // Scratch files from ad-hoc debugging ( _foo.php, _foo.sql, ... ).
    // These would be web-reachable at the root if they ever leaked in.
    '_deploy-stage',
];
$skipPaths = [
    // path relative to the app root
    'config/config.php',
    // Build-time template only; the generated config.php is what runs.
    'config/config.production.example.php',
];

$copied = 0;
$skipped = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($it as $item) {
    $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen($root) + 1);
    if ($rel === '' || $rel === false) {
        continue;
    }
    $top = explode('/', $rel)[0];

    // uploads/ and logs/ hold per-tenant data and the dev error log, not
    // application code. Reproduce the folder tree so the app can write on
    // a fresh server, but carry over only the .htaccess guards that make
    // those folders safe.
    if (in_array($top, $skipDirs, true)) {
        if (in_array($top, ['uploads', 'logs'], true)) {
            if ($item->isDir()) {
                ensureDir($stage . '/' . $rel);
            } elseif ($item->getFilename() === '.htaccess') {
                ensureDir(dirname($stage . '/' . $rel));
                copyFile($item->getPathname(), $stage . '/' . $rel);
                $copied++;
            }
        }
        continue;
    }

    $name = $item->getFilename();
    if (!$item->isDir()) {
        if (in_array($name, $skipFiles, true) || in_array($rel, $skipPaths, true)) {
            $skipped[] = $rel;
            continue;
        }
        // Scratch files left behind by ad-hoc debugging (_check.php, _x.sql).
        if (str_starts_with($name, '_')) {
            $skipped[] = $rel;
            continue;
        }
        if (str_ends_with(strtolower($name), '.zip')) {
            $skipped[] = $rel;
            continue;
        }
        // One-off upgrade scripts superseded by db_alter_upgrade.sql.
        if (str_ends_with($rel, '_upgrade.sql') && $rel !== 'db_alter_upgrade.sql') {
            $skipped[] = $rel;
            continue;
        }
    }

    if ($item->isDir()) {
        ensureDir($stage . '/' . $rel);
    } else {
        ensureDir(dirname($stage . '/' . $rel));
        copyFile($item->getPathname(), $stage . '/' . $rel);
        $copied++;
    }
}

// ------------------------------------------------------------
// Writable folders, guaranteed present and empty
// ------------------------------------------------------------
foreach (['logs', 'uploads/signatures', 'uploads/tenants'] as $dir) {
    ensureDir($stage . '/' . $dir);
    // A stray index.html stops directory listings even where Options
    // -Indexes is not honoured.
    file_put_contents($stage . '/' . $dir . '/index.html', '');
}

// The deny rules for config/ and logs/ are written here rather than copied,
// because /logs/ is gitignored - a fresh clone has no logs/.htaccess to
// copy, and silently shipping the folder unprotected would expose the error
// log. (uploads/*/.htaccess blocks PHP execution and IS copied by the walk.)
foreach (['config', 'logs'] as $dir) {
    file_put_contents($stage . '/' . $dir . '/.htaccess', DENY_HTACCESS);
}

// ------------------------------------------------------------
// Production config
// ------------------------------------------------------------
$template = file_get_contents($root . '/config/config.production.example.php');
if ($template === false) {
    fail('could not read config/config.production.example.php');
}

$replacements = [
    'YOUR_DB_USER'     => (string)$opt['db-user'],
    'YOUR_DB_PASSWORD' => (string)$opt['db-pass'],
    'YOUR_DB_NAME'     => (string)$opt['db-name'],
];
$production = $template;
foreach ($replacements as $placeholder => $value) {
    if ($value === '' || $value === null) {
        continue;
    }
    $production = str_replace(
        "define('$placeholder', '$placeholder');",
        "define('$placeholder', '" . addslashes($value) . "');",
        $production
    );
}

// BASE_URL
$production = preg_replace(
    "/define\('BASE_URL', '[^']*'\);/",
    "define('BASE_URL', '" . addslashes($baseUrl) . "');",
    $production,
    1
);

// Mark which values still need filling in.
$todo = [];
foreach ($replacements as $placeholder => $value) {
    if (str_contains($production, $placeholder)) {
        $todo[] = $placeholder;
    }
}
$banner = "// ----------------------------------------------------------------\n"
    . "// GENERATED by tools/build_deploy.php - edit on the SERVER, not here.\n"
    . "// config.production.example.php is the untouched template.\n";
if ($todo) {
    $banner .= "// Still to fill in: " . implode(', ', $todo) . "\n";
} else {
    $banner .= "// All values supplied at build time.\n";
}
$banner .= "// ----------------------------------------------------------------\n";

// The banner MUST go after the opening tag. Anything before <?php is raw
// output, and config.php is required_once by every request, so a banner
// placed in front of the tag would be echoed into the top of every page.
$openTag = "<?php\n";
if (!str_starts_with($production, $openTag)) {
    fail('config.production.example.php does not start with <?php');
}
file_put_contents(
    $stage . '/config/config.php',
    $openTag . $banner . substr($production, strlen($openTag))
);

// ------------------------------------------------------------
// Report
// ------------------------------------------------------------
echo "Staging folder: $stage\n";
echo "Files copied:   $copied\n";
if ($skipped) {
    echo "\nExcluded from the package:\n";
    foreach ($skipped as $s) {
        echo "  - $s\n";
    }
}
echo "\nAlso excluded by design: .git/ .kilo/ .commandcode/ tools/ logs/* uploads/*\n";

$leaked = auditSecrets($stage);
if ($leaked) {
    echo "\nWARNING - these look like development leftovers in the package:\n";
    foreach ($leaked as $l) {
        echo "  ! $l\n";
    }
} else {
    echo "\nSecret scan: clean (no dev DB credentials found in the package).\n";
}

if ($todo) {
    echo "\nNEXT: fill in " . implode(', ', $todo) . " in config/config.php on the server.\n";
}
echo "      BASE_URL in the package is set to $baseUrl\n";

if ($opt['no-zip']) {
    echo "\n--no-zip given, stopping at the staging folder.\n";
    exit(0);
}

if (!class_exists('ZipArchive')) {
    warn('the PHP zip extension is not loaded - cannot create the archive.');
    warn('zip the staging folder by hand: ' . $stage);
    exit(1);
}

@unlink($opt['out']);
$zip = new ZipArchive();
if ($zip->open($opt['out'], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('could not create ' . $opt['out']);
}
$zip->addEmptyDir('rms');
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($it as $item) {
    $rel = 'rms/' . substr(str_replace('\\', '/', $item->getPathname()), strlen($stage) + 1);
    $item->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($item->getPathname(), $rel);
}
$zip->close();
rrmdir($root . '/_deploy-stage');

echo "\nPackage written: " . $opt['out'] . "\n";
echo 'Size: ' . round(filesize($opt['out']) / 1024) . " KB (staging folder removed)\n\n";
echo "Upload steps are in DEPLOY.md.\n";

// ==============================================================
// Helpers
// ==============================================================

/**
 * Scan the package for values that only make sense on a dev machine.
 * Cheap, but it is the last line of defence against shipping a
 * database password to a public web root.
 *
 * @return list<string>
 */
function auditSecrets(string $stage): array
{
    $hits = [];
    $needles = [
        'dev DB password'  => "define('DB_PASS', '')",
        'dev DB name'      => "define('DB_NAME', 'oop_rms')",
        'dev base url'     => "define('BASE_URL', '/oop-rms/')",
        'dev db user root' => "define('DB_USER', 'root')",
    ];
    $exts = ['php', 'sql', 'js', 'md', 'htaccess'];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        $ext = strtolower($name);
        if (!in_array($ext, $exts, true) && !str_ends_with($name, '.htaccess')) {
            continue;
        }
        $body = (string)file_get_contents($file->getPathname());
        foreach ($needles as $label => $needle) {
            if (str_contains($body, $needle)) {
                $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen($stage) + 1);
                $hits[] = "$label in $rel";
            }
        }
    }
    return array_values(array_unique($hits));
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function copyFile(string $from, string $to): void
{
    if (!copy($from, $to)) {
        fail("could not copy $from");
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function warn(string $msg): void
{
    fwrite(STDERR, "Warning: $msg\n");
}

function fail(string $msg): never
{
    fwrite(STDERR, "Error: $msg\n");
    exit(1);
}
