<?php
if (Session::isLoggedIn()) {
    require_once dirname(__DIR__).'/config/messaging.php';
    $akMsgUid=Session::getUserId();
    $akMsgRole=Session::getUserRole();
    $akMsgUnread=get_unified_unread_count($akMsgUid,$akMsgRole);
    $akMsgRecent=get_messages($akMsgUid,$akMsgRole,'all',5,0);
?>
<style>
#akMessagingBell{position:relative;z-index:1060;display:inline-flex;align-items:center;flex-shrink:0}
#akMessagingBell .ak-msg-toggle{position:relative;border:1px solid rgba(255,255,255,.2);border-radius:6px;width:34px;height:34px;padding:0;background:rgba(255,255,255,.1);color:#fff;box-shadow:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
#akMessagingBell .ak-msg-toggle:hover{background:rgba(255,255,255,.2);color:#fff}
#akMessagingBell .ak-msg-badge{position:absolute;top:-5px;inset-inline-end:-6px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#dc3545;color:#fff;font:700 10px/18px Cairo,sans-serif;text-align:center}
#akMessagingBell .ak-msg-panel{display:none;position:absolute;top:calc(100% + 7px);inset-inline-end:0;width:340px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #e3e7ee;border-radius:12px;box-shadow:0 10px 35px rgba(10,31,68,.2);overflow:hidden}
#akMessagingBell.open .ak-msg-panel{display:block}
.ak-msg-panel-head{padding:12px 14px;background:#1b4d8f;color:#fff;display:flex;justify-content:space-between;align-items:center}.ak-msg-panel-body{max-height:330px;overflow:auto}.ak-msg-item{display:block;padding:11px 14px;border-bottom:1px solid #eef1f5;text-decoration:none;color:#21315b}.ak-msg-item:hover{background:#f6f9fd}.ak-msg-item strong{display:block;font-size:.86rem}.ak-msg-item small{color:#6c757d}.ak-msg-empty{padding:30px 15px;text-align:center;color:#6c757d}
@media(max-width:991px){#akMessagingBell .ak-msg-panel{inset-inline-end:-120px}}
@media(max-width:576px){#akMessagingBell .ak-msg-panel{position:fixed;top:62px;inset-inline-end:10px;width:calc(100vw - 20px)}}
</style>
<div id="akMessagingBell">
 <button type="button" class="ak-msg-toggle" aria-label="<?php echo e(t('messages.title')); ?>" title="<?php echo e(t('messages.title')); ?>" onclick="document.getElementById('akMessagingBell').classList.toggle('open')"><i class="fas fa-envelope"></i><?php if($akMsgUnread>0):?><span id="akMsgBadge" class="ak-msg-badge"><?php echo $akMsgUnread>99?'99+':$akMsgUnread;?></span><?php else:?><span id="akMsgBadge" class="ak-msg-badge d-none"></span><?php endif;?></button>
 <div class="ak-msg-panel">
  <div class="ak-msg-panel-head"><strong><i class="fas fa-envelope-open-text me-1"></i><?php echo e(t('messages.title')); ?></strong><a href="<?php echo APP_URL;?>modules/messages/index.php" class="text-white text-decoration-none small"><?php echo e(t('messages.open_all')); ?></a></div>
  <div class="ak-msg-panel-body" id="akMsgPanelBody">
   <?php if(!$akMsgRecent):?><div class="ak-msg-empty"><?php echo e(t('messages.no_messages')); ?></div><?php else:foreach($akMsgRecent as $mi):?><a class="ak-msg-item" href="<?php echo APP_URL;?>modules/messages/index.php?message=<?php echo (int)$mi['id'];?>"><strong><?php echo e($mi['subject']);?></strong><small><?php echo e($mi['sender_name']);?> · <?php echo e($mi['created_at']);?></small><div class="small text-secondary text-truncate"><?php echo e($mi['body_preview']??'');?></div></a><?php endforeach;endif;?>
  </div>
 </div>
</div>
<script>
(function(){
 const root=document.getElementById('akMessagingBell');
 const badge=document.getElementById('akMsgBadge');
 const endpoint=<?php echo json_encode(APP_URL.'modules/messages/realtime.php');?>;
 const newMessageText=<?php echo json_encode(t('messages.new_message')); ?>;
 if(!root)return;
 const controls=document.querySelector('.qa-user-controls');
 const userDropdown=document.getElementById('userDropdown');
 if(controls){controls.insertBefore(root,userDropdown||controls.firstChild)}
 function setBadge(n){n=parseInt(n||0,10);if(!badge)return;if(n>0){badge.textContent=n>99?'99+':n;badge.classList.remove('d-none')}else{badge.textContent='';badge.classList.add('d-none')}}
 document.addEventListener('click',function(e){if(!root.contains(e.target))root.classList.remove('open')});
 try{
  let last=parseInt(localStorage.getItem('ak_msg_last_id')||'0',10)||0;
  const es=new EventSource(endpoint+'?stream=1&last_id='+last);
  es.addEventListener('message_update',function(ev){try{const d=JSON.parse(ev.data);setBadge(d.unread);if(d.last_id)localStorage.setItem('ak_msg_last_id',d.last_id);if(document.visibilityState!=='visible'&&'Notification' in window&&Notification.permission==='granted'){new Notification(newMessageText,{body:d.subject+' — '+d.sender})}}catch(e){}});
  es.addEventListener('unread',function(ev){try{setBadge(JSON.parse(ev.data).unread)}catch(e){}});
 }catch(e){}
})();
</script>
<?php } ?>
