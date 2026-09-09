<?php
// modules/transactions/cancel_returned.php - Cancel a returned ordinary transaction
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$uid=(int)Session::getUserId();
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf()) { header('Location: ' . APP_URL . 'modules/transactions/index.php'); exit(); }
$tid=(int)($_POST['transaction_id']??0);
$reason=trim($_POST['cancel_reason']??'') ?: 'إلغاء من المنشئ بعد الإرجاع';
$t=dbFetchOne("SELECT * FROM transactions WHERE id=? AND status='returned' AND created_by=?",[$tid,$uid]);
if(!$t){ flash('error','لا يمكن إلغاء هذه الدفعة. يجب أن تكون مُعادة إليك وأن تكون أنت منشئها.'); header('Location: ' . APP_URL . 'modules/transactions/index.php'); exit(); }
try {
    db()->beginTransaction();
    dbExecute("UPDATE transactions SET status='cancelled', cancelled_at=NOW(), cancelled_by=?, cancel_reason=? WHERE id=? AND status='returned' AND created_by=?",[$uid,$reason,$tid,$uid]);
    if(db()->lastInsertId()!=='' ) {} // no-op; keep PDO transaction compatibility
    ak_transaction_review_audit($uid,'CANCEL_RETURNED',$tid,$t,['status'=>'cancelled','cancel_reason'=>$reason]);
    db()->commit();
    ak_transaction_review_delete_receipt_if_unreferenced($t['receipt_path']??null);
    ak_transaction_review_delete_receipt_if_unreferenced($t['unified_receipt_path']??null);
    flash('success','تم إلغاء الدفعة المُعادة وحذف الإيصالات غير المستخدمة مع الحفاظ على سجل التدقيق.');
} catch(Throwable $e) {
    if(db()->inTransaction()) db()->rollBack();
    flash('error','تعذر إلغاء الدفعة بشكل ذري.');
}
header('Location: ' . APP_URL . 'modules/transactions/index.php'); exit();
