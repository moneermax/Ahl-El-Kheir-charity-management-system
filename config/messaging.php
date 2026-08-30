<?php
/**
 * Internal Messaging Service Layer
 * Plan 2 (Forward-Compatible with Plan 3)
 * 
 * Location: config/messaging.php
 */

// ---------------------------------------------------------------------------
// AUTO-DETECT PDO CONNECTION
// ---------------------------------------------------------------------------
// If your project uses a different variable or function, add it below.
// ---------------------------------------------------------------------------

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/database.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($db) && $db instanceof PDO) {
        $pdo = $db;
    } elseif (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } elseif (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
        $pdo = $GLOBALS['db'];
    } elseif (function_exists('db')) {
        $pdo = db();
    } elseif (function_exists('getDB')) {
        $pdo = getDB();
    } elseif (function_exists('get_db')) {
        $pdo = get_db();
    } else {
        die('
            <div style="direction:rtl;text-align:right;padding:20px;font-family:tahoma;">
                <h3>خطأ في الإعداد</h3>
                <p>لم يتم العثور على اتصال PDO. يرجى تشغيل ملف <code>test_db_vars.php</code> وإخباري بالنتيجة.</p>
                <p>PDO connection not found. Please run <code>test_db_vars.php</code> and tell me the output.</p>
            </div>
        ');
    }
}

require_once __DIR__ . '/session.php';

// ============================================================================
// SYSTEM NOTIFICATIONS (uses existing `notifications` table)
// ============================================================================

/**
 * Send a system-generated notification to a specific user.
 * 
 * @param int    $user_id        Target user
 * @param string $title          Short title (Arabic or English)
 * @param string $message        Full body text
 * @param string $type           info | success | warning | danger
 * @param string $reference_type Optional linked entity type (e.g., 'disbursement', 'family')
 * @param int    $reference_id   Optional linked entity ID
 * @return int|false             Inserted notification ID or false
 */
function send_system_notification($user_id, $title, $message, $type = 'info', $reference_type = null, $reference_id = null) {
    global $pdo;

    $valid_types = ['info', 'success', 'warning', 'danger'];
    if (!in_array($type, $valid_types, true)) {
        $type = 'info';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
                (user_id, title, message, type, reference_type, reference_id, is_read, created_at)
            VALUES 
                (:user_id, :title, :message, :type, :ref_type, :ref_id, 0, NOW())
        ");
        $stmt->execute([
            ':user_id'  => $user_id,
            ':title'    => $title,
            ':message'  => $message,
            ':type'     => $type,
            ':ref_type' => $reference_type,
            ':ref_id'   => $reference_id
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('send_system_notification failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// USER MESSAGES (uses new `messages` table)
// ============================================================================

/**
 * Send a direct message from one user to another.
 * 
 * @param int    $sender_id         Sender user ID
 * @param int    $recipient_user_id Target user ID
 * @param string $subject           Message subject
 * @param string $body              Message body
 * @param int    $parent_id         NULL for new thread, or ID of message being replied to
 * @param string $reference_type    Optional linked entity type
 * @param int    $reference_id      Optional linked entity ID
 * @return int|false                Inserted message ID or false
 */
function send_user_message($sender_id, $recipient_user_id, $subject, $body, $parent_id = null, $reference_type = null, $reference_id = null) {
    global $pdo;

    if ($sender_id == $recipient_user_id) {
        return false; // Prevent self-messaging
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, recipient_user_id, recipient_role, subject, body, parent_id, reference_type, reference_id, is_urgent, created_at)
            VALUES 
                (:sender_id, :recipient_id, NULL, :subject, :body, :parent_id, :ref_type, :ref_id, 0, NOW())
        ");
        $stmt->execute([
            ':sender_id'    => $sender_id,
            ':recipient_id' => $recipient_user_id,
            ':subject'      => $subject,
            ':body'         => $body,
            ':parent_id'    => $parent_id,
            ':ref_type'     => $reference_type,
            ':ref_id'       => $reference_id
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('send_user_message failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Broadcast a message to all users holding a specific role.
 * Each user in that role sees it individually; read receipts are per-user.
 * 
 * @param int    $sender_id      Sender user ID
 * @param string $recipient_role Role code from `roles` table (e.g., 'nanny', 'supervisor')
 * @param string $subject        Message subject
 * @param string $body           Message body
 * @param string $reference_type Optional linked entity type
 * @param int    $reference_id   Optional linked entity ID
 * @return int|false             Inserted message ID or false
 */
function broadcast_to_role($sender_id, $recipient_role, $subject, $body, $reference_type = null, $reference_id = null) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, recipient_user_id, recipient_role, subject, body, parent_id, reference_type, reference_id, is_urgent, created_at)
            VALUES 
                (:sender_id, NULL, :role, :subject, :body, NULL, :ref_type, :ref_id, 0, NOW())
        ");
        $stmt->execute([
            ':sender_id' => $sender_id,
            ':role'      => $recipient_role,
            ':subject'   => $subject,
            ':body'      => $body,
            ':ref_type'  => $reference_type,
            ':ref_id'    => $reference_id
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('broadcast_to_role failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// INBOX QUERIES
// ============================================================================

/**
 * Get a unified feed of recent notifications + messages for the header bell dropdown.
 * 
 * @param int    $user_id  Current user ID
 * @param string $user_role Current user role code
 * @param int    $limit    Max rows to return
 * @param int    $offset   Pagination offset
 * @return array           Mixed list of notifications and messages
 */
function get_unified_inbox($user_id, $user_role, $limit = 10, $offset = 0) {
    global $pdo;

    $sql = "
        (
            SELECT 
                n.id,
                n.title AS subject,
                LEFT(n.message, 120) AS body_preview,
                n.type AS item_type_label,
                n.reference_type,
                n.reference_id,
                n.is_read,
                n.created_at,
                'notification' AS item_type,
                NULL AS sender_id,
                NULL AS sender_name,
                NULL AS parent_id
            FROM notifications n
            WHERE n.user_id = :uid1
        )
        UNION ALL
        (
            SELECT 
                m.id,
                m.subject,
                LEFT(m.body, 120) AS body_preview,
                'message' AS item_type_label,
                m.reference_type,
                m.reference_id,
                CASE WHEN mr.read_at IS NOT NULL THEN 1 ELSE 0 END AS is_read,
                m.created_at,
                'message' AS item_type,
                m.sender_id,
                u.name AS sender_name,
                m.parent_id
            FROM messages m
            LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid2
            LEFT JOIN users u ON u.id = m.sender_id
            WHERE 
                (m.recipient_user_id = :uid3 OR (m.recipient_role = :role AND m.sender_id != :uid4))
        )
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':uid1', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':role', $user_role, PDO::PARAM_STR);
        $stmt->bindValue(':uid4', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_unified_inbox failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get system notifications only (for the Notifications tab).
 * 
 * @param int    $user_id
 * @param string $filter   'all' | 'unread'
 * @param int    $limit
 * @param int    $offset
 * @return array
 */
function get_notifications($user_id, $filter = 'all', $limit = 20, $offset = 0) {
    global $pdo;

    $where = ($filter === 'unread') ? 'AND n.is_read = 0' : '';

    try {
        $stmt = $pdo->prepare("
            SELECT 
                n.*,
                'notification' AS item_type
            FROM notifications n
            WHERE n.user_id = :user_id {$where}
            ORDER BY n.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_notifications failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get user messages only (for the Messages tab).
 * Includes direct messages and role broadcasts.
 * 
 * @param int    $user_id
 * @param string $user_role
 * @param string $filter   'all' | 'unread'
 * @param int    $limit
 * @param int    $offset
 * @return array
 */
function get_messages($user_id, $user_role, $filter = 'all', $limit = 20, $offset = 0) {
    global $pdo;

    $readFilter = ($filter === 'unread') ? 'AND mr.read_at IS NULL' : '';

    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.name AS sender_name,
                CASE WHEN mr.read_at IS NOT NULL THEN 1 ELSE 0 END AS is_read,
                'message' AS item_type
            FROM messages m
            LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid1
            LEFT JOIN users u ON u.id = m.sender_id
            WHERE 
                (m.recipient_user_id = :uid2 OR (m.recipient_role = :role AND m.sender_id != :uid3))
                {$readFilter}
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':uid1', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':role', $user_role, PDO::PARAM_STR);
        $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_messages failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get messages sent by the current user (for the Sent tab).
 * 
 * @param int $user_id
 * @param int $limit
 * @param int $offset
 * @return array
 */
function get_sent_messages($user_id, $limit = 20, $offset = 0) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.name AS recipient_name,
                'sent' AS item_type
            FROM messages m
            LEFT JOIN users u ON u.id = m.recipient_user_id
            WHERE m.sender_id = :user_id
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_sent_messages failed: ' . $e->getMessage());
        return [];
    }
}

// ============================================================================
// READ TRACKING & COUNTERS
// ============================================================================

/**
 * Total unread count for the header badge (notifications + messages).
 * 
 * @param int    $user_id
 * @param string $user_role
 * @return int
 */
function get_unread_count($user_id, $user_role) {
    global $pdo;

    try {
        // Unread notifications
        $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt1->execute([':uid' => $user_id]);
        $notifCount = (int) $stmt1->fetchColumn();

        // Unread messages (direct + broadcast)
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) FROM messages m
            LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
            WHERE 
                (m.recipient_user_id = :uid2 OR (m.recipient_role = :role AND m.sender_id != :uid3))
                AND mr.id IS NULL
        ");
        $stmt2->execute([
            ':uid'   => $user_id,
            ':uid2'  => $user_id,
            ':role'  => $user_role,
            ':uid3'  => $user_id
        ]);
        $msgCount = (int) $stmt2->fetchColumn();

        return $notifCount + $msgCount;
    } catch (PDOException $e) {
        error_log('get_unread_count failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Mark one or all notifications as read for a user.
 * 
 * @param int      $user_id
 * @param int|null $notification_id  NULL = mark ALL as read
 * @return bool
 */
function mark_notification_read($user_id, $notification_id = null) {
    global $pdo;

    try {
        if ($notification_id !== null) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $notification_id, ':uid' => $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
            $stmt->execute([':uid' => $user_id]);
        }
        return true;
    } catch (PDOException $e) {
        error_log('mark_notification_read failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark a message as read for a user. Uses INSERT IGNORE so duplicates are harmless.
 * 
 * @param int $user_id
 * @param int $message_id
 * @return bool
 */
function mark_message_read($user_id, $message_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
            VALUES (:msg_id, :uid, NOW())
        ");
        $stmt->execute([':msg_id' => $message_id, ':uid' => $user_id]);
        return true;
    } catch (PDOException $e) {
        error_log('mark_message_read failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// THREADS & DETAIL VIEWS
// ============================================================================

/**
 * Get a single message by ID with sender info and read status for the viewer.
 * 
 * @param int $message_id
 * @param int $viewer_user_id
 * @return array|null
 */
function get_message_by_id($message_id, $viewer_user_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.name AS sender_name,
                CASE WHEN mr.read_at IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM messages m
            LEFT JOIN users u ON u.id = m.sender_id
            LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
            WHERE m.id = :msg_id
            LIMIT 1
        ");
        $stmt->execute([':msg_id' => $message_id, ':uid' => $viewer_user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('get_message_by_id failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get full conversation thread starting from a root message.
 * Also marks the root as read for the viewer.
 * 
 * @param int $message_id  Any message in the thread (reply or root)
 * @param int $viewer_user_id
 * @return array           ['root' => array, 'replies' => array]
 */
function get_message_thread($message_id, $viewer_user_id) {
    global $pdo;

    try {
        // 1. Find the root message
        $currentId = $message_id;
        for ($i = 0; $i < 50; $i++) { // Safety limit against loops
            $stmt = $pdo->prepare("SELECT parent_id FROM messages WHERE id = :id");
            $stmt->execute([':id' => $currentId]);
            $parentId = $stmt->fetchColumn();
            if ($parentId === null || $parentId == 0) {
                break;
            }
            $currentId = (int) $parentId;
        }
        $rootId = $currentId;

        // 2. Mark root as read for viewer
        mark_message_read($viewer_user_id, $rootId);

        // 3. Fetch root with sender
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name
            FROM messages m
            LEFT JOIN users u ON u.id = m.sender_id
            WHERE m.id = :root_id
            LIMIT 1
        ");
        $stmt->execute([':root_id' => $rootId]);
        $root = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$root) {
            return ['root' => null, 'replies' => []];
        }

        // 4. Fetch all replies
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name
            FROM messages m
            LEFT JOIN users u ON u.id = m.sender_id
            WHERE m.parent_id = :root_id
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([':root_id' => $rootId]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Mark all replies as read too
        foreach ($replies as $reply) {
            mark_message_read($viewer_user_id, $reply['id']);
        }

        return ['root' => $root, 'replies' => $replies];
    } catch (PDOException $e) {
        error_log('get_message_thread failed: ' . $e->getMessage());
        return ['root' => null, 'replies' => []];
    }
}

/**
 * Get a list of users the current user has had conversations with,
 * plus the latest message preview. Useful for a conversation sidebar.
 * 
 * @param int $user_id
 * @param int $limit
 * @return array
 */
function get_conversation_list($user_id, $limit = 20) {
    global $pdo;

    try {
        // Direct conversations: people who sent to me or I sent to
        $stmt = $pdo->prepare("
            SELECT 
                other_user_id,
                other_name,
                last_subject,
                last_body,
                last_at
            FROM (
                SELECT 
                    CASE WHEN m.sender_id = :uid THEN m.recipient_user_id ELSE m.sender_id END AS other_user_id,
                    u.name AS other_name,
                    m.subject AS last_subject,
                    LEFT(m.body, 80) AS last_body,
                    m.created_at AS last_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY CASE WHEN m.sender_id = :uid2 THEN m.recipient_user_id ELSE m.sender_id END 
                        ORDER BY m.created_at DESC
                    ) AS rn
                FROM messages m
                LEFT JOIN users u ON u.id = CASE WHEN m.sender_id = :uid3 THEN m.recipient_user_id ELSE m.sender_id END
                WHERE 
                    (m.recipient_user_id = :uid4 AND m.sender_id != :uid5)
                    OR (m.sender_id = :uid6 AND m.recipient_user_id IS NOT NULL)
            ) ranked
            WHERE rn = 1
            ORDER BY last_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid4', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid5', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid6', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_conversation_list failed: ' . $e->getMessage());
        return [];
    }
}

// ============================================================================
// RECIPIENT HELPERS (for Compose UI)
// ============================================================================

/**
 * Get all active users suitable for messaging.
 * Excludes the requesting user.
 * 
 * @param int    $exclude_user_id
 * @param string $role_filter     Optional role code to filter
 * @return array
 */
function get_messaging_users($exclude_user_id, $role_filter = null) {
    global $pdo;

    $roleSql = ($role_filter !== null) ? "AND role = :role" : "";

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, username, role, is_active
            FROM users
            WHERE id != :exclude AND is_active = 1 {$roleSql}
            ORDER BY role, name
        ");
        $params = [':exclude' => $exclude_user_id];
        if ($role_filter !== null) {
            $params[':role'] = $role_filter;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_messaging_users failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get distinct active roles for broadcast dropdown.
 * 
 * @return array
 */
function get_broadcast_roles() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.role 
            FROM users u
            WHERE u.is_active = 1
            ORDER BY u.role
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('get_broadcast_roles failed: ' . $e->getMessage());
        return [];
    }
}