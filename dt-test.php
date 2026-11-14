<?php
chdir('E:/wamp64/www/oop-rms');
require 'classes/Lang.php';
require 'classes/Helpers.php';
$_SESSION = [];
$_SESSION['lang'] = 'bn';
echo "bn session: " . dateLong('2026-11-14 09:12:33') . PHP_EOL;
$_SESSION['lang'] = 'en';
echo "en session: " . dateLong('2026-11-14 09:12:33') . PHP_EOL;
echo "bn forced : " . dateLong('2026-11-14 09:12:33', 'bn') . PHP_EOL;
echo "bad input : " . dateLong('') . PHP_EOL;
