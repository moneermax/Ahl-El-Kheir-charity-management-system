<?php
// modules/administration/sponsorship_family_view.php - Sponsorship-safe family/orphan profile for Administration and management
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$role = Session::getUserRole();
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'administration'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = 'ملف الأسرة للكفالة';
$active = 'winback';
$familyId = (int)($_GET['id'] ?? 0);

$family = dbFetchOne("
    SELECT id, family_code, mother_name, city, children_count, monthly_need_amount, status
    FROM families
    WHERE id = ?
", [$familyId]);

if (!$family) {
    flash('error', 'الأسرة غير موجودة.');
    redirect('modules/administration/available_families.php');
}

$children = dbFetchAll("
    SELECT
        fc.id,
        fc.child_name,
        fc.gender,
        fc.birth_date,
        fc.nationality,
        fc.health_status,
        fc.health_status_other,
        fc.psychological_state,
        fc.psychological_state_other,
        fc.education_level,
        fc.monthly_sponsorship_value,
        fc.extra_allowance,
        fc.is_critical,
        fc.match_status,
        (
            SELECT sp.monthly_amount
            FROM sponsorships sp
            WHERE sp.child_id = fc.id
            ORDER BY sp.id DESC
            LIMIT 1
        ) AS last_sponsorship_amount,
        (
            SELECT sp.status
            FROM sponsorships sp
            WHERE sp.child_id = fc.id
            ORDER BY sp.id DESC
            LIMIT 1
        ) AS last_sponsorship_status,
        (
            SELECT sp.start_date
            FROM sponsorships sp
            WHERE sp.child_id = fc.id
            ORDER BY sp.id DESC
            LIMIT 1
        ) AS last_sponsorship_start
    FROM family_children fc
    WHERE fc.family_id = ?
    ORDER BY fc.birth_date ASC, fc.id ASC
", [$familyId]);

$genderLabels = ['male' => 'ذكر', 'female' => 'أنثى', 'unknown' => 'غير معروف'];
$matchLabels = [
    'matched' => ['مكفول', 'bg-success'],
    'waiting_list' => ['في الانتظار', 'bg-info'],
    'lost_sponsor' => ['فقد كفيله', 'bg-warning text-dark'],
    'unmatched' => ['غير مكفول', 'bg-secondary'],
];
$sponsorshipStatusLabels = [
    'active' => ['نشطة', 'bg-success'],
    'paused' => ['متوقفة', 'bg-warning text-dark'],
    'completed' => ['مكتملة', 'bg-info'],
    'cancelled' => ['ملغية', 'bg-danger'],
];

function ak_sponsorship_age(?string $birthDate): string
{
    if (!$birthDate) return '—';
    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime('today');
        if ($birth > $today) return '—';
        return (string)$birth->diff($today)->y . ' سنة';
    } catch (Throwable $e) {
        return '—';
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2><i class="fas fa-hand-holding-heart me-2"></i><?php echo e($family['mother_name']); ?></h2>
            <p>
                <?php echo e($family['family_code']); ?>
                <?php echo !empty($family['city']) ? ' · ' . e($family['city']) : ''; ?>
                · ملف مخصص لعرض المعلومات اللازمة لقرار الكفالة
            </p>
        </div>
        <a href="<?php echo APP_URL; ?>modules/administration/available_families.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i>العودة إلى الأسر المتاحة
        </a>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="alert alert-info fade-in">
    <i class="fas fa-shield-halved me-2"></i>
    يعرض هذا الملف المعلومات المرتبطة بقرار الكفالة فقط. بيانات الاتصال الخاصة، العنوان التفصيلي، الحسابات البنكية، الوثائق والملاحظات الداخلية غير معروضة هنا.
</div>

<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="text-muted small">كود الأسرة</div>
            <div class="fw-bold fs-5"><?php echo e($family['family_code']); ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="text-muted small">عدد الأطفال</div>
            <div class="fw-bold fs-4"><?php echo count($children); ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="text-muted small">المدينة / المنطقة العامة</div>
            <div class="fw-bold fs-5"><?php echo e($family['city'] ?: '—'); ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="text-muted small">احتياج الأسرة الشهري</div>
            <div class="fw-bold fs-4"><?php echo number_format((float)$family['monthly_need_amount'], 0); ?></div>
        </div></div>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header">
        <i class="fas fa-children me-2"></i>الأيتام / الأطفال المتاحون للمراجعة قبل عرض الكفالة
    </div>
    <div class="card-body">
        <?php if (!$children): ?>
            <div class="text-center text-muted py-5">لا يوجد أطفال مسجلون لهذه الأسرة.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($children as $child):
                    $match = $matchLabels[$child['match_status'] ?? 'unmatched'] ?? ['غير معروف', 'bg-secondary'];
                    $lastStatus = $sponsorshipStatusLabels[$child['last_sponsorship_status'] ?? ''] ?? [($child['last_sponsorship_status'] ?: 'لا توجد كفالة سابقة'), 'bg-secondary'];
                ?>
                <div class="col-12">
                    <div class="card border shadow-sm">
                        <div class="card-header sponsorship-child-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <strong class="fs-5"><?php echo e($child['child_name']); ?></strong>
                                <span class="badge <?php echo e($match[1]); ?> ms-2"><?php echo e($match[0]); ?></span>
                                <?php if ((int)($child['is_critical'] ?? 0) === 1): ?>
                                    <span class="badge bg-danger ms-1">حالة حرجة</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small">رقم الطفل: <?php echo (int)$child['id']; ?></div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3"><small class="text-muted">الجنس</small><div class="fw-semibold"><?php echo e($genderLabels[$child['gender'] ?? 'unknown'] ?? 'غير معروف'); ?></div></div>
                                <div class="col-md-3"><small class="text-muted">تاريخ الميلاد</small><div class="fw-semibold"><?php echo e($child['birth_date'] ?: '—'); ?></div></div>
                                <div class="col-md-3"><small class="text-muted">العمر</small><div class="fw-semibold"><?php echo e(ak_sponsorship_age($child['birth_date'] ?? null)); ?></div></div>
                                <div class="col-md-3"><small class="text-muted">الجنسية</small><div class="fw-semibold"><?php echo e($child['nationality'] ?: '—'); ?></div></div>

                                <div class="col-md-4">
                                    <small class="text-muted">الحالة الصحية</small>
                                    <div class="fw-semibold"><?php echo e($child['health_status'] ?: '—'); ?></div>
                                    <?php if (!empty($child['health_status_other'])): ?><div class="small mt-1"><?php echo e($child['health_status_other']); ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">الحالة النفسية</small>
                                    <div class="fw-semibold"><?php echo e($child['psychological_state'] ?: '—'); ?></div>
                                    <?php if (!empty($child['psychological_state_other'])): ?><div class="small mt-1"><?php echo e($child['psychological_state_other']); ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-4"><small class="text-muted">المستوى التعليمي</small><div class="fw-semibold"><?php echo e($child['education_level'] ?: '—'); ?></div></div>

                                <div class="col-md-4">
                                    <small class="text-muted">قيمة الكفالة الشهرية الحالية</small>
                                    <div class="fw-bold text-primary fs-5"><?php echo number_format((float)($child['monthly_sponsorship_value'] ?? 0), 0); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">بدل / دعم إضافي مسجل</small>
                                    <div class="fw-semibold"><?php echo number_format((float)($child['extra_allowance'] ?? 0), 0); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">آخر قيمة كفالة سابقة</small>
                                    <div class="fw-semibold"><?php echo $child['last_sponsorship_amount'] !== null ? number_format((float)$child['last_sponsorship_amount'], 0) : '—'; ?></div>
                                </div>

                                <div class="col-md-4"><small class="text-muted">حالة آخر كفالة</small><div><span class="badge <?php echo e($lastStatus[1]); ?>"><?php echo e($lastStatus[0]); ?></span></div></div>
                                <div class="col-md-4"><small class="text-muted">بداية آخر كفالة</small><div class="fw-semibold"><?php echo e($child['last_sponsorship_start'] ?: '—'); ?></div></div>
                                <div class="col-md-4"><small class="text-muted">حالة المطابقة المسجلة</small><div class="fw-semibold"><?php echo e($match[0]); ?></div></div>
                            </div>

                            <?php if (!empty($child['health_status_other']) || !empty($child['psychological_state_other']) || (int)($child['is_critical'] ?? 0) === 1): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-circle-exclamation me-2"></i>
                                توجد معلومات صحية / نفسية أو حالة حرجة مسجلة لهذا الطفل. يجب مراجعتها وشرح المعلومات ذات الصلة للكفيل قبل تأكيد موافقته على الكفالة.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
