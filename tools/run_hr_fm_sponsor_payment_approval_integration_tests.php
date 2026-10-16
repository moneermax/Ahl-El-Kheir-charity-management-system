<?php
declare(strict_types=1);

/** Rollback-only test for the shared FM sponsor-payment approval operation. */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib_sponsor_payment_approval.php';

function fmApprovalTestAssert(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
function fmApprovalTestOne(PDO $pdo,string $sql,array $params=[]): ?array { $s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null; }
function fmApprovalTestCount(PDO $pdo,string $sql,array $params=[]): int { $s=$pdo->prepare($sql);$s->execute($params);return (int)$s->fetchColumn(); }

$pdo=db();$dbName=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if($dbName!=='ahl_el_kheir_incentive_test_20261015_r1'){fwrite(STDERR,"REFUSED | expected disposable DB ahl_el_kheir_incentive_test_20261015_r1; connected={$dbName}\n");exit(2);}
$r5=fmApprovalTestOne($pdo,"SELECT p.id FROM hr_incentive_policy_versions p JOIN hr_incentive_policy_rules r ON r.policy_version_id=p.id WHERE p.policy_name='ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5' AND p.status='active' AND p.effective_from<=CURDATE() AND (p.effective_to IS NULL OR p.effective_to>=CURDATE()) AND r.incentive_type='new_sponsor' AND r.is_enabled=1 ORDER BY p.version_no DESC LIMIT 1");
$recipient=fmApprovalTestOne($pdo,"SELECT s.id AS sponsor_id,s.brought_by_user_id,s.created_at,MIN(e.id) AS employee_id FROM sponsors s JOIN users u ON u.id=s.brought_by_user_id AND u.is_active=1 JOIN employees e ON e.user_id=u.id WHERE s.brought_by_user_id IS NOT NULL AND s.created_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sponsor_payments sp LEFT JOIN sponsorships sx ON sx.id=sp.sponsorship_id WHERE sp.payment_type='monthly_sponsorship' AND sp.status='approved' AND COALESCE(sp.sponsor_id,sx.sponsor_id)=s.id) AND NOT EXISTS (SELECT 1 FROM winback_campaigns wc WHERE wc.sponsor_id=s.id AND wc.status='persuaded') GROUP BY s.id,s.brought_by_user_id,s.created_at HAVING COUNT(e.id)=1 ORDER BY s.id LIMIT 1");
$reviewer=$recipient?fmApprovalTestOne($pdo,'SELECT id FROM users WHERE is_active=1 AND id<>? ORDER BY id LIMIT 1',[(int)$recipient['brought_by_user_id']]):null;
$accounts=fmApprovalTestOne($pdo,"SELECT (SELECT id FROM accounts WHERE code='1100' AND is_active=1 LIMIT 1) AS cash_id,(SELECT id FROM accounts WHERE code='4100' AND is_active=1 LIMIT 1) AS revenue_id");
if(!$r5||!$recipient||!$reviewer||!$accounts||(int)$accounts['cash_id']<=0||(int)$accounts['revenue_id']<=0){echo "SKIP | missing active R5 rule, eligible sponsor attribution, reviewer, or accounts; no fixtures written.\n";exit(0);}
$currency=strtoupper(trim((string)APP_CURRENCY_CODE));if(!preg_match('/^[A-Z]{3}$/',$currency)){echo "SKIP | invalid configured currency; no fixtures written.\n";exit(0);}
$sponsorId=(int)$recipient['sponsor_id'];$collectorUserId=(int)$recipient['brought_by_user_id'];$employeeId=(int)$recipient['employee_id'];$reviewerId=(int)$reviewer['id'];
$paymentIds=[];$transactionIds=[];$journalIds=[];$eventIds=[];
$paymentInsert=$pdo->prepare("INSERT INTO sponsor_payments (payment_type,sponsorship_id,sponsor_id,supervisor_id,payment_period,amount,currency_code,status,reviewed_by_user_id,reviewed_at,transaction_id,created_at) VALUES ('monthly_sponsorship',NULL,?,?,?,?,?,'pending',NULL,NULL,NULL,?)");
$makePending=static function()use($pdo,$paymentInsert,$sponsorId,$collectorUserId,$currency,&$paymentIds):int{$now=new DateTimeImmutable();$stamp=$now->format('Y-m-d H:i:s');$paymentInsert->execute([$sponsorId,$collectorUserId,$now->format('M/Y'),100.00,$currency,$stamp]);$id=(int)$pdo->lastInsertId();$paymentIds[]=$id;return $id;};
$assertClean=static function()use($pdo,&$paymentIds,&$transactionIds,&$journalIds,&$eventIds):void{
 foreach($paymentIds as $id){fmApprovalTestAssert(fmApprovalTestCount($pdo,'SELECT COUNT(*) FROM sponsor_payments WHERE id=?',[$id])===0,'rollback left sponsor payment '.$id);fmApprovalTestAssert(fmApprovalTestCount($pdo,"SELECT COUNT(*) FROM hr_incentive_events WHERE source_table='sponsor_payments' AND source_record_id=?",[$id])===0,'rollback left a source event for payment '.$id);}
 foreach($transactionIds as $id)fmApprovalTestAssert(fmApprovalTestCount($pdo,'SELECT COUNT(*) FROM transactions WHERE id=?',[$id])===0,'rollback left transaction '.$id);
 foreach($journalIds as $id)fmApprovalTestAssert(fmApprovalTestCount($pdo,'SELECT COUNT(*) FROM journal_entries WHERE id=?',[$id])===0,'rollback left journal '.$id);
 foreach($eventIds as $id)fmApprovalTestAssert(fmApprovalTestCount($pdo,'SELECT COUNT(*) FROM hr_incentive_events WHERE id=?',[$id])===0,'rollback left incentive event '.$id);
};
try{
 $pdo->beginTransaction();$paymentId=$makePending();$result=ak_approve_sponsor_payment($pdo,$paymentId,$reviewerId);
 $transactionIds[]=(int)$result['transaction_id'];$journalIds[]=(int)$result['journal_id'];
 $approved=fmApprovalTestOne($pdo,'SELECT status,transaction_id,reviewed_at FROM sponsor_payments WHERE id=?',[$paymentId]);
 fmApprovalTestAssert($approved&&$approved['status']==='approved'&&(int)$approved['transaction_id']===(int)$result['transaction_id']&&!empty($approved['reviewed_at']),'approval state/link missing');
 fmApprovalTestAssert(fmApprovalTestCount($pdo,"SELECT COUNT(*) FROM journal_entries WHERE id=? AND reference_type='transaction' AND reference_id=? AND status='posted'",[(int)$result['journal_id'],(int)$result['transaction_id']])===1,'posted transaction journal missing');
 $event=fmApprovalTestOne($pdo,"SELECT id,status,recipient_user_id,recipient_employee_id FROM hr_incentive_events WHERE source_table='sponsor_payments' AND source_record_id=? AND incentive_type='new_sponsor' LIMIT 1",[$paymentId]);
 fmApprovalTestAssert($event&&$event['status']==='pending_verification'&&(int)$event['recipient_user_id']===$collectorUserId&&(int)$event['recipient_employee_id']===$employeeId,'new-sponsor event or recipient snapshot missing');
 $eventIds[]=(int)$event['id'];fmApprovalTestAssert(fmApprovalTestCount($pdo,'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id=?',[(int)$event['id']])===0,'approval issued an award before HR verification');
 $pdo->rollBack();$assertClean();
 echo "PASS | shared_FM_approval=PASS | posted_transaction_journal=PASS\n";
 echo "PASS | new_sponsor_pending_verification=PASS | no_premature_award=PASS | success_rollback=PASS\n";

 $paymentIds=[];$transactionIds=[];$journalIds=[];$eventIds=[];$pdo->beginTransaction();$failedPaymentId=$makePending();$failureTransactionId=0;$failureJournalId=0;
 try{ak_approve_sponsor_payment($pdo,$failedPaymentId,$reviewerId,static function(PDO $connection,int $id,int $actor)use(&$failureTransactionId,&$failureJournalId):void{
   $row=fmApprovalTestOne($connection,'SELECT transaction_id FROM sponsor_payments WHERE id=?',[$id]);$failureTransactionId=(int)($row['transaction_id']??0);
   if($failureTransactionId>0){$j=fmApprovalTestOne($connection,"SELECT id FROM journal_entries WHERE reference_type='transaction' AND reference_id=? AND status='posted' LIMIT 1",[$failureTransactionId]);$failureJournalId=(int)($j['id']??0);}
   throw new RuntimeException('INJECTED_NEW_SPONSOR_EVENT_FAILURE');
 });throw new RuntimeException('injected failure seam was not reached');}
 catch(RuntimeException $e){fmApprovalTestAssert($e->getMessage()==='INJECTED_NEW_SPONSOR_EVENT_FAILURE','unexpected failure before injected seam: '.$e->getMessage());}
 fmApprovalTestAssert($failureTransactionId>0&&$failureJournalId>0,'failure was not injected after transaction and journal creation');
 $transactionIds[]=$failureTransactionId;$journalIds[]=$failureJournalId;$pdo->rollBack();$assertClean();
 echo "PASS | injected_incentive_failure=PASS | payment_transaction_journal_rollback=PASS\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"FAIL | FM sponsor-payment approval integration | ".$e->getMessage()."\n");exit(1);}
