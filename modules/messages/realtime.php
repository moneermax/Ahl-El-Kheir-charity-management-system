<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once dirname(__DIR__,2).'/config/messaging.php';
Session::start();
require_login();

$uid=Session::getUserId();
$role=Session::getUserRole();

if (!isset($_GET['stream'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'unread'=>get_unified_unread_count($uid,$role)],JSON_UNESCAPED_UNICODE);
    exit;
}

set_time_limit(0);
ignore_user_abort(true);
while (ob_get_level()>0) { ob_end_flush(); }
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

// Do not keep the PHP session locked while the SSE connection is open.
session_write_close();

$lastId=max(0,(int)($_GET['last_id']??0));
$started=time();
$lastHeartbeat=0;

while ((time()-$started)<300) {
    try {
        $updates=get_message_updates($uid,$role,$lastId);
        $unread=get_unified_unread_count($uid,$role);
        if ($updates) {
            foreach($updates as $u){
                $lastId=(int)$u['id'];
                echo "event: message_update\n";
                echo 'data: '.json_encode(['id'=>(int)$u['id'],'last_id'=>$lastId,'unread'=>$unread,'subject'=>$u['subject'],'sender'=>$u['sender_name'],'urgent'=>(int)$u['is_urgent']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n\n";
            }
            @ob_flush(); flush();
        } elseif ((time()-$lastHeartbeat)>=15) {
            $lastHeartbeat=time();
            echo ": heartbeat\n\n";
            echo "event: unread\n";
            echo 'data: '.json_encode(['unread'=>$unread],JSON_UNESCAPED_UNICODE)."\n\n";
            @ob_flush(); flush();
        }
    } catch(Throwable $e) {
        echo "event: error\n";
        echo 'data: '.json_encode(['message'=>t('messages.realtime_unavailable')],JSON_UNESCAPED_UNICODE)."\n\n";
        @ob_flush(); flush();
    }
    sleep(2);
}