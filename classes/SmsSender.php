<?php
// ============================================================
// SmsSender - generic HTTP SMS gateway client
// Credentials/endpoint come from the settings table.
// ============================================================

require_once __DIR__ . '/../classes/Helpers.php';

class SmsSender {

    /**
     * Send an SMS via the configured gateway.
     *
     * The gateway receives POST fields: api_key, sender_id, to, message.
     * Adapt request params in the gateway settings if your provider uses a
     * different contract.
     *
     * @return array ['success' => bool, 'response' => string|null, 'error' => string|null]
     */
    public static function send($phone, $message) {
        $url = trim((string)getSetting('sms_api_url', ''));
        $key = trim((string)getSetting('sms_api_key', ''));
        if ($url === '' || $key === '') {
            return ['success' => false, 'response' => null, 'error' => t('sms_not_configured')];
        }

        $params = [
            'api_key' => $key,
            'sender_id' => trim((string)getSetting('sms_sender_id', '')),
            'to' => trim((string)$phone),
            'message' => trim((string)$message),
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            if ($resp === false) {
                return ['success' => false, 'response' => null, 'error' => $err ?: 'HTTP request failed'];
            }
            return ['success' => true, 'response' => $resp, 'error' => null];
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params),
            'timeout' => 20,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['success' => false, 'response' => null, 'error' => 'HTTP request failed'];
        }
        return ['success' => true, 'response' => $resp, 'error' => null];
    }
}