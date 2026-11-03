<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$format = (string) ($_GET['format'] ?? '');
$monthInput = (string) ($_GET['month'] ?? date('Y-m'));
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $monthInput);
if (!$monthDate || $monthDate->format('Y-m') !== $monthInput) {
    http_response_code(400);
    exit('Invalid report month.');
}
$monthStart = $monthDate->format('Y-m-01');
$monthEnd = $monthDate->modify('last day of this month')->format('Y-m-d');

$stmt = $pdo->prepare("
 SELECT sp.sponsor_name, mo.mother_name, o.orphan_name,
        me.sponsorship_amount, me.services_amount, me.entered_by, me.supervisor_name
 FROM mde_monthly_sponsorship_entries me
 JOIN mde_sponsorships ss ON ss.id=me.sponsorship_id
 JOIN mde_sponsors sp ON sp.id=ss.sponsor_id
 JOIN mde_orphans o ON o.id=ss.orphan_id
 JOIN mde_mothers mo ON mo.id=o.mother_id
 WHERE me.report_month=?
 ORDER BY sp.sponsor_name, mo.mother_name, o.orphan_name
");
$stmt->execute([$monthStart]);
$rows = $stmt->fetchAll();

$departuresStmt = $pdo->prepare("
 SELECT sp.sponsor_name, d.departure_date, d.orphan_count_snapshot,
        d.sponsorship_amount_snapshot, d.services_amount_snapshot, d.last_paid_month
 FROM mde_sponsor_departures d
 JOIN mde_sponsors sp ON sp.id=d.sponsor_id
 WHERE d.departure_date BETWEEN ? AND ?
 ORDER BY d.departure_date, sp.sponsor_name
");
$departuresStmt->execute([$monthStart, $monthEnd]);
$departures = $departuresStmt->fetchAll();

$donationsStmt = $pdo->prepare("
 SELECT sp.sponsor_name, d.donation_type, d.donation_date, d.amount, d.notes
 FROM mde_monthly_other_donations d
 LEFT JOIN mde_sponsors sp ON sp.id=d.sponsor_id
 WHERE d.report_month=?
 ORDER BY d.donation_type, sp.sponsor_name
");
$donationsStmt->execute([$monthStart]);
$donations = $donationsStmt->fetchAll();

$filenameMonth = $monthDate->format('Y-m');
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="monthly-report-' . $filenameMonth . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['التقرير الشهري', $filenameMonth]);
    fputcsv($out, []);
    fputcsv($out, ['الكفالات والخدمات']);
    fputcsv($out, ['الكفيل', 'الأم', 'اليتيم', 'قيمة الكفالة', 'الخدمات', 'بواسطة', 'المشرف']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['sponsor_name'], $row['mother_name'], $row['orphan_name'], $row['sponsorship_amount'], $row['services_amount'], $row['entered_by'], $row['supervisor_name']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['التبرعات الأخرى']);
    fputcsv($out, ['الكفيل', 'نوع التبرع', 'التاريخ', 'القيمة', 'ملاحظات']);
    foreach ($donations as $row) {
        fputcsv($out, [$row['sponsor_name'], $row['donation_type'], $row['donation_date'], $row['amount'], $row['notes']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['مغادرات الكفلاء']);
    fputcsv($out, ['الكفيل', 'تاريخ المغادرة', 'عدد الأيتام', 'قيمة الكفالة', 'الخدمات', 'آخر سداد']);
    foreach ($departures as $row) {
        fputcsv($out, [$row['sponsor_name'], $row['departure_date'], $row['orphan_count_snapshot'], $row['sponsorship_amount_snapshot'], $row['services_amount_snapshot'], $row['last_paid_month']]);
    }
    fclose($out);
    exit;
}

if ($format === 'pdf') {
    $tcpdf = dirname(__DIR__, 2) . '/TCPDF/tcpdf.php';
    if (!is_file($tcpdf)) {
        http_response_code(503);
        exit('TCPDF library is not available.');
    }
    require_once $tcpdf;
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Ahl El Kheir Monthly Data Entry');
    $pdf->SetTitle('Monthly report ' . $filenameMonth);
    $pdf->SetMargins(8, 10, 8);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->setRTL(true);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->AddPage();
    $html = '<h2 style="text-align:center">التقرير الشهري - ' . htmlspecialchars($filenameMonth, ENT_QUOTES, 'UTF-8') . '</h2>';
    $html .= '<h3>الكفالات والخدمات</h3><table border="1" cellpadding="4"><thead><tr style="font-weight:bold;background-color:#eeeeee"><th>الكفيل</th><th>الأم</th><th>اليتيم</th><th>قيمة الكفالة</th><th>الخدمات</th><th>بواسطة</th><th>المشرف</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ([$row['sponsor_name'], $row['mother_name'], $row['orphan_name'], number_format((int)$row['sponsorship_amount']), number_format((int)$row['services_amount']), $row['entered_by'], $row['supervisor_name']] as $value) {
            $html .= '<td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table><h3>التبرعات الأخرى</h3><table border="1" cellpadding="4"><tr style="font-weight:bold;background-color:#eeeeee"><th>الكفيل</th><th>نوع التبرع</th><th>التاريخ</th><th>القيمة</th></tr>';
    foreach ($donations as $row) {
        $html .= '<tr><td>' . htmlspecialchars((string)$row['sponsor_name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$row['donation_type'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$row['donation_date'], ENT_QUOTES, 'UTF-8') . '</td><td>' . number_format((int)$row['amount']) . '</td></tr>';
    }
    $html .= '</table><h3>مغادرات الكفلاء</h3><table border="1" cellpadding="4"><tr style="font-weight:bold;background-color:#eeeeee"><th>الكفيل</th><th>تاريخ المغادرة</th><th>عدد الأيتام</th><th>قيمة الكفالة</th><th>الخدمات</th><th>آخر سداد</th></tr>';
    foreach ($departures as $row) {
        $html .= '<tr><td>' . htmlspecialchars((string)$row['sponsor_name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$row['departure_date'], ENT_QUOTES, 'UTF-8') . '</td><td>' . (int)$row['orphan_count_snapshot'] . '</td><td>' . number_format((int)$row['sponsorship_amount_snapshot']) . '</td><td>' . number_format((int)$row['services_amount_snapshot']) . '</td><td>' . htmlspecialchars((string)$row['last_paid_month'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('monthly-report-' . $filenameMonth . '.pdf', 'D');
    exit;
}

http_response_code(400);
echo 'Unsupported export format.';
