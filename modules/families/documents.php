<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
Session::start();

// Schema evolution: ensure table and doc_type column exist
dbExecute("CREATE TABLE IF NOT EXISTS family_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    doc_type VARCHAR(50) NOT NULL DEFAULT 'other',
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NULL,
    uploaded_at DATETIME NOT NULL,
    INDEX (family_id)
)");
dbExecute("ALTER TABLE family_documents ADD COLUMN IF NOT EXISTS doc_type VARCHAR(50) NOT NULL DEFAULT 'other'");

// Role guard
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'accountant', 'accountant_staff', 'financial_manager'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowed_roles, true)) {
    flash('error', t('غير مصرح بالوصول / Unauthorized access'));
    redirect(url('index.php'));
}

$family_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$family = dbFetchOne("SELECT * FROM families WHERE id = ?", [$family_id]);
if (!$family) {
    flash('error', t('الأسرة غير موجودة / Family not found'));
    redirect(url('modules/families/index.php'));
}

// Nanny scope check
if (Session::getUserRole() === 'nanny' && (int)($family['nanny_id'] ?? 0) !== Session::getUserId()) {
    flash('error', t('غير مصرح بالوصول لهذه الأسرة / Unauthorized access to this family'));
    redirect(url('dashboard/nanny_dashboard.php'));
}

$doc_types = [
    'eligibility' => t('شهادة استحقاق / Eligibility'),
    'id-card' => t('بطاقة هوية / ID Card'),
    'national-id' => t('رقم وطني / National ID'),
    'bank' => t('بيانات بنكية / Bank Info'),
    'birth-cert' => t('شهادة ميلاد / Birth Certificate'),
    'death-cert' => t('شهادة وفاة / Death Certificate'),
    'other' => t('أخرى / Other')
];

$fam_code = $family['family_code'] ?? ($family['code'] ?? 'FAM' . $family['id']);
$fam_code_safe = preg_replace('/[^A-Za-z0-9_-]/', '', $fam_code);
if (empty($fam_code_safe)) $fam_code_safe = 'FAM' . $family['id'];

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_documents'])) {
    verify_csrf();
    $doc_type = $_POST['doc_type'] ?? 'other';
    if (!array_key_exists($doc_type, $doc_types)) {
        $doc_type = 'other';
    }

    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_size = 10 * 1024 * 1024;
    $upload_dir = __DIR__ . '/../../storage/documents/' . $fam_code_safe . '/' . $doc_type;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!empty($_FILES['documents']['name'][0])) {
        $files = $_FILES['documents'];
        $file_count = count($files['name']);

        $existing_files = glob($upload_dir . '/' . $fam_code_safe . '_' . $doc_type . '_' . date('Y-m-d') . '_*');
        $max_seq = 0;
        if ($existing_files) {
            foreach ($existing_files as $ef) {
                if (preg_match('/_(\d{2})\.[a-zA-Z0-9]+$/', $ef, $m)) {
                    $seq = (int)$m[1];
                    if ($seq > $max_seq) $max_seq = $seq;
                }
            }
        }
        $current_seq = $max_seq + 1;
        $success_count = 0;

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $files['tmp_name'][$i];
                $orig_name = $files['name'][$i];
                $size = $files['size'][$i];
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_exts)) {
                    flash('error', t('نوع ملف غير صالح / Invalid file type') . ': ' . e($orig_name));
                    continue;
                }
                if ($size > $max_size) {
                    flash('error', t('الملف يتجاوز حد 10MB / File exceeds 10MB limit') . ': ' . e($orig_name));
                    continue;
                }

                $seq_str = str_pad($current_seq, 2, '0', STR_PAD_LEFT);
                $new_filename = $fam_code_safe . '_' . $doc_type . '_' . date('Y-m-d') . '_' . $seq_str . '.' . $ext;
                $relative_path = 'storage/documents/' . $fam_code_safe . '/' . $doc_type . '/' . $new_filename;
                $absolute_path = __DIR__ . '/../../' . $relative_path;

                if (move_uploaded_file($tmp_name, $absolute_path)) {
                    dbExecute(
                        "INSERT INTO family_documents (family_id, doc_type, file_path, file_name, file_size, uploaded_by, uploaded_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [$family['id'], $doc_type, $relative_path, $orig_name, $size, Session::getUserId()]
                    );
                    $new_id = (int)(dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'] ?? 0);
                    dbExecute(
                        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                         VALUES (?, 'upload_document', 'family_document', ?, ?, ?, ?)",
                        [Session::getUserId(), $new_id, json_encode(['file_name' => $orig_name, 'doc_type' => $doc_type]), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
                    );
                    $current_seq++;
                    $success_count++;
                } else {
                    flash('error', t('فشل رفع الملف / Failed to upload') . ': ' . e($orig_name));
                }
            }
        }

        if ($success_count > 0) {
            flash('success', t('تم رفع الملفات بنجاح / Files uploaded successfully') . " ($success_count)");
        }
        redirect(url('modules/families/documents.php?id=' . $family['id']));
    } else {
        flash('error', t('لم يتم اختيار أي ملف / No files selected'));
        redirect(url('modules/families/documents.php?id=' . $family['id']));
    }
}

// Handle Reorganize Archive (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorganize_archive'])) {
    verify_csrf();
    if (Session::getUserRole() !== 'admin') {
        flash('error', t('غير مصرح / Unauthorized'));
        redirect(url('modules/families/documents.php?id=' . $family['id']));
    }

    $all_docs = dbFetchAll("SELECT * FROM family_documents");
    $moved_count = 0;
    foreach ($all_docs as $doc) {
        $fam = dbFetchOne("SELECT * FROM families WHERE id = ?", [$doc['family_id']]);
        if (!$fam) continue;

        $f_code = $fam['family_code'] ?? ($fam['code'] ?? 'FAM' . $fam['id']);
        $f_code_safe = preg_replace('/[^A-Za-z0-9_-]/', '', $f_code);
        if (empty($f_code_safe)) $f_code_safe = 'FAM' . $fam['id'];

        $d_type = $doc['doc_type'] ?? 'other';
        if (!array_key_exists($d_type, $doc_types)) $d_type = 'other';

        $new_dir = __DIR__ . '/../../storage/documents/' . $f_code_safe . '/' . $d_type;
        if (!is_dir($new_dir)) {
            mkdir($new_dir, 0777, true);
        }

        $old_relative_path = ltrim($doc['file_path'], '/\\');
        $old_absolute_path = __DIR__ . '/../../' . $old_relative_path;
        if (!file_exists($old_absolute_path)) continue;

        $ext = strtolower(pathinfo($old_absolute_path, PATHINFO_EXTENSION));
        $upload_date = !empty($doc['uploaded_at']) ? date('Y-m-d', strtotime($doc['uploaded_at'])) : date('Y-m-d');

        $existing_files = glob($new_dir . '/' . $f_code_safe . '_' . $d_type . '_' . $upload_date . '_*');
        $max_seq = 0;
        if ($existing_files) {
            foreach ($existing_files as $ef) {
                if (preg_match('/_(\d{2})\.[a-zA-Z0-9]+$/', $ef, $m)) {
                    $seq = (int)$m[1];
                    if ($seq > $max_seq) $max_seq = $seq;
                }
            }
        }
        $new_seq = str_pad($max_seq + 1, 2, '0', STR_PAD_LEFT);
        $new_filename = $f_code_safe . '_' . $d_type . '_' . $upload_date . '_' . $new_seq . '.' . $ext;
        $new_relative_path = 'storage/documents/' . $f_code_safe . '/' . $d_type . '/' . $new_filename;
        $new_absolute_path = __DIR__ . '/../../' . $new_relative_path;

        if ($old_absolute_path !== $new_absolute_path) {
            if (rename($old_absolute_path, $new_absolute_path)) {
                dbExecute("UPDATE family_documents SET file_path = ? WHERE id = ?", [$new_relative_path, $doc['id']]);
                $moved_count++;
            }
        }
    }
    flash('success', t('تم إعادة تنظيم الأرشيف بنجاح / Archive reorganized') . " ($moved_count)");
    redirect(url('modules/families/documents.php?id=' . $family['id']));
}

// Handle Update Document (Rename / Change Type)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_document'])) {
    verify_csrf();
    $doc_id = (int)$_POST['doc_id'];
    $new_type = $_POST['new_doc_type'] ?? 'other';
    $new_name = trim($_POST['new_file_name'] ?? '');

    $doc = dbFetchOne("SELECT * FROM family_documents WHERE id = ?", [$doc_id]);
    if ($doc && (int)$doc['family_id'] === $family['id']) {
        $can_manage = in_array(Session::getUserRole(), ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny'], true);
        if ($can_manage) {
            if (!array_key_exists($new_type, $doc_types)) $new_type = 'other';
            if ($new_name === '') $new_name = $doc['file_name'];

            dbExecute("UPDATE family_documents SET doc_type = ?, file_name = ? WHERE id = ?", [$new_type, $new_name, $doc_id]);

            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'update_document', 'family_document', ?, ?, ?, ?, ?)",
                [Session::getUserId(), $doc_id, json_encode(['file_name' => $doc['file_name'], 'doc_type' => $doc['doc_type']]), json_encode(['file_name' => $new_name, 'doc_type' => $new_type]), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}

            flash('success', t('تم تحديث بيانات الملف / Document details updated'));
        } else {
            flash('error', t('غير مصرح بالتعديل / Unauthorized to update'));
        }
    }
    redirect(url('modules/families/documents.php?id=' . $family['id']));
}

// Handle Delete (Supervisor + Nanny allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    verify_csrf();
    $doc_id = (int)$_POST['doc_id'];
    $doc = dbFetchOne("SELECT * FROM family_documents WHERE id = ?", [$doc_id]);

    if ($doc && (int)$doc['family_id'] === $family['id']) {
        $can_manage = in_array(Session::getUserRole(), ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny'], true);
        if ($can_manage) {
            $absolute_path = __DIR__ . '/../../' . ltrim($doc['file_path'], '/\\');
            if (file_exists($absolute_path)) {
                unlink($absolute_path);
            }
            dbExecute("DELETE FROM family_documents WHERE id = ?", [$doc_id]);
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, ip_address, user_agent)
                 VALUES (?, 'delete_document', 'family_document', ?, ?, ?, ?)",
                [Session::getUserId(), $doc_id, json_encode(['file_name' => $doc['file_name']]), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            flash('success', t('تم حذف الملف / Document deleted'));
        } else {
            flash('error', t('غير مصرح بالحذف / Unauthorized to delete'));
        }
    }
    redirect(url('modules/families/documents.php?id=' . $family['id']));
}

$documents = dbFetchAll("SELECT * FROM family_documents WHERE family_id = ? ORDER BY uploaded_at DESC", [$family['id']]);
$grouped_docs = [];
foreach ($documents as $doc) {
    $type = $doc['doc_type'] ?? $doc['type'] ?? 'other';
    $grouped_docs[$type][] = $doc;
}

/* Session-7 FIX: short header title (no duplication with the in-page heading) */
$pageTitle = 'مستندات الأسرة';
$active = 'families';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <?php include __DIR__ . '/../../includes/alerts.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">
            <i class="fas fa-folder-open me-2 text-primary"></i>
            <?php echo t('أرشيف مستندات الأسرة / Family Document Archive'); ?>
        </h3>
        <a href="<?php echo url('modules/families/view.php?id=' . $family['id']); ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-<?php echo (defined('AK_DIR') && AK_DIR === 'rtl') ? 'right' : 'left'; ?> me-1"></i> <?php echo t('العودة للأسرة / Back to Family'); ?>
        </a>
    </div>

    <!-- Session-7 FIX: family NAME on top, family CODE underneath -->
    <div class="card mb-4 shadow-sm border-primary">
        <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0 me-3">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fas fa-users fa-lg"></i>
                </div>
            </div>
            <div>
                <h5 class="mb-0"><?php echo e($family['mother_name'] ?? ($family['father_name'] ?? t('غير معروف / Unknown'))); ?></h5>
                <small class="text-muted"><?php echo e($family['family_code'] ?? ($family['code'] ?? 'FAM-' . $family['id'])); ?></small>
            </div>
        </div>
    </div>

    <?php if (Session::getUserRole() === 'admin'): ?>
    <div class="card mb-4 border-warning">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 text-warning"><i class="fas fa-tools me-2"></i><?php echo t('إعادة تنظيم الأرشيف / Reorganize Archive'); ?></h5>
                <small class="text-muted"><?php echo t('نقل وإعادة تسمية جميع الملفات لتطابق الاتفاقية الجديدة / Move and rename all files to match the new convention.'); ?></small>
            </div>
            <form method="POST" action="" class="mb-0">
                <?php echo csrf_field(); ?>
                <button type="submit" name="reorganize_archive" class="btn btn-warning" onclick="return confirm('<?php echo t('هل أنت متأكد؟ قد تستغرق العملية بعض الوقت. / Are you sure? This may take some time.'); ?>');">
                    <i class="fas fa-sync-alt me-1"></i> <?php echo t('إعادة تنظيم / Reorganize'); ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-upload me-2"></i><?php echo t('رفع ملفات جديدة / Upload New Documents'); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?php echo t('نوع الملف / Document Type'); ?></label>
                        <select name="doc_type" class="form-select" required>
                            <?php foreach ($doc_types as $slug => $label): ?>
                                <option value="<?php echo $slug; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label"><?php echo t('اختر الملفات (PDF, JPG, PNG - حد أقصى 10MB) / Select Files (PDF, JPG, PNG - Max 10MB)'); ?></label>
                        <input type="file" name="documents[]" class="form-control" multiple required accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="upload_documents" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt me-1"></i> <?php echo t('رفع / Upload'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i><?php echo t('أرشيف المستندات / Document Archive'); ?></h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($grouped_docs)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3"></i>
                    <p><?php echo t('لا توجد مستندات حتى الآن / No documents yet.'); ?></p>
                </div>
            <?php else: ?>
                <div class="accordion" id="docsAccordion">
                    <?php $idx = 0; foreach ($grouped_docs as $type => $docs): $idx++; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $idx; ?>">
                                <button class="accordion-button <?php echo $idx > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $idx; ?>">
                                    <strong><?php echo $doc_types[$type] ?? $type; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo count($docs); ?></span>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $idx; ?>" class="accordion-collapse collapse <?php echo $idx === 1 ? 'show' : ''; ?>" data-bs-parent="#docsAccordion">
                                <div class="accordion-body p-0">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th><?php echo t('اسم الملف / File Name'); ?></th>
                                                <th><?php echo t('الحجم / Size'); ?></th>
                                                <th><?php echo t('تاريخ الرفع / Upload Date'); ?></th>
                                                <th class="text-end"><?php echo t('إجراءات / Actions'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($docs as $doc): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas <?php echo pathinfo($doc['file_path'], PATHINFO_EXTENSION) === 'pdf' ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary'; ?> me-2"></i>
                                                        <?php echo e($doc['file_name']); ?>
                                                        <br><small class="text-muted"><?php echo e(basename($doc['file_path'])); ?></small>
                                                    </td>
                                                    <td><?php echo number_format($doc['file_size'] / 1024, 1) . ' KB'; ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($doc['uploaded_at'])); ?></td>
                                                    <td class="text-end">
                                                        <a href="<?php echo APP_URL; ?>modules/families/document_file.php?id=<?php echo (int)$doc['id']; ?>&mode=view" target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="<?php echo APP_URL; ?>modules/families/document_file.php?id=<?php echo (int)$doc['id']; ?>&mode=download" class="btn btn-sm btn-outline-secondary me-1">
                                                            <i class="fas fa-download"></i>
                                                        </a>

                                                        <button class="btn btn-sm btn-outline-success me-1" type="button" data-bs-toggle="collapse" data-bs-target="#editDoc<?php echo $doc['id']; ?>" title="<?php echo t('تعديل / Edit'); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                        <?php
                                                        $can_manage = in_array(Session::getUserRole(), ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny'], true);
                                                        if ($can_manage): ?>
                                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('<?php echo t('هل أنت متأكد من الحذف؟ / Confirm delete?'); ?>');">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                                                <button type="submit" name="delete_document" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <div class="collapse mt-2 text-start" id="editDoc<?php echo $doc['id']; ?>">
                                                            <form method="POST" action="" class="row g-2 align-items-end bg-light p-2 rounded border">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                                                <div class="col-md-5">
                                                                    <label class="form-label small mb-1"><?php echo t('نوع الملف / Type'); ?></label>
                                                                    <select name="new_doc_type" class="form-select form-select-sm">
                                                                        <?php foreach ($doc_types as $slug => $label): ?>
                                                                            <option value="<?php echo $slug; ?>" <?php echo ($doc['doc_type'] ?? 'other') === $slug ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-5">
                                                                    <label class="form-label small mb-1"><?php echo t('اسم الملف / Name'); ?></label>
                                                                    <input type="text" name="new_file_name" class="form-control form-control-sm" value="<?php echo e($doc['file_name']); ?>">
                                                                </div>
                                                                <div class="col-md-2">
                                                                    <button type="submit" name="update_document" class="btn btn-sm btn-success w-100"><?php echo t('حفظ / Save'); ?></button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>