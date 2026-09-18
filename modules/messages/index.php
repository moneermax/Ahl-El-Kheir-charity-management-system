<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once dirname(__DIR__,2).'/config/messaging.php';
Session::start();
require_login();

$uid = Session::getUserId();
$role = Session::getUserRole();
$pageTitle = 'الرسائل الداخلية';
$active = 'messages';
$canBroadcast = in_array($role,['admin','general_manager','vice_general_manager','financial_manager','hr_manager'],true);
$msgUrl = APP_URL.'modules/messages/index.php';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf()) { http_response_code(419); echo json_encode(['ok'=>false,'message'=>'انتهت صلاحية الجلسة.'],JSON_UNESCAPED_UNICODE); exit; }
    $action = $_POST['action'] ?? '';
    if ($action==='send') {
        $type=$_POST['recipient_type']??'user'; $subject=trim((string)($_POST['subject']??'')); $body=trim((string)($_POST['body']??'')); $urgent=!empty($_POST['is_urgent']);
        if($subject===''||$body===''){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'الموضوع ونص الرسالة مطلوبان.'],JSON_UNESCAPED_UNICODE);exit;}
        if($type==='role'){
            if(!$canBroadcast){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'ليس لديك صلاحية الإرسال إلى دور كامل.'],JSON_UNESCAPED_UNICODE);exit;}
            $id=broadcast_to_role($uid,trim((string)($_POST['recipient_role']??'')),$subject,$body,null,null,$urgent);
        }else{$id=send_user_message($uid,(int)($_POST['recipient_user_id']??0),$subject,$body,null,null,null,$urgent);}
        if(!$id){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'تعذر إرسال الرسالة. تحقق من المستلم والبيانات.'],JSON_UNESCAPED_UNICODE);exit;}
        echo json_encode(['ok'=>true,'id'=>$id,'message'=>'تم إرسال الرسالة بنجاح.'],JSON_UNESCAPED_UNICODE);exit;
    }
    if($action==='read'){echo json_encode(['ok'=>mark_message_read($uid,(int)($_POST['message_id']??0))],JSON_UNESCAPED_UNICODE);exit;}
    if($action==='read_all'){echo json_encode(['ok'=>mark_all_messages_read($uid,$role)],JSON_UNESCAPED_UNICODE);exit;}
    if($action==='reply'){
        $parent=(int)($_POST['message_id']??0); $parentMessage=get_message_by_id($parent,$uid);
        if(!$parentMessage){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'الرسالة غير متاحة.'],JSON_UNESCAPED_UNICODE);exit;}
        $body=trim((string)($_POST['body']??'')); if($body===''){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'نص الرد مطلوب.'],JSON_UNESCAPED_UNICODE);exit;}
        $target=(int)$parentMessage['sender_id']===$uid?(int)($parentMessage['recipient_user_id']??0):(int)$parentMessage['sender_id'];
        if($target<=0){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'لا يمكن الرد على رسالة جماعية من هذه الشاشة حالياً.'],JSON_UNESCAPED_UNICODE);exit;}
        $id=send_user_message($uid,$target,'رد: '.$parentMessage['subject'],$body,$parent,null,null,false);
        echo json_encode(['ok'=>(bool)$id,'id'=>$id,'message'=>$id?'تم إرسال الرد.':'تعذر إرسال الرد.'],JSON_UNESCAPED_UNICODE);exit;
    }
    http_response_code(400);echo json_encode(['ok'=>false,'message'=>'طلب غير معروف.'],JSON_UNESCAPED_UNICODE);exit;
}

$view=$_GET['view']??'inbox'; $filter=$_GET['filter']??'all'; $messageId=(int)($_GET['message']??0);
$thread=$messageId?get_message_thread($messageId,$uid):['root'=>null,'replies'=>[]];
$messages=$view==='sent'?get_sent_messages($uid):get_messages($uid,$role,$filter);
$users=get_messaging_users($uid); $roles=$canBroadcast?get_broadcast_roles():[]; $unread=get_unread_message_count($uid,$role);
include dirname(__DIR__,2).'/includes/header.php';
?>


<style id="msg-gmail-workspace-v2">
body:has(.messages-shell){background:#f6f8fc!important}body:has(.messages-shell) .sidebar,body:has(.messages-shell) .header-sticky-wrapper{display:none!important}body:has(.messages-shell) .main-area{width:100%;min-width:0}body:has(.messages-shell) .content{padding:0!important;background:#f6f8fc!important}
.messages-shell{--gmail-bg:#f6f8fc;--gmail-surface:#fff;--gmail-sidebar:#f2f6fc;--gmail-blue:#1a73e8;--gmail-blue-soft:#d3e3fd;--gmail-hover:#e8f0fe;--gmail-text:#202124;--gmail-muted:#5f6368;--gmail-border:#dadce0;--gmail-rule:#edf0f2;--gmail-font:'Cairo',Arial,sans-serif;direction:rtl;margin:0 -12px;min-height:calc(100vh - 120px);background:var(--gmail-bg);color:var(--gmail-text);font-family:var(--gmail-font);font-size:14px}.messages-shell *{box-sizing:border-box}.messages-shell button,.messages-shell input,.messages-shell select,.messages-shell textarea{font-family:inherit}.messages-shell a{color:inherit}
.msg-toolbar{height:64px;display:flex;align-items:center;gap:26px;padding:8px 20px;background:#fff;border-bottom:1px solid var(--gmail-border)}.msg-title{display:flex;align-items:center;gap:11px;min-width:180px}.msg-title-icon{width:34px;height:34px;display:grid;place-items:center;color:#ea4335;font-size:20px}.msg-title h2{margin:0;font-size:1rem;font-weight:500;color:#3c4043}.msg-title p{display:none}.msg-toolbar>.msg-search-wrap{position:relative;flex:1;max-width:720px;margin:0 auto}.msg-toolbar>.msg-search-wrap .msg-search{display:block;width:100%;height:44px;padding:0 46px 0 18px;border:0;border-radius:8px;background:#f1f3f4;color:var(--gmail-text);font-size:.72rem;outline:none}.msg-toolbar>.msg-search-wrap .msg-search:focus{background:#fff;box-shadow:0 1px 3px rgba(60,64,67,.3),0 0 0 1px #c7d9f8}.msg-toolbar>.msg-search-wrap i{position:absolute;right:17px;top:50%;transform:translateY(-50%);color:var(--gmail-muted);font-size:.8rem}.msg-settings{display:flex;align-items:center;gap:6px;color:var(--gmail-muted);font-size:.64rem;white-space:nowrap}.msg-settings select{height:28px;width:82px;padding:0 5px;border:1px solid var(--gmail-border);border-radius:4px;background:#fff;font-size:.63rem;color:var(--gmail-text)}
.msg-layout{display:grid;grid-template-areas:'sidebar list reading';grid-template-columns:232px 390px minmax(0,1fr);height:calc(100vh - 184px);min-height:570px;overflow:hidden;background:#fff;direction:rtl}.msg-sidebar{grid-area:sidebar;min-width:0;padding:18px 8px;background:var(--gmail-sidebar);border-left:1px solid var(--gmail-border);overflow:auto}.msg-compose-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;height:46px;min-width:152px;margin:0 4px 18px;padding:0 20px;border:0;border-radius:15px;background:#c2e7ff;color:#001d35;box-shadow:0 1px 3px rgba(60,64,67,.25);font-size:.74rem;font-weight:700;cursor:pointer}.msg-compose-btn:hover{background:#b3dcf5}.msg-nav{display:flex;flex-direction:column;gap:2px}.msg-nav a,.msg-nav button{display:flex;align-items:center;gap:13px;min-height:37px;width:100%;padding:0 15px;border:0;border-radius:0 18px 18px 0;background:transparent;color:var(--gmail-text);text-align:right;text-decoration:none;font-size:.71rem;font-weight:500;cursor:pointer}.msg-nav a:hover,.msg-nav button:hover{background:var(--gmail-hover)}.msg-nav a.active{background:var(--gmail-blue-soft);color:#001d35;font-weight:700}.msg-nav i{width:19px;color:var(--gmail-muted);text-align:center}.msg-nav a.active i{color:#174ea6}.msg-count{margin-inline-start:auto;min-width:20px;color:var(--gmail-text);font-size:.62rem;font-weight:700}.msg-section-label{margin:23px 14px 7px;color:var(--gmail-muted);font-size:.61rem;font-weight:700}.msg-sidebar>.small{padding:0 14px!important;font-size:.63rem!important;line-height:1.8!important}
.msg-list-pane{grid-area:list;display:flex;min-width:0;flex-direction:column;background:#fff;border-left:1px solid var(--gmail-border);border-right:1px solid var(--gmail-border)}.msg-list-head{height:54px;min-height:54px;display:flex;align-items:center;gap:10px;padding:0 15px;background:#fff;border-bottom:1px solid var(--gmail-border)}.msg-list-head h3{margin:0;flex:1;font-size:.82rem;font-weight:500}.msg-list-head>.msg-search-wrap{display:none}.msg-filter{display:flex;align-items:center;gap:2px}.msg-filter a{padding:8px 7px;border-bottom:2px solid transparent;color:var(--gmail-muted);font-size:.61rem;text-decoration:none}.msg-filter a.active{border-bottom-color:var(--gmail-blue);color:var(--gmail-blue);font-weight:700}.msg-list{min-height:0;flex:1;overflow:auto;background:#fff}.msg-row{position:relative;display:flex;align-items:flex-start;gap:11px;width:100%;min-height:72px;padding:11px 14px;border:0;border-bottom:1px solid var(--gmail-rule);background:#fff;color:var(--gmail-text);text-decoration:none;cursor:pointer}.msg-row:hover{background:#f2f6fc}.msg-row.unread{background:#f8fbff}.msg-row.active{background:var(--gmail-blue-soft)}.msg-row.unread:before{content:'';position:absolute;right:0;top:0;bottom:0;width:3px;background:var(--gmail-blue)}.msg-avatar{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border-radius:50%;background:#e8eaed;color:#5f6368;font-size:.71rem;font-weight:600}.msg-row-main{min-width:0;flex:1}.msg-row-top,.msg-row-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px}.msg-name,.msg-subject{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--gmail-text);font-size:.7rem;font-weight:500}.msg-row.unread .msg-name,.msg-row.unread .msg-subject{font-weight:700}.msg-time{color:var(--gmail-muted);font-size:.56rem;white-space:nowrap}.msg-subject{margin-top:3px}.msg-preview{margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--gmail-muted);font-size:.6rem}.msg-new{color:#174ea6;font-size:.52rem;font-weight:700}.msg-urgent{color:#d93025;font-size:.62rem}.msg-empty{height:100%;display:grid;place-items:center;text-align:center;color:var(--gmail-muted)}.msg-empty>div{display:grid;justify-items:center;gap:8px}.msg-empty-icon,.msg-read-empty-icon{width:56px;height:56px;display:grid;place-items:center;border-radius:50%;background:#f1f3f4;color:#9aa0a6}.msg-empty strong,.msg-read-empty strong{font-size:.76rem;font-weight:500}.msg-empty span,.msg-read-empty span{color:#9aa0a6;font-size:.63rem}
.msg-reading-pane{grid-area:reading;min-width:0;display:flex;flex-direction:column;background:#fff}.msg-reading-pane.empty{align-items:center;justify-content:center}.msg-reading-head{min-height:64px;padding:12px 23px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--gmail-border)}.msg-conv-person{display:flex;align-items:center;gap:10px;min-width:0}.msg-conv-name{font-size:.78rem;font-weight:600}.msg-conv-meta{margin-top:2px;color:var(--gmail-muted);font-size:.58rem}.msg-conv-actions{display:flex;gap:1px}.msg-icon-btn{width:34px;height:34px;display:grid;place-items:center;border:0;border-radius:50%;background:transparent;color:var(--gmail-muted);text-decoration:none;cursor:pointer}.msg-icon-btn:hover{background:#f1f3f4;color:var(--gmail-text)}.msg-thread{min-height:0;flex:1;overflow:auto;padding:30px 42px 28px}.msg-root{margin:0 0 18px;padding:0 0 23px;border-bottom:1px solid var(--gmail-border);background:#fff}.msg-subject-large{margin:0 0 9px;font-size:1.25rem;font-weight:400;letter-spacing:-.2px}.msg-root-meta,.msg-bubble-time{color:var(--gmail-muted);font-size:.6rem}.msg-root hr{margin:18px 0;border:0;border-top:1px solid var(--gmail-rule)}.msg-body{color:var(--gmail-text);font-size:.8rem;line-height:1.95;white-space:pre-wrap}.msg-bubble{padding:16px 0;border-top:1px solid var(--gmail-rule);background:#fff}.msg-bubble.mine{background:#fff}.msg-bubble-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}.msg-bubble-name{font-size:.68rem;font-weight:600}.msg-attachments{display:flex;flex-wrap:wrap;gap:7px;margin-top:16px}.msg-attachment-item{display:flex;align-items:center;gap:7px;max-width:260px;padding:7px 9px;border:1px solid var(--gmail-border);border-radius:6px;background:#f8fafd;font-size:.62rem}.msg-attachment-item a{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#174ea6;text-decoration:none}.msg-attachment-size{color:var(--gmail-muted);white-space:nowrap}.msg-attachment-del{border:0;background:transparent;color:var(--gmail-muted);cursor:pointer}.msg-reply{padding:12px 24px 16px;border-top:1px solid var(--gmail-border);background:#fff}.msg-reply-box{overflow:hidden;border:1px solid var(--gmail-border);border-radius:8px;background:#fff;box-shadow:0 1px 2px rgba(60,64,67,.12)}.msg-reply textarea{display:block;width:100%;min-height:75px;max-height:180px;padding:12px 14px;border:0;resize:vertical;outline:0;color:var(--gmail-text);font-size:.76rem}.msg-reply-tools{display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-top:1px solid var(--gmail-rule)}.msg-tools{display:flex;align-items:center;gap:2px}.msg-tool{width:30px;height:30px;border:0;border-radius:50%;background:transparent;color:var(--gmail-muted);cursor:pointer}.msg-tool:hover{background:#f1f3f4;color:var(--gmail-text)}.msg-send,.compose-send{padding:8px 16px;border:0;border-radius:18px;background:var(--gmail-blue);color:#fff;font-size:.67rem;font-weight:600;cursor:pointer}.msg-send:hover,.compose-send:hover{background:#0b57d0}.msg-no-reply{padding:13px 24px;border-top:1px solid var(--gmail-border);color:var(--gmail-muted);font-size:.63rem}.msg-status,.msg-attachment-chip{display:none;color:var(--gmail-muted);font-size:.6rem}.msg-status.show{display:inline}.msg-attachment-chip:not(:empty){display:inline;color:#174ea6;font-size:.61rem}.msg-reply-error,.compose-error{display:none;margin:0 0 8px!important;padding:7px 10px!important;font-size:.64rem!important}.msg-reply-error.show,.compose-error.show{display:block}.msg-read-empty{display:grid;justify-items:center;gap:8px;text-align:center}
.compose-modal .modal-dialog{max-width:600px;margin:1.75rem 1.75rem 1.75rem auto}.compose-modal .modal-content{overflow:hidden;border:0;border-radius:8px;box-shadow:0 8px 35px rgba(60,64,67,.35)}.compose-modal .modal-header{min-height:46px;padding:9px 12px;background:#404040;color:#fff;border:0}.compose-modal .modal-header h5{color:#fff;font-size:.78rem}.compose-modal .modal-header small{color:#d6d6d6!important}.compose-modal .modal-body{padding:0;background:#fff}.compose-modal .form-label{color:var(--gmail-muted);font-size:.64rem}.compose-recipient{padding:12px 14px;background:#fff}.compose-modal .form-control,.compose-modal .form-select{border:0;border-bottom:1px solid #e5e7e9;border-radius:0;background:#fff;font-size:.72rem}.compose-modal .form-control:focus,.compose-modal .form-select:focus{border-bottom-color:var(--gmail-blue);box-shadow:none}.compose-modal textarea{min-height:190px;padding:12px 14px}.compose-modal .modal-body>.mb-3,.compose-modal .modal-body>.mb-2{margin-inline:14px}.compose-modal .modal-footer{padding:8px 12px;background:#fff;border-top:1px solid var(--gmail-rule)}.compose-cancel{border:0;background:transparent;color:var(--gmail-muted);font-size:.67rem}.msg-attach-btn{padding:7px 10px;border:1px solid var(--gmail-border);border-radius:6px;background:#fff;color:var(--gmail-muted);font-size:.64rem}.compose-urgent{margin-inline:14px;color:var(--gmail-muted);font-size:.63rem}.compose-urgent i{color:#d93025}.msg-toast{position:fixed;right:24px;bottom:24px;z-index:5000;display:none;padding:10px 16px;border-radius:4px;background:#323232;color:#fff;font-size:.69rem;box-shadow:0 2px 8px rgba(0,0,0,.25)}.msg-toast.show{display:block}
@media(max-width:1200px){.msg-layout{grid-template-columns:205px 330px minmax(0,1fr)}.msg-thread{padding-inline:28px}}@media(max-width:767px){.messages-shell{margin:0}.msg-toolbar{height:auto;min-height:60px;padding:8px 12px;gap:10px;flex-wrap:wrap}.msg-title{min-width:0;flex:1}.msg-settings{display:none}.msg-toolbar>.msg-search-wrap{order:3;flex-basis:100%;max-width:none}.msg-layout{display:block;height:auto;min-height:0}.msg-sidebar{padding:8px;border-bottom:1px solid var(--gmail-border);border-left:0}.msg-compose-btn{margin:0 0 8px}.msg-nav{display:grid;grid-template-columns:repeat(3,1fr)}.msg-nav a,.msg-nav button{justify-content:center;flex-direction:column;gap:2px;border-radius:8px;padding:7px 3px;font-size:.58rem}.msg-section-label,.msg-sidebar>.small{display:none}.msg-list-pane{border:0}.msg-list-head{min-height:50px}.msg-reading-pane{position:fixed;inset:0;z-index:3000}.msg-thread{padding:20px 14px}.msg-reply{padding:8px}.compose-modal .modal-dialog{max-width:none;margin:0}.compose-modal .modal-content{min-height:100vh;border-radius:0}}
</style>
<div class="messages-shell">
 <div class="msg-toolbar">
  <div class="msg-title"><div class="msg-title-icon"><i class="fas fa-envelope-open-text"></i></div><div><h2>البريد</h2><p>الرسائل الداخلية</p></div></div>
  <div class="msg-search-wrap"><i class="fas fa-magnifying-glass"></i><input type="search" id="msgSearch" class="msg-search" placeholder="بحث في المرسل والموضوع ونص الرسالة" autocomplete="off"></div>
  <div class="msg-settings"><i class="fas fa-text-height"></i><span>حجم النص</span><select id="msgFontSize" class="form-select form-select-sm"><option value="13px">صغير</option><option value="14px" selected>متوسط</option><option value="15px">كبير</option><option value="16px">كبير جداً</option></select></div>
 </div>
 <div class="msg-layout <?php echo $thread['root']?'has-thread':'no-thread';?>">
  <aside class="msg-sidebar">
   <button type="button" class="msg-compose-btn" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="fas fa-pen-to-square me-2"></i>رسالة جديدة</button>
   <nav class="msg-nav">
    <a class="<?php echo $view==='inbox'?'active':'';?>" href="<?php echo $msgUrl;?>"><i class="fas fa-inbox"></i><span>الوارد</span><span class="badge bg-primary msg-count"><?php echo $unread;?></span></a>
    <a class="<?php echo $view==='sent'?'active':'';?>" href="<?php echo $msgUrl;?>?view=sent"><i class="fas fa-paper-plane"></i><span>المرسل</span></a>
    <button type="button" onclick="markAllRead()"><i class="fas fa-check-double"></i><span>تحديد الكل كمقروء</span></button>
   </nav>
   <div class="msg-section-label">حالة المراسلات</div><div class="small text-muted px-2" style="font-size:.65rem;line-height:1.8">يتم تحديث الإشعارات والرسائل الجديدة تلقائياً دون الحاجة إلى إعادة تحميل الصفحة.</div>
  </aside>
  <section class="msg-list-pane">
   <div class="msg-list-head"><h3><?php echo $view==='sent'?'الرسائل المرسلة':'صندوق الوارد';?></h3><div class="msg-filter"><a class="<?php echo $filter==='all'?'active':'';?>" href="?view=<?php echo e($view);?>&filter=all">الكل</a><a class="<?php echo $filter==='unread'?'active':'';?>" href="?view=<?php echo e($view);?>&filter=unread">غير مقروء</a></div></div>
   <div class="msg-list">
    <?php if(!$messages): ?><div class="msg-empty"><div><div class="msg-empty-icon"><i class="fas fa-inbox"></i></div><strong>لا توجد رسائل</strong><span>ستظهر الرسائل الجديدة هنا.</span></div></div><?php endif; ?>
    <?php foreach($messages as $m): $isUnread=isset($m['is_read'])&&(int)$m['is_read']===0; $name=$view==='sent'?($m['recipient_name']??''):$m['sender_name']; $initial=mb_substr($name!==''?$name:'?',0,1,'UTF-8'); ?>
     <a href="<?php echo $msgUrl;?>?message=<?php echo (int)$m['id'];?>" class="msg-row <?php echo $isUnread?'unread':'';?>" data-message-open="<?php echo (int)$m['id'];?>" data-message-search="<?php echo e(($name??'').' '.($m['subject']??'').' '.($m['body_preview']??$m['body']??''));?>"><div class="msg-avatar"><?php echo e($initial);?></div><div class="msg-row-main"><div class="msg-row-top"><span class="msg-name"><?php echo e($name);?></span><span class="msg-time"><?php echo e($m['created_at']);?></span></div><div class="msg-row-bottom"><span class="msg-subject"><?php echo e($m['subject']);?></span><?php if($isUnread):?><span class="msg-new">جديد</span><?php endif;?></div><div class="msg-preview"><?php echo $view==='sent'?'إلى: ':'من: '; echo e($name);?><?php if(!empty($m['recipient_role'])):?> · <?php echo e($m['recipient_role']);?><?php endif;?> · <?php echo e($m['body_preview']??mb_substr($m['body']??'',0,150,'UTF-8'));?></div></div><?php if(!empty($m['is_urgent'])):?><i class="fas fa-triangle-exclamation msg-urgent" title="عاجل"></i><?php endif;?></a>
    <?php endforeach; ?>
   </div>
  </section>

<?php if($thread['root']): $m=$thread['root']; mark_message_read($uid,(int)$m['id']); $rootName=$m['sender_name']; $rootInitial=mb_substr($rootName!==''?$rootName:'?',0,1,'UTF-8'); ?>
 <section class="msg-reading-pane" id="messageOverlay" aria-labelledby="messageOverlayTitle">
  <div class="msg-reading-head">
   <div class="msg-conv-person"><div class="msg-avatar"><?php echo e($rootInitial);?></div><div><div class="msg-conv-name" id="messageOverlayTitle"><?php echo e($rootName);?></div><div class="msg-conv-meta">مراسلة داخلية · <?php echo e($m['created_at']);?> · <?php echo count($thread['replies'])+1;?> رسائل</div></div></div>
   <div class="msg-conv-actions"><button class="msg-icon-btn" type="button" onclick="focusReply()" title="الرد"><i class="fas fa-reply"></i></button><a class="msg-icon-btn" href="<?php echo $msgUrl;?>" title="إغلاق القراءة"><i class="fas fa-xmark"></i></a></div>
  </div>
  <div class="msg-thread" id="messageThread">
   <article class="msg-root" data-message-id="<?php echo (int)$m['id'];?>"><div class="msg-subject-large"><?php echo e($m['subject']);?></div><div class="msg-root-meta"><i class="fas fa-user me-1"></i><?php echo e($rootName);?> · <?php echo e($m['created_at']);?></div><hr class="my-3"><div class="msg-body"><?php echo e($m['body']);?></div><div class="msg-attachments" id="attachments-<?php echo (int)$m['id'];?>"></div></article>
   <?php foreach($thread['replies'] as $r): $mine=(int)$r['sender_id']===$uid; ?><article class="msg-bubble <?php echo $mine?'mine':'';?>" data-message-id="<?php echo (int)$r['id'];?>"><div class="msg-bubble-head"><span class="msg-bubble-name"><?php echo e($r['sender_name']);?></span><span class="msg-bubble-time"><?php echo e($r['created_at']);?></span></div><div class="msg-body"><?php echo e($r['body']);?></div><div class="msg-attachments" id="attachments-<?php echo (int)$r['id'];?>"></div></article><?php endforeach; ?>
  </div>
  <?php if(($m['recipient_user_id']!==null||(int)$m['sender_id']!==$uid)&&!($m['recipient_role']!==null)): ?>
  <div class="msg-reply"><div class="msg-reply-inner"><form id="replyForm"><input type="hidden" name="action" value="reply"><input type="hidden" name="message_id" value="<?php echo (int)$m['id'];?>"><?php echo csrf_field();?><div id="replyError" class="alert alert-danger msg-reply-error"></div><div class="msg-reply-box"><textarea name="body" maxlength="10000" placeholder="اكتب ردك هنا..." required></textarea><div class="msg-reply-tools"><div class="msg-tools"><input type="file" id="replyAttachment" name="attachment" hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip"><button type="button" class="msg-tool" title="إرفاق ملف" onclick="document.getElementById('replyAttachment').click()"><i class="fas fa-paperclip"></i></button><button type="button" class="msg-tool" title="رمز تعبيري"><i class="far fa-face-smile"></i></button><span id="replyAttachmentName" class="msg-attachment-chip"></span></div><div class="d-flex align-items-center gap-2"><span id="replyStatus" class="msg-status"><i class="fas fa-circle-notch fa-spin"></i> جارٍ الإرسال...</span><button class="msg-send" type="submit"><i class="fas fa-paper-plane me-1"></i>إرسال الرد</button></div></div></div></form></div></div>
  <?php else: ?><div class="msg-no-reply">هذه الرسالة جماعية ولا يمكن الرد عليها مباشرة من هذه المحادثة.</div><?php endif; ?>
 </section>
<?php else: ?>
 <section class="msg-reading-pane empty"><div class="msg-read-empty"><div class="msg-read-empty-icon"><i class="fas fa-envelope-open"></i></div><strong>اختر رسالة لقراءتها</strong><span>ستظهر تفاصيل المحادثة والردود والمرفقات هنا.</span></div></section>
<?php endif; ?>
  </div>
 </div>

<div class="modal fade compose-modal" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><div class="d-flex align-items-center gap-2"><div class="compose-head-icon"><i class="fas fa-pen-to-square"></i></div><div><h5 class="modal-title mb-0 fw-bold" id="composeModalLabel">رسالة جديدة</h5><small class="text-muted" style="font-size:.62rem">مراسلة داخلية آمنة</small></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
 <form id="composeForm" novalidate><div class="modal-body"><?php echo csrf_field();?><input type="hidden" name="action" value="send"><div id="composeError" class="alert alert-danger compose-error mb-3"></div>
  <div class="compose-recipient mb-3"><div class="row g-2 align-items-end"><div class="col-sm-4"><label class="form-label">نوع المستلم</label><select name="recipient_type" id="recipientType" class="form-select" required><option value="user">مستخدم محدد</option><?php if($canBroadcast):?><option value="role">دور كامل</option><?php endif;?></select></div><div class="col-sm-8" id="userRecipientWrap"><label class="form-label">المستلم</label><select name="recipient_user_id" id="recipientUser" class="form-select" required><option value="">اختر المستخدم</option><?php foreach($users as $u):?><option value="<?php echo (int)$u['id'];?>"><?php echo e($u['name']);?> — <?php echo e($u['role_name_ar']??$u['role']);?></option><?php endforeach;?></select></div><div class="col-sm-8 d-none" id="roleRecipientWrap"><label class="form-label">الدور المستلم</label><select name="recipient_role" id="recipientRole" class="form-select"><option value="">اختر الدور</option><?php foreach($roles as $r):?><option value="<?php echo e($r['code']);?>"><?php echo e($r['name_ar']?:$r['name_en']);?> (<?php echo (int)$r['active_count'];?> مستخدم)</option><?php endforeach;?></select></div></div></div>
  <div class="mb-3"><label class="form-label">الموضوع</label><input name="subject" maxlength="255" class="form-control" required placeholder="اكتب عنواناً واضحاً للرسالة"></div><div class="mb-2"><label class="form-label">نص الرسالة</label><textarea name="body" maxlength="10000" class="form-control" required placeholder="اكتب رسالتك هنا..."></textarea></div>
  <div class="mb-3 d-flex align-items-center gap-2"><input type="file" id="composeAttachment" name="attachment" hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip"><button type="button" class="msg-attach-btn" onclick="document.getElementById('composeAttachment').click()"><i class="fas fa-paperclip"></i> إرفاق ملف</button><span id="composeAttachmentName" class="msg-attachment-chip"></span></div>
  <label class="compose-urgent d-inline-flex align-items-center gap-2"><input type="checkbox" name="is_urgent" value="1"><i class="fas fa-triangle-exclamation"></i> تعليم الرسالة كعاجلة</label>
 </div><div class="modal-footer"><div class="compose-footer-actions"><button type="button" class="compose-cancel" data-bs-dismiss="modal">إلغاء</button><div class="d-flex align-items-center gap-2"><span id="composeStatus" class="msg-status"><i class="fas fa-circle-notch fa-spin"></i> جارٍ الإرسال...</span><button type="submit" class="compose-send"><i class="fas fa-paper-plane me-1"></i>إرسال الرسالة</button></div></div></div></form>
 </div></div></div>
<div id="msgToast" class="msg-toast"></div>






<script>
const msgCsrf=<?php echo json_encode(csrf_token());?>; const msgUrl=<?php echo json_encode($msgUrl);?>;
const attachmentApiUrl=msgUrl.replace('index.php','attachment.php');
const typeEl=document.getElementById('recipientType'), userWrap=document.getElementById('userRecipientWrap'), roleWrap=document.getElementById('roleRecipientWrap'), userEl=document.getElementById('recipientUser'), roleEl=document.getElementById('recipientRole');
function setRecipientMode(){const roleMode=typeEl?.value==='role';userWrap?.classList.toggle('d-none',roleMode);roleWrap?.classList.toggle('d-none',!roleMode);if(userEl)userEl.required=!roleMode;if(roleEl)roleEl.required=roleMode} typeEl?.addEventListener('change',setRecipientMode);setRecipientMode();
function status(id,on){document.getElementById(id)?.classList.toggle('show',on)} function toast(msg){const x=document.getElementById('msgToast');if(!x)return;x.textContent=msg;x.classList.add('show');setTimeout(()=>x.classList.remove('show'),2500)}
const font=document.getElementById('msgFontSize'); if(font){font.value=localStorage.getItem('ak_msg_font_size')||'14px';document.documentElement.style.setProperty('--msg-size',font.value);font.addEventListener('change',()=>{document.documentElement.style.setProperty('--msg-size',font.value);localStorage.setItem('ak_msg_font_size',font.value)})}
async function postMessage(form){const r=await fetch(msgUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(new FormData(form)),credentials:'same-origin'});const text=await r.text();let data;try{data=JSON.parse(text)}catch(e){throw new Error('استجاب الخادم برد غير صالح.')};if(!r.ok||!data.ok)throw new Error(data.message||'تعذر تنفيذ الطلب.');return data}
document.querySelectorAll('[data-message-open]').forEach(a=>a.addEventListener('click',()=>{a.dataset.opening='1'}));
const msgSearch=document.getElementById('msgSearch');
msgSearch?.addEventListener('input',function(){
 const q=this.value.trim().toLocaleLowerCase();
 document.querySelectorAll('.msg-row[data-message-search]').forEach(row=>{
   row.style.display=!q||row.dataset.messageSearch.toLocaleLowerCase().includes(q)?'flex':'none';
 });
});

/* ---------- Attachments ---------- */
function wireAttachmentPicker(inputId,chipId){
  const input=document.getElementById(inputId), chip=document.getElementById(chipId);
  input?.addEventListener('change',()=>{ chip.textContent = input.files.length ? input.files[0].name : ''; });
}
wireAttachmentPicker('replyAttachment','replyAttachmentName');
wireAttachmentPicker('composeAttachment','composeAttachmentName');

async function uploadAttachmentIfAny(inputEl,messageId){
  if(!inputEl?.files?.length) return;
  const fd=new FormData();
  fd.append('action','upload_attachment');
  fd.append('message_id',messageId);
  fd.append('csrf_token',msgCsrf);
  fd.append('attachment',inputEl.files[0]);
  try{
    const r=await fetch(attachmentApiUrl,{method:'POST',body:fd,credentials:'same-origin'});
    const data=await r.json();
    if(!data.ok) toast(data.message||'تعذر إرفاق الملف.');
  }catch(e){
    toast('تعذر إرفاق الملف.');
  }
  inputEl.value='';
}

function escapeHtml(s){return (s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

function renderAttachments(list){
  const byMessage={};
  list.forEach(a=>{ (byMessage[a.message_id] ||= []).push(a); });
  Object.entries(byMessage).forEach(([mid,atts])=>{
    const box=document.getElementById('attachments-'+mid);
    if(!box) return;
    box.innerHTML=atts.map(a=>`
      <div class="msg-attachment-item" data-attachment-id="${a.id}">
        <i class="fas fa-paperclip"></i>
        <a href="${a.download_url}" target="_blank" rel="noopener">${escapeHtml(a.name ?? a.original_name)}</a>
        <span class="msg-attachment-size">${a.size_label}</span>
        ${a.can_delete ? `<button type="button" class="msg-attachment-del" title="حذف المرفق" onclick="deleteAttachment(${a.id})"><i class="fas fa-trash"></i></button>` : ''}
      </div>`).join('');
  });
}

async function loadThreadAttachments(rootId){
  if(!rootId) return;
  try{
    const r=await fetch(attachmentApiUrl+'?action=list&message_id='+encodeURIComponent(rootId),{credentials:'same-origin'});
    const data=await r.json();
    if(data.ok) renderAttachments(data.attachments);
  }catch(e){ /* attachments are non-critical to display */ }
}

async function deleteAttachment(id){
  if(!confirm('هل تريد حذف هذا المرفق؟')) return;
  const fd=new URLSearchParams({action:'delete_attachment',attachment_id:id,csrf_token:msgCsrf});
  try{
    const r=await fetch(attachmentApiUrl,{method:'POST',body:fd,credentials:'same-origin'});
    const data=await r.json();
    if(data.ok){
      document.querySelector(`.msg-attachment-item[data-attachment-id="${id}"]`)?.remove();
      toast(data.message);
    }else toast(data.message||'تعذر حذف المرفق.');
  }catch(e){ toast('تعذر حذف المرفق.'); }
}

<?php if($thread['root']): ?>
loadThreadAttachments(<?php echo (int)$thread['root']['id'];?>);
<?php endif; ?>

/* ---------- Forms ---------- */
document.getElementById('composeForm')?.addEventListener('submit',async e=>{
  e.preventDefault();
  const f=e.currentTarget,btn=f.querySelector('button[type=submit]'),err=document.getElementById('composeError');
  err?.classList.remove('show');
  if(!f.checkValidity()){f.reportValidity();return}
  btn.disabled=true;status('composeStatus',true);
  try{
    const x=await postMessage(f);
    await uploadAttachmentIfAny(document.getElementById('composeAttachment'),x.id);
    bootstrap.Modal.getInstance(document.getElementById('composeModal'))?.hide();
    toast(x.message);
    setTimeout(()=>location.href=msgUrl+'?message='+encodeURIComponent(x.id),250);
  }catch(ex){
    if(err){err.textContent=ex.message;err.classList.add('show')}else toast(ex.message)
  }finally{btn.disabled=false;status('composeStatus',false)}
});

document.getElementById('replyForm')?.addEventListener('submit',async e=>{
  e.preventDefault();
  const f=e.currentTarget,btn=f.querySelector('button[type=submit]'),err=document.getElementById('replyError');
  err?.classList.remove('show');
  if(!f.checkValidity()){f.reportValidity();return}
  btn.disabled=true;status('replyStatus',true);
  try{
    const x=await postMessage(f);
    await uploadAttachmentIfAny(document.getElementById('replyAttachment'),x.id);
    toast(x.message);
    setTimeout(()=>location.href=msgUrl+'?message='+encodeURIComponent(document.querySelector('[name=message_id]')?.value||''),250);
  }catch(ex){
    if(err){err.textContent=ex.message;err.classList.add('show')}else toast(ex.message)
  }finally{btn.disabled=false;status('replyStatus',false)}
});

function focusReply(){const t=document.querySelector('#replyForm textarea');if(t){t.focus();t.scrollIntoView({behavior:'smooth',block:'nearest'})}}
function closeMessageOverlay(){window.location.href=msgUrl}
document.getElementById('messageOverlay')?.addEventListener('click',e=>{if(e.target.id==='messageOverlay')closeMessageOverlay()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.getElementById('messageOverlay'))closeMessageOverlay()});
async function markAllRead(){try{const f=new URLSearchParams({action:'read_all',csrf_token:msgCsrf});const r=await fetch(msgUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:f,credentials:'same-origin'});const x=await r.json();if(x.ok){toast('تم تحديد الرسائل كمقروءة');setTimeout(()=>location.reload(),250)}}catch(e){toast('تعذر تحديث حالة الرسائل')}}
</script>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>