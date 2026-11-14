<?php
// ============================================================
// WebPush - send browser push notifications without any library.
//
// Implements the two specs a push service requires:
//   * RFC 8291 - aes128gcm payload encryption (ECDH P-256 + HKDF
//     + AES-128-GCM), verified against the RFC's own test vector
//     in tools/test_webpush.php;
//   * RFC 8292 - VAPID authentication (ES256 JWT).
//
// Everything is pure PHP + ext-openssl, so it runs on the same
// shared hosting as the rest of the app - no Composer, no Node.
//
// A minimal openssl.cnf is used when creating EC keys, because some
// Windows/WAMP and shared-host installs have no readable default
// config and openssl_pkey_new() then fails with
// "system library:fopen:No such process".
// ============================================================

class WebPush {

    /** Record size advertised in the aes128gcm header (RFC 8291 §4). */
    const RECORD_SIZE = 4096;

    /** Default TTL for a notification (seconds). */
    const DEFAULT_TTL = 86400;

    private $configFile;

    public function __construct() {
        $candidate = __DIR__ . '/../config/openssl.cnf';
        $this->configFile = is_file($candidate) ? $candidate : null;
    }

    /* ------------------------------------------------------------
     * VAPID keys
     * ------------------------------------------------------------ */

    /**
     * Create a new VAPID key pair.
     * Returns ['public' => base64url(raw 65-byte point), 'private' => base64url(32-byte scalar)].
     */
    public static function generateKeys(): array {
        $self = new self();
        $key = $self->newEcKey();
        $details = openssl_pkey_get_details($key);
        if (!$details || !isset($details['ec']['d'])) {
            throw new RuntimeException('Could not read the generated EC key');
        }
        return [
            'public'  => self::b64uEncode(self::rawPublicFromDetails($details)),
            'private' => self::b64uEncode($details['ec']['d']),
        ];
    }

    /* ------------------------------------------------------------
     * Encryption (RFC 8291)
     * ------------------------------------------------------------ */

    /**
     * Encrypt a payload for one subscription.
     *
     * @param string      $uaPublicRaw  subscription p256dh, 65 raw bytes
     * @param string      $authRaw      subscription auth, 16 raw bytes
     * @param string      $plaintext    JSON or text to deliver
     * @param string|null $asPrivatePem sender EC private key (null = ephemeral, used in the RFC test)
     * @param string|null $salt         fixed salt (tests only)
     * @return array{body:string, as_public:string}
     */
    public function encryptPayload(
        string $uaPublicRaw,
        string $authRaw,
        string $plaintext,
        ?string $asPrivatePem = null,
        ?string $salt = null
    ): array {
        if (strlen($uaPublicRaw) !== 65 || $uaPublicRaw[0] !== "\x04") {
            throw new RuntimeException('Invalid subscription public key');
        }
        if (strlen($authRaw) !== 16) {
            throw new RuntimeException('Invalid subscription auth secret');
        }
        if (strlen($plaintext) > 3993) {
            throw new RuntimeException('Push payload exceeds the 3993 byte limit');
        }

        if ($asPrivatePem === null) {
            $asKey = $this->newEcKey();
            $asDetails = openssl_pkey_get_details($asKey);
            $asPublicRaw = self::rawPublicFromDetails($asDetails);
            $asPrivatePem = $this->exportPrivatePem($asKey);
        } else {
            $asKey = openssl_pkey_get_private($asPrivatePem);
            if (!$asKey) {
                throw new RuntimeException('Could not load the sender private key');
            }
            $asPublicRaw = self::rawPublicFromDetails(openssl_pkey_get_details($asKey));
        }

        $uaKey = openssl_pkey_get_public(self::publicKeyPem($uaPublicRaw));
        if (!$uaKey) {
            throw new RuntimeException('Could not load the subscription public key');
        }

        // ECDH shared secret.
        $ecdh = openssl_pkey_derive($uaKey, $asKey, 32);
        if ($ecdh === false) {
            throw new RuntimeException('ECDH key agreement failed');
        }

        $salt = $salt ?? random_bytes(16);

        // HKDF-Extract(salt=auth_secret, IKM=ecdh_secret)
        $prkKey = hash_hmac('sha256', $ecdh, $authRaw, true);
        // IKM = HKDF-Expand(PRK_key, "WebPush: info" || 0x00 || ua_public || as_public)
        $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
        $ikm = hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true);

        // HKDF-Extract(salt, IKM)
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        // Single record: plaintext + padding delimiter 0x02.
        $data = $plaintext . "\x02";
        $tag = '';
        $cipher = openssl_encrypt($data, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('AES-GCM encryption failed');
        }

        $body = $salt
            . pack('N', self::RECORD_SIZE)
            . chr(strlen($asPublicRaw))
            . $asPublicRaw
            . $cipher
            . $tag;

        return ['body' => $body, 'as_public' => $asPublicRaw];
    }

    /* ------------------------------------------------------------
     * VAPID (RFC 8292)
     * ------------------------------------------------------------ */

    /**
     * Build the Authorization header value for a push endpoint.
     * $subscriber must be a mailto: or https: URL that identifies the app owner.
     */
    public function vapidHeader(string $endpoint, string $publicKeyB64, string $privateKeyB64, string $subscriber): string {
        $privRaw = self::b64uDecode($privateKeyB64);
        if (strlen($privRaw) !== 32) {
            throw new RuntimeException('Invalid VAPID private key');
        }

        $audience = self::audienceFromEndpoint($endpoint);
        $header = self::b64uEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64uEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $subscriber,
        ]));
        $signingInput = $header . '.' . $claims;

        $key = openssl_pkey_get_private(self::privateKeyPem($privRaw));
        if (!$key) {
            throw new RuntimeException('Could not load the VAPID private key');
        }
        $der = '';
        if (!openssl_sign($signingInput, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID signature failed');
        }

        $jwt = $signingInput . '.' . self::b64uEncode(self::derToRawSignature($der));
        return 'vapid t=' . $jwt . ', k=' . $publicKeyB64;
    }

    /* ------------------------------------------------------------
     * Delivery
     * ------------------------------------------------------------ */

    /**
     * Send one notification to one subscription row.
     *
     * @param array  $subscription ['endpoint','p256dh','auth']
     * @param string $payload      JSON string (or plain text)
     * @return array{success:bool,status:int,error:?string,gone:bool}
     */
    public function send(array $subscription, string $payload, int $ttl = self::DEFAULT_TTL): array {
        $publicKey = (string)getSetting('push_vapid_public', '');
        $privateKey = (string)getSetting('push_vapid_private', '');
        if ($publicKey === '' || $privateKey === '') {
            return ['success' => false, 'status' => 0, 'error' => 'VAPID keys are not generated', 'gone' => false];
        }

        $endpoint = (string)($subscription['endpoint'] ?? '');
        if ($endpoint === '') {
            return ['success' => false, 'status' => 0, 'error' => 'Empty endpoint', 'gone' => true];
        }

        try {
            $enc = $this->encryptPayload(
                self::b64uDecode((string)$subscription['p256dh']),
                self::b64uDecode((string)$subscription['auth']),
                $payload
            );
            $auth = $this->vapidHeader(
                $endpoint,
                $publicKey,
                $privateKey,
                self::subscriber()
            );
        } catch (Throwable $e) {
            return ['success' => false, 'status' => 0, 'error' => $e->getMessage(), 'gone' => false];
        }

        $headers = [
            'Authorization: ' . $auth,
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: ' . $ttl,
            'Urgency: normal',
        ];

        [$status, $responseBody, $curlError] = $this->post($endpoint, $enc['body'], $headers);

        if ($curlError !== null) {
            return ['success' => false, 'status' => 0, 'error' => $curlError, 'gone' => false];
        }

        $ok = $status >= 200 && $status < 300;
        // 404/410 mean the browser dropped the subscription for good.
        $gone = in_array($status, [404, 410], true);

        return [
            'success' => $ok,
            'status'  => $status,
            'error'   => $ok ? null : self::trimError($status, $responseBody),
            'gone'    => $gone,
        ];
    }

    /** Send a payload to every stored subscription. */
    public function sendToAllSubscriptions(string $payload): array {
        $rows = (new PushSubscription())->all();
        $result = ['total' => count($rows), 'sent' => 0, 'failed' => 0, 'pruned' => 0];
        foreach ($rows as $row) {
            $r = $this->send($row, $payload);
            if ($r['success']) {
                $result['sent']++;
                (new PushSubscription())->markResult((int)$row['id'], 'sent');
            } else {
                $result['failed']++;
                if ($r['gone']) {
                    (new PushSubscription())->delete((int)$row['id']);
                    $result['pruned']++;
                } else {
                    (new PushSubscription())->markResult((int)$row['id'], (string)$r['error']);
                }
            }
        }
        return $result;
    }

    private function post(string $url, string $body, array $headers): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                curl_close($ch);
                return [0, '', $err];
            }
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [$status, (string)$response, null];
        }

        // Fallback for hosts without cURL.
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $err = error_get_last()['message'] ?? 'HTTP request failed';
            return [0, '', $err];
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int)$m[1];
            }
        }
        return [$status, (string)$response, null];
    }

    /* ------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------ */

    public function newEcKey() {
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];
        if ($this->configFile) {
            $options['config'] = $this->configFile;
        }
        $key = @openssl_pkey_new($options);
        if (!$key) {
            // Retry without our config so a broken path never blocks the app.
            unset($options['config']);
            $key = @openssl_pkey_new($options);
        }
        if (!$key) {
            throw new RuntimeException('OpenSSL could not create an EC key: ' . openssl_error_string());
        }
        return $key;
    }

    public static function subscriber(): string {
        $sub = trim((string)getSetting('push_subject', ''));
        if ($sub === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $sub = 'mailto:admin@' . preg_replace('/:\d+$/', '', $host);
        }
        return $sub;
    }

    public static function audienceFromEndpoint(string $endpoint): string {
        $parts = parse_url($endpoint);
        if (!$parts || empty($parts['host'])) {
            throw new RuntimeException('Invalid push endpoint');
        }
        $scheme = $parts['scheme'] ?? 'https';
        return $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public static function b64uEncode(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $value): string {
        $value = strtr(trim($value), '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($value, true);
        return $raw === false ? '' : $raw;
    }

    /** Raw uncompressed point from an openssl key's details. */
    private static function rawPublicFromDetails(array $details): string {
        if (!isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Not an EC key');
        }
        return "\x04" . $details['ec']['x'] . $details['ec']['y'];
    }

    /** Wrap a raw uncompressed point in a SubjectPublicKeyInfo PEM. */
    public static function publicKeyPem(string $raw65): string {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw65;
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /** Wrap a raw 32-byte scalar in an SEC1 EC private key PEM. */
    public static function privateKeyPem(string $raw32): string {
        $der = hex2bin('30310201010420')
            . $raw32
            . hex2bin('a00a06082a8648ce3d030107');
        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    private function exportPrivatePem($key): string {
        $pem = '';
        if (!openssl_pkey_export($key, $pem, null, $this->configFile ? ['config' => $this->configFile] : null)) {
            throw new RuntimeException('Could not export the EC private key');
        }
        return $pem;
    }

    /** ECDSA DER signature -> fixed 64-byte R||S (JWS ES256). */
    private static function derToRawSignature(string $der): string {
        $offset = 2; // skip SEQUENCE tag + length
        if (ord($der[1]) & 0x80) {
            $offset += ord($der[1]) & 0x7f;
        }
        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $len);
            $int = ltrim($int, "\x00");
            $raw .= str_pad($int, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $len;
        }
        return $raw;
    }

    private static function trimError(int $status, string $body): string {
        $body = trim(preg_replace('/\s+/', ' ', $body));
        $msg = $status ? ('HTTP ' . $status) : 'Error';
        if ($body !== '') {
            $msg .= ': ' . mb_substr($body, 0, 180);
        }
        return $msg;
    }
}

