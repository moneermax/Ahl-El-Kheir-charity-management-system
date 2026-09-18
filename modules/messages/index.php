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

$dashboardMap = [
    'admin' => 'dashboard/admin_dashboard.php',
    'general_manager' => 'dashboard/gm_dashboard.php',
    'vice_general_manager' => 'dashboard/vgm_dashboard.php',
    'financial_manager' => 'modules/accounting/fm_dashboard.php',
    'accountant' => 'dashboard/accountant_dashboard.php',
    'accountant_staff' => 'dashboard/accountant_staff_dashboard.php',
    'nanny' => 'dashboard/nanny_dashboard.php',
    'supervisor' => 'dashboard/supervisor_dashboard.php',
    'administration' => 'dashboard/staff_dashboard.php',
    'staff' => 'dashboard/staff_dashboard.php',
    'social_media' => 'dashboard/staff_dashboard.php',
    'projects_manager' => 'dashboard/projects_dashboard.php',
    'project_supervisor' => 'dashboard/projects_dashboard.php',
    'hr_manager' => 'dashboard/hr_dashboard.php',
    'hr_staff' => 'dashboard/hr_dashboard.php'
];
$dashboardUrl = APP_URL . ($dashboardMap[$role] ?? '');

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


<style id="msg-mail-client-v1">
/* Keep the application's global header, sidebar and footer intact.
   The mail client lives inside the normal .content area. */
.messages-shell{
 margin:0;
 width:100%;
 max-width:100%;
 min-height:0;
 background:#f6f8fc;
 color:#202124;
 --mail-bg:#f6f8fc;--mail-surface:#fff;--mail-side:#f6f8fc;--mail-text:#202124;
 --mail-muted:#5f6368;--mail-line:#e0e3e7;--mail-hover:#f2f6fc;--mail-selected:#d3e3fd;
 --mail-blue:#0b57d0;--mail-compose:#c2e7ff;--mail-danger:#b3261e;
 direction:rtl;background:var(--mail-bg);color:var(--mail-text);
 font-family:'Cairo',Arial,sans-serif;font-size:14px
}
.messages-shell *{box-sizing:border-box}.messages-shell a{text-decoration:none;color:inherit}
.messages-shell button,.messages-shell input,.messages-shell select,.messages-shell textarea{font-family:inherit}

.mail-pagebar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:0 4px 14px}
.mail-page-title{display:flex;align-items:center;gap:10px;font-size:1.05rem;font-weight:700;color:#202124}
.mail-page-title i{color:#d93025;font-size:1rem}
.mail-page-subtitle{margin-top:2px;color:#6b7280;font-size:.66rem}
.mail-back-dashboard{display:inline-flex;align-items:center;gap:8px;height:36px;padding:0 14px;border:1px solid #dadce0;border-radius:18px;background:#fff;color:#3c4043;font-size:.67rem;font-weight:600;box-shadow:0 1px 2px rgba(60,64,67,.08)}
.mail-back-dashboard:hover{background:#f1f3f4;color:#202124}
.mail-appbar{height:64px;display:grid;grid-template-columns:240px minmax(300px,720px) 1fr;align-items:center;gap:24px;padding:8px 18px;background:var(--mail-bg)}
.mail-brand{display:flex;align-items:center;gap:12px;font-size:18px;font-weight:500;color:#3c4043}
.mail-brand-icon{font-size:22px;color:#d93025}.mail-search{position:relative}.mail-search i{position:absolute;right:17px;top:50%;transform:translateY(-50%);color:var(--mail-muted)}
.mail-search input{width:100%;height:46px;border:0;border-radius:24px;background:#eaf1fb;padding:0 48px 0 18px;outline:0;font-size:.78rem}
.mail-search input:focus{background:#fff;box-shadow:0 1px 3px rgba(60,64,67,.25),0 0 0 1px #d7dbe0}
.mail-tools{display:flex;justify-content:flex-start;align-items:center;gap:8px;color:var(--mail-muted);font-size:.66rem}.mail-tools select{height:32px;border:1px solid var(--mail-line);border-radius:8px;background:#fff;padding:0 8px;font-size:.65rem}

.mail-workspace{display:grid;grid-template-areas:"reader list folders";grid-template-columns:minmax(0,1fr) 420px 238px;height:min(720px,calc(100vh - 300px));min-height:520px;overflow:hidden;padding:0 0 10px;gap:0}
.mail-folders{grid-area:folders;background:var(--mail-side);padding:10px 8px 0;border-radius:0 16px 16px 0;overflow:auto}
.mail-compose{height:56px;min-width:150px;margin:2px 6px 16px;padding:0 22px;border:0;border-radius:16px;background:var(--mail-compose);color:#001d35;font-size:.78rem;font-weight:700;box-shadow:0 1px 2px rgba(60,64,67,.2);cursor:pointer}
.mail-compose:hover{box-shadow:0 2px 5px rgba(60,64,67,.25)}
.mail-nav{display:flex;flex-direction:column;gap:2px}.mail-nav a,.mail-nav button{height:34px;display:flex;align-items:center;gap:14px;width:100%;border:0;border-radius:17px 0 0 17px;background:transparent;padding:0 16px;color:#3c4043;text-align:right;font-size:.73rem;cursor:pointer}
.mail-nav a:hover,.mail-nav button:hover{background:#e8eaed}.mail-nav a.active{background:var(--mail-selected);font-weight:700;color:#001d35}.mail-nav i{width:19px;text-align:center}.mail-nav .count{margin-inline-start:auto;font-size:.65rem;font-weight:700}
.mail-side-note{margin:22px 16px 0;color:var(--mail-muted);font-size:.62rem;line-height:1.8}

.mail-list{grid-area:list;display:flex;min-width:0;flex-direction:column;background:#fff;border:1px solid var(--mail-line);border-left:0;border-radius:0 16px 16px 0;overflow:hidden}
.mail-list-toolbar{height:54px;display:flex;align-items:center;gap:8px;padding:0 14px;border-bottom:1px solid var(--mail-line);background:#fff}
.mail-list-toolbar h3{margin:0;flex:1;font-size:.83rem;font-weight:600}.mail-filter{display:flex;gap:4px}.mail-filter a{padding:7px 8px;border-radius:7px;color:var(--mail-muted);font-size:.62rem}.mail-filter a:hover{background:#f1f3f4}.mail-filter a.active{background:#e8f0fe;color:var(--mail-blue);font-weight:700}
.mail-rows{min-height:0;flex:1;overflow:auto}.mail-row{position:relative;display:grid;grid-template-columns:38px minmax(0,1fr);gap:10px;min-height:76px;padding:10px 14px;border-bottom:1px solid #edf0f2;background:#fff}
.mail-row:hover{background:var(--mail-hover);box-shadow:inset 0 1px #e7eaed,inset 0 -1px #e7eaed}.mail-row.unread{background:#f8fbff}.mail-row.selected{background:var(--mail-selected)}
.mail-row.unread:before{content:"";position:absolute;right:0;top:0;bottom:0;width:3px;background:var(--mail-blue)}
.mail-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e8eaed;color:#5f6368;font-size:.72rem;font-weight:700}.mail-row-content{min-width:0}
.mail-line1,.mail-line2{display:flex;align-items:center;gap:8px}.mail-line1{justify-content:space-between}.mail-sender,.mail-subject,.mail-snippet{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.mail-sender{font-size:.72rem}.mail-time{font-size:.56rem;color:var(--mail-muted);white-space:nowrap}.mail-subject{margin-top:3px;font-size:.7rem}.mail-snippet{margin-top:3px;font-size:.61rem;color:var(--mail-muted)}.mail-row.unread .mail-sender,.mail-row.unread .mail-subject{font-weight:700}.mail-badge-new{margin-inline-start:auto;color:var(--mail-blue);font-size:.52rem;font-weight:700}.mail-urgent{position:absolute;left:12px;bottom:10px;color:var(--mail-danger);font-size:.62rem}

.mail-reader{grid-area:reader;min-width:0;display:flex;flex-direction:column;background:#fff;border:1px solid var(--mail-line);border-right:0;border-radius:16px 0 0 16px;overflow:hidden}
.mail-reader.empty{align-items:center;justify-content:center;color:var(--mail-muted)}.mail-empty{text-align:center}.mail-empty i{font-size:40px;color:#bdc1c6;margin-bottom:12px}.mail-empty strong{display:block;font-size:.82rem;font-weight:500}.mail-empty span{display:block;margin-top:4px;font-size:.64rem;color:#9aa0a6}
.mail-reader-toolbar{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 18px;border-bottom:1px solid var(--mail-line)}
.mail-person{display:flex;align-items:center;gap:10px;min-width:0}.mail-person-name{font-size:.77rem;font-weight:700}.mail-person-meta{margin-top:2px;color:var(--mail-muted);font-size:.58rem}.mail-reader-actions{display:flex;gap:2px}.mail-icon-btn{width:34px;height:34px;border:0;border-radius:50%;display:grid;place-items:center;background:transparent;color:var(--mail-muted);cursor:pointer}.mail-icon-btn:hover{background:#f1f3f4;color:#202124}
.mail-thread{min-height:0;flex:1;overflow:auto;padding:28px 38px}.mail-subject-large{margin:0 0 18px;font-size:1.3rem;font-weight:400}.mail-message{padding:0 0 24px;margin-bottom:18px;border-bottom:1px solid #edf0f2}.mail-message:last-child{margin-bottom:0}.mail-message-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.mail-message-from{font-size:.7rem;font-weight:700}.mail-message-time{font-size:.59rem;color:var(--mail-muted)}.mail-body{white-space:pre-wrap;font-size:.79rem;line-height:1.95;color:#202124}
.msg-attachments{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}.msg-attachment-item{display:flex;align-items:center;gap:7px;max-width:270px;border:1px solid var(--mail-line);border-radius:8px;background:#f8fafd;padding:8px 10px;font-size:.63rem}.msg-attachment-item a{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--mail-blue)}.msg-attachment-size{color:var(--mail-muted)}.msg-attachment-del{border:0;background:transparent;color:var(--mail-muted)}
.mail-reply{padding:12px 20px 16px;border-top:1px solid var(--mail-line);background:#fff}.mail-reply-box{overflow:hidden;border:1px solid var(--mail-line);border-radius:12px;box-shadow:0 1px 2px rgba(60,64,67,.1)}.mail-reply textarea{display:block;width:100%;min-height:86px;max-height:190px;border:0;padding:12px 14px;resize:vertical;outline:0;font-size:.76rem}.mail-reply-tools{display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-top:1px solid #edf0f2}.msg-tools{display:flex;align-items:center;gap:2px}.msg-tool{width:30px;height:30px;border:0;border-radius:50%;background:transparent;color:var(--mail-muted)}.msg-tool:hover{background:#f1f3f4}.msg-send,.compose-send{border:0;border-radius:18px;background:var(--mail-blue);color:#fff;padding:8px 18px;font-size:.68rem;font-weight:700}.mail-no-reply{padding:14px 20px;border-top:1px solid var(--mail-line);color:var(--mail-muted);font-size:.64rem}
.msg-status,.msg-attachment-chip{display:none}.msg-status.show{display:inline;color:var(--mail-muted);font-size:.6rem}.msg-attachment-chip:not(:empty){display:inline;color:var(--mail-blue);font-size:.62rem}.msg-reply-error,.compose-error{display:none}.msg-reply-error.show,.compose-error.show{display:block}

.compose-modal .modal-dialog{max-width:620px;margin:1.75rem 1.75rem 1.75rem auto}.compose-modal .modal-content{overflow:hidden;border:0;border-radius:10px;box-shadow:0 8px 35px rgba(60,64,67,.35)}.compose-modal .modal-header{padding:9px 12px;background:#404040;color:#fff;border:0}.compose-modal .modal-header h5{font-size:.78rem;color:#fff}.compose-modal .modal-header small{color:#d6d6d6!important}.compose-modal .modal-body{padding:0}.compose-recipient{padding:12px 14px}.compose-modal .form-label{font-size:.64rem;color:var(--mail-muted)}.compose-modal .form-control,.compose-modal .form-select{border:0;border-bottom:1px solid #e5e7e9;border-radius:0;font-size:.72rem}.compose-modal .form-control:focus,.compose-modal .form-select:focus{box-shadow:none;border-bottom-color:var(--mail-blue)}.compose-modal textarea{min-height:200px;padding:12px 14px}.compose-modal .modal-body>.mb-3,.compose-modal .modal-body>.mb-2{margin-inline:14px}.compose-modal .modal-footer{padding:8px 12px;border-top:1px solid #edf0f2}.compose-cancel{border:0;background:transparent;color:var(--mail-muted)}.msg-attach-btn{border:1px solid var(--mail-line);border-radius:8px;background:#fff;padding:7px 10px;color:var(--mail-muted);font-size:.65rem}.compose-urgent{margin-inline:14px;font-size:.64rem;color:var(--mail-muted)}.compose-urgent i{color:var(--mail-danger)}
.msg-toast{position:fixed;right:24px;bottom:24px;z-index:5000;display:none;padding:10px 16px;border-radius:5px;background:#323232;color:#fff;font-size:.7rem}.msg-toast.show{display:block}

@media(max-width:1180px){.mail-workspace{grid-template-columns:minmax(0,1fr) 360px 205px}.mail-appbar{grid-template-columns:205px minmax(260px,1fr) auto}.mail-thread{padding-inline:26px}}
@media(max-width:900px){.mail-workspace{grid-template-areas:"list folders";grid-template-columns:minmax(0,1fr) 190px}.mail-reader{position:fixed;inset:0;z-index:3000;border:0;border-radius:0}.mail-reader.empty{display:none}}
@media(max-width:767px){.mail-appbar{height:auto;grid-template-columns:1fr auto;gap:8px;padding:8px 10px}.mail-search{grid-column:1/-1;grid-row:2}.mail-tools span,.mail-tools select{display:none}.mail-workspace{display:block;height:auto;min-height:0;padding:0}.mail-folders{border-radius:0;padding:8px}.mail-compose{height:44px;margin:0 0 8px}.mail-nav{display:grid;grid-template-columns:repeat(3,1fr)}.mail-nav a,.mail-nav button{height:auto;min-height:48px;justify-content:center;flex-direction:column;gap:3px;border-radius:8px;font-size:.58rem}.mail-side-note{display:none}.mail-list{border:0;border-radius:0}.mail-reader{position:fixed;inset:0;z-index:3000;border:0;border-radius:0}.mail-thread{padding:20px 14px}.mail-reply{padding:8px}.compose-modal .modal-dialog{max-width:none;margin:0}.compose-modal .modal-content{min-height:100vh;border-radius:0}}
</style>
<div class="messages-shell">
 <div class="mail-pagebar">
  <div>
   <div class="mail-page-title"><i class="fas fa-envelope-open-text"></i><span>الرسائل الداخلية</span></div>
   <div class="mail-page-subtitle">البريد والمراسلات داخل النظام</div>
  </div>
  <a class="mail-back-dashboard" href="javascript:void(0)" onclick="window.location.href='<?php echo e($dashboardUrl); ?>'" title="العودة إلى لوحة التحكم">
   <i class="fas fa-arrow-right"></i><span>لوحة التحكم</span>
  </a>
 </div>
 <header class="mail-appbar">
  <div class="mail-brand"><i class="fas fa-envelope mail-brand-icon"></i><span>البريد</span></div>
  <div class="mail-search"><i class="fas fa-magnifying-glass"></i><input type="search" id="msgSearch" placeholder="البحث في البريد" autocomplete="off"></div>
  <div class="mail-tools"><i class="fas fa-text-height"></i><span>حجم النص</span><select id="msgFontSize"><option value="13px">صغير</option><option value="14px" selected>متوسط</option><option value="15px">كبير</option><option value="16px">كبير جداً</option></select></div>
 </header>

 <main class="mail-workspace">
  <aside class="mail-folders">
   <button type="button" class="mail-compose" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="fas fa-pen me-2"></i>إنشاء</button>
   <nav class="mail-nav">
    <a class="<?php echo $view==='inbox'?'active':'';?>" href="<?php echo $msgUrl;?>"><i class="fas fa-inbox"></i><span>الوارد</span><span class="count"><?php echo $unread;?></span></a>
    <a class="<?php echo $view==='sent'?'active':'';?>" href="<?php echo $msgUrl;?>?view=sent"><i class="fas fa-paper-plane"></i><span>المرسل</span></a>
    <button type="button" onclick="markAllRead()"><i class="fas fa-check-double"></i><span>تحديد الكل كمقروء</span></button>
   </nav>
   <div class="mail-side-note">يتم تحديث حالة الرسائل والإشعارات تلقائياً داخل النظام.</div>
  </aside>

  <section class="mail-list">
   <div class="mail-list-toolbar">
    <h3><?php echo $view==='sent'?'الرسائل المرسلة':'صندوق الوارد';?></h3>
    <div class="mail-filter">
     <a class="<?php echo $filter==='all'?'active':'';?>" href="?view=<?php echo e($view);?>&filter=all">الكل</a>
     <a class="<?php echo $filter==='unread'?'active':'';?>" href="?view=<?php echo e($view);?>&filter=unread">غير مقروء</a>
    </div>
   </div>
   <div class="mail-rows">
    <?php if(!$messages): ?>
     <div class="mail-empty" style="padding:70px 20px"><i class="fas fa-inbox"></i><strong>لا توجد رسائل</strong><span>ستظهر الرسائل الجديدة هنا.</span></div>
    <?php endif; ?>
    <?php foreach($messages as $m):
      $isUnread=isset($m['is_read'])&&(int)$m['is_read']===0;
      $name=$view==='sent'?($m['recipient_name']??''):$m['sender_name'];
      $initial=mb_substr($name!==''?$name:'?',0,1,'UTF-8');
      $openUrl=$msgUrl.'?view='.urlencode((string)$view).'&filter='.urlencode((string)$filter).'&message='.(int)$m['id'];
    ?>
     <a href="<?php echo e($openUrl);?>" class="mail-row <?php echo $isUnread?'unread ':'';?><?php echo $messageId===(int)$m['id']?'selected':'';?>" data-message-open="<?php echo (int)$m['id'];?>" data-message-search="<?php echo e(($name??'').' '.($m['subject']??'').' '.($m['body_preview']??$m['body']??''));?>">
      <div class="mail-avatar"><?php echo e($initial);?></div>
      <div class="mail-row-content">
       <div class="mail-line1"><span class="mail-sender"><?php echo e($name);?></span><span class="mail-time"><?php echo e($m['created_at']);?></span></div>
       <div class="mail-line2"><span class="mail-subject"><?php echo e($m['subject']);?></span><?php if($isUnread):?><span class="mail-badge-new">جديد</span><?php endif;?></div>
       <div class="mail-snippet"><?php echo $view==='sent'?'إلى: ':'من: '; echo e($name);?><?php if(!empty($m['recipient_role'])):?> · <?php echo e($m['recipient_role']);?><?php endif;?> · <?php echo e($m['body_preview']??mb_substr($m['body']??'',0,150,'UTF-8'));?></div>
      </div>
      <?php if(!empty($m['is_urgent'])):?><i class="fas fa-triangle-exclamation mail-urgent" title="عاجل"></i><?php endif;?>
     </a>
    <?php endforeach; ?>
   </div>
  </section>

  <?php if($thread['root']): $m=$thread['root']; mark_message_read($uid,(int)$m['id']); $rootName=$m['sender_name']; $rootInitial=mb_substr($rootName!==''?$rootName:'?',0,1,'UTF-8'); ?>
   <section class="mail-reader" id="messageOverlay" aria-labelledby="messageOverlayTitle">
    <div class="mail-reader-toolbar">
     <div class="mail-person">
      <div class="mail-avatar"><?php echo e($rootInitial);?></div>
      <div><div class="mail-person-name" id="messageOverlayTitle"><?php echo e($rootName);?></div><div class="mail-person-meta"><?php echo e($m['created_at']);?> · <?php echo count($thread['replies'])+1;?> رسائل</div></div>
     </div>
     <div class="mail-reader-actions">
      <button class="mail-icon-btn" type="button" onclick="focusReply()" title="الرد"><i class="fas fa-reply"></i></button>
      <a class="mail-icon-btn" href="<?php echo $msgUrl;?>?view=<?php echo e($view);?>&filter=<?php echo e($filter);?>" title="إغلاق"><i class="fas fa-xmark"></i></a>
     </div>
    </div>
    <div class="mail-thread" id="messageThread">
     <h1 class="mail-subject-large"><?php echo e($m['subject']);?></h1>
     <article class="mail-message" data-message-id="<?php echo (int)$m['id'];?>">
      <div class="mail-message-head"><span class="mail-message-from"><?php echo e($rootName);?></span><span class="mail-message-time"><?php echo e($m['created_at']);?></span></div>
      <div class="mail-body"><?php echo e($m['body']);?></div>
      <div class="msg-attachments" id="attachments-<?php echo (int)$m['id'];?>"></div>
     </article>
     <?php foreach($thread['replies'] as $r): ?>
      <article class="mail-message" data-message-id="<?php echo (int)$r['id'];?>">
       <div class="mail-message-head"><span class="mail-message-from"><?php echo e($r['sender_name']);?></span><span class="mail-message-time"><?php echo e($r['created_at']);?></span></div>
       <div class="mail-body"><?php echo e($r['body']);?></div>
       <div class="msg-attachments" id="attachments-<?php echo (int)$r['id'];?>"></div>
      </article>
     <?php endforeach; ?>
    </div>

    <?php if(($m['recipient_user_id']!==null||(int)$m['sender_id']!==$uid)&&!($m['recipient_role']!==null)): ?>
     <div class="mail-reply"><form id="replyForm"><input type="hidden" name="action" value="reply"><input type="hidden" name="message_id" value="<?php echo (int)$m['id'];?>"><?php echo csrf_field();?><div id="replyError" class="alert alert-danger msg-reply-error"></div><div class="mail-reply-box"><textarea name="body" maxlength="10000" placeholder="اكتب ردك هنا..." required></textarea><div class="mail-reply-tools"><div class="msg-tools"><input type="file" id="replyAttachment" name="attachment" hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip"><button type="button" class="msg-tool" title="إرفاق ملف" onclick="document.getElementById('replyAttachment').click()"><i class="fas fa-paperclip"></i></button><span id="replyAttachmentName" class="msg-attachment-chip"></span></div><div class="d-flex align-items-center gap-2"><span id="replyStatus" class="msg-status"><i class="fas fa-circle-notch fa-spin"></i> جارٍ الإرسال...</span><button class="msg-send" type="submit"><i class="fas fa-paper-plane me-1"></i>إرسال</button></div></div></div></form></div>
    <?php else: ?><div class="mail-no-reply">هذه الرسالة جماعية ولا يمكن الرد عليها مباشرة من هذه المحادثة.</div><?php endif; ?>
   </section>
  <?php else: ?>
   <section class="mail-reader empty"><div class="mail-empty"><i class="far fa-envelope-open"></i><strong>اختر رسالة لقراءتها</strong><span>ستظهر الرسالة والمحادثة هنا.</span></div></section>
  <?php endif; ?>
 </main>
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
 document.querySelectorAll('.mail-row[data-message-search]').forEach(row=>{
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