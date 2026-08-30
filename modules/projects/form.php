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

                $count =
                    (int)(
                        dbFetchOne(
                            'SELECT COUNT(*) AS c
                             FROM other_projects'
                        )['c'] ?? 0
                    ) + 1;

                $code =
                    'PRJ-' .
                    str_pad(
                        (string)$count,
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

            exit();
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

                        <div class="form-text">
                            يجب أن يساوي هذا المبلغ إجمالي بنود الميزانية الأولية تماماً.
                        </div>

                    <?php endif; ?>

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        العملة
                    </label>

                    <select
                        name="currency_code"
                        class="form-select"
                    >

                        <option value="SDG">
                            الجنيه السوداني
                        </option>

                        <?php foreach ($currencies as $currency): ?>

                            <option
                                value="<?php echo e($currency['code']); ?>"
                                <?php echo
                                    $input['currency_code']
                                    === $currency['code']
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                <?php echo e($currency['name_ar']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        تاريخ البداية المخطط
                    </label>

                    <input
                        type="date"
                        name="start_date"
                        class="form-control"
                        value="<?php echo e($input['start_date']); ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        تاريخ النهاية المخطط
                    </label>

                    <input
                        type="date"
                        name="end_date"
                        class="form-control"
                        value="<?php echo e($input['end_date']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        الموقع
                    </label>

                    <input
                        type="text"
                        name="location"
                        class="form-control"
                        value="<?php echo e($input['location']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        المدينة
                    </label>

                    <input
                        type="text"
                        name="city"
                        class="form-control"
                        value="<?php echo e($input['city']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        المنطقة
                    </label>

                    <input
                        type="text"
                        name="district"
                        class="form-control"
                        value="<?php echo e($input['district']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        عدد المستفيدين المتوقع
                    </label>

                    <input
                        type="number"
                        min="0"
                        name="total_beneficiaries"
                        class="form-control"
                        value="<?php echo e($input['total_beneficiaries']); ?>"
                    >

                </div>


                <div class="col-md-8">

                    <label class="form-label">
                        مشرف المشروع الأساسي
                        <?php if (!$id): ?>
                            *
                        <?php endif; ?>
                    </label>

                    <select
                        name="supervisor_user_id"
                        class="form-select"
                        <?php echo !$id ? 'required' : ''; ?>
                    >

                        <option value="">
                            اختر مشرف المشروع
                        </option>

                        <?php foreach ($supervisors as $supervisor): ?>

                            <option
                                value="<?php echo (int)$supervisor['id']; ?>"
                                <?php echo
                                    (string)$input['supervisor_user_id']
                                    ===
                                    (string)$supervisor['id']
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                <?php
                                echo e(
                                    $supervisor['full_name'] .
                                    ' · ' .
                                    $supervisor['username']
                                );
                                ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="form-text">
                        يتولى المشرف متابعة التنفيذ والتقدم والمستفيدين والعمالة الخارجية وطلبات المصروفات.
                    </div>

                </div>


                <div class="col-12">

                    <label class="form-label">
                        الوصف
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['description']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        الأهداف
                    </label>

                    <textarea
                        name="objectives"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['objectives']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        مبررات المشروع
                    </label>

                    <textarea
                        name="justification"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['justification']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        النتائج المتوقعة
                    </label>

                    <textarea
                        name="expected_outcomes"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['expected_outcomes']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        وصف الفئة المستفيدة
                    </label>

                    <textarea
                        name="target_beneficiary_description"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['target_beneficiary_description']); ?></textarea>

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        الجهة المنفذة أو الشريك
                    </label>

                    <input
                        type="text"
                        name="implementing_partner"
                        class="form-control"
                        value="<?php echo e($input['implementing_partner']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        طريقة الشراء أو التوريد
                    </label>

                    <input
                        type="text"
                        name="procurement_method"
                        class="form-control"
                        placeholder="مثال: شراء مباشر"
                        value="<?php echo e($input['procurement_method']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        متطلبات حكومية أولية
                    </label>

                    <input
                        type="text"
                        name="government_requirements"
                        class="form-control"
                        value="<?php echo e($input['government_requirements']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        جهة الاتصال
                    </label>

                    <input
                        type="text"
                        name="contact_person"
                        class="form-control"
                        value="<?php echo e($input['contact_person']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        هاتف الاتصال
                    </label>

                    <input
                        type="text"
                        name="contact_phone"
                        class="form-control"
                        value="<?php echo e($input['contact_phone']); ?>"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label">
                        البريد الإلكتروني
                    </label>

                    <input
                        type="email"
                        name="contact_email"
                        class="form-control"
                        value="<?php echo e($input['contact_email']); ?>"
                    >

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        خطة الاستدامة
                    </label>

                    <textarea
                        name="sustainability_plan"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['sustainability_plan']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        المخاطر وإجراءات الحد منها
                    </label>

                    <textarea
                        name="risk_mitigation"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['risk_mitigation']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        قيود المانحين أو التمويل
                    </label>

                    <textarea
                        name="donor_restrictions"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['donor_restrictions']); ?></textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        ملاحظات داخلية
                    </label>

                    <textarea
                        name="notes"
                        class="form-control"
                        rows="3"
                    ><?php echo e($input['notes']); ?></textarea>

                </div>


                <?php if (!$id): ?>

                <!-- =========================================================
                     INITIAL BUDGET
                     ========================================================= -->

                <div class="col-12">

                    <hr>

                    <h5>
                        <i class="fas fa-coins me-2"></i>
                        بنود الميزانية الأولية
                    </h5>

                    <p class="text-muted small mb-2">
                        يجب أن يساوي إجمالي بنود الميزانية الميزانية التقديرية تماماً.
                        لن يسمح النظام بإنشاء المشروع إذا كان الإجمالي أعلى أو أقل.
                    </p>

                    <div
                        id="budget-validation-message"
                        class="alert alert-info py-2 d-none"
                    ></div>

                </div>


                <div
                    class="col-12"
                    id="budget-lines-container"
                >

                    <div
                        class="budget-line-item row g-2 mb-2 border rounded p-2 bg-light"
                    >

                        <div class="col-md-4">

                            <label class="form-label small">
                                فئة البند
                            </label>

                            <input
                                type="text"
                                name="budget_lines[0][category]"
                                class="form-control form-control-sm"
                                placeholder="مثال: مواد بناء، عمالة، معدات"
                                required
                            >

                        </div>


                        <div class="col-md-5">

                            <label class="form-label small">
                                وصف البند
                            </label>

                            <input
                                type="text"
                                name="budget_lines[0][description]"
                                class="form-control form-control-sm"
                                placeholder="وصف تفصيلي"
                                required
                            >

                        </div>


                        <div class="col-md-2">

                            <label class="form-label small">
                                المبلغ التقديري
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="budget_lines[0][amount]"
                                class="form-control form-control-sm budget-amount"
                                placeholder="0.00"
                                required
                            >

                        </div>


                        <div class="col-md-1">

                            <label class="form-label small">
                                &nbsp;
                            </label>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger w-100 remove-budget-line"
                                disabled
                            >
                                <i class="fas fa-trash"></i>
                            </button>

                        </div>

                    </div>

                </div>


                <div class="col-12">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        onclick="addBudgetLine()"
                    >
                        <i class="fas fa-plus me-1"></i>
                        إضافة بند آخر
                    </button>


                    <div
                        class="mt-2 p-3 rounded border"
                        id="budget-summary"
                    >

                        <div class="row">

                            <div class="col-md-4">

                                <strong>
                                    الميزانية التقديرية:
                                </strong>

                                <span
                                    id="target-budget-display"
                                    class="fw-bold"
                                >
                                    0.00
                                </span>

                                <span class="text-muted">
                                    SDG
                                </span>

                            </div>


                            <div class="col-md-4">

                                <strong>
                                    إجمالي بنود الميزانية:
                                </strong>

                                <span
                                    id="total-budget-display"
                                    class="fs-5 fw-bold"
                                >
                                    0.00
                                </span>

                                <span class="text-muted">
                                    SDG
                                </span>

                            </div>


                            <div class="col-md-4">

                                <strong>
                                    الفرق:
                                </strong>

                                <span
                                    id="budget-difference-display"
                                    class="fs-5 fw-bold"
                                >
                                    0.00
                                </span>

                                <span class="text-muted">
                                    SDG
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

                <?php endif; ?>

            </div>


            <div class="mt-4">

                <button
                    class="btn btn-primary"
                    id="save-project-button"
                >
                    <i class="fas fa-save me-1"></i>
                    حفظ
                </button>


                <a
                    href="<?php echo APP_URL; ?>modules/projects/index.php"
                    class="btn btn-secondary"
                >
                    إلغاء
                </a>

            </div>

        </form>

    </div>

</div>


<?php if (!$id): ?>

<script>

let budgetLineCount = 1;


/*
|--------------------------------------------------------------------------
| Add budget line
|--------------------------------------------------------------------------
*/

function addBudgetLine() {

    const container =
        document.getElementById(
            'budget-lines-container'
        );

    const newLine =
        document.createElement('div');

    newLine.className =
        'budget-line-item row g-2 mb-2 border rounded p-2 bg-light';

    newLine.innerHTML = `
        <div class="col-md-4">

            <label class="form-label small">
                فئة البند
            </label>

            <input
                type="text"
                name="budget_lines[${budgetLineCount}][category]"
                class="form-control form-control-sm"
                placeholder="مثال: مواد بناء، عمالة، معدات"
                required
            >

        </div>

        <div class="col-md-5">

            <label class="form-label small">
                وصف البند
            </label>

            <input
                type="text"
                name="budget_lines[${budgetLineCount}][description]"
                class="form-control form-control-sm"
                placeholder="وصف تفصيلي"
                required
            >

        </div>

        <div class="col-md-2">

            <label class="form-label small">
                المبلغ التقديري
            </label>

            <input
                type="number"
                step="0.01"
                min="0.01"
                name="budget_lines[${budgetLineCount}][amount]"
                class="form-control form-control-sm budget-amount"
                placeholder="0.00"
                required
            >

        </div>

        <div class="col-md-1">

            <label class="form-label small">
                &nbsp;
            </label>

            <button
                type="button"
                class="btn btn-sm btn-outline-danger w-100 remove-budget-line"
                onclick="removeBudgetLine(this)"
            >
                <i class="fas fa-trash"></i>
            </button>

        </div>
    `;

    container.appendChild(newLine);

    budgetLineCount++;

    updateRemoveButtons();

    attachBudgetAmountListeners();

    calculateTotalBudget();
}


/*
|--------------------------------------------------------------------------
| Remove budget line
|--------------------------------------------------------------------------
*/

function removeBudgetLine(btn) {

    const line =
        btn.closest('.budget-line-item');

    if (line) {
        line.remove();
    }

    calculateTotalBudget();

    updateRemoveButtons();
}


/*
|--------------------------------------------------------------------------
| Update remove buttons
|--------------------------------------------------------------------------
*/

function updateRemoveButtons() {

    const lines =
        document.querySelectorAll(
            '.budget-line-item'
        );

    lines.forEach((line) => {

        const btn =
            line.querySelector(
                '.remove-budget-line'
            );

        if (!btn) {
            return;
        }

        btn.disabled =
            lines.length === 1;
    });
}


/*
|--------------------------------------------------------------------------
| Convert browser number to cents
|--------------------------------------------------------------------------
*/

function moneyToCents(value) {

    value = String(value || '').trim();

    if (value === '') {
        return null;
    }

    const number =
        Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return null;
    }

    return Math.round(number * 100);
}


/*
|--------------------------------------------------------------------------
| Format money
|--------------------------------------------------------------------------
*/

function formatMoney(cents) {

    return (
        cents / 100
    ).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


/*
|--------------------------------------------------------------------------
| Calculate total and compare with target
|--------------------------------------------------------------------------
*/

function calculateTotalBudget() {

    let totalCents = 0;

    document
        .querySelectorAll('.budget-amount')
        .forEach(input => {

            const cents =
                moneyToCents(
                    input.value
                );

            if (cents !== null) {
                totalCents += cents;
            }
        });


    const targetInput =
        document.getElementById(
            'target_amount'
        );

    const targetCents =
        moneyToCents(
            targetInput
                ? targetInput.value
                : ''
        );


    const totalDisplay =
        document.getElementById(
            'total-budget-display'
        );

    const targetDisplay =
        document.getElementById(
            'target-budget-display'
        );

    const differenceDisplay =
        document.getElementById(
            'budget-difference-display'
        );

    const message =
        document.getElementById(
            'budget-validation-message'
        );

    const saveButton =
        document.getElementById(
            'save-project-button'
        );


    /*
     * Always show total.
     */

    if (totalDisplay) {

        totalDisplay.textContent =
            formatMoney(totalCents);
    }


    /*
     * No target yet.
     */

    if (targetCents === null) {

        if (targetDisplay) {
            targetDisplay.textContent =
                'غير محدد';
        }

        if (differenceDisplay) {
            differenceDisplay.textContent =
                '—';
        }

        if (message) {

            message.classList.remove(
                'd-none',
                'alert-success',
                'alert-danger',
                'alert-warning'
            );

            message.classList.add(
                'alert-warning'
            );

            message.textContent =
                'أدخل الميزانية التقديرية حتى يتم التحقق من تطابق إجمالي بنود الميزانية.';
        }

        if (saveButton) {
            saveButton.disabled = true;
        }

        return;
    }


    /*
     * Display target.
     */

    if (targetDisplay) {

        targetDisplay.textContent =
            formatMoney(targetCents);
    }


    const differenceCents =
        totalCents - targetCents;


    if (differenceDisplay) {

        differenceDisplay.textContent =
            formatMoney(
                Math.abs(differenceCents)
            );
    }


    /*
     * Exact match.
     */

    if (differenceCents === 0) {

        if (message) {

            message.classList.remove(
                'd-none',
                'alert-danger',
                'alert-warning'
            );

            message.classList.add(
                'alert-success'
            );

            message.textContent =
                '✓ إجمالي بنود الميزانية يساوي الميزانية التقديرية تماماً. يمكن حفظ المشروع.';
        }

        if (saveButton) {
            saveButton.disabled = false;
        }

        return;
    }


    /*
     * Over budget.
     */

    if (differenceCents > 0) {

        if (message) {

            message.classList.remove(
                'd-none',
                'alert-success',
                'alert-warning'
            );

            message.classList.add(
                'alert-danger'
            );

            message.textContent =
                '⚠ إجمالي بنود الميزانية يتجاوز الميزانية التقديرية بمبلغ ' +
                formatMoney(differenceCents) +
                ' SDG. يجب تصحيح البنود قبل الحفظ.';
        }

        if (saveButton) {
            saveButton.disabled = true;
        }

        return;
    }


    /*
     * Under budget.
     */

    const underAmount =
        Math.abs(differenceCents);


    if (message) {

        message.classList.remove(
            'd-none',
            'alert-success',
            'alert-danger'
        );

        message.classList.add(
            'alert-warning'
        );

        message.textContent =
            '⚠ إجمالي بنود الميزانية أقل من الميزانية التقديرية بمبلغ ' +
            formatMoney(underAmount) +
            ' SDG. يجب أن يساوي الإجمالي الميزانية التقديرية تماماً.';
    }


    if (saveButton) {
        saveButton.disabled = true;
    }
}


/*
|--------------------------------------------------------------------------
| Attach listeners
|--------------------------------------------------------------------------
*/

function attachBudgetAmountListeners() {

    document
        .querySelectorAll('.budget-amount')
        .forEach(input => {

            input.removeEventListener(
                'input',
                calculateTotalBudget
            );

            input.removeEventListener(
                'change',
                calculateTotalBudget
            );

            input.addEventListener(
                'input',
                calculateTotalBudget
            );

            input.addEventListener(
                'change',
                calculateTotalBudget
            );
        });
}


/*
|--------------------------------------------------------------------------
| Initial listeners
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        attachBudgetAmountListeners();

        const targetInput =
            document.getElementById(
                'target_amount'
            );

        if (targetInput) {

            targetInput.addEventListener(
                'input',
                calculateTotalBudget
            );

            targetInput.addEventListener(
                'change',
                calculateTotalBudget
            );
        }

        calculateTotalBudget();

        updateRemoveButtons();
    }
);


/*
|--------------------------------------------------------------------------
| Final client-side safety check
|--------------------------------------------------------------------------
|
| This is NOT the real security layer.
| PHP validates everything again.
|--------------------------------------------------------------------------
*/

document
    .getElementById('project-form')
    .addEventListener(
        'submit',
        function (event) {

            const targetInput =
                document.getElementById(
                    'target_amount'
                );

            const targetCents =
                moneyToCents(
                    targetInput
                        ? targetInput.value
                        : ''
                );

            let totalCents = 0;

            document
                .querySelectorAll('.budget-amount')
                .forEach(input => {

                    const cents =
                        moneyToCents(
                            input.value
                        );

                    if (cents !== null) {
                        totalCents += cents;
                    }
                });


            if (
                targetCents === null ||
                totalCents !== targetCents
            ) {

                event.preventDefault();

                calculateTotalBudget();

                alert(
                    'لا يمكن حفظ المشروع. يجب أن يساوي إجمالي بنود الميزانية الميزانية التقديرية تماماً.'
                );
            }
        }
    );

</script>

<?php endif; ?>


<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>