<?php
// ============================================================
// Lease-end advance settlement for shops and commercial floors
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class LeaseSettlement extends BaseModel {
    protected $table = 'lease_settlements';

    /** Build a read-only settlement preview from existing rent invoices. */
    public function preview($leaseId) {
        $lease = (new Lease())->findWithDetails((int)$leaseId);
        if (!$lease) {
            throw new DomainException(t('lease_not_found'));
        }
        if (!in_array($lease['unit_type'] ?? '', ['shop', 'commercial_floor'], true)) {
            throw new DomainException(t('settlement_shop_commercial_only'));
        }
        if (empty($lease['end_date'])) {
            throw new DomainException(t('settlement_end_date_required'));
        }

        $settlement = $this->db->fetch(
            "SELECT * FROM lease_settlements WHERE lease_id = :l LIMIT 1",
            ['l' => (int)$leaseId]
        );
        if ($settlement) {
            return ['lease' => $lease, 'settled' => true, 'settlement' => $settlement];
        }

        $start = new DateTimeImmutable($lease['start_date']);
        $end = new DateTimeImmutable($lease['end_date']);
        if ($end < $start) {
            throw new DomainException(t('settlement_invalid_dates'));
        }

        $dhakaToday = new DateTimeImmutable('today', new DateTimeZone('Asia/Dhaka'));
        if ($end->format('Y-m-d') > $dhakaToday->format('Y-m-d')) {
            return [
                'lease' => $lease,
                'settled' => false,
                'end_date' => $end->format('Y-m-d'),
                'can_settle' => false,
                'future_end_date' => true,
            ];
        }

        $monthStart = $end->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $end->modify('last day of this month')->setTime(0, 0);
        $occupiedStart = $start > $monthStart ? $start : $monthStart;
        $occupiedEnd = $end < $monthEnd ? $end : $monthEnd;
        $daysInMonth = (int)$monthEnd->format('j');
        $daysOccupied = (int)$occupiedStart->diff($occupiedEnd)->days + 1;
        $monthlyRent = (float)$lease['rent_amount'];
        $proratedRent = round($monthlyRent * $daysOccupied / $daysInMonth, 2);
        $endMonth = (int)$end->format('n');
        $endYear = (int)$end->format('Y');
        $startKey = ((int)$start->format('Y')) * 100 + (int)$start->format('n');
        $endKey = $endYear * 100 + $endMonth;

        $rows = $this->db->fetchAll(
            "SELECT i.id, i.month, i.year, i.rent_amount, i.total_due, i.paid_amount AS invoice_paid,
                    COALESCE(p.paid_total, 0) AS paid_total,
                    COALESCE(a.allocated_rent, 0) AS allocated_rent
             FROM invoices i
             LEFT JOIN (
                 SELECT lease_id, month, year, SUM(total_amount) AS paid_total
                 FROM payments GROUP BY lease_id, month, year
             ) p ON p.lease_id = i.lease_id AND p.month = i.month AND p.year = i.year
             LEFT JOIN (
                 SELECT invoice_id, SUM(amount) AS allocated_rent
                 FROM lease_settlement_allocations GROUP BY invoice_id
             ) a ON a.invoice_id = i.id
             WHERE i.lease_id = :l AND (i.year * 100 + i.month) BETWEEN :start_key AND :end_key
             ORDER BY i.year, i.month, i.id",
            ['l' => (int)$leaseId, 'start_key' => $startKey, 'end_key' => $endKey]
        );

        $finalInvoice = $this->db->fetch(
            "SELECT * FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => (int)$leaseId, 'm' => $endMonth, 'y' => $endYear]
        );
        $paymentsByMonth = $this->db->fetchAll(
            "SELECT month, year, SUM(total_amount) AS paid_total
             FROM payments
             WHERE lease_id = :l
               AND (year * 100 + month) BETWEEN :start_key AND :end_key
             GROUP BY year, month",
            ['l' => (int)$leaseId, 'start_key' => $startKey, 'end_key' => $endKey]
        );
        $paidByMonth = [];
        foreach ($paymentsByMonth as $paymentRow) {
            $key = ((int)$paymentRow['year'] * 100) + (int)$paymentRow['month'];
            $paidByMonth[$key] = (float)$paymentRow['paid_total'];
        }

        $rowsByMonth = [];
        foreach ($rows as $row) {
            $key = ((int)$row['year'] * 100) + (int)$row['month'];
            $row['invoice_id'] = (int)$row['id'];
            $row['rent_due'] = $key === $endKey ? $proratedRent : (float)$row['rent_amount'];
            $row['paid_rent'] = max((float)$row['invoice_paid'], (float)$row['paid_total']);
            $row['is_final_month'] = $key === $endKey;
            $row['needs_invoice'] = false;
            $rowsByMonth[$key] = $row;
        }

        // Include any month without an invoice in the lease-end accounting.
        // A confirmed settlement will backfill that month's rent invoice before
        // credit is applied so the adjustment remains auditable and reversible.
        $cursor = $start->modify('first day of this month');
        while (((int)$cursor->format('Y') * 100 + (int)$cursor->format('n')) <= $endKey) {
            $key = ((int)$cursor->format('Y') * 100) + (int)$cursor->format('n');
            if (!isset($rowsByMonth[$key])) {
                $isFinalMonth = $key === $endKey;
                $rentDue = $isFinalMonth ? $proratedRent : $monthlyRent;
                $rowsByMonth[$key] = [
                    'id' => null,
                    'invoice_id' => null,
                    'month' => (int)$cursor->format('n'),
                    'year' => (int)$cursor->format('Y'),
                    'rent_amount' => $rentDue,
                    'rent_due' => $rentDue,
                    'paid_rent' => $paidByMonth[$key] ?? 0,
                    'allocated_rent' => 0,
                    'is_final_month' => $isFinalMonth,
                    'needs_invoice' => true,
                ];
            }
            $cursor = $cursor->modify('+1 month');
        }
        ksort($rowsByMonth);
        $rows = array_values($rowsByMonth);

        $unpaidRent = 0.0;
        $paymentCredit = 0.0;
        foreach ($rows as $row) {
            $balance = (float)$row['rent_due'] - (float)$row['paid_rent'] - (float)$row['allocated_rent'];
            if ($balance > 0.009) {
                $unpaidRent += $balance;
            } elseif ($balance < -0.009) {
                $paymentCredit += abs($balance);
            }
        }
        $unpaidRent = round($unpaidRent, 2);
        $paymentCredit = round($paymentCredit, 2);
        $advance = max(0, (float)$lease['advance_amount']);
        $paymentCreditApplied = min($paymentCredit, $unpaidRent);
        $advanceApplied = min($advance, max(0, $unpaidRent - $paymentCreditApplied));
        $refund = round(max(0, $advance - $advanceApplied) + max(0, $paymentCredit - $paymentCreditApplied), 2);
        $balanceDue = round(max(0, $unpaidRent - $paymentCreditApplied - $advanceApplied), 2);
        return [
            'lease' => $lease,
            'settled' => false,
            'end_date' => $end->format('Y-m-d'),
            'end_month' => $endMonth,
            'end_year' => $endYear,
            'days_occupied' => $daysOccupied,
            'days_in_month' => $daysInMonth,
            'monthly_rent' => round($monthlyRent, 2),
            'prorated_rent' => $proratedRent,
            'eligible_due' => $unpaidRent,
            'payment_credit' => $paymentCredit,
            'advance_amount' => round($advance, 2),
            'advance_applied' => round($advanceApplied, 2),
            'refund_amount' => $refund,
            'balance_due' => $balanceDue,
            'can_settle' => true,
            'rows' => $rows,
            'final_invoice' => $finalInvoice,
            'payment_credit_applied' => round($paymentCreditApplied, 2),
        ];
    }

    /** Apply the preview atomically and save a permanent settlement record. */
    public function settle($leaseId, $userId = null) {
        $connection = $this->db->getConnection();
        $connection->beginTransaction();
        try {
            $this->db->fetch(
                "SELECT id FROM leases WHERE id = :l FOR UPDATE",
                ['l' => (int)$leaseId]
            );
            $calc = $this->preview($leaseId);
            if ($calc['settled']) {
                throw new DomainException(t('settlement_already_done'));
            }
            if (!$calc['can_settle']) {
                throw new DomainException(t('settlement_end_date_not_reached'));
            }

            $lease = $calc['lease'];
            $endMonth = (int)$calc['end_month'];
            $endYear = (int)$calc['end_year'];
            $invoiceModel = new Invoice();
            $finalInvoice = $calc['final_invoice'];
            $finalInvoiceId = 0;
            foreach ($calc['rows'] as &$monthRow) {
                if (!empty($monthRow['invoice_id'])) {
                    $invoiceId = (int)$monthRow['invoice_id'];
                    if (!empty($monthRow['is_final_month'])) {
                        $otherDue = max(0, (float)$finalInvoice['total_due'] - (float)$finalInvoice['rent_amount']);
                        $invoiceModel->update($invoiceId, [
                            'rent_amount' => $calc['prorated_rent'],
                            'total_due' => round($otherDue + $calc['prorated_rent'], 2),
                            'is_manual' => 1,
                        ]);
                    }
                } else {
                    $monthStart = sprintf('%04d-%02d-01', (int)$monthRow['year'], (int)$monthRow['month']);
                    $dueDate = !empty($monthRow['is_final_month'])
                        ? $calc['end_date']
                        : date('Y-m-t', strtotime($monthStart));
                    $invoiceId = (int)$invoiceModel->create([
                        'lease_id' => (int)$lease['id'],
                        'tenant_id' => (int)$lease['tenant_id'],
                        'flat_id' => (int)$lease['flat_id'],
                        'month' => (int)$monthRow['month'],
                        'year' => (int)$monthRow['year'],
                        'rent_amount' => (float)$monthRow['rent_due'],
                        'utility_fee' => (float)$lease['utility_fee'],
                        'parking_amount' => 0,
                        'gas_amount' => 0,
                        'water_fee' => 0,
                        'waste_fee' => 0,
                        'arrears' => 0,
                        'arrears_months' => 0,
                        'total_due' => (float)$monthRow['rent_due'],
                        'status' => 'draft',
                        'is_manual' => 1,
                        'due_date' => $dueDate,
                        'note' => !empty($monthRow['is_final_month'])
                            ? t('settlement_prorated_invoice_note')
                            : t('settlement_backfill_invoice_note'),
                    ]);
                    $monthRow['invoice_id'] = $invoiceId;
                }
                if (!empty($monthRow['is_final_month'])) {
                    $finalInvoiceId = $invoiceId;
                }
            }
            unset($monthRow);

            $settlementId = (int)$this->create([
                'lease_id' => (int)$lease['id'],
                'tenant_id' => (int)$lease['tenant_id'],
                'flat_id' => (int)$lease['flat_id'],
                'end_date' => $calc['end_date'],
                'monthly_rent' => $calc['monthly_rent'],
                'prorated_rent' => $calc['prorated_rent'],
                'days_occupied' => $calc['days_occupied'],
                'days_in_month' => $calc['days_in_month'],
                'eligible_due' => $calc['eligible_due'],
                'payment_credit' => $calc['payment_credit'],
                'advance_amount' => $calc['advance_amount'],
                'advance_applied' => $calc['advance_applied'],
                'refund_amount' => $calc['refund_amount'],
                'balance_due' => $calc['balance_due'],
                'settled_by' => $userId ? (int)$userId : null,
                'settled_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d H:i:s'),
            ]);

            $paymentCreditRemaining = (float)$calc['payment_credit_applied'];
            $advanceRemaining = (float)$calc['advance_applied'];
            $invoiceMonths = [];
            foreach ($calc['rows'] as $row) {
                $invoiceId = !empty($row['invoice_id']) ? (int)$row['invoice_id'] : $finalInvoiceId;
                $balance = max(0, round((float)$row['rent_due'] - (float)$row['paid_rent'] - (float)$row['allocated_rent'], 2));
                if ($balance <= 0.009) {
                    continue;
                }

                $fromPaymentCredit = min($balance, $paymentCreditRemaining);
                if ($fromPaymentCredit > 0.009) {
                    $this->db->execute(
                        "INSERT INTO lease_settlement_allocations
                         (settlement_id, invoice_id, lease_id, month, year, source, amount)
                         VALUES (:s, :i, :l, :m, :y, 'payment_credit', :a)",
                        ['s' => $settlementId, 'i' => $invoiceId, 'l' => (int)$lease['id'], 'm' => (int)$row['month'], 'y' => (int)$row['year'], 'a' => $fromPaymentCredit]
                    );
                    $paymentCreditRemaining -= $fromPaymentCredit;
                    $balance -= $fromPaymentCredit;
                }

                $fromAdvance = min($balance, $advanceRemaining);
                if ($fromAdvance > 0.009) {
                    $this->db->execute(
                        "INSERT INTO lease_settlement_allocations
                         (settlement_id, invoice_id, lease_id, month, year, source, amount)
                         VALUES (:s, :i, :l, :m, :y, 'advance', :a)",
                        ['s' => $settlementId, 'i' => $invoiceId, 'l' => (int)$lease['id'], 'm' => (int)$row['month'], 'y' => (int)$row['year'], 'a' => $fromAdvance]
                    );
                    $advanceRemaining -= $fromAdvance;
                }
                $invoiceMonths[] = ['month' => (int)$row['month'], 'year' => (int)$row['year']];
            }

            $paymentModel = new Payment();
            $invoiceMonths[] = ['month' => $endMonth, 'year' => $endYear];
            foreach ($invoiceMonths as $monthRow) {
                $paymentModel->reconcileInvoice((int)$lease['id'], $monthRow['month'], $monthRow['year']);
            }

            (new Lease())->update((int)$lease['id'], ['status' => 'expired']);
            (new Flat())->refreshStatus((int)$lease['flat_id']);
            $connection->commit();

            return $this->db->fetch("SELECT * FROM lease_settlements WHERE id = :id", ['id' => $settlementId]);
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }
}
