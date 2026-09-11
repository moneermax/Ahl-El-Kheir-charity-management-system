<?php
// modules/accounting/serve_receipt.php — Secure receipt viewer
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never show errors in file output

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_outflows.php';

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Unauthorized');
}

$uid = (int)Session::getUserId();
$role = (string)Session::getUserRole();
$allowed = in_array($role, ['admin', 'financial_manager', 'general_manager', 'vice_general_manager', 'accountant', 'accountant_staff'], true);

$id = (int)($_GET['id'] ?? 0);
$kind = (string)($_GET['kind'] ?? 'batch');

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

if ($kind === 'item') {
    $item = ak_out_row('disbursement_items', $id);
    $row = $item ? ak_out_row('monthly_disbursements', (int)$item['disbursement_id']) : null;
    $receiptPath = trim((string)($item['receipt_file_path'] ?? ''));
} else {
    $kind = 'batch';
    $row = ak_out_row('monthly_disbursements', $id);
    $receiptPath = trim((string)($row['receipt_file_path'] ?? ''));
}

if (!$row || !((int)$row['nanny_id'] === $uid || $allowed)) {
    http_response_code(403);
    exit('403');
}

// Resolve the stored path here so stale/missing files are handled by the
// application instead of falling through to a browser/server 404.
$base = realpath(dirname(__DIR__, 2));
$full = false;
if ($receiptPath !== '' && $base !== false) {
    $candidate = realpath($base . '/' . ltrim($receiptPath, '/\\'));
    if ($candidate !== false && strpos($candidate, $base . DIRECTORY_SEPARATOR) === 0 && is_file($candidate)) {
        $full = $candidate;
    }
}

if ($receiptPath === '' || $full === false) {
    $title = $kind === 'batch' ? 'لا يوجد إيصال نهائي للدفعة' : 'لا يوجد إيصال لهذا السجل';
    $message = $kind === 'batch'
        ? 'لا يوجد ملف إيصال نهائي متاح لهذه الدفعة حالياً. إيصالات الأسر الفردية لا تُستخدم كبديل عن إيصال الدفعة.'
        : 'لا يوجد ملف إيصال متاح لهذا السجل حالياً.';
    $back = APP_URL . 'modules/accounting/disbursements.php';
    if ($kind === 'batch') {
        $back .= '?view=' . (int)$id;
    }
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo e($title); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
        <style>body{background:#f8f9fa}.receipt-message{max-width:650px;margin:12vh auto;padding:2rem}</style>
    </head>
    <body>
    <script>
    (function () {
        var title = <?php echo json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var message = <?php echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var openerWindow = window.opener;

        if (openerWindow && !openerWindow.closed) {
            try {
                var doc = openerWindow.document;
                var existing = doc.getElementById('receiptMissingModal');
                if (existing) {
                    existing.remove();
                }

                var modal = doc.createElement('div');
                modal.id = 'receiptMissingModal';
                modal.className = 'modal fade';
                modal.tabIndex = -1;
                modal.setAttribute('aria-hidden', 'true');
                modal.innerHTML =
                    '<div class="modal-dialog modal-dialog-centered">' +
                        '<div class="modal-content border-warning">' +
                            '<div class="modal-header">' +
                                '<h5 class="modal-title"><i class="fas fa-triangle-exclamation text-warning me-2"></i>' + title + '</h5>' +
                                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>' +
                            '</div>' +
                            '<div class="modal-body text-center py-4">' +
                                '<div class="fs-1 text-warning mb-3"><i class="fas fa-file-circle-xmark"></i></div>' +
                                '<p class="text-muted mb-0">' + message + '</p>' +
                            '</div>' +
                            '<div class="modal-footer justify-content-center">' +
                                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                doc.body.appendChild(modal);

                if (openerWindow.bootstrap && openerWindow.bootstrap.Modal) {
                    var instance = openerWindow.bootstrap.Modal.getOrCreateInstance(modal);
                    instance.show();
                    modal.addEventListener('hidden.bs.modal', function () {
                        modal.remove();
                    }, { once: true });
                } else {
                    openerWindow.alert(title + '\n\n' + message);
                    modal.remove();
                }

                window.close();
                return;
            } catch (e) {
                // Fall through to the standalone application message below.
            }
        }
    }());
    </script>
        <div class="container">
            <div class="card receipt-message shadow-sm border-warning">
                <div class="card-body text-center">
                    <div class="fs-1 text-warning mb-3">&#9888;</div>
                    <h4 class="mb-3"><?php echo e($title); ?></h4>
                    <p class="text-muted mb-4"><?php echo e($message); ?></p>
                    <a href="<?php echo e($back); ?>" class="btn btn-secondary">العودة</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

ak_out_serve_receipt($id, $uid, $allowed, $kind);
