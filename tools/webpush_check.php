<?php
// ============================================================
// Self-check for classes/WebPush.php
//
//    php tools/test_webpush.php
//
// 1. Reproduces the RFC 8291 §5 test vector byte for byte.
// 2. Signs a VAPID JWT and verifies the ES256 signature.
// 3. Generates a fresh key pair and checks the round trip.
//
// Runs standalone (no DB, no session, no network).
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../classes/WebPush.php';

$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . ($detail !== '' ? '  ' . $detail : '') . PHP_EOL;
}

function u(string $s): string {
    return WebPush::b64uDecode($s);
}

echo "RFC 8291 §5 test vector" . PHP_EOL;

$plaintext = 'When I grow up, I want to be a watermelon';
$uaPublic  = u('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
$uaPrivate = u('q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94');
$asPublic  = u('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
$asPrivate = u('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
$auth      = u('BTBZMqHH6r4Tts7J_aSIgg');
$salt      = u('DGv6ra1nlYgDCS1FRnbzlw');
$expected  = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

check('vector key sizes', strlen($uaPublic) === 65 && strlen($uaPrivate) === 32 && strlen($asPrivate) === 32 && strlen($auth) === 16);

$push = new WebPush();
try {
    $enc = $push->encryptPayload($uaPublic, $auth, $plaintext, WebPush::privateKeyPem($asPrivate), $salt);
    $actual = WebPush::b64uEncode($enc['body']);
    check('encrypted body matches the RFC', hash_equals($expected, $actual), strlen($enc['body']) . ' bytes');
    if (!hash_equals($expected, $actual)) {
        echo '        expected: ' . $expected . PHP_EOL;
        echo '        actual:   ' . $actual . PHP_EOL;
    }
} catch (Throwable $e) {
    check('encryption ran', false, $e->getMessage());
}

// Decrypt our own output the way a browser would, to catch a subtle
// "encrypts but nobody can read it" bug.
try {
    $body = $enc['body'];
    $salt2 = substr($body, 0, 16);
    $rs = unpack('N', substr($body, 16, 4))[1];
    $idLen = ord($body[20]);
    $asPub = substr($body, 21, $idLen);
    $cipherTag = substr($body, 21 + $idLen);
    $cipher = substr($cipherTag, 0, -16);
    $tag = substr($cipherTag, -16);

    $asKey = openssl_pkey_get_public(WebPush::publicKeyPem($asPub));
    $uaKey = openssl_pkey_get_private(WebPush::privateKeyPem($uaPrivate));
    $ecdh = openssl_pkey_derive($asKey, $uaKey, 32);
    $prkKey = hash_hmac('sha256', $ecdh, $auth, true);
    $ikm = hash_hmac('sha256', "WebPush: info\x00" . $uaPublic . $asPub . "\x01", $prkKey, true);
    $prk = hash_hmac('sha256', $ikm, $salt2, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

    $plain = openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    check('record size is 4096', $rs === 4096);
    check('payload round-trips', $plain === $plaintext . "\x02");
} catch (Throwable $e) {
    check('decryption round trip', false, $e->getMessage());
}

echo PHP_EOL . "VAPID (RFC 8292)" . PHP_EOL;

try {
    $keys = WebPush::generateKeys();
    check('key pair generated', strlen(WebPush::b64uDecode($keys['public'])) === 65 && strlen(WebPush::b64uDecode($keys['private'])) === 32);

    $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';
    $header = $push->vapidHeader($endpoint, $keys['public'], $keys['private'], 'mailto:admin@example.com');
    check('authorization header shape', strpos($header, 'vapid t=') === 0 && strpos($header, ', k=') !== false);

    if (preg_match('/^vapid t=([^,]+), k=(.+)$/', $header, $m)) {
        [$all, $jwt, $k] = $m;
        $parts = explode('.', $jwt);
        check('JWT has three parts', count($parts) === 3);
        $claims = json_decode(WebPush::b64uDecode($parts[1]), true);
        check('JWT audience matches endpoint host', ($claims['aud'] ?? '') === 'https://fcm.googleapis.com', $claims['aud'] ?? 'missing');
        check('JWT sub is set', ($claims['sub'] ?? '') === 'mailto:admin@example.com');
        check('JWT k equals the public key', $k === $keys['public']);

        $sig = WebPush::b64uDecode($parts[2]);
        check('signature is 64 raw bytes', strlen($sig) === 64);

        // Re-wrap R||S into DER and verify with openssl.
        $r = ltrim(substr($sig, 0, 32), "\x00") ?: "\x00";
        $s = ltrim(substr($sig, 32), "\x00") ?: "\x00";
        $der = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
        $der = "\x30" . chr(strlen($der)) . $der;

        $pubPem = WebPush::publicKeyPem(WebPush::b64uDecode($keys['public']));
        $verified = openssl_verify($parts[0] . '.' . $parts[1], $der, $pubPem, OPENSSL_ALGO_SHA256);
        check('ES256 signature verifies', $verified === 1, 'openssl_verify=' . var_export($verified, true));
    }

    $enc2 = $push->encryptPayload($uaPublic, $auth, '{"title":"test"}');
    check('ephemeral encryption works', strlen($enc2['body']) > 86 && strlen($enc2['as_public']) === 65);
} catch (Throwable $e) {
    check('VAPID flow', false, $e->getMessage());
}

echo PHP_EOL . ($failures === 0 ? 'ALL CHECKS PASSED' : $failures . ' CHECK(S) FAILED') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
