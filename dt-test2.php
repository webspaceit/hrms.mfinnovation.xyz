<?php
chdir('E:/wamp64/www/oop-rms');
require 'classes/Helpers.php';
$_SESSION = ['lang' => 'en'];
Lang::init();
echo "UI=en -> " . dateLong('2026-11-14 09:12:33') . PHP_EOL;
$_SESSION = ['lang' => 'bn'];
Lang::init();
echo "UI=bn -> " . dateLong('2026-11-14 09:12:33') . PHP_EOL;
echo "UI=bn, english month name? " . (strpos(dateLong('2026-11-14'), 'November') === false ? 'no' : 'yes') . PHP_EOL;
