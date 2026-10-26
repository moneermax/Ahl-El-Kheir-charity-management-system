<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employment.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_contract_salary.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_payroll_policy.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_payroll_accounting.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_salary_advance_payroll.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employee_incentives.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_gm_payroll_period.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_payroll_transfer.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_payroll_management.php';

$user = ak_api_authenticated_user();
$allowedRoles = ['hr_manager', 'hr_staff', 'financial_manager', 'admin'];
if (!in_array((string)($user['raw_role_code'] ?? ''), $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Payroll management is restricted to HR, Finance Manager, and administrator roles.',
    ]]);
}

function mobilePayrollManagementPeriod(array $input): array
{
    $month = filter_var($input['month'] ?? date('n'), FILTER_VALIDATE_INT);
    $year = filter_var($input['year'] ?? date('Y'), FILTER_VALIDATE_INT);
    if ($month === false || $year === false || $month === null || $year === null || $month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
        throw new InvalidArgumentException('Select a valid payroll month and year.');
    }
    return [(int)$month, (int)$year];
}

function mobilePayrollManagementPositiveIds(mixed $raw): array
{
    if (!is_array($raw) || !$raw || count($raw) > 500) {
        throw new InvalidArgumentException('Select between 1 and 500 payroll rows.');
    }
    $ids = [];
    foreach ($raw as $value) {
        if (!is_scalar($value) || !preg_match('/\A[1-9][0-9]*\z/', (string)$value)) {
            throw new InvalidArgumentException('A selected payroll ID is invalid.');
        }
        $ids[] = (int)$value;
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        throw new InvalidArgumentException('Select at least one payroll row.');
    }
    return $ids;
}

function mobilePayrollManagementRead(PDO $pdo, int $month, int $year): array
{
    $transferSchema = hrPayrollTransferSchemaAvailable($pdo);
    $proofSelect = $transferSchema
        ? 'pp.id AS payment_proof_id, pp.proof_state AS payment_proof_state, pp.original_filename AS payment_proof_filename, pp.detected_mime_type AS payment_proof_mime_type, pp.file_size_bytes AS payment_proof_size, pp.uploaded_at AS payment_proof_uploaded_at'
        : 'NULL AS payment_proof_id, NULL AS payment_proof_state, NULL AS payment_proof_filename, NULL AS payment_proof_mime_type, NULL AS payment_proof_size, NULL AS payment_proof_uploaded_at';
    $proofJoin = $transferSchema ? 'LEFT JOIN hr_payroll_payment_proofs pp ON pp.payroll_id = p.id' : '';
    $rows = dbFetchAll(
        "SELECT p.id, p.employee_id, p.month, p.year, p.basic_salary, p.allowances, p.overtime,
                p.deductions, p.attendance_deduction, p.salary_advance_deduction, p.incentive_amount,
                p.net_salary, p.status, p.payment_date, p.payment_account_id, p.accounting_status,
                p.accounting_entry_id, p.payroll_policy_version_id,
                e.employee_code, e.full_name AS employee_name,
                a.code AS payment_account_code, a.name_ar AS payment_account_name_ar, a.name_en AS payment_account_name_en,
                $proofSelect
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         LEFT JOIN accounts a ON a.id = p.payment_account_id
         $proofJoin
         WHERE p.month = ? AND p.year = ?
         ORDER BY e.full_name ASC, p.id ASC",
        [$month, $year]
    );
    $accounts = $transferSchema ? hrPayrollActivePaymentAccounts($pdo) : [];
    $setting = $transferSchema ? hrPayrollGetPeriodSetting($pdo, $month, $year) : null;
    $periodEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $policy = hrPayrollPolicyGetActive($pdo, $periodEnd);
    $summary = ['count' => count($rows), 'draft' => 0, 'approved' => 0, 'paid' => 0, 'net' => 0.0, 'incentives' => 0.0];
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        foreach (['employee_id','month','year','accounting_entry_id','payment_account_id','payment_proof_id','payment_proof_size'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int)$row[$field];
        }
        foreach (['basic_salary','allowances','overtime','deductions','attendance_deduction','salary_advance_deduction','incentive_amount','net_salary'] as $field) {
            $row[$field] = $row[$field] === null ? null : (float)$row[$field];
        }
        $row['has_payment_proof'] = $row['payment_proof_id'] !== null;
        if (isset($summary[$row['status']])) $summary[$row['status']]++;
        $summary['net'] += (float)$row['net_salary'];
        $summary['incentives'] += (float)($row['incentive_amount'] ?? 0);
    }
    unset($row);

    return [
        'month' => $month,
        'year' => $year,
        'period' => sprintf('%04d-%02d', $year, $month),
        'period_end' => $periodEnd,
        'summary' => $summary,
        'rows' => $rows,
        'transfer_schema_available' => $transferSchema,
        'default_payment_account' => $setting,
        'active_payment_accounts' => $accounts,
        'active_payroll_policy' => $policy ? [
            'id' => (int)$policy['id'],
            'version_no' => (int)$policy['version_no'],
            'effective_from' => (string)$policy['effective_from'],
        ] : null,
        'read_only' => true,
    ];
}

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        [$month, $year] = mobilePayrollManagementPeriod($_GET);
        ak_api_json(200, ['ok' => true, 'data' => mobilePayrollManagementRead(db(), $month, $year)]);
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
    }

    $isMultipart = str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');
    $input = $isMultipart ? $_POST : ak_api_read_json();
    $action = trim((string)($input['action'] ?? ''));
    $pdo = db();
    [$month, $year] = mobilePayrollManagementPeriod($input);
    $periodEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $payrollId = filter_var($input['payroll_id'] ?? null, FILTER_VALIDATE_INT);
    $actorId = (int)$user['id'];

    if ($action === 'generate') {
        $policy = hrPayrollPolicyGetActive($pdo, $periodEnd);
        if (!$policy) throw new DomainException('No payroll policy is effective at the end of this period.');
        $eligible = payrollEligibleEmployees($pdo, $periodEnd);
        $inserted = 0;
        $refreshed = 0;
        $skipped = 0;
        $noSalary = 0;
        $pdo->beginTransaction();
        try {
            $existsStmt = $pdo->prepare('SELECT id,status,allowances,overtime,deductions,payroll_policy_version_id FROM payroll WHERE employee_id=? AND month=? AND year=? LIMIT 1 FOR UPDATE');
            $insertStmt = $pdo->prepare("INSERT INTO payroll (employee_id,month,year,basic_salary,allowances,overtime,deductions,net_salary,status,payroll_policy_version_id) VALUES (?,?,?, ?,0,0,0,?,'draft',?)");
            $refreshStmt = $pdo->prepare("UPDATE payroll SET basic_salary=?,net_salary=? WHERE id=? AND status='draft'");
            $policyLinkStmt = $pdo->prepare('UPDATE payroll SET payroll_policy_version_id=? WHERE id=? AND status=\'draft\' AND payroll_policy_version_id IS NULL');
            foreach ($eligible as $candidate) {
                $state = payrollStateAtDate((int)$candidate['id'], $periodEnd);
                if (!$state || $state['category'] !== 'working') { $skipped++; continue; }
                $salary = (float)$candidate['basic_salary'];
                if ($salary <= 0) { $noSalary++; continue; }
                $existsStmt->execute([(int)$candidate['id'], $month, $year]);
                $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    if ($existing['status'] === 'draft') {
                        $net = $salary + (float)$existing['allowances'] + (float)$existing['overtime'] - (float)$existing['deductions'];
                        if ($net < 0) $net = 0;
                        $refreshStmt->execute([$salary, $net, (int)$existing['id']]);
                        if ($existing['payroll_policy_version_id'] === null) $policyLinkStmt->execute([(int)$policy['id'], (int)$existing['id']]);
                        $refreshed++;
                    } else { $skipped++; }
                    continue;
                }
                $insertStmt->execute([(int)$candidate['id'], $month, $year, $salary, $salary, (int)$policy['id']]);
                $inserted++;
            }
            if (hrPayrollTransferSchemaAvailable($pdo)) {
                $setting = hrPayrollGetPeriodSetting($pdo, $month, $year);
                if ($setting) {
                    $apply = $pdo->prepare("UPDATE payroll SET payment_account_id=? WHERE month=? AND year=? AND status='draft' AND payment_account_id IS NULL");
                    $apply->execute([(int)$setting['default_payment_account_id'], $month, $year]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        payrollRefreshDraftBaseSalary($pdo, $month, $year);
        $data = ['inserted' => $inserted, 'refreshed' => $refreshed, 'skipped' => $skipped, 'without_salary' => $noSalary];
    } elseif ($action === 'update_amounts') {
        if ($payrollId === false || $payrollId === null || $payrollId <= 0) throw new InvalidArgumentException('A valid payroll row is required.');
        $allowances = filter_var($input['allowances'] ?? null, FILTER_VALIDATE_FLOAT);
        $overtime = filter_var($input['overtime'] ?? null, FILTER_VALIDATE_FLOAT);
        $deductions = filter_var($input['deductions'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($allowances === false || $overtime === false || $deductions === false || $allowances < 0 || $overtime < 0 || $deductions < 0) {
            throw new InvalidArgumentException('Allowances, overtime and deductions must be non-negative numbers.');
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id,basic_salary,status FROM payroll WHERE id=? AND month=? AND year=? LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$payrollId, $month, $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new DomainException('Payroll row was not found in the selected period.');
            if ($row['status'] !== 'draft') throw new DomainException('Only draft payroll rows can be edited.');
            $net = round((float)$row['basic_salary'] + (float)$allowances + (float)$overtime - (float)$deductions, 2);
            if ($net < 0) throw new DomainException('Deductions cannot exceed basic salary plus allowances and overtime.');
            $update = $pdo->prepare("UPDATE payroll SET allowances=?,overtime=?,deductions=?,net_salary=? WHERE id=? AND status='draft'");
            $update->execute([$allowances, $overtime, $deductions, max(0, $net), (int)$payrollId]);
            hrPayrollRefreshAttendanceDeductionDraft($pdo, (int)$payrollId);
            hrSalaryAdvancePayrollRefreshDraft($pdo, (int)$payrollId);
            hrIncentiveRefreshPayrollDraft($pdo, (int)$payrollId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $data = ['payroll_id' => (int)$payrollId, 'updated' => true];
    } elseif ($action === 'approve') {
        if ($payrollId === false || $payrollId === null || $payrollId <= 0) throw new InvalidArgumentException('A valid payroll row is required.');
        payrollRefreshDraftBaseSalary($pdo, $month, $year);
        $count = hrPayrollApproveSelected($pdo, [(int)$payrollId], $month, $year);
        $data = ['approved_count' => $count];
    } elseif ($action === 'approve_selected' || $action === 'set_group_payment_account') {
        $ids = mobilePayrollManagementPositiveIds($input[$action === 'approve_selected' ? 'payroll_ids' : 'payroll_ids'] ?? []);
        if ($action === 'approve_selected') {
            payrollRefreshDraftBaseSalary($pdo, $month, $year);
            $count = hrPayrollApproveSelected($pdo, $ids, $month, $year);
            $data = ['approved_count' => $count];
        } else {
            if (!hrPayrollTransferSchemaAvailable($pdo)) throw new DomainException('Apply the payroll transfer migration before changing payment accounts.');
            $count = hrPayrollSetPaymentAccountForRows($pdo, $ids, $month, $year, (int)($input['payment_account_id'] ?? 0));
            $data = ['updated_count' => $count];
        }
    } elseif ($action === 'approve_all') {
        $draftRows = dbFetchAll("SELECT id FROM payroll WHERE month=? AND year=? AND status='draft' ORDER BY id", [$month, $year]);
        if (!$draftRows) throw new DomainException('There are no draft payroll rows to approve.');
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $draftRows);
        payrollRefreshDraftBaseSalary($pdo, $month, $year);
        $data = ['approved_count' => hrPayrollApproveSelected($pdo, $ids, $month, $year)];
    } elseif ($action === 'save_period_payment_account') {
        if (!hrPayrollTransferSchemaAvailable($pdo)) throw new DomainException('Apply the payroll transfer migration before configuring payment accounts.');
        $accountId = filter_var($input['default_payment_account_id'] ?? null, FILTER_VALIDATE_INT);
        if ($accountId === false || $accountId === null || $accountId <= 0) throw new InvalidArgumentException('Select an active payment account.');
        hrPayrollSavePeriodDefaultAccount($pdo, $month, $year, (int)$accountId, $actorId);
        $data = ['saved' => true];
    } elseif ($action === 'set_row_payment_account') {
        if ($payrollId === false || $payrollId === null || $payrollId <= 0) throw new InvalidArgumentException('A valid payroll row is required.');
        if (!hrPayrollTransferSchemaAvailable($pdo)) throw new DomainException('Apply the payroll transfer migration before changing payment accounts.');
        $count = hrPayrollSetPaymentAccountForRows($pdo, [(int)$payrollId], $month, $year, (int)($input['payment_account_id'] ?? 0));
        $data = ['updated_count' => $count];
    } elseif ($action === 'upload_payment_proof' || $action === 'upload_legacy_payment_evidence') {
        if (!$isMultipart) throw new InvalidArgumentException('A multipart file upload is required for payroll proof.');
        if (!hrPayrollTransferSchemaAvailable($pdo)) throw new DomainException('Apply the payroll transfer migration before uploading payroll proof.');
        if ($payrollId === false || $payrollId === null || $payrollId <= 0) throw new InvalidArgumentException('A valid payroll row is required.');
        $legacy = $action === 'upload_legacy_payment_evidence';
        hrPayrollUploadProof($pdo, (int)$payrollId, $_FILES['proof_file'] ?? [], $actorId, $legacy);
        $data = ['payroll_id' => (int)$payrollId, 'proof_state' => $legacy ? 'legacy_evidence' : 'prepared'];
    } elseif ($action === 'confirm_transfer') {
        if ($payrollId === false || $payrollId === null || $payrollId <= 0) throw new InvalidArgumentException('A valid payroll row is required.');
        if (!hrPayrollTransferSchemaAvailable($pdo)) throw new DomainException('Apply the payroll transfer migration before confirming transfers.');
        hrPayrollChangeStatus($pdo, (int)$payrollId, 'paid', null, $actorId, false);
        $data = ['payroll_id' => (int)$payrollId, 'status' => 'paid'];
    } else {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported payroll management action.']]);
    }

    $read = mobilePayrollManagementRead($pdo, $month, $year);
    ak_api_json(200, ['ok' => true, 'data' => array_merge(['action' => $action, 'result' => $data], $read)]);
} catch (InvalidArgumentException $e) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_input', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'payroll_conflict', 'message' => $e->getMessage()]]);
} catch (PDOException $e) {
    error_log('Mobile payroll management database failure: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'payroll_management_failed', 'message' => 'The payroll operation could not be completed.']]);
} catch (RuntimeException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'payroll_operation_rejected', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile payroll management failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'payroll_management_failed', 'message' => 'The payroll operation could not be completed.']]);
}
