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


<style id="msg-gmail-clean-v7">
.messages-shell{--g-bg:#f6f8fc;--g-sidebar:#f2f6fc;--g-blue:#0b57d0;--g-blue-soft:#d3e3fd;--g-text:#202124;--g-muted:#5f6368;--g-line:#dadce0;--g-line-soft:#f1f3f4;--g-font:'Cairo',Arial,sans-serif;margin:0 -12px 0;font-family:var(--g-font)!important;font-size:14px!important;color:var(--g-text)!important;background:var(--g-bg)!important}
.messages-shell *{box-sizing:border-box}
.messages-shell .msg-toolbar{height:72px!important;margin:0!important;padding:10px 20px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;background:var(--g-bg)!important;border:0!important}
.messages-shell .msg-title{display:flex!important;align-items:center!important;gap:12px!important}.messages-shell .msg-title-icon{width:40px!important;height:40px!important;flex:0 0 40px!important;display:grid!important;place-items:center!important;border-radius:50%!important;background:var(--g-blue)!important;color:#fff!important;box-shadow:none!important;font-size:17px!important}.messages-shell .msg-title h2{margin:0!important;color:var(--g-text)!important;font-size:1.05rem!important;font-weight:500!important}.messages-shell .msg-title p{margin:2px 0 0!important;color:var(--g-muted)!important;font-size:.67rem!important}.messages-shell .msg-settings{display:flex!important;align-items:center!important;gap:7px!important;color:var(--g-muted)!important;font-size:.68rem!important}.messages-shell .msg-settings select{width:88px!important;height:30px!important;padding:.15rem .45rem!important;border:1px solid var(--g-line)!important;border-radius:6px!important;background:#fff!important;color:var(--g-text)!important}
.messages-shell .msg-layout,.messages-shell .msg-layout.has-thread,.messages-shell .msg-layout.no-thread{display:grid!important;grid-template-columns:220px 390px minmax(0,1fr)!important;width:100%!important;height:calc(100vh - 190px)!important;min-height:620px!important;margin:0!important;background:#fff!important;border:0!important;border-top:1px solid var(--g-line)!important;border-radius:0!important;box-shadow:none!important;overflow:hidden!important}.messages-shell .msg-layout.no-thread{grid-template-columns:220px minmax(0,1fr)!important}
.messages-shell .msg-sidebar{min-width:0!important;padding:14px 8px!important;background:var(--g-sidebar)!important;border:0!important;border-inline-end:1px solid var(--g-line)!important}.messages-shell .msg-compose-btn{width:auto!important;min-width:145px!important;height:48px!important;margin:0 4px 14px!important;padding:0 18px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;border:0!important;border-radius:16px!important;background:#c2e7ff!important;color:#001d35!important;box-shadow:0 1px 3px rgba(60,64,67,.25)!important;font-size:.75rem!important;font-weight:700!important}.messages-shell .msg-compose-btn i{color:#001d35!important}.messages-shell .msg-compose-btn:hover{background:#b3dcf5!important}.messages-shell .msg-nav{display:flex!important;flex-direction:column!important;gap:1px!important}.messages-shell .msg-nav a,.messages-shell .msg-nav button{min-height:36px!important;width:100%!important;display:flex!important;align-items:center!important;gap:12px!important;padding:0 12px!important;border:0!important;border-radius:0 18px 18px 0!important;background:transparent!important;color:var(--g-text)!important;text-decoration:none!important;text-align:start!important;font-size:.72rem!important;font-weight:500!important}.messages-shell .msg-nav a i,.messages-shell .msg-nav button i{width:20px!important;text-align:center!important;color:#5f6368!important}.messages-shell .msg-nav a:hover,.messages-shell .msg-nav button:hover{background:#e8f0fe!important}.messages-shell .msg-nav a.active{background:var(--g-blue-soft)!important;color:#001d35!important;font-weight:700!important}.messages-shell .msg-nav a.active i{color:#001d35!important}.messages-shell .msg-count{min-width:22px!important;margin-inline-start:auto!important;padding:0!important;background:transparent!important;color:var(--g-text)!important;font-size:.62rem!important;font-weight:700!important}.messages-shell .msg-section-label{margin:22px 12px 7px!important;color:var(--g-muted)!important;font-size:.61rem!important;font-weight:700!important}
.messages-shell .msg-list-pane{min-width:0!important;display:flex!important;flex-direction:column!important;background:#fff!important;border:0!important;border-inline-end:1px solid var(--g-line)!important}.messages-shell .msg-list-head{min-height:64px!important;padding:10px 14px!important;display:grid!important;grid-template-columns:auto minmax(120px,1fr) auto!important;align-items:center!important;gap:10px!important;background:#fff!important;border:0!important;border-bottom:1px solid var(--g-line)!important}.messages-shell .msg-list-head h3{margin:0!important;color:var(--g-text)!important;font-size:.84rem!important;font-weight:500!important;white-space:nowrap!important}.messages-shell .msg-search-wrap{min-width:0!important;position:relative!important}.messages-shell .msg-search{width:100%!important;height:38px!important;min-width:0!important;max-width:none!important;padding:0 12px 0 34px!important;border:0!important;border-radius:20px!important;background:#eaf1fb!important;color:var(--g-text)!important;font-family:var(--g-font)!important;font-size:.66rem!important;outline:0!important}.messages-shell .msg-search:focus{background:#fff!important;box-shadow:0 1px 3px rgba(60,64,67,.28)!important;outline:1px solid #d3e3fd!important}.messages-shell .msg-search-wrap i{position:absolute!important;inset-inline-start:12px!important;color:var(--g-muted)!important;font-size:.68rem!important}.messages-shell .msg-filter{display:flex!important;align-items:center!important;gap:2px!important}.messages-shell .msg-filter a{padding:7px 6px!important;border-radius:0!important;color:var(--g-muted)!important;background:transparent!important;text-decoration:none!important;font-size:.61rem!important}.messages-shell .msg-filter a.active{color:var(--g-blue)!important;background:transparent!important;font-weight:700!important;border-bottom:2px solid var(--g-blue)!important}.messages-shell .msg-list{min-height:0!important;flex:1!important;overflow:auto!important;background:#fff!important}
.messages-shell .msg-row{position:relative!important;display:flex!important;align-items:flex-start!important;gap:12px!important;width:100%!important;min-height:70px!important;padding:10px 14px!important;border:0!important;border-bottom:1px solid var(--g-line-soft)!important;background:#fff!important;color:var(--g-text)!important;text-decoration:none!important;cursor:pointer!important;box-shadow:none!important;transition:background .12s ease!important}.messages-shell .msg-row:hover{background:#f2f6fc!important;box-shadow:none!important}.messages-shell .msg-row.unread{background:#f2f6fc!important}.messages-shell .msg-row.active{background:var(--g-blue-soft)!important;box-shadow:none!important}.messages-shell .msg-row.unread:before{content:''!important;position:absolute!important;inset-inline-start:0!important;top:0!important;bottom:0!important;width:3px!important;background:var(--g-blue)!important}.messages-shell .msg-avatar{width:36px!important;height:36px!important;flex:0 0 36px!important;display:grid!important;place-items:center!important;border:0!important;border-radius:50%!important;background:#e8eaed!important;color:#5f6368!important;font-size:.74rem!important;font-weight:600!important}.messages-shell .msg-row-main{min-width:0!important;flex:1!important}.messages-shell .msg-row-top,.messages-shell .msg-row-bottom{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important}.messages-shell .msg-name{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;color:var(--g-text)!important;font-size:.72rem!important;font-weight:500!important}.messages-shell .msg-row.unread .msg-name,.messages-shell .msg-row.unread .msg-subject{font-weight:700!important}.messages-shell .msg-time{color:var(--g-muted)!important;font-size:.58rem!important;white-space:nowrap!important}.messages-shell .msg-subject{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;color:var(--g-text)!important;font-size:.71rem!important;font-weight:500!important;white-space:nowrap!important;margin-top:2px!important}.messages-shell .msg-preview{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;color:var(--g-muted)!important;font-size:.61rem!important;white-space:nowrap!important;margin-top:3px!important}.messages-shell .msg-new{padding:0!important;background:transparent!important;color:var(--g-blue)!important;font-size:.52rem!important;font-weight:700!important}.messages-shell .msg-urgent{color:#d93025!important;font-size:.62rem!important}.messages-shell .msg-empty{height:100%!important;display:grid!important;place-items:center!important;color:var(--g-muted)!important;background:#fff!important}.messages-shell .msg-empty-icon{width:56px!important;height:56px!important;border-radius:50%!important;background:#f1f3f4!important;color:#9aa0a6!important}
.messages-shell .msg-reading-pane,.messages-shell .msg-reading-pane.empty{min-width:0!important;display:flex!important;flex-direction:column!important;background:#fff!important;position:relative!important}.messages-shell .msg-reading-pane.empty{align-items:center!important;justify-content:center!important}.messages-shell .msg-reading-head{min-height:64px!important;padding:10px 18px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;background:#fff!important;border:0!important;border-bottom:1px solid var(--g-line)!important}.messages-shell .msg-conv-person{display:flex!important;align-items:center!important;gap:10px!important;min-width:0!important}.messages-shell .msg-conv-person .msg-avatar{width:36px!important;height:36px!important;flex-basis:36px!important}.messages-shell .msg-conv-name{color:var(--g-text)!important;font-size:.78rem!important;font-weight:600!important}.messages-shell .msg-conv-meta{color:var(--g-muted)!important;font-size:.58rem!important;margin-top:2px!important}.messages-shell .msg-conv-actions{display:flex!important;gap:2px!important}.messages-shell .msg-icon-btn{width:34px!important;height:34px!important;display:grid!important;place-items:center!important;border:0!important;border-radius:50%!important;background:transparent!important;color:var(--g-muted)!important;text-decoration:none!important}.messages-shell .msg-icon-btn:hover{background:#f1f3f4!important;color:var(--g-text)!important}
.messages-shell .msg-thread{min-height:0!important;flex:1!important;overflow:auto!important;padding:22px 34px 28px!important;background:#fff!important}.messages-shell .msg-root{max-width:none!important;margin:0 0 18px!important;padding:0 0 18px!important;background:#fff!important;border:0!important;border-radius:0!important;box-shadow:none!important;border-bottom:1px solid var(--g-line-soft)!important}.messages-shell .msg-subject-large{margin:0 0 8px!important;color:var(--g-text)!important;font-size:1.05rem!important;font-weight:400!important}.messages-shell .msg-root-meta{color:var(--g-muted)!important;font-size:.59rem!important}.messages-shell .msg-root hr{margin:14px 0!important;border:0!important;border-top:1px solid var(--g-line-soft)!important}.messages-shell .msg-body{color:var(--g-text)!important;font-size:.78rem!important;line-height:1.95!important;white-space:pre-wrap!important}.messages-shell .msg-bubble{max-width:90%!important;margin:0 auto 0 0!important;padding:13px 0!important;background:#fff!important;border:0!important;border-top:1px solid var(--g-line-soft)!important;border-radius:0!important;box-shadow:none!important}.messages-shell .msg-bubble.mine{margin-left:auto!important;margin-right:0!important;background:#fff!important;border-color:var(--g-line-soft)!important}.messages-shell .msg-bubble-head{margin-bottom:5px!important}.messages-shell .msg-bubble-name{color:var(--g-text)!important;font-size:.68rem!important;font-weight:600!important}.messages-shell .msg-bubble-time{color:var(--g-muted)!important;font-size:.56rem!important}
.messages-shell .msg-attachment-item{background:#f8fafd!important;border:1px solid var(--g-line)!important;border-radius:6px!important;padding:7px 9px!important}.messages-shell .msg-attachment-item a{color:var(--g-blue)!important}.messages-shell .msg-attachment-size{color:var(--g-muted)!important}
.messages-shell .msg-reply{padding:10px 24px 14px!important;background:#fff!important;border:0!important;border-top:1px solid var(--g-line)!important}.messages-shell .msg-reply-inner{max-width:none!important}.messages-shell .msg-reply-box{overflow:hidden!important;background:#fff!important;border:1px solid #dadce0!important;border-radius:8px!important;box-shadow:0 1px 2px rgba(60,64,67,.12)!important}.messages-shell .msg-reply textarea{min-height:70px!important;max-height:180px!important;padding:11px 13px!important;background:#fff!important;color:var(--g-text)!important;font-size:.75rem!important}.messages-shell .msg-reply-tools{padding:5px 7px!important;background:#fff!important;border-top:1px solid var(--g-line-soft)!important}.messages-shell .msg-tool{width:30px!important;height:30px!important;border:0!important;border-radius:50%!important;background:transparent!important;color:var(--g-muted)!important}.messages-shell .msg-tool:hover{background:#f1f3f4!important;color:var(--g-text)!important}.messages-shell .msg-send{padding:7px 15px!important;border:0!important;border-radius:16px!important;background:var(--g-blue)!important;color:#fff!important;font-size:.67rem!important;font-weight:600!important}.messages-shell .msg-send:hover{background:#0842a0!important}.messages-shell .msg-no-reply{padding:12px!important;background:#fff!important;color:var(--g-muted)!important;border-top:1px solid var(--g-line)!important;font-size:.62rem!important}
.compose-modal .modal-dialog{max-width:600px!important;margin:1.75rem 1.75rem 1.75rem auto!important}.compose-modal .modal-content{border:0!important;border-radius:8px!important;overflow:hidden!important;box-shadow:0 8px 35px rgba(60,64,67,.35)!important}.compose-modal .modal-header{min-height:46px!important;padding:9px 12px!important;background:#404040!important;color:#fff!important;border:0!important}.compose-modal .modal-header h5{color:#fff!important;font-size:.78rem!important;font-weight:600!important}.compose-modal .modal-header small{color:#d6d6d6!important}.compose-head-icon{display:none!important}.compose-modal .modal-body{padding:0!important;background:#fff!important}.compose-modal .form-label{font-size:.64rem!important;color:var(--g-muted)!important}.compose-recipient{padding:12px 14px!important;background:#fff!important;border:0!important;border-radius:0!important}.compose-modal .form-control,.compose-modal .form-select{border:0!important;border-bottom:1px solid #e5e7e9!important;border-radius:0!important;background:#fff!important;font-family:var(--g-font)!important;font-size:.72rem!important}.compose-modal .form-control:focus,.compose-modal .form-select:focus{box-shadow:none!important;border-bottom-color:var(--g-blue)!important}.compose-modal textarea{min-height:190px!important;padding:12px 14px!important}.compose-modal .modal-body > .mb-3,.compose-modal .modal-body > .mb-2{margin-left:14px!important;margin-right:14px!important}.compose-modal .modal-footer{padding:8px 12px!important;background:#fff!important;border-top:1px solid var(--g-line-soft)!important}.compose-send{padding:7px 16px!important;border:0!important;border-radius:16px!important;background:var(--g-blue)!important;color:#fff!important;font-size:.68rem!important}.compose-cancel{border:0!important;background:transparent!important;color:var(--g-muted)!important;font-size:.68rem!important}.messages-shell .msg-read-empty-icon{width:64px!important;height:64px!important;border-radius:50%!important;background:#f1f3f4!important;color:#9aa0a6!important}.messages-shell .msg-read-empty strong{color:var(--g-muted)!important;font-size:.78rem!important}.messages-shell .msg-read-empty span{color:#9aa0a6!important;font-size:.64rem!important}.messages-shell .msg-attach-btn{background:#fff!important;border:1px solid var(--g-line)!important;color:var(--g-muted)!important;border-radius:6px!important}
@media(max-width:1200px){.messages-shell .msg-layout,.messages-shell .msg-layout.has-thread{grid-template-columns:200px 340px minmax(0,1fr)!important}}
@media(max-width:950px){.messages-shell .msg-layout.has-thread{grid-template-columns:190px minmax(300px,1fr)!important;position:relative!important}.messages-shell .msg-reading-pane{position:absolute!important;inset:0!important;z-index:50!important}}
@media(max-width:767px){.messages-shell{margin:0!important}.messages-shell .msg-toolbar{height:auto!important;min-height:58px!important;padding:8px 12px!important}.messages-shell .msg-settings{display:none!important}.messages-shell .msg-layout,.messages-shell .msg-layout.no-thread{display:block!important;height:auto!important;min-height:0!important}.messages-shell .msg-sidebar{border-bottom:1px solid var(--g-line)!important;border-inline-end:0!important;padding:8px!important}.messages-shell .msg-compose-btn{margin:0 0 8px!important}.messages-shell .msg-nav{display:grid!important;grid-template-columns:repeat(3,1fr)!important}.messages-shell .msg-nav a,.messages-shell .msg-nav button{justify-content:center!important;flex-direction:column!important;gap:2px!important;border-radius:8px!important;padding:7px 3px!important;font-size:.58rem!important}.messages-shell .msg-section-label,.messages-shell .msg-sidebar > .small{display:none!important}.messages-shell .msg-list-pane{border-inline-end:0!important}.messages-shell .msg-list-head{grid-template-columns:auto 1fr!important;grid-template-areas:"title filter" "search search"!important}.messages-shell .msg-list-head h3{grid-area:title!important}.messages-shell .msg-search-wrap{grid-area:search!important}.messages-shell .msg-filter{grid-area:filter!important;justify-content:flex-end!important}.messages-shell .msg-reading-pane{position:fixed!important;inset:0!important;z-index:3000!important}.messages-shell .msg-thread{padding:18px 14px 22px!important}.messages-shell .msg-reply{padding:8px!important}.compose-modal .modal-dialog{max-width:none!important;margin:0!important}.compose-modal .modal-content{min-height:100vh!important;border-radius:0!important}}
</style>
<div class="messages-shell">
 <div class="msg-toolbar">
  <div class="msg-title"><div class="msg-title-icon"><i class="fas fa-envelope-open-text"></i></div><div><h2>الرسائل الداخلية</h2><p>مراسلات آمنة بين مستخدمي النظام والأدوار الإدارية</p></div></div>
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
   <div class="msg-list-head"><h3><?php echo $view==='sent'?'الرسائل المرسلة':'صندوق الوارد';?></h3><div class="msg-search-wrap"><i class="fas fa-magnifying-glass"></i><input type="search" id="msgSearch" class="msg-search" placeholder="بحث في المرسل والموضوع ونص الرسالة" autocomplete="off"></div><div class="msg-filter"><a class="<?php echo $filter==='all'?'active':'';?>" href="?view=<?php echo e($view);?>&filter=all">الكل</a><a class="<?php echo $filter==='unread'?'active':'';?>" href="?view=<?php echo e($view);?>&filter=unread">غير مقروء</a></div></div>
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