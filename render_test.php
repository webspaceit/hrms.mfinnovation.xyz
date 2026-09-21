<?php
$_SERVER['PHP_SELF'] = '/payments.php';
$_GET['lang'] = 'bn';
require __DIR__ . '/classes/init.php';
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 1;
ob_start();
require __DIR__ . '/payments.php';
$html = ob_get_clean();
$text = trim(strip_tags($html));
$lines = preg_split('/\R+/', $text);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line !== '' && preg_match('/^\s*[A-Za-z]/', $line) && !preg_match('/^(const|function|document|const BASE|if|new|for|let)/', $line)) {
        echo $line . "\n";
    }
}