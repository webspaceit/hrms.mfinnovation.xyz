<?php
// ============================================================
// Tenant Portal - Receipt View (printable Money Receipt, PDF
// download). Forces the tenant copy and tenant-only access; the
// tenant may only view receipts for its own payments. Former
// tenant_receipt.php (wrapper around receipt.php), now a
// controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantReceiptController extends Controller {

    public function index(): void {
        \Auth::requireTenantLogin();

        // Tenant portal context: always the tenant copy, no admin actions.
        $isTenantPortal = true;

        // Handle signature upload - admins only; the tenant portal is always
        // declined (mirrors receipt.php behaviour when tenant_portal is set).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_signature') {
            if ($isTenantPortal || !csrf_verify()) {
                \Session::setFlash('danger', t('access_denied'));
                redirect('tenant-payments');
            }
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $signatureFile = $_FILES['signature'] ?? null;

            if ($paymentId && $signatureFile && $signatureFile['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $signatureFile['tmp_name']);

                if (in_array($mime, $allowed)) {
                    $ext = pathinfo($signatureFile['name'], PATHINFO_EXTENSION) ?: 'png';
                    $filename = 'sig_' . $paymentId . '_' . time() . '.' . $ext;
                    $uploadDir = dirname(__DIR__, 2) . '/uploads/signatures/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $filepath = $uploadDir . $filename;
                    if (move_uploaded_file($signatureFile['tmp_name'], $filepath)) {
                        // Delete old signature if exists
                        $db = \Database::getInstance()->getConnection();
                        $stmt = $db->prepare("SELECT signature FROM payments WHERE id = :id");
                        $stmt->execute(['id' => $paymentId]);
                        $oldSig = $stmt->fetchColumn();
                        if ($oldSig && file_exists(dirname(__DIR__, 2) . '/' . $oldSig)) {
                            unlink(dirname(__DIR__, 2) . '/' . $oldSig);
                        }
                        // Save new signature path
                        $relativePath = 'uploads/signatures/' . $filename;
                        $stmt = $db->prepare("UPDATE payments SET signature = :sig WHERE id = :id");
                        $stmt->execute(['sig' => $relativePath, 'id' => $paymentId]);
                    }
                }
            }
            header('Location: ' . url('receipt') . '?id=' . $paymentId);
            exit;
        }

        // Optional per-request language override: tenant-receipt?id=5&lang=bn
        if (isset($_GET['lang'])) {
            \Lang::override($_GET['lang']);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            redirect('tenant-payments');
        }

        // Verify ownership before rendering (tenant may view only its own receipt)
        $paymentModel = new \Payment();
        $receipt = $paymentModel->getReceiptData($id);

        if (!$receipt || (int)$receipt['tenant_id'] !== \Auth::tenantId()) {
            \Session::setFlash('danger', t('access_denied'));
            redirect('tenant-payments');
        }

        $siteName = t('app_name');
        $receiptNo = 'RMS-' . str_pad($receipt['id'], 4, '0', STR_PAD_LEFT);
        $totalAmount = (float)$receipt['total_amount'];
        $amountWords = numberToWords((int)$totalAmount);
        $cur = CURRENCY;
        $unitLabel = $receipt['unit_type'] === 'shop' ? t('shop') : t('flat_no');
        $monthLabel = monthName($receipt['month']);
        $currentLang = \Lang::current();

        // Copy type: for the tenant portal, always force the tenant copy
        $copyType = 'tenant';
        $copyLabel = $currentLang === 'bn' ? 'ভাড়াটিয়া কপি' : 'TENANT COPY';
        $copySuffix = '&copy=tenant';

        // Bengali month name for the receipt
        $monthNameBn = monthName($receipt['month'], 'bn');

        // Receipt type comes from the payment entry: flat receipts can be flat rent
        // (default), parking bill or gas bill. Shop receipts are always shop rent.
        $propertyType = $receipt['unit_type'] === 'shop' ? 'shop' : 'apartment';
        $allowedTypes = ['rent', 'parking', 'gas', 'water', 'waste'];
        $receiptType = in_array($receipt['receipt_type'] ?? 'rent', $allowedTypes)
            ? $receipt['receipt_type']
            : 'rent';
        if ($propertyType === 'shop') {
            $receiptType = 'rent';
        }
        $propertyLabel = $propertyType === 'shop' ? t('shop') : t('flat_no');
        $propertyLabelEn = $propertyType === 'shop' ? 'Shop' : 'Flat';
        $propertyLabelBn = $propertyType === 'shop' ? 'দোকান' : 'অ্যাপার্টমেন্ট';

        // Receipt type label (matches the badge on the receipt)
        $typeLabel = $currentLang === 'bn'
            ? ($receiptType === 'parking' ? 'পার্কিং বিল' : ($receiptType === 'gas' ? 'গ্যাস বিল' : ($propertyType === 'shop' ? 'দোকান ভাড়া' : 'অ্যাপার্টমেন্ট ভাড়া')))
            : ($receiptType === 'parking' ? 'Parking Bill' : ($receiptType === 'gas' ? 'Gas Bill' : ($propertyType === 'shop' ? 'Shop Rent' : 'Flat Rent')));
        $feeRowLabel = $currentLang === 'bn'
            ? ($receiptType === 'parking' ? 'পার্কিং ফি :' : ($receiptType === 'gas' ? 'গ্যাস ফি :' : 'মাসিক ভাড়া :'))
            : ($receiptType === 'parking' ? 'Parking Fee :' : ($receiptType === 'gas' ? 'Gas Fee :' : 'Monthly Rent :'));

        // Holding label
        $holdingLabel = $propertyType === 'shop' ? t('shop_no') : t('flat_no');
        $holdingLabelBn = $propertyType === 'shop' ? 'দোকান নং' : 'ফ্ল্যাট নং';

        // Format receipt details for WhatsApp/Email (full receipt content)
        $formattedDate = date('d-m-Y', strtotime($receipt['payment_date']));
        $receiptDetails = "";
        $receiptDetails .= $copyLabel . "\n";
        $receiptDetails .= t('money_receipt') . " - $receiptNo\n";
        $receiptDetails .= str_repeat("=", 30) . "\n";
        $receiptDetails .= t('date') . ": $formattedDate\n";
        $receiptDetails .= t('unit_type') . ": $typeLabel\n";
        $receiptDetails .= str_repeat("-", 30) . "\n";
        if ($currentLang === 'bn') {
            $receiptDetails .= "$propertyLabelBn নাম : " . localizeText($receipt['building_name']) . "\n";
            $receiptDetails .= "$holdingLabelBn : " . bnFlatCode($receipt['flat_no']) . "\n";
            $receiptDetails .= "ঠিকানা : " . localizeText($receipt['building_address']) . "\n";
            $receiptDetails .= "ভাড়াটিয়ার নাম : " . localizeName($receipt['tenant_name']) . "\n";
        } else {
            $receiptDetails .= "$propertyLabelEn Name : " . localizeText($receipt['building_name']) . "\n";
            $receiptDetails .= "$holdingLabel : " . bnFlatCode($receipt['flat_no']) . "\n";
            $receiptDetails .= "Address : " . localizeText($receipt['building_address']) . "\n";
            $receiptDetails .= "Tenant Name : " . localizeName($receipt['tenant_name']) . "\n";
        }
        if ($receipt['tenant_phone']) {
            $receiptDetails .= "Phone : " . bnNumeral(enDigits($receipt['tenant_phone'])) . "\n";
        }
        $receiptDetails .= str_repeat("-", 30) . "\n";
        $receiptDetails .= ($currentLang === 'bn' ? 'সাল' : 'Year') . " : " . bnNumeral($receipt['year']) . "\n";
        $receiptDetails .= ($currentLang === 'bn' ? 'মাস' : 'Month') . " : " . ($currentLang === 'bn' ? monthName($receipt['month'], 'bn') : monthName($receipt['month'])) . "\n";
        $receiptDetails .= "$feeRowLabel " . $cur . " " . number_format((float)$receipt['amount'], 2) . "\n";
        if ($propertyType !== 'shop' && $receiptType === 'rent') {
            if ((float)$receipt['parking_amount'] > 0) {
                $receiptDetails .= ($currentLang === 'bn' ? 'পার্কিং বিল' : 'Parking Bill') . " : " . $cur . " " . number_format((float)$receipt['parking_amount'], 2) . "\n";
            }
            if ((float)$receipt['gas_amount'] > 0) {
                $receiptDetails .= ($currentLang === 'bn' ? 'গ্যাস বিল' : 'Gas Bill') . " : " . $cur . " " . number_format((float)$receipt['gas_amount'], 2) . "\n";
            }
            if ((float)$receipt['water_fee'] > 0) {
                $receiptDetails .= ($currentLang === 'bn' ? 'পানি বিল' : 'Water Bill') . " : " . $cur . " " . number_format((float)$receipt['water_fee'], 2) . "\n";
            }
            if ((float)$receipt['waste_fee'] > 0) {
                $receiptDetails .= ($currentLang === 'bn' ? 'বর্জ্য ব্যবস্থাপনা বিল' : 'Waste Management Bill') . " : " . $cur . " " . number_format((float)$receipt['waste_fee'], 2) . "\n";
            }
        }
        if ((float)$receipt['arrears'] > 0) {
            $receiptDetails .= t('arrears') . " : " . $cur . " " . number_format((float)$receipt['arrears'], 2) . "\n";
        }
        $receiptDetails .= str_repeat("-", 30) . "\n";
        $receiptDetails .= t('total') . " : " . $cur . " " . number_format($totalAmount, 2) . "\n";
        $receiptDetails .= t('taka_only') . " : " . $amountWords . "\n";
        $receiptDetails .= t('payment_method') . " : " . t($receipt['payment_method'], $receipt['payment_method']) . "\n";
        if ($receipt['note']) {
            $receiptDetails .= str_repeat("-", 30) . "\n";
            $receiptDetails .= t('note') . " : " . localizeText($receipt['note']) . "\n";
        }
        $receiptDetails .= str_repeat("=", 30) . "\n";
        $receiptDetails .= t('app_name');

        // WhatsApp link (BD mobile format)
        $phoneDigits = preg_replace('/[^0-9]/', '', enDigits($receipt['tenant_phone']));
        if (strlen($phoneDigits) === 11 && substr($phoneDigits, 0, 2) === '01') {
            $phoneDigits = '88' . $phoneDigits;
        }
        $waText = $receiptDetails;
        $waUrl = $phoneDigits ? 'https://wa.me/' . $phoneDigits . '?text=' . urlencode($waText) : '#';

        $fontFamily = $currentLang === 'bn'
            ? "'Noto Serif Bengali', 'SolaimanLipi', serif"
            : "'Helvetica Neue', Helvetica, Arial, sans-serif";

        // ---- Payment status watermark ----
        // Status is stored on the invoice (paid_amount / payment_status / paid_at),
        // maintained by Payments each time an installment is recorded. Mirroring the
        // reference app, installments accumulate until they cover the total due.
        $_db = \Database::getInstance()->getConnection();
        $_invStmt = $_db->prepare(
            "SELECT id, total_due, paid_amount, payment_status, payment_status_override FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1"
        );
        $_invStmt->execute(['l' => (int)$receipt['lease_id'], 'm' => (int)$receipt['month'], 'y' => (int)$receipt['year']]);
        $_invoice = $_invStmt->fetch(\PDO::FETCH_ASSOC);

        if ($_invoice) {
            $_invoiceDue   = (float)$_invoice['total_due'];
            $_totalPaid    = (float)$_invoice['paid_amount'];
            $paymentStatus = !empty($_invoice['payment_status_override'])
                ? $_invoice['payment_status_override']
                : ($_invoice['payment_status'] ?? 'unpaid');
        } else {
            // No invoice — receipt exists so it's fully paid
            $_invoiceDue   = 0;
            $_totalPaid    = (float)$receipt['total_amount'];
            $paymentStatus = 'paid';
        }

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
        $balanceDue = max(0, $_invoiceDue - $_totalPaid);
        $paidAmountDisplay = $currentLang === 'bn'
            ? bnNumeral(number_format($_totalPaid, 2))
            : number_format($_totalPaid, 2);
        $balanceDisplay = $currentLang === 'bn'
            ? bnNumeral(number_format($balanceDue, 2))
            : number_format($balanceDue, 2);

        // Owner signature: a payment-specific uploaded signature overrides the
        // permanent default (assets/images/signature.jpg).
        $ownerSig = url('assets/images/signature.jpg');
        $sigSrc = (!empty($receipt['signature']) && file_exists(dirname(__DIR__, 2) . '/' . $receipt['signature']))
            ? url($receipt['signature'])
            : $ownerSig;

        $this->view('tenant-receipt/index', [
            'receipt'           => $receipt,
            'isTenantPortal'    => $isTenantPortal,
            'currentLang'       => $currentLang,
            'receiptNo'         => $receiptNo,
            'totalAmount'       => $totalAmount,
            'amountWords'       => $amountWords,
            'cur'               => $cur,
            'monthLabel'        => $monthLabel,
            'copyType'          => $copyType,
            'copyLabel'         => $copyLabel,
            'copySuffix'        => $copySuffix,
            'monthNameBn'       => $monthNameBn,
            'propertyType'      => $propertyType,
            'receiptType'       => $receiptType,
            'propertyLabel'     => $propertyLabel,
            'propertyLabelEn'   => $propertyLabelEn,
            'propertyLabelBn'   => $propertyLabelBn,
            'typeLabel'         => $typeLabel,
            'feeRowLabel'       => $feeRowLabel,
            'holdingLabel'      => $holdingLabel,
            'holdingLabelBn'    => $holdingLabelBn,
            'formattedDate'     => $formattedDate,
            'receiptDetails'    => $receiptDetails,
            'phoneDigits'       => $phoneDigits,
            'waUrl'             => $waUrl,
            'fontFamily'        => $fontFamily,
            'paymentStatus'     => $paymentStatus,
            'watermarkLabels'   => $watermarkLabels,
            'watermarkText'     => $watermarkText,
            'watermarkColor'    => $watermarkColor,
            'balanceDue'        => $balanceDue,
            'paidAmountDisplay' => $paidAmountDisplay,
            'balanceDisplay'    => $balanceDisplay,
            'sigSrc'            => $sigSrc,
        ]);
    }
}