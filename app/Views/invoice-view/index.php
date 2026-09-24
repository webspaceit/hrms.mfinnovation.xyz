<?php
// Invoice (Demand Note) view - standalone printable + sendable document.
// Data: isTenantPortal, inv, no, cur, totalDue, totalPaid, amountWords,
// monthLabel, monthNameBn, propertyType, propertyLabel, propertyLabelEn,
// propertyLabelBn, issuedDate, dueDate, typeLabel, waUrl, fontFamily,
// paymentStatus, watermarkText, watermarkColor, paidAmountDisplay, currentLang
?><!DOCTYPE html>
<html lang="<?php echo $currentLang === 'bn' ? 'bn' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('due_invoice'); ?> - <?php echo e($no); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
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
            /* border: 1px solid #e0e0e0; */
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
            width: 196px;
            min-width: 196px;
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
            /* 4-column shop details table: let labels share the row nicely */
            .details table.fourcol td:first-child {
                width: auto;
                min-width: 74px;
            }
            .details table.fourcol .label-cell {
                white-space: normal;
                font-size: 13px;
            }
            .details table.fourcol .value-cell {
                font-size: 14px;
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
            font-size: 96px;
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
</head>
<body>

    <!-- Toolbar (hidden when printing) -->
    <div class="toolbar no-print flex flex-wrap items-center justify-between gap-2">
        <a href="<?php echo url($isTenantPortal ? 'tenant-dues' : 'invoices'); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left mr-1 inline-block"></i><?php echo t('back'); ?></a>
        <div class="toolbar-actions w-full md:w-auto flex flex-wrap items-center gap-2">
            <?php if ($currentLang === 'bn'): ?>
                <a class="btn btn-outline-dark btn-sm btn-tool" href="<?php echo url('invoice-view'); ?>?id=<?php echo (int)$inv['id']; ?>&lang=en<?php echo $isTenantPortal ? '&tenant_portal=1' : ''; ?>"><i class="bi bi-translate mr-1"></i>English</a>
            <?php else: ?>
                <a class="btn btn-outline-dark btn-sm btn-tool" href="<?php echo url('invoice-view'); ?>?id=<?php echo (int)$inv['id']; ?>&lang=bn<?php echo $isTenantPortal ? '&tenant_portal=1' : ''; ?>"><i class="bi bi-translate mr-1"></i>বাংলা</a>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm btn-tool" onclick="window.print()"><i class="bi bi-printer mr-1"></i><?php echo t('print'); ?></button>
            <?php if (!$isTenantPortal): ?>
            <?php if ($inv['tenant_phone']): ?>
                <a class="btn btn-success btn-sm btn-tool" href="<?php echo e($waUrl); ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp mr-1"></i><?php echo t('send_whatsapp'); ?>
                </a>
            <?php endif; ?>
            <?php if ($inv['tenant_email']): ?>
                <button class="btn btn-info btn-sm btn-tool text-white" onclick="sendInvoice(<?php echo (int)$inv['id']; ?>, 'email')">
                    <i class="bi bi-envelope mr-1"></i><?php echo t('send_email'); ?>
                </button>
            <?php endif; ?>
            <?php if ($inv['tenant_phone']): ?>
                <button class="btn btn-warning btn-sm btn-tool" onclick="sendInvoice(<?php echo (int)$inv['id']; ?>, 'sms')">
                    <i class="bi bi-chat-dots mr-1"></i><?php echo t('send_sms'); ?>
                </button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice / Demand Note -->
    <div class="receipt-container">
        <div class="border-frame"></div>
        <!-- Payment status watermark -->
        <div class="watermark"><?php echo $watermarkText; ?></div>
        <div class="content">
            <!-- Status bar (screen only) -->
            <div class="payment-status-bar no-print">
                <span class="status-badge status-badge-<?php echo $paymentStatus; ?>">
                    <?php if ($currentLang === 'bn'): ?>
                        <?php if ($paymentStatus === 'paid'): ?>পরিশোধিত
                        <?php elseif ($paymentStatus === 'partial'): ?>আংশিক পরিশোধিত — পরিশোধিত: ৳<?php echo $paidAmountDisplay; ?>
                        <?php else: ?>অপরিশোধিত<?php endif; ?>
                    <?php else: ?>
                        <?php if ($paymentStatus === 'paid'): ?>Paid in Full
                        <?php elseif ($paymentStatus === 'partial'): ?>Partially Paid — Paid: ৳<?php echo $paidAmountDisplay; ?>
                        <?php else: ?>Unpaid<?php endif; ?>
                    <?php endif; ?>
                </span>
<?php if ($paymentStatus !== 'paid' && !$isTenantPortal): ?>
                    <button class="btn btn-success btn-sm ml-2" onclick="markInvoicePaid(<?php echo (int)$inv['id']; ?>">
                        <i class="bi bi-check2-circle mr-1"></i> Mark as Paid
                    </button>
                <?php endif; ?>
            </div>
            <div class="header">
                <h1 class="title"><?php echo t('due_invoice'); ?></h1>
                <div class="badge"><?php echo $currentLang === 'bn' ? $typeLabel : strtoupper($typeLabel); ?></div>

                <div class="receipt-no">
                    <div class="label"><?php echo t('invoice_no_short'); ?></div>
                    <div style="font-size:14px;"><?php echo e(bnNumeral($no)); ?></div>
                    <div style="font-size:14px;"><?php echo t('date'); ?>: <?php echo e(bnNumeral($issuedDate)); ?></div>
                    <div style="font-size:14px; font-weight:700;
                        color:<?php echo $paymentStatus === 'paid' ? '#15803d' : ($paymentStatus === 'partial' ? '#b45309' : '#b91c1c'); ?>;">
                        <?php echo $watermarkText; ?>
                    </div>
                </div>
            </div>

            <div class="details">
                <?php if ($propertyType === 'shop'): ?>
                <table class="fourcol">
                    <colgroup>
                        <col style="width:16%;">
                        <col style="width:34%;">
                        <col style="width:16%;">
                        <col style="width:34%;">
                    </colgroup>
                    <?php if ($currentLang === 'bn'): ?>
                    <tr>
                        <td class="label-cell">দোকানের নাম :</td>
                        <td class="value-cell" style="white-space:normal;"><?php echo e(localizeText($shopName !== '' ? $shopName : '—')); ?></td>
                        <td class="label-cell" style="padding-left:16px;">দোকান নং :</td>
                        <td class="value-cell"><?php echo e(bnFlatCode($inv['flat_no'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ভবন :</td>
                        <td class="value-cell"><?php echo e(localizeText($inv['building_name'])); ?></td>
                        <td class="label-cell" style="padding-left:16px;">হোল্ডিং নং :</td>
                        <td class="value-cell"><?php echo e($holdingNo !== '' ? bnFlatCode($holdingNo) : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ঠিকানা :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeText($inv['building_address'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ভাড়াটিয়ার নাম :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeName($inv['tenant_name'])); ?></td>
                    </tr>
                    <?php if ($inv['tenant_phone']): ?>
                    <tr>
                        <td class="label-cell">ফোন :</td>
                        <td class="value-cell" colspan="3"><?php echo e(bnNumeral(enDigits($inv['tenant_phone']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($inv['tenant_email']): ?>
                    <tr>
                        <td class="label-cell">ইমেইল :</td>
                        <td class="value-cell" colspan="3"><?php echo e($inv['tenant_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php else: ?>
                    <tr>
                        <td class="label-cell">Shop Name :</td>
                        <td class="value-cell" style="white-space:normal;"><?php echo e(localizeText($shopName !== '' ? $shopName : '—')); ?></td>
                        <td class="label-cell" style="padding-left:16px;">Shop No :</td>
                        <td class="value-cell"><?php echo e(bnFlatCode($inv['flat_no'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Building :</td>
                        <td class="value-cell"><?php echo e(localizeText($inv['building_name'])); ?></td>
                        <td class="label-cell" style="padding-left:16px;">Holding No :</td>
                        <td class="value-cell"><?php echo e($holdingNo !== '' ? bnFlatCode($holdingNo) : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Address :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeText($inv['building_address'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Tenant Name :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeName($inv['tenant_name'])); ?></td>
                    </tr>
                    <?php if ($inv['tenant_phone']): ?>
                    <tr>
                        <td class="label-cell">Phone :</td>
                        <td class="value-cell" colspan="3"><?php echo e(bnNumeral(enDigits($inv['tenant_phone']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($inv['tenant_email']): ?>
                    <tr>
                        <td class="label-cell">Email :</td>
                        <td class="value-cell" colspan="3"><?php echo e($inv['tenant_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>
                <?php else: ?>
                <table class="fourcol">
                    <colgroup>
                        <col style="width:16%;">
                        <col style="width:34%;">
                        <col style="width:16%;">
                        <col style="width:34%;">
                    </colgroup>
                    <?php if ($currentLang === 'bn'): ?>
                    <tr>
                        <td class="label-cell"><?php echo $propertyLabelBn; ?> নাম :</td>
                        <td class="value-cell"><?php echo e(localizeText($inv['building_name'])); ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo $propertyType === 'shop' ? 'দোকান নং' : 'ফ্ল্যাট নং'; ?> :</td>
                        <td class="value-cell"><?php echo e(bnFlatCode($inv['flat_no'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ঠিকানা :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeText($inv['building_address'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">ভাড়াটিয়ার নাম :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeName($inv['tenant_name'])); ?></td>
                    </tr>
                    <?php if ($inv['tenant_phone']): ?>
                    <tr>
                        <td class="label-cell">ফোন :</td>
                        <td class="value-cell" colspan="3"><?php echo e(bnNumeral(enDigits($inv['tenant_phone']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($inv['tenant_email']): ?>
                    <tr>
                        <td class="label-cell">ইমেইল :</td>
                        <td class="value-cell" colspan="3"><?php echo e($inv['tenant_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php else: ?>
                    <tr>
                        <td class="label-cell">Property / <?php echo e($propertyLabelEn); ?> Name :</td>
                        <td class="value-cell"><?php echo e(localizeText($inv['building_name'])); ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo e($propertyLabel); ?> :</td>
                        <td class="value-cell"><?php echo e(bnFlatCode($inv['flat_no'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Address :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeText($inv['building_address'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Tenant Name :</td>
                        <td class="value-cell" colspan="3"><?php echo e(localizeName($inv['tenant_name'])); ?></td>
                    </tr>
                    <?php if ($inv['tenant_phone']): ?>
                    <tr>
                        <td class="label-cell">Phone :</td>
                        <td class="value-cell" colspan="3"><?php echo e(bnNumeral(enDigits($inv['tenant_phone']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($inv['tenant_email']): ?>
                    <tr>
                        <td class="label-cell">Email :</td>
                        <td class="value-cell" colspan="3"><?php echo e($inv['tenant_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>
                <?php endif; ?>

                <table style="margin-top:12px; width:100%;">
                    <colgroup>
                        <col style="width:24%;">
                        <col style="width:22%;">
                        <col style="width:20%;">
                        <col style="width:34%;">
                    </colgroup>
                    <?php if ($currentLang === 'bn'): ?>
                    <tr>
                        <td class="label-cell">সাল</td>
                        <td class="value-cell"><?php echo bnNumeral($inv['year']); ?></td>
                        <td class="label-cell" style="padding-left:16px;">মাস :</td>
                        <td class="value-cell"><?php echo e($monthNameBn); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">মাসিক ভাড়া :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format((float)$inv['rent_amount'], 2)); ?></td>
                        <?php if ((float)$inv['utility_fee'] > 0): ?>
                        <td class="label-cell no-print" style="padding-left:16px;">ইউটিলিটি ফি :</td>
                        <td class="value-cell no-print"><?php echo $cur . ' ' . bnNumeral(number_format((float)$inv['utility_fee'], 2)); ?><div class="small" style="font-size:11px;color:#6b7280;">(মোটে অন্তর্ভুক্ত নয়)</div></td>
                        <?php else: ?>
                        <td></td>
                        <td></td>
                        <?php endif; ?>
                    </tr>
                    <?php if ((float)$inv['parking_amount'] > 0 || (float)$inv['gas_amount'] > 0): ?>
                    <tr>
                        <td class="label-cell"><?php echo (float)$inv['parking_amount'] > 0 ? 'পার্কিং বিল :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['parking_amount'] > 0 ? $cur . ' ' . bnNumeral(number_format((float)$inv['parking_amount'], 2)) : ''; ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo (float)$inv['gas_amount'] > 0 ? 'গ্যাস বিল :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['gas_amount'] > 0 ? $cur . ' ' . bnNumeral(number_format((float)$inv['gas_amount'], 2)) : ''; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float)$inv['water_fee'] > 0 || (float)$inv['waste_fee'] > 0): ?>
                    <tr>
                        <td class="label-cell"><?php echo (float)$inv['waste_fee'] > 0 ? 'বর্জ্য ব্যবস্থাপনা বিল :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['waste_fee'] > 0 ? $cur . ' ' . bnNumeral(number_format((float)$inv['waste_fee'], 2)) : ''; ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo (float)$inv['water_fee'] > 0 ? 'পানি বিল :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['water_fee'] > 0 ? $cur . ' ' . bnNumeral(number_format((float)$inv['water_fee'], 2)) : ''; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float)$inv['arrears'] > 0): ?>
                    <tr>
                        <td class="label-cell">বকেয়া (<?php echo bnNumeral($inv['arrears_months']); ?> মাস) :</td>
                        <td class="value-cell" colspan="3"><?php echo $cur . ' ' . bnNumeral(number_format((float)$inv['arrears'], 2)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="label-cell"><?php echo t('due_date'); ?> :</td>
                        <td class="value-cell"><?php echo e(bnNumeral($dueDate)); ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo t('total_due'); ?> :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . bnNumeral(number_format($totalDue, 2)); ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td class="label-cell">Year</td>
                        <td class="value-cell"><?php echo e($inv['year']); ?></td>
                        <td class="label-cell" style="padding-left:16px;">Month :</td>
                        <td class="value-cell"><?php echo e($monthLabel); ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Monthly Rent :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format((float)$inv['rent_amount'], 2); ?></td>
                        <?php if ((float)$inv['utility_fee'] > 0): ?>
                        <td class="label-cell no-print" style="padding-left:16px;">Utility Fee :</td>
                        <td class="value-cell no-print"><?php echo $cur . ' ' . number_format((float)$inv['utility_fee'], 2); ?><div class="small" style="font-size:11px;color:#6b7280;">(not included in total)</div></td>
                        <?php else: ?>
                        <td></td>
                        <td></td>
                        <?php endif; ?>
                    </tr>
                    <?php if ((float)$inv['parking_amount'] > 0 || (float)$inv['gas_amount'] > 0): ?>
                    <tr>
                        <td class="label-cell"><?php echo (float)$inv['parking_amount'] > 0 ? 'Parking Bill :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['parking_amount'] > 0 ? $cur . ' ' . number_format((float)$inv['parking_amount'], 2) : ''; ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo (float)$inv['gas_amount'] > 0 ? 'Gas Bill :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['gas_amount'] > 0 ? $cur . ' ' . number_format((float)$inv['gas_amount'], 2) : ''; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float)$inv['water_fee'] > 0 || (float)$inv['waste_fee'] > 0): ?>
                    <tr>
                        <td class="label-cell"><?php echo (float)$inv['waste_fee'] > 0 ? 'Waste Management Bill :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['waste_fee'] > 0 ? $cur . ' ' . number_format((float)$inv['waste_fee'], 2) : ''; ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo (float)$inv['water_fee'] > 0 ? 'Water Bill :' : ''; ?></td>
                        <td class="value-cell"><?php echo (float)$inv['water_fee'] > 0 ? $cur . ' ' . number_format((float)$inv['water_fee'], 2) : ''; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float)$inv['arrears'] > 0): ?>
                    <tr>
                        <td class="label-cell">Arrears (<?php echo (int)$inv['arrears_months']; ?> months) :</td>
                        <td class="value-cell" colspan="3"><?php echo $cur . ' ' . number_format((float)$inv['arrears'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="label-cell"><?php echo t('due_date'); ?> :</td>
                        <td class="value-cell"><?php echo e($dueDate); ?></td>
                        <td class="label-cell" style="padding-left:16px;"><?php echo t('total_due'); ?> :</td>
                        <td class="value-cell"><?php echo $cur . ' ' . number_format($totalDue, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <table style="margin-top:8px; width:100%;">
                    <?php if ($currentLang === 'bn'): ?>
                    <tr class="total-row">
                        <td style="font-weight:bold; width:24%; white-space:nowrap;">মোট পরিমাণ :</td>
                        <td class="value-cell" style="font-size:22px; font-weight:bold; text-align:left;"><?php echo $cur . ' ' . bnNumeral(number_format($totalDue, 2)); ?> /- টাকা মাত্র</td>
                    </tr>
                    <?php if ($paymentStatus === 'partial'): ?>
                    <tr>
                        <td style="font-weight:600; color:#b45309;">পরিশোধিত পরিমাণ :</td>
                        <td style="font-weight:600; color:#b45309; font-size:17px;"><?php echo $cur . ' ' . bnNumeral(number_format($totalPaid, 2)); ?> টাকা</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;">বকেয়া পরিমাণ :</td>
                        <td style="font-weight:600; color:#dc2626; font-size:17px;"><?php echo $cur . ' ' . bnNumeral(number_format($totalDue - $totalPaid, 2)); ?> টাকা</td>
                    </tr>
                    <?php elseif ($paymentStatus === 'unpaid'): ?>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;" colspan="2">সার্ভিস চার্জ : অপরিশোধিত (<?php echo e(monthName($inv['month'], 'bn') . ' ' . bnNumeral($inv['year'])); ?>)</td>
                    </tr>
                    <?php endif; ?>
                    <?php else: ?>
                    <tr class="total-row">
                        <td style="font-weight:bold; width:24%; white-space:nowrap;">Total Amount :</td>
                        <td class="value-cell" style="font-size:22px; font-weight:bold; text-align:left;"><?php echo $cur . ' ' . number_format($totalDue, 2); ?> /- Taka Only</td>
                    </tr>
                    <?php if ($paymentStatus === 'partial'): ?>
                    <tr>
                        <td style="font-weight:600; color:#b45309;">Paid Amount :</td>
                        <td style="font-weight:600; color:#b45309; font-size:17px;"><?php echo $cur . ' ' . number_format($totalPaid, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;">Balance Due :</td>
                        <td style="font-weight:600; color:#dc2626; font-size:17px;"><?php echo $cur . ' ' . number_format($totalDue - $totalPaid, 2); ?></td>
                    </tr>
                    <?php elseif ($paymentStatus === 'unpaid'): ?>
                    <tr>
                        <td style="font-weight:600; color:#dc2626;" colspan="2">Status: Unpaid (<?php echo e(monthName($inv['month']) . ' ' . $inv['year']); ?>)</td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($inv['note']): ?>
            <div class="amount-box" style="background:transparent; border:none; padding:8px 0;">
                <strong><?php echo t('note'); ?>:</strong>
                <span><?php echo e(localizeText($inv['note'])); ?></span>
            </div>
            <?php endif; ?>

            <div class="amount-box">
                <strong><?php if ($currentLang === 'bn'): ?>কথায়:<?php else: ?>Amount in Words:<?php endif; ?></strong>
                <span class="fw-semibold" style="font-size:20px;"><?php echo e($amountWords); ?></span>
                <small class="text-uppercase text-muted"><?php if ($currentLang === 'bn'): ?>টাকা মাত্র<?php else: ?>Taka Only<?php endif; ?></small>
            </div>

            <div class="signature">
                <div class="sig-img mb-2">
                    <img src="<?php echo e(BASE_URL . 'assets/images/signature.jpg'); ?>" alt="Owner Signature" style="max-height:60px; width:auto;">
                </div>
                <div class="sig-line"><?php if ($currentLang === 'bn'): ?>মালিকের স্বাক্ষর<?php else: ?>Owner's Signature<?php endif; ?></div>
            </div>
        </div>
    </div>

<script>
const CSRF_TOKEN = '<?php echo csrf_token(); ?>';

function sendInvoice(id, channel) {
    fetch('<?php echo BASE_URL; ?>ajax/invoice_send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: id, channel: channel, csrf_token: CSRF_TOKEN })
    })
    .then(res => res.json())
    .then(result => {
        alert(result.message || 'Error');
    })
    .catch(err => alert('Request failed'));
}

function markInvoicePaid(id) {
    if (!confirm('Mark this invoice as paid?')) return;
    fetch('<?php echo BASE_URL; ?>ajax/invoice_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&status=paid&csrf_token=' + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert(result.message || 'Marked as paid');
            setTimeout(function() { location.reload(); }, 600);
        } else {
            alert(result.message || 'Failed');
        }
    })
    .catch(function() { alert('Request failed'); });
}
</script>
</body>
</html>