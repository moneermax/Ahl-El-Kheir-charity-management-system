<?php
// modules/accounting/fina_dashboard.php — Dedicated Feena Al-Khair dashboard
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/fina_lib.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'login.php'); exit; }

$role = (string) Session::getUserRole();
if (!in_array($role, ['financial_manager', 'fm', 'admin', 'general_manager', 'vice_general_manager', 'supervisor'], true)) {
    http_response_code(403);
    $pageTitle = 'لوحة منظمة فينا الخير'; $active = 'fina_dashboard';
    include dirname(__DIR__, 2) . '/includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger"><i class="fas fa-lock me-1"></i>غير مصرح لك بالوصول إلى لوحة منظمة فينا الخير.</div></div>';
    include dirname(__DIR__, 2) . '/includes/footer.php'; exit;
}

fina_ensure_tables();
$liabilityId = fina_ensure_liability_account();
$pending = dbFetchOne("SELECT COUNT(*) AS cnt FROM fina_collections WHERE status='pending'");
$approved = dbFetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amount FROM fina_collections WHERE status='approved'");
$returned = dbFetchOne("SELECT COUNT(*) AS cnt FROM fina_collections WHERE status='returned'");
$account = dbFetchOne("SELECT code,is_active FROM accounts WHERE id=?", [$liabilityId]);

$pageTitle = 'لوحة منظمة فينا الخير';
$active = 'fina_dashboard';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.fina-page-header{background:linear-gradient(135deg,#4d2a73 0%,#6f42c1 100%);color:#fff;border-radius:14px;padding:18px 20px;margin-top:-6px;margin-bottom:18px}
.fina-brand{display:flex;align-items:center;gap:14px}.fina-brand-logo{width:64px;height:64px;object-fit:contain;border-radius:10px;background:#fff;border:1px solid rgba(255,255,255,.5);padding:4px}
.fina-name-ar{font-size:1.35rem;font-weight:800;line-height:1.2}.fina-name-en{font-size:.8rem;font-weight:600;opacity:.9;margin-top:3px}.fina-page-header p{margin:5px 0 0;opacity:.9;font-size:.84rem}
.fina-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:20px}.fina-stat{background:#fff;border-radius:12px;padding:18px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.06);border-top:4px solid #6f42c1}
.fina-stat.amber{border-top-color:#ffc107}.fina-stat.green{border-top-color:#28a745}.fina-stat.red{border-top-color:#dc3545}.fina-stat-value{font-size:1.55rem;font-weight:800;color:#4d2a73;margin:6px 0}.fina-stat-label{font-size:.84rem;color:#666}.fina-stat-sub{font-size:.72rem;color:#999;margin-top:4px}
.fina-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:20px}.fina-action{background:#fff;border-radius:12px;padding:20px 15px;text-decoration:none;color:inherit;box-shadow:0 2px 10px rgba(0,0,0,.06);text-align:center;border-top:3px solid #6f42c1;transition:transform .18s,box-shadow .18s}
.fina-action:hover{transform:translateY(-2px);box-shadow:0 5px 14px rgba(0,0,0,.12);color:inherit}.fina-action i{font-size:1.5rem;color:#6f42c1;margin-bottom:8px}.fina-action-title{font-weight:700;font-size:.9rem}.fina-action-desc{font-size:.72rem;color:#777;margin-top:4px}
.fina-note{background:#f8f5fc;border:1px solid #e5d9f2;border-radius:10px;padding:13px 15px;color:#5b4670}
@media(max-width:991.98px){.fina-stat-grid,.fina-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:575.98px){.fina-stat-grid,.fina-actions{grid-template-columns:1fr}.fina-brand-logo{width:56px;height:56px}.fina-name-ar{font-size:1.15rem}}
</style>
<div class="container-fluid">
<div class="fina-page-header"><div class="fina-brand">
<img src="<?php echo APP_URL; ?>assets/img/Feen_logo.jpeg" alt="منظمة فينا الخير — Feena Al-Khair" class="fina-brand-logo">
<div><div class="fina-name-ar">منظمة فينا الخير</div><div class="fina-name-en">Feena Al-Khair</div><p>لوحة مستقلة لمتابعة تحصيلات وتسويات أموال منظمة فينا الخير.</p></div>
</div></div>
<div class="fina-stat-grid">
<div class="fina-stat amber"><div class="fina-stat-value"><?php echo (int)($pending['cnt'] ?? 0); ?></div><div class="fina-stat-label">طلبات بانتظار المراجعة</div><div class="fina-stat-sub">مراجعة المدير المالي</div></div>
<div class="fina-stat green"><div class="fina-stat-value"><?php echo (int)($approved['cnt'] ?? 0); ?></div><div class="fina-stat-label">تحصيلات معتمدة</div><div class="fina-stat-sub">التحصيلات المعتمدة فقط</div></div>
<div class="fina-stat red"><div class="fina-stat-value"><?php echo (int)($returned['cnt'] ?? 0); ?></div><div class="fina-stat-label">تحصيلات مرتجعة</div><div class="fina-stat-sub">مستبعدة من إجمالي المعتمد</div></div>
<div class="fina-stat"><div class="fina-stat-value"><?php echo number_format((float)($approved['amount'] ?? 0),2); ?> <?php echo e(APP_CURRENCY_CODE); ?></div><div class="fina-stat-label">إجمالي التحصيلات المعتمدة</div><div class="fina-stat-sub">حساب الالتزام <?php echo e($account['code'] ?? '2300'); ?> — <?php echo !empty($account['is_active']) ? 'نشط' : 'موقوف'; ?></div></div>
</div>
<div class="fina-actions">
<a class="fina-action" href="<?php echo APP_URL; ?>modules/accounting/fina_payment_review.php"><i class="fas fa-clipboard-check d-block"></i><div class="fina-action-title">مراجعة منظمة فينا الخير</div><div class="fina-action-desc">اعتماد أو إرجاع التحصيلات</div></a>
<a class="fina-action" href="<?php echo APP_URL; ?>modules/accounting/fina_settlements.php"><i class="fas fa-money-bill-transfer d-block"></i><div class="fina-action-title">تسويات منظمة فينا الخير</div><div class="fina-action-desc">متابعة دورات التسوية والتحويل</div></a>
<a class="fina-action" href="<?php echo APP_URL; ?>modules/accounting/fina_payment_history.php"><i class="fas fa-clock-rotate-left d-block"></i><div class="fina-action-title">سجل منظمة فينا الخير</div><div class="fina-action-desc">السجل التاريخي للتحصيلات</div></a>
<a class="fina-action" href="<?php echo APP_URL; ?>modules/accounting/fina_payment_report.php"><i class="fas fa-file-chart-column d-block"></i><div class="fina-action-title">تقرير منظمة فينا الخير</div><div class="fina-action-desc">التقارير والتحليلات المالية</div></a>
</div>
<div class="fina-note"><i class="fas fa-circle-info me-1"></i>أموال منظمة فينا الخير مستقلة عن إيرادات الكفالات والرسوم الإدارية، ويظل حساب الالتزام <?php echo e($account['code'] ?? '2300'); ?> مخصصاً لها.</div>
<div class="container-fluid px-0 pb-4 mt-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/accounting/fm_dashboard.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة إلى المدير المالي</a></div></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>