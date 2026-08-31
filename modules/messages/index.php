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

if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf()) { http_response_code(419); echo json_encode(['ok'=>false,'message'=>'انتهت صلاحية الجلسة.'],JSON_UNESCAPED_UNICODE); exit; }
    $action = $_POST['action'] ?? '';
    if ($action==='send') {
        $type = $_POST['recipient_type'] ?? 'user';
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $urgent = !empty($_POST['is_urgent']);
        if ($subject==='' || $body==='') { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'الموضوع ونص الرسالة مطلوبان.'],JSON_UNESCAPED_UNICODE); exit; }
        if ($type==='role') {
            if (!$canBroadcast) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'ليس لديك صلاحية الإرسال إلى دور كامل.'],JSON_UNESCAPED_UNICODE); exit; }
            $recipientRole = trim((string)($_POST['recipient_role'] ?? ''));
            $id = broadcast_to_role($uid,$recipientRole,$subject,$body,null,null,$urgent);
        } else {
            $recipient = (int)($_POST['recipient_user_id'] ?? 0);
            $id = send_user_message($uid,$recipient,$subject,$body,null,null,null,$urgent);
        }
        if (!$id) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'تعذر إرسال الرسالة. تحقق من المستلم والبيانات.'],JSON_UNESCAPED_UNICODE); exit; }
        echo json_encode(['ok'=>true,'id'=>$id,'message'=>'تم إرسال الرسالة بنجاح.'],JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action==='read') {
        $mid=(int)($_POST['message_id']??0);
        echo json_encode(['ok'=>mark_message_read($uid,$mid)],JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action==='read_all') {
        echo json_encode(['ok'=>mark_all_messages_read($uid,$role)],JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action==='reply') {
        $parent=(int)($_POST['message_id']??0);
        $parentMessage=get_message_by_id($parent,$uid);
        if(!$parentMessage){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'الرسالة غير متاحة.'],JSON_UNESCAPED_UNICODE);exit;}
        $body=trim((string)($_POST['body']??''));
        if($body===''){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'نص الرد مطلوب.'],JSON_UNESCAPED_UNICODE);exit;}
        $target=(int)$parentMessage['sender_id']===$uid ? (int)($parentMessage['recipient_user_id']??0) : (int)$parentMessage['sender_id'];
        if($target<=0){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'لا يمكن الرد على رسالة جماعية من هذه الشاشة حالياً.'],JSON_UNESCAPED_UNICODE);exit;}
        $id=send_user_message($uid,$target,'رد: '.$parentMessage['subject'],$body,$parent,null,null,false);
        echo json_encode(['ok'=>(bool)$id,'id'=>$id,'message'=>$id?'تم إرسال الرد.':'تعذر إرسال الرد.'],JSON_UNESCAPED_UNICODE);exit;
    }
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'طلب غير معروف.'],JSON_UNESCAPED_UNICODE); exit;
}

$view = $_GET['view'] ?? 'inbox';
$filter = $_GET['filter'] ?? 'all';
$messageId = (int)($_GET['message'] ?? 0);
$thread = $messageId ? get_message_thread($messageId,$uid) : ['root'=>null,'replies'=>[]];
$messages = $view==='sent' ? get_sent_messages($uid) : get_messages($uid,$role,$filter);
$users = get_messaging_users($uid);
$roles = $canBroadcast ? get_broadcast_roles() : [];
$unread = get_unread_message_count($uid,$role);
include dirname(__DIR__,2).'/includes/header.php';
?>
<style>
.msg-card{border:0;border-radius:14px;box-shadow:0 4px 18px rgba(20,58,107,.08)}
.msg-row{cursor:pointer;border-inline-start:4px solid transparent;transition:.15s}.msg-row:hover{background:#f6f9fd}.msg-row.unread{border-inline-start-color:#1b4d8f;background:#eef5ff}.msg-avatar{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#e7effa;color:#1b4d8f;font-weight:800}.msg-body{white-space:pre-wrap;line-height:1.9}.compose-panel{background:#f8fafc;border-radius:12px;padding:18px}
</style>
<div class="welcome-section fade-in d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h2><i class="fas fa-envelope-open-text me-2"></i>الرسائل الداخلية</h2><p class="text-muted mb-0">مراسلات داخلية آمنة بين مستخدمي النظام</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="fas fa-pen me-1"></i>رسالة جديدة</button>
</div>
<div class="row g-4">
 <div class="col-lg-3">
  <div class="card msg-card"><div class="list-group list-group-flush">
   <a class="list-group-item list-group-item-action <?php echo $view==='inbox'?'active':'';?>" href="<?php echo APP_URL;?>modules/messages/index.php"><i class="fas fa-inbox me-2"></i>الوارد <span class="badge <?php echo $view==='inbox'?'bg-light text-primary':'bg-primary';?> float-end"><?php echo $unread;?></span></a>
   <a class="list-group-item list-group-item-action <?php echo $view==='sent'?'active':'';?>" href="<?php echo APP_URL;?>modules/messages/index.php?view=sent"><i class="fas fa-paper-plane me-2"></i>المرسل</a>
   <button class="list-group-item list-group-item-action text-start" onclick="markAllRead()"><i class="fas fa-check-double me-2"></i>تحديد الوارد كمقروء</button>
  </div></div>
 </div>
 <div class="col-lg-9">
  <?php if($thread['root']): ?>
   <?php $m=$thread['root']; mark_message_read($uid,(int)$m['id']); ?>
   <div class="card msg-card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between gap-3"><div><h4><?php echo e($m['subject']);?></h4><div class="text-muted small">من: <?php echo e($m['sender_name']);?> · <?php echo e($m['created_at']);?></div></div><a class="btn btn-outline-secondary btn-sm" href="<?php echo APP_URL;?>modules/messages/index.php">عودة</a></div>
    <hr><div class="msg-body"><?php echo e($m['body']);?></div>
   </div></div>
   <?php foreach($thread['replies'] as $r): ?><div class="card msg-card mb-2"><div class="card-body"><div class="small text-muted mb-2"><?php echo e($r['sender_name']);?> · <?php echo e($r['created_at']);?></div><div class="msg-body"><?php echo e($r['body']);?></div></div></div><?php endforeach; ?>
   <?php if(($m['recipient_user_id']!==null || (int)$m['sender_id']!==$uid) && !($m['recipient_role']!==null)): ?>
   <form id="replyForm" class="card msg-card p-3 mt-3"><input type="hidden" name="action" value="reply"><input type="hidden" name="message_id" value="<?php echo (int)$m['id'];?>"><?php echo csrf_field();?><textarea name="body" class="form-control mb-2" rows="3" placeholder="اكتب ردك..."></textarea><div class="text-end"><button class="btn btn-primary">إرسال الرد</button></div></form>
   <?php endif; ?>
  <?php else: ?>
   <div class="d-flex justify-content-between align-items-center mb-2"><div class="btn-group btn-group-sm"><a class="btn <?php echo $filter==='all'?'btn-primary':'btn-outline-primary';?>" href="?view=<?php echo e($view);?>&filter=all">الكل</a><a class="btn <?php echo $filter==='unread'?'btn-primary':'btn-outline-primary';?>" href="?view=<?php echo e($view);?>&filter=unread">غير المقروء</a></div></div>
   <div class="card msg-card"><div class="list-group list-group-flush">
    <?php if(!$messages): ?><div class="p-5 text-center text-muted"><i class="fas fa-inbox fa-2x mb-2"></i><div>لا توجد رسائل.</div></div><?php endif; ?>
    <?php foreach($messages as $m): ?>
      <?php $isUnread=isset($m['is_read'])&&(int)$m['is_read']===0; $name=$view==='sent'?($m['recipient_name']??''):$m['sender_name']; ?>
      <a href="<?php echo APP_URL;?>modules/messages/index.php?message=<?php echo (int)$m['id'];?>" class="list-group-item list-group-item-action msg-row <?php echo $isUnread?'unread':'';?>">
       <div class="d-flex gap-3 align-items-start"><div class="msg-avatar"><?php echo e(mb_substr($name!==''?$name:'?',0,1,'UTF-8'));?></div><div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><strong><?php echo e($m['subject']);?></strong><small class="text-muted"><?php echo e($m['created_at']);?></small></div><div class="small text-muted mt-1"><?php echo $view==='sent'?'إلى: ':'من: '; echo e($name);?><?php if(!empty($m['recipient_role'])):?> · إلى دور: <?php echo e($m['recipient_role']);?><?php endif;?></div><div class="text-secondary small mt-1"><?php echo e($m['body_preview']??mb_substr($m['body']??'',0,150,'UTF-8'));?></div></div><?php if($isUnread):?><span class="badge bg-primary">جديد</span><?php endif;?></div>
      </a>
    <?php endforeach; ?>
   </div></div>
  <?php endif; ?>
 </div>
</div>

<div class="modal fade" id="composeModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">رسالة جديدة</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form id="composeForm"><div class="modal-body"><?php echo csrf_field();?><input type="hidden" name="action" value="send">
 <div class="row g-3"><div class="col-md-4"><label class="form-label">نوع الإرسال</label><select name="recipient_type" id="recipientType" class="form-select"><option value="user">مستخدم محدد</option><?php if($canBroadcast):?><option value="role">دور كامل</option><?php endif;?></select></div>
 <div class="col-md-8" id="userRecipientWrap"><label class="form-label">المستلم</label><select name="recipient_user_id" class="form-select" required><option value="">اختر المستخدم</option><?php foreach($users as $u):?><option value="<?php echo (int)$u['id'];?>"><?php echo e($u['name']);?> — <?php echo e($u['role_name_ar']??$u['role']);?></option><?php endforeach;?></select></div>
 <div class="col-md-8 d-none" id="roleRecipientWrap"><label class="form-label">الدور المستلم</label><select name="recipient_role" class="form-select"><?php foreach($roles as $r):?><option value="<?php echo e($r['code']);?>"><?php echo e($r['name_ar']?:$r['name_en']);?> (<?php echo (int)$r['active_count'];?> مستخدم)</option><?php endforeach;?></select></div>
 <div class="col-12"><label class="form-label">الموضوع</label><input name="subject" maxlength="255" class="form-control" required></div><div class="col-12"><label class="form-label">الرسالة</label><textarea name="body" rows="7" class="form-control" maxlength="10000" required></textarea></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_urgent" id="urgent"><label class="form-check-label" for="urgent">رسالة عاجلة</label></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>إرسال</button></div></form></div></div></div>
<script>
const msgCsrf=<?php echo json_encode(csrf_token());?>;
const apiUrl=<?php echo json_encode(APP_URL.'modules/messages/realtime.php');?>;
const msgUrl=<?php echo json_encode(APP_URL.'modules/messages/index.php');?>;
const typeEl=document.getElementById('recipientType');
if(typeEl)typeEl.addEventListener('change',()=>{const role=typeEl.value==='role';document.getElementById('roleRecipientWrap').classList.toggle('d-none',!role);document.getElementById('userRecipientWrap').classList.toggle('d-none',role);});
async function postMessage(form){const r=await fetch(msgUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(new FormData(form))});return r.json();}
document.getElementById('composeForm')?.addEventListener('submit',async e=>{e.preventDefault();const b=e.currentTarget.querySelector('button[type=submit]');b.disabled=true;try{const x=await postMessage(e.currentTarget);if(!x.ok)throw new Error(x.message||'فشل الإرسال');location.href=msgUrl+'?message='+x.id;}catch(err){alert(err.message)}finally{b.disabled=false;}});
document.getElementById('replyForm')?.addEventListener('submit',async e=>{e.preventDefault();const x=await postMessage(e.currentTarget);if(x.ok)location.reload();else alert(x.message||'فشل إرسال الرد');});
async function markAllRead(){const f=new URLSearchParams({action:'read_all',csrf_token:msgCsrf});const r=await fetch(msgUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:f});const x=await r.json();if(x.ok)location.reload();}
(function(){let last=0;try{last=parseInt(localStorage.getItem('ak_msg_last_id')||'0',10)||0}catch(e){};const es=new EventSource(apiUrl+'?stream=1&last_id='+last);es.addEventListener('message_update',e=>{try{const d=JSON.parse(e.data);if(d.last_id){try{localStorage.setItem('ak_msg_last_id',d.last_id)}catch(x){}}if(d.unread>0&&location.pathname.indexOf('/messages/')===-1){document.title='('+d.unread+') '+document.title;}}catch(x){}});es.onerror=()=>{};})();
</script>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>