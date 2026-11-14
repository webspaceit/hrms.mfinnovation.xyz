<?php
$attempts = ["prime256v1", "secp256r1", "P-256"];
foreach ($attempts as $c) {
    $k = @openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => $c]);
    echo "$c => ", $k ? "ok" : "fail", " err=", openssl_error_string(), "\n";
}
echo "cnf env: ", getenv("OPENSSL_CONF") ?: "(none)", "\n";
echo "php ini openssl.cafile: ", ini_get("openssl.cafile") ?: "(none)", "\n";
echo "openssl version: ", OPENSSL_VERSION_TEXT, "\n";
$k2 = @openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_RSA, "private_key_bits" => 2048]);
echo "rsa => ", $k2 ? "ok" : "fail ", openssl_error_string(), "\n";
