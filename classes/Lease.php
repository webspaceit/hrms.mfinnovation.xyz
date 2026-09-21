<?php
// ============================================================
// Lease Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Lease extends BaseModel {
    protected $table = 'leases';

    public function allWithDetails() {
        return $this->db->fetchAll("
            SELECT l.*, t.name AS tenant_name, t.phone AS tenant_phone,
                   f.flat_no, f.unit_type, f.advance_amount AS unit_advance, b.name AS building_name
            FROM leases l
            LEFT JOIN tenants t ON t.id = l.tenant_id
            LEFT JOIN flats f ON f.id = l.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            ORDER BY l.start_date DESC
        ");
    }

    public function findWithDetails($id) {
        return $this->db->fetch("
            SELECT l.*, t.name AS tenant_name, f.flat_no, f.unit_type, f.advance_amount AS unit_advance, b.name AS building_name
            FROM leases l
            LEFT JOIN tenants t ON t.id = l.tenant_id
            LEFT JOIN flats f ON f.id = l.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            WHERE l.id = :id
        ", ['id' => $id]);
    }

    public function activeLeases() {
        return $this->db->fetchAll("
            SELECT l.*, t.name AS tenant_name, t.phone AS tenant_phone,
                   f.flat_no, f.unit_type, b.name AS building_name
            FROM leases l
            LEFT JOIN tenants t ON t.id = l.tenant_id
            LEFT JOIN flats f ON f.id = l.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            WHERE l.status = 'active'
            ORDER BY l.start_date DESC
        ");
    }

    /**
     * Auto-expire leases whose end_date has passed
     */
    public function checkExpired() {
        $today = date('Y-m-d');
        $this->db->execute(
            "UPDATE leases SET status = 'expired' WHERE status = 'active' AND end_date IS NOT NULL AND end_date < :today",
            ['today' => $today]
        );
        return true;
    }

    public function expectedMonthlyIncome() {
        return (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(rent_amount + utility_fee), 0) FROM leases WHERE status = 'active'"
        );
    }

    public function hasActiveLeaseForFlat($flatId, $excludeLeaseId = null) {
        $sql = "SELECT COUNT(*) FROM leases WHERE flat_id = :flat AND status = 'active'";
        $params = ['flat' => $flatId];
        if ($excludeLeaseId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeLeaseId;
        }
        return (int)$this->db->fetchColumn($sql, $params) > 0;
    }

    // ------------------------------------------------------------
    // Tenant portal (queries scoped to one tenant id)
    // ------------------------------------------------------------

    /**
     * Every lease of one tenant with flat / building details.
     * The active lease comes first, then the most recent ones.
     */
    public function forTenant($tenantId) {
        return $this->db->fetchAll(
            "SELECT l.*, f.flat_no, f.unit_type, f.floor, f.bedrooms, f.bathrooms, f.size_sqft,
                    b.name AS building_name, b.address AS building_address
             FROM leases l
             LEFT JOIN flats f ON f.id = l.flat_id
             LEFT JOIN buildings b ON b.id = f.building_id
             WHERE l.tenant_id = :tid
             ORDER BY (l.status = 'active') DESC, l.start_date DESC, l.id DESC",
            ['tid' => (int)$tenantId]
        );
    }

    /**
     * The lease that represents the tenant's current home: the active one when
     * present, otherwise the most recent lease. Null when the tenant has none.
     */
    public function currentForTenant($tenantId) {
        $rows = $this->forTenant($tenantId);
        return $rows ? $rows[0] : null;
    }
}
