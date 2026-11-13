<?php
// ============================================================
// Invoice Model (monthly due / demand note)
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Invoice extends BaseModel {
    protected $table = 'invoices';

    /**
     * Generate (create or update) invoices for a given month/year for every
     * lease that is active within that month.
     *
     * @return array [created, updated]
     */
    /**
     * The service charges a lease was last billed, used when creating a new
     * month's invoice so recurring bills (parking, gas, water, waste) are not
     * silently dropped to zero.
     *
     * Leases have no charge columns, so a prior invoice is the only source.
     * The newest invoice that actually carried a charge is used rather than
     * strictly the previous month: months billed before charges were carried
     * forward hold zeros, and following those would propagate the zeros and
     * leave every later month unbilled. Charges are copied as a set so a month
     * inherits one consistent set.
     */
    private function lastChargesForLease($leaseId, $month, $year) {
        $zero = ['parking_amount' => 0.0, 'gas_amount' => 0.0, 'water_fee' => 0.0, 'waste_fee' => 0.0];

        $prev = $this->db->fetch(
            "SELECT parking_amount, gas_amount, water_fee, waste_fee
             FROM invoices
             WHERE lease_id = :l
               AND (year < :y1 OR (year = :y2 AND month < :m1))
               AND (parking_amount <> 0 OR gas_amount <> 0
                    OR water_fee <> 0 OR waste_fee <> 0)
             ORDER BY year DESC, month DESC
             LIMIT 1",
            ['l' => (int)$leaseId, 'y1' => (int)$year, 'y2' => (int)$year, 'm1' => (int)$month]
        );
        if (!$prev) {
            return $zero;
        }

        return [
            'parking_amount' => (float)$prev['parking_amount'],
            'gas_amount' => (float)$prev['gas_amount'],
            'water_fee' => (float)$prev['water_fee'],
            'waste_fee' => (float)$prev['waste_fee'],
        ];
    }

    public function generateForMonth($month, $year) {
        $firstDay = sprintf('%04d-%02d-01', (int)$year, (int)$month);
        $lastDay = date('Y-m-t', strtotime($firstDay));

        $leases = $this->db->fetchAll("
            SELECT l.*
            FROM leases l
            JOIN tenants t ON t.id = l.tenant_id
            WHERE l.status = 'active' AND t.status = 'active'
              AND l.start_date <= :last_day
              AND (l.end_date IS NULL OR l.end_date >= :first_day)" . tenantScope('t') . "
            ORDER BY l.id
        ", ['last_day' => $lastDay, 'first_day' => $firstDay]);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($leases as $lease) {
            // Arrears disabled by default - user can manually add them later if needed
            $arrears = 0.0;
            $arrearsMonths = 0;
            $rent = (float)$lease['rent_amount'];
            // Utility fee is a screen-only reference (shown on the invoice view,
            // never billed). It is stored but excluded from the invoice total.
            $util = (float)$lease['utility_fee'];

            // Service charges this lease was last billed. Only used when
            // creating a month that has no invoice yet, so a recurring
            // parking/gas/water/waste bill is not silently dropped to zero.
            $prevCharges = $this->lastChargesForLease((int)$lease['id'], (int)$month, (int)$year);
            $chargeSum = $prevCharges['parking_amount'] + $prevCharges['gas_amount']
                + $prevCharges['water_fee'] + $prevCharges['waste_fee'];
            $total = $rent + $arrears;

            $existing = $this->db->fetch(
                "SELECT id, status, is_manual, parking_amount, gas_amount, water_fee, waste_fee, total_due
                 FROM invoices WHERE lease_id = :l AND month = :m AND year = :y",
                ['l' => (int)$lease['id'], 'm' => (int)$month, 'y' => (int)$year]
            );

            if ($existing) {
                // Manually edited invoices keep their values until reset.
                // Exception: when nothing has been collected for this
                // lease/month/year there is no payment depending on those
                // figures, so regenerating is safe. This is what makes
                // "deleted the receipt, then regenerate the invoice" work -
                // otherwise the delete left a manual invoice that generation
                // silently skipped forever.
                if ((int)$existing['is_manual'] === 1) {
                    $collected = (new Payment())->sumForInvoice((int)$lease['id'], (int)$month, (int)$year);
                    if ($collected > 0.009) {
                        $skipped++;
                        continue;
                    }
                }
                $this->update($existing['id'], [
                    'tenant_id' => (int)$lease['tenant_id'],
                    'flat_id' => (int)$lease['flat_id'],
                    'rent_amount' => $rent,
                    'utility_fee' => $util,
                    // Keep the charges this month was already issued with.
                    // Recalculating a month must not import another month's
                    // charges, which would rebill a tenant who has already
                    // paid this one.
                    'parking_amount' => (float)$existing['parking_amount'],
                    'gas_amount' => (float)$existing['gas_amount'],
                    'water_fee' => (float)$existing['water_fee'],
                    'waste_fee' => (float)$existing['waste_fee'],
                    'arrears' => $arrears,
                    'arrears_months' => $arrearsMonths,
                    'total_due' => $rent + (float)$existing['parking_amount']
                        + (float)$existing['gas_amount'] + (float)$existing['water_fee']
                        + (float)$existing['waste_fee'] + $arrears,
                    'status' => $existing['status'] === 'sent' ? 'sent' : 'draft'
                ]);
                $updated++;
            } else {
                $this->create([
                    'lease_id' => (int)$lease['id'],
                    'tenant_id' => (int)$lease['tenant_id'],
                    'flat_id' => (int)$lease['flat_id'],
                    'month' => (int)$month,
                    'year' => (int)$year,
                    'rent_amount' => $rent,
                    'utility_fee' => $util,
                    // Carry the service charges the lease was last billed
                    // forward. Writing zeros here made a regenerated invoice
                    // bill rent only, so a receipt that had been deleted could
                    // not be re-entered at its original total - the Add Payment
                    // cap rejected it. Only the newest invoice per lease is
                    // consulted, so each month reflects the current charges.
                    'parking_amount' => $prevCharges['parking_amount'],
                    'gas_amount' => $prevCharges['gas_amount'],
                    'water_fee' => $prevCharges['water_fee'],
                    'waste_fee' => $prevCharges['waste_fee'],
                    'arrears' => $arrears,
                    'arrears_months' => $arrearsMonths,
                    'total_due' => $rent + $chargeSum + $arrears,
                    'status' => 'draft'
                ]);
                $created++;
            }
        }
        // Keep stored payment status/paid amounts consistent after regeneration.
        (new Payment())->reconcileAllForMonth($month, $year);
        return [$created, $updated, $skipped];
    }

    /**
     * Sum the unpaid months between the lease start and the month before the
     * target month. A month counts as paid when a payments row exists for the
     * lease in that month/year.
     *
     * @return array [totalArrears, monthCount]
     */
    public function computeArrears($lease, $month, $year) {
        $start = new DateTime($lease['start_date']);
        $startIndex = (int)$start->format('Y') * 12 + ((int)$start->format('n') - 1);
        $targetIndex = (int)$year * 12 + ((int)$month - 1);
        if ($startIndex >= $targetIndex) {
            return [0.0, 0];
        }
        // Arrears per unpaid month = rent only; utility is billed separately and
            // never included in the invoice total or the money receipt.
            $owed = (float)$lease['rent_amount'];
        $total = 0.0;
        $count = 0;
        for ($i = $startIndex; $i < $targetIndex; $i++) {
            $m = ($i % 12) + 1;
            $y = intdiv($i, 12);
            $paid = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM payments WHERE lease_id = :l AND month = :m AND year = :y",
                ['l' => (int)$lease['id'], 'm' => $m, 'y' => $y]
            );
            if (!$paid) {
                $total += $owed;
                $count++;
            }
        }
        return [$total, $count];
    }

    /**
     * Current-month invoices with tenant / flat / building details.
     */
    public function listForMonth($month, $year) {
        return $this->db->fetchAll("
            SELECT i.*, t.name AS tenant_name, t.phone AS tenant_phone, t.email AS tenant_email,
                   f.flat_no, f.unit_type, f.building_id, b.name AS building_name, b.address AS building_address,
                   (SELECT GROUP_CONCAT(s.channel, ':', s.status ORDER BY s.sent_at SEPARATOR '|')
                    FROM invoice_sends s WHERE s.invoice_id = i.id) AS sends_log
            FROM invoices i
            JOIN tenants t ON t.id = i.tenant_id
            JOIN flats f ON f.id = i.flat_id
            JOIN buildings b ON b.id = f.building_id
            WHERE i.month = :month AND i.year = :year" . tenantScope('t') . "
            ORDER BY b.name, f.flat_no
        ", ['month' => (int)$month, 'year' => (int)$year]);
    }

    /**
     * Invoices that are not fully paid (unpaid or partial) with tenant /
     * flat / building details. The payments page uses this so every due bill
     * automatically appears there even when no payment record exists yet.
     */
    public function listUnpaid($month = null, $year = null) {
        $sql = "SELECT i.*, t.name AS tenant_name, t.phone AS tenant_phone,
                       f.flat_no, f.unit_type, f.building_id, b.name AS building_name,
                       COALESCE(NULLIF(i.payment_status_override, ''), i.payment_status, 'unpaid') AS status_effective
                FROM invoices i
                JOIN tenants t ON t.id = i.tenant_id
                JOIN flats f ON f.id = i.flat_id
                JOIN buildings b ON b.id = f.building_id
                WHERE (
                    -- Financial truth: paid_amount < total_due means still owes money
                    i.paid_amount < i.total_due
                    -- OR override explicitly set to unpaid/partial (even if paid_amount looks full)
                    OR NULLIF(i.payment_status_override, '') IN ('unpaid', 'partial')
                )
                -- Exclude only when override is 'paid' AND financial reality agrees (paid_amount >= total_due)
                AND NOT (
                    NULLIF(i.payment_status_override, '') = 'paid'
                    AND i.paid_amount >= i.total_due
                )";
        $params = [];
        if ($month) {
            $sql .= " AND i.month = :m";
            $params['m'] = (int)$month;
        }
        if ($year) {
            $sql .= " AND i.year = :y";
            $params['y'] = (int)$year;
        }
        $sql .= tenantScope('t');
        $sql .= " ORDER BY b.name ASC, f.unit_type ASC, f.flat_no ASC, i.year DESC, i.month DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Billed breakdown of every invoice (lease/month/year key) so the payment
     * form can pre-fill the exact amounts the invoice carries — the invoice is
     * the single source of truth and the receipt always matches it.
     */
    public function allWithBreakdown() {
        $scope = tenantScope('t');
        if ($scope !== '') {
            return $this->db->fetchAll("
                SELECT i.lease_id, i.month, i.year, i.rent_amount, i.utility_fee,
                       i.parking_amount, i.gas_amount, i.water_fee, i.waste_fee, i.arrears,
                       i.total_due, i.paid_amount, i.payment_status, i.payment_status_override
                FROM invoices i
                JOIN tenants t ON t.id = i.tenant_id
                WHERE 1=1" . $scope
            );
        }
        return $this->db->fetchAll("
            SELECT lease_id, month, year, rent_amount, utility_fee,
                   parking_amount, gas_amount, water_fee, waste_fee, arrears,
                   total_due, paid_amount, payment_status, payment_status_override
            FROM invoices
        ");
    }

    /**
     * A single invoice with tenant / flat / building details (for viewing).
     */
    public function getWithDetails($id) {
        return $this->db->fetch("
            SELECT i.*, t.name AS tenant_name, t.phone AS tenant_phone, t.email AS tenant_email,
                   f.flat_no, f.unit_type, f.shop_name, b.name AS building_name, b.address AS building_address, b.holding_no AS building_holding_no,
                   u.full_name AS landowner_name
            FROM invoices i
            JOIN tenants t ON t.id = i.tenant_id
            LEFT JOIN users u ON u.id = t.created_by
            JOIN flats f ON f.id = i.flat_id
            JOIN buildings b ON b.id = f.building_id
            WHERE i.id = :id" . tenantScope('t'),
            ['id' => (int)$id]
        );
    }

    // ------------------------------------------------------------
    // Tenant portal (every query is scoped to one tenant id)
    // ------------------------------------------------------------

    /**
     * Monthly dues of one tenant, newest first (optionally one year).
     */
    public function listForTenant($tenantId, $year = null) {
        $sql = "SELECT i.*, f.flat_no, f.unit_type, f.building_id, b.name AS building_name,
                       COALESCE(NULLIF(i.payment_status_override, ''), i.payment_status) AS status_effective
                FROM invoices i
                LEFT JOIN flats f ON f.id = i.flat_id
                LEFT JOIN buildings b ON b.id = f.building_id
                WHERE i.tenant_id = :tid";
        $params = ['tid' => (int)$tenantId];
        if ($year) {
            $sql .= " AND i.year = :year";
            $params['year'] = (int)$year;
        }
        return $this->db->fetchAll($sql . " ORDER BY i.year DESC, i.month DESC, i.id DESC", $params);
    }

    /**
     * Years in which the tenant has dues (for the year filter).
     */
    public function yearsForTenant($tenantId) {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT year FROM invoices WHERE tenant_id = :tid ORDER BY year DESC",
            ['tid' => (int)$tenantId]
        );
        return array_map('intval', array_column($rows, 'year'));
    }

    /**
     * Due / paid / outstanding totals of one tenant (optionally one year).
     */
    public function totalsForTenant($tenantId, $year = null) {
        $sql = "SELECT COALESCE(SUM(total_due), 0) AS due, COALESCE(SUM(paid_amount), 0) AS paid
                FROM invoices WHERE tenant_id = :tid";
        $params = ['tid' => (int)$tenantId];
        if ($year) {
            $sql .= " AND year = :year";
            $params['year'] = (int)$year;
        }
        $row = $this->db->fetch($sql, $params) ?: ['due' => 0, 'paid' => 0];
        $due = (float)$row['due'];
        $paid = (float)$row['paid'];
        return ['due' => $due, 'paid' => $paid, 'outstanding' => max(0, $due - $paid)];
    }

    /**
     * The tenant's invoice for one month, with the effective status.
     * Returns null when no invoice exists for that month.
     */
    /**
     * Create the missing invoice for a payment's lease/month/year.
     *
     * A receipt can be recorded for a month that has no invoice, e.g. rent
     * collected for a month before the lease start date (generateForMonth
     * skips those). The payment status is stored on the invoice, so without
     * one the status could not be set at all - the row just showed a dash
     * with no control to change it. This back-fills the invoice from the
     * payment's own amounts so the receipt and its invoice agree, and marks
     * it manual so a later regeneration never silently overwrites the values.
     *
     * @return array the created invoice row
     */
    public function ensureForPayment($payment) {
        $leaseId = (int)$payment['lease_id'];
        $month = (int)$payment['month'];
        $year = (int)$payment['year'];

        $existing = $this->db->fetch(
            "SELECT * FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        if ($existing) {
            return $existing;
        }

        $total = (float)$payment['total_amount'];
        $this->create([
            'lease_id'     => $leaseId,
            'tenant_id'    => (int)$payment['tenant_id'],
            'flat_id'      => (int)$payment['flat_id'],
            'month'        => $month,
            'year'         => $year,
            'rent_amount'  => (float)$payment['amount'],
            'utility_fee'  => 0,
            'parking_amount' => (float)($payment['parking_amount'] ?? 0),
            'gas_amount'   => (float)($payment['gas_amount'] ?? 0),
            'water_fee'    => (float)($payment['water_fee'] ?? 0),
            'waste_fee'    => (float)($payment['waste_fee'] ?? 0),
            'arrears'      => (float)($payment['arrears'] ?? 0),
            'arrears_months' => 0,
            'total_due'    => $total,
            'status'       => 'draft',
            'is_manual'    => 1,
            'note'         => 'Auto-created from payment #' . (int)$payment['id'],
        ]);

        return $this->db->fetch(
            "SELECT * FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
    }

    public function forMonthForTenant($tenantId, $month, $year) {
        return $this->db->fetch(
            "SELECT i.*, COALESCE(NULLIF(i.payment_status_override, ''), i.payment_status) AS status_effective
             FROM invoices i
             WHERE i.tenant_id = :tid AND i.month = :m AND i.year = :y
             ORDER BY i.id DESC
             LIMIT 1",
            ['tid' => (int)$tenantId, 'm' => (int)$month, 'y' => (int)$year]
        );
    }

    /**
     * Does this invoice belong to the given tenant? (ownership check)
     */
    public function belongsToTenant($invoiceId, $tenantId) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM invoices WHERE id = :id AND tenant_id = :tid",
            ['id' => (int)$invoiceId, 'tid' => (int)$tenantId]
        ) > 0;
    }

    public function sendsFor($invoiceId) {
        return $this->db->fetchAll(
            "SELECT * FROM invoice_sends WHERE invoice_id = :id ORDER BY sent_at DESC",
            ['id' => (int)$invoiceId]
        );
    }

    public function hasSent($invoiceId, $channel) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM invoice_sends WHERE invoice_id = :id AND channel = :c AND status = 'sent'",
            ['id' => (int)$invoiceId, 'c' => $channel]
        ) > 0;
    }

    public function addSendLog($invoiceId, $channel, $recipient, $status, $error = null) {
        return $this->db->execute(
            "INSERT INTO invoice_sends (invoice_id, channel, recipient, status, error)
             VALUES (:id, :c, :r, :s, :err)",
            ['id' => (int)$invoiceId, 'c' => $channel, 'r' => $recipient, 's' => $status, 'err' => $error]
        );
    }

    public function markSent($invoiceId) {
        return $this->update((int)$invoiceId, ['status' => 'sent']);
    }

    public function invoiceNo($inv) {
        return 'RMS-' . str_pad((int)$inv['id'], 4, '0', STR_PAD_LEFT);
    }

    /**
     * Deliver an invoice through a channel and record the attempt.
     *
     * $auto marks an automated (cron) send: the whatsapp channel then uses the
     * configured WhatsApp Business API gateway instead of a wa.me link.
     *
     * @return array ['success' => bool, 'message' => string, 'link' => string]
     */
    public function deliver($invoiceId, $channel, $auto = false) {
        $inv = $this->getWithDetails($invoiceId);
        if (!$inv) {
            return ['success' => false, 'message' => t('error_occurred'), 'link' => ''];
        }
        $channel = in_array($channel, ['email', 'whatsapp', 'sms']) ? $channel : 'email';
        $no = $this->invoiceNo($inv);

        if ($channel === 'email') {
            $to = trim((string)$inv['tenant_email']);
            if ($to === '') {
                return ['success' => false, 'message' => t('no_email'), 'link' => ''];
            }
            $subject = t('due_invoice') . ' - ' . $no;
            $result = \Mailer::send($to, $subject, $this->toHtml($inv), $this->toPlain($inv));
            $this->addSendLog($inv['id'], 'email', $to, $result['success'] ? 'sent' : 'failed', $result['success'] ? null : ($result['error'] ?? null));
            if ($result['success']) {
                $this->markSent($inv['id']);
            }
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? t('sent_success') : ($result['error'] ?? t('send_failed')),
                'link' => ''
            ];
        }

        if ($channel === 'sms') {
            $phone = preg_replace('/[^0-9]/', '', enDigits($inv['tenant_phone']));
            if ($phone === '') {
                return ['success' => false, 'message' => t('no_phone'), 'link' => ''];
            }
            $result = \SmsSender::send('88' . $phone, $this->toPlain($inv));
            $this->addSendLog($inv['id'], 'sms', $phone, $result['success'] ? 'sent' : 'failed', $result['success'] ? null : ($result['error'] ?? null));
            if ($result['success']) {
                $this->markSent($inv['id']);
            }
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? t('sent_success') : ($result['error'] ?? t('send_failed')),
                'link' => ''
            ];
        }

        // whatsapp
        $phone = preg_replace('/[^0-9]/', '', enDigits($inv['tenant_phone']));
        if ($phone === '') {
            return ['success' => false, 'message' => t('no_phone'), 'link' => ''];
        }
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '01') {
            $phone = '88' . $phone;
        }

        if ($auto) {
            // Automated send through the WhatsApp Business API gateway. Does not
            // fall back to a wa.me link: an automated run must actually deliver.
            $result = \WhatsAppSender::send($phone, $this->toPlain($inv));
            $this->addSendLog($inv['id'], 'whatsapp', $phone, $result['success'] ? 'sent' : 'failed', $result['success'] ? null : ($result['error'] ?? null));
            if ($result['success']) {
                $this->markSent($inv['id']);
            }
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? t('sent_success') : ($result['error'] ?? t('send_failed')),
                'link' => ''
            ];
        }

        // Manual: generate a wa.me deep link (personal WhatsApp can't be sent
        // from the server). The link is logged as a generated send.
        $link = 'https://wa.me/' . $phone . '?text=' . urlencode($this->toPlain($inv));
        $this->addSendLog($inv['id'], 'whatsapp', $phone, 'sent', null);
        $this->markSent($inv['id']);
        return ['success' => true, 'message' => t('sent_success'), 'link' => $link];
    }

    /**
     * Plain-text invoice body (SMS / WhatsApp / email text part).
     */
    public function toPlain($inv) {
        $lang = lang();
        $cur = CURRENCY;
        $flatLabel = $inv['unit_type'] === 'shop' ? t('shop_no') : t('flat_no');
        $flatValue = bnFlatCode($inv['flat_no']);
        $shopName = trim((string)($inv['shop_name'] ?? ''));
        $lines = [];
        $lines[] = t('due_invoice') . ' - ' . $this->invoiceNo($inv);
        $lines[] = t('tenant_name') . ': ' . localizeName($inv['tenant_name']);
        if ($inv['unit_type'] === 'shop') {
            $lines[] = t('shop_name') . ': ' . ($shopName !== '' ? localizeText($shopName) : '—');
            $lines[] = t('building_name') . ': ' . localizeText($inv['building_name']);
        } else {
            $lines[] = t('building_name') . ': ' . localizeText($inv['building_name']);
        }
        $lines[] = $flatLabel . ': ' . $flatValue;
        $lines[] = t('paid_for') . ': ' . monthName($inv['month']) . ' ' . bnNumeral($inv['year']);
        $lines[] = t('monthly_rent') . ': ' . $cur . ' ' . bnNumeral(number_format((float)$inv['rent_amount'], 2));
        if ((float)$inv['parking_amount'] > 0) {
            $lines[] = t('parking_bill') . ': ' . $cur . ' ' . bnNumeral(number_format((float)$inv['parking_amount'], 2));
        }
        if ((float)$inv['gas_amount'] > 0) {
            $lines[] = t('gas_bill') . ': ' . $cur . ' ' . bnNumeral(number_format((float)$inv['gas_amount'], 2));
        }
        if ((float)$inv['water_fee'] > 0) {
            $lines[] = t('water_bill') . ': ' . $cur . ' ' . bnNumeral(number_format((float)$inv['water_fee'], 2));
        }
        if ((float)$inv['waste_fee'] > 0) {
            $lines[] = t('waste_bill') . ': ' . $cur . ' ' . bnNumeral(number_format((float)$inv['waste_fee'], 2));
        }
        if ((float)$inv['arrears'] > 0) {
            $lines[] = t('arrears') . ' (' . bnNumeral($inv['arrears_months']) . ' ' . t('months') . '): ' . $cur . ' ' . bnNumeral(number_format((float)$inv['arrears'], 2));
        }
        $lines[] = t('total_due') . ': ' . $cur . ' ' . bnNumeral(number_format((float)$inv['total_due'], 2));
        $lines[] = t('amount_words') . ': ' . numberToWords((int)$inv['total_due']) . ' ' . t('taka_only');
        if ($inv['note']) {
            $lines[] = t('note') . ': ' . localizeText($inv['note']);
        }
        return implode("\n", $lines);
    }

    /**
     * Simple inline-styled HTML invoice body for email.
     */
    public function toHtml($inv) {
        $cur = CURRENCY;
        $flatLabel = $inv['unit_type'] === 'shop' ? t('shop_no') : t('flat_no');
        $shopName = trim((string)($inv['shop_name'] ?? ''));
        $rows = function ($label, $value, $bold = false) {
            $b = $bold ? ' style="font-weight:bold;"' : '';
            return '<tr><td style="padding:4px 8px;color:#555;background:#f7f7f7;"' . $b . '>' . $label . '</td>'
                 . '<td style="padding:4px 8px;text-align:right;background:#f7f7f7;"' . $b . '>' . $value . '</td></tr>';
        };
        $body =
            '<div style="max-width:520px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;border:1px solid #ddd;border-radius:8px;overflow:hidden;">'
            . '<div style="background:#007c47;color:#fff;padding:14px 18px;">'
            . '<div style="font-size:18px;font-weight:bold;">' . t('due_invoice') . '</div>'
            . '<div style="font-size:13px;opacity:.9;">' . $this->invoiceNo($inv) . '</div>'
            . '</div>'
            . '<div style="padding:16px 18px;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<tr><td style="padding:2px 8px;">' . t('tenant_name') . '</td><td style="padding:2px 8px;font-weight:600;text-align:right;">' . localizeName($inv['tenant_name']) . '</td></tr>'
            . ($inv['unit_type'] === 'shop'
                ? '<tr><td style="padding:2px 8px;">' . t('shop_name') . '</td><td style="padding:2px 8px;font-weight:600;text-align:right;">' . ($shopName !== '' ? localizeText($shopName) : '—') . '</td></tr>'
                : '')
            . '<tr><td style="padding:2px 8px;">' . t('building_name') . '</td><td style="padding:2px 8px;font-weight:600;text-align:right;">' . localizeText($inv['building_name']) . '</td></tr>'
            . '<tr><td style="padding:2px 8px;">' . $flatLabel . '</td><td style="padding:2px 8px;font-weight:600;text-align:right;">' . bnFlatCode($inv['flat_no']) . '</td></tr>'
            . '<tr><td style="padding:2px 8px;">' . t('paid_for') . '</td><td style="padding:2px 8px;font-weight:600;text-align:right;">' . monthName($inv['month']) . ' ' . bnNumeral($inv['year']) . '</td></tr>'
            . '</table>'
            . '<hr style="border:none;border-top:1px dashed #ccc;margin:12px 0;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . $rows(t('monthly_rent'), $cur . ' ' . bnNumeral(number_format((float)$inv['rent_amount'], 2)))
            . ((float)$inv['parking_amount'] > 0 ? $rows(t('parking_bill'), $cur . ' ' . bnNumeral(number_format((float)$inv['parking_amount'], 2))) : '')
            . ((float)$inv['gas_amount'] > 0 ? $rows(t('gas_bill'), $cur . ' ' . bnNumeral(number_format((float)$inv['gas_amount'], 2))) : '')
            . ((float)$inv['water_fee'] > 0 ? $rows(t('water_bill'), $cur . ' ' . bnNumeral(number_format((float)$inv['water_fee'], 2))) : '')
            . ((float)$inv['waste_fee'] > 0 ? $rows(t('waste_bill'), $cur . ' ' . bnNumeral(number_format((float)$inv['waste_fee'], 2))) : '');
        if ((float)$inv['arrears'] > 0) {
            $body .= $rows(t('arrears') . ' (' . bnNumeral($inv['arrears_months']) . ' ' . t('months') . ')', $cur . ' ' . bnNumeral(number_format((float)$inv['arrears'], 2)));
        }
        $body .= $rows(t('total_due'), $cur . ' ' . bnNumeral(number_format((float)$inv['total_due'], 2)), true)
            . '</table>'
            . '<div style="margin-top:10px;font-size:13px;color:#555;">' . t('amount_words') . ': <strong>' . numberToWords((int)$inv['total_due']) . ' ' . t('taka_only') . '</strong></div>';
        if ($inv['note']) {
            $body .= '<div style="margin-top:8px;font-size:13px;color:#555;">' . t('note') . ': ' . localizeText($inv['note']) . '</div>';
        }
        $body .= '</div></div>';
        return $body;
    }
}