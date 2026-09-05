<?php
/*
 |--------------------------------------------------------------------------
 | Application notification bell
 |--------------------------------------------------------------------------
 | Deliberately separate from the internal messaging envelope.
 |--------------------------------------------------------------------------
 */
if (Session::isLoggedIn()) {
    $akNotifUnread = isset($notifUnread) ? (int)$notifUnread : 0;
    $akNotifItems = isset($notifItems) && is_array($notifItems) ? $notifItems : [];
    $akNotifMarkReadUrl = APP_URL . 'modules/notifications/mark_all_read.php';
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
.ak-notif-item{display:block;padding:11px 14px;border-bottom:1px solid #eef1f5;text-decoration:none;color:#21315b}
.ak-notif-item:hover{background:#f6f9fd}
.ak-notif-item strong{display:block;font-size:.8rem;margin-bottom:3px}
.ak-notif-item .ak-notif-body{font-size:.7rem;line-height:1.7;color:#657286}
.ak-notif-item small{display:block;margin-top:4px;color:#9aa4b2;font-size:.58rem}
.ak-notif-empty{padding:30px 15px;text-align:center;color:#6c757d;font-size:.72rem}
.ak-notif-mark{font-size:.6rem;color:#fff;text-decoration:none;opacity:.9}
.ak-notif-mark:hover{opacity:1;color:#fff}
@media(max-width:991px){#akNotificationBell .ak-notif-panel{inset-inline-end:-90px}}
@media(max-width:576px){#akNotificationBell .ak-notif-panel{position:fixed;top:62px;inset-inline-end:10px;width:calc(100vw - 20px)}}
</style>
<div id="akNotificationBell">
 <button type="button" class="ak-notif-toggle" aria-label="الإشعارات" title="الإشعارات" onclick="document.getElementById('akNotificationBell').classList.toggle('open')">
  <i class="fas fa-bell"></i>
  <?php if ($akNotifUnread > 0): ?><span class="ak-notif-badge"><?php echo $akNotifUnread > 99 ? '99+' : $akNotifUnread; ?></span><?php endif; ?>
 </button>
 <div class="ak-notif-panel">
  <div class="ak-notif-panel-head">
   <strong><i class="fas fa-bell me-1"></i>الإشعارات</strong>
   <?php if ($akNotifUnread > 0 && $akNotifMarkReadUrl): ?><a class="ak-notif-mark" href="<?php echo e($akNotifMarkReadUrl); ?>">تحديد الكل كمقروء</a><?php endif; ?>
  </div>
  <div class="ak-notif-panel-body">
   <?php if (!$akNotifItems): ?>
    <div class="ak-notif-empty">لا توجد إشعارات.</div>
   <?php else: foreach ($akNotifItems as $ni): ?>
    <?php $notifLink = trim((string)($ni['link'] ?? '')); ?>
    <?php if ($notifLink !== ''): ?><a class="ak-notif-item" href="<?php echo e($notifLink); ?>"><?php else: ?><div class="ak-notif-item"><?php endif; ?>
     <strong><?php echo e($ni['title'] ?? ''); ?></strong>
     <div class="ak-notif-body"><?php echo e($ni['body'] ?? ''); ?></div>
     <small><?php echo e($ni['created_at'] ?? ''); ?></small>
    <?php if ($notifLink !== ''): ?></a><?php else: ?></div><?php endif; ?>
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
})();
</script>
<?php } ?>
