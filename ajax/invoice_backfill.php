<?php
// ============================================================
// Invoice AJAX - Backfill invoices across a range of previous
// months. Month-by-month, oldest first, so each month's
// reconcileAllForMonth() sees the payments already recorded.
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$fromMonth = (int)post('from_month', 0);
$fromYear  = (int)post('from_year', 0);
$toMonth   = (int)post('to_month', 0);
$toYear    = (int)post('to_year', 0);

foreach ([[$fromMonth, 'from_month'], [$toMonth, 'to_month']] as [$m, $label]) {
    if ($m < 1 || $m > 12) {
        jsonResponse(['success' => false, 'message' => t('error_occurred')], 400);
    }
}
foreach ([[$fromYear, 'from_year'], [$toYear, 'to_year']] as [$y, $label]) {
    if ($y < 2000 || $y > 2100) {
        jsonResponse(['success' => false, 'message' => t('error_occurred')], 400);
    }
}

// Compare as year-month so a range like 12/2025 -> 02/2026 works.
$from = $fromYear * 100 + $fromMonth;
$to   = $toYear * 100 + $toMonth;
if ($from > $to) {
    jsonResponse(['success' => false, 'message' => t('invalid_range')], 400);
}

$invoiceModel = new Invoice();

$totalCreated = 0;
$totalUpdated = 0;
$totalSkipped = 0;

// Step back one month at a time from $to. Adding $month-- would roll
// January back into December of the previous year, so decrement by
// year when the month hits zero.
$month = $toMonth;
$year  = $toYear;

while ($year * 100 + $month >= $from) {
    [$created, $updated, $skipped] = $invoiceModel->generateForMonth($month, $year);
    $totalCreated += $created;
    $totalUpdated += $updated;
    $totalSkipped += $skipped;

    $month--;
    if ($month < 1) {
        $month = 12;
        $year--;
    }
    if ($year < 2000) {
        break;
    }
}

$total = $totalCreated + $totalUpdated;

if ($total > 0) {
    $message = t('generated_success') . ' (' . $totalCreated . ' ' . t('created') . ', ' . $totalUpdated . ' ' . t('updated') . ')';
} else {
    // Not an error: the range is simply outside every lease's active period
    // (generateForMonth only bills leases that were running that month). Say
    // so plainly instead of reporting a failure, which is what made this look
    // broken when it had in fact done exactly the right thing.
    $message = t('backfill_nothing');
}

if ($totalSkipped > 0) {
    $message .= ' ' . str_replace('{n}', (string)$totalSkipped, t('manual_skipped'));
}

jsonResponse([
    'success' => true,
    'created' => $totalCreated,
    'updated' => $totalUpdated,
    'skipped' => $totalSkipped,
    'nothing' => $total === 0,
    'message' => $message,
]);