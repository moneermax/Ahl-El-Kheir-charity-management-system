<?php
// modules/families/delete.php - Delete a family only when it has zero actual orphans.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();

// Deletion is intentionally limited to senior management/admin roles.
if (!in_array($role, ['admin', 'vice_general_manager'], true)) {
    flash('error', 'ليس لديك صلاحية حذف الأسر.');
    redirect('modules/families/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'طلب غير صالح.');
    redirect('modules/families/index.php');
}

if (!verify_csrf()) {
    flash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
    redirect('modules/families/index.php');
}

$familyId = (int)($_POST['family_id'] ?? 0);

if ($familyId <= 0) {
    flash('error', 'معرّف الأسرة غير صالح.');
    redirect('modules/families/index.php');
}

$fam = dbFetchOne(
    "SELECT id, family_code, mother_name FROM families WHERE id = ? LIMIT 1",
    [$familyId]
);

if (!$fam) {
    flash('error', 'الأسرة غير موجودة.');
    redirect('modules/families/index.php');
}

// The authoritative relationship is family_children. Never rely on children_count.
$childRow = dbFetchOne(
    "SELECT COUNT(*) AS c FROM family_children WHERE family_id = ?",
    [$familyId]
);
$childCount = (int)($childRow['c'] ?? 0);

if ($childCount > 0) {
    flash('error', 'لا يمكن حذف الأسرة لأنها تحتوي على ' . $childCount . ' يتيم/أيتام مسجلين.');
    redirect('modules/families/index.php');
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Re-check inside the transaction so a stale list page cannot delete a family
    // after an orphan has been added in another request.
    $lock = dbFetchOne(
        "SELECT id, family_code, mother_name FROM families WHERE id = ? FOR UPDATE",
        [$familyId]
    );

    if (!$lock) {
        $pdo->rollBack();
        flash('error', 'الأسرة غير موجودة.');
        redirect('modules/families/index.php');
    }

    $lockedChildRow = dbFetchOne(
        "SELECT COUNT(*) AS c FROM family_children WHERE family_id = ?",
        [$familyId]
    );
    $lockedChildCount = (int)($lockedChildRow['c'] ?? 0);

    if ($lockedChildCount > 0) {
        $pdo->rollBack();
        flash('error', 'لا يمكن حذف الأسرة لأن لديها الآن ' . $lockedChildCount . ' يتيم/أيتام مسجلين.');
        redirect('modules/families/index.php');
    }

    // Let the database enforce all remaining foreign-key dependencies.
    // This is safer than manually deleting related business/financial records.
    dbExecute("DELETE FROM families WHERE id = ?", [$familyId]);

    $pdo->commit();

    flash('success', 'تم حذف الأسرة ' . (string)$fam['family_code'] . ' (' . (string)$fam['mother_name'] . ') بنجاح.');
} catch (Throwable $e) {
    try {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable $rollbackError) {
        error_log('Family deletion rollback error: ' . $rollbackError->getMessage());
    }

    error_log('Family deletion failed for ID ' . $familyId . ': ' . $e->getMessage());
    flash('error', 'لم يتم حذف الأسرة. قد تكون مرتبطة بسجلات أخرى في النظام. تم الحفاظ على البيانات ولم يتم حذف أي سجلات مرتبطة.');
}

redirect('modules/families/index.php');
