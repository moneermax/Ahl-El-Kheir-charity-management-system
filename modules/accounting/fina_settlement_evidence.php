<?php
// modules/accounting/fina_settlement_evidence.php - Secure evidence upload for فينا الخير settlements
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
Session::start();
if(!Session::isLoggedIn()){header('Location: '.APP_URL.'index.php');exit();}
$role=Session::getUserRole();$uid=(int)Session::getUserId();
if(!in_array($role,['financial_manager','admin'],true)){header('Location: '.APP_URL.'index.php');exit();}
$id=(int)($_GET['id']??$_POST['settlement_id']??0);$errors=[];$settlement=$id?dbFetchOne("SELECT * FROM fina_settlements WHERE id=?",[$id]):null;
if(!$settlement){http_response_code(404);exit('طلب التسوية غير موجود.');}
if($_SERVER['REQUEST_METHOD']==='POST'&&verify_csrf()){
    if(!in_array($settlement['status'],['approved','transferred'],true))$errors[]='لا يمكن رفع إثبات التحويل قبل اعتماد التسوية.';
    if(isset($_FILES['evidence_file'])&&$_FILES['evidence_file']['error']===UPLOAD_ERR_OK){
        $file=$_FILES['evidence_file'];$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','pdf'],true))$errors[]='صيغة الإثبات يجب أن تكون JPG أو PNG أو PDF.';
        elseif((int)$file['size']>10*1024*1024)$errors[]='حجم الإثبات يتجاوز 10 ميجابايت.';
        else{$dir=dirname(__DIR__,2).'/storage/receipts/fina';if(!is_dir($dir))@mkdir($dir,0777,true);$fn='FINA-STL-'.$id.'-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$ext;$path='storage/receipts/fina/'.$fn;if(move_uploaded_file($file['tmp_name'],$dir.'/'.$fn)){dbExecute("UPDATE fina_settlements SET evidence_path=? WHERE id=?",[$path,$id]);flash('success','تم حفظ إثبات تحويل فينا الخير بنجاح.');header('Location: '.APP_URL.'modules/accounting/fina_settlements.php');exit();}else$errors[]='فشل حفظ إثبات التحويل على الخادم.';}}
    elseif(isset($_FILES['evidence_file'])&&$_FILES['evidence_file']['error']!==UPLOAD_ERR_NO_FILE)$errors[]='حدث خطأ أثناء رفع الإثبات.';
}
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-file-circle-check me-2"></i>إثبات تحويل فينا الخير</h2><p>التسوية: <strong><?php echo e($settlement['settlement_code']);?></strong> — المبلغ <?php echo number_format((float)$settlement['amount'],2).' '.e($settlement['currency_code']);?></p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php';?>
<?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?php echo e($e);?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card"><div class="card-body"><form method="post" enctype="multipart/form-data"><?php echo csrf_field();?><input type="hidden" name="settlement_id" value="<?php echo $id;?>"><label class="form-label">إثبات التحويل *</label><input type="file" name="evidence_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required><div class="form-text">JPG / PNG / PDF حتى 10MB.</div><div class="mt-3"><button class="btn btn-primary">حفظ الإثبات</button><a class="btn btn-secondary ms-2" href="<?php echo APP_URL;?>modules/accounting/fina_settlements.php">رجوع</a></div></form></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php';?>