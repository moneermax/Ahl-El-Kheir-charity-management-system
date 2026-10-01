<?php
// Accounting transaction FM review helpers.
// Procedural PHP only. Ordinary transactions follow:
// pending_fm_review -> returned -> pending_fm_review -> posted
// pending_fm_review -> cancelled
// Posted transactions remain immutable through the normal edit path.

if (!function_exists('ak_transaction_review_is_fm')) {
function ak_transaction_review_is_fm(string $role): bool {
    return in_array($role, ['financial_manager', 'fm', 'finance', 'admin'], true);
}
}

if (!function_exists('ak_transaction_review_receipt_refs')) {
function ak_transaction_review_receipt_refs(string $path): int {
    if ($path === '') return 0;
    $count = 0;
    try {
        $r = dbFetchOne("SELECT COUNT(*) c FROM transactions WHERE receipt_path = ? OR unified_receipt_path = ?", [$path, $path]);
        $count += (int)($r['c'] ?? 0);
    } catch (Throwable $e) {}
    try {
        $r = dbFetchOne("SELECT COUNT(*) c FROM sponsor_payments WHERE receipt_file_path = ? OR unified_receipt_path = ?", [$path, $path]);
        $count += (int)($r['c'] ?? 0);
    } catch (Throwable $e) {}
    return $count;
}
}

if (!function_exists('ak_transaction_review_delete_receipt_if_unreferenced')) {
function ak_transaction_review_delete_receipt_if_unreferenced(?string $path): void {
    $path = trim((string)$path);
    if ($path === '' || ak_transaction_review_receipt_refs($path) > 0) return;
    $root = dirname(__DIR__, 2);
    $relative = ltrim(str_replace(['\\', '/'], '/', $path), '/');
    $full = $root . '/' . $relative;
    if (is_file($full)) @unlink($full);
}
}

if (!function_exists('ak_transaction_review_notify_event')) {
function ak_transaction_review_notify_event(int $userId, string $title, string $body, string $link, ?int $referenceId = null, ?string $referenceType = null): void {
    if ($userId <= 0 || trim($title) === '') return;

    try {
        // Newer notification schemas may have workflow reference columns.
        // If they are unavailable in an existing installation, fall back to
        // the long-standing notification columns so business notifications
        // are still delivered instead of being silently discarded.
        if ($referenceId !== null && trim((string)$referenceType) !== '') {
            try {
                $existing = dbFetchOne(
                    "SELECT id FROM notifications
                     WHERE recipient_user_id = ?
                       AND reference_id = ?
                       AND reference_type = ?
                       AND is_read = 0
                     LIMIT 1",
                    [$userId, $referenceId, $referenceType]
                );

                if (!$existing) {
                    dbExecute(
                        "INSERT INTO notifications (recipient_user_id, title, body, link, type, reference_id, reference_type, is_read, created_at)
                         VALUES (?, ?, ?, ?, 'workflow', ?, ?, 0, NOW())",
                        [$userId, $title, $body, $link, $referenceId, $referenceType]
                    );
                }
                return;
            } catch (Throwable $referenceError) {
                // Fall through to the legacy notification shape below.
            }
        }

        // Suppress duplicates only while an equivalent notification is still
        // unread. Once the FM has read it, a later workflow event must create
        // a fresh notification so the new pending transaction is visible.
        $existing = dbFetchOne(
            "SELECT id FROM notifications
             WHERE recipient_user_id = ?
               AND title = ?
               AND link = ?
               AND is_read = 0
             LIMIT 1",
            [$userId, $title, $link]
        );

        if (!$existing) {
            dbExecute(
                "INSERT INTO notifications (recipient_user_id, title, body, link, is_read, created_at)
                 VALUES (?, ?, ?, ?, 0, NOW())",
                [$userId, $title, $body, $link]
            );
        }
    } catch (Throwable $e) {
        // Notification delivery must never roll back an already-completed business action.
    }
}
}

if (!function_exists('ak_transaction_review_notify_user')) {
function ak_transaction_review_notify_user(int $userId, string $title, string $body, string $link): void {
    ak_transaction_review_notify_event($userId, $title, $body, $link);
}
}

if (!function_exists('ak_transaction_review_notify_fm')) {
function ak_transaction_review_notify_fm(int $count, string $creatorName = ''): void {
    if ($count <= 0) return;

    try {
        $users = dbFetchAll(
            "SELECT u.id
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.code IN ('financial_manager', 'fm', 'finance')
               AND u.is_active = 1"
        );

        foreach ($users as $u) {
            ak_transaction_review_notify_user(
                (int)$u['id'],
                'دفعات بانتظار المراجعة المالية',
                'لديك ' . $count . ' دفعة بانتظار الاعتماد' .
                    ($creatorName !== '' ? ' من ' . $creatorName : '') . '.',
                APP_URL . 'modules/accounting/fm_transaction_review.php'
            );
        }
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_notify_fm_event')) {
function ak_transaction_review_notify_fm_event(int $referenceId, string $referenceType, string $title, string $body, string $link, ?int $excludeUserId = null): void {
    if ($referenceId <= 0 || trim($referenceType) === '' || trim($title) === '') return;

    try {
        $users = dbFetchAll(
            "SELECT u.id
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.code IN ('financial_manager', 'fm', 'finance')
               AND u.is_active = 1"
        );

        foreach ($users as $u) {
            $userId = (int)$u['id'];
            if ($excludeUserId !== null && $userId === $excludeUserId) continue;
            ak_transaction_review_notify_event($userId, $title, $body, $link, $referenceId, $referenceType);
        }
    } catch (Throwable $e) {
        // Notification delivery must never roll back the completed payment workflow.
    }
}
}

/**
 * Project approval notifications are registered at request shutdown so they
 * run only after the project action has completed (including its transaction).
 * This keeps notification delivery separate from the accounting/business
 * transition while covering all project approval return paths consistently.
 */
if (!function_exists('ak_register_project_approval_notifications')) {
function ak_register_project_approval_notifications(): void {
    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    if (!str_ends_with($path, '/modules/projects/view.php')) return;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $action = (string)($_POST['action'] ?? '');
    if (!in_array($action, ['fm_reject_project', 'approve_project', 'reject_project'], true)) return;

    $projectId = (int)($_POST['project_id'] ?? $_GET['id'] ?? 0);
    if ($projectId <= 0) return;

    register_shutdown_function(static function () use ($projectId, $action): void {
        try {
            $approval = dbFetchOne(
                'SELECT pa.approval_status, pa.submitted_by, op.name, op.project_code
                 FROM project_approval pa
                 JOIN other_projects op ON op.id = pa.project_id
                 WHERE pa.project_id = ?',
                [$projectId]
            );
            if (!$approval) return;

            $name = (string)($approval['name'] ?? '');
            $code = (string)($approval['project_code'] ?? '');
            $label = 'المشروع «' . $name . '» (' . $code . ')';
            $link = APP_URL . 'modules/projects/view.php?id=' . $projectId;
            $submittedBy = (int)($approval['submitted_by'] ?? 0);

            if ($action === 'fm_reject_project' && $approval['approval_status'] === 'rejected') {
                if ($submittedBy > 0) {
                    $reason = (string)(dbFetchOne('SELECT fm_rejection_reason FROM project_approval WHERE project_id = ?', [$projectId])['fm_rejection_reason'] ?? '');
                    ak_transaction_review_notify_event(
                        $submittedBy,
                        'إعادة المشروع للتعديل',
                        $label . ' تم رفضه مالياً وإعادته إلى مدير المشاريع للتعديل.' . ($reason !== '' ? ' سبب الرفض: ' . $reason : ''),
                        $link,
                        $projectId,
                        'project_fm_rejection'
                    );
                }
                return;
            }

            if ($action === 'approve_project' && $approval['approval_status'] === 'approved') {
                // Final GM/VGM approval means the FM can now execute the actual
                // payment evidence step. Notify every active FM.
                ak_transaction_review_notify_fm_event(
                    $projectId,
                    'project_gm_approval',
                    'المشروع معتمد نهائياً — بانتظار تنفيذ الصرف',
                    $label . ' تم اعتماده نهائياً من الإدارة التنفيذية. يمكن للمدير المالي الآن تنفيذ إجراءات الصرف وإثبات الدفع.',
                    $link
                );

                // The PM also needs confirmation because the project may now
                // proceed to actual execution after payment release.
                if ($submittedBy > 0) {
                    ak_transaction_review_notify_event(
                        $submittedBy,
                        'تم اعتماد المشروع نهائياً',
                        $label . ' تم اعتماده نهائياً ويمكن الانتقال إلى إجراءات التنفيذ بعد استكمال الصرف.',
                        $link,
                        $projectId,
                        'project_gm_approval_pm'
                    );
                }
                return;
            }

            if ($action === 'reject_project' && $approval['approval_status'] === 'rejected') {
                if ($submittedBy > 0) {
                    $reason = (string)(dbFetchOne('SELECT rejection_reason FROM project_approval WHERE project_id = ?', [$projectId])['rejection_reason'] ?? '');
                    ak_transaction_review_notify_event(
                        $submittedBy,
                        'إعادة المشروع للتعديل',
                        $label . ' تم رفضه نهائياً وإعادته إلى مدير المشاريع للتعديل.' . ($reason !== '' ? ' سبب الرفض: ' . $reason : ''),
                        $link,
                        $projectId,
                        'project_gm_rejection'
                    );
                }
            }
        } catch (Throwable $notificationError) {
            // Never change the completed project action because notification delivery failed.
        }
    });
}
}
ak_register_project_approval_notifications();

if (!function_exists('ak_transaction_review_audit')) {
function ak_transaction_review_audit(int $userId, string $action, int $entityId, $old, $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                   VALUES (?, ?, 'transactions', ?, ?, ?, ?, ?)",
            [$userId, $action, $entityId,
             json_encode($old, JSON_UNESCAPED_UNICODE),
             json_encode($new, JSON_UNESCAPED_UNICODE),
             $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_creator')) {
function ak_transaction_review_creator(int $transactionId): ?array {
    return dbFetchOne("SELECT t.*, u.full_name AS creator_name FROM transactions t LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?", [$transactionId]);
}
}

if (!function_exists('ak_transaction_review_validate_returned_owner')) {
function ak_transaction_review_validate_returned_owner(int $transactionId, int $userId): ?array {
    $t = ak_transaction_review_creator($transactionId);
    if (!$t || $t['status'] !== 'returned' || (int)$t['created_by'] !== $userId) return null;
    return $t;
}
}
