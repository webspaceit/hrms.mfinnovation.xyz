<?php
// ============================================================
// Installer - Create database schema and admin user
// Visit: /oop-rms/install.php
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php'; // for Database::prefix() on direct queries

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
function post($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

$step = isset($_GET['step']) ? $_GET['step'] : 'welcome';
$errors = [];
$success = [];
// Set by the action handlers below. The state re-check further down must not
// override a step an action already chose - without this, creating the admin
// immediately re-locks the installer and the success page is never shown.
$stepChosenByAction = false;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    // Security guard: once an admin user exists the system is considered
    // installed, and both the schema install and the admin-creation actions
    // are refused unless the operator supplies a secret configured in
    // config/config.php (INSTALL_TOKEN). Without this, anyone could POST
    // action=create_admin to overwrite the admin password.
    $installToken   = defined('INSTALL_TOKEN') ? INSTALL_TOKEN : '';
    $installTokenOk = $installToken !== '' && hash_equals($installToken, post('install_token', ''));

    $alreadyInstalled = false;
    try {
        $guardPdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $alreadyInstalled = (bool)$guardPdo->query(Database::prefix("SELECT COUNT(*) FROM users WHERE role = 'admin'"))->fetchColumn();
    } catch (Exception $e) {
        // Database not reachable / not installed yet - allow the action to proceed.
    }

    if ($alreadyInstalled && !$installTokenOk && ($action === 'install' || $action === 'create_admin')) {
        $errors[] = 'Installer is locked: the system is already installed. '
            . 'To reinstall, define INSTALL_TOKEN in config/config.php and submit it as install_token.';
        $action = '';
    }

    if ($action === 'install') {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            // Read SQL file and execute after removing CREATE DATABASE lines
            $schemaFile = __DIR__ . '/database.sql';
            if (!is_file($schemaFile)) {
                throw new Exception('database.sql is missing from the server. '
                    . 'Upload it next to install.php (it ships with the package).');
            }
            $sql = file_get_contents($schemaFile);
            $sql = preg_replace('/^\s*CREATE\s+DATABASE.*$/im', '', $sql);
            // Drop the hardcoded USE `oop_rms` line too - the connection is
            // already pointed at DB_NAME, and a leftover USE of a different
            // name (e.g. cPanel's user_dbname) would abort the import.
            $sql = preg_replace('/^\s*USE\s+`[^`]+`.*$/im', '', $sql);
            $pdo->exec("USE `" . DB_NAME . "`");
            $pdo->exec($sql);

            $success[] = 'Database `' . DB_NAME . '` created and all tables installed successfully.';
            $step = 'admin';
            $stepChosenByAction = true;
        } catch (Exception $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }

    if ($action === 'create_admin') {
        $username = post('username', 'admin');
        $password = post('password');
        $confirm = post('confirm_password');
        $email = post('email', 'admin@rms.com');
        $full_name = post('full_name', 'System Admin');

        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match';
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(Database::prefix(
                    "INSERT INTO users (username, email, password, full_name, role)
                     VALUES (:u, :e, :p, :fn, 'admin')
                     ON DUPLICATE KEY UPDATE password = :p2, email = :e2, full_name = :fn2"
                ));
                $stmt->execute([
                    'u' => $username,
                    'e' => $email,
                    'p' => $hash,
                    'fn' => $full_name,
                    'p2' => $hash,
                    'e2' => $email,
                    'fn2' => $full_name
                ]);
                $success[] = 'Admin user "' . $username . '" created successfully. You can now login.';
                $step = 'done';
                $stepChosenByAction = true;
            } catch (Exception $e) {
                $errors[] = 'Error creating admin: ' . $e->getMessage();
            }
        }
    }
}

// Check if DB exists and whether the schema is already installed
$dbExists = false;
$schemaExists = false;
$adminExists = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    $dbExists = (bool)$stmt->fetch();
    if ($dbExists) {
        $pdo->exec("USE `" . DB_NAME . "`");
        // The schema counts as installed once the users table is there, so an
        // admin can be created without re-importing (e.g. when database.sql was
        // imported by hand through phpMyAdmin).
        $schemaExists = (bool)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . Database::prefix('users') . "'"
        )->fetchColumn();
        if ($schemaExists) {
            $adminExists = (bool)$pdo->query(Database::prefix("SELECT COUNT(*) FROM users"))->fetchColumn();
        }
    }
} catch (Exception $e) {
    // not connected
}

// Work out which step to show from the current database state. Skipped when
// an action in this same request already picked a step, otherwise the admin
// we just created would immediately lock the installer.
if (!$stepChosenByAction) {
    if ($dbExists && $adminExists && (!isset($_GET['force']) || $_GET['force'] !== 'yes')) {
        // Already installed: require an explicit force=yes to re-run, so a
        // stray visit cannot wipe the data.
        $step = 'locked';
    } elseif ($schemaExists) {
        // Tables are in place, only the admin user is left to create.
        $step = 'admin';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer - Rent Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .install-card { max-width: 560px; margin: 60px auto; }
        .step-indicator { display: flex; justify-content: center; gap: 2rem; margin-bottom: 1.5rem; }
        .step-item { text-align: center; color: #94a3b8; }
        .step-item.active { color: #007c47; }
        .step-item.done { color: #10b981; }
    </style>
</head>
<body>
<div class="px-4">
    <div class="card install-card shadow-lg">
        <div class="card-body">
            <div class="text-center mb-4">
                <i class="bi bi-buildings" style="font-size:3rem;color:#007c47;"></i>
                <h4 class="fw-bold mt-2">Rent Management System</h4>
                <p class="text-muted">Bilingual (English / Bengali) - OOP PHP</p>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-item <?php echo in_array($step, ['welcome', 'admin', 'done']) ? 'done' : 'active'; ?>">
                    <i class="bi bi-database"></i><div><small>Database</small></div>
                </div>
                <div class="step-item <?php echo $step == 'admin' ? 'active' : ''; ?>">
                    <i class="bi bi-person-gear"></i><div><small>Admin</small></div>
                </div>
                <div class="step-item <?php echo $step == 'done' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle"></i><div><small>Done</small></div>
                </div>
                <div class="step-item <?php echo $step == 'locked' ? 'active' : ''; ?>">
                    <i class="bi bi-lock"></i><div><small>Locked</small></div>
                </div>
            </div>

            <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger py-2"><i class="bi bi-x-circle mr-1"></i><?php echo e($e); ?></div>
            <?php endforeach; ?>
            <?php foreach ($success as $s): ?>
                <div class="alert alert-success py-2"><i class="bi bi-check-circle mr-1"></i><?php echo e($s); ?></div>
            <?php endforeach; ?>

            <?php if ($step === 'welcome'): ?>
                <?php if ($dbExists): ?>
                    <div class="alert alert-info">Database <code><?php echo e(DB_NAME); ?></code> already exists but has no tables yet. Installing creates every table in <code>database.sql</code>.</div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="install">
                    <div class="mb-3">
                        <label class="form-label">Database Host</label>
                        <input type="text" class="form-control" value="<?php echo e(DB_HOST); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Database Name</label>
                        <input type="text" class="form-control" value="<?php echo e(DB_NAME); ?>" readonly>
                    </div>
                    <div class="alert alert-warning py-2">
                        <i class="bi bi-info-circle mr-1"></i>
                        This will create the database <code><?php echo e(DB_NAME); ?></code> and all required tables.
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Install Database <i class="bi bi-arrow-right ml-1"></i></button>
                </form>
            <?php elseif ($step === 'admin'): ?>
                <?php if ($schemaExists): ?>
                    <div class="alert alert-success py-2">
                        <i class="bi bi-check-circle mr-1"></i>
                        All tables are already in place. Only the admin user is left to create.
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="admin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="System Admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="admin@rms.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <small class="text-muted">(min 6 chars)</small></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Create Admin User <i class="bi bi-arrow-right ml-1"></i></button>
                </form>
            <?php elseif ($step === 'done'): ?>
                <div class="text-center">
                    <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-2">Installation Complete!</h5>
                    <p class="text-muted">You can now login to the system.</p>
                    <a href="login" class="btn btn-primary"><i class="bi bi-box-arrow-in-right mr-1"></i> Go to Login</a>
                    <p class="text-muted small mt-3 mb-0">
                        <strong>Do not forget:</strong> delete <code>install.php</code> from the server now.
                    </p>
                </div>
            <?php elseif ($step === 'locked'): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-lock-fill mr-1"></i>
                    <strong>Installer Locked</strong>
                    <p class="mb-0 mt-2">The system is already installed. Re-running the installer will destroy all data.</p>
                </div>
                <p class="text-muted small">
                    The system is already installed. For security, delete <code>install.php</code> now.
                </p>
                <a href="login" class="btn btn-primary mt-3"><i class="bi bi-box-arrow-in-right mr-1"></i> Go to Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
