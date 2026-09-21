<?php
// ============================================================
// Expenses - list, add/edit via modal (ajax), delete. Former
// expenses.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class ExpensesController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $expenseModel = new \Expense();
        $expenses = $expenseModel->all('expense_date DESC, id DESC');

        $this->view('expenses/index', [
            'expenses'     => $expenses,
            'expenseModel' => $expenseModel,
        ]);
    }
}