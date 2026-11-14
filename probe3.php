<?php
$cnf = getenv("TEMP") . DIRECTORY_SEPARATOR . "min-openssl.cnf";
$k = @openssl_pkey_new(["config" => $cnf, "private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => "prime256v1"]);
echo "ec with cnf => ", $k ? "ok" : "fail " . openssl_error_string(), "\n";
if ($k) { $d = openssl_pkey_get_details($k); echo "x=" . strlen($d["ec"]["x"]) . " y=" . strlen($d["ec"]["y"]) . "\n"; }
$sig = "";
if ($k) { openssl_sign("hello", $sig, $k, OPENSSL_ALGO_SHA256); echo "sig len=" . strlen($sig) . "\n"; }
