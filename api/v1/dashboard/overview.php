<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
$userId = (int)$user['id'];

if (in_array($role, ['admin', 'sudo', 'super_admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'use_admin_overview', 'message' => 'Use the administrator overview endpoint for this role.']]);
}

$metric = static function (string $key, string $ar, string $en, int|float|string|null $value): array {
    return ['key' => $key, 'label_ar' => $ar, 'label_en' => $en, 'value' => $value ?? 0];
};
$metrics = [];

try {
    switch ($role) {
        case 'general_manager':
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM sponsors WHERE status='active') active_sponsors,
                (SELECT COUNT(*) FROM families WHERE status='active') active_families,
                (SELECT COUNT(*) FROM families WHERE status='pending') pending_families,
                (SELECT COUNT(*) FROM family_children) children,
                (SELECT COUNT(*) FROM sponsorships WHERE status='active') active_sponsorships,
                (SELECT COUNT(*) FROM sponsorships WHERE status='paused') paused_sponsorships,
                (SELECT COALESCE(SUM(monthly_amount),0) FROM sponsorships WHERE status='active') monthly_commitment,
                (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted' AND DATE_FORMAT(transaction_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')) month_posted,
                (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted' AND YEAR(transaction_date)=YEAR(CURDATE())) year_posted,
                (SELECT COUNT(*) FROM families f WHERE f.status IN ('active','pending') AND NOT EXISTS (
                    SELECT 1 FROM family_children fc
                    JOIN sponsorships sp ON sp.child_id=fc.id AND sp.status='active'
                    WHERE fc.family_id=f.id
                )) uncovered_families,
                (SELECT COUNT(*) FROM letters l WHERE NOT EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.letter_id=l.id)) unassigned_letters,
                (SELECT COUNT(*) FROM winback_campaigns WHERE status IN ('open','contacted')) open_winback,
                (SELECT COUNT(*) FROM sponsor_requests WHERE status IN ('new','contacted')) open_sponsor_requests");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('active_sponsors','الكفلاء النشطون','Active sponsors',$row['active_sponsors']??0),
                $metric('active_families','الأسر النشطة','Active families',$row['active_families']??0),
                $metric('uncovered_families','أسر بلا كفالة نشطة','Families without active sponsorship',$row['uncovered_families']??0),
                $metric('active_sponsorships','الكفالات النشطة','Active sponsorships',$row['active_sponsorships']??0),
                $metric('paused_sponsorships','الكفالات المتوقفة','Paused sponsorships',$row['paused_sponsorships']??0),
                $metric('monthly_commitment','الالتزام الشهري','Monthly commitment',$row['monthly_commitment']??0),
                $metric('month_posted','المبالغ المرحلة هذا الشهر','Posted this month',$row['month_posted']??0),
                $metric('open_sponsor_requests','طلبات الرعاية المفتوحة','Open sponsor requests',$row['open_sponsor_requests']??0),
                $metric('open_winback','متابعات استرجاع الكفلاء','Open sponsor win-back',$row['open_winback']??0),
                $metric('unassigned_letters','حروف غير مسندة','Unassigned letters',$row['unassigned_letters']??0),
            ];
            break;

        case 'vice_general_manager':
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='supervisor' AND u.is_active=1) active_supervisors,
                (SELECT COUNT(*) FROM supervisor_letters) letters_assigned,
                (SELECT COUNT(*) FROM letters l WHERE NOT EXISTS(SELECT 1 FROM supervisor_letters sl WHERE sl.letter_id=l.id)) letters_unassigned,
                (SELECT COUNT(*) FROM sponsors) total_sponsors,
                (SELECT COUNT(*) FROM families) total_families,
                (SELECT COUNT(*) FROM families WHERE status='active') active_families,
                (SELECT COUNT(*) FROM sponsorships WHERE status='active') active_sponsorships");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('active_supervisors','المشرفون النشطون','Active supervisors',$row['active_supervisors']??0),
                $metric('letters_assigned','إسنادات الحروف','Letter assignments',$row['letters_assigned']??0),
                $metric('letters_unassigned','حروف غير مسندة','Unassigned letters',$row['letters_unassigned']??0),
                $metric('total_sponsors','إجمالي الكفلاء','Total sponsors',$row['total_sponsors']??0),
                $metric('total_families','إجمالي الأسر','Total families',$row['total_families']??0),
                $metric('active_families','الأسر النشطة','Active families',$row['active_families']??0),
                $metric('active_sponsorships','الكفالات النشطة','Active sponsorships',$row['active_sponsorships']??0),
            ];
            break;

        case 'financial_manager':
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM transactions WHERE status='pending_fm_review') pending_transactions,
                (SELECT COUNT(*) FROM monthly_disbursements WHERE status IN ('draft','pending_approval')) pending_disbursements,
                (SELECT COUNT(*) FROM fina_collections WHERE status='pending') pending_fina,
                (SELECT COUNT(*) FROM project_approval WHERE approval_status='submitted') pending_project_approvals,
                (SELECT COUNT(*) FROM journal_entries WHERE status='posted') posted_journals");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('pending_transactions','معاملات بانتظار المراجعة','Transactions awaiting review',$row['pending_transactions']??0),
                $metric('pending_disbursements','تحويلات بانتظار الإجراء','Disbursements awaiting action',$row['pending_disbursements']??0),
                $metric('pending_fina','تحصيلات فينا بانتظار المراجعة','Fina collections pending review',$row['pending_fina']??0),
                $metric('pending_project_approvals','ميزانيات مشروعات بانتظار المراجعة','Project budgets awaiting review',$row['pending_project_approvals']??0),
                $metric('posted_journals','قيود يومية مرحلة','Posted journal entries',$row['posted_journals']??0),
            ];
            break;

        case 'accountant':
        case 'accountant_staff':
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM transactions WHERE status='posted') tx_count,
                (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted' AND DATE_FORMAT(transaction_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')) month_total,
                (SELECT COUNT(*) FROM sponsor_payments WHERE status='pending') pending_inflows,
                (SELECT COUNT(*) FROM monthly_disbursements WHERE status IN ('draft','pending_approval')) pending_outflows,
                (SELECT COALESCE(SUM(total_amount),0) FROM monthly_disbursements WHERE status='pending_approval') outflow_amount_pending");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('tx_count','المعاملات المرحلة','Posted transactions',$row['tx_count']??0),
                $metric('month_total','إجمالي المرحلة هذا الشهر','Posted this month',$row['month_total']??0),
                $metric('pending_inflows','إيرادات بانتظار المراجعة','Pending inflows',$row['pending_inflows']??0),
                $metric('pending_outflows','تحويلات بانتظار الإجراء','Pending disbursements',$row['pending_outflows']??0),
                $metric('outflow_amount_pending','قيمة التحويلات بانتظار الاعتماد','Disbursement amount awaiting approval',$row['outflow_amount_pending']??0),
            ];
            break;

        case 'supervisor':
            $scope = "EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.supervisor_id=? AND sl.letter_id=s.first_letter_id AND (CONVERT(sl.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci=CONVERT(s.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci OR CONVERT(sl.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('both','all','كلاهما','الكل')))";
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM sponsors s WHERE $scope) my_sponsors,
                (SELECT COUNT(*) FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.status='active' AND $scope) my_active_sponsorships,
                (SELECT COUNT(DISTINCT sp.child_id) FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.status='active' AND $scope) my_orphans,
                (SELECT COALESCE(SUM(sp.amount),0) FROM sponsor_payments sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.status='approved' AND DATE_FORMAT(sp.created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND $scope) month_collected",
                [$userId,$userId,$userId,$userId]);
            $row = is_array($row) ? $row : [];
            $fina = dbFetchOne("SELECT COUNT(*) fina_total,SUM(status='pending') fina_pending,COUNT(CASE WHEN status='approved' THEN 1 END) fina_approved_count,COALESCE(SUM(CASE WHEN status='approved' THEN amount ELSE 0 END),0) fina_approved_amount FROM fina_collections WHERE created_by=?",[$userId]);
            $fina = is_array($fina) ? $fina : [];
            $metrics = [
                $metric('my_sponsors','الكفلاء المسندون إليّ','My assigned sponsors',$row['my_sponsors']??0),
                $metric('my_active_sponsorships','الكفالات النشطة','Active sponsorships',$row['my_active_sponsorships']??0),
                $metric('my_orphans','الأيتام المكفولون','Sponsored children',$row['my_orphans']??0),
                $metric('month_collected','تحصيلات هذا الشهر','Collections this month',$row['month_collected']??0),
                $metric('fina_pending','تحصيلات فينا المعلقة','My pending Fina collections',$fina['fina_pending']??0),
                $metric('fina_approved_amount','تحصيلات فينا المعتمدة','My approved Fina amount',$fina['fina_approved_amount']??0),
            ];
            break;

        case 'nanny':
            $row = dbFetchOne("SELECT COUNT(DISTINCT f.id) total_families,COUNT(DISTINCT fc.id) total_children,COALESCE(SUM(CASE WHEN sp.status='active' THEN sp.monthly_amount ELSE 0 END),0) total_monthly FROM families f LEFT JOIN family_children fc ON fc.family_id=f.id AND fc.is_active=1 LEFT JOIN sponsorships sp ON sp.child_id=fc.id AND sp.status='active' WHERE f.nanny_id=? AND f.status IN ('active','pending')",[$userId]);
            $row = is_array($row) ? $row : [];
            $verified = dbFetchOne("SELECT COUNT(*) fully_verified FROM nanny_family_verifications WHERE nanny_id=? AND month=? AND is_orphan_verified=1 AND is_mother_contact_verified=1 AND is_bank_verified=1",[$userId,date('Y-m')]);
            $batches = dbFetchOne("SELECT COUNT(*) pending_batches,COALESCE(SUM(total_amount),0) pending_amount FROM monthly_disbursements WHERE nanny_id=? AND status='transferred'",[$userId]);
            $verified = is_array($verified) ? $verified : [];
            $batches = is_array($batches) ? $batches : [];
            $metrics = [
                $metric('total_families','الأسر المسندة','Assigned families',$row['total_families']??0),
                $metric('total_children','الأطفال النشطون','Active children',$row['total_children']??0),
                $metric('total_monthly','إجمالي الكفالات الشهري','Monthly sponsorship amount',$row['total_monthly']??0),
                $metric('fully_verified','أسر مكتملة التحقق هذا الشهر','Families fully verified this month',$verified['fully_verified']??0),
                $metric('pending_batches','دفعات محولة بانتظار التأكيد','Transferred batches awaiting confirmation',$batches['pending_batches']??0),
                $metric('pending_amount','قيمة الدفعات المحولة','Transferred batch amount',$batches['pending_amount']??0),
            ];
            break;

        case 'administration':
        case 'staff':
        case 'social_media':
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM sponsor_requests WHERE status='new') new_requests,
                (SELECT COUNT(*) FROM sponsor_requests WHERE status='contacted') contacted_requests,
                (SELECT COUNT(*) FROM families f WHERE f.status IN ('active','pending') AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON fc.id=sp.child_id WHERE fc.family_id=f.id AND sp.status='active')) uncovered_families");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('new_requests','طلبات رعاية جديدة','New sponsor requests',$row['new_requests']??0),
                $metric('contacted_requests','طلبات تمت متابعتها','Contacted sponsor requests',$row['contacted_requests']??0),
            ];
            if ($role === 'administration') $metrics[] = $metric('uncovered_families','أسر متاحة للكفالة','Families available for sponsorship',$row['uncovered_families']??0);
            break;

        case 'hr_manager':
        case 'hr_staff':
            $row = dbFetchOne("SELECT
                (SELECT COUNT(*) FROM employees WHERE status='active') total_employees,
                (SELECT COUNT(*) FROM leaves WHERE status IN ('pending','manager_approved')) pending_leaves,
                (SELECT COUNT(*) FROM hr_employee_contracts WHERE status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) expiring_contracts,
                (SELECT COALESCE(SUM(basic_salary),0) FROM employees WHERE status='active') monthly_payroll,
                (SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status IN ('present','remote_work','late')) attendance_today");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('total_employees','الموظفون النشطون','Active employees',$row['total_employees']??0),
                $metric('pending_leaves','طلبات إجازة بانتظار الإجراء','Leave requests pending action',$row['pending_leaves']??0),
                $metric('expiring_contracts','عقود تنتهي خلال 30 يوماً','Contracts expiring within 30 days',$row['expiring_contracts']??0),
                $metric('monthly_payroll','إجمالي الرواتب الأساسية النشطة','Active basic salary total',$row['monthly_payroll']??0),
                $metric('attendance_today','حضور اليوم','Attendance today',$row['attendance_today']??0),
            ];
            break;

        case 'projects_manager':
            $row = dbFetchOne("SELECT COUNT(*) total_projects,
                SUM(CASE WHEN BINARY current_status IN (BINARY 'active', BINARY 'reopened') THEN 1 ELSE 0 END) active_projects,
                SUM(CASE WHEN BINARY approval_status = BINARY 'submitted' THEN 1 ELSE 0 END) pending_approval,
                COALESCE(SUM(CASE WHEN BINARY current_status IN (BINARY 'active', BINARY 'reopened') THEN COALESCE((SELECT SUM(COALESCE(bl.approved_amount, bl.estimated_amount)) FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=x.id AND BINARY b.status = BINARY 'approved'), target_amount) ELSE 0 END),0) active_budget
                FROM (SELECT p.id,p.target_amount,COALESCE(l.lifecycle_status,p.status) current_status,COALESCE((SELECT pa.approval_status FROM project_approval pa WHERE pa.project_id=p.id),'approved') approval_status FROM other_projects p LEFT JOIN project_lifecycle l ON l.project_id=p.id) x");
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('total_projects','إجمالي المشروعات','Total projects',$row['total_projects']??0),
                $metric('active_projects','المشروعات النشطة','Active projects',$row['active_projects']??0),
                $metric('pending_approval','مشروعات بانتظار الاعتماد','Projects awaiting approval',$row['pending_approval']??0),
                $metric('active_budget','ميزانيات المشروعات النشطة','Active project budgets',$row['active_budget']??0),
            ];
            break;

        case 'project_supervisor':
            $row = dbFetchOne("SELECT COUNT(DISTINCT p.id) assigned_projects,
                SUM(CASE WHEN BINARY COALESCE(l.lifecycle_status,p.status) IN (BINARY 'active', BINARY 'reopened') THEN 1 ELSE 0 END) active_assigned,
                COUNT(DISTINCT lh.id) pending_labor,COUNT(DISTINCT pm.id) upcoming_milestones
                FROM other_projects p JOIN project_approval pa ON pa.project_id=p.id
                LEFT JOIN project_lifecycle l ON l.project_id=p.id
                LEFT JOIN project_supervisor_assignments psa ON psa.project_id=p.id AND psa.ended_at IS NULL AND psa.supervisor_user_id=?
                LEFT JOIN project_labor_helpers lh ON lh.project_id=p.id AND lh.supervisor_user_id=? AND BINARY lh.status IN (BINARY 'planned', BINARY 'in_progress')
                LEFT JOIN project_milestones pm ON pm.project_id=p.id AND BINARY pm.status = BINARY 'pending'
                WHERE psa.supervisor_user_id=? AND BINARY pa.approval_status = BINARY 'approved'
                AND BINARY COALESCE(l.lifecycle_status,p.status) IN (BINARY 'active',BINARY 'reopened',BINARY 'completed',BINARY 'under_review',BINARY 'closed',BINARY 'cancelled')",
                [$userId,$userId,$userId]);
            $row = is_array($row) ? $row : [];
            $metrics = [
                $metric('assigned_projects','المشروعات المسندة','Assigned projects',$row['assigned_projects']??0),
                $metric('active_assigned','المشروعات النشطة المسندة','Active assigned projects',$row['active_assigned']??0),
                $metric('pending_labor','مهام العمالة المفتوحة','Open labor tasks',$row['pending_labor']??0),
                $metric('upcoming_milestones','معالم بانتظار الإجراء','Milestones awaiting action',$row['upcoming_milestones']??0),
            ];
            break;

        default:
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'dashboard_not_available', 'message' => 'A native overview is not available for this role.']]);
    }
} catch (Throwable $e) {
    error_log('Mobile dashboard overview failed for role ' . $role . ': ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'dashboard_overview_failed', 'message' => 'Dashboard data could not be loaded.']]);
}

ak_api_json(200, ['ok' => true, 'data' => ['role_code' => $user['raw_role_code'] ?? $role, 'metrics' => $metrics]]);
