<?php
/** Internal messaging service. */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

function messaging_db(): PDO { return db(); }
function messaging_role_for_user(int $userId): ?string { $r=dbFetchOne("SELECT r.code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1",[$userId]); return $r?(string)$r['code']:null; }
function messaging_user_can_read(int $userId,int $messageId): bool { $r=dbFetchOne("SELECT m.id FROM messages m WHERE m.id=? AND (m.sender_id=? OR m.recipient_user_id=? OR m.recipient_role=(SELECT r.code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?)) LIMIT 1",[$messageId,$userId,$userId,$userId]); return $r!==null; }
function messaging_user_can_delete(int $userId,int $messageId): bool { $r=dbFetchOne("SELECT sender_id FROM messages WHERE id=? LIMIT 1",[$messageId]); if(!$r || !messaging_user_can_read($userId,$messageId)) return false; return (int)$r['sender_id']===$userId || messaging_role_for_user($userId)==='admin'; }
function delete_message(int $userId,int $messageId): bool {
    if($messageId<=0 || !messaging_user_can_delete($userId,$messageId)) return false;
    $pdo=messaging_db();
    try {
        $message=$pdo->prepare("SELECT id,deleted_at FROM messages WHERE id=? LIMIT 1"); $message->execute([$messageId]); $row=$message->fetch(PDO::FETCH_ASSOC);
        if(!$row || !empty($row['deleted_at'])) return false;
        $att=$pdo->prepare("SELECT stored_name FROM message_attachments WHERE message_id=?"); $att->execute([$messageId]); $files=$att->fetchAll(PDO::FETCH_COLUMN);
        $pdo->beginTransaction();
        $u=$pdo->prepare("UPDATE messages SET deleted_at=NOW(), deleted_by=? WHERE id=? AND deleted_at IS NULL"); $u->execute([$userId,$messageId]);
        if($u->rowCount()!==1){$pdo->rollBack();return false;}
        $d=$pdo->prepare("DELETE FROM message_attachments WHERE message_id=?"); $d->execute([$messageId]);
        $pdo->commit();
        $storage=dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'message_attachments';
        foreach($files as $stored){$path=$storage.DIRECTORY_SEPARATOR.basename((string)$stored);if(is_file($path)) @unlink($path);}
        error_log('message deleted: id='.$messageId.' by user='.$userId); return true;
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); error_log('message delete: '.$e->getMessage()); return false; }
}

function send_user_message(int $senderId,int $recipientUserId,string $subject,string $body,?int $parentId=null,?string $referenceType=null,?int $referenceId=null,bool $urgent=false): int|false {
    if($senderId<=0||$recipientUserId<=0||$senderId===$recipientUserId||trim($subject)===''||trim($body)==='') return false;
    if(!dbFetchOne("SELECT id FROM users WHERE id=? AND is_active=1",[$recipientUserId])) return false;
    try{$s=messaging_db()->prepare("INSERT INTO messages(sender_id,recipient_user_id,recipient_role,subject,body,parent_id,reference_type,reference_id,is_urgent,created_at) VALUES(?,?,NULL,?,?,?,?,?,?,NOW())");$s->execute([$senderId,$recipientUserId,trim($subject),trim($body),$parentId,$referenceType,$referenceId,$urgent?1:0]);return (int)messaging_db()->lastInsertId();}catch(Throwable $e){error_log('send_user_message: '.$e->getMessage());return false;}
}
function broadcast_to_role(int $senderId,string $recipientRole,string $subject,string $body,?string $referenceType=null,?int $referenceId=null,bool $urgent=false): int|false {
    if($senderId<=0||trim($recipientRole)===''||trim($subject)===''||trim($body)==='') return false;
    if(!dbFetchOne("SELECT code FROM roles WHERE code=? LIMIT 1",[$recipientRole])) return false;
    try{$s=messaging_db()->prepare("INSERT INTO messages(sender_id,recipient_user_id,recipient_role,subject,body,parent_id,reference_type,reference_id,is_urgent,created_at) VALUES(?,NULL,?,?,?,NULL,?,?,?,NOW())");$s->execute([$senderId,$recipientRole,trim($subject),trim($body),$referenceType,$referenceId,$urgent?1:0]);return (int)messaging_db()->lastInsertId();}catch(Throwable $e){error_log('broadcast_to_role: '.$e->getMessage());return false;}
}
function get_messaging_users(int $excludeUserId,?string $roleFilter=null): array { $sql="SELECT u.id,u.full_name AS name,u.username,r.code AS role,r.name_ar AS role_name_ar,r.name_en AS role_name_en FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id<>? AND u.is_active=1";$p=[$excludeUserId];if($roleFilter){$sql.=" AND r.code=?";$p[]=$roleFilter;}$sql.=" ORDER BY r.id,u.full_name";return dbFetchAll($sql,$p); }
function get_broadcast_roles(): array { return dbFetchAll("SELECT r.code,r.name_ar,r.name_en,COUNT(u.id) active_count FROM roles r JOIN users u ON u.role_id=r.id AND u.is_active=1 GROUP BY r.id,r.code,r.name_ar,r.name_en ORDER BY r.id"); }
function get_unread_message_count(int $userId,string $role): int { $r=dbFetchOne("SELECT COUNT(*) c FROM messages m LEFT JOIN message_reads mr ON mr.message_id=m.id AND mr.user_id=? WHERE (m.recipient_user_id=? OR (m.recipient_role=? AND m.sender_id<>?)) AND mr.id IS NULL AND m.deleted_at IS NULL",[$userId,$userId,$role,$userId]);return (int)($r['c']??0); }
function get_messages(int $userId,string $role,string $filter='all',int $limit=25,int $offset=0): array {
    $limit=max(1,min(100,$limit));$offset=max(0,$offset);
    $sql="SELECT x.* FROM (SELECT m.*,u.full_name sender_name,r.name_ar sender_role_name_ar,r.name_en sender_role_name_en,CASE WHEN SUM(CASE WHEN mr.id IS NULL AND m.deleted_at IS NULL THEN 1 ELSE 0 END) OVER (PARTITION BY m.sender_id)>0 THEN 0 ELSE 1 END is_read,COUNT(*) OVER (PARTITION BY m.sender_id) message_count,SUM(CASE WHEN mr.id IS NULL AND m.deleted_at IS NULL THEN 1 ELSE 0 END) OVER (PARTITION BY m.sender_id) unread_count,ROW_NUMBER() OVER (PARTITION BY m.sender_id ORDER BY m.id DESC) sender_row,CASE WHEN m.deleted_at IS NULL THEN m.body ELSE 'تم حذف هذه الرسالة' END body_preview FROM messages m JOIN users u ON u.id=m.sender_id LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN message_reads mr ON mr.message_id=m.id AND mr.user_id=? WHERE (m.recipient_user_id=? OR (m.recipient_role=? AND m.sender_id<>?))) x WHERE x.sender_row=1";
    $p=[$userId,$userId,$role,$userId];if($filter==='unread')$sql.=" AND x.unread_count>0";$sql.=" ORDER BY x.id DESC LIMIT $limit OFFSET $offset";return dbFetchAll($sql,$p);
}
function get_sent_messages(int $userId,int $limit=25,int $offset=0): array {
    $limit=max(1,min(100,$limit));$offset=max(0,$offset);
    return dbFetchAll("SELECT x.* FROM (SELECT m.*,COALESCE(u.full_name,CONCAT('الدور: ',m.recipient_role)) recipient_name,CASE WHEN m.recipient_role IS NULL THEN 'direct' ELSE 'broadcast' END delivery_type,CASE WHEN m.deleted_at IS NULL THEN m.body ELSE 'تم حذف هذه الرسالة' END body_preview,COUNT(*) OVER (PARTITION BY COALESCE(CONCAT('u:',m.recipient_user_id),CONCAT('r:',m.recipient_role))) message_count,ROW_NUMBER() OVER (PARTITION BY COALESCE(CONCAT('u:',m.recipient_user_id),CONCAT('r:',m.recipient_role)) ORDER BY m.id DESC) recipient_row FROM messages m LEFT JOIN users u ON u.id=m.recipient_user_id WHERE m.sender_id=?) x WHERE x.recipient_row=1 ORDER BY x.id DESC LIMIT $limit OFFSET $offset",[$userId]);
}
function get_conversation_list(int $userId,int $limit=20): array { $limit=max(1,min(50,$limit));return dbFetchAll("SELECT other_user_id,other_name,last_subject,last_body,last_at FROM (SELECT CASE WHEN m.sender_id=? THEN m.recipient_user_id ELSE m.sender_id END other_user_id,u.full_name other_name,m.subject last_subject,CASE WHEN m.deleted_at IS NULL THEN LEFT(m.body,100) ELSE 'تم حذف هذه الرسالة' END last_body,m.created_at last_at,ROW_NUMBER() OVER(PARTITION BY CASE WHEN m.sender_id=? THEN m.recipient_user_id ELSE m.sender_id END ORDER BY m.id DESC) rn FROM messages m JOIN users u ON u.id=CASE WHEN m.sender_id=? THEN m.recipient_user_id ELSE m.sender_id END WHERE ((m.sender_id=? AND m.recipient_user_id IS NOT NULL) OR (m.recipient_user_id=? AND m.sender_id IS NOT NULL))) x WHERE rn=1 ORDER BY last_at DESC LIMIT $limit",[$userId,$userId,$userId,$userId,$userId]); }
function get_message_by_id(int $messageId,int $viewerId): ?array { if(!messaging_user_can_read($viewerId,$messageId))return null;$r=dbFetchOne("SELECT m.*,u.full_name sender_name,r.code sender_role,CASE WHEN mr.id IS NULL THEN 0 ELSE 1 END is_read FROM messages m JOIN users u ON u.id=m.sender_id LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN message_reads mr ON mr.message_id=m.id AND mr.user_id=? WHERE m.id=? LIMIT 1",[$viewerId,$messageId]);return $r?:null; }
function mark_message_read(int $userId,int $messageId): bool { if(!messaging_user_can_read($userId,$messageId))return false;try{$s=messaging_db()->prepare("INSERT IGNORE INTO message_reads(message_id,user_id,read_at) VALUES(?,?,NOW())");$s->execute([$messageId,$userId]);return true;}catch(Throwable $e){return false;} }
function mark_all_messages_read(int $userId,string $role): bool { try{$s=messaging_db()->prepare("INSERT IGNORE INTO message_reads(message_id,user_id,read_at) SELECT m.id,?,NOW() FROM messages m LEFT JOIN message_reads mr ON mr.message_id=m.id AND mr.user_id=? WHERE (m.recipient_user_id=? OR (m.recipient_role=? AND m.sender_id<>?)) AND mr.id IS NULL AND m.deleted_at IS NULL");$s->execute([$userId,$userId,$userId,$role,$userId]);return true;}catch(Throwable $e){return false;} }
function get_message_thread(int $messageId,int $viewerId): array {
    $m=get_message_by_id($messageId,$viewerId);if(!$m)return ['root'=>null,'replies'=>[]];$rootId=(int)$m['id'];$guard=0;
    while(!empty($m['parent_id'])&&$guard++<50){$p=get_message_by_id((int)$m['parent_id'],$viewerId);if(!$p)break;$m=$p;$rootId=(int)$p['id'];}
    mark_message_read($viewerId,$rootId);$root=get_message_by_id($rootId,$viewerId);if(!$root)return ['root'=>null,'replies'=>[]];$replies=[];
    if($root['recipient_user_id']!==null){$rootSender=(int)$root['sender_id'];$rootRecipient=(int)$root['recipient_user_id'];$replies=dbFetchAll("SELECT m.*,u.full_name sender_name,r.code sender_role FROM messages m JOIN users u ON u.id=m.sender_id LEFT JOIN roles r ON r.id=u.role_id WHERE m.parent_id=? AND m.recipient_user_id IS NOT NULL AND ((m.sender_id=? AND m.recipient_user_id=?) OR (m.sender_id=? AND m.recipient_user_id=?)) ORDER BY m.id ASC",[$rootId,$rootSender,$rootRecipient,$rootRecipient,$rootSender]);foreach($replies as $reply){if((int)$reply['sender_id']!==$viewerId&&((int)$reply['recipient_user_id']===$viewerId||(int)$reply['sender_id']===$viewerId))mark_message_read($viewerId,(int)$reply['id']);}}
    return ['root'=>$root,'replies'=>$replies];
}
function get_message_updates(int $userId,string $role,int $afterId=0): array { return dbFetchAll("SELECT m.id,m.subject,CASE WHEN m.deleted_at IS NULL THEN LEFT(m.body,160) ELSE 'تم حذف هذه الرسالة' END body_preview,m.created_at,m.sender_id,u.full_name sender_name,m.is_urgent FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.id>? AND (m.recipient_user_id=? OR (m.recipient_role=? AND m.sender_id<>?)) AND m.deleted_at IS NULL ORDER BY m.id ASC LIMIT 50",[$afterId,$userId,$role,$userId]); }
function get_unified_unread_count(int $userId,string $role): int { $n=0;try{$r=dbFetchOne("SELECT COUNT(*) c FROM notifications WHERE recipient_user_id=? AND is_read=0",[$userId]);$n=(int)($r['c']??0);}catch(Throwable $e){}return get_unread_message_count($userId,$role)+$n; }
function get_unread_count(int $userId,string $role): int{return get_unified_unread_count($userId,$role);}
function get_notifications(int $userId,string $filter='all',int $limit=20,int $offset=0): array{$w=$filter==='unread'?' AND is_read=0':'';$limit=max(1,min(100,$limit));$offset=max(0,$offset);try{return dbFetchAll("SELECT * FROM notifications WHERE recipient_user_id=? $w ORDER BY id DESC LIMIT $limit OFFSET $offset",[$userId]);}catch(Throwable $e){return[];}}
function mark_notification_read(int $userId,?int $notificationId=null):bool{try{if($notificationId!==null)dbExecute("UPDATE notifications SET is_read=1 WHERE id=? AND recipient_user_id=?",[$notificationId,$userId]);else dbExecute("UPDATE notifications SET is_read=1 WHERE recipient_user_id=? AND is_read=0",[$userId]);return true;}catch(Throwable $e){return false;}}
function send_system_notification(int $userId,string $title,string $message,string $type='info',?string $referenceType=null,?int $referenceId=null):int|false{try{$s=messaging_db()->prepare("INSERT INTO notifications(recipient_user_id,title,body,link,is_read,created_at) VALUES(?,?,?,?,0,NOW())");$link=($referenceType&&$referenceId)?APP_URL.'modules/messages/index.php?message='.$referenceId:null;$s->execute([$userId,$title,$message,$link]);return(int)messaging_db()->lastInsertId();}catch(Throwable $e){return false;}}
