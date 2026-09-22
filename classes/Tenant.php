<?php
// ============================================================
// Tenant Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Tenant extends BaseModel {
    protected $table = 'tenants';

    public function allWithLease() {
        $scope = tenantScope('t');
        return $this->db->fetchAll("
            SELECT t.*, l.id AS lease_id, l.flat_id, l.rent_amount, l.status AS lease_status,
                   f.flat_no, f.unit_type, f.advance_amount, b.name AS building_name,
                   cu.full_name AS created_by_name
            FROM tenants t
            LEFT JOIN leases l ON l.tenant_id = t.id AND l.status = 'active'
            LEFT JOIN flats f ON f.id = l.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            LEFT JOIN users cu ON cu.id = t.created_by
            " . ($scope !== '' ? "WHERE 1=1" . $scope : "") . "
            ORDER BY t.name
        ");
    }

    /**
     * The tenant's current unit (via their active lease), including the
     * unit's advance amount as maintained on the Flats page.
     */
    public function currentUnit($tenantId) {
        return $this->db->fetch("
            SELECT l.id AS lease_id, l.flat_id,
                   f.flat_no, f.unit_type, f.advance_amount,
                   b.name AS building_name
            FROM leases l
            JOIN flats f ON f.id = l.flat_id
            LEFT JOIN buildings b ON b.id = f.building_id
            WHERE l.tenant_id = :id AND l.status = 'active'
            LIMIT 1
        ", ['id' => (int)$tenantId]);
    }

    public function search($term) {
        return $this->db->fetchAll(
            "SELECT * FROM tenants WHERE (name LIKE :name OR phone LIKE :phone OR email LIKE :email)" . tenantScope(''),
            ['name' => "%$term%", 'phone' => "%$term%", 'email' => "%$term%"]
        );
    }

    /**
     * Active tenants visible to the current user (all of them for admins;
     * only the user's own for landlords). Used for dropdowns such as the
     * lease form so landlords never see other tenants' names.
     */
    public function activeVisible() {
        return $this->db->fetchAll(
            "SELECT * FROM tenants WHERE status = 'active'" . tenantScope('')
        );
    }

    // ------------------------------------------------------------
    // Tenant portal (self-service login + profile)
    // ------------------------------------------------------------

    /**
     * Find a tenant account by portal login identifier. Accepts the portal
     * username, the email address or the phone number. Only enabled accounts
     * that already have a password may log in.
     */
    public function findByPortalLogin($identifier) {
        $row = $this->findByPortalLoginRaw($identifier);
        if ($row && (int)$row['portal_enabled'] === 1) {
            return $row;
        }
        return null;
    }

    /**
     * Same lookup as findByPortalLogin but ignores the portal_enabled flag
     * (used by Auth to give a disabled account a specific error message).
     */
    public function findByPortalLoginRaw($identifier) {
        $identifier = trim((string)$identifier);
        if ($identifier === '') {
            return null;
        }
        return $this->db->fetch(
            "SELECT * FROM tenants
             WHERE portal_password IS NOT NULL AND portal_password <> ''
               AND (portal_username = :u OR email = :e OR phone = :p)
             LIMIT 1",
            ['u' => $identifier, 'e' => $identifier, 'p' => $identifier]
        );
    }

    /**
     * Is this portal username already taken by another tenant?
     */
    public function portalUsernameTaken($username, $excludeId = 0) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM tenants WHERE portal_username = :u AND id <> :id",
            ['u' => (string)$username, 'id' => (int)$excludeId]
        ) > 0;
    }

    /**
     * Store a new portal password (hashed with password_hash).
     */
    public function setPortalPassword($id, $plainPassword) {
        return $this->update((int)$id, [
            'portal_password' => password_hash($plainPassword, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * Record the last successful portal login.
     */
    public function touchPortalLogin($id) {
        return $this->db->execute(
            "UPDATE tenants SET portal_last_login = NOW() WHERE id = :id",
            ['id' => (int)$id]
        );
    }
}
