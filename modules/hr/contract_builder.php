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
    "SELECT e.id, e.employee_code, e.full_name, e.national_id, e.phone, e.email,
            e.address, e.position, e.basic_salary, d.name_ar AS department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     ORDER BY e.full_name",
    []
);
$jobDescriptions = dbFetchAll(
    "SELECT id, position_title, role_purpose, responsibilities, qualifications, reporting_to, role_specific_terms, description_status
     FROM hr_job_description_templates
     WHERE is_active = 1
     ORDER BY position_title",
    []
);
$jobDescriptionByPosition = [];
foreach ($jobDescriptions as $description) {
    $jobDescriptionByPosition[trim((string)$description['position_title'])] = $description;
}
$organizationSettings = [];
foreach (dbFetchAll(
    "SELECT setting_key, setting_value FROM settings
     WHERE setting_key IN ('org_name_ar','org_name_en','org_address','support_phone','support_email','site_logo')",
    []
) as $setting) {
    $organizationSettings[(string)$setting['setting_key']] = (string)$setting['setting_value'];
}
$selectedEmployeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);

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
        if ($value !== '') $parts[] = $label . ': ' . $value;
    }
    return implode("\n\n", $parts);
}

function akContractValue(array $data, string $key, string $default = ''): string
{
    return trim((string)($data[$key] ?? $default));
}

function akContractEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        $employeeExists = dbFetchOne('SELECT id, position FROM employees WHERE id = ? LIMIT 1', [(int)$employeeId]);
        if (!$employeeExists) {
            throw new InvalidArgumentException('الموظف المحدد غير موجود.');
        }
        $employeePosition = trim((string)($employeeExists['position'] ?? ''));
        $jobTemplate = $employeePosition !== ''
            ? ($jobDescriptionByPosition[$employeePosition] ?? null)
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
            'duties' => akContractValue($_POST, 'duties'),
            'job_description_status' => $jobDescriptionStatus,
            'special_terms' => akContractValue($_POST, 'special_terms'),
        ];

        $fieldLimits = [
            'contract_number' => 80, 'employee_name' => 180, 'national_id' => 100,
            'nationality' => 100, 'address' => 500, 'phone' => 80, 'email' => 180,
            'position' => 180, 'department' => 180, 'supervisor' => 180,
            'work_location' => 250, 'probation' => 250, 'working_days' => 180,
            'allowances' => 500, 'benefits' => 500, 'annual_leave' => 250,
            'sick_leave' => 250, 'notice_period' => 250, 'duties' => 5000,
            'special_terms' => 5000, 'weekly_hours' => 12,
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
        if ($data['duties'] === '') {
            $data['duties'] = akContractTemplateText($jobTemplate);
        }
        if (strlen($data['duties']) > 5000) {
            throw new InvalidArgumentException('الوصف الوظيفي أطول من الحد المسموح به للعقد؛ اختصره من فهرس الأوصاف أو عدّله قبل التوليد.');
        }
        $dutiesText = $data['duties'] !== '' ? nl2br($v('duties')) : 'تنفيذ المهام المرتبطة بالوظيفة والوصف الوظيفي والتعليمات المشروعة ذات الصلة.';
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
        $organizationContactHtml = $contactParts
            ? '<p class="meta" style="text-align:center;">' . akContractEscape(implode(' | ', $contactParts)) . '</p>'
            : '';

        require_once APP_DIR . '/TCPDF/tcpdf.php';
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor(APP_NAME_AR);
        $pdf->SetTitle('عقد عمل - ' . $data['employee_name']);
        $pdf->SetSubject('عقد عمل');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterMargin(10);
        $pdf->SetMargins(16, 15, 16);
        $pdf->SetAutoPageBreak(true, 17);
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 11);
        $pdf->AddPage();

        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 168, 12, 25, 25);
        }
        $pdf->SetY(15);
        $pdf->SetFont('aealarabiya', 'B', 17);
        $pdf->SetTextColor(23, 63, 115);
        $pdf->Cell(0, 9, $organizationName, 0, 1, 'C');
        $pdf->SetFont('aealarabiya', 'B', 14);
        $pdf->SetTextColor(35, 45, 60);
        $pdf->Cell(0, 8, 'عقد عمل', 0, 1, 'C');
        $pdf->SetDrawColor(190, 151, 66);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(16, 43, 194, 43);
        $pdf->Ln(7);

        $html = '
        <style>
            body { font-family: aealarabiya; color: #263445; font-size: 10.5pt; line-height: 1.55; }
            h2 { color: #173f73; font-size: 12pt; font-weight: bold; background-color: #f0f4f9; padding: 5px; }
            .meta { color: #475467; font-size: 9pt; }
            .rule { border: 1px solid #d6dde7; padding: 6px; }
            .clause { margin-bottom: 5px; }
        </style>
        ' . $organizationContactHtml . '
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
$selectedJobDescription = $jobDescriptionByPosition[$selectedPosition] ?? null;
$selectedJobDuties = akContractTemplateText($selectedJobDescription);
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
.contract-builder{max-width:1200px;margin:auto}.cb-hero{background:linear-gradient(120deg,#173f73,#2d67ad);color:#fff;padding:22px 26px;border-radius:14px;margin-bottom:18px}.cb-hero h1{font-size:1.4rem;font-weight:800;margin:0}.cb-hero p{margin:7px 0 0;font-size:.82rem;opacity:.92}.cb-card{background:#fff;border:1px solid #e4e9f0;border-radius:13px;box-shadow:0 3px 12px rgba(16,24,40,.05);margin-bottom:16px;overflow:hidden}.cb-head{padding:13px 17px;border-bottom:1px solid #e7ecf2;font-weight:800;color:#173f73}.cb-body{padding:17px}.cb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.cb-field label{display:block;font-size:.76rem;font-weight:800;color:#344054;margin-bottom:5px}.cb-field input,.cb-field select,.cb-field textarea{width:100%;border:1px solid #b9c7d8;border-radius:8px;padding:9px 10px;font:inherit;font-size:.8rem;background:#fff}.cb-field textarea{min-height:78px}.cb-full{grid-column:1/-1}.cb-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:17px}.cb-actions .btn{font-size:.82rem;font-weight:700}.cb-note{font-size:.78rem;color:#475467;background:#f8fafc;border:1px solid #e4e9f0;padding:12px;border-radius:9px;margin-bottom:15px}.cb-error{font-size:.82rem}.cb-required{color:#b42318}@media(max-width:700px){.cb-grid{grid-template-columns:1fr}.cb-full{grid-column:auto}}
</style>
<div class="contract-builder">
  <div class="cb-hero d-flex align-items-center justify-content-between gap-3 flex-wrap"><div class="d-flex align-items-center gap-3"><img src="<?= e(APP_URL . $portalLogoPath) ?>" alt="شعار جمعية أهل الخير" style="width:68px;height:68px;object-fit:contain;background:#fff;border-radius:10px;padding:5px"><div><h1><i class="fa-solid fa-file-signature me-2"></i>بوابة عقود العمل</h1><p><?= e(trim((string)($organizationSettings['org_name_ar'] ?? '')) ?: 'جمعية أهل الخير الخيرية') ?> — إعداد عقد مناسب للمسمى الوظيفي ثم معاينته وطباعته للتوقيع.</p><?php $portalContact = array_filter([trim((string)($organizationSettings['org_address'] ?? '')), trim((string)($organizationSettings['support_phone'] ?? '')), trim((string)($organizationSettings['support_email'] ?? ''))]); if ($portalContact): ?><small><?= e(implode(' | ', $portalContact)) ?></small><?php endif; ?></div></div><a class="btn btn-light btn-sm fw-bold" href="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php"><i class="fa-solid fa-list-check me-1"></i> إدارة الأوصاف الوظيفية</a></div>
  <?php if (!empty($errorMessage)): ?><div class="alert alert-danger cb-error"><?= e($errorMessage) ?></div><?php endif; ?>
  <div class="cb-note"><strong>ملاحظة قانونية:</strong> هذا نموذج تشغيلي أولي وليس اعتماداً قانونياً. يجب مراجعته بواسطة محامٍ مؤهل في السودان قبل استخدامه رسمياً، والتحقق من توافق الشروط والسياسات مع أحكام قانون العمل السوداني السارية. لا تُدخل أي استحقاق أو مدة قانونية غير متحقق منها.</div>
  <form method="post" action="<?= e(APP_URL) ?>modules/hr/contract_builder.php" target="_blank">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate_pdf">
    <div class="cb-card"><div class="cb-head">بيانات العقد والموظف</div><div class="cb-body"><div class="cb-grid">
      <div class="cb-field cb-full"><label for="employee_id">اختيار ملف الموظف <span class="cb-required">*</span></label><select id="employee_id" name="employee_id" required><option value="">— اختر الموظف —</option><?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>" data-name="<?= e($employee['full_name']) ?>" data-basic-salary="<?= e((string)($employee['basic_salary'] ?? '')) ?>" data-national-id="<?= e($employee['national_id'] ?? '') ?>" data-phone="<?= e($employee['phone'] ?? '') ?>" data-email="<?= e($employee['email'] ?? '') ?>" data-address="<?= e($employee['address'] ?? '') ?>" data-position="<?= e($employee['position'] ?? '') ?>" data-department="<?= e($employee['department_name'] ?? '') ?>" data-duties="<?= e(akContractTemplateText($jobDescriptionByPosition[trim((string)($employee['position'] ?? ''))] ?? null)) ?>" data-description-status="<?= e((string)(($jobDescriptionByPosition[trim((string)($employee['position'] ?? ''))]['description_status'] ?? 'needs_review'))) ?>" data-role-terms="<?= e((string)(($jobDescriptionByPosition[trim((string)($employee['position'] ?? ''))]['role_specific_terms'] ?? ''))) ?>" <?= $selectedEmployeeId === (int)$employee['id'] ? 'selected' : '' ?>><?= e($employee['full_name']) ?> — <?= e($employee['employee_code']) ?></option><?php endforeach; ?></select><small>اختيار الموظف يملأ البيانات المتاحة تلقائياً؛ ويمكنك تعديل الحقول قبل إنشاء PDF.</small></div>
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
      <div class="cb-field"><label>المشرف المباشر</label><input name="supervisor" maxlength="180"></div>
      <div class="cb-field"><label>مكان العمل</label><input name="work_location" maxlength="250" value="حسب تكليف الجمعية المكتوب"></div>
    </div></div></div>
    <div class="cb-card"><div class="cb-head">المدة والدوام والأجر</div><div class="cb-body"><div class="cb-grid">
      <div class="cb-field"><label>نوع العقد</label><select name="contract_type"><option value="fixed_term">محدد المدة</option><option value="permanent">غير محدد المدة</option><option value="part_time">دوام جزئي</option><option value="temporary">مؤقت</option><option value="internship">تدريب</option><option value="other">أخرى</option></select></div>
      <div class="cb-field"><label>تاريخ بداية العقد <span class="cb-required">*</span></label><input type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="cb-field"><label>تاريخ نهاية العقد</label><input type="date" name="end_date"></div>
      <div class="cb-field"><label>فترة التجربة (المدة/النص المتفق عليه)</label><input name="probation" maxlength="250" placeholder="وفق القانون والاتفاق المكتوب"></div>
      <div class="cb-field"><label>ساعات العمل أسبوعياً</label><input type="number" name="weekly_hours" min="0" max="168" step="0.25"></div>
      <div class="cb-field"><label>أيام العمل</label><input name="working_days" maxlength="180" placeholder="تُحدد وفق الجدول المعتمد"></div>
      <div class="cb-field"><label>الراتب الأساسي (ج.س) <span class="cb-required">*</span></label><input type="number" name="basic_salary" min="0" step="0.01" required value="<?= e((string)($selectedEmployee['basic_salary'] ?? '')) ?>"></div>
      <div class="cb-field"><label>دورية الدفع</label><select name="pay_frequency"><option value="monthly">شهرياً</option><option value="weekly">أسبوعياً</option><option value="daily">يومياً</option><option value="hourly">بالساعة</option></select></div>
      <div class="cb-field cb-full"><label>البدلات</label><input name="allowances" maxlength="500" value="لا توجد إلا ما يرد صراحةً في ملحق مكتوب"></div>
      <div class="cb-field cb-full"><label>المزايا الأخرى</label><input name="benefits" maxlength="500" value="وفق السياسات المعتمدة وأحكام القانون واجب التطبيق"></div>
      <div class="cb-field"><label>الإجازة السنوية</label><input name="annual_leave" maxlength="250" value="وفق قانون العمل السوداني والسياسات المعتمدة"></div>
      <div class="cb-field"><label>الإجازة المرضية</label><input name="sick_leave" maxlength="250" value="وفق قانون العمل السوداني والسياسات المعتمدة"></div>
      <div class="cb-field cb-full"><label>مدة الإخطار</label><input name="notice_period" maxlength="250" value="وفق القانون واجب التطبيق وشروط العقد"></div>
    </div></div></div>
    <div class="cb-card"><div class="cb-head">تفاصيل إضافية</div><div class="cb-body"><div class="cb-grid">
      <div class="cb-field cb-full"><label>المهام والمسؤوليات والوصف الوظيفي</label><textarea id="duties" name="duties" maxlength="5000" placeholder="يُحمّل الوصف من فهرس المسمى الوظيفي، ويمكن مراجعته وتعديله للعقد الحالي."><?= e($selectedJobDuties) ?></textarea><small id="jobDescriptionStatus" class="<?= $selectedJobStatus === 'approved' ? 'text-success' : 'text-warning-emphasis' ?>"><?= $selectedJobStatus === 'approved' ? 'الوصف الوظيفي معتمد داخلياً.' : 'تنبيه: لا يوجد وصف معتمد لهذا المسمى؛ راجع فهرس الأوصاف الوظيفية قبل اعتماد العقد.' ?></small></div>
      <div class="cb-field cb-full"><label>الشروط الخاصة المتفق عليها / البنود المرتبطة بالمسمى</label><textarea id="special_terms" name="special_terms" maxlength="5000" placeholder="أي شروط إضافية متفق عليها كتابةً، دون مخالفة القانون."><?= e($selectedJobDescription['role_specific_terms'] ?? '') ?></textarea><small>يُحمّل النص من فهرس المسمى الوظيفي عند توفره، ويمكن تعديله لهذا العقد فقط.</small></div>
    </div><div class="cb-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-pdf me-1"></i> عرض العقد بصيغة PDF</button><a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>modules/hr/contracts.php<?= $selectedEmployeeId ? '?employee_id=' . $selectedEmployeeId . '&view=add' : '' ?>">العودة إلى العقود والرواتب</a></div></div></div>
  </form>
</div>
<script>
(function(){
  const select=document.getElementById('employee_id');
  if(!select)return;
  const mapping={employee_name:'name',national_id:'nationalId',phone:'phone',email:'email',address:'address',position:'position',department:'department',basic_salary:'basicSalary'};
  select.addEventListener('change',function(){
    const option=this.options[this.selectedIndex];
    if(!option||!option.value)return;
    Object.entries(mapping).forEach(([field,key])=>{
      const input=document.getElementById(field);
      const value=option.dataset[key];
      if(input&&value!==undefined)input.value=value;
    });
    const duties=document.getElementById('duties');
    if(duties)duties.value=option.dataset.duties||'';
    const specialTerms=document.getElementById('special_terms');
    if(specialTerms)specialTerms.value=option.dataset.roleTerms||'';
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
