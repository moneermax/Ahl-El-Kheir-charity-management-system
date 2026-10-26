<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employment.php';
require_once __DIR__ . '/lib_contract_salary.php';
require_once __DIR__ . '/lib_payroll_policy.php';
require_once __DIR__ . '/lib_payroll_accounting.php';
require_once __DIR__ . '/lib_salary_advance_payroll.php';
require_once __DIR__ . '/lib_employee_incentives.php';
require_once __DIR__ . '/lib_gm_payroll_period.php';
require_once __DIR__ . '/lib_payroll_transfer.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'financial_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$message = '';
$msg_type = 'success';
$selectedMonth = max(1, min(12, (int)($_GET['month'] ?? $_POST['month'] ?? date('n'))));
$selectedYear = max(2020, min(2100, (int)($_GET['year'] ?? $_POST['year'] ?? date('Y'))));
$periodStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$periodEnd = date('Y-m-t', strtotime($periodStart));

require_once __DIR__ . '/lib_payroll_management.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $month = max(1, min(12, (int)($_POST['month'] ?? $selectedMonth)));
        $year = max(2020, min(2100, (int)($_POST['year'] ?? $selectedYear)));
        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $selectedMonth = $month;
        $selectedYear = $year;

        if ($action === 'generate') {
            $policy = hrPayrollPolicyGetActive($pdo, $periodEnd);
            if (!$policy) throw new RuntimeException(t('hr.payroll_error_no_policy'));
            $eligible = payrollEligibleEmployees($pdo, $periodEnd);
            $inserted = 0; $refreshed = 0; $skipped = 0; $noSalary = 0;
            $pdo->beginTransaction();
            try {
                $existsStmt = $pdo->prepare("SELECT id,status,allowances,overtime,deductions,payroll_policy_version_id FROM payroll WHERE employee_id=? AND month=? AND year=? LIMIT 1");
                $insertStmt = $pdo->prepare("INSERT INTO payroll (employee_id,month,year,basic_salary,allowances,overtime,deductions,net_salary,status,payroll_policy_version_id) VALUES (?,?,?, ?,0,0,0,?,'draft',?)");
                $refreshStmt = $pdo->prepare("UPDATE payroll SET basic_salary=?,net_salary=? WHERE id=? AND status='draft'");
                $policyLinkStmt = $pdo->prepare("UPDATE payroll SET payroll_policy_version_id=? WHERE id=? AND status='draft' AND payroll_policy_version_id IS NULL");
                foreach ($eligible as $candidate) {
                    $state = payrollStateAtDate((int)$candidate['id'], $periodEnd);
                    if (!$state || $state['category'] !== 'working') { $skipped++; continue; }
                    $salary = (float)$candidate['basic_salary'];
                    if ($salary <= 0) { $noSalary++; continue; }
                    $existsStmt->execute([(int)$candidate['id'],$month,$year]);
                    $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        if ($existing['status'] === 'draft') {
                            $net = $salary + (float)$existing['allowances'] + (float)$existing['overtime'] - (float)$existing['deductions'];
                            if ($net < 0) $net = 0;
                            $refreshStmt->execute([$salary,$net,(int)$existing['id']]);
                            if ($existing['payroll_policy_version_id'] === null) $policyLinkStmt->execute([(int)$policy['id'],(int)$existing['id']]);
                            $refreshed++;
                        } else { $skipped++; }
                        continue;
                    }
                    $insertStmt->execute([(int)$candidate['id'],$month,$year,$salary,$salary,(int)$policy['id']]);
                    $inserted++;
                }
                if (hrPayrollTransferSchemaAvailable($pdo)) {
                    $periodAccountSetting = hrPayrollGetPeriodSetting($pdo, $month, $year);
                    if ($periodAccountSetting) {
                        $applyPeriodAccount = $pdo->prepare(
                            "UPDATE payroll SET payment_account_id = ?
                             WHERE month = ? AND year = ? AND status = 'draft' AND payment_account_id IS NULL"
                        );
                        $applyPeriodAccount->execute([
                            (int)$periodAccountSetting['default_payment_account_id'],
                            $month,
                            $year,
                        ]);
                    }
                }
                $pdo->commit();
                $message = t('hr.payroll_msg_generate', [
                    'created' => $inserted,
                    'refreshed' => $refreshed,
                    'period' => sprintf('%04d-%02d', $year, $month),
                    'version' => (int)$policy['version_no'],
                    'effective' => (string)$policy['effective_from']
                ]);
                if ($noSalary) $message .= ' ' . t('hr.payroll_msg_no_salary', ['count' => $noSalary]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        } elseif ($action === 'update_amounts') {
            $id = (int)($_POST['payroll_id'] ?? 0);
            $row = dbFetchOne("SELECT id,status,basic_salary FROM payroll WHERE id=? LIMIT 1",[$id]);
            if (!$row) throw new RuntimeException(t('hr.payroll_error_not_found'));
            if ($row['status'] !== 'draft') throw new RuntimeException(t('hr.payroll_error_approved_edit'));
            $allowances = max(0,(float)($_POST['allowances'] ?? 0));
            $overtime = max(0,(float)($_POST['overtime'] ?? 0));
            $deductions = max(0,(float)($_POST['deductions'] ?? 0));
            $basic = (float)$row['basic_salary'];
            $netBeforeAdvance = $basic + $allowances + $overtime - $deductions;
            if ($netBeforeAdvance < 0) throw new RuntimeException(t('hr.payroll_error_negative_net'));

            $pdo->prepare(
                "UPDATE payroll
                 SET allowances=?, overtime=?, deductions=?, net_salary=?
                 WHERE id=? AND status='draft'"
            )->execute([$allowances,$overtime,$deductions,max(0.00,$netBeforeAdvance),$id]);

            hrPayrollRefreshAttendanceDeductionDraft($pdo, $id);
            hrSalaryAdvancePayrollRefreshDraft($pdo, $id);
            hrIncentiveRefreshPayrollDraft($pdo, $id);
            $message = t('hr.payroll_msg_components_saved');
        } elseif ($action === 'update_status') {
            $id = (int)($_POST['payroll_id'] ?? 0);
            $newStatus = (string)($_POST['status'] ?? '');
            $paymentAccountId = isset($_POST['payment_account_id']) && $_POST['payment_account_id'] !== '' ? (int)$_POST['payment_account_id'] : null;
            if ($newStatus === 'paid') {
                throw new RuntimeException('استخدم إجراء تأكيد تنفيذ التحويل بعد رفع الإثبات؛ لا يمكن تسجيل الدفع من إجراء تغيير الحالة القديم.');
            }
            if ($newStatus === 'approved') {
                payrollRefreshDraftBaseSalary($pdo, $month, $year);
            }
            hrPayrollChangeStatus($pdo, $id, $newStatus, $paymentAccountId);
            $message = $newStatus === 'approved' ? t('hr.payroll_msg_approved') : t('hr.payroll_msg_paid');
        } elseif ($action === 'approve_selected') {
            $ids = $_POST['payroll_ids'] ?? [];
            if (!is_array($ids)) $ids = [];
            if (!$ids) throw new RuntimeException(t('hr.payroll_error_no_selection'));
            payrollRefreshDraftBaseSalary($pdo, $month, $year);
            $approvedCount = hrPayrollApproveSelected($pdo, $ids, $month, $year);
            $message = t('hr.payroll_msg_approve_selected', ['count' => $approvedCount]);
        } elseif ($action === 'approve_all') {
            payrollRefreshDraftBaseSalary($pdo, $month, $year);
            hrPayrollApproveAll($pdo, $month, $year);
            $message = t('hr.payroll_msg_approve_all');
        } elseif (in_array($action, ['save_period_payment_account', 'set_group_payment_account', 'set_row_payment_account', 'upload_payment_proof', 'upload_legacy_payment_evidence', 'confirm_transfer'], true)) {
            if (!verify_csrf()) {
                throw new RuntimeException('انتهت صلاحية النموذج أو رمز الحماية غير صالح. أعد تحميل الصفحة وحاول مرة أخرى.');
            }
            if (!hrPayrollTransferSchemaAvailable($pdo)) {
                throw new RuntimeException('يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل استخدام هذه الوظيفة.');
            }
            if ($action === 'save_period_payment_account') {
                hrPayrollSavePeriodDefaultAccount(
                    $pdo,
                    $month,
                    $year,
                    (int)($_POST['default_payment_account_id'] ?? 0),
                    Session::getUserId()
                );
                $message = 'تم حفظ حساب التحويل الافتراضي للفترة وتطبيقه على السجلات المعتمدة التي لا تملك حساباً محدداً.';
            } elseif ($action === 'set_group_payment_account' || $action === 'set_row_payment_account') {
                $ids = $action === 'set_row_payment_account'
                    ? [(int)($_POST['payroll_id'] ?? 0)]
                    : ($_POST['account_payroll_ids'] ?? []);
                if (!is_array($ids)) {
                    $ids = [];
                }
                $count = hrPayrollSetPaymentAccountForRows(
                    $pdo,
                    $ids,
                    $month,
                    $year,
                    (int)($_POST['payment_account_id'] ?? 0)
                );
                $message = 'تم تعيين حساب التحويل لعدد ' . $count . ' من سجلات الرواتب المعتمدة.';
            } elseif ($action === 'confirm_transfer') {
                hrPayrollChangeStatus($pdo, (int)($_POST['payroll_id'] ?? 0), 'paid', null);
                $message = 'تم تأكيد التحويل وترحيل المحاسبة. ستتم محاولة إرسال إشعار الموظف بعد اكتمال العملية.';
            } else {
                $legacyEvidence = $action === 'upload_legacy_payment_evidence';
                hrPayrollUploadProof(
                    $pdo,
                    (int)($_POST['payroll_id'] ?? 0),
                    $_FILES['proof_file'] ?? [],
                    Session::getUserId(),
                    $legacyEvidence
                );
                $message = $legacyEvidence
                    ? 'تم إرفاق الدليل التاريخي دون تغيير حالة الراتب أو القيد المحاسبي.'
                    : 'تم حفظ إثبات التحويل كإثبات مُجهز فقط؛ لم يتم تأكيد الدفع أو ترحيل المحاسبة.';
            }
        }
    }
    payrollRefreshDraftBaseSalary($pdo, $selectedMonth, $selectedYear);
} catch (Throwable $e) {
    $message = t('hr.payroll_error_prefix') . ' ' . $e->getMessage();
    $msg_type = 'error';
}

$transferSchemaAvailable = hrPayrollTransferSchemaAvailable($pdo);
$periodPaymentSetting = $transferSchemaAvailable
    ? hrPayrollGetPeriodSetting($pdo, $selectedMonth, $selectedYear)
    : null;
$activePaymentAccounts = $transferSchemaAvailable ? hrPayrollActivePaymentAccounts($pdo) : [];
if ($transferSchemaAvailable) {
    $payrolls = dbFetchAll(
        "SELECT p.*, e.employee_code, e.full_name AS emp_name,
                pp.id AS payment_proof_id, pp.proof_state AS payment_proof_state,
                pp.original_filename AS payment_proof_filename
         FROM payroll p
         JOIN employees e ON p.employee_id=e.id
         LEFT JOIN hr_payroll_payment_proofs pp ON pp.payroll_id = p.id
         WHERE p.month=? AND p.year=? ORDER BY e.full_name ASC,p.id ASC",
        [$selectedMonth,$selectedYear]
    );
} else {
    $payrolls = dbFetchAll(
        "SELECT p.*, e.employee_code, e.full_name AS emp_name
         FROM payroll p
         JOIN employees e ON p.employee_id=e.id
         WHERE p.month=? AND p.year=? ORDER BY e.full_name ASC,p.id ASC",
        [$selectedMonth,$selectedYear]
    );
}

// Idempotently catch a period that was fully paid before this completion notice existed.
hrGmPayrollNotifyPeriodComplete($pdo, $selectedMonth, $selectedYear);

$activePolicy = hrPayrollPolicyGetActive($pdo, $periodEnd);
$summary = ['count'=>count($payrolls),'draft'=>0,'approved'=>0,'paid'=>0,'net'=>0.0,'incentives'=>0.0];
foreach ($payrolls as $p) { if (isset($summary[$p['status']])) $summary[$p['status']]++; $summary['net'] += (float)$p['net_salary']; $summary['incentives'] += (float)($p['incentive_amount'] ?? 0); }
$monthNames = AK_LANG === 'en'
    ? [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December']
    : [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
$pageTitle = t('hr.payroll_title');
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.pr-wrap{max-width:1500px;margin:auto}.pr-header{background:linear-gradient(135deg,#1b4d8f,#2c5aa0);color:#fff;padding:24px;border-radius:12px;margin-bottom:20px}.pr-header h1{margin:0;font-size:1.7rem}.pr-header p{margin:5px 0 0;opacity:.9}.pr-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow:hidden}.pr-head{padding:14px 18px;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.pr-body{padding:18px}.pr-filter{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.pr-filter label{font-size:.78rem;font-weight:700;color:#495057;display:block;margin-bottom:4px}.pr-filter select{min-width:130px}.pr-table{width:100%;table-layout:auto;border-collapse:separate;border-spacing:0;border:0;border-radius:0;overflow:visible}.pr-table th,.pr-table td{padding:6px 5px;border-left:1px solid #c7d7e8;border-bottom:1px solid #c7d7e8;text-align:right;vertical-align:middle;font-size:.75rem;line-height:1.35;overflow-wrap:anywhere}.pr-table th{position:sticky;top:0;z-index:5;background:#17365d;color:#fff;font-weight:800;white-space:nowrap;overflow-wrap:normal;padding:7px 5px}.pr-table tr:last-child td{border-bottom:0}.pr-table th:last-child,.pr-table td:last-child{border-left:0}.pr-table tr:hover{background:#f3f8fe}.pr-table td{white-space:normal}.pr-table .money-input{max-width:100%;width:64px}.pr-table .employee-main{display:block;min-width:0}.pr-table .employee-code{white-space:nowrap}.pr-table td:nth-child(n+2):nth-child(-n+10){font-variant-numeric:tabular-nums}.pr-table .salary-source{display:block;margin-top:2px}.pr-table th{width:1%;}.pr-table td{overflow-wrap:anywhere}.pr-table .employee-cell{min-width:115px}.pr-table .select-draft{margin-left:4px;vertical-align:middle}.pr-table .employee-heading{display:flex;align-items:center;gap:5px;white-space:nowrap}.pr-table .action-stack{display:flex;flex-direction:column;gap:5px;align-items:stretch}.pr-table .action-stack .pr-btn{white-space:nowrap;text-align:center;padding:5px 7px}.pr-table .action-stack form{margin:0}.pr-table-scroll{max-height:min(68vh,720px);overflow:auto;position:relative;overscroll-behavior:contain;border:1px solid #9bb8d6;border-radius:9px}.pr-btn{border:0;border-radius:7px;padding:6px 10px;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.pr-primary{background:#1b4d8f;color:#fff}.pr-success{background:#198754;color:#fff}.pr-outline{background:#fff;border:1px solid #ced4da;color:#344054}.pr-badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:.72rem;font-weight:800}.b-draft{background:#fff3cd;color:#856404}.b-approved{background:#dbeafe;color:#1d4ed8}.b-paid{background:#d1e7dd;color:#146c43}.pr-stat{border:1px solid #e8edf3;border-radius:10px;padding:13px 15px;background:#fff;min-width:145px}.pr-stat small{color:#6c757d}.pr-stat strong{display:block;font-size:1.25rem;margin-top:3px}.money-input{width:105px;padding:5px 7px;border:1px solid #ced4da;border-radius:6px;text-align:right}.employee-main{font-weight:800}.employee-code{display:block;color:#6c757d;font-size:.7rem;margin-top:2px}.salary-source{font-size:.72rem;color:#6c757d}.pr-note{background:#f5f8fc;border-right:3px solid #1b4d8f;padding:10px 13px;border-radius:6px;color:#495057;font-size:.82rem}.acct-ready{background:#dbeafe;color:#1d4ed8}.acct-posted{background:#d1e7dd;color:#146c43}.acct-none{background:#f1f3f5;color:#6c757d}
</style>
<div class="pr-wrap">
<div class="pr-header"><h1><i class="fas fa-money-bill-wave me-2"></i> <?php echo htmlspecialchars(t('hr.payroll_title')); ?></h1><p><?php echo htmlspecialchars(t('hr.payroll_description')); ?></p></div>
<?php if($message): ?><div class="alert alert-<?php echo $msg_type==='error'?'danger':'success'; ?> alert-dismissible fade show"><i class="fas <?php echo $msg_type==='error'?'fa-exclamation-circle':'fa-check-circle'; ?> me-2"></i><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="pr-card"><div class="pr-body"><form method="GET" class="pr-filter"><div><label><?php echo htmlspecialchars(t('hr.payroll_month')); ?></label><select name="month" class="form-select form-select-sm"><?php foreach($monthNames as $m=>$name): ?><option value="<?php echo $m; ?>" <?php echo $m===$selectedMonth?'selected':''; ?>><?php echo $name; ?></option><?php endforeach; ?></select></div><div><label><?php echo htmlspecialchars(t('hr.payroll_year')); ?></label><select name="year" class="form-select form-select-sm"><?php for($y=date('Y')-2;$y<=date('Y')+2;$y++): ?><option value="<?php echo $y; ?>" <?php echo $y===$selectedYear?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div><button class="pr-btn pr-primary" type="submit"><i class="fas fa-filter me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_show_period')); ?></button></form></div></div>
<div class="d-flex gap-2 flex-wrap mb-3"><div class="pr-stat"><small><?php echo htmlspecialchars(t('hr.payroll_records')); ?></small><strong><?php echo number_format($summary['count']); ?></strong></div><div class="pr-stat"><small><?php echo htmlspecialchars(t('hr.payroll_drafts')); ?></small><strong><?php echo number_format($summary['draft']); ?></strong></div><div class="pr-stat"><small><?php echo htmlspecialchars(t('hr.payroll_approved')); ?></small><strong><?php echo number_format($summary['approved']); ?></strong></div><div class="pr-stat"><small><?php echo htmlspecialchars(t('hr.payroll_paid')); ?></small><strong><?php echo number_format($summary['paid']); ?></strong></div><div class="pr-stat"><small><?php echo htmlspecialchars(t('hr.payroll_net_total')); ?></small><strong><?php echo number_format($summary['net'],2); ?> <span style="font-size:.75rem"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span></strong></div><div class="pr-stat"><small><?php echo htmlspecialchars(t('hr.payroll_scheduled_incentives')); ?></small><strong><?php echo number_format($summary['incentives'],2); ?> <span style="font-size:.75rem"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span></strong></div></div>
<div class="pr-card"><div class="pr-head"><div><strong><i class="fas fa-calendar-alt me-1 text-primary"></i> <?php echo $monthNames[$selectedMonth].' '.$selectedYear; ?></strong><div class="text-muted" style="font-size:.75rem"><?php echo htmlspecialchars(t('hr.payroll_historical_salary')); ?> <?php echo htmlspecialchars($periodEnd); ?></div></div><div class="d-flex gap-2 flex-wrap"><form method="POST"><input type="hidden" name="action" value="generate"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_generate')); ?></button></form><?php if($summary['draft']>0): ?><form id="bulk-approve-form" method="POST" class="d-inline"><input type="hidden" name="action" value="approve_selected"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"></form><button id="approve-selected-button" form="bulk-approve-form" class="pr-btn pr-primary" type="submit" disabled><i class="fas fa-check-square me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_approve_selected')); ?> (<span id="selected-draft-count">0</span>)</button><form method="POST"><input type="hidden" name="action" value="approve_all"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-success" type="submit"><i class="fas fa-check-double me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_approve_all')); ?></button></form><?php endif; ?></div></div><div class="pr-body">
<?php if($transferSchemaAvailable): ?>
<div class="pr-card"><div class="pr-head"><strong><i class="fas fa-university me-1"></i> <?php echo AK_LANG === 'en' ? 'Transfer preparation' : 'تجهيز التحويلات'; ?></strong></div><div class="pr-body">
<div class="pr-note mb-3"><?php echo AK_LANG === 'en' ? 'Preparing or uploading proof does not confirm payment or post accounting. Only the later explicit transfer-confirmation step may do that.' : 'حفظ حساب التحويل أو رفع الإثبات لا يؤكد الدفع ولا يرحّل المحاسبة. تأكيد التحويل سيكون إجراءً مستقلاً لاحقاً.'; ?></div>
<form method="POST" class="d-flex gap-2 align-items-end flex-wrap mb-3">
<?php echo csrf_field(); ?><input type="hidden" name="action" value="save_period_payment_account"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
<div><label class="form-label mb-1"><?php echo AK_LANG === 'en' ? 'Period default account' : 'حساب التحويل الافتراضي للفترة'; ?></label><select class="form-select form-select-sm" name="default_payment_account_id" required>
<?php foreach($activePaymentAccounts as $account): ?><option value="<?php echo (int)$account['id']; ?>" <?php echo (int)($periodPaymentSetting['default_payment_account_id'] ?? 0)===(int)$account['id']?'selected':''; ?>><?php echo htmlspecialchars((string)$account['code'].' · '.(AK_LANG === 'en' ? ($account['name_en'] ?: $account['name_ar']) : $account['name_ar'])); ?></option><?php endforeach; ?>
</select></div><button class="pr-btn pr-primary" type="submit"><?php echo AK_LANG === 'en' ? 'Save period default' : 'حفظ الحساب الافتراضي'; ?></button>
</form>
<form id="group-account-form" method="POST" class="d-flex gap-2 align-items-end flex-wrap">
<?php echo csrf_field(); ?><input type="hidden" name="action" value="set_group_payment_account"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
<div><label class="form-label mb-1"><?php echo AK_LANG === 'en' ? 'Override account for selected approved rows' : 'تغيير حساب السجلات المعتمدة المحددة'; ?></label><select class="form-select form-select-sm" name="payment_account_id" required>
<?php foreach($activePaymentAccounts as $account): ?><option value="<?php echo (int)$account['id']; ?>"><?php echo htmlspecialchars((string)$account['code'].' · '.(AK_LANG === 'en' ? ($account['name_en'] ?: $account['name_ar']) : $account['name_ar'])); ?></option><?php endforeach; ?>
</select></div><button class="pr-btn pr-outline" type="submit"><?php echo AK_LANG === 'en' ? 'Apply to selected' : 'تطبيق على المحدد'; ?></button><span class="text-muted small"><?php echo AK_LANG === 'en' ? 'Use the checkboxes beside approved employees.' : 'استخدم مربعات التحديد بجوار الموظفين المعتمدين.'; ?></span>
</form></div></div>
<?php elseif(isset($_GET['transfer_setup'])): ?><div class="alert alert-warning"><?php echo AK_LANG === 'en' ? 'Apply the approved payroll transfer migration before enabling transfer preparation.' : 'يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تفعيل هذه الوظائف.'; ?></div><?php endif; ?>
<div class="pr-note mb-3"><i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_info_note', ['currency' => AK_LANG === 'en' ? APP_CURRENCY_NAME_EN.' ('.APP_CURRENCY_CODE.')' : APP_CURRENCY_NAME_AR.' ('.APP_CURRENCY_CODE.')'])); ?></div>
<?php if($activePolicy): ?><div class="alert alert-info py-2"><i class="fas fa-gavel me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_policy_active')); ?>: <strong>#<?php echo (int)$activePolicy['version_no']; ?></strong> — <?php echo htmlspecialchars($activePolicy['effective_from']); ?></div><?php else: ?><div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle me-1"></i> <?php echo htmlspecialchars(t('hr.payroll_no_active_policy')); ?> <?php echo htmlspecialchars($periodEnd); ?>.</div><?php endif; ?>
<div class="pr-table-scroll" data-ak-preserve-scroll="payroll-table" tabindex="0" aria-label="سجلات الرواتب"><table class="pr-table"><thead><tr><th><div class="employee-heading"><?php if($summary['draft']>0): ?><input id="select-all-drafts" class="select-draft" type="checkbox" aria-label="<?php echo htmlspecialchars(t('hr.payroll_select_all_drafts')); ?>" title="<?php echo htmlspecialchars(t('hr.payroll_select_all_drafts')); ?>"><?php endif; ?><?php echo htmlspecialchars(t('hr.payroll_employee_short')); ?></div></th><th title="<?php echo htmlspecialchars(t('hr.payroll_basic_salary')); ?>"><?php echo htmlspecialchars(t('hr.payroll_basic_salary_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_allowances')); ?>"><?php echo htmlspecialchars(t('hr.payroll_allowances_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_overtime')); ?>"><?php echo htmlspecialchars(t('hr.payroll_overtime_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_general_deductions')); ?>"><?php echo htmlspecialchars(t('hr.payroll_general_deductions_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_attendance_deduction')); ?>"><?php echo htmlspecialchars(t('hr.payroll_attendance_deduction_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_advance_deduction')); ?>"><?php echo htmlspecialchars(t('hr.payroll_advance_deduction_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_net_salary')); ?>"><?php echo htmlspecialchars(t('hr.payroll_net_salary_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_scheduled_incentives')); ?>"><?php echo htmlspecialchars(t('hr.payroll_scheduled_incentives_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_total_paid')); ?>"><?php echo htmlspecialchars(t('hr.payroll_total_paid_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_status_accounting')); ?>"><?php echo htmlspecialchars(t('hr.payroll_status_accounting_short')); ?></th><th title="<?php echo htmlspecialchars(t('hr.payroll_actions')); ?>"><?php echo htmlspecialchars(t('hr.payroll_actions_short')); ?></th></tr></thead><tbody>
<?php if(!$payrolls): ?><tr><td colspan="12" class="text-center py-5 text-muted"><i class="fas fa-file-invoice-dollar fa-2x mb-2 d-block"></i><?php echo htmlspecialchars(t('hr.payroll_no_records')); ?> <?php echo htmlspecialchars(t('hr.payroll_empty_hint')); ?></td></tr><?php else: foreach($payrolls as $p): ?>
<tr><td class="employee-cell"><?php if($transferSchemaAvailable && $p['status']==='approved'): ?><input form="group-account-form" type="checkbox" name="account_payroll_ids[]" value="<?php echo (int)$p['id']; ?>" aria-label="<?php echo htmlspecialchars((AK_LANG === 'en' ? 'Select account override for ' : 'تحديد حساب التحويل لـ ').$p['emp_name']); ?>"><?php endif; ?><?php if($p['status']==='draft'): ?><input form="bulk-approve-form" class="payroll-select select-draft" type="checkbox" name="payroll_ids[]" value="<?php echo (int)$p['id']; ?>" aria-label="<?php echo htmlspecialchars(t('hr.payroll_select_draft')); ?>: <?php echo htmlspecialchars($p['emp_name']); ?>"><?php endif; ?><span class="employee-main"><?php echo htmlspecialchars($p['emp_name']); ?></span><span class="employee-code"><?php echo htmlspecialchars($p['employee_code']??''); ?></span><?php if($p['status']==='draft'): ?><form id="payroll-amounts-<?php echo (int)$p['id']; ?>" method="POST"><input type="hidden" name="action" value="update_amounts"><input type="hidden" name="payroll_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"></form><?php endif; ?></td><td><strong><?php echo number_format((float)$p['basic_salary'],2); ?></strong></td>
<?php if($p['status']==='draft'): ?><td><input form="payroll-amounts-<?php echo (int)$p['id']; ?>" class="money-input" type="number" min="0" step="0.01" name="allowances" value="<?php echo htmlspecialchars((string)$p['allowances']); ?>"></td><td><input form="payroll-amounts-<?php echo (int)$p['id']; ?>" class="money-input" type="number" min="0" step="0.01" name="overtime" value="<?php echo htmlspecialchars((string)$p['overtime']); ?>"></td><td><input form="payroll-amounts-<?php echo (int)$p['id']; ?>" class="money-input" type="number" min="0" step="0.01" name="deductions" value="<?php echo htmlspecialchars((string)$p['deductions']); ?>"></td><td><strong><?php echo number_format((float)($p['attendance_deduction'] ?? 0),2); ?></strong></td><td><strong><?php echo number_format((float)($p['salary_advance_deduction'] ?? 0),2); ?></strong></td><td><strong><?php echo number_format((float)$p['net_salary'],2); ?></strong></td><td><strong><?php echo number_format((float)($p['incentive_amount'] ?? 0),2); ?></strong></td><td><strong><?php echo number_format((float)$p['net_salary']+(float)($p['incentive_amount'] ?? 0),2); ?></strong><?php else: ?><td>+<?php echo number_format((float)$p['allowances'],2); ?></td><td>+<?php echo number_format((float)$p['overtime'],2); ?></td><td>-<?php echo number_format((float)$p['deductions'],2); ?></td><td>-<?php echo number_format((float)($p['attendance_deduction'] ?? 0),2); ?></td><td><strong><?php echo number_format((float)($p['salary_advance_deduction'] ?? 0),2); ?></strong></td><td><strong><?php echo number_format((float)$p['net_salary'],2); ?></strong></td><td><strong><?php echo number_format((float)($p['incentive_amount'] ?? 0),2); ?></strong></td><td><strong><?php echo number_format((float)$p['net_salary']+(float)($p['incentive_amount'] ?? 0),2); ?></strong></td><?php endif; ?>
<td><?php $map=['draft'=>['b-draft','مسودة'],'approved'=>['b-approved','معتمد'],'paid'=>['b-paid','مصروف']]; $st=$map[$p['status']]??['b-draft',$p['status']]; ?><span class="pr-badge <?php echo $st[0]; ?>" title="<?php echo htmlspecialchars(t('hr.payroll_status_accounting')); ?>"><?php echo htmlspecialchars(t('hr.payroll_status_'.$p['status'])); ?></span><?php $as=(string)($p['accounting_status']??'none'); $ac=$as==='posted'?['acct-posted','hr.payroll_accounting_posted']:($as==='ready'?['acct-ready','hr.payroll_accounting_ready']:['acct-none','hr.payroll_accounting_none']); ?><span class="pr-badge <?php echo $ac[0]; ?>" title="<?php echo htmlspecialchars(t('hr.payroll_status_accounting')); ?>"><?php echo htmlspecialchars(t($ac[1])); ?></span><?php if(!empty($p['accounting_entry_id'])): ?><span class="salary-source d-block"><?php echo htmlspecialchars(t('hr.payroll_entry')); ?> #<?php echo (int)$p['accounting_entry_id']; ?></span><?php endif; ?></td>
<td><div class="action-stack"><?php if($p['status']==='draft'): ?><button form="payroll-amounts-<?php echo (int)$p['id']; ?>" class="pr-btn pr-outline" type="submit" title="<?php echo htmlspecialchars(t('hr.payroll_save_components')); ?>" aria-label="<?php echo htmlspecialchars(t('hr.payroll_save_components')); ?>"><i class="fas fa-save me-1"></i><?php echo htmlspecialchars(t('hr.payroll_save_short')); ?></button><form method="POST"><input type="hidden" name="action" value="update_status"><input type="hidden" name="payroll_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="status" value="approved"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-primary" type="submit"><?php echo htmlspecialchars(t('hr.payroll_approve')); ?></button></form><?php elseif($p['status']==='approved'): ?>
<?php if($transferSchemaAvailable): ?>
<form method="POST" class="mb-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_row_payment_account"><input type="hidden" name="payroll_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><select class="form-select form-select-sm mb-1" name="payment_account_id" required aria-label="<?php echo AK_LANG === 'en' ? 'Payment account' : 'حساب التحويل'; ?>"><?php foreach($activePaymentAccounts as $account): ?><option value="<?php echo (int)$account['id']; ?>" <?php echo (int)($p['payment_account_id'] ?? 0)===(int)$account['id']?'selected':''; ?>><?php echo htmlspecialchars((string)$account['code'].' · '.(AK_LANG === 'en' ? ($account['name_en'] ?: $account['name_ar']) : $account['name_ar'])); ?></option><?php endforeach; ?></select><button class="pr-btn pr-outline" type="submit"><?php echo AK_LANG === 'en' ? 'Set account' : 'تعيين الحساب'; ?></button></form>
<?php if(empty($p['payment_proof_id'])): ?><form method="POST" enctype="multipart/form-data" class="mb-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="upload_payment_proof"><input type="hidden" name="payroll_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="form-control form-control-sm mb-1" aria-label="<?php echo AK_LANG === 'en' ? 'Transfer proof' : 'إثبات التحويل'; ?>"><button class="pr-btn pr-outline" type="submit"><?php echo AK_LANG === 'en' ? 'Upload proof' : 'رفع الإثبات'; ?></button></form><?php else: ?><a class="pr-btn pr-outline mb-1" href="<?php echo APP_URL; ?>modules/hr/payroll_proof_download.php?payroll_id=<?php echo (int)$p['id']; ?>"><?php echo AK_LANG === 'en' ? 'View proof' : 'عرض الإثبات'; ?> (<?php echo htmlspecialchars((string)$p['payment_proof_state']); ?>)</a><?php endif; ?>
<?php endif; ?>
<?php if($transferSchemaAvailable && ($p['payment_proof_state'] ?? '') === 'prepared'): ?><form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="confirm_transfer"><input type="hidden" name="payroll_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-success" type="submit"><?php echo AK_LANG === 'en' ? 'Confirm transfer made' : 'تأكيد تنفيذ التحويل'; ?></button></form><?php endif; ?><?php else: ?><?php if($transferSchemaAvailable && empty($p['payment_proof_id'])): ?><form method="POST" enctype="multipart/form-data" class="mb-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="upload_legacy_payment_evidence"><input type="hidden" name="payroll_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="form-control form-control-sm mb-1" aria-label="<?php echo AK_LANG === 'en' ? 'Historical payment evidence' : 'دليل الدفع التاريخي'; ?>"><button class="pr-btn pr-outline" type="submit"><?php echo AK_LANG === 'en' ? 'Attach legacy evidence' : 'إرفاق الدليل التاريخي'; ?></button></form><?php elseif($transferSchemaAvailable && !empty($p['payment_proof_id'])): ?><a class="pr-btn pr-outline mb-1" href="<?php echo APP_URL; ?>modules/hr/payroll_proof_download.php?payroll_id=<?php echo (int)$p['id']; ?>"><?php echo AK_LANG === 'en' ? 'View proof' : 'عرض الإثبات'; ?> (<?php echo htmlspecialchars((string)$p['payment_proof_state']); ?>)</a><?php else: ?><span class="text-muted">—</span><?php endif; ?><?php endif; ?></div></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div></div>


<script>
(function(){
    var selectAll = document.getElementById('select-all-drafts');
    var selectedRows = Array.prototype.slice.call(document.querySelectorAll('.payroll-select'));
    var countLabel = document.getElementById('selected-draft-count');
    var approveButton = document.getElementById('approve-selected-button');
    function syncSelection(){
        var selected = selectedRows.filter(function(box){ return box.checked; }).length;
        if (countLabel) countLabel.textContent = String(selected);
        if (approveButton) approveButton.disabled = selected === 0;
        if (selectAll) {
            selectAll.checked = selectedRows.length > 0 && selected === selectedRows.length;
            selectAll.indeterminate = selected > 0 && selected < selectedRows.length;
        }
    }
    if (selectAll) selectAll.addEventListener('change', function(){
        selectedRows.forEach(function(box){ box.checked = selectAll.checked; });
        syncSelection();
    });
    selectedRows.forEach(function(box){ box.addEventListener('change', syncSelection); });
    var bulkForm = document.getElementById('bulk-approve-form');
    if (bulkForm) bulkForm.addEventListener('submit', function(event){
        if (!selectedRows.some(function(box){ return box.checked; })) event.preventDefault();
    });
    syncSelection();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>