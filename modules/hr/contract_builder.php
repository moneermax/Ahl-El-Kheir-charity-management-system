<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
$allowedRoles = ['hr_manager', 'hr_staff', 'admin'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowedRoles, true)) {
    http_response_code(403);
    exit('ليس لديك صلاحية إعداد عقود العمل.');
}

$pdo = db();
$employees = dbFetchAll(
    "SELECT e.id, e.user_id, e.employee_code, e.full_name, e.national_id, e.phone, e.email,
            e.address, e.position, e.basic_salary, d.name_ar AS department_name,
            r.code AS system_role_code, r.name_ar AS system_role_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     LEFT JOIN users u ON u.id = e.user_id
     LEFT JOIN roles r ON r.id = u.role_id
     ORDER BY e.full_name",
    []
);
$jobDescriptions = dbFetchAll(
    "SELECT id, position_title, system_role_code, role_purpose, responsibilities, qualifications, reporting_to, role_specific_terms, description_status
     FROM hr_job_description_templates
     WHERE is_active = 1
     ORDER BY position_title, system_role_code",
    []
);
$jobDescriptionByPosition = [];
$jobDescriptionByPositionRole = [];
foreach ($jobDescriptions as $description) {
    $positionKey = trim((string)$description['position_title']);
    $systemRoleCode = trim((string)($description['system_role_code'] ?? ''));
    if ($systemRoleCode === '') {
        $jobDescriptionByPosition[$positionKey] = $description;
    } else {
        $jobDescriptionByPositionRole[$positionKey . '|' . $systemRoleCode] = $description;
    }
}
$jobDescriptionForEmployee = static function (array $employee) use ($jobDescriptionByPosition, $jobDescriptionByPositionRole): ?array {
    $position = trim((string)($employee['position'] ?? ''));
    $roleCode = trim((string)($employee['system_role_code'] ?? ''));
    if ($position === '') return null;
    return ($roleCode !== '' ? ($jobDescriptionByPositionRole[$position . '|' . $roleCode] ?? null) : null)
        ?? ($jobDescriptionByPosition[$position] ?? null);
};
$organizationSettings = [];
foreach (dbFetchAll(
    "SELECT setting_key, setting_value FROM settings
     WHERE setting_key IN ('org_name_ar','org_name_en','org_address','support_phone','support_email','site_logo')",
    []
) as $setting) {
    $organizationSettings[(string)$setting['setting_key']] = (string)$setting['setting_value'];
}
$selectedEmployeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$supervisorCandidates = array_values(array_filter($employees, static function ($employee) use ($selectedEmployeeId) {
    return (int)$employee['id'] !== $selectedEmployeeId;
}));

function akContractTemplateItems(string $value): array
{
    $items = preg_split('/\\R/u', trim($value)) ?: [];
    return array_values(array_filter(array_map(static function ($item) {
        return trim((string)preg_replace('/^\\s*(?:[•*\\-–—]|\\d+[.)])\\s*/u', '', (string)$item));
    }, $items), static fn($item) => $item !== ''));
}

function akContractTemplateText(?array $template): string
{
    if (!$template) return '';
    $parts = [];
    foreach ([
        'الغرض الوظيفي' => $template['role_purpose'] ?? '',
        'المهام والمسؤوليات' => $template['responsibilities'] ?? '',
        'المؤهلات والخبرات' => $template['qualifications'] ?? '',
        'جهة الإشراف' => $template['reporting_to'] ?? '',
    ] as $label => $value) {
        $value = trim((string)$value);
        if ($value === '') continue;
        if (in_array($label, ['الغرض الوظيفي', 'المهام والمسؤوليات', 'المؤهلات والخبرات'], true)) {
            $items = preg_split('/\\R/u', $value) ?: [];
            $items = array_values(array_filter(array_map(static function ($item) {
                return trim((string)preg_replace('/^\\s*(?:[•*\\-–—]|\\d+[.)])\\s*/u', '', (string)$item));
            }, $items), static fn($item) => $item !== ''));
            if ($items) {
                $parts[] = $label . ':';
                foreach ($items as $item) $parts[] = '• ' . $item;
            }
        } else {
            $parts[] = $label . ': ' . $value;
        }
    }
    return implode("\n", $parts);
}

function akContractValue(array $data, string $key, string $default = ''): string
{
    return trim((string)($data[$key] ?? $default));
}

function akContractEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function akContractDutiesHtml(string $text): string
{
    $lines = preg_split('/\R/u', trim($text)) ?: [];
    $html = '';
    $inList = false;
    $section = '';
    $closeList = static function () use (&$html, &$inList): void {
        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
    };
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            $closeList();
            continue;
        }
        if (preg_match('/^(الغرض الوظيفي|المهام والمسؤوليات|المؤهلات والخبرات|جهة الإشراف)\s*:\s*(.*)$/u', $line, $matches)) {
            $closeList();
            $section = (string)$matches[1];
            $tail = trim((string)$matches[2]);
            $html .= '<p><strong>' . akContractEscape($section) . ':</strong></p>';
            if ($tail !== '') {
                if (in_array($section, ['الغرض الوظيفي', 'المهام والمسؤوليات', 'المؤهلات والخبرات'], true)) {
                    $item = trim((string)preg_replace('/^\s*(?:[•*\-–—]|\d+[.)])\s*/u', '', $tail));
                    if ($item !== '') {
                        $html .= '<ul style="margin-right:18px;padding-right:10px;">';
                        $inList = true;
                        $html .= '<li>' . akContractEscape($item) . '</li>';
                    }
                } else {
                    $html .= '<p>' . akContractEscape($tail) . '</p>';
                }
            }
            continue;
        }
        $item = trim((string)preg_replace('/^\s*(?:[•*\-–—]|\d+[.)])\s*/u', '', $line));
        if (in_array($section, ['الغرض الوظيفي', 'المهام والمسؤوليات', 'المؤهلات والخبرات'], true)) {
            if (!$inList) {
                $html .= '<ul style="margin-right:18px;padding-right:10px;">';
                $inList = true;
            }
            $html .= '<li>' . akContractEscape($item) . '</li>';
        } else {
            $closeList();
            $html .= '<p>' . akContractEscape($line) . '</p>';
        }
    }
    $closeList();
    return $html !== '' ? $html : 'تنفيذ المهام المرتبطة بالوظيفة والوصف الوظيفي والتعليمات المشروعة ذات الصلة.';
}

function akContractDate(string $value, string $label, bool $required = false): ?string
{
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException('يرجى إدخال ' . $label . '.');
        }
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('قيمة ' . $label . ' غير صالحة.');
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_pdf') {
    try {
        if (!verify_csrf()) {
            http_response_code(403);
            throw new RuntimeException('انتهت صلاحية النموذج. أعد تحميل الصفحة ثم حاول مرة أخرى.');
        }

        $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$employeeId || $employeeId < 1) {
            throw new InvalidArgumentException('اختر الموظف المرتبط بالعقد.');
        }
        $employeeExists = dbFetchOne(
            'SELECT e.id, e.position, r.code AS system_role_code
             FROM employees e
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE e.id = ? LIMIT 1',
            [(int)$employeeId]
        );
        if (!$employeeExists) {
            throw new InvalidArgumentException('الموظف المحدد غير موجود.');
        }
        $employeePosition = trim((string)($employeeExists['position'] ?? ''));
        $employeeSystemRoleCode = trim((string)($employeeExists['system_role_code'] ?? ''));
        $jobTemplate = $employeePosition !== ''
            ? (($employeeSystemRoleCode !== '' ? ($jobDescriptionByPositionRole[$employeePosition . '|' . $employeeSystemRoleCode] ?? null) : null)
                ?? ($jobDescriptionByPosition[$employeePosition] ?? null))
            : null;
        $jobDescriptionStatus = (string)($jobTemplate['description_status'] ?? 'needs_review');

        $data = [
            'contract_number' => akContractValue($_POST, 'contract_number'),
            'issue_date' => akContractValue($_POST, 'issue_date', date('Y-m-d')),
            'employee_name' => akContractValue($_POST, 'employee_name'),
            'national_id' => akContractValue($_POST, 'national_id'),
            'nationality' => akContractValue($_POST, 'nationality'),
            'address' => akContractValue($_POST, 'address'),
            'phone' => akContractValue($_POST, 'phone'),
            'email' => akContractValue($_POST, 'email'),
            'position' => $employeePosition,
            'department' => akContractValue($_POST, 'department'),
            'supervisor' => akContractValue($_POST, 'supervisor'),
            'work_location' => akContractValue($_POST, 'work_location'),
            'contract_type' => akContractValue($_POST, 'contract_type', 'fixed_term'),
            'start_date' => akContractValue($_POST, 'start_date'),
            'end_date' => akContractValue($_POST, 'end_date'),
            'probation' => akContractValue($_POST, 'probation'),
            'weekly_hours' => akContractValue($_POST, 'weekly_hours'),
            'working_days' => akContractValue($_POST, 'working_days'),
            'basic_salary' => akContractValue($_POST, 'basic_salary'),
            'pay_frequency' => akContractValue($_POST, 'pay_frequency', 'monthly'),
            'allowances' => akContractValue($_POST, 'allowances', 'لا توجد إلا ما يرد صراحةً في ملحق مكتوب'),
            'benefits' => akContractValue($_POST, 'benefits', 'وفق السياسات المعتمدة وأحكام القانون واجب التطبيق'),
            'annual_leave' => akContractValue($_POST, 'annual_leave', 'وفق قانون العمل السوداني والسياسات المعتمدة'),
            'sick_leave' => akContractValue($_POST, 'sick_leave', 'وفق قانون العمل السوداني والسياسات المعتمدة'),
            'notice_period' => akContractValue($_POST, 'notice_period', 'وفق القانون واجب التطبيق وشروط العقد'),
            'duties' => (static function (): string {
                $sections = [];
                $purpose = array_values(array_filter(array_map(static fn($item) => trim((string)$item), is_array($_POST['role_purpose'] ?? null) ? $_POST['role_purpose'] : [$_POST['role_purpose'] ?? '']), static fn($item) => $item !== ''));
                $responsibilities = array_values(array_filter(array_map(static fn($item) => trim((string)$item), is_array($_POST['responsibilities'] ?? null) ? $_POST['responsibilities'] : [$_POST['responsibilities'] ?? '']), static fn($item) => $item !== ''));
                $qualifications = array_values(array_filter(array_map(static fn($item) => trim((string)$item), is_array($_POST['qualifications'] ?? null) ? $_POST['qualifications'] : [$_POST['qualifications'] ?? '']), static fn($item) => $item !== ''));
                $reportingTo = trim((string)($_POST['reporting_to'] ?? ''));
                if ($purpose) $sections[] = "الغرض الوظيفي:\n" . implode("\n", array_map(static fn($item) => '• ' . $item, $purpose));
                if ($responsibilities) $sections[] = "المهام والمسؤوليات:\n" . implode("\n", array_map(static fn($item) => '• ' . $item, $responsibilities));
                if ($qualifications) $sections[] = "المؤهلات والخبرات:\n" . implode("\n", array_map(static fn($item) => '• ' . $item, $qualifications));
                if ($reportingTo !== '') $sections[] = "جهة الإشراف: " . $reportingTo;
                return implode("\n", $sections);
            })(),
            'job_description_status' => $jobDescriptionStatus,
            'special_terms' => implode("\n", array_values(array_filter(array_map(static fn($item) => trim((string)$item), is_array($_POST['special_terms'] ?? null) ? $_POST['special_terms'] : [$_POST['special_terms'] ?? '']), static fn($item) => $item !== ''))),
        ];

        $fieldLimits = [
            'contract_number' => 80, 'employee_name' => 180, 'national_id' => 100,
            'nationality' => 100, 'address' => 500, 'phone' => 80, 'email' => 180,
            'position' => 180, 'department' => 180, 'supervisor' => 180,
            'work_location' => 250, 'probation' => 250, 'working_days' => 180,
            'allowances' => 500, 'benefits' => 500, 'annual_leave' => 250,
            'sick_leave' => 250, 'notice_period' => 250, 'duties' => 5000,
            'special_terms' => 5000, 'weekly_hours' => 12, 'reporting_to' => 180,
        ];
        foreach ($fieldLimits as $field => $maxLength) {
            if (strlen((string)$data[$field]) > $maxLength) {
                throw new InvalidArgumentException('أحد الحقول أطول من الحد المسموح به.');
            }
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('البريد الإلكتروني غير صالح.');
        }
        if ($data['weekly_hours'] !== '' &&
            (!is_numeric($data['weekly_hours']) || (float)$data['weekly_hours'] < 0 || (float)$data['weekly_hours'] > 168)) {
            throw new InvalidArgumentException('ساعات العمل الأسبوعية يجب أن تكون بين صفر و168 ساعة.');
        }
        if ($data['contract_type'] === 'fixed_term' && $data['end_date'] === '') {
            throw new InvalidArgumentException('تاريخ نهاية العقد مطلوب للعقد محدد المدة.');
        }

        foreach (['employee_name' => 'اسم الموظف', 'position' => 'المسمى الوظيفي', 'start_date' => 'تاريخ بداية العقد', 'basic_salary' => 'الراتب الأساسي'] as $key => $label) {
            if ($data[$key] === '') {
                throw new InvalidArgumentException('يرجى إدخال ' . $label . '.');
            }
        }
        if (!in_array($data['contract_type'], ['permanent', 'fixed_term', 'part_time', 'temporary', 'internship', 'other'], true)) {
            throw new InvalidArgumentException('نوع العقد المحدد غير صالح.');
        }
        if (!in_array($data['pay_frequency'], ['monthly', 'weekly', 'daily', 'hourly'], true)) {
            throw new InvalidArgumentException('دورية الراتب المحددة غير صالحة.');
        }
        if (!is_numeric($data['basic_salary']) || !is_finite((float)$data['basic_salary']) || (float)$data['basic_salary'] < 0) {
            throw new InvalidArgumentException('الراتب الأساسي يجب أن يكون رقماً غير سالب.');
        }
        $data['issue_date'] = akContractDate($data['issue_date'], 'تاريخ تحرير العقد', true) ?? date('Y-m-d');
        $data['start_date'] = akContractDate($data['start_date'], 'تاريخ بداية العقد', true) ?? '';
        $data['end_date'] = akContractDate($data['end_date'], 'تاريخ نهاية العقد');
        if ($data['end_date'] !== null && $data['end_date'] < $data['start_date']) {
            throw new InvalidArgumentException('تاريخ نهاية العقد لا يمكن أن يسبق تاريخ بدايته.');
        }
        $typeLabels = [
            'permanent' => 'غير محدد المدة',
            'fixed_term' => 'محدد المدة',
            'part_time' => 'دوام جزئي',
            'temporary' => 'مؤقت',
            'internship' => 'تدريب',
            'other' => 'نوع آخر موضح في الشروط الخاصة',
        ];
        $frequencyLabels = ['monthly' => 'شهرياً', 'weekly' => 'أسبوعياً', 'daily' => 'يومياً', 'hourly' => 'بالساعة'];
        $v = static fn(string $key): string => akContractEscape((string)($data[$key] ?? '—'));
        $endDateText = $data['end_date'] ?: 'لا يوجد تاريخ نهاية محدد';
        $probationText = $data['probation'] !== '' ? $data['probation'] : 'وفق ما يجيزه القانون واجب التطبيق وما يتفق عليه كتابةً';
        if ($data['duties'] === '' && !isset($_POST['role_purpose']) && !isset($_POST['responsibilities']) && !isset($_POST['qualifications'])) {
            $data['duties'] = akContractTemplateText($jobTemplate);
        }
        if (strlen($data['duties']) > 5000) {
            throw new InvalidArgumentException('الوصف الوظيفي أطول من الحد المسموح به للعقد؛ اختصره من فهرس الأوصاف أو عدّله قبل التوليد.');
        }
        $dutiesText = $data['duties'] !== '' ? akContractDutiesHtml($data['duties']) : 'تنفيذ المهام المرتبطة بالوظيفة والوصف الوظيفي والتعليمات المشروعة ذات الصلة.';
        $jobDescriptionNotice = $jobDescriptionStatus === 'approved'
            ? ''
            : '<p style="color:#8a4b08;background-color:#fff7e6;border:1px solid #e5c07b;padding:5px;"><b>تنبيه داخلي:</b> الوصف الوظيفي لهذا المسمى يحتاج مراجعة واعتماد الموارد البشرية، ولا يُعد وصفاً معتمداً.</p>';
        $specialTermsText = $data['special_terms'] !== '' ? nl2br($v('special_terms')) : 'لا توجد شروط خاصة إضافية.';
        $configuredLogo = (string)($organizationSettings['site_logo'] ?? 'assets/img/logo.png');
        if ($configuredLogo === '' || strpos($configuredLogo, '..') !== false || str_starts_with($configuredLogo, '/')) {
            $configuredLogo = 'assets/img/logo.png';
        }
        $logoPath = APP_DIR . '/' . $configuredLogo;
        if (!is_file($logoPath)) {
            $logoPath = APP_DIR . '/assets/img/logo.png';
        }
        $organizationName = trim((string)($organizationSettings['org_name_ar'] ?? '')) ?: 'جمعية أهل الخير الخيرية';
        $contactParts = [];
        foreach (['org_address' => 'العنوان', 'support_phone' => 'الهاتف', 'support_email' => 'البريد الإلكتروني'] as $key => $label) {
            $value = trim((string)($organizationSettings[$key] ?? ''));
            if ($value !== '') $contactParts[] = $label . ': ' . $value;
        }
        $headerContact = $contactParts ? 'عقد عمل | ' . implode(' | ', $contactParts) : 'عقد عمل';

        require_once APP_DIR . '/TCPDF/tcpdf.php';
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor(APP_NAME_AR);
        $pdf->SetTitle('عقد عمل - ' . $data['employee_name']);
        $pdf->SetSubject('عقد عمل');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->setHeaderData(is_file($logoPath) ? $logoPath : '', 20, $organizationName, $headerContact);
        $pdf->setHeaderFont(['aealarabiya', '', 8]);
        $pdf->setFooterFont(['aealarabiya', '', 8]);
        $pdf->setHeaderMargin(5);
        $pdf->setFooterMargin(10);
        $pdf->SetMargins(16, 30, 16);
        $pdf->SetAutoPageBreak(true, 17);
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 11);
        $pdf->AddPage();

        $html = '
        <style>
            body { font-family: aealarabiya; color: #263445; font-size: 10.5pt; line-height: 1.55; }
            h2 { color: #173f73; font-size: 12pt; font-weight: bold; background-color: #f0f4f9; padding: 5px; }
            .meta { color: #475467; font-size: 9pt; }
            .rule { border: 1px solid #d6dde7; padding: 6px; }
            .clause { margin-bottom: 5px; }
        </style>
        <p class="meta">رقم العقد: <b>' . ($data['contract_number'] !== '' ? $v('contract_number') : 'يُستكمل عند التسجيل') . '</b> &nbsp;&nbsp; تاريخ التحرير: <b>' . $v('issue_date') . '</b></p>
        <p>تم إبرام هذا العقد بين ' . akContractEscape($organizationName) . '، ويشار إليها في هذا العقد بـ«الجمعية» أو «صاحب العمل»، وبين الموظف المبينة بياناته أدناه، ويشار إليه بـ«الموظف»، وفق أحكام هذا العقد وقوانين العمل السودانية السارية والسياسات المعتمدة المتوافقة معها.</p>
        <h2>أولاً: بيانات الطرفين والوظيفة</h2>
        <table cellpadding="5" border="1" bordercolor="#d6dde7">
          <tr><td width="50%"><b>اسم الموظف:</b> ' . $v('employee_name') . '</td><td width="50%"><b>الرقم الوطني/الهوية:</b> ' . ($data['national_id'] !== '' ? $v('national_id') : '—') . '</td></tr>
          <tr><td><b>الجنسية:</b> ' . $v('nationality') . '</td><td><b>المسمى الوظيفي:</b> ' . $v('position') . '</td></tr>
          <tr><td><b>القسم:</b> ' . ($data['department'] !== '' ? $v('department') : '—') . '</td><td><b>المشرف المباشر:</b> ' . ($data['supervisor'] !== '' ? $v('supervisor') : '—') . '</td></tr>
          <tr><td><b>العنوان:</b> ' . ($data['address'] !== '' ? $v('address') : '—') . '</td><td><b>الهاتف:</b> ' . ($data['phone'] !== '' ? $v('phone') : '—') . '</td></tr>
          <tr><td><b>البريد الإلكتروني:</b> ' . ($data['email'] !== '' ? $v('email') : '—') . '</td><td><b>مكان العمل:</b> ' . ($data['work_location'] !== '' ? $v('work_location') : 'حسب تكليف الجمعية المكتوب') . '</td></tr>
        </table>
        <h2>ثانياً: نوع العقد ومدته وفترة التجربة</h2>
        <p>نوع العقد: <b>' . akContractEscape($typeLabels[$data['contract_type']]) . '</b>. يبدأ في <b>' . $v('start_date') . '</b> وينتهي في <b>' . akContractEscape($endDateText) . '</b>. فترة التجربة: <b>' . akContractEscape($probationText) . '</b>. لا يغيّر هذا النص أي حماية أو شرط إلزامي مقرر بموجب القانون واجب التطبيق.</p>
        <h2>ثالثاً: المهام والمسؤوليات</h2>
        ' . $jobDescriptionNotice . '
        <p>' . $dutiesText . '</p>
        <h2>رابعاً: ساعات العمل والإجازات</h2>
        <p>ساعات العمل الأسبوعية: <b>' . ($data['weekly_hours'] !== '' ? $v('weekly_hours') . ' ساعة' : 'وفق الجدول المعتمد والقانون واجب التطبيق') . '</b>. أيام العمل: <b>' . ($data['working_days'] !== '' ? $v('working_days') : 'وفق الجدول المعتمد') . '</b>. الإجازة السنوية: <b>' . $v('annual_leave') . '</b>. الإجازة المرضية: <b>' . $v('sick_leave') . '</b>. وتراعى فترات الراحة والعطلات والإجازات النظامية وأي شروط أفضل مقررة بصورة صحيحة.</p>
        <h2>خامساً: الأجر والمزايا</h2>
        <p>يتقاضى الموظف أجراً أساسياً قدره <b>' . number_format((float)$data['basic_salary'], 2, '.', ',') . ' ' . akContractEscape(APP_CURRENCY_CODE) . '</b>، يُدفع <b>' . akContractEscape($frequencyLabels[$data['pay_frequency']]) . '</b> وفق دورة الرواتب المعتمدة وبعد الاستقطاعات النظامية المسموح بها. البدلات: <b>' . $v('allowances') . '</b>. المزايا الأخرى: <b>' . $v('benefits') . '</b>. ولا ينشئ هذا العقد استحقاقاً مالياً غير مذكور صراحةً أو غير مقرر بموجب القانون أو سياسة معتمدة واجبة التطبيق.</p>
        <h2>سادساً: السلوك المهني والحماية</h2>
        <p>يلتزم الموظف بمدونة السلوك والسياسات والتعليمات المشروعة للجمعية، وباحترام المستفيدين والزملاء والشركاء. يُحظر الاستغلال والانتهاك والتحرش والتمييز والتنمر وأي إساءة للطفل أو للشخص المستضعف. ويلتزم الموظف بمتطلبات الحماية من الاستغلال والانتهاك الجنسي (PSEA) وحماية الطفل، وبالإبلاغ عبر القنوات المعتمدة عن أي مخاوف أو مخالفات، وبالتعاون مع التحقيقات النظامية. وتُعالج الادعاءات بإجراءات عادلة وسرية وبما يتفق مع القانون والسياسات.</p>
        <h2>سابعاً: السرية والبيانات وتعارض المصالح</h2>
        <p>يحافظ الموظف على سرية المعلومات والبيانات الشخصية والملفات التي يطلع عليها بسبب عمله، ويستخدمها للغرض الوظيفي المصرح به فقط. ويلتزم بحماية كلمات المرور والسجلات وعدم نسخ المعلومات أو إفشائها دون تفويض. كما يلتزم بالإفصاح عن تعارض المصالح، وعدم الاحتيال أو الرشوة أو إساءة استخدام أموال الجمعية أو ممتلكاتها، وإعادة العهد والأصول والوثائق عند الطلب أو انتهاء الخدمة، مع مراعاة الحقوق التي لا يجوز التنازل عنها قانوناً.</p>
        <h2>ثامناً: الصحة والسلامة والأداء والتدريب</h2>
        <p>يلتزم الطرفان بإجراءات الصحة والسلامة المهنية والإبلاغ عن المخاطر والحوادث. ويؤدي الموظف مهامه بعناية مهنية، ويشارك في التوجيه والتدريب والتقييم المعقول المرتبط بالعمل. ولا يحد أي تقييم أو إجراء إداري من حق الموظف في الاعتراض أو الشكوى وفق الإجراءات المعتمدة والقانون.</p>
        <h2>تاسعاً: إنهاء العقد والمستحقات</h2>
        <p>يكون الإنهاء أو عدم التجديد أو الاستقالة وفق هذا العقد والإجراءات القانونية الواجبة. مدة الإخطار: <b>' . $v('notice_period') . '</b>. وتُسوّى الأجور والمستحقات والإجازات وأي حقوق نهائية واجبة وفق القانون والأدلة والسجلات المعتمدة، ولا يتضمن هذا العقد تنازلاً مسبقاً عن حق لا يجوز التنازل عنه قانوناً.</p>
        <h2>عاشراً: الشكاوى وتسوية النزاعات والقانون الواجب التطبيق</h2>
        <p>يسعى الطرفان إلى معالجة الخلافات مهنياً وبحسن نية عبر قنوات الشكاوى المعتمدة، دون منع أي طرف من اللجوء إلى الجهات المختصة. يخضع العقد لقوانين جمهورية السودان السارية، وتُطبّق الأحكام القانونية الآمرة عند تعارضها مع أي شرط تعاقدي.</p>
        <h2>حادي عشر: التعديلات والشروط الخاصة</h2>
        <p>لا يكون أي تعديل نافذاً إلا إذا كان مكتوباً وموافقاً عليه من الطرفين، وبما لا يخالف القانون. الشروط الخاصة: ' . $specialTermsText . '</p>
        <h2>إقرار وتوقيع الطرفين</h2>
        <p>يقر الطرفان بأنهما قرآ العقد وفهما أحكامه، وأتيحت لهما فرصة طلب التوضيح، وأنهما تسلما نسخة موقعة منه. لا يُعد توقيع الموظف تنازلاً عن أي حق إلزامي مقرر بموجب القانون.</p>
        <table cellpadding="8" border="1" bordercolor="#d6dde7">
          <tr><td width="50%"><b>عن ' . akContractEscape($organizationName) . '</b><br /><br />الاسم: __________________________<br /><br />الصفة: __________________________<br /><br />التوقيع والختم: ___________________<br /><br />التاريخ: _________________________</td><td width="50%"><b>الموظف</b><br /><br />الاسم: ' . $v('employee_name') . '<br /><br />التوقيع: _________________________<br /><br />التاريخ: _________________________</td></tr>
          <tr><td colspan="2"><b>الشاهد (إن لزم):</b> الاسم: __________________________ &nbsp; التوقيع: __________________ &nbsp; التاريخ: ______________</td></tr>
        </table>
        ';

        $pdf->SetFont('aealarabiya', '', 10.5);
        $pdf->writeHTML($html, true, false, true, false, '');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        $pdf->Output('Ahl-El-Kheir-Employment-Contract.pdf', 'I');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((int)$employee['id'] === $selectedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}
$selectedPosition = trim((string)($selectedEmployee['position'] ?? ''));
$selectedJobDescription = $jobDescriptionForEmployee($selectedEmployee ?? []);
$selectedJobPurposeItems = akContractTemplateItems((string)($selectedJobDescription['role_purpose'] ?? ''));
$selectedJobResponsibilityItems = akContractTemplateItems((string)($selectedJobDescription['responsibilities'] ?? ''));
$selectedJobQualificationItems = akContractTemplateItems((string)($selectedJobDescription['qualifications'] ?? ''));
$selectedJobReportingTo = trim((string)($selectedJobDescription['reporting_to'] ?? ''));
$selectedJobStatus = (string)($selectedJobDescription['description_status'] ?? 'needs_review');
$portalLogoPath = (string)($organizationSettings['site_logo'] ?? 'assets/img/logo.png');
if ($portalLogoPath === '' || strpos($portalLogoPath, '..') !== false || str_starts_with($portalLogoPath, '/')
    || !is_file(APP_DIR . '/' . $portalLogoPath)) {
    $portalLogoPath = 'assets/img/logo.png';
}
$pageTitle = 'بوابة عقود العمل';
$active = 'hr';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.contract-builder{max-width:1200px;margin:auto}.cb-hero{background:linear-gradient(120deg,#173f73,#2d67ad);color:#fff;padding:22px 26px;border-radius:14px;margin-bottom:18px}.cb-hero h1{font-size:1.4rem;font-weight:800;margin:0}.cb-hero p{margin:7px 0 0;font-size:.82rem;opacity:.92}.cb-card{background:#fff;border:1px solid #e4e9f0;border-radius:13px;box-shadow:0 3px 12px rgba(16,24,40,.05);margin-bottom:16px;overflow:hidden}.cb-head{padding:13px 17px;border-bottom:1px solid #e7ecf2;font-weight:800;color:#173f73}.cb-body{padding:17px}.cb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.cb-field label{display:block;font-size:.76rem;font-weight:800;color:#344054;margin-bottom:5px}.cb-field input,.cb-field select,.cb-field textarea{width:100%;border:1px solid #b9c7d8;border-radius:8px;padding:9px 10px;font:inherit;font-size:.8rem;background:#fff}.cb-field textarea{min-height:78px}.cb-full{grid-column:1/-1}.cb-repeat-list{display:grid;gap:9px;margin-bottom:9px}.cb-repeat-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:start}.cb-repeat-row textarea{min-height:64px}.cb-repeat-row .cb-remove-item{white-space:nowrap;margin-top:4px}.cb-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:17px}.cb-actions .btn{font-size:.82rem;font-weight:700}.cb-note{font-size:.78rem;color:#475467;background:#f8fafc;border:1px solid #e4e9f0;padding:12px;border-radius:9px;margin-bottom:15px}.cb-error{font-size:.82rem}.cb-required{color:#b42318}@media(max-width:700px){.cb-grid{grid-template-columns:1fr}.cb-full{grid-column:auto}}
</style>
<div class="contract-builder">
  <div class="cb-hero d-flex align-items-center justify-content-between gap-3 flex-wrap"><div class="d-flex align-items-center gap-3"><img src="<?= e(APP_URL . $portalLogoPath) ?>" alt="شعار جمعية أهل الخير" style="width:68px;height:68px;object-fit:contain;background:#fff;border-radius:10px;padding:5px"><div><h1><i class="fa-solid fa-file-signature me-2"></i>بوابة عقود العمل</h1><p><?= e(trim((string)($organizationSettings['org_name_ar'] ?? '')) ?: 'جمعية أهل الخير الخيرية') ?> — إعداد عقد مناسب للمسمى الوظيفي ثم معاينته وطباعته للتوقيع.</p><?php $portalContact = array_filter([trim((string)($organizationSettings['org_address'] ?? '')), trim((string)($organizationSettings['support_phone'] ?? '')), trim((string)($organizationSettings['support_email'] ?? ''))]); if ($portalContact): ?><small><?= e(implode(' | ', $portalContact)) ?></small><?php endif; ?></div></div><a class="btn btn-light btn-sm fw-bold" href="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php"><i class="fa-solid fa-list-check me-1"></i> إدارة الأوصاف الوظيفية</a></div>
  <?php if (!empty($errorMessage)): ?><div class="alert alert-danger cb-error"><?= e($errorMessage) ?></div><?php endif; ?>
  <div class="cb-note"><strong>ملاحظة قانونية:</strong> هذا نموذج تشغيلي أولي وليس اعتماداً قانونياً. يجب مراجعته بواسطة محامٍ مؤهل في السودان قبل استخدامه رسمياً، والتحقق من توافق الشروط والسياسات مع أحكام قانون العمل السوداني السارية. لا تُدخل أي استحقاق أو مدة قانونية غير متحقق منها.</div>
  <form method="post" action="<?= e(APP_URL) ?>modules/hr/contract_builder.php" target="_blank">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate_pdf">
    <div class="cb-card"><div class="cb-head">بيانات العقد والموظف</div><div class="cb-body"><div class="cb-grid">
      <div class="cb-field cb-full"><label for="employee_id">اختيار ملف الموظف <span class="cb-required">*</span></label><select id="employee_id" name="employee_id" required><option value="">— اختر الموظف —</option><?php foreach ($employees as $employee): ?><?php $employeeTemplate = $jobDescriptionForEmployee($employee); ?><option value="<?= (int)$employee['id'] ?>" data-name="<?= e($employee['full_name']) ?>" data-basic-salary="<?= e((string)($employee['basic_salary'] ?? '')) ?>" data-national-id="<?= e($employee['national_id'] ?? '') ?>" data-phone="<?= e($employee['phone'] ?? '') ?>" data-email="<?= e($employee['email'] ?? '') ?>" data-address="<?= e($employee['address'] ?? '') ?>" data-position="<?= e($employee['position'] ?? '') ?>" data-department="<?= e($employee['department_name'] ?? '') ?>" data-purpose="<?= e((string)($employeeTemplate['role_purpose'] ?? '')) ?>" data-responsibilities="<?= e((string)($employeeTemplate['responsibilities'] ?? '')) ?>" data-qualifications="<?= e((string)($employeeTemplate['qualifications'] ?? '')) ?>" data-reporting-to="<?= e((string)($employeeTemplate['reporting_to'] ?? '')) ?>" data-description-status="<?= e((string)($employeeTemplate['description_status'] ?? 'needs_review')) ?>" data-role-terms="<?= e((string)($employeeTemplate['role_specific_terms'] ?? '')) ?>" data-system-role="<?= e((string)($employee['system_role_name'] ?? $employee['system_role_code'] ?? '')) ?>" <?= $selectedEmployeeId === (int)$employee['id'] ? 'selected' : '' ?>><?= e($employee['full_name']) ?> — <?= e($employee['employee_code']) ?><?php if (!empty($employee['system_role_name'])): ?> — <?= e($employee['system_role_name']) ?><?php endif; ?></option><?php endforeach; ?></select><small>يُحمّل الوصف المطابق للمسمى ودور حساب النظام عند توفره، ثم يستخدم الوصف العام للمسمى كخيار احتياطي. دور النظام دليل على مسارات العمل المتاحة وليس بديلاً عن تأكيد HR للمهام الفعلية.</small></div>
      <div class="cb-field"><label>رقم العقد (اختياري)</label><input name="contract_number" maxlength="80" placeholder="يُستكمل عند التسجيل"></div>
      <div class="cb-field"><label>تاريخ تحرير العقد <span class="cb-required">*</span></label><input type="date" name="issue_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="cb-field"><label>اسم الموظف <span class="cb-required">*</span></label><input id="employee_name" name="employee_name" maxlength="180" required value="<?= e($selectedEmployee['full_name'] ?? '') ?>"></div>
      <div class="cb-field"><label>الرقم الوطني / الهوية</label><input id="national_id" name="national_id" maxlength="100" value="<?= e($selectedEmployee['national_id'] ?? '') ?>"></div>
      <div class="cb-field"><label>الجنسية</label><input name="nationality" maxlength="100" value=""></div>
      <div class="cb-field"><label>رقم الهاتف</label><input id="phone" name="phone" maxlength="80" value="<?= e($selectedEmployee['phone'] ?? '') ?>"></div>
      <div class="cb-field"><label>البريد الإلكتروني</label><input id="email" name="email" type="email" maxlength="180" value="<?= e($selectedEmployee['email'] ?? '') ?>"></div>
      <div class="cb-field cb-full"><label>العنوان</label><input id="address" name="address" maxlength="500" value="<?= e($selectedEmployee['address'] ?? '') ?>"></div>
      <div class="cb-field"><label>المسمى الوظيفي (من ملف الموظف) <span class="cb-required">*</span></label><input id="position" name="position" maxlength="180" required readonly value="<?= e($selectedEmployee['position'] ?? '') ?>"></div>
      <div class="cb-field"><label>القسم</label><input id="department" name="department" maxlength="180" value="<?= e($selectedEmployee['department_name'] ?? '') ?>"></div>
      <div class="cb-field"><label for="supervisor">المشرف المباشر (من الموظفين الحاليين)</label><select id="supervisor" name="supervisor"><option value="">— اختر المشرف المباشر —</option><?php foreach ($supervisorCandidates as $candidate): ?><option data-employee-id="<?= (int)$candidate['id'] ?>" value="<?= e($candidate['full_name']) ?>" <?= (string)($_POST['supervisor'] ?? '') === (string)$candidate['full_name'] ? 'selected' : '' ?>><?= e($candidate['full_name']) ?><?php if (!empty($candidate['position'])): ?> — <?= e($candidate['position']) ?><?php endif; ?></option><?php endforeach; ?></select><small>اختر الشخص الفعلي من ملفات الموظفين الحالية. لا يُستنتج اسم المشرف من دور الصلاحيات أو من مسمى جهة الإشراف.</small></div>
      <div class="cb-field"><label>مكان العمل</label><input name="work_location" maxlength="250" value="حسب تكليف الجمعية المكتوب"></div>
    </div></div></div>
    <div class="cb-card"><div class="cb-head">المدة والدوام والأجر</div><div class="cb-body"><div class="cb-grid">
      <div class="cb-field"><label>نوع العقد</label><select name="contract_type"><option value="fixed_term">محدد المدة</option><option value="permanent">غير محدد المدة</option><option value="part_time">دوام جزئي</option><option value="temporary">مؤقت</option><option value="internship">تدريب</option><option value="other">أخرى</option></select></div>
      <div class="cb-field"><label>تاريخ بداية العقد <span class="cb-required">*</span></label><input type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="cb-field"><label>تاريخ نهاية العقد</label><input type="date" name="end_date"></div>
      <div class="cb-field"><label>فترة التجربة (المدة/النص المتفق عليه)</label><input name="probation" maxlength="250" placeholder="وفق القانون والاتفاق المكتوب"></div>
      <div class="cb-field"><label>ساعات العمل أسبوعياً</label><input type="number" name="weekly_hours" min="0" max="168" step="0.25"></div>
      <div class="cb-field"><label>أيام العمل</label><input name="working_days" maxlength="180" placeholder="تُحدد وفق الجدول المعتمد"></div>
      <div class="cb-field"><label>الراتب الأساسي (ج.س) <span class="cb-required">*</span></label><input id="basic_salary" type="number" name="basic_salary" min="0" step="0.01" required value="<?= e((string)($selectedEmployee['basic_salary'] ?? '')) ?>"></div>
      <div class="cb-field"><label>دورية الدفع</label><select name="pay_frequency"><option value="monthly">شهرياً</option><option value="weekly">أسبوعياً</option><option value="daily">يومياً</option><option value="hourly">بالساعة</option></select></div>
      <div class="cb-field cb-full"><label>البدلات</label><input name="allowances" maxlength="500" value="لا توجد إلا ما يرد صراحةً في ملحق مكتوب"></div>
      <div class="cb-field cb-full"><label>المزايا الأخرى</label><input name="benefits" maxlength="500" value="وفق السياسات المعتمدة وأحكام القانون واجب التطبيق"></div>
      <div class="cb-field"><label>الإجازة السنوية</label><input name="annual_leave" maxlength="250" value="وفق قانون العمل السوداني والسياسات المعتمدة"></div>
      <div class="cb-field"><label>الإجازة المرضية</label><input name="sick_leave" maxlength="250" value="وفق قانون العمل السوداني والسياسات المعتمدة"></div>
      <div class="cb-field cb-full"><label>مدة الإخطار</label><input name="notice_period" maxlength="250" value="وفق القانون واجب التطبيق وشروط العقد"></div>
    </div></div></div>
    <div class="cb-card"><div class="cb-head">تفاصيل إضافية</div><div class="cb-body"><div class="cb-grid">
      <div class="cb-field cb-full"><label>الغرض من الوظيفة</label><div id="purposeFields" class="cb-repeat-list" data-name="role_purpose[]"><?php foreach ($selectedJobPurposeItems as $purposeItem): ?><div class="cb-repeat-row"><textarea name="role_purpose[]" maxlength="1000" aria-label="بند من الغرض من الوظيفة"><?= e($purposeItem) ?></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endforeach; ?><?php if (!$selectedJobPurposeItems): ?><div class="cb-repeat-row"><textarea name="role_purpose[]" maxlength="1000" aria-label="بند من الغرض من الوظيفة" placeholder="الغرض أو النتيجة الأساسية التي وُجدت الوظيفة لتحقيقها"></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endif; ?></div><button type="button" class="btn btn-outline-primary btn-sm cb-add-item" data-target="purposeFields"><i class="fa-solid fa-plus me-1"></i> إضافة بند للغرض</button></div>
      <div class="cb-field cb-full"><label>المهام والمسؤوليات الأساسية</label><div id="responsibilitiesFields" class="cb-repeat-list" data-name="responsibilities[]"><?php foreach ($selectedJobResponsibilityItems as $responsibilityItem): ?><div class="cb-repeat-row"><textarea name="responsibilities[]" maxlength="1000" aria-label="مهمة أو مسؤولية أساسية"><?= e($responsibilityItem) ?></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endforeach; ?><?php if (!$selectedJobResponsibilityItems): ?><div class="cb-repeat-row"><textarea name="responsibilities[]" maxlength="1000" aria-label="مهمة أو مسؤولية أساسية" placeholder="مهمة أو مسؤولية واحدة مستمدة من العمل الفعلي"></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endif; ?></div><button type="button" class="btn btn-outline-primary btn-sm cb-add-item" data-target="responsibilitiesFields"><i class="fa-solid fa-plus me-1"></i> إضافة مسؤولية</button><small id="jobDescriptionStatus" class="d-block mt-2 <?= $selectedJobStatus === 'approved' ? 'text-success' : 'text-warning-emphasis' ?>"><?= $selectedJobStatus === 'approved' ? 'الوصف الوظيفي معتمد داخلياً.' : 'المسودة تحتاج مراجعة واعتماد الموارد البشرية، ويجب مطابقة البنود مع العمل الفعلي قبل اعتماد العقد.' ?></small></div>
      <div class="cb-field cb-full"><label>المؤهلات والخبرات المطلوبة (مسودة للمراجعة)</label><div id="qualificationsFields" class="cb-repeat-list" data-name="qualifications[]"><?php foreach ($selectedJobQualificationItems as $qualificationItem): ?><div class="cb-repeat-row"><textarea name="qualifications[]" maxlength="1000" aria-label="مؤهل أو خبرة مطلوبة"><?= e($qualificationItem) ?></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endforeach; ?><?php if (!$selectedJobQualificationItems): ?><div class="cb-repeat-row"><textarea name="qualifications[]" maxlength="1000" aria-label="مؤهل أو خبرة مطلوبة" placeholder="تُحدد المؤهلات بعد مراجعة HR والمشرف المختص"></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endif; ?></div><button type="button" class="btn btn-outline-primary btn-sm cb-add-item" data-target="qualificationsFields"><i class="fa-solid fa-plus me-1"></i> إضافة مؤهل أو خبرة</button></div>
      <div class="cb-field cb-full"><label for="reporting_to">جهة الإشراف الوظيفي (وفق فهرس الوصف)</label><input id="reporting_to" name="reporting_to" maxlength="180" value="<?= e($selectedJobReportingTo) ?>" placeholder="تُراجع وتؤكد من الموارد البشرية"></div>
      <div class="cb-field cb-full"><label>الشروط الخاصة المتفق عليها / البنود المرتبطة بالمسمى</label><div id="specialTermsFields" class="cb-repeat-list" data-name="special_terms[]"><?php foreach (preg_split('/\\R/u', trim((string)($selectedJobDescription['role_specific_terms'] ?? ''))) ?: [] as $termItem): ?><?php if (trim($termItem) !== ''): ?><div class="cb-repeat-row"><textarea name="special_terms[]" maxlength="5000" aria-label="شرط خاص أو بند مرتبط بالمسمى"><?= e(trim($termItem)) ?></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endif; ?><?php endforeach; ?><?php if (trim((string)($selectedJobDescription['role_specific_terms'] ?? '')) === ''): ?><div class="cb-repeat-row"><textarea name="special_terms[]" maxlength="5000" aria-label="شرط خاص أو بند مرتبط بالمسمى" placeholder="أدخل شرطاً خاصاً متفقاً عليه كتابةً (اختياري)"></textarea><button type="button" class="btn btn-outline-danger btn-sm cb-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i> حذف</button></div><?php endif; ?></div><button type="button" class="btn btn-outline-primary btn-sm cb-add-item" data-target="specialTermsFields"><i class="fa-solid fa-plus me-1"></i> إضافة شرط أو بند</button><small class="d-block mt-2">يُحمّل النص من فهرس المسمى الوظيفي عند توفره، ويمكن تعديله لهذا العقد فقط.</small></div>
    </div><div class="cb-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-pdf me-1"></i> عرض العقد بصيغة PDF</button><a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>modules/hr/contracts.php<?= $selectedEmployeeId ? '?employee_id=' . $selectedEmployeeId . '&view=add' : '' ?>">العودة إلى العقود والرواتب</a></div></div></div>
  </form>
</div>
<script>
(function(){
  const select=document.getElementById('employee_id');
  if(!select)return;
  function setRepeatableItems(containerId,name,text){
    const container=document.getElementById(containerId);
    if(!container)return;
    const items=(text||'').split(/\r?\n/).map(item=>item.trim().replace(/^(?:[•*\-–—]|\d+[.)])\s*/u,'')).filter(Boolean);
    container.innerHTML='';
    (items.length?items:['']).forEach(item=>addRepeatableItem(container,name,item));
  }
  function addRepeatableItem(container,name,value=''){
    const row=document.createElement('div');row.className='cb-repeat-row';
    const textarea=document.createElement('textarea');textarea.name=name;textarea.maxLength=5000;textarea.value=value;textarea.setAttribute('aria-label',name==='role_purpose[]'?'بند من الغرض من الوظيفة':name==='responsibilities[]'?'مهمة أو مسؤولية أساسية':name==='qualifications[]'?'مؤهل أو خبرة مطلوبة':'شرط خاص أو بند مرتبط بالمسمى');
    const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm cb-remove-item';remove.innerHTML='<i class="fa-solid fa-trash"></i> حذف';
    row.append(textarea,remove);container.appendChild(row);
  }
  document.querySelectorAll('.cb-add-item').forEach(button=>button.addEventListener('click',()=>{
    const container=document.getElementById(button.dataset.target);if(container)addRepeatableItem(container,container.dataset.name);
  }));
  document.addEventListener('click',event=>{
    const button=event.target.closest('.cb-remove-item');if(!button)return;
    const container=button.closest('.cb-repeat-list');const row=button.closest('.cb-repeat-row');if(!container||!row)return;
    if(container.querySelectorAll('.cb-repeat-row').length>1)row.remove();else{const textarea=row.querySelector('textarea');if(textarea)textarea.value='';}
  });
  const mapping={employee_name:'name',national_id:'nationalId',phone:'phone',email:'email',address:'address',position:'position',department:'department',basic_salary:'basicSalary'};
  const supervisorSelect=document.getElementById('supervisor');
  function refreshSupervisorOptions(){
    if(!supervisorSelect)return;
    Array.from(supervisorSelect.options).forEach(option=>{
      const isSelectedEmployee=option.dataset.employeeId===select.value;
      option.disabled=isSelectedEmployee;
      if(isSelectedEmployee&&option.selected)supervisorSelect.value='';
    });
  }
  refreshSupervisorOptions();
  select.addEventListener('change',function(){refreshSupervisorOptions();
    const option=this.options[this.selectedIndex];
    if(!option||!option.value){
      setRepeatableItems('purposeFields','role_purpose[]','');
      setRepeatableItems('responsibilitiesFields','responsibilities[]','');
      setRepeatableItems('qualificationsFields','qualifications[]','');
      const reportingTo=document.getElementById('reporting_to');if(reportingTo)reportingTo.value='';
      setRepeatableItems('specialTermsFields','special_terms[]','');
      const status=document.getElementById('jobDescriptionStatus');
      if(status){status.textContent='اختر موظفاً لتحميل بيانات الوصف الوظيفي.';status.className='text-warning-emphasis';}
      return;
    }
    Object.entries(mapping).forEach(([field,key])=>{
      const input=document.getElementById(field);
      const value=option.dataset[key];
      if(input&&value!==undefined)input.value=value;
    });
    setRepeatableItems('purposeFields','role_purpose[]',option.dataset.purpose||'');
    setRepeatableItems('responsibilitiesFields','responsibilities[]',option.dataset.responsibilities||'');
    setRepeatableItems('qualificationsFields','qualifications[]',option.dataset.qualifications||'');
    const reportingTo=document.getElementById('reporting_to');if(reportingTo)reportingTo.value=option.dataset.reportingTo||'';
    setRepeatableItems('specialTermsFields','special_terms[]',option.dataset.roleTerms||'');
    const status=document.getElementById('jobDescriptionStatus');
    if(status){
      const approved=option.dataset.descriptionStatus==='approved';
      status.textContent=approved?'الوصف الوظيفي معتمد داخلياً.':'تنبيه: لا يوجد وصف معتمد لهذا المسمى؛ راجع فهرس الأوصاف الوظيفية قبل اعتماد العقد.';
      status.className=approved?'text-success':'text-warning-emphasis';
    }
  });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
