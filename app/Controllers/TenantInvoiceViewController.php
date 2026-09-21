<?php
// ============================================================
// Tenant Portal - Invoice (Demand Note) View (printable +
// sendable). Forces tenant-only access and ownership, renders
// the tenant copy of the demand note. Former tenant_invoice_view.php
// (wrapper around invoice_view.php), now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantInvoiceViewController extends Controller {

    public function index(): void {
        \Auth::requireTenantLogin();

        // Per-request language override: tenant-invoice-view?id=5&lang=bn
        if (isset($_GET['lang'])) {
            \Lang::override($_GET['lang']);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            redirect('tenant-dues');
        }

        // Verify ownership before rendering
        $invoiceModel = new \Invoice();
        $inv = $invoiceModel->getWithDetails($id);

        if (!$inv || (int)$inv['tenant_id'] !== \Auth::tenantId()) {
            \Session::setFlash('danger', t('access_denied'));
            redirect('tenant-dues');
        }

        // Tenant portal context (hides admin toolbar actions / send buttons)
        $isTenantPortal = true;

        $currentLang = \Lang::current();
        $siteName = t('app_name');
        $no = $invoiceModel->invoiceNo($inv);
        $cur = CURRENCY;
        $totalDue = (float)$inv['total_due'];
        $amountWords = numberToWords((int)$totalDue);
        $monthLabel = monthName($inv['month']) . ' ' . $inv['year'];
        $monthNameBn = monthName($inv['month'], 'bn');
        $propertyType = $inv['unit_type'] === 'shop' ? 'shop' : 'apartment';
        $propertyLabel = $propertyType === 'shop' ? t('shop') : t('flat_no');
        $propertyLabelEn = $propertyType === 'shop' ? 'Shop' : 'Flat';
        $propertyLabelBn = $propertyType === 'shop' ? 'দোকান' : 'অ্যাপার্টমেন্ট';
        $issuedDate = date('d-m-Y');
        $dueDate = date('d-m-Y', strtotime(sprintf('%04d-%02d-01', $inv['year'], $inv['month']) . ' +1 month -1 day'));

        // Invoice type label (mirrors the receipt badge)
        $typeLabel = $propertyType === 'shop'
            ? ($currentLang === 'bn' ? 'দোকান ভাড়া' : 'Shop Rent')
            : ($currentLang === 'bn' ? 'মাসিক ভাড়া' : 'Monthly Rent');

        // WhatsApp link (BD mobile format)
        $phoneDigits = preg_replace('/[^0-9]/', '', enDigits($inv['tenant_phone']));
        if (strlen($phoneDigits) === 11 && substr($phoneDigits, 0, 2) === '01') {
            $phoneDigits = '88' . $phoneDigits;
        }
        $waText = $invoiceModel->toPlain($inv);
        $waUrl = $phoneDigits ? 'https://wa.me/' . $phoneDigits . '?text=' . urlencode($waText) : '#';

        $fontFamily = $currentLang === 'bn'
            ? "'Noto Serif Bengali', 'SolaimanLipi', serif"
            : "'Helvetica Neue', Helvetica, Arial, sans-serif";

        // ---- Payment status watermark ----
        // Status is stored on the invoice and maintained by Payments (installments
        // accumulate until they cover the total due — mirroring the reference app).
        $totalPaid = (float)$inv['paid_amount'];
        $paymentStatus = (string)(!empty($inv['payment_status_override'])
            ? $inv['payment_status_override']
            : ($inv['payment_status'] ?? 'unpaid'));

        if ($currentLang === 'bn') {
            $watermarkLabels = [
                'paid'    => 'পরিশোধিত',
                'partial' => 'আংশিক পরিশোধিত',
                'unpaid'  => 'অপরিশোধিত',
            ];
        } else {
            $watermarkLabels = [
                'paid'    => 'PAID',
                'partial' => 'PARTIAL',
                'unpaid'  => 'UNPAID',
            ];
        }
        $watermarkText  = $watermarkLabels[$paymentStatus];
        $watermarkColor = $paymentStatus === 'paid'
            ? 'rgba(0,150,60,0.18)'
            : ($paymentStatus === 'partial'
                ? 'rgba(217,119,6,0.20)'
                : 'rgba(220,38,38,0.18)');
        $paidAmountDisplay = $currentLang === 'bn'
            ? bnNumeral(number_format($totalPaid, 2))
            : number_format($totalPaid, 2);

        $this->view('tenant-invoice-view/index', [
            'inv'               => $inv,
            'isTenantPortal'    => $isTenantPortal,
            'no'                => $no,
            'cur'               => $cur,
            'totalDue'          => $totalDue,
            'amountWords'       => $amountWords,
            'monthLabel'        => $monthLabel,
            'monthNameBn'       => $monthNameBn,
            'propertyType'      => $propertyType,
            'propertyLabel'     => $propertyLabel,
            'propertyLabelEn'   => $propertyLabelEn,
            'propertyLabelBn'   => $propertyLabelBn,
            'issuedDate'        => $issuedDate,
            'dueDate'           => $dueDate,
            'typeLabel'         => $typeLabel,
            'phoneDigits'       => $phoneDigits,
            'waUrl'             => $waUrl,
            'fontFamily'        => $fontFamily,
            'totalPaid'         => $totalPaid,
            'paymentStatus'     => $paymentStatus,
            'watermarkLabels'   => $watermarkLabels,
            'watermarkText'     => $watermarkText,
            'watermarkColor'    => $watermarkColor,
            'paidAmountDisplay' => $paidAmountDisplay,
        ]);
    }
}