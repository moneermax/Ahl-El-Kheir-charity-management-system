<?php
// modules/projects/form.php
// Create a project with initial budget OR edit the general-info section.
//
// Financial rule for NEW projects:
// If a suggested budget is provided, the total of the initial budget
// lines MUST equal the suggested budget exactly.
//
// This is enforced SERVER-SIDE. JavaScript is only an additional UX aid.

require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';

Session::start();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$project = $id ? akp_get_project($id) : null;

if ($id && !$project) {
    flash('error', 'المشروع غير موجود.');
    redirect('modules/projects/index.php');
}

if (!$id && !akp_can_create_project()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

if ($id && !akp_can_edit_section('general', $id)) {
    flash(
        'error',
        'تعديل البيانات الأساسية متاح للمدير العام ونائبه فقط، وبعد إغلاق المشروع للمدير العام فقط.'
    );

    redirect('modules/projects/view.php?id=' . $id);
}

$returnQuery=trim((string)($_GET['return']??$_POST['return']??'')); $backUrl=APP_URL.'modules/projects/index.php'; if($returnQuery!==''){$backUrl.='?'.ltrim(rawurldecode($returnQuery),'?');}
$pageTitle = $id ? 'تعديل البيانات الأساسية للمشروع' : 'مشروع جديد';
$active = 'projects';

$currencies = dbFetchAll(
    "SELECT code, name_ar
     FROM currencies
     WHERE is_active = 1
     ORDER BY code"
);

$supervisors = dbFetchAll(
    "SELECT u.id, u.full_name, u.username
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE r.code = 'project_supervisor'
       AND u.is_active = 1
     ORDER BY u.full_name"
);

$errors = [];

/*
|--------------------------------------------------------------------------
| Helper: convert a monetary value into integer cents
|--------------------------------------------------------------------------
| Using integer cents avoids normal floating-point comparison problems.
|
| Example:
| 500000     -> 50000000
| 500000.00  -> 50000000
| 0.01       -> 1
|--------------------------------------------------------------------------
*/
function akp_money_to_cents($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    /*
     * Accept only normal decimal monetary input.
     * We intentionally reject strange values such as:
     * 1e5
     * abc
     * 1,000
     *
     * This keeps financial input predictable.
     */
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        return false;
    }

    $parts = explode('.', $value, 2);

    $whole = $parts[0];
    $decimal = $parts[1] ?? '00';

    if (strlen($decimal) === 1) {
        $decimal .= '0';
    }

    $decimal = substr($decimal, 0, 2);

    return ((int)$whole * 100) + (int)$decimal;
}


/*
|--------------------------------------------------------------------------
| Form input
|--------------------------------------------------------------------------
*/

$input = [
    'name' => $project['name'] ?? '',
    'project_code' => $project['project_code'] ?? '',
    'project_type' => $project['project_type'] ?? '',
    'description' => $project['description'] ?? '',
    'objectives' => '',
    'justification' => '',
    'expected_outcomes' => '',
    'target_beneficiary_description' => '',
    'implementing_partner' => '',
    'donor_restrictions' => '',
    'procurement_method' => '',
    'sustainability_plan' => '',
    'risk_mitigation' => '',
    'government_requirements' => '',
    'contact_person' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'notes' => '',
    'target_amount' => (string)($project['target_amount'] ?? ''),
    'currency_code' => $project['currency_code'] ?? 'SDG',
    'start_date' => $project['start_date'] ?? '',
    'end_date' => $project['end_date'] ?? '',
    'location' => $project['location'] ?? '',
    'city' => $project['city'] ?? '',
    'district' => $project['district'] ?? '',
    'total_beneficiaries' => (string)($project['total_beneficiaries'] ?? ''),
    'supervisor_user_id' => '',
];


/*
|--------------------------------------------------------------------------
| Existing project data
|--------------------------------------------------------------------------
*/

if ($id) {

    $currentSupervisor = dbFetchOne(
        'SELECT supervisor_user_id
         FROM project_supervisor_assignments
         WHERE project_id = ?
           AND ended_at IS NULL
         ORDER BY id DESC
         LIMIT 1',
        [$id]
    );

    $input['supervisor_user_id'] =
        (string)($currentSupervisor['supervisor_user_id'] ?? '');

    $details = dbFetchOne(
        'SELECT *
         FROM project_details
         WHERE project_id = ?',
        [$id]
    );

    if ($details) {
        foreach (array_keys($input) as $field) {
            if (array_key_exists($field, $details)) {
                $input[$field] = (string)($details[$field] ?? '');
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {

        $errors[] =
            'انتهت صلاحية الجلسة. يرجى إعادة تحميل الصفحة.';

    } else {

        /*
         * Read normal form fields.
         */
        foreach (array_keys($input) as $field) {
            $input[$field] = trim(
                (string)($_POST[$field] ?? '')
            );
        }


        /*
         * Basic validation.
         */

        if ($input['name'] === '') {
            $errors[] = 'اسم المشروع مطلوب.';
        }


        /*
         * Validate suggested budget.
         */
        $targetCents = null;

        if ($input['target_amount'] !== '') {

            $targetCents =
                akp_money_to_cents($input['target_amount']);

            if ($targetCents === false) {

                $errors[] =
                    'الميزانية التقديرية يجب أن تكون رقماً صحيحاً أو رقماً يحتوي على منزلتين عشريتين كحد أقصى.';

            } elseif ($targetCents < 0) {

                $errors[] =
                    'الميزانية التقديرية لا يمكن أن تكون سالبة.';
            }
        }


        /*
         * Dates.
         */
        if (
            $input['start_date'] !== '' &&
            $input['end_date'] !== '' &&
            $input['end_date'] < $input['start_date']
        ) {
            $errors[] =
                'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.';
        }


        /*
         * Currency.
         */
        if ($input['currency_code'] === '') {
            $input['currency_code'] = 'SDG';
        }


        /*
         * Email.
         */
        if (
            $input['contact_email'] !== '' &&
            !filter_var(
                $input['contact_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $errors[] = 'البريد الإلكتروني غير صالح.';
        }


        /*
         * Project code.
         *
         * Existing codes may be edited, but a non-empty code must remain unique.
         * The database currently provides only a normal index, so enforce this
         * rule server-side rather than changing the schema blindly.
         */
        if ($input['project_code'] !== '') {
            $codeExists = dbFetchOne(
                'SELECT id
                 FROM other_projects
                 WHERE project_code = ?
                   AND id <> ?
                 LIMIT 1',
                [$input['project_code'], $id]
            );

            if ($codeExists) {
                $errors[] = 'كود المشروع مستخدم بالفعل لمشروع آخر.';
            }
        }


        /*
         * Supervisor.
         */
        if (
            $input['supervisor_user_id'] === '' &&
            !$id
        ) {
            $errors[] =
                'مشرف المشروع الأساسي مطلوب.';
        }


        if ($input['supervisor_user_id'] !== '') {

            $validSupervisor = dbFetchOne(
                "SELECT u.id
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ?
                   AND r.code = 'project_supervisor'
                   AND u.is_active = 1",
                [(int)$input['supervisor_user_id']]
            );

            if (!$validSupervisor) {

                $errors[] =
                    'مشرف المشروع غير موجود أو غير نشط.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | NEW PROJECT INITIAL BUDGET VALIDATION
        |--------------------------------------------------------------------------
        |
        | This is the important new protection.
        |
        | We do NOT silently discard invalid budget lines.
        |
        | Every submitted line is validated.
        |--------------------------------------------------------------------------
        */

        $budgetLines = [];
        $totalBudgetCents = 0;

        if (!$id) {

            $submittedBudgetLines =
                $_POST['budget_lines'] ?? [];

            if (!is_array($submittedBudgetLines)) {

                $errors[] =
                    'بيانات بنود الميزانية غير صالحة.';

                $submittedBudgetLines = [];
            }


            /*
             * Remove only completely empty rows.
             *
             * A row that contains some data but is incomplete
             * is treated as an error.
             */
            foreach ($submittedBudgetLines as $index => $line) {

                if (!is_array($line)) {
                    $errors[] =
                        'أحد بنود الميزانية يحتوي على بيانات غير صالحة.';
                    continue;
                }

                $category =
                    trim((string)($line['category'] ?? ''));

                $description =
                    trim((string)($line['description'] ?? ''));

                $amountRaw =
                    trim((string)($line['amount'] ?? ''));


                /*
                 * Completely empty row.
                 */
                if (
                    $category === '' &&
                    $description === '' &&
                    $amountRaw === ''
                ) {
                    continue;
                }


                /*
                 * Category.
                 */
                if ($category === '') {

                    $errors[] =
                        'بند الميزانية رقم ' .
                        ((int)$index + 1) .
                        ': فئة البند مطلوبة.';
                }


                /*
                 * Description.
                 */
                if ($description === '') {

                    $errors[] =
                        'بند الميزانية رقم ' .
                        ((int)$index + 1) .
                        ': وصف البند مطلوب.';
                }


                /*
                 * Amount.
                 */
                if ($amountRaw === '') {

                    $errors[] =
                        'بند الميزانية رقم ' .
                        ((int)$index + 1) .
                        ': المبلغ مطلوب.';

                    continue;
                }


                $amountCents =
                    akp_money_to_cents($amountRaw);

                if ($amountCents === false) {

                    $errors[] =
                        'بند الميزانية رقم ' .
                        ((int)$index + 1) .
                        ': المبلغ غير صالح. استخدم رقماً صحيحاً أو رقماً يحتوي على منزلتين عشريتين كحد أقصى.';

                    continue;
                }


                if ($amountCents <= 0) {

                    $errors[] =
                        'بند الميزانية رقم ' .
                        ((int)$index + 1) .
                        ': المبلغ يجب أن يكون أكبر من صفر.';

                    continue;
                }


                /*
                 * Store validated line.
                 */
                $budgetLines[] = [
                    'category' => $category,
                    'description' => $description,
                    'amount_cents' => $amountCents,
                    'amount' => number_format(
                        $amountCents / 100,
                        2,
                        '.',
                        ''
                    ),
                ];

                $totalBudgetCents += $amountCents;
            }


            /*
             |--------------------------------------------------------------------------
             | Suggested budget is required when initial budget lines exist.
             |--------------------------------------------------------------------------
             */
            if (
                !empty($budgetLines) &&
                $targetCents === null
            ) {

                $errors[] =
                    'يجب إدخال الميزانية التقديرية للمشروع قبل إضافة بنود الميزانية.';
            }


            /*
             |--------------------------------------------------------------------------
             | THE CRITICAL RULE
             |--------------------------------------------------------------------------
             |
             | Initial budget lines MUST equal suggested budget exactly.
             |--------------------------------------------------------------------------
             */
            if (
                $targetCents !== null &&
                !empty($budgetLines) &&
                $totalBudgetCents !== $targetCents
            ) {

                $differenceCents =
                    $totalBudgetCents - $targetCents;

                $difference =
                    number_format(
                        abs($differenceCents) / 100,
                        2,
                        '.',
                        ','
                    );

                $targetDisplay =
                    number_format(
                        $targetCents / 100,
                        2,
                        '.',
                        ','
                    );

                $totalDisplay =
                    number_format(
                        $totalBudgetCents / 100,
                        2,
                        '.',
                        ','
                    );


                if ($differenceCents > 0) {

                    $errors[] =
                        'إجمالي بنود الميزانية (' .
                        $totalDisplay .
                        ') يتجاوز الميزانية التقديرية (' .
                        $targetDisplay .
                        ') بمبلغ ' .
                        $difference .
                        '. يجب أن يساوي إجمالي البنود الميزانية التقديرية تماماً.';

                } else {

                    $errors[] =
                        'إجمالي بنود الميزانية (' .
                        $totalDisplay .
                        ') أقل من الميزانية التقديرية (' .
                        $targetDisplay .
                        ') بمبلغ ' .
                        $difference .
                        '. يجب أن يساوي إجمالي البنود الميزانية التقديرية تماماً.';
                }
            }


            /*
             |--------------------------------------------------------------------------
             | If a suggested budget exists but there are no valid budget lines,
             | reject the submission.
             |--------------------------------------------------------------------------
             */
            if (
                $targetCents !== null &&
                empty($budgetLines)
            ) {

                $errors[] =
                    'تم إدخال ميزانية تقديرية ولكن لم تتم إضافة أي بنود ميزانية صحيحة. يجب أن تكون هناك بنود مجموعها يساوي الميزانية التقديرية.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Save only if EVERYTHING passed validation.
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $projectCreateTransaction = !$id;
            if ($projectCreateTransaction) {
                db()->beginTransaction();
            }

            try {
                /*
                 * Convert values.
                 */
            $target =
                $targetCents !== null
                    ? ($targetCents / 100)
                    : null;

            $beneficiaries =
                $input['total_beneficiaries'] !== ''
                    ? (int)$input['total_beneficiaries']
                    : null;


            /*
             |--------------------------------------------------------------------------
             | EDIT EXISTING PROJECT
             |--------------------------------------------------------------------------
             */

            if ($id) {

                $old = akp_get_project($id);

                dbExecute(
                    "UPDATE other_projects
                     SET
                        name = ?,
                        project_code = ?,
                        description = ?,
                        target_amount = ?,
                        currency_code = ?,
                        start_date = ?,
                        end_date = ?,
                        project_type = ?,
                        location = ?,
                        city = ?,
                        district = ?,
                        total_beneficiaries = ?,
                        updated_by = ?
                     WHERE id = ?",
                    [
                        $input['name'],

                        $input['project_code'] !== ''
                            ? $input['project_code']
                            : null,

                        $input['description'] !== ''
                            ? $input['description']
                            : null,

                        $target,

                        $input['currency_code'],

                        $input['start_date'] !== ''
                            ? $input['start_date']
                            : null,

                        $input['end_date'] !== ''
                            ? $input['end_date']
                            : null,

                        $input['project_type'] !== ''
                            ? $input['project_type']
                            : null,

                        $input['location'] !== ''
                            ? $input['location']
                            : null,

                        $input['city'] !== ''
                            ? $input['city']
                            : null,

                        $input['district'] !== ''
                            ? $input['district']
                            : null,

                        $beneficiaries,

                        akp_user_id(),

                        $id
                    ]
                );

                $projectId = $id;

                $action = 'UPDATE_GENERAL';

                akp_audit(
                    $action,
                    'other_projects',
                    $projectId,
                    [
                        'name' =>
                            $old['name'],

                        'status' =>
                            $old['lifecycle_status']
                            ?? $old['status']
                    ],
                    [
                        'name' =>
                            $input['name']
                    ]
                );

            }

            /*
             |--------------------------------------------------------------------------
             | CREATE NEW PROJECT
             |--------------------------------------------------------------------------
             */

            else {

                /*
                 * Generate the next project code from existing PRJ-#### values.
                 * COUNT(*) is intentionally not used because deletions and gaps
                 * can make the row count unrelated to the next project number.
                 */
                $nextNumberRow = dbFetchOne(
                    "SELECT MAX(CAST(SUBSTRING(project_code, 5) AS UNSIGNED)) AS max_number
                     FROM other_projects
                     WHERE project_code REGEXP '^PRJ-[0-9]+


                dbExecute(
                    "INSERT INTO other_projects
                    (
                        name,
                        project_code,
                        description,
                        target_amount,
                        currency_code,
                        start_date,
                        end_date,
                        status,
                        created_by,
                        project_type,
                        location,
                        city,
                        district,
                        total_beneficiaries
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $input['name'],

                        $code,

                        $input['description'] !== ''
                            ? $input['description']
                            : null,

                        $target,

                        $input['currency_code'],

                        $input['start_date'] !== ''
                            ? $input['start_date']
                            : null,

                        $input['end_date'] !== ''
                            ? $input['end_date']
                            : null,

                        'planned',

                        akp_user_id(),

                        $input['project_type'] !== ''
                            ? $input['project_type']
                            : null,

                        $input['location'] !== ''
                            ? $input['location']
                            : null,

                        $input['city'] !== ''
                            ? $input['city']
                            : null,

                        $input['district'] !== ''
                            ? $input['district']
                            : null,

                        $beneficiaries
                    ]
                );


                $projectId =
                    (int)(
                        dbFetchOne(
                            'SELECT LAST_INSERT_ID() AS id'
                        )['id'] ?? 0
                    );


                $action = 'CREATE';


                /*
                 * Initial lifecycle budget.
                 *
                 * Because validation above guarantees that
                 * initial budget lines equal the suggested budget,
                 * this value remains aligned.
                 */
                dbExecute(
                    "INSERT INTO project_lifecycle
                    (
                        project_id,
                        lifecycle_status,
                        final_budget_amount
                    )
                    VALUES (?,?,?)",
                    [
                        $projectId,
                        'planned',
                        $target
                    ]
                );


                /*
                 * Project Manager starts in draft approval.
                 */
                dbExecute(
                    "INSERT INTO project_approval
                    (
                        project_id,
                        approval_status
                    )
                    VALUES (?,?)",
                    [
                        $projectId,
                        akp_role() === 'projects_manager'
                            ? 'draft'
                            : 'approved'
                    ]
                );


                akp_audit(
                    $action,
                    'other_projects',
                    $projectId,
                    null,
                    [
                        'name' =>
                            $input['name'],

                        'status' =>
                            'planned'
                    ]
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Project details
             |--------------------------------------------------------------------------
             */

            $detailValues = [
                $input['objectives'] !== ''
                    ? $input['objectives']
                    : null,

                $input['justification'] !== ''
                    ? $input['justification']
                    : null,

                $input['expected_outcomes'] !== ''
                    ? $input['expected_outcomes']
                    : null,

                $input['target_beneficiary_description'] !== ''
                    ? $input['target_beneficiary_description']
                    : null,

                $input['implementing_partner'] !== ''
                    ? $input['implementing_partner']
                    : null,

                $input['donor_restrictions'] !== ''
                    ? $input['donor_restrictions']
                    : null,

                $input['procurement_method'] !== ''
                    ? $input['procurement_method']
                    : null,

                $input['sustainability_plan'] !== ''
                    ? $input['sustainability_plan']
                    : null,

                $input['risk_mitigation'] !== ''
                    ? $input['risk_mitigation']
                    : null,

                $input['government_requirements'] !== ''
                    ? $input['government_requirements']
                    : null,

                $input['contact_person'] !== ''
                    ? $input['contact_person']
                    : null,

                $input['contact_phone'] !== ''
                    ? $input['contact_phone']
                    : null,

                $input['contact_email'] !== ''
                    ? $input['contact_email']
                    : null,

                $input['notes'] !== ''
                    ? $input['notes']
                    : null,

                akp_user_id(),

                $projectId
            ];


            dbExecute(
                "INSERT INTO project_details
                (
                    objectives,
                    justification,
                    expected_outcomes,
                    target_beneficiary_description,
                    implementing_partner,
                    donor_restrictions,
                    procurement_method,
                    sustainability_plan,
                    risk_mitigation,
                    government_requirements,
                    contact_person,
                    contact_phone,
                    contact_email,
                    notes,
                    updated_by,
                    project_id
                )
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)

                ON DUPLICATE KEY UPDATE

                    objectives = VALUES(objectives),
                    justification = VALUES(justification),
                    expected_outcomes = VALUES(expected_outcomes),
                    target_beneficiary_description = VALUES(target_beneficiary_description),
                    implementing_partner = VALUES(implementing_partner),
                    donor_restrictions = VALUES(donor_restrictions),
                    procurement_method = VALUES(procurement_method),
                    sustainability_plan = VALUES(sustainability_plan),
                    risk_mitigation = VALUES(risk_mitigation),
                    government_requirements = VALUES(government_requirements),
                    contact_person = VALUES(contact_person),
                    contact_phone = VALUES(contact_phone),
                    contact_email = VALUES(contact_email),
                    notes = VALUES(notes),
                    updated_by = VALUES(updated_by)",
                $detailValues
            );


            /*
             |--------------------------------------------------------------------------
             | Primary supervisor
             |--------------------------------------------------------------------------
             */

            if ($input['supervisor_user_id'] !== '') {

                $newSupervisorId =
                    (int)$input['supervisor_user_id'];

                $current =
                    dbFetchOne(
                        "SELECT
                            id,
                            supervisor_user_id
                         FROM project_supervisor_assignments
                         WHERE project_id = ?
                           AND ended_at IS NULL
                         ORDER BY id DESC
                         LIMIT 1",
                        [$projectId]
                    );


                if (
                    !$current ||
                    (int)$current['supervisor_user_id']
                    !== $newSupervisorId
                ) {

                    if ($current) {

                        dbExecute(
                            "UPDATE project_supervisor_assignments
                             SET
                                ended_at = NOW(),
                                end_reason = ?
                             WHERE id = ?",
                            [
                                'إعادة تعيين مشرف المشروع',
                                (int)$current['id']
                            ]
                        );
                    }


                    dbExecute(
                        "INSERT INTO project_supervisor_assignments
                        (
                            project_id,
                            supervisor_user_id,
                            assigned_by,
                            notes
                        )
                        VALUES (?,?,?,?)",
                        [
                            $projectId,
                            $newSupervisorId,
                            akp_user_id(),
                            'التكليف الأساسي للمشروع'
                        ]
                    );


                    akp_audit(
                        'ASSIGN_PRIMARY_SUPERVISOR',
                        'project_supervisor_assignment',
                        $projectId,

                        $current
                            ? [
                                'supervisor_user_id' =>
                                    (int)$current['supervisor_user_id']
                            ]
                            : null,

                        [
                            'supervisor_user_id' =>
                                $newSupervisorId
                        ]
                    );
                }
            }


            /*
             |--------------------------------------------------------------------------
             | CREATE INITIAL BUDGET
             |--------------------------------------------------------------------------
             |
             | At this point:
             |
             | - every line is valid
             | - every amount > 0
             | - suggested budget exists
             | - total lines == suggested budget
             |
             | Therefore it is safe to create the budget.
             |--------------------------------------------------------------------------
             */

            if (!$id && !empty($budgetLines)) {

                $budgetName =
                    $input['name'] .
                    ' - الميزانية الأولية';


                dbExecute(
                    "INSERT INTO project_budgets
                    (
                        project_id,
                        version_no,
                        budget_name,
                        currency_code,
                        status,
                        created_by
                    )
                    VALUES (?,?,?,?,?,?)",
                    [
                        $projectId,
                        1,
                        $budgetName,
                        $input['currency_code'],
                        'draft',
                        akp_user_id()
                    ]
                );


                $budgetId =
                    (int)(
                        dbFetchOne(
                            'SELECT LAST_INSERT_ID() AS id'
                        )['id'] ?? 0
                    );


                foreach ($budgetLines as $line) {

                    dbExecute(
                        "INSERT INTO project_budget_lines
                        (
                            budget_id,
                            category,
                            description,
                            estimated_amount,
                            created_by
                        )
                        VALUES (?,?,?,?,?)",
                        [
                            $budgetId,
                            $line['category'],
                            $line['description'],
                            (float)$line['amount'],
                            akp_user_id()
                        ]
                    );
                }


                /*
                 * IMPORTANT:
                 *
                 * Do NOT replace the final budget with an independently
                 * calculated value here.
                 *
                 * The validation above has already guaranteed:
                 *
                 * total budget lines == target amount
                 *
                 * So we explicitly use the validated target.
                 */
                dbExecute(
                    "UPDATE project_lifecycle
                     SET final_budget_amount = ?
                     WHERE project_id = ?",
                    [
                        $target,
                        $projectId
                    ]
                );


                akp_audit(
                    'CREATE_BUDGET',
                    'project_budget',
                    $budgetId,
                    null,
                    [
                        'project_id' =>
                            $projectId,

                        'total' =>
                            $totalBudgetCents / 100,

                        'target_amount' =>
                            $target
                    ]
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Reset rejected approval back to draft when editing.
             |--------------------------------------------------------------------------
             */

            if (
                $id &&
                akp_role() === 'projects_manager'
            ) {

                $approval =
                    dbFetchOne(
                        'SELECT approval_status
                         FROM project_approval
                         WHERE project_id = ?',
                        [$projectId]
                    );


                if (
                    ($approval['approval_status'] ?? 'draft')
                    === 'rejected'
                ) {

                    dbExecute(
                        "UPDATE project_approval
                         SET
                            approval_status = 'draft',
                            rejection_reason = NULL
                         WHERE project_id = ?",
                        [$projectId]
                    );
                }
            }


            /*
             |--------------------------------------------------------------------------
             | Synchronize lifecycle and accounting accounts.
             |--------------------------------------------------------------------------
             */

            akp_sync_lifecycle_row($projectId);

            if (
                function_exists('ak_sync_project_accounts')
            ) {
                ak_sync_project_accounts(
                    $projectId,
                    $input['name']
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Success
             |--------------------------------------------------------------------------
             */

            flash(
                'success',

                $id
                    ? 'تم تحديث البيانات الأساسية للمشروع.'
                    : 'تم إنشاء المشروع مع ميزانيته الأولية المتطابقة مع الميزانية التقديرية. يمكنك الآن إرساله للاعتماد أو إضافة تفاصيل أخرى.'
            );


            if ($projectCreateTransaction && db()->inTransaction()) {
                db()->commit();
            }

            header(
                'Location: ' .
                APP_URL .
                'modules/projects/view.php?id=' .
                $projectId
            );

            exit();

            } catch (Throwable $e) {
                if ($projectCreateTransaction && db()->inTransaction()) {
                    db()->rollBack();
                }

                $errors[] = APP_ENV === 'development'
                    ? 'تعذر حفظ المشروع: ' . $e->getMessage()
                    : 'تعذر حفظ المشروع بسبب خطأ داخلي. لم يتم حفظ أي جزء من عملية الإنشاء.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| View
|--------------------------------------------------------------------------
*/

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">

    <h2>
        <?php
        echo $id
            ? 'تعديل البيانات الأساسية: ' . e($project['name'])
            : 'مشروع جديد';
        ?>
    </h2>

    <p>
        <?php
        echo $id
            ? 'هذه الصفحة مخصصة للبيانات العامة. الميزانية والمصروفات والوثائق والتشغيل تُدار من ملف المشروع.'
            : 'أنشئ مشروعاً جديداً مع ميزانيته الأولية في خطوة واحدة. يجب أن يساوي إجمالي بنود الميزانية الميزانية التقديرية تماماً.';
        ?>
    </p>

</div>


<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>


<?php if ($errors): ?>

<div class="alert alert-danger fade-in">

    <strong>
        تعذر حفظ المشروع:
    </strong>

    <ul class="mb-0 mt-2">

        <?php foreach ($errors as $error): ?>

            <li>
                <?php echo e($error); ?>
            </li>

        <?php endforeach; ?>

    </ul>

</div>

<?php endif; ?>


<div class="card fade-in">

    <div class="card-body">

        <form method="post" id="project-form">

            <?php echo csrf_field(); ?>

            <?php if ($id): ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?php echo $id; ?>"
                >

            <?php endif; ?>


            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label">
                        اسم المشروع *
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        required
                        value="<?php echo e($input['name']); ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        كود المشروع
                    </label>

                    <input
                        type="text"
                        name="project_code"
                        class="form-control"
                        value="<?php echo e($input['project_code']); ?>"
                        placeholder="يُنشأ تلقائياً للمشروع الجديد"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        نوع المشروع
                    </label>

                    <input
                        type="text"
                        name="project_type"
                        class="form-control"
                        placeholder="مياه / طاقة شمسية / علاجي"
                        value="<?php echo e($input['project_type']); ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        الميزانية التقديرية
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="target_amount"
                        id="target_amount"
                        class="form-control"
                        value="<?php echo e($input['target_amount']); ?>"
                    >

                    <?php if (!$id): ?>"
                );

                $nextNumber =
                    ((int)($nextNumberRow['max_number'] ?? 0)) + 1;

                $code =
                    'PRJ-' .
                    str_pad(
                        (string)$nextNumber,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );


                dbExecute(
                    "INSERT INTO other_projects
                    (
                        name,
                        project_code,
                        description,
                        target_amount,
                        currency_code,
                        start_date,
                        end_date,
                        status,
                        created_by,
                        project_type,
                        location,
                        city,
                        district,
                        total_beneficiaries
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $input['name'],

                        $code,

                        $input['description'] !== ''
                            ? $input['description']
                            : null,

                        $target,

                        $input['currency_code'],

                        $input['start_date'] !== ''
                            ? $input['start_date']
                            : null,

                        $input['end_date'] !== ''
                            ? $input['end_date']
                            : null,

                        'planned',

                        akp_user_id(),

                        $input['project_type'] !== ''
                            ? $input['project_type']
                            : null,

                        $input['location'] !== ''
                            ? $input['location']
                            : null,

                        $input['city'] !== ''
                            ? $input['city']
                            : null,

                        $input['district'] !== ''
                            ? $input['district']
                            : null,

                        $beneficiaries
                    ]
                );


                $projectId =
                    (int)(
                        dbFetchOne(
                            'SELECT LAST_INSERT_ID() AS id'
                        )['id'] ?? 0
                    );


                $action = 'CREATE';


                /*
                 * Initial lifecycle budget.
                 *
                 * Because validation above guarantees that
                 * initial budget lines equal the suggested budget,
                 * this value remains aligned.
                 */
                dbExecute(
                    "INSERT INTO project_lifecycle
                    (
                        project_id,
                        lifecycle_status,
                        final_budget_amount
                    )
                    VALUES (?,?,?)",
                    [
                        $projectId,
                        'planned',
                        $target
                    ]
                );


                /*
                 * Project Manager starts in draft approval.
                 */
                dbExecute(
                    "INSERT INTO project_approval
                    (
                        project_id,
                        approval_status
                    )
                    VALUES (?,?)",
                    [
                        $projectId,
                        akp_role() === 'projects_manager'
                            ? 'draft'
                            : 'approved'
                    ]
                );


                akp_audit(
                    $action,
                    'other_projects',
                    $projectId,
                    null,
                    [
                        'name' =>
                            $input['name'],

                        'status' =>
                            'planned'
                    ]
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Project details
             |--------------------------------------------------------------------------
             */

            $detailValues = [
                $input['objectives'] !== ''
                    ? $input['objectives']
                    : null,

                $input['justification'] !== ''
                    ? $input['justification']
                    : null,

                $input['expected_outcomes'] !== ''
                    ? $input['expected_outcomes']
                    : null,

                $input['target_beneficiary_description'] !== ''
                    ? $input['target_beneficiary_description']
                    : null,

                $input['implementing_partner'] !== ''
                    ? $input['implementing_partner']
                    : null,

                $input['donor_restrictions'] !== ''
                    ? $input['donor_restrictions']
                    : null,

                $input['procurement_method'] !== ''
                    ? $input['procurement_method']
                    : null,

                $input['sustainability_plan'] !== ''
                    ? $input['sustainability_plan']
                    : null,

                $input['risk_mitigation'] !== ''
                    ? $input['risk_mitigation']
                    : null,

                $input['government_requirements'] !== ''
                    ? $input['government_requirements']
                    : null,

                $input['contact_person'] !== ''
                    ? $input['contact_person']
                    : null,

                $input['contact_phone'] !== ''
                    ? $input['contact_phone']
                    : null,

                $input['contact_email'] !== ''
                    ? $input['contact_email']
                    : null,

                $input['notes'] !== ''
                    ? $input['notes']
                    : null,

                akp_user_id(),

                $projectId
            ];


            dbExecute(
                "INSERT INTO project_details
                (
                    objectives,
                    justification,
                    expected_outcomes,
                    target_beneficiary_description,
                    implementing_partner,
                    donor_restrictions,
                    procurement_method,
                    sustainability_plan,
                    risk_mitigation,
                    government_requirements,
                    contact_person,
                    contact_phone,
                    contact_email,
                    notes,
                    updated_by,
                    project_id
                )
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)

                ON DUPLICATE KEY UPDATE

                    objectives = VALUES(objectives),
                    justification = VALUES(justification),
                    expected_outcomes = VALUES(expected_outcomes),
                    target_beneficiary_description = VALUES(target_beneficiary_description),
                    implementing_partner = VALUES(implementing_partner),
                    donor_restrictions = VALUES(donor_restrictions),
                    procurement_method = VALUES(procurement_method),
                    sustainability_plan = VALUES(sustainability_plan),
                    risk_mitigation = VALUES(risk_mitigation),
                    government_requirements = VALUES(government_requirements),
                    contact_person = VALUES(contact_person),
                    contact_phone = VALUES(contact_phone),
                    contact_email = VALUES(contact_email),
                    notes = VALUES(notes),
                    updated_by = VALUES(updated_by)",
                $detailValues
            );


            /*
             |--------------------------------------------------------------------------
             | Primary supervisor
             |--------------------------------------------------------------------------
             */

            if ($input['supervisor_user_id'] !== '') {

                $newSupervisorId =
                    (int)$input['supervisor_user_id'];

                $current =
                    dbFetchOne(
                        "SELECT
                            id,
                            supervisor_user_id
                         FROM project_supervisor_assignments
                         WHERE project_id = ?
                           AND ended_at IS NULL
                         ORDER BY id DESC
                         LIMIT 1",
                        [$projectId]
                    );


                if (
                    !$current ||
                    (int)$current['supervisor_user_id']
                    !== $newSupervisorId
                ) {

                    if ($current) {

                        dbExecute(
                            "UPDATE project_supervisor_assignments
                             SET
                                ended_at = NOW(),
                                end_reason = ?
                             WHERE id = ?",
                            [
                                'إعادة تعيين مشرف المشروع',
                                (int)$current['id']
                            ]
                        );
                    }


                    dbExecute(
                        "INSERT INTO project_supervisor_assignments
                        (
                            project_id,
                            supervisor_user_id,
                            assigned_by,
                            notes
                        )
                        VALUES (?,?,?,?)",
                        [
                            $projectId,
                            $newSupervisorId,
                            akp_user_id(),
                            'التكليف الأساسي للمشروع'
                        ]
                    );


                    akp_audit(
                        'ASSIGN_PRIMARY_SUPERVISOR',
                        'project_supervisor_assignment',
                        $projectId,

                        $current
                            ? [
                                'supervisor_user_id' =>
                                    (int)$current['supervisor_user_id']
                            ]
                            : null,

                        [
                            'supervisor_user_id' =>
                                $newSupervisorId
                        ]
                    );
                }
            }


            /*
             |--------------------------------------------------------------------------
             | CREATE INITIAL BUDGET
             |--------------------------------------------------------------------------
             |
             | At this point:
             |
             | - every line is valid
             | - every amount > 0
             | - suggested budget exists
             | - total lines == suggested budget
             |
             | Therefore it is safe to create the budget.
             |--------------------------------------------------------------------------
             */

            if (!$id && !empty($budgetLines)) {

                $budgetName =
                    $input['name'] .
                    ' - الميزانية الأولية';


                dbExecute(
                    "INSERT INTO project_budgets
                    (
                        project_id,
                        version_no,
                        budget_name,
                        currency_code,
                        status,
                        created_by
                    )
                    VALUES (?,?,?,?,?,?)",
                    [
                        $projectId,
                        1,
                        $budgetName,
                        $input['currency_code'],
                        'draft',
                        akp_user_id()
                    ]
                );


                $budgetId =
                    (int)(
                        dbFetchOne(
                            'SELECT LAST_INSERT_ID() AS id'
                        )['id'] ?? 0
                    );


                foreach ($budgetLines as $line) {

                    dbExecute(
                        "INSERT INTO project_budget_lines
                        (
                            budget_id,
                            category,
                            description,
                            estimated_amount,
                            created_by
                        )
                        VALUES (?,?,?,?,?)",
                        [
                            $budgetId,
                            $line['category'],
                            $line['description'],
                            (float)$line['amount'],
                            akp_user_id()
                        ]
                    );
                }


                /*
                 * IMPORTANT:
                 *
                 * Do NOT replace the final budget with an independently
                 * calculated value here.
                 *
                 * The validation above has already guaranteed:
                 *
                 * total budget lines == target amount
                 *
                 * So we explicitly use the validated target.
                 */
                dbExecute(
                    "UPDATE project_lifecycle
                     SET final_budget_amount = ?
                     WHERE project_id = ?",
                    [
                        $target,
                        $projectId
                    ]
                );


                akp_audit(
                    'CREATE_BUDGET',
                    'project_budget',
                    $budgetId,
                    null,
                    [
                        'project_id' =>
                            $projectId,

                        'total' =>
                            $totalBudgetCents / 100,

                        'target_amount' =>
                            $target
                    ]
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Reset rejected approval back to draft when editing.
             |--------------------------------------------------------------------------
             */

            if (
                $id &&
                akp_role() === 'projects_manager'
            ) {

                $approval =
                    dbFetchOne(
                        'SELECT approval_status
                         FROM project_approval
                         WHERE project_id = ?',
                        [$projectId]
                    );


                if (
                    ($approval['approval_status'] ?? 'draft')
                    === 'rejected'
                ) {

                    dbExecute(
                        "UPDATE project_approval
                         SET
                            approval_status = 'draft',
                            rejection_reason = NULL
                         WHERE project_id = ?",
                        [$projectId]
                    );
                }
            }


            /*
             |--------------------------------------------------------------------------
             | Synchronize lifecycle and accounting accounts.
             |--------------------------------------------------------------------------
             */

            akp_sync_lifecycle_row($projectId);

            if (
                function_exists('ak_sync_project_accounts')
            ) {
                ak_sync_project_accounts(
                    $projectId,
                    $input['name']
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Success
             |--------------------------------------------------------------------------
             */

            flash(
                'success',

                $id
                    ? 'تم تحديث البيانات الأساسية للمشروع.'
                    : 'تم إنشاء المشروع مع ميزانيته الأولية المتطابقة مع الميزانية التقديرية. يمكنك الآن إرساله للاعتماد أو إضافة تفاصيل أخرى.'
            );


            header(
                'Location: ' .
                APP_URL .
                'modules/projects/view.php?id=' .
                $projectId
            );

            if ($projectCreateTransaction && db()->inTransaction()) {
                db()->commit();
            }

            exit();

            } catch (Throwable $e) {
                if ($projectCreateTransaction && db()->inTransaction()) {
                    db()->rollBack();
                }

                $errors[] = APP_ENV === 'development'
                    ? 'تعذر حفظ المشروع: ' . $e->getMessage()
                    : 'تعذر حفظ المشروع بسبب خطأ داخلي. لم يتم حفظ أي جزء من عملية الإنشاء.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| View
|--------------------------------------------------------------------------
*/

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">

    <h2>
        <?php
        echo $id
            ? 'تعديل البيانات الأساسية: ' . e($project['name'])
            : 'مشروع جديد';
        ?>
    </h2>

    <p>
        <?php
        echo $id
            ? 'هذه الصفحة مخصصة للبيانات العامة. الميزانية والمصروفات والوثائق والتشغيل تُدار من ملف المشروع.'
            : 'أنشئ مشروعاً جديداً مع ميزانيته الأولية في خطوة واحدة. يجب أن يساوي إجمالي بنود الميزانية الميزانية التقديرية تماماً.';
        ?>
    </p>

</div>


<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>


<?php if ($errors): ?>

<div class="alert alert-danger fade-in">

    <strong>
        تعذر حفظ المشروع:
    </strong>

    <ul class="mb-0 mt-2">

        <?php foreach ($errors as $error): ?>

            <li>
                <?php echo e($error); ?>
            </li>

        <?php endforeach; ?>

    </ul>

</div>

<?php endif; ?>


<div class="card fade-in">

    <div class="card-body">

        <form method="post" id="project-form">

            <?php echo csrf_field(); ?>

            <?php if ($id): ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?php echo $id; ?>"
                >

            <?php endif; ?>


            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label">
                        اسم المشروع *
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        required
                        value="<?php echo e($input['name']); ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        كود المشروع
                    </label>

                    <input
                        type="text"
                        name="project_code"
                        class="form-control"
                        value="<?php echo e($input['project_code']); ?>"
                        placeholder="يُنشأ تلقائياً للمشروع الجديد"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        نوع المشروع
                    </label>

                    <input
                        type="text"
                        name="project_type"
                        class="form-control"
                        placeholder="مياه / طاقة شمسية / علاجي"
                        value="<?php echo e($input['project_type']); ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        الميزانية التقديرية
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="target_amount"
                        id="target_amount"
                        class="form-control"
                        value="<?php echo e($input['target_amount']); ?>"
                    >

                    <?php if (!$id): ?>