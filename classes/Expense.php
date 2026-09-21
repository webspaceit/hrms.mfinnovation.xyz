<?php
// ============================================================
// Expense Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class Expense extends BaseModel {
    protected $table = 'expenses';

    public function totalExpense($year = null, $month = null) {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM expenses";
        $params = [];
        $conditions = [];
        if ($year) {
            $conditions[] = "YEAR(expense_date) = :year";
            $params['year'] = $year;
        }
        if ($month) {
            $conditions[] = "MONTH(expense_date) = :month";
            $params['month'] = $month;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        return (float)$this->db->fetchColumn($sql, $params);
    }

    public function monthlySummary($year) {
        return $this->db->fetchAll("
            SELECT MONTH(expense_date) AS month, COALESCE(SUM(amount), 0) AS total
            FROM expenses
            WHERE YEAR(expense_date) = :year
            GROUP BY MONTH(expense_date)
            ORDER BY month
        ", ['year' => $year]);
    }
}
