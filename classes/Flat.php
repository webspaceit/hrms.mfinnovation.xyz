<?php
// ============================================================
// Flat Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Flat extends BaseModel {
    protected $table = 'flats';

    public function allWithBuilding($type = '') {
        $sql = "
            SELECT f.*, b.name AS building_name
            FROM flats f
            LEFT JOIN buildings b ON b.id = f.building_id
        ";
        $params = [];
        if ($type) {
            $sql .= " WHERE f.unit_type = :type";
            $params['type'] = $type;
        }
        $sql .= " ORDER BY b.name, f.flat_no";
        return $this->db->fetchAll($sql, $params);
    }

    public function getCurrentTenant($flatId) {
        return $this->db->fetch("
            SELECT t.*, l.id AS lease_id
            FROM leases l
            JOIN tenants t ON t.id = l.tenant_id
            WHERE l.flat_id = :id AND l.status = 'active'
            LIMIT 1
        ", ['id' => $flatId]);
    }

    public function availableFlats($type = '') {
        $sql = "
            SELECT f.*, b.name AS building_name
            FROM flats f
            LEFT JOIN buildings b ON b.id = f.building_id
            WHERE f.status = 'available'
        ";
        $params = [];
        if ($type) {
            $sql .= " AND f.unit_type = :type";
            $params['type'] = $type;
        }
        $sql .= " ORDER BY f.unit_type, b.name, f.flat_no";
        return $this->db->fetchAll($sql, $params);
    }

    public function countByType($type) {
        return $this->count("unit_type = :type", ['type' => $type]);
    }

    /**
     * Update flat status based on leases
     */
    public function refreshStatus($flatId) {
        $active = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM leases WHERE flat_id = :id AND status = 'active'",
            ['id' => $flatId]
        );
        if ($active > 0) {
            $this->update($flatId, ['status' => 'occupied']);
        } else {
            // If no active lease but was set by maintenance, keep as is
            $flat = $this->find($flatId);
            if ($flat && $flat['status'] != 'maintenance') {
                $this->update($flatId, ['status' => 'available']);
            }
        }
    }
}
