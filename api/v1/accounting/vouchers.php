<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_vouchers.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
// Match the existing website's exact persisted-role allowlist; do not widen via aliases.
$role = (string)($user['raw_role_code'] ?? '');
if (!ak_voucher_can('view', $role)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Voucher access is not allowed for this account.']]);
}

$type = trim((string)($_GET['vtype'] ?? ''));
if ($type !== '' && !in_array($type, ['receipt', 'payment'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_voucher_type', 'message' => 'Voucher type must be receipt or payment.']]);
}

$pdo = db();
if ($method === 'POST') {
    $input = ak_api_read_json();
    $action = trim((string)($input['action'] ?? ''));
    if ($action === 'issue_voucher') {
        if (!ak_voucher_can('issue', $role)) ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Voucher issuance is not allowed for this account.']]);
        $type=(string)($input['voucher_type']??''); $date=trim((string)($input['voucher_date']??'')); $amountInput=$input['amount']??null;
        $cashId=filter_var($input['cash_account_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $otherId=filter_var($input['other_account_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $party=trim((string)($input['party_name']??'')); $description=trim((string)($input['description']??'')); $reference=trim((string)($input['reference_number']??''));
        $dateObject=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!in_array($type,['receipt','payment'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_voucher_type','message'=>'Voucher type must be receipt or payment.']]);
        if(!$dateObject||$dateObject->format('Y-m-d')!==$date||$date>date('Y-m-d')) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_voucher_date','message'=>'Voucher date must be valid and cannot be in the future.']]);
        if(!is_numeric($amountInput)||!is_finite((float)$amountInput)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_voucher_amount','message'=>'Voucher amount must be a valid number.']]);
        $amount=round((float)$amountInput,2);
        if($amount<=0||$amount>999999999999.99) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_voucher_amount','message'=>'Voucher amount must be greater than zero and within the allowed limit.']]);
        if($cashId===false||$cashId===null) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_cash_account','message'=>'Choose a cash, bank, or wallet account.']]);
        if($otherId===false||$otherId===null) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_other_account','message'=>'Choose a valid counter-account.']]);
        if($party===''||mb_strlen($party,'UTF-8')>150) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_party_name','message'=>'Party name is required and must not exceed 150 characters.']]);
        if(mb_strlen($description,'UTF-8')<3||mb_strlen($description,'UTF-8')>255) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_description','message'=>'Description must contain 3 to 255 characters.']]);
        if(mb_strlen($reference,'UTF-8')>100) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_reference','message'=>'Reference must not exceed 100 characters.']]);
        $locked=false;
        try {
            $locked=ak_voucher_lock();
            if(!$locked) ak_api_json(409,['ok'=>false,'error'=>['code'=>'voucher_lock_busy','message'=>'Voucher numbering is busy. Please try again.']]);
            $pdo->beginTransaction();
            $stmt=$pdo->prepare("SELECT id FROM accounts WHERE id=? AND code IN ('1100','1200','1300') FOR UPDATE"); $stmt->execute([$cashId]);
            if(!$stmt->fetchColumn()) throw new DomainException('Choose a valid cash, bank, or wallet account.');
            $stmt=$pdo->prepare('SELECT id,account_type,is_active FROM accounts WHERE id=? FOR UPDATE'); $stmt->execute([$otherId]); $other=$stmt->fetch(PDO::FETCH_ASSOC);
            $required=$type==='receipt'?'revenue':'expense';
            if(!$other||(int)$other['is_active']!==1) throw new DomainException('The counter-account does not exist or is inactive.');
            if((string)$other['account_type']!==$required) throw new DomainException($type==='receipt'?'A receipt voucher must use a revenue counter-account.':'A payment voucher must use an expense counter-account.');
            if($type==='payment') { $available=ak_voucher_cash_balance((int)$cashId); if($amount>$available+0.000001) throw new DomainException('Insufficient balance in the selected cash account. Available: '.number_format($available,2)); }
            $no=ak_voucher_next_number($type);
            $stmt=$pdo->prepare("INSERT INTO vouchers (voucher_type,voucher_no,voucher_date,party_name,amount,cash_account_id,other_account_id,description,reference_number,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,'posted',?)");
            $stmt->execute([$type,$no,$date,$party,$amount,(int)$cashId,(int)$otherId,$description,$reference!==''?$reference:null,(int)$user['id']]); $voucherId=(int)$pdo->lastInsertId();
            if($voucherId<=0) throw new RuntimeException('Voucher creation failed.');
            $entryCode=ak_voucher_next_journal_code(); $label=($type==='receipt'?'سند قبض ':'سند صرف ').$no.' — '.$party;
            $stmt=$pdo->prepare("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,'voucher',?,'posted',?)");
            $stmt->execute([$entryCode,$date,mb_substr($label,0,250),$voucherId,(int)$user['id']]); $entryId=(int)$pdo->lastInsertId();
            if($entryId<=0) throw new RuntimeException('Voucher journal entry creation failed.');
            $line=$pdo->prepare('INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)');
            $line->execute([$entryId,$type==='receipt'?(int)$cashId:(int)$otherId,$amount,0,mb_substr($label,0,250)]);
            $line->execute([$entryId,$type==='receipt'?(int)$otherId:(int)$cashId,0,$amount,mb_substr($label,0,250)]);
            $stmt=$pdo->prepare('SELECT COUNT(*) n,COALESCE(SUM(debit),0) d,COALESCE(SUM(credit),0) c FROM journal_lines WHERE entry_id=?'); $stmt->execute([$entryId]); $totals=$stmt->fetch(PDO::FETCH_ASSOC);
            if((int)$totals['n']!==2||abs((float)$totals['d']-$amount)>0.000001||abs((float)$totals['c']-$amount)>0.000001) throw new RuntimeException('Voucher journal entry failed balance verification.');
            $pdo->prepare('UPDATE vouchers SET entry_id=? WHERE id=?')->execute([$entryId,$voucherId]);
            try { $stmt=$pdo->prepare("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,'CREATE','vouchers',?,NULL,?,?,?)"); $stmt->execute([(int)$user['id'],$voucherId,json_encode(['no'=>$no,'type'=>$type,'amount'=>$amount,'entry'=>$entryCode],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']); } catch(Throwable $auditError) {}
            $pdo->commit();
            ak_api_json(201,['ok'=>true,'data'=>['id'=>$voucherId,'voucher_no'=>$no,'voucher_type'=>$type,'amount'=>$amount,'entry_id'=>$entryId,'entry_code'=>$entryCode,'status'=>'posted']]);
        } catch(DomainException $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            ak_api_json(422,['ok'=>false,'error'=>['code'=>'voucher_issue_rejected','message'=>$e->getMessage()]]);
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log('Mobile voucher issue failed: '.$e->getMessage());
            ak_api_json(500,['ok'=>false,'error'=>['code'=>'voucher_issue_failed','message'=>'The voucher could not be issued. No changes were committed.']]);
        } finally { if($locked) ak_voucher_unlock(); }
    }
    if ($action === 'void_voucher') {
        if (!ak_voucher_can('void',$role)) ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Voucher voiding is not allowed for this account.']]);
        $id=filter_var($input['voucher_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $reason=trim((string)($input['reason']??''));
        if($id===false||$id===null) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_voucher_id','message'=>'A valid voucher ID is required.']]);
        if(mb_strlen($reason,'UTF-8')<3) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_void_reason','message'=>'A void reason of at least 3 characters is required.']);
        $locked=false;
        try {
            $locked=ak_voucher_lock();
            if(!$locked) ak_api_json(409,['ok'=>false,'error'=>['code'=>'voucher_lock_busy','message'=>'Voucher numbering is busy. Please try again.']]);
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('SELECT id,voucher_no,voucher_type,amount,cash_account_id,status,entry_id FROM vouchers WHERE id=? FOR UPDATE'); $stmt->execute([(int)$id]); $v=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$v||$v['status']!=='posted') throw new DomainException('Voucher does not exist or is not posted.');
            if(empty($v['entry_id'])) throw new DomainException('Voucher has no linked journal entry.');
            $stmt=$pdo->prepare("SELECT id,entry_date,status FROM journal_entries WHERE id=? AND reference_type='voucher' AND reference_id=? FOR UPDATE"); $stmt->execute([(int)$v['entry_id'],(int)$id]); $je=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$je) throw new DomainException('The linked journal entry is missing or has an invalid reference.');
            if($je['status']!=='posted') throw new DomainException('The linked journal entry is not posted.');
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type='voucher' AND reference_id=?"); $stmt->execute([(int)$id]);
            if((int)$stmt->fetchColumn()!==1) throw new DomainException('The voucher must have exactly one linked journal entry.');
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type='voucher_void' AND reference_id=?"); $stmt->execute([(int)$id]);
            if((int)$stmt->fetchColumn()!==0) throw new DomainException('A reversal entry already exists for this voucher.');
            $stmt=$pdo->prepare('SELECT account_id,debit,credit FROM journal_lines WHERE entry_id=? ORDER BY id FOR UPDATE'); $stmt->execute([(int)$je['id']]); $lines=$stmt->fetchAll(PDO::FETCH_ASSOC);
            if(count($lines)<2) throw new DomainException('The original journal entry has too few lines.');
            $debit=0.0;$credit=0.0;foreach($lines as $line){$debit+=(float)$line['debit'];$credit+=(float)$line['credit'];}
            if($debit<=0||abs($debit-$credit)>0.000001) throw new DomainException('The original journal entry is not balanced.');
            if($v['voucher_type']==='receipt') { $available=ak_voucher_cash_balance((int)$v['cash_account_id']); if((float)$v['amount']>$available+0.000001) throw new DomainException('The current cash balance is below the receipt amount; this voucher cannot be voided.'); }
            $code=ak_voucher_next_journal_code(); $label='عكس سند '.$v['voucher_no'].' — '.$reason;
            $stmt=$pdo->prepare("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,'voucher_void',?,'posted',?)");
            $stmt->execute([$code,$je['entry_date'],mb_substr($label,0,250),(int)$id,(int)$user['id']]); $reverseId=(int)$pdo->lastInsertId();
            if($reverseId<=0) throw new RuntimeException('Voucher reversal journal entry creation failed.');
            $stmt=$pdo->prepare('INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)');
            foreach($lines as $line) $stmt->execute([$reverseId,(int)$line['account_id'],(float)$line['credit'],(float)$line['debit'],mb_substr($label,0,250)]);
            $stmt=$pdo->prepare('SELECT COUNT(*) n,COALESCE(SUM(debit),0) d,COALESCE(SUM(credit),0) c FROM journal_lines WHERE entry_id=?'); $stmt->execute([$reverseId]); $totals=$stmt->fetch(PDO::FETCH_ASSOC);
            if((int)$totals['n']!==count($lines)||(float)$totals['d']<=0||abs((float)$totals['d']-(float)$totals['c'])>0.000001) throw new RuntimeException('Voucher reversal journal entry failed balance verification.');
            $stmt=$pdo->prepare("UPDATE vouchers SET status='voided' WHERE id=? AND status='posted'"); $stmt->execute([(int)$id]);
            if($stmt->rowCount()!==1) throw new DomainException('Voucher status changed before it could be voided.');
            try { $stmt=$pdo->prepare("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,'VOID','vouchers',?,?,?,?,?)"); $stmt->execute([(int)$user['id'],(int)$id,json_encode(['status'=>'posted','entry_id'=>(int)$je['id']],JSON_UNESCAPED_UNICODE),json_encode(['status'=>'voided','entry_id'=>(int)$je['id'],'reversal_journal_id'=>$reverseId,'reason'=>$reason],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']); } catch(Throwable $auditError) {}
            $pdo->commit();
            ak_api_json(200,['ok'=>true,'data'=>['id'=>(int)$id,'status'=>'voided','reversal_entry_id'=>$reverseId,'reversal_entry_code'=>$code]]);
        } catch(DomainException $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            ak_api_json(422,['ok'=>false,'error'=>['code'=>'voucher_void_rejected','message'=>$e->getMessage()]]);
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log('Mobile voucher void failed: '.$e->getMessage());
            ak_api_json(500,['ok'=>false,'error'=>['code'=>'voucher_void_failed','message'=>'The voucher could not be voided. No changes were committed.']]);
        } finally { if($locked) ak_voucher_unlock(); }
    }
    ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_voucher_action','message'=>'A supported voucher action is required.']]);
}
try {
    $cashAccounts = $pdo->query("SELECT id, code, name_ar FROM accounts WHERE code IN ('1100','1200','1300') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
    $balanceStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0) AS balance
         FROM journal_lines jl
         JOIN journal_entries je ON je.id = jl.entry_id
         WHERE jl.account_id = ? AND je.status = 'posted'"
    );
    $otherAccounts = $pdo->query("SELECT id, code, name_ar, account_type FROM accounts WHERE account_type IN ('revenue','expense') AND is_active = 1 ORDER BY account_type, code")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($otherAccounts as &$otherAccount) $otherAccount['id'] = (int)$otherAccount['id'];
    unset($otherAccount);

    foreach ($cashAccounts as &$cashAccount) {
        $balanceStmt->execute([(int)$cashAccount['id']]);
        $cashAccount['balance'] = round((float)$balanceStmt->fetchColumn(), 2);
        $cashAccount['id'] = (int)$cashAccount['id'];
    }
    unset($cashAccount);

    $where = $type === '' ? '' : 'WHERE v.voucher_type = ?';
    $stmt = $pdo->prepare(
        "SELECT v.id, v.voucher_type, v.voucher_no, v.voucher_date, v.party_name, v.amount,
                v.cash_account_id, c1.code AS cash_code, c1.name_ar AS cash_name,
                v.other_account_id, c2.code AS other_code, c2.name_ar AS other_name,
                v.description, v.reference_number, v.entry_id, v.status, v.created_by,
                v.created_at, u.full_name AS creator, je.entry_code
         FROM vouchers v
         JOIN accounts c1 ON c1.id = v.cash_account_id
         JOIN accounts c2 ON c2.id = v.other_account_id
         LEFT JOIN users u ON u.id = v.created_by
         LEFT JOIN journal_entries je ON je.id = v.entry_id
         $where
         ORDER BY v.id DESC
         LIMIT 300"
    );
    $stmt->execute($type === '' ? [] : [$type]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vouchers as &$voucher) {
        $voucher['id'] = (int)$voucher['id'];
        $voucher['amount'] = round((float)$voucher['amount'], 2);
        $voucher['cash_account_id'] = (int)$voucher['cash_account_id'];
        $voucher['other_account_id'] = (int)$voucher['other_account_id'];
        $voucher['entry_id'] = $voucher['entry_id'] === null ? null : (int)$voucher['entry_id'];
        $voucher['created_by'] = $voucher['created_by'] === null ? null : (int)$voucher['created_by'];
    }
    unset($voucher);

    ak_api_json(200, ['ok' => true, 'data' => [
        'vouchers' => $vouchers,
        'cash_accounts' => $cashAccounts,
        'other_accounts' => $otherAccounts,
        'can_issue' => ak_voucher_can('issue', $role),
        'can_void' => ak_voucher_can('void', $role),
        'limit' => 300,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile voucher register failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'voucher_list_failed', 'message' => 'The voucher register could not be loaded.']]);
}
