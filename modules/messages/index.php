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
<style>
:root{--msg-primary:#1b4d8f;--msg-primary-dark:#143b70;--msg-surface:#fff;--msg-border:#e5eaf1;--msg-text:#172338;--msg-muted:#778398;--msg-font:'Cairo',sans-serif;--msg-size:14px;--msg-radius:16px}
.messages-shell{font-family:var(--msg-font);font-size:var(--msg-size);color:var(--msg-text)}
.msg-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.msg-title{display:flex;align-items:center;gap:12px}.msg-title-icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:#eaf2ff;color:var(--msg-primary);font-size:19px}.msg-title h2{font-size:1.25rem;font-weight:800;margin:0}.msg-title p{font-size:.75rem;color:var(--msg-muted);margin:3px 0 0}.msg-settings{display:flex;align-items:center;gap:7px;color:#687589;font-size:.7rem}.msg-settings select{width:100px;font-size:.7rem;border-radius:8px;border-color:var(--msg-border)}
.msg-layout{display:grid;grid-template-columns:215px minmax(0,1fr);height:calc(100vh - 235px);min-height:590px;background:var(--msg-surface);border:1px solid var(--msg-border);border-radius:var(--msg-radius);box-shadow:0 8px 30px rgba(22,55,95,.07);overflow:hidden}.msg-sidebar{background:#f8fafd;border-inline-end:1px solid var(--msg-border);padding:16px}.msg-compose-btn{width:100%;border:0;border-radius:11px;padding:10px 12px;font-weight:800;background:var(--msg-primary);color:#fff;box-shadow:0 5px 14px rgba(27,77,143,.18);margin-bottom:15px}.msg-compose-btn:hover{background:var(--msg-primary-dark)}.msg-nav{display:flex;flex-direction:column;gap:3px}.msg-nav a,.msg-nav button{display:flex;align-items:center;gap:10px;width:100%;border:0;background:transparent;color:#526176;text-decoration:none;border-radius:10px;padding:10px 11px;font-size:.78rem;text-align:start}.msg-nav a:hover,.msg-nav button:hover{background:#edf3fb;color:var(--msg-primary)}.msg-nav a.active{background:#e7f0fc;color:var(--msg-primary);font-weight:800}.msg-nav i{width:20px;text-align:center}.msg-count{margin-inline-start:auto;min-width:22px;font-size:.62rem}.msg-section-label{font-size:.65rem;font-weight:800;color:#9aa5b5;margin:22px 10px 8px}
.msg-list-pane{min-width:0;display:flex;flex-direction:column}.msg-list-head{padding:13px 15px;border-bottom:1px solid var(--msg-border);display:flex;align-items:center;justify-content:space-between;gap:8px}.msg-list-head h3{font-size:.86rem;font-weight:800;margin:0}.msg-filter{display:flex;gap:3px}.msg-filter a{font-size:.63rem;padding:5px 8px;border-radius:7px;text-decoration:none;color:var(--msg-muted)}.msg-filter a.active{background:#eaf2ff;color:var(--msg-primary);font-weight:700}.msg-list{overflow:auto;flex:1}.msg-row{position:relative;display:flex;gap:10px;padding:14px 16px;border:0;border-bottom:1px solid #eef1f5;text-decoration:none;color:inherit;transition:.15s;cursor:pointer}.msg-row:hover{background:#f8fbff}.msg-row.unread{background:#f0f6ff}.msg-row.unread:before{content:'';position:absolute;inset-inline-start:0;top:0;bottom:0;width:3px;background:var(--msg-primary)}.msg-avatar{width:40px;height:40px;flex:0 0 40px;border-radius:12px;display:grid;place-items:center;background:#e8f0fb;color:var(--msg-primary);font-weight:800}.msg-row-main{min-width:0;flex:1}.msg-row-top,.msg-row-bottom{display:flex;align-items:center;justify-content:space-between;gap:7px}.msg-name{font-size:.76rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-time{font-size:.6rem;color:#9aa4b2;white-space:nowrap}.msg-subject{font-size:.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}.msg-preview{font-size:.68rem;color:var(--msg-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px}.msg-new{font-size:.55rem;padding:3px 5px;border-radius:5px;background:#dceaff;color:var(--msg-primary);font-weight:800}.msg-urgent{color:#c0392b;font-size:.66rem}
.msg-empty{height:100%;display:grid;place-items:center;text-align:center;color:var(--msg-muted);padding:50px 20px}.msg-empty-icon{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;background:#eef4fb;color:#8ca0b8;font-size:22px;margin:0 auto 12px}.msg-empty strong{display:block;color:#536176;font-size:.8rem;margin-bottom:3px}
/* Floating conversation overlay */
.msg-overlay{position:fixed;inset:0;z-index:1080;display:none;align-items:center;justify-content:center;padding:28px;background:rgba(15,29,50,.42);backdrop-filter:blur(4px)}.msg-overlay.open{display:flex}.msg-dialog{width:min(1180px,calc(100vw - 70px));height:min(820px,calc(100vh - 70px));min-height:520px;background:#fff;border:1px solid rgba(255,255,255,.55);border-radius:20px;box-shadow:0 25px 80px rgba(5,24,52,.28);display:flex;flex-direction:column;overflow:hidden;animation:msgDialogIn .18s ease-out}.msg-overlay.open .msg-dialog{animation:msgDialogIn .18s ease-out}.msg-conv-head{min-height:70px;padding:13px 20px;border-bottom:1px solid var(--msg-border);display:flex;align-items:center;justify-content:space-between;gap:14px;background:#fff}.msg-conv-person{display:flex;align-items:center;gap:11px;min-width:0}.msg-conv-person .msg-avatar{width:44px;height:44px;flex-basis:44px}.msg-conv-name{font-weight:800;font-size:.88rem}.msg-conv-meta{font-size:.62rem;color:var(--msg-muted);margin-top:3px}.msg-conv-actions{display:flex;gap:6px}.msg-icon-btn{width:34px;height:34px;border:1px solid var(--msg-border);background:#fff;color:#657286;border-radius:9px;display:grid;place-items:center;text-decoration:none}.msg-icon-btn:hover{background:#f4f7fb;color:var(--msg-primary)}.msg-close-btn{background:#f7f8fa}.msg-thread{padding:26px 34px;overflow:auto;flex:1;background:linear-gradient(180deg,#f8fafd 0%,#fff 45%);scroll-behavior:smooth}.msg-root{max-width:980px;margin:0 auto 16px;border:1px solid var(--msg-border);border-radius:15px;padding:19px 21px;background:#fff;box-shadow:0 3px 14px rgba(30,60,100,.045)}.msg-bubble{max-width:min(78%,850px);border-radius:15px;padding:13px 16px;margin:10px auto 10px 0;border:1px solid var(--msg-border);background:#fff;box-shadow:0 2px 9px rgba(30,60,100,.035)}.msg-bubble.mine{margin-left:auto;margin-right:0;background:#eaf3ff;border-color:#d8e7fb}.msg-bubble-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px}.msg-bubble-name{font-weight:800;font-size:.7rem}.msg-bubble-time{font-size:.59rem;color:#99a3b2}.msg-body{white-space:pre-wrap;line-height:1.9;font-size:.82rem;word-break:break-word}.msg-subject-large{font-size:1.02rem;font-weight:800;margin-bottom:5px}.msg-root-meta{font-size:.64rem;color:var(--msg-muted)}.msg-root hr{border-color:#edf0f4}.msg-reply{padding:13px 20px 16px;border-top:1px solid var(--msg-border);background:#fff}.msg-reply-inner{max-width:980px;margin:0 auto}.msg-reply-box{border:1px solid #d9e1eb;border-radius:14px;background:#fafbfd;overflow:hidden;box-shadow:0 3px 12px rgba(20,50,90,.04)}.msg-reply textarea{display:block;width:100%;border:0!important;background:transparent;resize:none;min-height:82px;max-height:190px;padding:13px 15px;font-family:var(--msg-font);font-size:.8rem;line-height:1.8;box-shadow:none!important;outline:none}.msg-reply textarea:focus{background:#fff}.msg-reply-tools{display:flex;align-items:center;justify-content:space-between;padding:7px 9px;border-top:1px solid #e9edf2}.msg-tools{display:flex;align-items:center;gap:2px}.msg-tool{border:0;background:transparent;color:#7c8796;width:31px;height:31px;border-radius:8px}.msg-tool:hover{background:#eaf0f7;color:var(--msg-primary)}.msg-send{border:0;border-radius:9px;background:var(--msg-primary);color:#fff;font-size:.7rem;font-weight:800;padding:8px 14px}.msg-send:hover{background:var(--msg-primary-dark)}.msg-status{display:none;font-size:.64rem;color:#7c8796}.msg-status.show{display:inline-flex;align-items:center;gap:5px}.msg-reply-error{display:none;font-size:.68rem;margin-bottom:7px;padding:7px 10px;border-radius:8px}.msg-reply-error.show{display:block}.msg-no-reply{padding:13px 20px;text-align:center;color:var(--msg-muted);font-size:.68rem;border-top:1px solid var(--msg-border)}
.compose-modal .modal-dialog{max-width:710px}.compose-modal .modal-content{border:0;border-radius:18px;overflow:hidden;box-shadow:0 18px 55px rgba(10,31,68,.25)}.compose-modal .modal-header{padding:14px 18px;background:#fff;color:var(--msg-text);border-bottom:1px solid var(--msg-border)}.compose-head-icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:#eaf2ff;color:var(--msg-primary)}.compose-modal .modal-body{padding:17px 19px}.compose-modal .modal-footer{padding:10px 19px;border-top:1px solid var(--msg-border);background:#fbfcfe}.compose-modal .form-label{font-size:.69rem;font-weight:800;color:#59677a;margin-bottom:5px}.compose-modal .form-control,.compose-modal .form-select{border-radius:9px;border-color:#dfe5ed;font-family:var(--msg-font);font-size:.76rem;padding:.52rem .68rem}.compose-modal textarea{min-height:145px;resize:vertical}.compose-recipient{padding:11px;background:#f7f9fc;border:1px solid #e8edf3;border-radius:12px}.compose-error{display:none;border-radius:9px;font-size:.72rem}.compose-error.show{display:block}.compose-urgent{font-size:.69rem;color:#667386}.compose-urgent input{accent-color:#c0392b}.compose-footer-actions{display:flex;align-items:center;justify-content:space-between;width:100%}.compose-send{border:0;background:var(--msg-primary);color:#fff;border-radius:9px;padding:8px 15px;font-size:.73rem;font-weight:800}.compose-send:hover{background:var(--msg-primary-dark)}.compose-cancel{border:1px solid #dfe5ed;background:#fff;color:#687589;border-radius:9px;padding:8px 13px;font-size:.72rem}.msg-toast{position:fixed;bottom:22px;inset-inline-end:22px;z-index:1090;display:none;padding:9px 13px;border-radius:10px;background:#202938;color:#fff;font-size:.72rem;box-shadow:0 8px 25px rgba(0,0,0,.18)}.msg-toast.show{display:block}
@keyframes msgDialogIn{from{opacity:0;transform:translateY(10px) scale(.985)}to{opacity:1;transform:none}}
/* Attachments */
.msg-attach-btn{display:inline-flex;align-items:center;gap:6px;font-size:.68rem;color:#59677a;background:#f4f7fb;border:1px solid #e3e9f1;border-radius:9px;padding:7px 11px;cursor:pointer}
.msg-attach-btn:hover{background:#eaf2ff;color:var(--msg-primary)}
.msg-attachment-chip{font-size:.68rem;color:var(--msg-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.msg-attachments{margin-top:.6rem;display:flex;flex-direction:column;gap:.35rem}
.msg-attachment-item{display:flex;align-items:center;gap:.4rem;font-size:.72rem;background:#f4f7fb;border:1px solid var(--msg-border);border-radius:8px;padding:.35rem .6rem}
.msg-attachment-item a{color:var(--msg-primary);text-decoration:none;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.msg-attachment-item a:hover{text-decoration:underline}
.msg-attachment-size{color:var(--msg-muted);font-size:.64rem;white-space:nowrap}
.msg-attachment-del{border:0;background:none;color:#c0392b;cursor:pointer;padding:0 .2rem;font-size:.72rem}
@media(max-width:767px){.msg-toolbar{align-items:flex-start}.msg-settings{display:none}.msg-layout{display:block;height:auto;min-height:0}.msg-sidebar{border:0;border-bottom:1px solid var(--msg-border)}.msg-nav{display:grid;grid-template-columns:repeat(3,1fr)}.msg-nav a,.msg-nav button{justify-content:center;flex-direction:column;gap:3px;padding:8px 4px;text-align:center;font-size:.61rem}.msg-section-label{display:none}.msg-compose-btn{margin-bottom:10px}.msg-list{min-height:520px}.msg-overlay{padding:0}.msg-dialog{width:100%;height:100%;min-height:100%;border-radius:0;border:0}.msg-conv-head{padding:11px 13px}.msg-thread{padding:16px 12px}.msg-root{padding:15px}.msg-bubble{max-width:94%}.msg-reply{padding:10px}.msg-reply textarea{min-height:74px}.msg-conv-meta{font-size:.58rem}}
</style>

<div class="messages-shell">
 <div class="msg-toolbar">
  <div class="msg-title"><div class="msg-title-icon"><i class="fas fa-envelope-open-text"></i></div><div><h2>الرسائل الداخلية</h2><p>مراسلات آمنة بين مستخدمي النظام والأدوار الإدارية</p></div></div>
  <div class="msg-settings"><i class="fas fa-text-height"></i><span>حجم النص</span><select id="msgFontSize" class="form-select form-select-sm"><option value="13px">صغير</option><option value="14px" selected>متوسط</option><option value="15px">كبير</option><option value="16px">كبير جداً</option></select></div>
 </div>
 <div class="msg-layout">
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
     <a href="<?php echo $msgUrl;?>?message=<?php echo (int)$m['id'];?>" class="msg-row <?php echo $isUnread?'unread':'';?>" data-message-open="<?php echo (int)$m['id'];?>"><div class="msg-avatar"><?php echo e($initial);?></div><div class="msg-row-main"><div class="msg-row-top"><span class="msg-name"><?php echo e($name);?></span><span class="msg-time"><?php echo e($m['created_at']);?></span></div><div class="msg-row-bottom"><span class="msg-subject"><?php echo e($m['subject']);?></span><?php if($isUnread):?><span class="msg-new">جديد</span><?php endif;?></div><div class="msg-preview"><?php echo $view==='sent'?'إلى: ':'من: '; echo e($name);?><?php if(!empty($m['recipient_role'])):?> · <?php echo e($m['recipient_role']);?><?php endif;?> · <?php echo e($m['body_preview']??mb_substr($m['body']??'',0,150,'UTF-8'));?></div></div><?php if(!empty($m['is_urgent'])):?><i class="fas fa-triangle-exclamation msg-urgent" title="عاجل"></i><?php endif;?></a>
    <?php endforeach; ?>
   </div>
  </section>
 </div>
</div>

<?php if($thread['root']): $m=$thread['root']; mark_message_read($uid,(int)$m['id']); $rootName=$m['sender_name']; $rootInitial=mb_substr($rootName!==''?$rootName:'?',0,1,'UTF-8'); ?>
<div class="msg-overlay open" id="messageOverlay" role="dialog" aria-modal="true" aria-labelledby="messageOverlayTitle">
 <div class="msg-dialog">
  <div class="msg-conv-head">
   <div class="msg-conv-person"><div class="msg-avatar"><?php echo e($rootInitial);?></div><div><div class="msg-conv-name" id="messageOverlayTitle"><?php echo e($rootName);?></div><div class="msg-conv-meta">مراسلة داخلية · <?php echo e($m['created_at']);?></div></div></div>
   <div class="msg-conv-actions"><button class="msg-icon-btn" type="button" onclick="focusReply()" title="الرد"><i class="fas fa-reply"></i></button><a class="msg-icon-btn msg-close-btn" href="<?php echo $msgUrl;?>" title="إغلاق"><i class="fas fa-xmark"></i></a></div>
  </div>
  <div class="msg-thread" id="messageThread">
   <article class="msg-root" data-message-id="<?php echo (int)$m['id'];?>"><div class="msg-subject-large"><?php echo e($m['subject']);?></div><div class="msg-root-meta"><i class="fas fa-user me-1"></i><?php echo e($rootName);?> · <?php echo e($m['created_at']);?></div><hr class="my-3"><div class="msg-body"><?php echo e($m['body']);?></div><div class="msg-attachments" id="attachments-<?php echo (int)$m['id'];?>"></div></article>
   <?php foreach($thread['replies'] as $r): $mine=(int)$r['sender_id']===$uid; ?><article class="msg-bubble <?php echo $mine?'mine':'';?>" data-message-id="<?php echo (int)$r['id'];?>"><div class="msg-bubble-head"><span class="msg-bubble-name"><?php echo e($r['sender_name']);?></span><span class="msg-bubble-time"><?php echo e($r['created_at']);?></span></div><div class="msg-body"><?php echo e($r['body']);?></div><div class="msg-attachments" id="attachments-<?php echo (int)$r['id'];?>"></div></article><?php endforeach; ?>
  </div>
  <?php if(($m['recipient_user_id']!==null||(int)$m['sender_id']!==$uid)&&!($m['recipient_role']!==null)): ?>
  <div class="msg-reply"><div class="msg-reply-inner"><form id="replyForm"><input type="hidden" name="action" value="reply"><input type="hidden" name="message_id" value="<?php echo (int)$m['id'];?>"><?php echo csrf_field();?><div id="replyError" class="alert alert-danger msg-reply-error"></div><div class="msg-reply-box"><textarea name="body" maxlength="10000" placeholder="اكتب ردك هنا..." required></textarea><div class="msg-reply-tools"><div class="msg-tools"><input type="file" id="replyAttachment" name="attachment" hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip"><button type="button" class="msg-tool" title="إرفاق ملف" onclick="document.getElementById('replyAttachment').click()"><i class="fas fa-paperclip"></i></button><button type="button" class="msg-tool" title="رمز تعبيري"><i class="far fa-face-smile"></i></button><span id="replyAttachmentName" class="msg-attachment-chip"></span></div><div class="d-flex align-items-center gap-2"><span id="replyStatus" class="msg-status"><i class="fas fa-circle-notch fa-spin"></i> جارٍ الإرسال...</span><button class="msg-send" type="submit"><i class="fas fa-paper-plane me-1"></i>إرسال الرد</button></div></div></div></form></div></div>
  <?php else: ?><div class="msg-no-reply">هذه الرسالة جماعية ولا يمكن الرد عليها مباشرة من هذه المحادثة.</div><?php endif; ?>
 </div>
</div>
<?php endif; ?>

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

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>