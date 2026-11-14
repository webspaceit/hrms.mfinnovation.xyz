<?php
// ============================================================
// Reminder - due-date reminders for unpaid invoices.
//
// Schedule (settings, all editable in Settings -> Rent reminders):
//   * N days before the due date   (reminder_days_before, default 3)
//   * on the due date
//   * then every R days            (reminder_repeat_days, default 7)
//   ... up to M reminders total    (reminder_max, default 3)
//
// Channels are tried in priority order and the first one that works
// wins, so a tenant with no push subscription still gets a WhatsApp
// or email instead of nothing:
//
//   push, whatsapp, email, sms
//
// Every attempt is written to invoice_reminders with a UNIQUE key on
// (invoice_id, stage, channel), which makes the daily cron idempotent:
// running it twice, or twice in one day, never double-notifies.
// ============================================================

class Reminder {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /* ------------------------------------------------------------
     * Configuration
     * ------------------------------------------------------------ */

    public function settings(): array {
        return [
            'enabled'     => (string)getSetting('reminder_enabled', '0') === '1',
            'days_before' => max(0, min(30, (int)getSetting('reminder_days_before', 3))),
            'repeat_days' => max(0, min(60, (int)getSetting('reminder_repeat_days', 7))),
            'max'         => max(1, min(10, (int)getSetting('reminder_max', 3))),
            'channels'    => $this->channels(),
        ];
    }

    public function channels(): array {
        $allowed = ['push', 'whatsapp', 'email', 'sms'];
        $raw = (string)getSetting('reminder_channels', 'push,whatsapp,email');
        $list = array_values(array_intersect($allowed, array_map('trim', explode(',', $raw))));
        return $list ?: ['push', 'whatsapp', 'email'];
    }

    /**
     * Reminder slots as [day offset => stage].
     * Offset is measured from the due date: negative = before, 0 = due, positive = overdue.
     */
    public function slots(array $settings = null): array {
        $s = $settings ?? $this->settings();
        $slots = [];

        if ($s['max'] === 1) {
            return [0 => 'due'];
        }
        if ($s['days_before'] > 0) {
            $slots[-$s['days_before']] = 'before';
        }
        $slots[0] = 'due';

        if ($s['repeat_days'] > 0) {
            $k = 1;
            while (count($slots) < $s['max']) {
                $slots[$k * $s['repeat_days']] = 'overdue_' . $k;
                $k++;
                if ($k > 20) {
                    break;
                }
            }
        }

        return $slots;
    }

    /** Human label for a stage, used in logs and the UI. */
    public function stageLabel(string $stage, int $offset, array $settings = null): string {
        if ($stage === 'before') {
            return sprintf(t('reminder_stage_before'), bnNumeral(-$offset));
        }
        if ($stage === 'due') {
            return t('reminder_stage_due');
        }
        return sprintf(t('reminder_stage_overdue'), bnNumeral($offset));
    }

    /* ------------------------------------------------------------
     * Data
     * ------------------------------------------------------------ */

    /**
     * Unpaid invoices whose due date falls inside the reminder window.
     */
    public function candidates(string $today, array $settings = null): array {
        $s = $settings ?? $this->settings();
        $slots = $this->slots($s);
        if (!$slots) {
            return [];
        }
        $minOffset = min(array_keys($slots));
        $maxOffset = max(array_keys($slots));

        // due date = today - offset, so the window is
        // [today - maxOffset, today - minOffset] (minOffset is usually negative).
        $base = new DateTimeImmutable($today);
        $from = $base->sub(new DateInterval('P' . $maxOffset . 'D'))->format('Y-m-d');
        $to = $minOffset <= 0
            ? $base->add(new DateInterval('P' . abs($minOffset) . 'D'))->format('Y-m-d')
            : $base->sub(new DateInterval('P' . $minOffset . 'D'))->format('Y-m-d');

        return $this->db->fetchAll("
            SELECT i.*,
                   t.name AS tenant_name, t.phone AS tenant_phone, t.email AS tenant_email,
                   f.flat_no, f.unit_type, f.shop_name,
                   b.name AS building_name
            FROM invoices i
            JOIN tenants t ON t.id = i.tenant_id
            JOIN flats f ON f.id = i.flat_id
            JOIN buildings b ON b.id = f.building_id
            WHERE i.due_date IS NOT NULL
              AND i.due_date BETWEEN :from AND :to
              AND COALESCE(NULLIF(i.payment_status_override, ''), i.payment_status) <> 'paid'
              AND i.total_due > i.paid_amount
            ORDER BY i.due_date ASC, i.id ASC",
            ['from' => $from, 'to' => $to]
        );
    }

    /** Stage due today for one invoice, or null. */
    public function stageFor(array $invoice, string $today, array $settings = null): ?string {
        $slots = $this->slots($settings ?? $this->settings());
        $offset = $this->offsetFor($invoice, $today);
        return $slots[$offset] ?? null;
    }

    /** Days between the due date and $today (negative = still in the future). */
    public function offsetFor(array $invoice, string $today): int {
        $due = new DateTimeImmutable((string)$invoice['due_date']);
        $now = new DateTimeImmutable($today);
        return (int)$due->diff($now)->format('%r%a');
    }

    public function alreadySent($invoiceId, string $stage): bool {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM invoice_reminders
             WHERE invoice_id = :id AND stage = :st AND status = 'sent'",
            ['id' => (int)$invoiceId, 'st' => $stage]
        ) > 0;
    }

    public function log($invoiceId, string $stage, string $channel, string $recipient, bool $ok, ?string $error = null) {
        // INSERT IGNORE keeps a same-day rerun from throwing on the unique key.
        return $this->db->execute(
            "INSERT IGNORE INTO invoice_reminders
                (invoice_id, stage, channel, recipient, status, error, sent_on)
             VALUES (:id, :st, :ch, :rc, :status, :err, CURDATE())",
            [
                'id'     => (int)$invoiceId,
                'st'     => $stage,
                'ch'     => $channel,
                'rc'     => mb_substr($recipient, 0, 191),
                'status' => $ok ? 'sent' : 'failed',
                'err'    => $ok ? null : mb_substr((string)$error, 0, 255),
            ]
        );
    }

    /* ------------------------------------------------------------
     * Runner
     * ------------------------------------------------------------ */

    /**
     * Send everything that is due today.
     *
     * @return array{attempts:int,sent:int,failed:int,skipped:int,lines:array}
     */
    public function run(string $today, bool $dry = false): array {
        $s = $this->settings();
        $out = ['attempts' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'lines' => []];

        if (!$s['enabled']) {
            $out['lines'][] = 'Reminders are disabled (reminder_enabled = 0).';
            return $out;
        }

        $slots = $this->slots($s);
        $invoices = $this->candidates($today, $s);
        $out['lines'][] = sprintf(
            'Reminder run for %s: %d unpaid invoice(s) in window, slots=%s, channels=%s',
            $today,
            count($invoices),
            implode(',', array_map(function ($off, $st) {
                return $st . '(' . ($off >= 0 ? '+' : '') . $off . ')';
            }, array_keys($slots), $slots)),
            implode(',', $s['channels'])
        );

        foreach ($invoices as $inv) {
            $stage = $this->stageFor($inv, $today, $s);
            $who = ($inv['tenant_name'] ?? '') . ' / ' . ($inv['building_name'] ?? '') . ' ' . ($inv['flat_no'] ?? '');

            if ($stage === null) {
                continue; // in the window but not a reminder day (gap between slots)
            }
            if ($this->alreadySent($inv['id'], $stage)) {
                $out['skipped']++;
                $out['lines'][] = "  - $who: {$stage} already sent";
                continue;
            }

            $offset = $this->offsetFor($inv, $today);
            $msg = $this->compose($inv, $stage, $offset);

            if ($dry) {
                $out['attempts']++;
                $out['lines'][] = "  - $who: {$stage} -> (" . $this->previewChannels($inv, $s['channels']) . ') [dry]';
                continue;
            }

            $result = $this->deliver($inv, $stage, $msg, $s['channels']);
            $out['attempts']++;
            if ($result['success']) {
                $out['sent']++;
                $out['lines'][] = "  - $who: {$stage} -> {$result['channel']}: sent";
            } elseif (!empty($result['no_channel'])) {
                // Nothing configured can reach this tenant. Not an error -
                // report it once per run so the landlord can fix the contact.
                $out['skipped']++;
                $out['lines'][] = "  - $who: {$stage} -> no reachable channel (no push device / phone / email or gateway)";
            } else {
                $out['failed']++;
                $out['lines'][] = "  - $who: {$stage} -> {$result['channel']}: FAILED - {$result['message']}";
            }
        }

        return $out;
    }

    /** Which channels would be used right now (dry run helper). */
    public function previewChannels(array $inv, array $channels): string {
        $list = [];
        foreach ($channels as $ch) {
            $r = $this->recipientFor($inv, $ch);
            if ($r !== null) {
                $list[] = $ch;
            }
        }
        return $list ? implode('>', $list) : 'none (no channel available)';
    }

    /** Recipient string for a channel, or null when the channel cannot reach this tenant. */
    public function recipientFor(array $inv, string $channel): ?string {
        if ($channel === 'push') {
            $n = (new PushSubscription())->countForTenant((int)$inv['tenant_id']);
            return $n > 0 ? $n . ' device(s)' : null;
        }
        if ($channel === 'email') {
            if (trim((string)getSetting('smtp_host', '')) === '') {
                return null;
            }
            $email = trim((string)($inv['tenant_email'] ?? ''));
            return $email !== '' ? $email : null;
        }
        $phone = preg_replace('/[^0-9]/', '', enDigits((string)($inv['tenant_phone'] ?? '')));
        if ($phone === '') {
            return null;
        }
        if ($channel === 'whatsapp') {
            $ok = trim((string)getSetting('wa_api_url', '')) !== '' && trim((string)getSetting('wa_api_key', '')) !== '';
            return $ok ? $phone : null;
        }
        if ($channel === 'sms') {
            $ok = trim((string)getSetting('sms_api_url', '')) !== '' && trim((string)getSetting('sms_api_key', '')) !== '';
            return $ok ? $phone : null;
        }
        return null;
    }

    /**
     * Try channels in priority order until one succeeds.
     *
     * @return array{success:bool,channel:string,message:string}
     */
    public function deliver(array $inv, string $stage, array $msg, array $channels): array {
        $lastChannel = 'none';
        $lastError = 'no channel available for this tenant';

        foreach ($channels as $channel) {
            $recipient = $this->recipientFor($inv, $channel);
            if ($recipient === null) {
                continue; // not configured / tenant has no address for it
            }
            $lastChannel = $channel;

            switch ($channel) {
                case 'push':
                    [$ok, $error, $delivered] = $this->sendPush($inv, $msg);
                    break;
                case 'email':
                    $r = Mailer::send($recipient, $msg['subject'], $msg['html'], $msg['text']);
                    $ok = (bool)$r['success'];
                    $error = $r['error'] ?? null;
                    $delivered = $recipient;
                    break;
                case 'whatsapp':
                    $r = WhatsAppSender::send($recipient, $msg['text']);
                    $ok = (bool)$r['success'];
                    $error = $r['error'] ?? null;
                    $delivered = $recipient;
                    break;
                case 'sms':
                default:
                    $r = SmsSender::send('88' . $recipient, $msg['text']);
                    $ok = (bool)$r['success'];
                    $error = $r['error'] ?? null;
                    $delivered = $recipient;
                    break;
            }

            $this->log($inv['id'], $stage, $channel, (string)$delivered, $ok, $error);

            if ($ok) {
                return ['success' => true, 'channel' => $channel, 'message' => t('sent_success')];
            }
            $lastError = (string)($error ?: t('send_failed'));
            // Fall through to the next channel so a dead push subscription
            // never costs the tenant their reminder.
        }

        return [
            'success'    => false,
            'channel'    => $lastChannel,
            'message'    => $lastError,
            'no_channel' => $lastChannel === 'none',
        ];
    }

    /** @return array{0:bool,1:?string,2:string} [ok, error, recipient label] */
    private function sendPush(array $inv, array $msg): array {
        $subs = (new PushSubscription())->forTenant((int)$inv['tenant_id']);
        if (!$subs) {
            return [false, 'no push subscription', 'push'];
        }

        $payload = json_encode([
            'title' => $msg['push_title'],
            'body'  => $msg['push_body'],
            'url'   => $msg['url'],
            'tag'   => 'rms-invoice-' . (int)$inv['id'],
            'icon'  => $msg['icon'],
            'badge' => $msg['icon'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $push = new WebPush();
        $okCount = 0;
        $lastError = null;
        foreach ($subs as $sub) {
            $r = $push->send($sub, $payload);
            if ($r['success']) {
                $okCount++;
                (new PushSubscription())->markResult((int)$sub['id'], 'sent');
                continue;
            }
            $lastError = $r['error'] ?? 'push failed';
            if ($r['gone']) {
                (new PushSubscription())->delete((int)$sub['id']);
            } else {
                (new PushSubscription())->markResult((int)$sub['id'], (string)$lastError);
            }
        }

        return [$okCount > 0, $okCount > 0 ? null : $lastError, $okCount . '/' . count($subs) . ' device(s)'];
    }

    /* ------------------------------------------------------------
     * Message
     * ------------------------------------------------------------ */

    /**
     * Build the reminder in every shape the channels need.
     *
     * @return array{subject:string,text:string,html:string,push_title:string,push_body:string,url:string,icon:string}
     */
    public function compose(array $inv, string $stage, int $offset): array {
        $no = (new Invoice())->invoiceNo($inv);
        $cur = CURRENCY;
        $outstanding = max(0, (float)$inv['total_due'] - (float)$inv['paid_amount']);
        $dueDate = date('d-m-Y', strtotime((string)$inv['due_date']));

        $flatLabel = $inv['unit_type'] === 'commercial_floor'
            ? t('unit_no')
            : ($inv['unit_type'] === 'shop' ? t('shop_no') : t('flat_no'));
        $unitName = localizeText((string)$inv['building_name']) . ' · ' . $flatLabel . ' ' . bnFlatCode($inv['flat_no']);

        $stageLine = $this->stageLabel($stage, $offset);
        $url = $this->tenantInvoiceUrl((int)$inv['id']);
        $icon = $this->baseUrl() . 'assets/icons/icon-192.png';

        $lines = [
            t('reminder_heading') . ' — ' . $stageLine,
            $no,
            $unitName,
            t('tenant_name') . ': ' . localizeName((string)$inv['tenant_name']),
            t('total_due') . ': ' . $cur . ' ' . bnNumeral(number_format($outstanding, 2)),
            t('due_date') . ': ' . $dueDate,
        ];
        if ($url !== '') {
            $lines[] = $url;
        }

        $text = implode("\n", $lines);

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#0f172a">'
            . '<p style="margin:0 0 8px;font-size:16px;font-weight:bold;color:#00603a">'
            . e(t('reminder_heading')) . ' — ' . e($stageLine) . '</p>'
            . '<p style="margin:0 0 4px"><strong>' . e($no) . '</strong></p>'
            . '<p style="margin:0 0 12px">' . e($unitName) . '</p>'
            . '<table style="border-collapse:collapse;margin:0 0 14px">'
            . '<tr><td style="padding:2px 12px 2px 0;color:#64748b">' . e(t('total_due')) . '</td>'
            . '<td style="padding:2px 0;font-weight:bold">' . e($cur . ' ' . bnNumeral(number_format($outstanding, 2))) . '</td></tr>'
            . '<tr><td style="padding:2px 12px 2px 0;color:#64748b">' . e(t('due_date')) . '</td>'
            . '<td style="padding:2px 0">' . e($dueDate) . '</td></tr>'
            . '</table>'
            . ($url !== '' ? '<p style="margin:0"><a href="' . e($url) . '" style="color:#007c47">' . e(t('view_demand_note')) . '</a></p>' : '')
            . '</div>';

        return [
            'subject'    => t('reminder_subject') . ' - ' . $no,
            'text'       => $text,
            'html'       => $html,
            'push_title' => t('reminder_subject') . ' — ' . $stageLine,
            'push_body'  => $unitName . ' · ' . $cur . ' ' . bnNumeral(number_format($outstanding, 2)),
            'url'        => $url,
            'icon'       => $icon,
        ];
    }

    private function tenantInvoiceUrl(int $invoiceId): string {
        $base = $this->baseUrl();
        return $base === '' ? '' : $base . 'tenant-invoice-view?id=' . $invoiceId;
    }

    /** Absolute app base URL (works from CLI cron too). */
    private function baseUrl(): string {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            $host = trim((string)getSetting('site_domain', ''));
        }
        if ($host === '') {
            return '';
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        return $scheme . '://' . $host . BASE_URL;
    }
}

