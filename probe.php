<?php
echo "openssl_pkey_derive: ", function_exists("openssl_pkey_derive") ? "yes" : "no", "\n";
echo "aes-128-gcm: ", in_array("aes-128-gcm", openssl_get_cipher_methods()) ? "yes" : "no", "\n";
$k = openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => "prime256v1"]);
echo "ec key: ", $k ? "ok" : "fail", "\n";
$d = openssl_pkey_get_details($k);
echo "x=", strlen($d["ec"]["x"]), " y=", strlen($d["ec"]["y"]), "\n";
$sig = "";
openssl_sign("test", $sig, $k, OPENSSL_ALGO_SHA256);
echo "ecdsa sig len: ", strlen($sig), "\n";
