<?php
// Tenant Receipt View (Money Receipt, printable + PDF) - data: receipt,
// isTenantPortal, receiptNo, totalAmount, amountWords, cur, monthLabel,
// copyType, copyLabel, copySuffix, monthNameBn, propertyType, receiptType,
// propertyLabel, propertyLabelEn, propertyLabelBn, typeLabel, feeRowLabel,
// holdingLabel, holdingLabelBn, formattedDate, receiptDetails, phoneDigits,
// waUrl, fontFamily, paymentStatus, watermarkLabels, watermarkText,
// watermarkColor, balanceDue, paidAmountDisplay, balanceDisplay, sigSrc
$currentLang = Lang::current();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang === 'bn' ? 'bn' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('money_receipt'); ?> - <?php echo e($receiptNo); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    <!-- html2pdf.js for client-side PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page { margin: 15.32mm; }
        body {
            background: #eef2f7;
            font-family: <?php echo $fontFamily; ?>;
            color: #333;
            font-size: 13px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .toolbar {
            max-width: 860px;
            margin: 20px auto 10px;
        }
        .receipt-container {
            max-width: 860px;
            margin: 0 auto 40px;
            background: #fff;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-radius: 8px;
            padding: 0;
            position: relative;
        }
        .border-frame {
            position: absolute;
            inset: 12px;
            border: 4px double #007c47;
            border-radius: 4px;
            pointer-events: none;
        }
        .content {
            position: relative;
            z-index: 10;
            padding: 20px 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
            position: relative;
        }
        .receipt-no {
            position: absolute;
            right: 0;
            top: 10px;
            text-align: right;
        }
        .receipt-no .label {
            color: #DC2626;
            font-weight: bold;
            font-size: 16px;
        }
        .title {
            font-size: 36px;
            font-weight: bold;
            color: #007c47;
            margin-bottom: 12px;
            margin-top: 30px;
        }
        .badge {
            display: inline-block;
            background: #007c47;
            color: #fff;
            padding: 4px 24px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: <?php echo $currentLang === 'bn' ? '0' : '1px'; ?>;
            font-family: <?php echo $fontFamily; ?>;
            margin-bottom: 20px;
        }
        .details {
            margin-top: 8px;
            font-size: 15px;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details td {
            padding: 4px 4px;
            vertical-align: baseline;
        }
        .label-cell {
            white-space: nowrap;
            font-size: 17px;
        }
        .details tr > td:first-child {
            width: 182px;
            min-width: 182px;
        }
        .value-cell {
            text-align: left;
            font-weight: 600;
            color: #007c47;
            border-bottom: 2px dotted #999;
            font-size: 17px;
        }
        .value-cell-sm {
            text-align: left;
            font-weight: 600;
            color: #007c47;
            border-bottom: 2px dotted #999;
            min-width: 80px;
            font-size: 17px;
        }
        .total-row td {
            font-size: 17px;
            padding-top: 6px;
        }
        .total-row .value-cell {
            font-size: 22px;
            font-weight: bold;
        }
        /* Prevent month/year wrapping in PDF */
        @media print {
            .details td.value-cell {
                white-space: nowrap;
            }
            /* Ensure second table value cells don't wrap */
            .details table td.value-cell {
                white-space: nowrap;
            }
        }
        .amount-box {
            background: #f7f7f7;
            border: 1px dashed #aaa;
            padding: .75rem 1rem;
            border-radius: 6px;
            margin-top: 14px;
        }
        .signature {
            margin-top: 32px;
            text-align: right;
        }
        .sig-line {
            display: inline-block;
            border-top: 2px dashed #333;
            padding-top: 6px;
            font-size: 14px;
            color: #555;
        }
        .sig-img {
            text-align: right;
        }
        .sig-img img {
            display: inline-block;
            vertical-align: bottom;
        }
        /* Ensure borders render in PDF */
        .border-frame {
            border: 4px double #007c47 !important;
        }
        /* .receipt-container {
            border: 1px solid #e0e0e0 !important;
        } */
        /* PDF specific: ensure centered and proper size */
        @media print {
            .receipt-container {
                max-width: 100%;
                margin: 0 auto;
                box-shadow: none;
            }
        }
        @media print {
            body { background: #fff; }
            .toolbar, .no-print { display: none !important; }
            .receipt-container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
            }
        }

        /* Mobile (phones) */
        @media (max-width: 576px) {
            body { background: #fff; }
            .toolbar {
                margin: 10px 8px 6px;
            }
            .toolbar .toolbar-actions {
                width: 100%;
            }
            .btn-tool {
                min-width: 0;
            }
            .receipt-container {
                margin: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
            .border-frame {
                inset: 6px;
                border-width: 2px;
            }
            .content {
                padding: 14px 16px;
            }
            .header {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .receipt-no {
                position: static;
                text-align: center;
                margin-bottom: 8px;
            }
            .receipt-no > div {
                display: inline-block;
                margin: 0 6px;
            }
            .title {
                font-size: 24px;
                margin-top: 6px;
                margin-bottom: 10px;
            }
            .badge {
                font-size: 11px;
                padding: 3px 14px;
                margin-bottom: 10px;
            }
            .label-cell,
            .value-cell,
            .value-cell-sm {
                font-size: 14px;
            }
            .value-cell-sm {
                min-width: 60px;
            }
            .total-row .value-cell {
                font-size: 16px !important;
            }
            .amount-box span.fw-semibold {
                font-size: 15px !important;
            }
        }

        .btn-tool { min-width: 110px; }

        /* Payment status watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 105px;
            font-weight: 900;
            white-space: nowrap;
            pointer-events: none;
            z-index: 5;
            letter-spacing: 4px;
            opacity: 1;
            color: <?php echo $watermarkColor; ?>;
            text-transform: uppercase;
            user-select: none;
        }
        .payment-status-bar {
            text-align: center;
            margin-bottom: 10px;
        }
        .status-badge-paid    { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
        .status-badge-partial { background:#fef3c7; color:#b45309; border:1px solid #fcd34d; }
        .status-badge-unpaid  { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
        .status-badge {
            display:inline-block;
            padding:3px 18px;
            border-radius:20px;
            font-size:13px;
            font-weight:600;
        }
    </style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
<body>

    <!-- Toolbar (hidden when printing) -->
    <div class="toolbar no-print flex flex-wrap items-center justify-between gap-2">
        <a href="<?php echo url('tenant-payments'); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left mr-1 inline-block"></i><?php echo t('back'); ?></a>
        <div class="toolbar-actions w-full md:w-auto flex flex-wrap items-center gap-2">
            <?php if ($currentLang === 'bn'): ?>
                <a class="btn btn-outline-dark btn-sm btn-tool" href="<?php echo url('tenant-receipt'); ?>?id=<?php echo (int)$receipt['id']; ?>&lang=en"><i class="bi bi-translate mr-1"></i>English</a>
            <?php else: ?>
                <a class="btn btn-outline-dark btn-sm btn-tool" href="<?php echo url('tenant-receipt'); ?>?id=<?php echo (int)$receipt['id']; ?>&lang=bn"><i class="bi bi-translate mr-1"></i>বাংলা</a>
            <?php endif; ?>
            <?php if ($isTenantPortal): ?>
                <!-- Tenant sees only tenant copy (forced) -->
            <?php else: ?>
                <?php if ($copyType === 'original'): ?>
                    <a class="btn btn-outline-warning btn-sm btn-tool" href="<?php echo url('tenant-receipt'); ?>?id=<?php echo (int)$receipt['id']; ?>&copy=tenant"><i class="bi bi-files mr-1"></i><?php echo $currentLang === 'bn' ? 'ভাড়াটিয়া কপি' : 'Tenant Copy'; ?></a>
                <?php else: ?>
                    <a class="btn btn-outline-secondary btn-sm btn-tool" href="<?php echo url('tenant-receipt'); ?>?id=<?php echo (int)$receipt['id']; ?>"><i class="bi bi-file-earmark mr-1"></i><?php echo $currentLang === 'bn' ? 'মূল কপি' : 'Original'; ?></a>
                <?php endif; ?>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm btn-tool" onClick="window.print()"><i class="bi bi-printer mr-1"></i><?php echo t('print'); ?></button>
            <button class="btn btn-outline-secondary btn-sm btn-tool" onClick="downloadPDF()"><i class="bi bi-file-earmark-pdf mr-1"></i><?php echo t('download_pdf'); ?></button>
            <?php if (!$isTenantPortal && $receipt['tenant_phone']): ?>
                <a class="btn btn-success btn-sm btn-tool" href="<?php echo e($waUrl); ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp mr-1"></i><?php echo t('send_whatsapp'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receipt -->
    <div class="receipt-container">
        <div class="border-frame"></div>
        <!-- Payment status watermark -->
        <div class="watermark"><?php echo $watermarkText; ?></div>
        <div class="content">
            <div class="header">
                <h1 class="title"><?php echo t('money_receipt'); ?></h1>
                <div class="badge"><?php echo $currentLang === 'bn' ? $typeLabel : strtoupper($typeLabel); ?></div>

                <div class="receipt-no">
                    <div class="label" style="<?php echo $copyType === 'tenant' ? 'color:#b45309;' : ''; ?>"><?php echo e($copyLabel); ?></div>
                    <div style="font-size:14px;"><?php echo t('receipt_no_short'); ?>: <?php echo e(bnNumeral($receiptNo)); ?></div>
                    <div style="font-size:14px;"><?php echo t('date'); ?>: <?php echo e(bnNumeral($formattedDate)); ?></div>
                    <div style="font-size:14px; font-weight:700;
                        color:<?php echo $paymentStatus === 'paid' ? '#15803d' : ($paymentStatus === 'partial' ? '#b45309' : '#b91c1c'); ?>;">
                        <?php echo $watermarkText; ?>
                    </div>
                </div>
            </div>

            <div class="details">
                <table>
                    <colgroup>
                        <col style="width:20%;">
                        <col style="width:80%;">
                    </colgroup>
                    <?php if ($currentLang === 'bn'): ?>
                    <tr>
                        <td class="label-cell"><?php echo $propertyLabelBn; ?> নাম :</td>
                        <td class="value-cell"><?php echo e(localizeText($receipt['building_name'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell"><?php echo $holdingLabelBn; ?> :</td>
                        <td class="value-cell"><?php echo e(bnFlatCode($receipt['flat_no'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ঠিকানা :</td>
                        <td class="value-cell"><?php echo e(localizeText($receipt['building_address'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ভাড়াটিয়ার নাম :</td>
                        <td class="value-cell"><?php echo e(localizeName($receipt['tenant_name'])); ?></td>
                    </tr>
                    <?php if ($receipt['tenant_phone']): ?>
                    <tr>
                        <td class="label-cell">ফোন :</td>
                        <td class="value-cell"><?php echo e(bnNumeral(enDigits($receipt['tenant_phone']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php else: ?>
                    <tr>
                        <td class="label-cell">Property / <?php echo e($propertyLabelEn); ?> Name :</td>
                        <td class="value-cell"><?php echo e(localizeText($receipt['building_name'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell"><?php echo e($holdingLabel); ?> :</td>
                        <td class="value-cell"><?php echo e(bnFlatCode($receipt['flat_no'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Address :</td>
                        <td class="value-cell"><?php echo e(localizeText($receipt['building_address'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Tenant Name :</td>
                        <td class="value-cell"><?php echo e(localizeName($receipt['tenant_name'])); ?></td>
                    </tr>
                    <?php if ($receipt['tenant_phone']): ?>
                    <tr>
                        <td class="label-cell">Phone :</td>
                        <td class="value-cell"><?php echo e(bnNumeral(enDigits($receipt['tenant_phone']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>

                <table>
                    <colgroup>
                        <col style="width:1%;">
                        <col style="width:23%;">
                        <col style="width:1%;">
                        <col style="width:23%;">
                    </colgroup>
                    <?php if ($currentLang === 'bn'): ?>
                    <tr>
                        <td class="label-cell">সাল :</td>
                        <td class="value-cell"><?php echo bnNumeral($receipt['year']); ?></td>
                        <td class="label-cell" style="padding-left:16px;">মাস :</td>
                        <td class="value-cell"><?php echo e($monthNameBn); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell"><?php echo $feeRowLabel; ?></td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format((float)$receipt['amount'], 2)); ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <?php if ($propertyType !== 'shop' && $receiptType === 'rent'): ?>
                    <tr>
                        <td class="label-cell">পার্কিং বিল :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format((float)$receipt['parking_amount'], 2)); ?></td>
                        <td class="label-cell" style="padding-left:16px;">গ্যাস বিল :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format((float)$receipt['gas_amount'], 2)); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">পানি বিল :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format((float)$receipt['water_fee'], 2)); ?></td>
                        <td class="label-cell" style="padding-left:16px;">বর্জ্য ব্যবস্থাপনা বিল :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format((float)$receipt['waste_fee'], 2)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float)$receipt['arrears'] > 0): ?>
                    <tr>
                        <td class="label-cell"><?php echo t('arrears'); ?> :</td>
                        <td class="value-cell" colspan="3"><?php echo $cur . ' ' . bnNumeral(number_format((float)$receipt['arrears'], 2)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="label-cell">পেমেন্ট পদ্ধতি :</td>
                        <td class="value-cell" colspan="3"><?php echo t($receipt['payment_method'], ucfirst($receipt['payment_method'])); ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td class="label-cell">Year :</td>
                        <td class="value-cell"><?php echo e($receipt['year']); ?></td>
                        <td class="label-cell" style="padding-left:16px;">Month :</td>
                        <td class="value-cell"><?php echo e($monthLabel); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell"><?php echo $feeRowLabel; ?></td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format((float)$receipt['amount'], 2); ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <?php if ($propertyType !== 'shop' && $receiptType === 'rent'): ?>
                    <tr>
                        <td class="label-cell">Parking Bill :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format((float)$receipt['parking_amount'], 2); ?></td>
                        <td class="label-cell" style="padding-left:16px;">Gas Bill :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format((float)$receipt['gas_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Water Bill :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format((float)$receipt['water_fee'], 2); ?></td>
                        <td class="label-cell" style="padding-left:16px;font-size:15px;">Waste Management Bill :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format((float)$receipt['waste_fee'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float)$receipt['arrears'] > 0): ?>
                    <tr>
                        <td class="label-cell"><?php echo t('arrears'); ?> :</td>
                        <td class="value-cell" colspan="3"><?php echo $cur . ' ' . number_format((float)$receipt['arrears'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="label-cell">Payment Method :</td>
                        <td class="value-cell" colspan="3"><?php echo t($receipt['payment_method'], ucfirst($receipt['payment_method'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <table style="margin-top:8px; width:100%;">
                    <?php if ($currentLang === 'bn'): ?>
                    <tr class="total-row">
                        <td style="font-weight:bold; width:24%; white-space:nowrap;">মোট পরিমাণ :</td>
                        <td class="value-cell" style="font-size:22px; font-weight:bold; text-align:left;"><?php echo $cur . ' ' . bnNumeral(number_format($totalAmount, 2)); ?> /- টাকা মাত্র</td>
                    </tr>
                    <?php if ($paymentStatus === 'partial'): ?>
                    <tr>
                        <td style="font-weight:600; color:#b45309;">পরিশোধিত পরিমাণ :</td>
                        <td style="font-weight:600; color:#b45309; font-size:17px;"><?php echo $cur . ' ' . $paidAmountDisplay; ?> টাকা</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;">বকেয়া পরিমাণ :</td>
                        <td style="font-weight:600; color:#dc2626; font-size:17px;"><?php echo $cur . ' ' . $balanceDisplay; ?> টাকা</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;" colspan="2">সার্ভিস চার্জ : অপরিশোধিত (<?php echo e(monthName((int)$receipt['month'], 'bn') . ' ' . bnNumeral($receipt['year'])); ?>)</td>
                    </tr>
                    <?php endif; ?>
                    <?php else: ?>
                    <tr class="total-row">
                        <td style="font-weight:bold; width:24%; white-space:nowrap;">Total Amount :</td>
                        <td class="value-cell" style="font-size:22px; font-weight:bold; text-align:left;"><?php echo $cur . ' ' . number_format($totalAmount, 2); ?> /- Taka Only</td>
                    </tr>
                    <?php if ($paymentStatus === 'partial'): ?>
                    <tr>
                        <td style="font-weight:600; color:#b45309;">Paid Amount :</td>
                        <td style="font-weight:600; color:#b45309; font-size:17px;"><?php echo $cur . ' ' . $paidAmountDisplay; ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;">Balance Due :</td>
                        <td style="font-weight:600; color:#dc2626; font-size:17px;"><?php echo $cur . ' ' . $balanceDisplay; ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;" colspan="2">Service Charge: Unpaid (<?php echo e(monthName((int)$receipt['month']) . ' ' . $receipt['year']); ?>)</td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($receipt['note']): ?>
            <div class="amount-box" style="background:transparent; border:none; padding:8px 0;">
                <strong><?php echo t('note'); ?>:</strong>
                <span><?php echo e(localizeText($receipt['note'])); ?></span>
            </div>
            <?php endif; ?>

            <div class="amount-box">
                <strong><?php if ($currentLang === 'bn'): ?>কথায়:<?php else: ?>Amount in Words:<?php endif; ?></strong>
                <span class="fw-semibold" style="font-size:20px;"><?php echo e($amountWords); ?></span>
                <small class="text-uppercase text-muted"><?php if ($currentLang === 'bn'): ?>টাকা মাত্র<?php else: ?>Taka Only<?php endif; ?></small>
            </div>

            <div class="signature">
                <div class="sig-img mb-2">
                    <img src="<?php echo e($sigSrc); ?>" alt="Owner Signature" style="max-height:60px; width:auto;">
                </div>
                <div class="sig-line"><?php if ($currentLang === 'bn'): ?>মালিকের স্বাক্ষর<?php else: ?>Owner's Signature<?php endif; ?></div>
                <div class="mt-2 no-print">
                    <?php if (!$isTenantPortal): ?>
                    <form method="POST" enctype="multipart/form-data" class="inline-block">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="upload_signature">
                        <input type="hidden" name="payment_id" value="<?php echo (int)$receipt['id']; ?>">
                        <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer;">
                            <i class="bi bi-upload mr-1"></i><?php if ($currentLang === 'bn'): ?>স্বাক্ষর আপলোড<?php else: ?>Upload Signature<?php endif; ?>
                            <input type="file" name="signature" accept="image/*" class="hidden" onChange="this.form.submit();">
                        </label>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
</div>
    </div>

    <script>
    function downloadPDF() {
        const element = document.querySelector('.receipt-container');
        
        // Hide toolbar (no-print elements)
        const toolbar = document.querySelector('.toolbar');
        if (toolbar) toolbar.style.display = 'none';
        
        // Hide upload signature form for PDF
        const uploadSig = document.querySelector('.signature form');
        if (uploadSig) uploadSig.style.display = 'none';
        
        // Ensure borders are visible
        const borderFrame = document.querySelector('.border-frame');
        if (borderFrame) borderFrame.style.border = '4px double #007c47';
        
        const container = document.querySelector('.receipt-container');
        if (container) {
            container.style.border = 'none';
            container.style.boxShadow = 'none';
        }
        
        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     'Receipt-<?php echo e($receiptNo); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true, 
                logging: false, 
                backgroundColor: '#fff',
                fontFamily: <?php echo json_encode($fontFamily); ?>
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
        };
        
        // Hide watermark for PDF
        const watermark = document.querySelector('.watermark');
        if (watermark) watermark.style.display = 'none';
        
        // Check if html2pdf is available
        if (typeof html2pdf === 'undefined') {
            console.error('html2pdf library not loaded');
            alert('PDF generation library not loaded. Please refresh the page.');
            if (watermark) watermark.style.display = '';
            if (uploadSig) uploadSig.style.display = '';
            if (toolbar) toolbar.style.display = '';
            return;
        }
        
        html2pdf().set(opt).from(element).save().then(function() {
            if (watermark) watermark.style.display = '';
            if (uploadSig) uploadSig.style.display = '';
            if (toolbar) toolbar.style.display = '';
        }).catch(function(err) {
            console.error('PDF generation failed:', err);
            alert('PDF generation failed. Please try again.');
            if (watermark) watermark.style.display = '';
            if (uploadSig) uploadSig.style.display = '';
            if (toolbar) toolbar.style.display = '';
        });
    }
    // Expose globally
    window.downloadPDF = downloadPDF;
    </script>

</body>
</html>