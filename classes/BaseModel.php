<?php
// ============================================================
// Base Model Class - provides CRUD operations
// ============================================================

require_once __DIR__ . '/Database.php';

class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct($table = null) {
        $this->db = Database::getInstance();
        if ($table) {
            $this->table = $table;
        }
    }

    /**
     * Get all records
     */
    public function all($orderBy = 'id DESC') {
        return $this->db->fetchAll("SELECT * FROM {$this->table} ORDER BY $orderBy");
    }

    /**
     * Find a record by id
     */
    public function find($id) {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id", ['id' => $id]);
    }

    /**
     * Insert a new record
     */
    public function create($data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $this->db->execute($sql, $data);
        return $this->db->lastInsertId();
    }

    /**
     * Update a record
     */
    public function update($id, $data) {
        $sets = [];
        $params = ['id' => $id];
        foreach ($data as $key => $value) {
            $sets[] = "$key = :$key";
            $params[$key] = $value;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = :id";
        return $this->db->execute($sql, $params);
    }

    /**
     * Delete a record
     */
    public function delete($id) {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * Count records
     */
    public function count($where = null, $params = []) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE $where";
        }
        return (int)$this->db->fetchColumn($sql, $params);
    }

    /**
     * Query with where conditions
     */
    public function where($column, $value) {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE $column = :value",
            ['value' => $value]
        );
    }
}
