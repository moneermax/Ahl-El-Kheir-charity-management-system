<?php
// modules/accounting/voucher_print.php - Printable receipt / payment voucher (سند قبض / سند صرف)
// A self-contained A4 document: unified letterhead, amount in figures and words, signatures, void watermark.
// Add  &copies=2  to print the original and a copy on one A4 sheet.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_vouchers.php';
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

$projectPaymentId = (int)($_GET['project_payment_id'] ?? 0);
$viewerRole = Session::getUserRole();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
if ($projectPaymentId > 0) {
    if (!in_array($viewerRole, ['admin','financial_manager','general_manager','vice_general_manager','projects_manager','accountant','accountant_staff'], true)) {
        http_response_code(403); exit('Forbidden');
    }
} elseif (!ak_voucher_can('view', $viewerRole)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
ak_ensure_tables();
// A receipt is always printed bilingually (Arabic + English): skip the site's UI-language translation pass.
while (ob_get_level() > 0) { ob_end_clean(); }
// The "voucher issued" flash message is shown by this page itself, so do not leave it for the next page.
if (function_exists('get_flashes')) { get_flashes(); }

$isProjectPayment = $projectPaymentId > 0;
if ($isProjectPayment) {
    $v = dbFetchOne(
        "SELECT pe.*, p.project_code, p.name AS project_name,
                c.code AS cash_code, c.name_ar AS cash_name,
                COALESCE(pa.id, 0) AS other_id, COALESCE(pa.code, '5100') AS other_code,
                COALESCE(pa.name_ar, 'مصروفات البرامج والمساعدات') AS other_name,
                je.entry_code,
                du.full_name AS creator
         FROM project_payment_evidence pe
         INNER JOIN other_projects p ON p.id = pe.project_id
         INNER JOIN accounts c ON c.id = pe.source_account_id
         LEFT JOIN other_projects op ON op.id = pe.project_id
         LEFT JOIN accounts pa ON pa.id = op.expense_account_id
         LEFT JOIN journal_entries je ON je.id = pe.journal_entry_id
         LEFT JOIN users du ON du.id = pe.documented_by
         WHERE pe.id = ? AND pe.payment_method = 'cash' AND pe.status = 'documented'",
        [$projectPaymentId]
    );
    if (!$v || !akp_can_view_project((int)$v['project_id'])) {
        http_response_code(404); echo 'سند صرف المشروع غير موجود / Project payment voucher not found'; exit();
    }
    $v['voucher_type'] = 'payment';
    $v['voucher_no'] = 'PRJ-PV-' . (string)$v['project_code'] . '-' . (int)$v['id'];
    $v['voucher_date'] = $v['payment_date'];
    $v['party_name'] = 'مدير المشاريع — ' . (string)$v['project_name'];
    $v['description'] = 'صرف تمويل مشروع: ' . (string)$v['project_name'];
    $v['reference_number'] = (string)($v['reference_number'] ?: ('PROJECT-PAY-' . (int)$v['id']));
    $v['amount'] = (float)$v['amount'];
    $v['status'] = 'posted';
    $v['created_by'] = (int)($v['documented_by'] ?? 0);
} else {
    $v = dbFetchOne("SELECT v.*, c1.code cash_code, c1.name_ar cash_name, c2.code other_code, c2.name_ar other_name,
                            u.full_name creator, je.entry_code
                     FROM vouchers v
                     JOIN accounts c1 ON c1.id = v.cash_account_id
                     JOIN accounts c2 ON c2.id = v.other_account_id
                     LEFT JOIN users u ON u.id = v.created_by
                     LEFT JOIN journal_entries je ON je.id = v.entry_id
                     WHERE v.id = ?", [(int)($_GET['id'] ?? 0)]);
    if (!$v) { http_response_code(404); echo 'السند غير موجود / Voucher not found'; exit(); }
}

$void = null;
if ($v['status'] === 'voided') {
    $void = dbFetchOne("SELECT entry_code, created_at FROM journal_entries WHERE reference_type = 'voucher_void' AND reference_id = ? ORDER BY id DESC LIMIT 1", [(int)$v['id']]);
}
$org = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $org[$r['setting_key']] = $r['setting_value'];
// Official names come from the language catalogs (same as the print header on every other page).
$orgAr   = (string)(ak_catalog('ar')['common.organization_name'] ?? 'منظمة أهل الخير النسوية لكفالة الأيتام');
$orgEn   = (string)(ak_catalog('en')['common.organization_name'] ?? 'Ahl El Kheir Women Organization for Orphan Sponsorship');
$orgAddr = trim((string)($org['org_address'] ?? ''));
$orgTel  = trim((string)($org['support_phone'] ?? ''));

$isReceipt = $v['voucher_type'] === 'receipt';
$copies = (($_GET['copies'] ?? '1') === '2') ? 2 : 1;
$amount = (float)$v['amount'];
$words = ak_voucher_amount_words($amount);
$printedBy = (string)(dbFetchOne("SELECT full_name FROM users WHERE id = ?", [(int)Session::getUserId()])['full_name'] ?? '');
$printedAt = date('Y-m-d H:i');
$self = $isProjectPayment
    ? APP_URL . 'modules/accounting/voucher_print.php?project_payment_id=' . (int)$v['id']
    : APP_URL . 'modules/accounting/voucher_print.php?id=' . (int)$v['id'];
$canIssue = !$isProjectPayment && ak_voucher_can('issue', Session::getUserRole());

$row = static function (string $ar, string $en, string $value, bool $strong = false): string {
    return '<tr><th><span class="ar">' . e($ar) . '</span><span class="en">' . e($en) . '</span></th><td' . ($strong ? ' class="strong"' : '') . '>' . ($value !== '' ? e($value) : '&nbsp;') . '</td></tr>';
};

$renderVoucher = static function (string $copyLabelAr, string $copyLabelEn) use ($v, $void, $orgAr, $orgEn, $orgAddr, $orgTel, $isReceipt, $amount, $words, $printedBy, $printedAt, $row): void {
    $titleAr = $isReceipt ? 'سند قبض' : 'سند صرف';
    $titleEn = $isReceipt ? 'RECEIPT VOUCHER' : 'PAYMENT VOUCHER';
    ?>
    <section class="voucher <?php echo $isReceipt ? 'is-receipt' : 'is-payment'; ?>">
        <?php if ($void): ?><div class="void-mark">مبطل <small>VOIDED</small></div><?php endif; ?>
        <header class="v-head">
            <img class="v-logo" src="<?php echo APP_URL; ?>assets/img/logo.png" alt="">
            <div class="v-org">
                <div class="v-org-ar"><?php echo e($orgAr); ?></div>
                <div class="v-org-en"><?php echo e($orgEn); ?></div>
                <?php if ($orgAddr !== '' || $orgTel !== ''): ?><div class="v-org-contact"><?php echo e(trim($orgAddr . ($orgAddr !== '' && $orgTel !== '' ? '  |  ' : '') . $orgTel)); ?></div><?php endif; ?>
            </div>
            <div class="v-no">
                <div class="v-no-label">الرقم / No.</div>
                <div class="v-no-value"><?php echo e($v['voucher_no']); ?></div>
                <div class="v-no-label mt">التاريخ / Date</div>
                <div class="v-no-date"><?php echo e($v['voucher_date']); ?></div>
            </div>
        </header>

        <div class="v-title"><span><?php echo $titleAr; ?></span><span class="sep">|</span><span class="en"><?php echo $titleEn; ?></span>
            <?php if ($copyLabelAr !== ''): ?><em><?php echo e($copyLabelAr . ' / ' . $copyLabelEn); ?></em><?php endif; ?></div>

        <div class="v-amount"><span class="lbl">المبلغ / Amount</span><span class="val"><?php echo number_format($amount, 2); ?></span><span class="cur">ج.س SDG</span></div>

        <table class="v-table">
            <?php
            echo $row($isReceipt ? 'استلمنا من' : 'صرفنا إلى', $isReceipt ? 'Received from' : 'Paid to', (string)($v['party_name'] ?? ''), true);
            echo $row('مبلغاً وقدره', 'The sum of', $words);
            echo $row('وذلك عن', 'Being', (string)($v['description'] ?? ''));
            echo $row($isReceipt ? 'حساب الإيراد' : 'حساب المصروف', 'Account', $v['other_name'] . ' (' . $v['other_code'] . ')');
            echo $row($isReceipt ? 'أُودع في' : 'صُرف من', $isReceipt ? 'Deposited to' : 'Paid from', $v['cash_name'] . ' (' . $v['cash_code'] . ')');
            if (!empty($v['reference_number'])) echo $row('رقم المرجع', 'Reference No.', (string)$v['reference_number']);
            ?>
        </table>

        <div class="v-sign">
            <div><span class="ar"><?php echo $isReceipt ? 'المُسلِّم (الدافع)' : 'المستفيد (المستلم)'; ?></span><span class="en"><?php echo $isReceipt ? 'Payer' : 'Beneficiary'; ?></span><i></i></div>
            <div><span class="ar">أعدّه</span><span class="en">Prepared by</span><i><?php echo e($v['creator'] ?? ''); ?></i></div>
            <div><span class="ar">المدير المالي</span><span class="en">Financial Manager</span><i></i></div>
        </div>

        <footer class="v-foot">
            <span>القيد المحاسبي / Journal: <b><bdi><?php echo e($v['entry_code'] ?? '—'); ?></bdi></b></span>
            <span>الحالة / Status: <b><?php echo $void ? 'مبطل / Voided (<bdi>' . e($void['entry_code']) . '</bdi>)' : 'مرحّل / Posted'; ?></b></span>
            <span>طُبع بواسطة / Printed by: <bdi><?php echo e($printedBy); ?></bdi> — <bdi><?php echo e($printedAt); ?></bdi></span>
        </footer>
    </section>
    <?php
};
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo e(($isReceipt ? 'سند قبض ' : 'سند صرف ') . $v['voucher_no']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    :root { --accent: <?php echo $isReceipt ? '#1b4d8f' : '#9a3412'; ?>; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9edf3; font-family: 'Cairo', Tahoma, sans-serif; color: #111; }
    .controls { max-width: 210mm; margin: 12px auto; padding: 0 8px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; }
    .controls .btn { font-family: inherit; font-size: .9rem; padding: 8px 18px; border-radius: 8px; border: 1px solid #1b4d8f; background: #fff; color: #1b4d8f; text-decoration: none; cursor: pointer; }
    .controls .btn.primary { background: #1b4d8f; color: #fff; }
    .banner { max-width: 210mm; margin: 12px auto 0; padding: 10px 16px; border-radius: 8px; background: #e7f6ec; color: #14532d; border: 1px solid #b7e4c7; text-align: center; font-weight: 700; }
    .sheet { width: 210mm; margin: 0 auto 24px; background: #fff; padding: 10mm; box-shadow: 0 2px 14px rgba(0,0,0,.15); }
    .cut { border: 0; border-top: 1px dashed #777; margin: 4mm 0; text-align: center; height: 0; overflow: visible; }
    .cut span { position: relative; top: -9px; background: #fff; padding: 0 8px; font-size: 8pt; color: #666; }

    .voucher { position: relative; border: 2px solid var(--accent); border-radius: 3mm; padding: 5mm 6mm; break-inside: avoid; }
    .ar { font-weight: 700; } .en { display: block; font-size: 7.5pt; color: #555; font-weight: 400; }
    .v-head { display: flex; align-items: center; gap: 5mm; padding-bottom: 3mm; border-bottom: 2px solid var(--accent); }
    .v-logo { width: 20mm; height: 20mm; border-radius: 50%; object-fit: cover; border: 1px solid #bbb; flex: 0 0 20mm; }
    .v-org { flex: 1; text-align: center; }
    .v-org-ar { font-size: 14pt; font-weight: 800; color: var(--accent); line-height: 1.3; }
    .v-org-en { font-size: 8.5pt; color: #444; }
    .v-org-contact { font-size: 8pt; color: #555; margin-top: 1mm; }
    .v-no { flex: 0 0 38mm; text-align: center; border: 1px solid var(--accent); border-radius: 2mm; padding: 2mm; }
    .v-no-label { font-size: 7.5pt; color: #555; } .v-no-label.mt { margin-top: 1.5mm; }
    .v-no-value { font-size: 13pt; font-weight: 800; color: var(--accent); direction: ltr; }
    .v-no-date { font-size: 10pt; font-weight: 700; direction: ltr; }
    .v-title { margin: 3mm 0; background: var(--accent); color: #fff; text-align: center; font-size: 15pt; font-weight: 800; padding: 1.5mm 4mm; border-radius: 2mm; position: relative; }
    .v-title .sep { margin: 0 4mm; opacity: .6; } .v-title .en { display: inline; color: #fff; font-size: 11pt; font-weight: 700; letter-spacing: .5px; }
    .v-title em { position: absolute; left: 4mm; top: 50%; transform: translateY(-50%); font-size: 8pt; font-style: normal; opacity: .9; }
    .v-amount { display: flex; align-items: baseline; justify-content: center; gap: 4mm; margin: 2mm auto 3mm; padding: 2mm 6mm; border: 2px solid var(--accent); border-radius: 2mm; width: fit-content; min-width: 70mm; }
    .v-amount .lbl { font-size: 8.5pt; color: #444; } .v-amount .val { font-size: 20pt; font-weight: 800; direction: ltr; color: var(--accent); } .v-amount .cur { font-size: 9pt; font-weight: 700; }
    .v-table { width: 100%; border-collapse: collapse; }
    .v-table th, .v-table td { border: 1px solid #b9c3d3; padding: 2mm 3mm; vertical-align: middle; font-size: 10pt; }
    .v-table th { width: 30mm; background: #eef2f9; text-align: right; }
    .v-table td { min-height: 8mm; } .v-table td.strong { font-weight: 800; font-size: 11.5pt; }
    .v-sign { display: flex; gap: 6mm; margin-top: 9mm; }
    .v-sign > div { flex: 1; text-align: center; font-size: 9.5pt; }
    .v-sign i { display: block; margin-top: 10mm; border-top: 1px solid #333; padding-top: 1mm; font-style: normal; font-size: 8.5pt; color: #333; min-height: 5mm; }
    .v-foot { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2mm 6mm; margin-top: 4mm; padding-top: 2mm; border-top: 1px solid #ccc; font-size: 7.5pt; color: #555; }
    .void-mark { position: absolute; top: 46%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg); z-index: 3; border: 3mm solid rgba(179,38,30,.35); color: rgba(179,38,30,.38); font-size: 54pt; font-weight: 800; padding: 0 10mm; line-height: 1.15; text-align: center; pointer-events: none; }
    .void-mark small { display: block; font-size: 20pt; letter-spacing: 4px; }

    .sheet.two .voucher { padding: 3mm 5mm; }
    .sheet.two .v-head { padding-bottom: 1.5mm; gap: 4mm; }
    .sheet.two .v-logo { width: 14mm; height: 14mm; flex-basis: 14mm; }
    .sheet.two .v-org-ar { font-size: 12pt; } .sheet.two .v-org-en { font-size: 7.5pt; }
    .sheet.two .v-no { flex-basis: 34mm; padding: 1mm; } .sheet.two .v-no-value { font-size: 11pt; } .sheet.two .v-no-date { font-size: 9pt; }
    .sheet.two .v-title { margin: 2mm 0; font-size: 12.5pt; padding: 1mm 4mm; }
    .sheet.two .v-amount { margin: 1mm auto 2mm; padding: 1mm 5mm; } .sheet.two .v-amount .val { font-size: 16pt; }
    .sheet.two .v-table th, .sheet.two .v-table td { padding: 1.2mm 2.5mm; font-size: 9pt; } .sheet.two .v-table td.strong { font-size: 10pt; }
    .sheet.two .v-sign { margin-top: 5mm; } .sheet.two .v-sign i { margin-top: 7mm; }
    .sheet.two .v-foot { margin-top: 2mm; padding-top: 1mm; }
    .sheet.two .cut { margin: 2mm 0; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print {
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { background: #fff; }
        .controls, .banner { display: none !important; }
        .sheet { width: auto; margin: 0; padding: 0; box-shadow: none; }
    }
</style>
</head>
<body>
<?php if (($_GET['new'] ?? '') === '1'): ?>
<div class="banner">✔ تم إصدار السند وترحيله محاسبياً — تم تحديث رصيد <?php echo e($v['cash_name']); ?>. يمكنك طباعته الآن.<br><small>Voucher issued and posted — the <?php echo e($v['cash_name']); ?> balance has been updated.</small></div>
<?php endif; ?>
<div class="controls">
    <button class="btn primary" onclick="window.print()">🖨 طباعة / Print</button>
    <?php if ($copies === 1): ?><a class="btn" href="<?php echo e($self); ?>&copies=2">نسختان في الصفحة / 2 copies</a>
    <?php else: ?><a class="btn" href="<?php echo e($self); ?>">نسخة واحدة / 1 copy</a><?php endif; ?>
    <?php if (!$isProjectPayment): ?><a class="btn" href="<?php echo APP_URL; ?>modules/accounting/vouchers.php?tab=list">سجل السندات / Register</a><?php endif; ?>
    <?php if ($canIssue): ?><a class="btn" href="<?php echo APP_URL; ?>modules/accounting/vouchers.php?tab=new">سند جديد / New voucher</a><?php endif; ?>
</div>
<div class="sheet<?php echo $copies === 2 ? ' two' : ''; ?>">
    <?php
    $renderVoucher($copies === 2 ? 'الأصل' : '', $copies === 2 ? 'Original' : '');
    if ($copies === 2) {
        echo '<div class="cut"><span>✂</span></div>';
        $renderVoucher('صورة', 'Copy');
    }
    ?>
</div>
</body>
</html>
