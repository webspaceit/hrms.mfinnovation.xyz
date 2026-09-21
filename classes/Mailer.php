<?php
// ============================================================
// Mailer - minimal SMTP client (no external libraries)
// Reads credentials from the settings table.
// ============================================================

require_once __DIR__ . '/../classes/Helpers.php';

class Mailer {

    /**
     * Send an email through the configured SMTP server.
     *
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function send($to, $subject, $htmlBody, $textBody = null) {
        $host = trim((string)getSetting('smtp_host', ''));
        if ($host === '') {
            return ['success' => false, 'error' => t('smtp_not_configured')];
        }
        $port = (int)getSetting('smtp_port', 587);
        $user = trim((string)getSetting('smtp_user', ''));
        $pass = (string)getSetting('smtp_pass', '');
        $from = trim((string)getSetting('smtp_from', ''));
        if ($from === '') {
            $from = $user !== '' ? $user : 'no-reply@localhost';
        }
        $fromName = trim((string)getSetting('smtp_from_name', ''));
        if ($fromName === '') {
            $fromName = (string)getSetting('site_name', 'Rent Management System');
        }
        $enc = strtolower((string)getSetting('smtp_encryption', 'tls'));
        if (!in_array($enc, ['ssl', 'tls', 'none'])) {
            $enc = 'tls';
        }

        $hostLine = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($hostLine, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            return ['success' => false, 'error' => 'Connection failed: ' . $errstr];
        }

        try {
            if (!self::expect($fp, 220)) {
                return ['success' => false, 'error' => 'Unexpected server greeting'];
            }
            $ehloHost = filter_var($host, FILTER_VALIDATE_IP) ? 'localhost' : $host;
            self::cmd($fp, "EHLO " . $ehloHost);
            if (!self::expect($fp, 250)) {
                return ['success' => false, 'error' => 'EHLO failed'];
            }
            if ($enc === 'tls') {
                self::cmd($fp, "STARTTLS");
                if (!self::expect($fp, 220)) {
                    return ['success' => false, 'error' => 'STARTTLS rejected'];
                }
                $crypto = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    return ['success' => false, 'error' => 'TLS negotiation failed'];
                }
                self::cmd($fp, "EHLO " . $ehloHost);
                if (!self::expect($fp, 250)) {
                    return ['success' => false, 'error' => 'EHLO after STARTTLS failed'];
                }
            }
            if ($user !== '' && $pass !== '') {
                self::cmd($fp, "AUTH LOGIN");
                if (!self::expect($fp, 334)) {
                    return ['success' => false, 'error' => 'AUTH LOGIN rejected by server'];
                }
                self::cmd($fp, base64_encode($user));
                if (!self::expect($fp, 334)) {
                    return ['success' => false, 'error' => 'SMTP username rejected'];
                }
                self::cmd($fp, base64_encode($pass));
                if (!self::expect($fp, 235)) {
                    return ['success' => false, 'error' => 'SMTP authentication failed'];
                }
            }
            self::cmd($fp, "MAIL FROM:<" . $from . ">");
            if (!self::expect($fp, 250)) {
                return ['success' => false, 'error' => 'MAIL FROM rejected'];
            }
            self::cmd($fp, "RCPT TO:<" . $to . ">");
            if (!self::expect($fp, [250, 251])) {
                return ['success' => false, 'error' => 'RCPT TO rejected'];
            }
            self::cmd($fp, "DATA");
            if (!self::expect($fp, 354)) {
                return ['success' => false, 'error' => 'DATA command rejected'];
            }

            $boundary = 'rms' . bin2hex(random_bytes(8));
            $message = self::buildMessage($from, $fromName, $to, $subject, $htmlBody, $textBody, $boundary);
            foreach (explode("\n", $message) as $line) {
                $line = rtrim($line, "\r");
                if ($line !== '' && $line[0] === '.') {
                    $line = '.' . $line;
                }
                if (!fwrite($fp, $line . "\r\n")) {
                    return ['success' => false, 'error' => 'Failed writing message to server'];
                }
            }
            self::cmd($fp, ".");
            if (!self::expect($fp, 250)) {
                return ['success' => false, 'error' => 'Message not accepted by server'];
            }
            self::cmd($fp, "QUIT");
            @fclose($fp);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            @fclose($fp);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the RFC-compliant MIME message (multipart/alternative).
     */
    private static function buildMessage($from, $fromName, $to, $subject, $htmlBody, $textBody, $boundary) {
        $textBody = $textBody !== null ? trim($textBody) : ($htmlBody !== null ? trim(strip_tags($htmlBody)) : '');
        $date = date('r');
        $msgId = '<' . bin2hex(random_bytes(8)) . '@localhost>';

        $headers = [];
        $headers[] = 'From: ' . self::encodeWord($fromName) . ' <' . $from . '>';
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . self::encodeWord($subject);
        $headers[] = 'Date: ' . $date;
        $headers[] = 'Message-ID: ' . $msgId;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($textBody)) . "\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $message .= '--' . $boundary . "--\r\n";
        return $message;
    }

    /**
     * RFC 2047 encode header field values containing non-ASCII text.
     */
    private static function encodeWord($text) {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }

    /**
     * Send a command line to the socket.
     */
    private static function cmd($fp, $line) {
        fwrite($fp, $line . "\r\n");
        fflush($fp);
    }

    /**
     * Read lines until one starts with an accepted response code.
     */
    private static function expect($fp, $codes) {
        $codes = is_array($codes) ? $codes : [$codes];
        stream_set_timeout($fp, 30);
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            if ($line === false) {
                return false;
            }
            $code = (int)substr($line, 0, 3);
            if (in_array($code, $codes, true)) {
                return true;
            }
            // A line starting with space+code means the previous code was final.
            if (isset($line[3]) && $line[3] === ' ') {
                return false;
            }
        }
        return false;
    }
}