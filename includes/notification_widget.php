<?php
/*
 |--------------------------------------------------------------------------
 | Application notification bell
 |--------------------------------------------------------------------------
 | Deliberately separate from the internal messaging envelope.
 | Notifications are also refreshed live by a lightweight polling endpoint so
 | users do not need to refresh, log out, or log back in to see new actions.
 |--------------------------------------------------------------------------
 */
if (Session::isLoggedIn()) {
    $akNotifUnread = isset($notifUnread) ? (int)$notifUnread : 0;
    $akNotifItems = [];

    try {
        $akNotifItems = dbFetchAll(
            "SELECT id, title, body, link, created_at
             FROM notifications
             WHERE recipient_user_id = ?
             ORDER BY id DESC
             LIMIT 8",
            [current_user_id()]
        );
    } catch (Throwable $e) {
        $akNotifItems = [];
    }

    $akNotifMarkReadUrl = APP_URL . 'modules/notifications/mark_all_read.php';
    $akNotifIndividualReadUrl = APP_URL . 'modules/notifications/mark_read.php';
    $akNotifPollUrl = APP_URL . 'modules/notifications/poll.php';
    $akNotifRedirect = (string)($_SERVER['REQUEST_URI'] ?? APP_URL);
?>
<style>
#akNotificationBell{position:relative;z-index:1061;display:inline-flex;align-items:center;flex-shrink:0}
#akNotificationBell .ak-notif-toggle{position:relative;border:1px solid rgba(255,255,255,.2);border-radius:6px;width:34px;height:34px;padding:0;background:rgba(255,255,255,.1);color:#fff;box-shadow:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
#akNotificationBell .ak-notif-toggle:hover{background:rgba(255,255,255,.2);color:#fff}
#akNotificationBell .ak-notif-badge{position:absolute;top:-5px;inset-inline-end:-6px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#ffc107;color:#172338;font:700 10px/18px Cairo,sans-serif;text-align:center}
#akNotificationBell .ak-notif-panel{display:none;position:absolute;top:calc(100% + 7px);inset-inline-end:0;width:360px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #e3e7ee;border-radius:12px;box-shadow:0 10px 35px rgba(10,31,68,.2);overflow:hidden;color:#21315b}
#akNotificationBell.open .ak-notif-panel{display:block}
.ak-notif-panel-head{padding:12px 14px;background:#1b4d8f;color:#fff;display:flex;justify-content:space-between;align-items:center}
.ak-notif-panel-body{max-height:360px;overflow:auto}
.ak-notif-item{display:block;width:100%;padding:11px 14px;border:0;border-bottom:1px solid #eef1f5;background:#fff;text-decoration:none;color:#21315b;text-align:inherit;cursor:pointer}
.ak-notif-item:hover{background:#f6f9fd}
.ak-notif-item strong{display:block;font-size:.8rem;margin-bottom:3px}
.ak-notif-item .ak-notif-body{font-size:.7rem;line-height:1.7;color:#657286}
.ak-notif-item small{display:block;margin-top:4px;color:#9aa4b2;font-size:.58rem}
.ak-notif-empty{padding:30px 15px;text-align:center;color:#6c757d;font-size:.72rem}
.ak-notif-mark{font-size:.6rem;color:#fff;opacity:.9}
.ak-notif-mark:hover{opacity:1;color:#fff}
.ak-notif-read-form{margin:0;padding:0}
@media(max-width:991px){#akNotificationBell .ak-notif-panel{inset-inline-end:-90px}}
@media(max-width:576px){#akNotificationBell .ak-notif-panel{position:fixed;top:62px;inset-inline-end:10px;width:calc(100vw - 20px)}}
</style>
<div id="akNotificationBell" data-poll-url="<?php echo e($akNotifPollUrl); ?>" data-mark-read-url="<?php echo e($akNotifIndividualReadUrl); ?>" data-current-url="<?php echo e($akNotifRedirect); ?>" data-csrf-token="<?php echo e(csrf_token()); ?>">
 <button type="button" class="ak-notif-toggle" aria-label="الإشعارات" title="الإشعارات" onclick="document.getElementById('akNotificationBell').classList.toggle('open')">
  <i class="fas fa-bell"></i>
  <?php if ($akNotifUnread > 0): ?><span class="ak-notif-badge"><?php echo $akNotifUnread > 99 ? '99+' : $akNotifUnread; ?></span><?php endif; ?>
 </button>
 <div class="ak-notif-panel">
  <div class="ak-notif-panel-head">
   <strong><i class="fas fa-bell me-1"></i>الإشعارات</strong>
   <?php if ($akNotifUnread > 0 && $akNotifMarkReadUrl): ?>
    <form method="post" action="<?php echo e($akNotifMarkReadUrl); ?>" class="d-inline">
     <?php echo csrf_field(); ?>
     <input type="hidden" name="redirect" value="<?php echo e($akNotifRedirect); ?>">
     <button type="submit" class="ak-notif-mark border-0 bg-transparent p-0">تحديد الكل كمقروء</button>
    </form>
   <?php endif; ?>
  </div>
  <div class="ak-notif-panel-body">
   <?php if (!$akNotifItems): ?>
    <div class="ak-notif-empty">لا توجد إشعارات.</div>
   <?php else: foreach ($akNotifItems as $ni): ?>
    <?php
    $notifId = (int)($ni['id'] ?? 0);
    $notifLink = trim((string)($ni['link'] ?? ''));
    $notifRedirect = $notifLink !== '' ? $notifLink : $akNotifRedirect;
    ?>
    <?php if ($notifId > 0): ?>
    <form method="post" action="<?php echo e($akNotifIndividualReadUrl); ?>" class="ak-notif-read-form">
     <?php echo csrf_field(); ?>
     <input type="hidden" name="notification_id" value="<?php echo $notifId; ?>">
     <input type="hidden" name="redirect" value="<?php echo e($notifRedirect); ?>">
     <button type="submit" class="ak-notif-item">
      <strong><?php echo e($ni['title'] ?? ''); ?></strong>
      <div class="ak-notif-body"><?php echo e($ni['body'] ?? ''); ?></div>
      <small><?php echo e($ni['created_at'] ?? ''); ?></small>
     </button>
    </form>
    <?php else: ?>
    <div class="ak-notif-item">
     <strong><?php echo e($ni['title'] ?? ''); ?></strong>
     <div class="ak-notif-body"><?php echo e($ni['body'] ?? ''); ?></div>
     <small><?php echo e($ni['created_at'] ?? ''); ?></small>
    </div>
    <?php endif; ?>
   <?php endforeach; endif; ?>
  </div>
 </div>
</div>
<script>
(function(){
 const root=document.getElementById('akNotificationBell');
 const controls=document.querySelector('.qa-user-controls');
 const messageBell=document.getElementById('akMessagingBell');
 if(root && controls){controls.insertBefore(root,messageBell || document.getElementById('userDropdown') || controls.firstChild)}
 document.addEventListener('click',function(e){if(root && !root.contains(e.target))root.classList.remove('open')});

 if(!root) return;

 const pollUrl=root.getAttribute('data-poll-url');
 const markReadUrl=root.getAttribute('data-mark-read-url');
 const csrfToken=root.getAttribute('data-csrf-token') || '';
 const currentUrl=root.getAttribute('data-current-url') || window.location.href;
 const panelBody=root.querySelector('.ak-notif-panel-body');
 const panelHead=root.querySelector('.ak-notif-panel-head');
 const toggle=root.querySelector('.ak-notif-toggle');
 const knownIds={};
 let initialized=false;
 let busy=false;

 <?php foreach ($akNotifItems as $ni): ?>
 knownIds[<?php echo (int)($ni['id'] ?? 0); ?>]=true;
 <?php endforeach; ?>

 function esc(value){
     return String(value == null ? '' : value)
         .replace(/&/g,'&amp;').replace(/</g,'&lt;')
         .replace(/>/g,'&gt;').replace(/\"/g,'&quot;').replace(/'/g,'&#039;');
 }

 function render(data){
     if(!panelBody) return;
     const items=Array.isArray(data.items) ? data.items : [];
     if(!items.length){
         panelBody.innerHTML='<div class="ak-notif-empty">لا توجد إشعارات.</div>';
     } else {
         panelBody.innerHTML=items.map(function(item){
             const id=Number(item.id||0);
             const link=String(item.link||'').trim() || currentUrl;
             if(!id) return '<div class="ak-notif-item"><strong>'+esc(item.title)+'</strong><div class="ak-notif-body">'+esc(item.body)+'</div><small>'+esc(item.created_at)+'</small></div>';
             return '<form method="post" action="'+esc(markReadUrl)+'" class="ak-notif-read-form">'
                 +'<input type="hidden" name="csrf_token" value="'+esc(csrfToken)+'">'
                 +'<input type="hidden" name="notification_id" value="'+id+'">'
                 +'<input type="hidden" name="redirect" value="'+esc(link)+'">'
                 +'<button type="submit" class="ak-notif-item"><strong>'+esc(item.title)+'</strong><div class="ak-notif-body">'+esc(item.body)+'</div><small>'+esc(item.created_at)+'</small></button>'
                 +'</form>';
         }).join('');
     }
 }

 function updateBadge(unread){
     const count=Number(unread||0);
     let badge=toggle ? toggle.querySelector('.ak-notif-badge') : null;
     if(count<=0){ if(badge) badge.remove(); return; }
     if(!badge){ badge=document.createElement('span'); badge.className='ak-notif-badge'; toggle.appendChild(badge); }
     badge.textContent=count>99?'99+':String(count);
 }

 function poll(){
     if(busy || !pollUrl) return;
     busy=true;
     fetch(pollUrl+'?t='+Date.now(),{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}})
         .then(function(response){ if(!response.ok) throw new Error('notification poll failed'); return response.json(); })
         .then(function(data){
             if(!data || data.ok!==true) return;
             const items=Array.isArray(data.items) ? data.items : [];
             if(initialized){
                 items.slice().reverse().forEach(function(item){
                     const id=Number(item.id||0);
                     if(id>0 && !knownIds[id]){
                         knownIds[id]=true;
                         if(window.AKNotify && typeof window.AKNotify.toast==='function'){
                             window.AKNotify.toast('info',(item.title||'إشعار جديد')+' — '+(item.body||''));
                         }
                     }
                 });
             } else {
                 items.forEach(function(item){ const id=Number(item.id||0); if(id>0) knownIds[id]=true; });
                 initialized=true;
             }
             updateBadge(data.unread);
             render(data);
         })
         .catch(function(){})
         .finally(function(){busy=false;});
 }

 poll();
 window.setInterval(poll,5000);
})();
</script>
<?php } ?>
