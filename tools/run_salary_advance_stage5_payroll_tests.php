<?php
declare(strict_types=1);

/**
 * Stage 5 payroll-integration verification.
 *
 * All payroll/schedule/request/journal mutations are performed inside one
 * transaction and rolled back. This harness never consumes a real payroll
 * period or salary-advance repayment.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_accounting.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_transfer.php';

$pdo = db();
ak_ensure_tables();
ak_seed_accounts();

$testActorId = (int)$pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($testActorId <= 0) {
    throw new RuntimeException('Stage 5 rollback-only fixture requires an existing user for the payment-proof audit foreign key.');
}
$_SESSION['user_id'] = $testActorId;

function findFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                s.id AS schedule_id, s.scheduled_month, s.scheduled_amount,
                p.insufficient_salary_rule, p.eligible_salary_basis,
                r.approved_repayment_method
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         JOIN hr_salary_advance_repayment_plans rp
           ON rp.id = s.repayment_plan_id
          AND rp.status = 'active'
         JOIN hr_salary_advance_policy_versions p
           ON p.id = r.policy_version_id
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending', 'partial')
                      AND (rp.maximum_repayment_months IS NULL OR
                s.scheduled_month < DATE_ADD(rp.start_month, INTERVAL rp.maximum_repayment_months MONTH))
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_payroll_repayments pr
               WHERE pr.salary_advance_request_id = r.id
                 AND pr.payroll_id IN (
                     SELECT p2.id
                     FROM payroll p2
                     WHERE p2.employee_id = r.employee_id
                       AND p2.month = MONTH(s.scheduled_month)
                       AND p2.year = YEAR(s.scheduled_month)
                 )
           )
           AND NOT EXISTS (
               SELECT 1
               FROM payroll px
               WHERE px.employee_id = r.employee_id
                 AND px.month = MONTH(s.scheduled_month)
                 AND px.year = YEAR(s.scheduled_month)
           )
         ORDER BY s.scheduled_month ASC, r.id ASC
         LIMIT 1"
    );
}

$fixture = findFixture($pdo);
if (!$fixture) {
    echo "SKIP | No unused disbursed salary-advance schedule period is available for a rollback-only payroll fixture.\n";
    exit(0);
}

$employeeId = (int)$fixture['employee_id'];
$month = (int)date('n', strtotime($fixture['scheduled_month']));
$year = (int)date('Y', strtotime($fixture['scheduled_month']));

$salaryRow = dbFetchOne(
    "SELECT basic_salary
     FROM hr_employee_salary_history
     WHERE employee_id = ?
       AND effective_from <= ?
       AND (effective_to IS NULL OR effective_to >= ?)
     ORDER BY effective_from DESC, id DESC
     LIMIT 1",
    [$employeeId, date('Y-m-t', strtotime($fixture['scheduled_month'])), date('Y-m-01', strtotime($fixture['scheduled_month']))]
);
$basicSalary = round((float)($salaryRow['basic_salary'] ?? 10000.00), 2);
if ($basicSalary <= 0) {
    $basicSalary = 10000.00;
}

$payrollId = 0;
$entryId = 0;
$proofPath = null;

try {
    $pdo->beginTransaction();

    /*
     * The rollback fixture must never reuse an existing payroll journal
     * reference. Do not derive the ID from MAX(reference_id): legacy data can
     * contain gaps and reference values from older payroll rows. Instead,
     * start above the current payroll IDs and explicitly probe the journal
     * reference key until a free integer is found.
     */
    $fixtureIdRow = dbFetchOne("SELECT COALESCE(MAX(id), 0) AS max_id FROM payroll");
    $journalRefRow = dbFetchOne(
        "SELECT COALESCE(MAX(reference_id), 0) AS max_id
         FROM journal_entries
         WHERE reference_type = 'payroll'"
    );

    /*
     * Legacy payroll journal references can be ahead of the current payroll
     * table's highest ID. Start above both domains so the rollback fixture
     * cannot collide with a historical journal reference.
     */
    $fixturePayrollId = max(
        (int)($fixtureIdRow['max_id'] ?? 0),
        (int)($journalRefRow['max_id'] ?? 0)
    ) + 1;

    if ($fixturePayrollId <= 0 || $fixturePayrollId > 2147483647) {
        throw new RuntimeException('تعذر تخصيص رقم مسير مؤقت آمن لاختبار Stage 5.');
    }

    while (true) {
        $collision = dbFetchOne(
            "SELECT id
             FROM journal_entries
             WHERE reference_type = 'payroll'
               AND reference_id = ?
             LIMIT 1",
            [$fixturePayrollId]
        );
        if (!$collision) {
            break;
        }

        $fixturePayrollId++;
        if ($fixturePayrollId >= 2147483647) {
            throw new RuntimeException('تعذر العثور على رقم مسير مؤقت غير مستخدم لاختبار Stage 5.');
        }
    }

    $pdo->prepare(
        "INSERT INTO payroll
            (id, employee_id, month, year, basic_salary, allowances, overtime,
             deductions, salary_advance_deduction, net_salary, status)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 'approved')"
    )->execute([
        $fixturePayrollId,
        $employeeId,
        $month,
        $year,
        $basicSalary,
        $basicSalary,
    ]);
    $payrollId = $fixturePayrollId;

    // The production paid transition now requires a prepared private proof.
    // Create disposable proof metadata and bytes for this rollback-only fixture.
    $paymentAccountId = hrPayrollResolvePaymentAccount($pdo, null);
    $pdo->prepare('UPDATE payroll SET payment_account_id = ? WHERE id = ?')
        ->execute([$paymentAccountId, $payrollId]);
    $storageKey = hash('sha256', 'stage5-' . $payrollId . '-' . bin2hex(random_bytes(16))) . '.pdf';
    $proofPath = hrPayrollProofPath($storageKey);
    if (file_put_contents($proofPath, "%PDF-1.4\n% Stage 5 rollback-only fixture\n%%EOF\n") === false) {
        throw new RuntimeException('Could not create the rollback-only payment proof fixture.');
    }
    $pdo->prepare(
        "INSERT INTO hr_payroll_payment_proofs
            (payroll_id, proof_state, storage_key, original_filename, detected_mime_type, file_size_bytes)
         VALUES (?, 'prepared', ?, 'stage5-fixture.pdf', 'application/pdf', ?)"
    )->execute([$payrollId, $storageKey, filesize($proofPath)]);

    $payroll = dbFetchOne(
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ?",
        [$payrollId]
    );

    $preview = hrSalaryAdvancePayrollCalculateDraft(
        $pdo,
        $payroll,
        sprintf('%04d-%02d-01', $year, $month)
    );
    $deduction = round((float)$preview['total'], 2);
    $net = round(max(0.00, $basicSalary - $deduction), 2);

    // Keep the payroll approved and let the production status transition
    // perform the paid-state update, journal posting, and repayment allocation.
    $pdo->prepare(
        "UPDATE payroll
         SET salary_advance_deduction = ?, net_salary = ?
         WHERE id = ? AND status = 'approved'"
    )->execute([$deduction, $net, $payrollId]);

    /*
     * Diagnostic guard: expose any pre-existing payroll journal before the
     * production status-transition function is invoked.
     */
    $prePostJournal = dbFetchOne(
        "SELECT id, entry_code, reference_type, reference_id, status
         FROM journal_entries
         WHERE reference_type = 'payroll'
           AND reference_id = ?
         ORDER BY id ASC
         LIMIT 1",
        [$payrollId]
    );
    if ($prePostJournal) {
        throw new RuntimeException(
            'Stage 5 fixture collision before payroll transition: payroll_id=' . $payrollId .
            ', journal_entry_id=' . (int)$prePostJournal['id'] .
            ', entry_code=' . (string)$prePostJournal['entry_code'] .
            ', status=' . (string)$prePostJournal['status']
        );
    }

    try {
        hrPayrollChangeStatus($pdo, $payrollId, 'paid', null);
    } catch (Throwable $payrollError) {
        $postErrorJournal = dbFetchOne(
            "SELECT id, entry_code, reference_type, reference_id, status
             FROM journal_entries
             WHERE reference_type = 'payroll'
               AND reference_id = ?
             ORDER BY id ASC
             LIMIT 1",
            [$payrollId]
        );
        if ($postErrorJournal) {
            throw new RuntimeException(
                $payrollError->getMessage() .
                ' [diagnostic payroll_id=' . $payrollId .
                ', matching_journal_entry_id=' . (int)$postErrorJournal['id'] .
                ', entry_code=' . (string)$postErrorJournal['entry_code'] . ']'
            );
        }
        throw $payrollError;
    }

    $payroll = dbFetchOne(
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ?",
        [$payrollId]
    );
    $postedJournal = dbFetchOne(
        "SELECT id
         FROM journal_entries
         WHERE reference_type = 'payroll'
           AND reference_id = ?
           AND status = 'posted'
         ORDER BY id ASC
         LIMIT 1",
        [$payrollId]
    );
    if (!$postedJournal) {
        throw new RuntimeException('Production payroll transition did not create a posted journal.');
    }
    $entryId = (int)$postedJournal['id'];

    $trace = dbFetchAll(
        "SELECT salary_advance_request_id AS request_id, repayment_schedule_id, actual_amount, outcome, accounting_entry_id
         FROM hr_salary_advance_payroll_repayments
         WHERE payroll_id = ?",
        [$payrollId]
    );
    $schedule = dbFetchOne(
        "SELECT applied_amount, status
         FROM hr_salary_advance_repayment_schedule
         WHERE id = ?",
        [(int)$fixture['schedule_id']]
    );
    $request = dbFetchOne(
        "SELECT outstanding_balance
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );
    $journal = dbFetchOne(
        "SELECT ROUND(SUM(debit),2) debit_total,
                ROUND(SUM(credit),2) credit_total,
                COUNT(*) line_count
         FROM journal_lines
         WHERE entry_id = ?",
        [$entryId]
    );
    $receivable = dbFetchOne(
        "SELECT ROUND(COALESCE(SUM(credit),0),2) amount
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE jl.entry_id = ? AND a.code = '1410'",
        [$entryId]
    );

    $traceTotal = round(array_sum(array_map(static fn(array $row): float => (float)$row['actual_amount'], $trace)), 2);
    if ($traceTotal !== $deduction) {
        throw new RuntimeException('Applied total does not match payroll salary_advance_deduction.');
    }
    if (count($trace) < 1) {
        throw new RuntimeException('No payroll repayment trace was created.');
    }
    if (round((float)$journal['debit_total'], 2) !== round((float)$journal['credit_total'], 2)) {
        throw new RuntimeException('Payroll journal is not balanced.');
    }
    if ($deduction > 0 && round((float)$receivable['amount'], 2) !== $deduction) {
        throw new RuntimeException('Cr 1410 does not match the actual payroll repayment.');
    }

    echo "PASS | Payroll repayment application | request={$fixture['request_no']} | payroll_id={$payrollId} | deduction={$deduction} | trace_rows=" . count($trace) . " | schedule_status={$schedule['status']} | outstanding_after={$request['outstanding_balance']} | journal={$entryId} | journal_balanced=yes\n";

    // Verify duplicate protection within the same transaction.
    $duplicateBlocked = false;
    try {
        hrSalaryAdvancePayrollApply($pdo, $payroll, $entryId);
    } catch (Throwable $duplicateError) {
        $duplicateBlocked = true;
    }
    if (!$duplicateBlocked) {
        throw new RuntimeException('Duplicate payroll repayment application was not blocked.');
    }
    echo "PASS | Duplicate repayment protection | request={$fixture['request_no']} | payroll_id={$payrollId}\n";

    $pdo->rollBack();
    if (is_string($proofPath) && is_file($proofPath)) { @unlink($proofPath); }
    echo "PASS | Rollback-only cleanup | no payroll/request/schedule/journal mutation was committed.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_string($proofPath) && is_file($proofPath)) { @unlink($proofPath); }
    fwrite(STDERR, "FAIL | Stage 5 payroll integration | " . $e->getMessage() . "\n");
    exit(1);
}
