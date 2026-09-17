<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib_transaction_review.php';
require_once __DIR__ . '/fina_lib.php';
require_once dirname(__DIR__, 2) . '/config/messaging.php';

Session::start();
$role = Session::getUserRole();
$uid = (int) Session::getUserId();

if (!ak_transaction_review_is_fm($role)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    flash('error', 'طلب الإرجاع غير صالح.');
    header('Location: ' . APP_URL . 'modules/accounting/fina_payment_review.php');
    exit();
}

$id = (int) ($_POST['return_fina'] ?? 0);
$note = trim($_POST['return_note'] ?? '');

if ($id <= 0 || $note === '') {
    flash('error', 'يجب كتابة سبب الإرجاع.');
    header('Location: ' . APP_URL . 'modules/accounting/fina_payment_review.php');
    exit();
}

$q = dbFetchOne(
    "SELECT id, created_by, amount, currency_code
     FROM fina_collections
     WHERE id = ? AND status = 'pending'",
    [$id]
);

if (!$q) {
    flash('error', 'طلب فينا الخير غير موجود أو تمت معالجته.');
    header('Location: ' . APP_URL . 'modules/accounting/fina_payment_review.php');
    exit();
}

if ((int) $q['created_by'] === $uid) {
    flash('error', 'لا يجوز للمنشئ إرجاع تحصيله بنفسه.');
    header('Location: ' . APP_URL . 'modules/accounting/fina_payment_review.php');
    exit();
}

$updated = dbExecute(
    "UPDATE fina_collections
     SET status = 'returned', reviewed_by = ?, reviewed_at = NOW(), return_note = ?
     WHERE id = ? AND status = 'pending'",
    [$uid, $note, $id]
);

if ($updated !== 1) {
    flash('error', 'تعذر إرجاع تحصيل فينا الخير. ربما تمت معالجته بالفعل.');
    header('Location: ' . APP_URL . 'modules/accounting/fina_payment_review.php');
    exit();
}

send_system_notification(
    (int) $q['created_by'],
    'تم إرجاع تحصيل فينا الخير',
    'تم إرجاع تحصيل فينا الخير رقم #' . $id . ' بمبلغ ' . number_format((float) $q['amount'], 2) . ' ' . $q['currency_code'] . '. سبب الإرجاع: ' . $note,
    'warning'
);

flash('success', 'تم إرجاع تحصيل فينا الخير وإرسال إشعار للمستخدم المنشئ.');
header('Location: ' . APP_URL . 'modules/accounting/fina_payment_review.php');
exit();
