<?php
// ============================================================
// Building Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Building extends BaseModel {
    protected $table = 'buildings';

    public function countFlats($buildingId) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM flats WHERE building_id = :id",
            ['id' => $buildingId]
        );
    }

    public function countAvailableFlats($buildingId) {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM flats WHERE building_id = :id AND status = 'available'",
            ['id' => $buildingId]
        );
    }

    public function getFlats($buildingId) {
        return $this->db->fetchAll(
            "SELECT * FROM flats WHERE building_id = :id ORDER BY flat_no",
            ['id' => $buildingId]
        );
    }

    public function allWithStats() {
        // Landlords only see buildings that contain a unit leased to a tenant
        // they entered (admins / anonymous see everything).
        $scope = buildingScope('b');
        $where = $scope !== '' ? " WHERE 1=1$scope" : '';
        return $this->db->fetchAll("
            SELECT b.*,
                   COUNT(f.id) AS total_flats,
                   SUM(CASE WHEN f.status = 'available' THEN 1 ELSE 0 END) AS available_flats,
                   SUM(CASE WHEN f.unit_type = 'flat' THEN 1 ELSE 0 END) AS flat_count,
                   SUM(CASE WHEN f.unit_type = 'shop' THEN 1 ELSE 0 END) AS shop_count,
                   SUM(CASE WHEN f.unit_type = 'flat' AND f.status = 'available' THEN 1 ELSE 0 END) AS available_flat_count,
                   SUM(CASE WHEN f.unit_type = 'shop' AND f.status = 'available' THEN 1 ELSE 0 END) AS available_shop_count
            FROM buildings b
            LEFT JOIN flats f ON f.building_id = b.id
            $where
            GROUP BY b.id
            ORDER BY b.name
        ");
    }
}
