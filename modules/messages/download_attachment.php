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
$id=(int)($_GET['id']??0);
if($id<=0){http_response_code(404);exit(t('messages.download_not_found'));}
$row=dbFetchOne('SELECT a.*,m.sender_id,m.recipient_user_id,m.recipient_role FROM message_attachments a JOIN messages m ON m.id=a.message_id WHERE a.id=? LIMIT 1',[$id]);
if(!$row || !messaging_user_can_read($uid,(int)$row['message_id'])){http_response_code(403);exit(t('messages.download_forbidden'));}
$path=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'message_attachments'.DIRECTORY_SEPARATOR.$row['stored_name'];
if(!is_file($path)){http_response_code(404);exit(t('messages.download_not_found'));}
header('Content-Type: '.($row['mime_type']?:'application/octet-stream'));
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'_',basename($row['original_name'])).'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
