<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle=t('users.password_title'); $active='password'; $forced=isset($_GET['forced'])&&$_GET['forced']==='1';
$me=dbFetchOne('SELECT * FROM users WHERE id = ?', [current_user_id()]); $hashCol=null;
if($me){if(array_key_exists('password_hash',$me))$hashCol='password_hash';elseif(array_key_exists('password',$me))$hashCol='password';}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf()){flash('error',t('users.session_expired'));}
 else{
  $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
  if(!$me||!$hashCol)flash('error',t('users.account_unavailable'));elseif($current===''||$new===''||$confirm==='')flash('error',t('users.fill_all'));elseif(!password_verify($current,(string)$me[$hashCol]))flash('error',t('users.current_invalid'));elseif(mb_strlen($new)<6)flash('error',t('users.minimum_error'));elseif($new!==$confirm)flash('error',t('users.mismatch'));else{
   dbExecute("UPDATE users SET {$hashCol} = ?, password_change_required = 0 WHERE id = ?",[password_hash($new,PASSWORD_DEFAULT),current_user_id()]);
   try{dbExecute("UPDATE password_recovery_requests SET status='completed' WHERE user_id=? AND status='approved'",[current_user_id()]);}catch(Throwable $e){}
   try{dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,'CHANGE_PASSWORD','users',?,NULL,?,?,?)",[current_user_id(),current_user_id(),json_encode(['password_changed'=>true,'forced'=>$forced],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);}catch(Throwable $e){}
   flash('success',$forced?t('users.forced_changed'):t('users.changed'));redirect(url('modules/users/change_password.php'));
  }
 }
 redirect(url('modules/users/change_password.php'.($forced?'?forced=1':'')));
}
include dirname(__DIR__,2).'/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2><i class="fas fa-key me-2"></i><?php echo e(t('users.password_title')); ?></h2><?php if($forced): ?><p class="mb-0"><strong><?php echo e(t('users.temporary_password')); ?>.</strong> <?php echo e(t('users.forced_intro')); ?></p><?php else: ?><p><?php echo e(t('users.password_intro')); ?></p><?php endif; ?></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<div class="card mb-4 fade-in" style="max-width:560px"><div class="card-body p-4"><form method="post" autocomplete="new-password"><?php echo csrf_field(); ?>
<div class="mb-3"><label class="form-label"><?php echo e($forced?t('users.temporary_password'):t('users.current_password')); ?></label><div class="input-group"><input type="password" name="current_password" id="ak_cur" class="form-control" required autocomplete="current-password"><button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('ak_cur');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button></div></div>
<div class="mb-3"><label class="form-label"><?php echo e(t('users.new_password')); ?></label><div class="input-group"><input type="password" name="new_password" id="ak_new" class="form-control" minlength="6" required autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('ak_new');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button></div><div class="form-text"><?php echo e(t('users.password_minimum')); ?></div></div>
<div class="mb-3"><label class="form-label"><?php echo e(t('users.confirm_password')); ?></label><div class="input-group"><input type="password" name="confirm_password" id="ak_conf" class="form-control" minlength="6" required autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('ak_conf');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button></div></div>
<div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo e(t('users.save_password')); ?></button><?php if(!$forced): ?><a href="<?php echo e(url('modules/users/profile.php')); ?>" class="btn btn-outline-secondary"><?php echo e(t('users.back')); ?></a><?php endif; ?></div>
</form></div></div>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="modules/users/profile.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>