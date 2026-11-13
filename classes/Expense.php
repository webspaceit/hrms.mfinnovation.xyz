<?php
// ============================================================
// Expense Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Expense extends BaseModel {
    protected $table = 'expenses';

    public function all($orderBy = 'id DESC') {
        $scope = expenseScope('e');
        if ($scope !== '') {
            return $this->db->fetchAll("SELECT e.* FROM expenses e WHERE 1=1$scope ORDER BY $orderBy");
        }
        return parent::all($orderBy);
    }

    public function totalExpense($year = null, $month = null) {
        $sql = "SELECT COALESCE(SUM(e.amount), 0) FROM expenses e";
        $params = [];
        $conditions = [];
        if ($year) {
            $conditions[] = "YEAR(e.expense_date) = :year";
            $params['year'] = $year;
        }
        if ($month) {
            $conditions[] = "MONTH(e.expense_date) = :month";
            $params['month'] = $month;
        }
        $scope = expenseScope('e');
        if ($scope !== '') {
            $conditions[] = ltrim($scope);
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        return (float)$this->db->fetchColumn($sql, $params);
    }

    public function monthlySummary($year) {
        return $this->db->fetchAll("
            SELECT MONTH(e.expense_date) AS month, COALESCE(SUM(e.amount), 0) AS total
            FROM expenses e
            WHERE YEAR(e.expense_date) = :year" . expenseScope('e') . "
            GROUP BY MONTH(e.expense_date)
            ORDER BY month
        ", ['year' => $year]);
    }
}
