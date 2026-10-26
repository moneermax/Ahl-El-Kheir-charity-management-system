<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employee_incentives.php';

$user = ak_api_authenticated_user();
if ((string)($user['raw_role_code'] ?? '') !== 'hr_manager') {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Incentive verification is restricted to the HR Manager role.']]);
}

function mobileIncentiveEventRow(array $event): array
{
    $eligible = (int)($event['recipient_user_id'] ?? 0) > 0
        && (int)($event['recipient_employee_id'] ?? 0) > 0
        && $event['recipient_name'] !== null
        && $event['employment_category'] === 'working'
        && (int)$event['employment_state_active'] === 1
        && (int)$event['user_active'] === 1;
    $collection = $event['incentive_type'] === 'collection';
    $project = $event['incentive_type'] === 'project_completion';
    $canVerify = $event['status'] === 'pending_verification' && !$collection && !$project
        && $eligible && (int)$event['is_enabled'] === 1 && (float)$event['reward_amount'] > 0;
    $message = '';
    if ($event['status'] === 'pending_verification' && $collection) {
        $message = 'Routine collection incentives are disabled; the event remains preserved without an award.';
    } elseif ($event['status'] === 'pending_verification' && $project) {
        $message = 'Project-completion incentives are system-controlled after verified financial closure.';
    } elseif ($event['status'] === 'pending_verification' && !$eligible) {
        $message = 'Recipient identity or employment eligibility requires review.';
    } elseif ($event['status'] === 'pending_verification' && !$canVerify) {
        $message = 'The linked policy rule is disabled or the reward amount is invalid.';
    } elseif ($event['status'] === 'held' || ($event['award_status'] ?? '') === 'held_currency') {
        $message = (string)($event['hold_reason'] ?? 'The award is held for review.');
    } elseif (!empty($event['award_id'])) {
        $message = 'Award #' . (int)$event['award_id'] . ' · ' . (string)($event['award_status'] ?? '');
    }
    return [
        'id'=>(int)$event['id'],
        'incentive_type'=>(string)$event['incentive_type'],
        'incentive_name_ar'=>(string)($event['incentive_name_ar']??$event['incentive_type']),
        'incentive_name_en'=>(string)($event['incentive_name_en']??$event['incentive_type']),
        'source_table'=>(string)$event['source_table'],
        'source_record_id'=>(int)$event['source_record_id'],
        'occurred_at'=>(string)$event['occurred_at'],
        'source_amount'=>$event['source_amount']===null?null:(float)$event['source_amount'],
        'source_currency_code'=>(string)($event['source_currency_code']??''),
        'recipient_user_id'=>(int)($event['recipient_user_id']??0),
        'recipient_employee_id'=>(int)($event['recipient_employee_id']??0),
        'recipient_name'=>(string)($event['recipient_name']??''),
        'employment_category'=>(string)($event['employment_category']??''),
        'employment_state_active'=>(int)($event['employment_state_active']??0)===1,
        'user_active'=>(int)($event['user_active']??0)===1,
        'reward_amount'=>(float)$event['reward_amount'],
        'reward_currency_code'=>(string)$event['reward_currency_code'],
        'is_enabled'=>(int)$event['is_enabled']===1,
        'status'=>(string)$event['status'],
        'award_id'=>empty($event['award_id'])?null:(int)$event['award_id'],
        'award_status'=>(string)($event['award_status']??''),
        'hold_reason'=>(string)($event['hold_reason']??''),
        'project_award_count'=>(int)($event['project_award_count']??0),
        'project_award_total'=>(float)($event['project_award_total']??0),
        'recipient_eligible'=>$eligible,
        'can_verify'=>$canVerify,
        'action_message'=>$message,
    ];
}

function mobileIncentiveVerificationRows(PDO $pdo): array
{
    $events=dbFetchAll(
        "SELECT ev.id, ev.incentive_type, ev.source_table, ev.source_record_id,
                ev.occurred_at, ev.source_amount, ev.source_currency_code, ev.status,
                ev.recipient_user_id, ev.recipient_employee_id,
                t.name_ar AS incentive_name_ar, t.name_en AS incentive_name_en,
                r.reward_amount, r.currency_code AS reward_currency_code, r.is_enabled,
                e.full_name AS recipient_name, es.category AS employment_category,
                es.is_active AS employment_state_active, u.is_active AS user_active,
                a.id AS award_id, a.status AS award_status, a.hold_reason,
                (SELECT COUNT(*) FROM hr_incentive_awards pa WHERE pa.event_id=ev.id) AS project_award_count,
                (SELECT ROUND(COALESCE(SUM(pa.reward_amount),0),2) FROM hr_incentive_awards pa WHERE pa.event_id=ev.id) AS project_award_total
         FROM hr_incentive_events ev
         JOIN hr_incentive_types t ON t.type_code=ev.incentive_type
         JOIN hr_incentive_policy_rules r
           ON r.policy_version_id=ev.policy_version_id AND r.incentive_type=ev.incentive_type
         LEFT JOIN employees e ON e.id=ev.recipient_employee_id
         LEFT JOIN hr_employment_states es ON es.id=e.employment_state_id
         LEFT JOIN users u ON u.id=ev.recipient_user_id
         LEFT JOIN hr_incentive_awards a ON a.event_id=ev.id AND a.employee_id=ev.recipient_employee_id
         WHERE ev.status IN ('pending_verification','verified','held')
         ORDER BY FIELD(ev.status,'pending_verification','held','verified'),ev.occurred_at ASC,ev.id ASC
         LIMIT 250"
    );
    return array_map('mobileIncentiveEventRow',$events);
}

try {
    $pdo=db();
    if (strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET') {
        ak_api_require_method('GET');
        ak_api_json(200,['ok'=>true,'data'=>['events'=>mobileIncentiveVerificationRows($pdo)]]);
    }
    ak_api_require_method('POST');
    $body=ak_api_read_json();
    if ((string)($body['action']??'')!=='verify_event') {
        ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Unsupported incentive-verification action.']]);
    }
    $eventId=filter_var($body['event_id']??null,FILTER_VALIDATE_INT);
    $note=trim((string)($body['verification_note']??''));
    if (!$eventId || $eventId<1) throw new InvalidArgumentException('A valid incentive event is required.');
    if (mb_strlen($note)>2000) throw new InvalidArgumentException('The verification note cannot exceed 2000 characters.');

    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare(
            "SELECT ev.*,r.reward_amount,r.currency_code AS reward_currency_code,r.is_enabled,
                    t.name_ar AS incentive_name_ar,t.name_en AS incentive_name_en
             FROM hr_incentive_events ev
             JOIN hr_incentive_policy_rules r ON r.policy_version_id=ev.policy_version_id AND r.incentive_type=ev.incentive_type
             JOIN hr_incentive_types t ON t.type_code=ev.incentive_type
             WHERE ev.id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([(int)$eventId]);
        $event=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            $pdo->rollBack();
            ak_api_json(404,['ok'=>false,'error'=>['code'=>'event_not_found','message'=>'Incentive event not found.']]);
        }
        if ($event['status']!=='pending_verification') throw new DomainException('This event has already been processed. Refresh the list to see its current status.');
        if ($event['incentive_type']==='collection') throw new DomainException('Routine collection incentives are disabled and cannot be issued.');
        if ($event['incentive_type']==='project_completion') throw new DomainException('Project-completion incentives are system-controlled after financial closure and cannot be manually verified here.');
        if ((int)$event['is_enabled']!==1 || (float)$event['reward_amount']<=0) throw new DomainException('The linked policy rule is disabled or the reward amount is invalid.');

        $recipientUserId=(int)($event['recipient_user_id']??0);
        $recipientEmployeeId=(int)($event['recipient_employee_id']??0);
        if($recipientUserId<=0||$recipientEmployeeId<=0) throw new DomainException('A trusted recipient identity is not stored for this event.');
        $employeeStmt=$pdo->prepare(
            "SELECT e.id,e.user_id,es.category,es.is_active AS state_is_active,u.is_active AS user_is_active
             FROM employees e JOIN hr_employment_states es ON es.id=e.employment_state_id
             JOIN users u ON u.id=e.user_id WHERE e.id=? LIMIT 1 FOR UPDATE"
        );
        $employeeStmt->execute([$recipientEmployeeId]);
        $employee=$employeeStmt->fetch(PDO::FETCH_ASSOC);
        if(!$employee||(int)$employee['user_id']!==$recipientUserId||$employee['category']!=='working'
            ||(int)$employee['state_is_active']!==1||(int)$employee['user_is_active']!==1) {
            throw new DomainException('The recipient is not linked to the stored user or is no longer an eligible working employee.');
        }
        $existing=$pdo->prepare('SELECT id FROM hr_incentive_awards WHERE event_id=? AND employee_id=? LIMIT 1 FOR UPDATE');
        $existing->execute([(int)$eventId,$recipientEmployeeId]);
        if($existing->fetchColumn()) throw new DomainException('An award already exists for this event and employee; duplicate issuance was blocked.');

        $verify=$pdo->prepare(
            "UPDATE hr_incentive_events SET status='verified',verification_note=?,verified_by_user_id=?,verified_at=NOW()
             WHERE id=? AND status='pending_verification'"
        );
        $verify->execute([$note!==''?$note:null,(int)$user['id'],(int)$eventId]);
        if($verify->rowCount()!==1) throw new DomainException('The event changed during review; no award was issued.');

        $award=$pdo->prepare(
            "INSERT INTO hr_incentive_awards
             (event_id,employee_id,recipient_user_id,reward_amount,reward_currency_code,status,earned_at)
             VALUES (?,?,?,?,?,'earned',?)"
        );
        $award->execute([(int)$eventId,$recipientEmployeeId,$recipientUserId,round((float)$event['reward_amount'],2),
            strtoupper(trim((string)$event['reward_currency_code'])),(string)$event['occurred_at']]);
        $awardId=(int)$pdo->lastInsertId();
        $journalId=hrIncentiveRecognizeAward($pdo,$awardId,(int)$user['id']);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }

    // The event/award/journal are durable before we attempt notice delivery.
    // Delivery failures remain isolated in the existing outbox helper.
    $outboxId=0;
    try {
        $outboxRow=dbFetchOne('SELECT id FROM hr_incentive_notification_outbox WHERE award_id=? LIMIT 1',[$awardId]);
        $outboxId=(int)($outboxRow['id']??0);
        if($outboxId>0) hrIncentiveDispatchPendingNotifications($pdo,$outboxId);
    } catch(Throwable $notifyError) {
        error_log('Mobile incentive notice dispatch failed for award '.$awardId.': '.$notifyError->getMessage());
    }
    $outboxRow=$outboxId>0?dbFetchOne('SELECT status FROM hr_incentive_notification_outbox WHERE id=? LIMIT 1',[$outboxId]):null;
    ak_api_json(200,['ok'=>true,'data'=>[
        'event_id'=>(int)$eventId,'award_id'=>$awardId,'recognition_journal_entry_id'=>$journalId,
        'award_status'=>$journalId>0?'earned':'held_currency','notification_status'=>(string)($outboxRow['status']??'pending'),
        'message'=>$journalId>0?'Achievement verified, award issued, and recognition journal posted.':'Achievement verified and award recorded, but it is held; no recognition journal was posted.',
    ]]);
} catch(InvalidArgumentException $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    ak_api_json(422,['ok'=>false,'error'=>['code'=>'incentive_validation_failed','message'=>$e->getMessage()]]);
} catch(DomainException $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    ak_api_json(409,['ok'=>false,'error'=>['code'=>'incentive_conflict','message'=>$e->getMessage()]]);
} catch(PDOException $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    if((string)$e->getCode()==='23000') ak_api_json(409,['ok'=>false,'error'=>['code'=>'incentive_conflict','message'=>'The incentive event or award conflicts with an existing record.']]);
    error_log('Mobile incentive verification failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'incentive_verification_failed','message'=>'The incentive event could not be processed.']]);
} catch(Throwable $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    error_log('Mobile incentive verification failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'incentive_verification_failed','message'=>'The incentive event could not be processed.']]);
}
