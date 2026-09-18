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
:root{
 --msg-primary:#1f5fae;--msg-primary-dark:#174a8b;--msg-primary-soft:#eaf3ff;
 --msg-surface:#fff;--msg-page:#f3f6fa;--msg-border:#dfe5ed;--msg-border-strong:#cfd8e4;
 --msg-text:#172338;--msg-muted:#718096;--msg-font:'Cairo',sans-serif;--msg-size:14px;
}
.messages-shell{font-family:var(--msg-font);font-size:var(--msg-size);color:var(--msg-text)}
.msg-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px}
.msg-title{display:flex;align-items:center;gap:12px}.msg-title-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:var(--msg-primary);color:#fff;font-size:18px;box-shadow:0 5px 16px rgba(31,95,174,.2)}
.msg-title h2{font-size:1.18rem;font-weight:800;margin:0}.msg-title p{font-size:.72rem;color:var(--msg-muted);margin:3px 0 0}
.msg-settings{display:flex;align-items:center;gap:7px;color:#687589;font-size:.68rem}.msg-settings select{width:96px;font-size:.68rem;border-radius:8px;border-color:var(--msg-border)}
.msg-layout{display:grid;grid-template-columns:210px minmax(360px,1fr);height:calc(100vh - 225px);min-height:620px;background:#fff;border:1px solid var(--msg-border);border-radius:14px;box-shadow:0 7px 28px rgba(24,45,72,.08);overflow:hidden}
.msg-sidebar{background:#f8fafc;border-inline-end:1px solid var(--msg-border);padding:15px 12px}
.msg-compose-btn{width:100%;border:0;border-radius:10px;padding:10px 12px;font-weight:800;background:var(--msg-primary);color:#fff;box-shadow:0 5px 13px rgba(31,95,174,.18);margin-bottom:14px}.msg-compose-btn:hover{background:var(--msg-primary-dark)}
.msg-nav{display:flex;flex-direction:column;gap:3px}.msg-nav a,.msg-nav button{display:flex;align-items:center;gap:10px;width:100%;border:0;background:transparent;color:#526176;text-decoration:none;border-radius:9px;padding:9px 10px;font-size:.76rem;text-align:start}.msg-nav a:hover,.msg-nav button:hover{background:#edf3fb;color:var(--msg-primary)}.msg-nav a.active{background:#e4effd;color:var(--msg-primary);font-weight:800}.msg-nav i{width:20px;text-align:center}.msg-count{margin-inline-start:auto;min-width:22px;font-size:.6rem}.msg-section-label{font-size:.62rem;font-weight:800;color:#98a4b5;margin:22px 9px 7px}
.msg-list-pane{min-width:0;display:flex;flex-direction:column;background:#fff}
.msg-list-head{padding:10px 12px;border-bottom:1px solid var(--msg-border);display:flex;align-items:center;justify-content:space-between;gap:9px;background:#fff}
.msg-list-head h3{font-size:.84rem;font-weight:800;margin:0;white-space:nowrap}.msg-filter{display:flex;gap:3px}.msg-filter a{font-size:.61rem;padding:5px 8px;border-radius:7px;text-decoration:none;color:var(--msg-muted)}.msg-filter a.active{background:var(--msg-primary-soft);color:var(--msg-primary);font-weight:800}
.msg-search{height:32px;min-width:170px;max-width:300px;flex:1;border:1px solid var(--msg-border);border-radius:9px;background:#f8fafc;padding:0 10px;font-family:var(--msg-font);font-size:.67rem;outline:none}.msg-search:focus{background:#fff;border-color:#a9c5e8;box-shadow:0 0 0 3px rgba(31,95,174,.08)}.msg-search-wrap{position:relative;flex:1;display:flex;align-items:center}.msg-search-wrap i{position:absolute;inset-inline-start:10px;color:#9aa5b5;font-size:.68rem}.msg-search{padding-inline-start:28px}
.msg-list{overflow:auto;flex:1;background:#fff}.msg-row{position:relative;display:flex;gap:11px;padding:13px 15px;border:0;border-bottom:1px solid #edf1f5;text-decoration:none;color:inherit;transition:background .14s,box-shadow .14s;cursor:pointer}.msg-row:hover{background:#f7faff}.msg-row.active{background:#edf5ff;box-shadow:inset -3px 0 0 var(--msg-primary)}.msg-row.unread{background:#f4f8fe}.msg-row.unread .msg-name,.msg-row.unread .msg-subject{font-weight:900}.msg-row.unread:before{content:'';position:absolute;inset-inline-end:auto;inset-inline-start:0;top:0;bottom:0;width:3px;background:var(--msg-primary)}
.msg-avatar{width:40px;height:40px;flex:0 0 40px;border-radius:50%;display:grid;place-items:center;background:#e6eef8;color:var(--msg-primary);font-weight:800;border:1px solid #d5e1ef}.msg-row-main{min-width:0;flex:1}.msg-row-top,.msg-row-bottom{display:flex;align-items:center;justify-content:space-between;gap:7px}.msg-name{font-size:.75rem;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-time{font-size:.59rem;color:#8d98a8;white-space:nowrap}.msg-subject{font-size:.76rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px}.msg-preview{font-size:.66rem;color:#7b8798;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px}.msg-new{font-size:.53rem;padding:3px 5px;border-radius:5px;background:#dceaff;color:var(--msg-primary);font-weight:800}.msg-urgent{color:#c0392b;font-size:.64rem}.msg-attachment-mark{color:#8a97a8;font-size:.63rem;margin-inline-start:5px}
.msg-empty{height:100%;display:grid;place-items:center;text-align:center;color:var(--msg-muted);padding:50px 20px}.msg-empty-icon{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;background:#edf3fa;color:#8ca0b8;font-size:22px;margin:0 auto 12px}.msg-empty strong{display:block;color:#536176;font-size:.8rem;margin-bottom:3px}
.msg-overlay{position:fixed;inset:0;z-index:1080;display:none;align-items:stretch;justify-content:flex-end;padding:0;background:rgba(18,35,57,.12)}
.msg-overlay.open{display:flex}
.msg-dialog{width:min(920px,62vw);height:100vh;min-height:100%;background:#fff;border:0;border-inline-start:1px solid var(--msg-border);border-radius:0;box-shadow:-18px 0 50px rgba(12,35,62,.18);display:flex;flex-direction:column;overflow:hidden;animation:msgPaneIn .18s ease-out}
.msg-conv-head{min-height:76px;padding:13px 18px;border-bottom:1px solid var(--msg-border);display:flex;align-items:center;justify-content:space-between;gap:14px;background:#fff}
.msg-conv-person{display:flex;align-items:center;gap:11px;min-width:0}.msg-conv-person .msg-avatar{width:44px;height:44px;flex-basis:44px}
.msg-conv-name{font-weight:850;font-size:.86rem}.msg-conv-meta{font-size:.61rem;color:var(--msg-muted);margin-top:3px}
.msg-conv-actions{display:flex;gap:6px}.msg-icon-btn{width:34px;height:34px;border:1px solid var(--msg-border);background:#fff;color:#657286;border-radius:8px;display:grid;place-items:center;text-decoration:none}.msg-icon-btn:hover{background:#f3f7fc;color:var(--msg-primary);border-color:#c9d8ea}.msg-close-btn{background:#f7f8fa}
.msg-thread{padding:22px 30px 28px;overflow:auto;flex:1;background:#f5f7fa;scroll-behavior:smooth}
.msg-root{max-width:820px;margin:0 auto 13px;border:1px solid var(--msg-border-strong);border-radius:11px;padding:18px 20px;background:#fff;box-shadow:0 2px 8px rgba(20,43,70,.06)}
.msg-subject-large{font-size:1rem;font-weight:850;margin-bottom:5px;color:#152238}.msg-root-meta{font-size:.62rem;color:#778398}.msg-root hr{border-color:#e8edf3}.msg-body{white-space:pre-wrap;line-height:1.9;font-size:.81rem;word-break:break-word;color:#25344a}
.msg-bubble{max-width:min(78%,800px);border-radius:11px;padding:12px 15px;margin:9px auto 9px 0;border:1px solid #dce3eb;background:#fff;box-shadow:0 2px 7px rgba(25,47,73,.045)}.msg-bubble.mine{margin-left:auto;margin-right:0;background:#e8f2ff;border-color:#c9dcf2}.msg-bubble-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:5px}.msg-bubble-name{font-weight:850;font-size:.68rem;color:#2b4665}.msg-bubble.mine .msg-bubble-name{color:#1f5fae}.msg-bubble-time{font-size:.57rem;color:#8995a5}
.msg-reply{padding:12px 18px 15px;border-top:1px solid var(--msg-border);background:#fff}.msg-reply-inner{max-width:820px;margin:0 auto}.msg-reply-box{border:1px solid #cbd6e2;border-radius:11px;background:#fff;overflow:hidden;box-shadow:0 2px 10px rgba(20,50,90,.05)}.msg-reply textarea{display:block;width:100%;border:0!important;background:#fff;resize:none;min-height:76px;max-height:190px;padding:12px 14px;font-family:var(--msg-font);font-size:.79rem;line-height:1.8;box-shadow:none!important;outline:none}.msg-reply textarea:focus{background:#fff}.msg-reply-tools{display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-top:1px solid #e8edf2;background:#fafbfd}.msg-tools{display:flex;align-items:center;gap:2px}.msg-tool{border:0;background:transparent;color:#7c8796;width:31px;height:31px;border-radius:7px}.msg-tool:hover{background:#e9f1fa;color:var(--msg-primary)}.msg-send{border:0;border-radius:8px;background:var(--msg-primary);color:#fff;font-size:.68rem;font-weight:850;padding:8px 14px}.msg-send:hover{background:var(--msg-primary-dark)}.msg-status{display:none;font-size:.61rem;color:#7c8796}.msg-status.show{display:inline-flex;align-items:center;gap:5px}.msg-reply-error{display:none;font-size:.67rem;margin-bottom:7px;padding:7px 10px;border-radius:8px}.msg-reply-error.show{display:block}.msg-no-reply{padding:13px 20px;text-align:center;color:var(--msg-muted);font-size:.66rem;border-top:1px solid var(--msg-border)}
.compose-modal .modal-dialog{max-width:710px}.compose-modal .modal-content{border:0;border-radius:16px;overflow:hidden;box-shadow:0 18px 55px rgba(10,31,68,.25)}.compose-modal .modal-header{padding:14px 18px;background:#fff;color:var(--msg-text);border-bottom:1px solid var(--msg-border)}.compose-head-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:var(--msg-primary-soft);color:var(--msg-primary)}.compose-modal .modal-body{padding:17px 19px}.compose-modal .modal-footer{padding:10px 19px;border-top:1px solid var(--msg-border);background:#fbfcfe}.compose-modal .form-label{font-size:.68rem;font-weight:850;color:#59677a;margin-bottom:5px}.compose-modal .form-control,.compose-modal .form-select{border-radius:8px;border-color:#dfe5ed;font-family:var(--msg-font);font-size:.75rem;padding:.52rem .68rem}.compose-modal textarea{min-height:145px;resize:vertical}.compose-recipient{padding:11px;background:#f7f9fc;border:1px solid #e8edf3;border-radius:11px}.compose-error{display:none;border-radius:8px;font-size:.7rem}.compose-error.show{display:block}.compose-urgent{font-size:.68rem;color:#667386}.compose-urgent input{accent-color:#c0392b}.compose-footer-actions{display:flex;align-items:center;justify-content:space-between;width:100%}.compose-send{border:0;background:var(--msg-primary);color:#fff;border-radius:8px;padding:8px 15px;font-size:.71rem;font-weight:850}.compose-send:hover{background:var(--msg-primary-dark)}.compose-cancel{border:1px solid #dfe5ed;background:#fff;color:#687589;border-radius:8px;padding:8px 13px;font-size:.7rem}
.msg-toast{position:fixed;bottom:22px;inset-inline-end:22px;z-index:1090;display:none;padding:9px 13px;border-radius:9px;background:#202938;color:#fff;font-size:.7rem;box-shadow:0 8px 25px rgba(0,0,0,.18)}.msg-toast.show{display:block}
.msg-attach-btn{display:inline-flex;align-items:center;gap:6px;font-size:.67rem;color:#59677a;background:#f4f7fb;border:1px solid #e3e9f1;border-radius:8px;padding:7px 11px;cursor:pointer}.msg-attach-btn:hover{background:#eaf2ff;color:var(--msg-primary)}
.msg-attachment-chip{font-size:.67rem;color:var(--msg-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.msg-attachments{margin-top:.65rem;display:flex;flex-direction:column;gap:.4rem}.msg-attachment-item{display:flex;align-items:center;gap:.45rem;font-size:.7rem;background:#f7f9fc;border:1px solid #dce4ed;border-radius:8px;padding:.45rem .6rem}.msg-attachment-item a{color:var(--msg-primary);text-decoration:none;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700}.msg-attachment-item a:hover{text-decoration:underline}.msg-attachment-size{color:var(--msg-muted);font-size:.61rem;white-space:nowrap}.msg-attachment-del{border:0;background:none;color:#c0392b;cursor:pointer;padding:0 .2rem;font-size:.7rem}
@keyframes msgPaneIn{from{opacity:0;transform:translateX(18px)}to{opacity:1;transform:none}}
@media(max-width:1100px){.msg-dialog{width:68vw}.msg-layout{grid-template-columns:195px minmax(320px,1fr)}}
@media(max-width:767px){.msg-toolbar{align-items:flex-start}.msg-settings{display:none}.msg-layout{display:block;height:auto;min-height:0}.msg-sidebar{border:0;border-bottom:1px solid var(--msg-border)}.msg-nav{display:grid;grid-template-columns:repeat(3,1fr)}.msg-nav a,.msg-nav button{justify-content:center;flex-direction:column;gap:3px;padding:8px 4px;text-align:center;font-size:.59rem}.msg-section-label{display:none}.msg-compose-btn{margin-bottom:10px}.msg-list{min-height:520px}.msg-list-head{flex-wrap:wrap}.msg-search-wrap{order:3;flex-basis:100%;max-width:none}.msg-overlay{padding:0;background:rgba(18,35,57,.22)}.msg-dialog{width:100%;height:100%;min-height:100%;border-radius:0;border:0}.msg-conv-head{padding:11px 13px}.msg-thread{padding:15px 11px 20px}.msg-root{padding:15px}.msg-bubble{max-width:94%}.msg-reply{padding:10px}.msg-reply textarea{min-height:74px}.msg-conv-meta{font-size:.57rem}}
</style>
<style>
/* Professional mail workspace override */
.messages-shell{font-family:'Cairo',sans-serif;color:#172033}
.msg-layout{grid-template-columns:190px 340px minmax(430px,1fr)!important;height:calc(100vh - 190px);min-height:600px;background:#fff;border:1px solid #dfe4ea;border-radius:12px;box-shadow:0 4px 20px rgba(15,23,42,.07);overflow:hidden}
.msg-layout.no-thread{grid-template-columns:190px minmax(0,1fr)!important}
.msg-sidebar{background:#fbfcfe!important;border-inline-end:1px solid #dfe4ea!important;padding:14px 10px!important}
.msg-compose-btn{background:#2563eb!important;border-radius:9px!important;box-shadow:0 3px 10px rgba(37,99,235,.18)!important}
.msg-nav a,.msg-nav button{color:#475467!important;border-radius:8px!important}.msg-nav a.active{background:#e8f1ff!important;color:#2563eb!important;font-weight:850}
.msg-list-pane{background:#fff!important;border-inline-end:1px solid #dfe4ea!important}
.msg-list-head{background:#fff!important;border-bottom:1px solid #dfe4ea!important}
.msg-row{padding:12px 11px!important;border-bottom:1px solid #edf0f4!important}.msg-row:hover{background:#f7f9fc!important}.msg-row.unread{background:#f8fbff!important}
.msg-reading-pane{min-width:0;display:flex;flex-direction:column;background:#f4f6f9;position:relative}
.msg-reading-pane.empty{grid-column:1/-1;align-items:center;justify-content:center}
.msg-reading-head{min-height:70px;padding:11px 16px;background:#fff;border-bottom:1px solid #dfe4ea;display:flex;align-items:center;justify-content:space-between}
.msg-thread{padding:18px 22px 24px;background:#f4f6f9!important;overflow:auto;flex:1}
.msg-root{border:1px solid #d7dee7!important;background:#fff!important;border-radius:10px!important;box-shadow:0 1px 3px rgba(16,24,40,.05)!important}
.msg-bubble{border:1px solid #dce2e9!important;background:#fff!important;border-radius:10px!important;box-shadow:0 1px 3px rgba(16,24,40,.045)!important}
.msg-bubble.mine{background:#eaf3ff!important;border-color:#c8dcf6!important}
.msg-reply{background:#fff!important;border-top:1px solid #dfe4ea!important;padding:10px 15px 13px!important}
.msg-reply-box{border:1px solid #cbd5e1!important;border-radius:9px!important}
.msg-send{background:#2563eb!important}.msg-send:hover{background:#1d4ed8!important}
.msg-icon-btn{border-color:#dfe4ea!important}.msg-icon-btn:hover{background:#eff6ff!important;color:#2563eb!important}
.msg-read-empty-icon{background:#e9eef5!important;color:#64748b!important}
.msg-attachment-item{background:#f8fafc!important;border:1px solid #d8e0e9!important}
@media(max-width:1100px){.msg-layout.has-thread{grid-template-columns:175px 320px minmax(360px,1fr)!important}}
@media(max-width:900px){.msg-layout.has-thread{grid-template-columns:170px 1fr!important;position:relative}.msg-reading-pane{position:absolute;inset:0;z-index:50}}
@media(max-width:767px){.msg-layout,.msg-layout.no-thread{display:block;height:auto;min-height:0}.msg-reading-pane{position:fixed;inset:0;z-index:3000}.msg-thread{padding:12px 10px 18px}.msg-bubble{max-width:94%}}
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
<style id="msg-redesign-v3">
/* ============================================================
   Messaging V3 — clean Outlook/Gmail-inspired workspace
   Visual-only redesign; existing PHP/JS message functionality stays intact.
   ============================================================ */
.messages-shell{
  --mail-ink:#17202a;
  --mail-muted:#66717f;
  --mail-line:#dfe3e8;
  --mail-line-soft:#eceff2;
  --mail-bg:#f5f6f8;
  --mail-surface:#fff;
  --mail-accent:#2b4c73;
  --mail-accent-soft:#edf2f7;
  --mail-accent-dark:#1f3856;
  font-family:'Cairo',sans-serif!important;
  color:var(--mail-ink)!important;
}
.messages-shell .msg-toolbar{
  margin-bottom:10px!important;
  padding:0 2px!important;
}
.messages-shell .msg-title{gap:10px!important}
.messages-shell .msg-title-icon{
  width:38px!important;height:38px!important;border-radius:9px!important;
  background:var(--mail-accent)!important;box-shadow:none!important;font-size:16px!important;
}
.messages-shell .msg-title h2{font-size:1.05rem!important;letter-spacing:-.15px}
.messages-shell .msg-title p{font-size:.67rem!important;color:#7b8591!important}
.messages-shell .msg-settings{gap:6px!important}
.messages-shell .msg-settings select{
  width:88px!important;height:29px!important;background:#fff!important;
  border:1px solid var(--mail-line)!important;border-radius:7px!important;
}

/* Main workspace */
.messages-shell .msg-layout{
  grid-template-columns:178px 365px minmax(430px,1fr)!important;
  height:calc(100vh - 188px)!important;
  min-height:570px!important;
  background:var(--mail-surface)!important;
  border:1px solid #d8dde3!important;
  border-radius:7px!important;
  box-shadow:0 2px 10px rgba(16,24,40,.055)!important;
  overflow:hidden!important;
}
.messages-shell .msg-layout.no-thread{
  grid-template-columns:178px minmax(0,1fr)!important;
}

/* Folder rail */
.messages-shell .msg-sidebar{
  background:#f8f9fa!important;
  border-inline-end:1px solid var(--mail-line)!important;
  padding:13px 9px!important;
}
.messages-shell .msg-compose-btn{
  background:var(--mail-accent)!important;
  border-radius:7px!important;
  box-shadow:none!important;
  padding:9px 10px!important;
  margin-bottom:12px!important;
  font-size:.72rem!important;
}
.messages-shell .msg-compose-btn:hover{background:var(--mail-accent-dark)!important}
.messages-shell .msg-nav{gap:1px!important}
.messages-shell .msg-nav a,
.messages-shell .msg-nav button{
  min-height:36px!important;
  padding:7px 9px!important;
  border-radius:6px!important;
  color:#4e5966!important;
  font-size:.69rem!important;
}
.messages-shell .msg-nav a:hover,
.messages-shell .msg-nav button:hover{
  background:#eef1f4!important;color:var(--mail-accent)!important;
}
.messages-shell .msg-nav a.active{
  background:#e7edf3!important;color:var(--mail-accent)!important;font-weight:850!important;
}
.messages-shell .msg-nav i{font-size:.72rem!important;width:18px!important}
.messages-shell .msg-count{
  background:#64748b!important;color:#fff!important;
  min-width:19px!important;height:19px!important;line-height:19px!important;
  padding:0!important;border-radius:10px!important;font-size:.54rem!important;
}
.messages-shell .msg-section-label{
  margin:19px 8px 6px!important;
  color:#9aa3ad!important;font-size:.58rem!important;text-transform:uppercase;
  letter-spacing:.25px;
}

/* Message list */
.messages-shell .msg-list-pane{
  background:#fff!important;
  border-inline-end:1px solid var(--mail-line)!important;
}
.messages-shell .msg-list-head{
  min-height:68px!important;
  padding:10px 12px!important;
  background:#fff!important;
  border-bottom:1px solid var(--mail-line)!important;
  flex-wrap:wrap!important;
}
.messages-shell .msg-list-head h3{
  font-size:.78rem!important;color:#202a35!important;letter-spacing:.1px;
}
.messages-shell .msg-search-wrap{
  order:3!important;flex-basis:100%!important;max-width:none!important;
}
.messages-shell .msg-search{
  height:30px!important;
  background:#f5f6f8!important;
  border:1px solid #e2e5e9!important;
  border-radius:6px!important;
  font-size:.63rem!important;
}
.messages-shell .msg-search:focus{
  background:#fff!important;border-color:#aab6c3!important;
  box-shadow:0 0 0 2px rgba(43,76,115,.07)!important;
}
.messages-shell .msg-filter{
  margin-inline-start:auto!important;
}
.messages-shell .msg-filter a{
  font-size:.57rem!important;padding:4px 7px!important;border-radius:5px!important;
}
.messages-shell .msg-filter a.active{
  background:#edf1f5!important;color:var(--mail-accent)!important;
}

/* Rows: compact, dense, email-client feel */
.messages-shell .msg-list{background:#fff!important}
.messages-shell .msg-row{
  min-height:72px!important;
  padding:10px 12px!important;
  gap:9px!important;
  border-bottom:1px solid var(--mail-line-soft)!important;
  background:#fff!important;
}
.messages-shell .msg-row:hover{background:#f6f8fa!important}
.messages-shell .msg-row.active{
  background:#eef3f7!important;
  box-shadow:inset -3px 0 0 var(--mail-accent)!important;
}
.messages-shell .msg-row.unread{
  background:#fafcff!important;
}
.messages-shell .msg-row.unread:before{
  width:3px!important;background:#2b4c73!important;
}
.messages-shell .msg-avatar{
  width:34px!important;height:34px!important;flex-basis:34px!important;
  border:0!important;background:#e8edf2!important;color:#344d68!important;
  font-size:.68rem!important;
}
.messages-shell .msg-name{font-size:.68rem!important;font-weight:750!important}
.messages-shell .msg-row.unread .msg-name,
.messages-shell .msg-row.unread .msg-subject{font-weight:900!important;color:#182432!important}
.messages-shell .msg-time{font-size:.54rem!important;color:#8b949e!important}
.messages-shell .msg-subject{font-size:.69rem!important;margin-top:2px!important}
.messages-shell .msg-preview{
  font-size:.58rem!important;color:#7c8792!important;margin-top:2px!important;
}
.messages-shell .msg-new{
  background:#e5ebf1!important;color:#344d68!important;
  font-size:.48rem!important;padding:2px 4px!important;border-radius:4px!important;
}

/* Reading pane — clean document/email view */
.messages-shell .msg-reading-pane{
  background:#f6f7f9!important;
  position:relative!important;
}
.messages-shell .msg-reading-head{
  min-height:66px!important;
  padding:10px 18px!important;
  background:#fff!important;
  border-bottom:1px solid var(--mail-line)!important;
}
.messages-shell .msg-conv-person .msg-avatar{
  width:38px!important;height:38px!important;flex-basis:38px!important;
}
.messages-shell .msg-conv-name{font-size:.75rem!important;color:#202a35!important}
.messages-shell .msg-conv-meta{font-size:.55rem!important;color:#87919c!important}
.messages-shell .msg-icon-btn{
  width:31px!important;height:31px!important;
  border:0!important;background:#f2f4f6!important;color:#596674!important;
  border-radius:6px!important;
}
.messages-shell .msg-icon-btn:hover{
  background:#e8edf2!important;color:var(--mail-accent)!important;
}

/* Message body */
.messages-shell .msg-thread{
  padding:25px 32px 30px!important;
  background:#f6f7f9!important;
}
.messages-shell .msg-root{
  max-width:850px!important;
  margin:0 auto 14px!important;
  padding:21px 24px!important;
  border:0!important;
  border-radius:5px!important;
  background:#fff!important;
  box-shadow:0 1px 3px rgba(16,24,40,.07)!important;
}
.messages-shell .msg-subject-large{
  font-size:1.03rem!important;
  font-weight:850!important;
  color:#17202a!important;
  margin-bottom:9px!important;
}
.messages-shell .msg-root-meta{
  font-size:.57rem!important;color:#78838e!important;
}
.messages-shell .msg-root hr{
  border-color:#e8ebee!important;margin:15px 0!important;
}
.messages-shell .msg-body{
  font-size:.76rem!important;
  line-height:2!important;
  color:#2f3945!important;
}
.messages-shell .msg-bubble{
  max-width:850px!important;
  margin:8px auto!important;
  padding:12px 15px!important;
  border:1px solid #e0e4e8!important;
  border-radius:5px!important;
  background:#fff!important;
  box-shadow:0 1px 2px rgba(16,24,40,.035)!important;
}
.messages-shell .msg-bubble.mine{
  background:#edf3f8!important;border-color:#d5e0ea!important;
}
.messages-shell .msg-bubble-name{font-size:.61rem!important;color:#34485d!important}
.messages-shell .msg-bubble-time{font-size:.52rem!important;color:#8b949e!important}

/* Reply composer */
.messages-shell .msg-reply{
  padding:10px 15px 12px!important;
  background:#fff!important;
  border-top:1px solid var(--mail-line)!important;
}
.messages-shell .msg-reply-inner{max-width:850px!important}
.messages-shell .msg-reply-box{
  border:1px solid #ccd3da!important;
  border-radius:6px!important;
  box-shadow:none!important;
}
.messages-shell .msg-reply textarea{
  min-height:66px!important;padding:10px 12px!important;
  font-size:.72rem!important;
}
.messages-shell .msg-reply-tools{
  padding:5px 7px!important;background:#fafbfc!important;
  border-top:1px solid #edf0f2!important;
}
.messages-shell .msg-tool{
  width:29px!important;height:29px!important;border-radius:5px!important;
}
.messages-shell .msg-send{
  background:var(--mail-accent)!important;
  border-radius:5px!important;
  font-size:.64rem!important;padding:7px 12px!important;
}
.messages-shell .msg-send:hover{background:var(--mail-accent-dark)!important}

/* Empty reading state */
.messages-shell .msg-reading-pane.empty{background:#f6f7f9!important}
.messages-shell .msg-read-empty{text-align:center!important;color:#89939e!important}
.messages-shell .msg-read-empty-icon{
  width:64px!important;height:64px!important;border-radius:50%!important;
  background:#e9edf1!important;color:#687786!important;
  margin:0 auto 12px!important;
}
.messages-shell .msg-read-empty strong{
  display:block!important;color:#4a5662!important;font-size:.76rem!important;margin-bottom:3px!important;
}
.messages-shell .msg-read-empty span{font-size:.61rem!important}

/* Compose modal matches the new neutral mail chrome */
.messages-shell ~ .compose-modal .modal-content,
.compose-modal .modal-content{
  border-radius:7px!important;box-shadow:0 15px 45px rgba(16,24,40,.20)!important;
}
.compose-modal .modal-header{
  padding:12px 16px!important;border-bottom:1px solid var(--mail-line)!important;
}
.compose-head-icon{
  width:34px!important;height:34px!important;border-radius:7px!important;
  background:#e9eef3!important;color:var(--mail-accent)!important;
}
.compose-modal .modal-body{padding:15px 17px!important}
.compose-modal .modal-footer{padding:9px 17px!important;background:#fafbfc!important}
.compose-modal .form-control,.compose-modal .form-select{
  border-radius:6px!important;font-size:.7rem!important;
}
.compose-send{background:var(--mail-accent)!important;border-radius:5px!important}
.compose-cancel{border-radius:5px!important}

/* Responsive */
@media(max-width:1100px){
  .messages-shell .msg-layout.has-thread{
    grid-template-columns:165px 325px minmax(340px,1fr)!important;
  }
}
@media(max-width:900px){
  .messages-shell .msg-layout.has-thread{
    grid-template-columns:165px minmax(0,1fr)!important;
  }
  .messages-shell .msg-list-pane{min-width:0}
  .messages-shell .msg-reading-pane{position:absolute!important;inset:0!important;z-index:20!important}
}
@media(max-width:767px){
  .messages-shell .msg-layout{display:block!important;height:auto!important}
  .messages-shell .msg-sidebar{border-bottom:1px solid var(--mail-line)!important}
  .messages-shell .msg-thread{padding:15px 10px 20px!important}
  .messages-shell .msg-root{padding:16px!important}
}
</style>
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