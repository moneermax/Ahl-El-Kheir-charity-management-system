<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
Session::start();
$userRole=Session::getUserRole();
if(!Session::isLoggedIn()||!in_array($userRole,['hr_manager','hr_staff','admin'])){header('Location: '.APP_URL.'index.php');exit();}
$selected_date=$_GET['date']??date('Y-m-d'); $message=''; $msg_type='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    $emp_id=(int)($_POST['employee_id']??0);
    try{
        if($action==='check_in'){
            $work_mode=$_POST['work_mode']??'remote';
            $check_in_time=date('H:i:s');
            $status='present';
            $sql="INSERT INTO attendance (employee_id, date, check_in, work_mode, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), work_mode = VALUES(work_mode), status = VALUES(status)";
            dbExecute($sql,[$emp_id,$selected_date,$check_in_time,$work_mode,$status]);
            $message=t('hr.attendance_recorded');
        }elseif($action==='check_out'){
            $check_out_time=date('H:i:s');
            dbExecute("UPDATE attendance SET check_out = ? WHERE employee_id = ? AND date = ?",[$check_out_time,$emp_id,$selected_date]);
            $message=t('hr.checkout_recorded');
        }elseif($action==='mark_absent'){
            dbExecute("INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, 'absent') ON DUPLICATE KEY UPDATE status = 'absent'",[$emp_id,$selected_date]);
            $message=t('hr.absence_recorded');
        }elseif($action==='mark_leave'){
            dbExecute("INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, 'on_leave') ON DUPLICATE KEY UPDATE status = 'on_leave'",[$emp_id,$selected_date]);
            $message=t('hr.attendance_leave_recorded');
        }
    }catch(Throwable $e){
        $message=t('hr.attendance_error',['message'=>$e->getMessage()]);
        $msg_type='error';
    }
}
$employees=dbFetchAll("SELECT e.id,e.full_name,e.department_id,d.name_ar as dept_name FROM employees e LEFT JOIN departments d ON e.department_id=d.id WHERE e.status='active' ORDER BY e.full_name",[]);
$attendance_records=[];
$att_list=dbFetchAll("SELECT * FROM attendance WHERE date = ?",[$selected_date]);
foreach($att_list as $att)$attendance_records[$att['employee_id']]=$att;
$stats=['present'=>0,'absent'=>0,'late'=>0,'on_leave'=>0,'remote'=>0,'onsite'=>0];
foreach($attendance_records as $att){if(isset($stats[$att['status']]))$stats[$att['status']]++;if(isset($stats[$att['work_mode']]))$stats[$att['work_mode']]++;}
$pageTitle=t('hr.attendance_title'); require_once __DIR__.'/../../includes/header.php';
?>
<style>
.fm-header{background:linear-gradient(135deg,#1b4d8f 0%,#2c5aa0 100%);color:#fff;padding:25px;border-radius:12px;margin-bottom:25px}.fm-header h1{margin:0;font-size:1.8rem}.fm-header p{margin:5px 0 0;opacity:.9}.fm-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden}.fm-card-head{background:#1b4d8f;color:#fff;padding:12px 18px;font-weight:700;font-size:1rem;display:flex;justify-content:space-between;align-items:center}.fm-card-body{padding:20px}.stat-box{background:#fff;border-radius:10px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.05);border-top:4px solid #1b4d8f;text-align:center}.stat-box.green{border-top-color:#28a745}.stat-box.red{border-top-color:#dc3545}.stat-box.amber{border-top-color:#ffc107}.stat-box.blue{border-top-color:#17a2b8}.stat-value{font-size:1.6rem;font-weight:700;color:#1b4d8f;margin:8px 0}.stat-label{color:#666;font-size:.85rem}.fm-table{width:100%;border-collapse:collapse}.fm-table th,.fm-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:right;font-size:.9rem}.fm-table th{background:#f8f9fa;font-weight:700;color:#1b4d8f}.fm-table tr:hover{background:#f8f9fa}.badge-fm{padding:4px 10px;border-radius:12px;font-size:.75rem;font-weight:700}.badge-green{background:#d4edda;color:#155724}.badge-red{background:#f8d7da;color:#721c24}.badge-amber{background:#fff3cd;color:#856404}.badge-blue{background:#d1ecf1;color:#0c5460}.badge-gray{background:#e9ecef;color:#6c757d}.btn-fm{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:34px;padding:5px 10px;border-radius:7px;border:1px solid transparent;cursor:pointer;font-weight:700;text-decoration:none;font-size:.78rem;line-height:1.2;margin:2px;box-shadow:0 1px 3px rgba(0,0,0,.08);transition:all .15s ease}.btn-fm:hover{transform:translateY(-1px);box-shadow:0 3px 7px rgba(0,0,0,.12);text-decoration:none}.btn-navy{background:#1b4d8f;color:#fff;border-color:#1b4d8f}.btn-navy:hover{background:#153e75;color:#fff}.btn-success{background:#198754;color:#fff;border-color:#198754}.btn-success:hover{background:#157347;color:#fff}.btn-danger{background:#dc3545;color:#fff;border-color:#dc3545}.btn-danger:hover{background:#bb2d3b;color:#fff}.btn-warning{background:#fff3cd;color:#7a5b00;border-color:#ffe69c}.btn-warning:hover{background:#ffecb5;color:#664d03}.attendance-action-form{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}.attendance-action-form .form-select{width:auto;min-width:105px}.form-control,.form-select{border-radius:7px;border:1px solid #ced4da;padding:.4rem .6rem;font-size:.85rem}.grid-4{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px}
</style>
<div class="fm-header"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px"><div><h1><i class="fas fa-clock me-2"></i> <?php echo e(t('hr.attendance_title')); ?></h1><p><?php echo e(t('hr.attendance_intro',['date'=>date('Y-m-d',strtotime($selected_date))])); ?></p></div><form method="GET" style="display:flex;gap:10px"><input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" style="width:auto"><button type="submit" class="btn-fm btn-navy"><i class="fas fa-filter"></i> <?php echo e(t('hr.show')); ?></button></form></div></div>
<?php if($message): ?><div class="alert alert-<?php echo $msg_type==='error'?'danger':'success'; ?> alert-dismissible fade show" style="border-radius:8px"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="grid-4"><div class="stat-box green"><div class="stat-value"><?php echo $stats['present']; ?></div><div class="stat-label"><?php echo e(t('hr.present')); ?></div></div><div class="stat-box amber"><div class="stat-value"><?php echo $stats['late']; ?></div><div class="stat-label"><?php echo e(t('hr.late')); ?></div></div><div class="stat-box red"><div class="stat-value"><?php echo $stats['absent']; ?></div><div class="stat-label"><?php echo e(t('hr.absent')); ?></div></div><div class="stat-box blue"><div class="stat-value"><?php echo $stats['on_leave']; ?></div><div class="stat-label"><?php echo e(t('hr.on_leave')); ?></div></div></div>
<div class="fm-card"><div class="fm-card-head"><span>📋 <?php echo e(t('hr.attendance_records',['date'=>date('Y-m-d',strtotime($selected_date))])); ?></span><span class="badge-fm badge-blue"><?php echo e(t('hr.active_employee_count',['count'=>count($employees)])); ?></span></div><div class="fm-card-body"><div class="table-responsive"><table class="fm-table"><thead><tr><th><?php echo e(t('hr.employee')); ?></th><th><?php echo e(t('groups.group')); ?></th><th><?php echo e(t('hr.check_in')); ?></th><th><?php echo e(t('hr.check_out')); ?></th><th><?php echo e(t('hr.work_mode_remote')); ?></th><th><?php echo e(t('common.status')); ?></th><th class="text-end"><?php echo e(t('common.actions')); ?></th></tr></thead><tbody>
<?php if(empty($employees)): ?><tr><td colspan="7" class="text-center py-4 text-muted"><?php echo e(t('hr.no_active_employees')); ?></td></tr><?php else: foreach($employees as $emp): $att=$attendance_records[$emp['id']]??null;$status=$att['status']??'absent';$work_mode=$att['work_mode']??''; ?>
<tr><td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td><td><?php echo htmlspecialchars($emp['dept_name']??'-'); ?></td><td><?php if($att&&$att['check_in']): ?><span class="badge-fm badge-green"><?php echo substr($att['check_in'],0,5); ?></span><?php else: ?><span class="badge-fm badge-gray">--:--</span><?php endif; ?></td><td><?php if($att&&$att['check_out']): ?><span class="badge-fm badge-red"><?php echo substr($att['check_out'],0,5); ?></span><?php else: ?><span class="badge-fm badge-gray">--:--</span><?php endif; ?></td><td><?php $modes=['remote'=>'hr.work_mode_remote','onsite'=>'hr.work_mode_onsite','hybrid'=>'hr.work_mode_hybrid']; echo $att?e(t($modes[$work_mode]??'hr.work_mode_remote')):'-'; ?></td><td><?php $sc=['present'=>'badge-green','late'=>'badge-amber','absent'=>'badge-red','on_leave'=>'badge-blue','half_day'=>'badge-gray'];$sl=['present'=>'hr.present','late'=>'hr.late','absent'=>'hr.absent','on_leave'=>'hr.on_leave','half_day'=>'hr.half_day']; ?><span class="badge-fm <?php echo $sc[$status]??'badge-gray'; ?>"><?php echo e(t($sl[$status]??'common.status')); ?></span></td><td class="text-end"><?php if(!$att||!$att['check_in']): ?><form method="POST" class="attendance-action-form"><input type="hidden" name="action" value="check_in"><input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>"><select name="work_mode" class="form-select" required><option value="remote"><?php echo e(t('hr.work_mode_remote')); ?></option><option value="onsite"><?php echo e(t('hr.work_mode_onsite')); ?></option></select><button type="submit" class="btn-fm btn-success"><i class="fas fa-right-to-bracket"></i> <?php echo e(t('hr.check_in')); ?></button></form><?php elseif($att&&!$att['check_out']): ?><form method="POST" class="attendance-action-form"><input type="hidden" name="action" value="check_out"><input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>"><button type="submit" class="btn-fm btn-danger"><i class="fas fa-right-from-bracket"></i> <?php echo e(t('hr.check_out')); ?></button></form><?php else: ?><span class="text-muted" style="font-size:.8rem"><?php echo e(t('hr.complete')); ?></span><?php endif; ?><?php if(!$att||$att['status']==='absent'): ?><form method="POST" class="attendance-action-form"><input type="hidden" name="action" value="mark_leave"><input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>"><button type="submit" class="btn-fm btn-warning" title="<?php echo e(t('hr.leave')); ?>"><i class="fas fa-calendar-check"></i> <?php echo e(t('hr.leave')); ?></button></form><?php endif; ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>