<?php
// ============================================================
// Payment Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Payment extends BaseModel {
    protected $table = 'payments';

    public function allWithDetails() {
        $scope = tenantScope('t');
        return $this->db->fetchAll("
            SELECT p.*, t.name AS tenant_name, f.flat_no, f.unit_type, b.name AS building_name, u.full_name AS collector,
                   iv.total_due, iv.paid_amount AS inv_paid, iv.payment_status, iv.payment_status_override,
                   COALESCE(NULLIF(iv.payment_status_override, ''), iv.payment_status) AS inv_status_effective
            FROM payments p
            LEFT JOIN tenants t ON t.id = p.tenant_id
            LEFT JOIN flats f ON f.id = p.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            LEFT JOIN users u ON u.id = p.received_by
            LEFT JOIN invoices iv ON iv.lease_id = p.lease_id AND iv.month = p.month AND iv.year = p.year
            " . ($scope !== '' ? "WHERE 1=1" . $scope : "") . "
            ORDER BY p.payment_date DESC, p.id DESC
        ");
    }

    public function getReceiptData($id) {
        return $this->db->fetch("
            SELECT p.*, t.name AS tenant_name, t.phone AS tenant_phone, t.address AS tenant_address, t.email AS tenant_email,
                   f.flat_no, f.unit_type, b.name AS building_name, b.address AS building_address,
                   u.full_name AS collector, u.username AS collector_user
            FROM payments p
            LEFT JOIN tenants t ON t.id = p.tenant_id
            LEFT JOIN flats f ON f.id = p.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            LEFT JOIN users u ON u.id = p.received_by
            WHERE p.id = :id" . tenantScope('t'),
            ['id' => $id]
        );
    }

    public function lastN($limit = 10) {
        $scope = tenantScope('t');
        return $this->db->fetchAll("
            SELECT p.*, t.name AS tenant_name, f.flat_no, b.name AS building_name
            FROM payments p
            LEFT JOIN tenants t ON t.id = p.tenant_id
            LEFT JOIN flats f ON f.id = p.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            " . ($scope !== '' ? "WHERE 1=1" . $scope : "") . "
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT " . (int)$limit
        );
    }

    /**
     * Check if a payment already exists for a lease in a given month/year
     */
    public function existsForMonth($leaseId, $month, $year) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM payments WHERE lease_id = :lease AND month = :month AND year = :year",
            ['lease' => $leaseId, 'month' => $month, 'year' => $year]
        ) > 0;
    }

    /**
     * Check if a payment already exists for a lease in a given month/year,
     * excluding the given payment id (used when editing).
     */
    public function existsForMonthExcept($paymentId, $leaseId, $month, $year) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM payments WHERE lease_id = :lease AND month = :month AND year = :year AND id <> :id",
            ['lease' => $leaseId, 'month' => $month, 'year' => $year, 'id' => $paymentId]
        ) > 0;
    }

    public function totalCollected($year = null, $month = null) {
        $scope = tenantScope('t');
        $sql = $scope !== ''
            ? "SELECT COALESCE(SUM(p.total_amount), 0) FROM payments p
               JOIN tenants t ON t.id = p.tenant_id WHERE 1=1" . $scope
            : "SELECT COALESCE(SUM(total_amount), 0) FROM payments";
        $params = [];
        $conditions = [];
        if ($year) {
            $conditions[] = "year = :year";
            $params['year'] = $year;
        }
        if ($month) {
            $conditions[] = "month = :month";
            $params['month'] = $month;
        }
        if ($conditions) {
            $sql .= $scope !== '' ? " AND " . implode(' AND ', $conditions) : " WHERE " . implode(' AND ', $conditions);
        }
        return (float)$this->db->fetchColumn($sql, $params);
    }

    public function monthlySummary($year) {
        $scope = tenantScope('t');
        if ($scope !== '') {
            return $this->db->fetchAll("
                SELECT month, COALESCE(SUM(p.total_amount), 0) AS total
                FROM payments p
                JOIN tenants t ON t.id = p.tenant_id
                WHERE year = :year" . $scope . "
                GROUP BY month
                ORDER BY month
            ", ['year' => $year]);
        }
        return $this->db->fetchAll("
            SELECT month, COALESCE(SUM(total_amount), 0) AS total
            FROM payments
            WHERE year = :year
            GROUP BY month
            ORDER BY month
        ", ['year' => $year]);
    }

    public function perBuildingSummary($year) {
        $scope = tenantScope('t');
        if ($scope !== '') {
            // Landlord: report their own income per building while still
            // keeping every building visible (rows with no income show 0).
            $uid = (int)Auth::id();
            return $this->db->fetchAll("
                SELECT b.name, COALESCE(SUM(p.total_amount), 0) AS total
                FROM buildings b
                LEFT JOIN flats f ON f.building_id = b.id
                LEFT JOIN payments p ON p.flat_id = f.id AND p.year = :year
                    AND p.tenant_id IN (SELECT id FROM tenants WHERE created_by = $uid)
                GROUP BY b.id, b.name
                ORDER BY total DESC
            ", ['year' => $year]);
        }
        return $this->db->fetchAll("
            SELECT b.name, COALESCE(SUM(p.total_amount), 0) AS total
            FROM buildings b
            LEFT JOIN flats f ON f.building_id = b.id
            LEFT JOIN payments p ON p.flat_id = f.id AND p.year = :year
            GROUP BY b.id, b.name
            ORDER BY total DESC
        ", ['year' => $year]);
    }

    /**
     * Sum of all installments collected for a lease/month/year.
     */
    public function sumForInvoice($leaseId, $month, $year) {
        return (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_amount), 0) FROM payments
             WHERE lease_id = :lease AND month = :month AND year = :year",
            ['lease' => $leaseId, 'month' => $month, 'year' => $year]
        );
    }

    /**
     * Remaining due after installments collected. Returns null when no invoice
     * exists for the lease/month/year (i.e. no cap applies), otherwise >= 0.
     */
    public function remainingDue($leaseId, $month, $year) {
        $due = (float)$this->db->fetchColumn(
            "SELECT total_due FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        $hasInvoice = $this->db->fetch(
            "SELECT id FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        if (!$hasInvoice) {
            return null;
        }
        return max(0, (float)$due - $this->sumForInvoice($leaseId, $month, $year));
    }

    /**
     * Recompute and store the invoice's paid_amount / payment_status / paid_at
     * from the payments collected for the same lease/month/year.
     *
     * Mirrors the reference app: status is stored on the bill and derived from
     * cumulative installments vs total due.
     */
    public function reconcileInvoice($leaseId, $month, $year) {
        $inv = $this->db->fetch(
            "SELECT id, total_due FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        if (!$inv) {
            return null;
        }

        $sum = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_amount), 0) FROM payments
             WHERE lease_id = :l AND month = :m AND year = :y",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        $due = (float)$inv['total_due'];
        $paidAt = $this->db->fetchColumn(
            "SELECT MAX(created_at) FROM payments WHERE lease_id = :l AND month = :m AND year = :y",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );

        $status = $sum <= 0.009 ? 'unpaid' : ($sum >= $due - 0.009 ? 'paid' : 'partial');
        $this->db->execute(
            "UPDATE invoices SET paid_amount = :pa, payment_status = :ps, paid_at = :pt WHERE id = :id",
            [
                'pa' => min($sum, $due),
                'ps' => $status,
                'pt' => $sum > 0 ? ($paidAt ?: date('Y-m-d H:i:s')) : null,
                'id' => (int)$inv['id'],
            ]
        );
        return $status;
    }

    /**
     * Sum of rent installments collected for a lease/month/year.
     */
    public function sumRentForInvoice($leaseId, $month, $year) {
        return (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE lease_id = :lease AND month = :month AND year = :year",
            ['lease' => $leaseId, 'month' => $month, 'year' => $year]
        );
    }

    /**
     * Remaining rent for a lease/month/year. Returns null when no invoice
     * exists (i.e. no cap applies), otherwise >= 0. Service bills (utility,
     * parking, gas, water, waste) are additional charges and not capped.
     */
    public function remainingRent($leaseId, $month, $year) {
        $hasInvoice = $this->db->fetch(
            "SELECT id FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        if (!$hasInvoice) {
            return null;
        }
        $due = (float)$this->db->fetchColumn(
            "SELECT total_due FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        return max(0, $due - $this->sumRentForInvoice($leaseId, $month, $year));
    }

    /**
     * Reconcile every invoice of a month (after generation/regeneration).
     */
    public function reconcileAllForMonth($month, $year) {
        $rows = $this->db->fetchAll(
            "SELECT id, lease_id FROM invoices WHERE month = :m AND year = :y",
            ['m' => $month, 'y' => $year]
        );
        $count = 0;
        foreach ($rows as $r) {
            $this->reconcileInvoice($r['lease_id'], $month, $year);
            $count++;
        }
        return $count;
    }

    // ------------------------------------------------------------
    // Tenant portal (every query is scoped to one tenant id)
    // ------------------------------------------------------------

    /**
     * Rent collections of a single tenant, newest first (optionally one year).
     */
    public function listForTenant($tenantId, $year = null) {
        $sql = "SELECT p.*, f.flat_no, f.unit_type, b.name AS building_name,
                       iv.total_due, iv.paid_amount AS inv_paid,
                       COALESCE(NULLIF(iv.payment_status_override, ''), iv.payment_status) AS inv_status_effective
                FROM payments p
                LEFT JOIN flats f ON f.id = p.flat_id
                LEFT JOIN buildings b ON b.id = f.building_id
                LEFT JOIN invoices iv ON iv.lease_id = p.lease_id AND iv.month = p.month AND iv.year = p.year
                WHERE p.tenant_id = :tid";
        $params = ['tid' => (int)$tenantId];
        if ($year) {
            $sql .= " AND p.year = :year";
            $params['year'] = (int)$year;
        }
        return $this->db->fetchAll($sql . " ORDER BY p.payment_date DESC, p.id DESC", $params);
    }

    /**
     * Years in which the tenant has payments (for the year filter).
     */
    public function yearsForTenant($tenantId) {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT year FROM payments WHERE tenant_id = :tid ORDER BY year DESC",
            ['tid' => (int)$tenantId]
        );
        return array_map('intval', array_column($rows, 'year'));
    }

    /**
     * Total collected from a tenant (optionally within one year).
     */
    public function totalForTenant($tenantId, $year = null) {
        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE tenant_id = :tid";
        $params = ['tid' => (int)$tenantId];
        if ($year) {
            $sql .= " AND year = :year";
            $params['year'] = (int)$year;
        }
        return (float)$this->db->fetchColumn($sql, $params);
    }

    /**
     * A single payment owned by this tenant (used for receipt access checks).
     */
    public function findForTenant($paymentId, $tenantId) {
        return $this->db->fetch(
            "SELECT * FROM payments WHERE id = :id AND tenant_id = :tid",
            ['id' => (int)$paymentId, 'tid' => (int)$tenantId]
        );
    }

    /**
     * Record the remaining due as an installment and mark the invoice paid.
     * $date is the actual payment date (defaults to today) and is also stored
     * as the invoice's paid_at.
     */
    public function markFullyPaid($leaseId, $month, $year, $method = 'cash', $date = null) {
        $date = $date ?: date('Y-m-d');
        $lease = (new Lease())->find($leaseId);
        if (!$lease) {
            return null;
        }

        $inv = $this->db->fetch(
            "SELECT id, total_due FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
            ['l' => $leaseId, 'm' => $month, 'y' => $year]
        );
        if (!$inv) {
            return null;
        }

        $paid = $this->sumForInvoice($leaseId, $month, $year);
        $remaining = max(0, round((float)$inv['total_due'] - $paid, 2));
        if ($remaining <= 0.009) {
            $this->reconcileInvoice($leaseId, $month, $year);
            $this->db->execute(
                "UPDATE invoices SET paid_at = :pt WHERE id = :id",
                ['pt' => $date . ' 12:00:00', 'id' => (int)$inv['id']]
            );
            return $remaining;
        }

        $this->create([
            'lease_id' => (int)$leaseId,
            'tenant_id' => (int)$lease['tenant_id'],
            'flat_id' => (int)$lease['flat_id'],
            'month' => (int)$month,
            'year' => (int)$year,
            'amount' => $remaining,
            'utility_fee' => 0,
            'parking_amount' => 0,
            'gas_amount' => 0,
            'water_fee' => 0,
            'waste_fee' => 0,
            'total_amount' => $remaining,
            'payment_method' => $method,
            'receipt_type' => 'rent',
            'received_by' => Auth::id(),
            'payment_date' => $date,
            'note' => t('marked_fully_paid'),
        ]);

        $this->reconcileInvoice($leaseId, $month, $year);
        $this->db->execute(
            "UPDATE invoices SET paid_at = :pt WHERE id = :id",
            ['pt' => $date . ' 12:00:00', 'id' => (int)$inv['id']]
        );
        return $remaining;
    }
}
