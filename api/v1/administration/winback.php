<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/administration/lib_winback.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employee_incentives.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
$allowedRoles = ['admin', 'general_manager', 'vice_general_manager', 'administration'];
if (!in_array((string)$user['role_code'], $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Sponsor win-back is not available for this role.']]);
}

function ak_api_winback_audit(int $userId, string $action, int $campaignId, array $values = []): void
{
    try {
        dbExecute(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, 'winback', ?, NULL, ?, ?, ?)",
            [
                $userId,
                $action,
                $campaignId,
                json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]
        );
    } catch (Throwable $exception) {
        error_log('Mobile win-back audit write failed: ' . $exception->getMessage());
    }
}

function ak_api_winback_not_found(): never
{
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'The requested win-back record was not found.']]);
}

function ak_api_winback_invalid_transition(): never
{
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'invalid_transition', 'message' => 'This win-back action is not valid for the current status.']]);
}

if ($method === 'GET') {
    $viewId = filter_var($_GET['view_id'] ?? 0, FILTER_VALIDATE_INT);
    if ($viewId === false || $viewId < 0) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_view_id', 'message' => 'The selected case reference is invalid.']]);
    }

    try {
        $reactivators = dbFetchAll(
            "SELECT e.user_id, e.employee_code, e.full_name
             FROM employees e
             JOIN users u ON u.id = e.user_id AND u.is_active = 1
             JOIN hr_employment_states es ON es.id = e.employment_state_id
                AND es.category = 'working' AND es.is_active = 1
             WHERE e.user_id IS NOT NULL AND e.user_id > 0
               AND NOT EXISTS (
                   SELECT 1 FROM employees e2
                   WHERE e2.user_id = e.user_id AND e2.id <> e.id
               )
             ORDER BY e.full_name, e.id"
        );

        $queue = dbFetchAll(
            "SELECT s.id, s.full_name, s.phone, s.status, s.sponsor_code,
                    (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id) AS total_ships,
                    (SELECT MAX(COALESCE(sp.pause_start_date, sp.end_date, sp.updated_at))
                     FROM sponsorships sp
                     WHERE sp.sponsor_id = s.id AND sp.status IN ('cancelled', 'paused')) AS last_stop
             FROM sponsors s
             WHERE s.status IN ('inactive', 'suspended', 'cancelled')
               AND NOT EXISTS (
                   SELECT 1 FROM winback_campaigns wc
                   WHERE wc.sponsor_id = s.id AND wc.status IN ('open', 'contacted')
               )
             HAVING (last_stop IS NULL OR last_stop < DATE_SUB(NOW(), INTERVAL 90 DAY))
             ORDER BY last_stop ASC"
        );

        $uncoveredFamilies = dbFetchAll(
            "SELECT f.id, f.family_code, f.mother_name, f.children_count, f.monthly_need_amount, f.city
             FROM families f
             WHERE f.status IN ('active', 'pending')
               AND NOT EXISTS (
                   SELECT 1 FROM sponsorships sp
                   JOIN family_children fc ON fc.id = sp.child_id
                   WHERE fc.family_id = f.id AND sp.status = 'active'
               )
             ORDER BY f.children_count DESC, f.monthly_need_amount DESC
             LIMIT 10"
        );

        $cases = dbFetchAll(
            "SELECT wc.*, s.full_name AS sponsor_name, u.full_name AS handler_name
             FROM winback_campaigns wc
             JOIN sponsors s ON s.id = wc.sponsor_id
             LEFT JOIN users u ON u.id = wc.handled_by
             ORDER BY (wc.status IN ('open', 'contacted')) DESC, wc.id DESC
             LIMIT 200"
        );

        $detail = null;
        $contacts = [];
        $pastFamilies = [];
        if ((int)$viewId > 0) {
            $detail = dbFetchOne(
                "SELECT wc.*, s.full_name AS sponsor_name, s.sponsor_code, s.phone AS sponsor_phone,
                        s.status AS sponsor_status, u.full_name AS handler_name, ru.full_name AS reactivated_by_name
                 FROM winback_campaigns wc
                 JOIN sponsors s ON s.id = wc.sponsor_id
                 LEFT JOIN users u ON u.id = wc.handled_by
                 LEFT JOIN users ru ON ru.id = wc.reactivated_by_user_id
                 WHERE wc.id = ?",
                [(int)$viewId]
            );
            if (!$detail) {
                ak_api_winback_not_found();
            }

            $contacts = dbFetchAll(
                "SELECT c.*, u.full_name AS by_name
                 FROM winback_contacts c
                 LEFT JOIN users u ON u.id = c.contacted_by
                 WHERE c.campaign_id = ?
                 ORDER BY c.id DESC",
                [(int)$viewId]
            );
            $pastFamilies = dbFetchAll(
                "SELECT f.id, f.family_code, f.mother_name,
                        (SELECT COUNT(*) FROM sponsorships x
                         JOIN family_children xfc ON xfc.id = x.child_id
                         WHERE xfc.family_id = f.id AND x.status = 'active') AS active_now
                 FROM sponsorships sp
                 JOIN family_children pfc ON pfc.id = sp.child_id
                 JOIN families f ON f.id = pfc.family_id
                 WHERE sp.sponsor_id = ?
                 GROUP BY f.id
                 ORDER BY f.mother_name",
                [(int)$detail['sponsor_id']]
            );
        }
    } catch (Throwable $exception) {
        error_log('Mobile win-back read failed: ' . $exception->getMessage());
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'winback_unavailable', 'message' => 'Sponsor win-back data is temporarily unavailable.']]);
    }

    ak_api_json(200, [
        'ok' => true,
        'data' => [
            'reactivators' => $reactivators,
            'queue' => $queue,
            'uncovered_families' => $uncoveredFamilies,
            'cases' => $cases,
            'detail' => $detail,
            'contacts' => $contacts,
            'past_families' => $pastFamilies,
        ],
    ]);
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$userId = (int)$user['id'];

try {
    if ($action === 'open_case') {
        $sponsorId = filter_var($input['sponsor_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($sponsorId === false || $sponsorId <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_sponsor_id', 'message' => 'A valid sponsor is required.']]);
        }

        $eligible = dbFetchOne(
            "SELECT s.id
             FROM sponsors s
             WHERE s.id = ?
               AND s.status IN ('inactive', 'suspended', 'cancelled')
               AND NOT EXISTS (
                   SELECT 1 FROM winback_campaigns wc
                   WHERE wc.sponsor_id = s.id AND wc.status IN ('open', 'contacted')
               )
             HAVING (
                 SELECT MAX(COALESCE(sp.pause_start_date, sp.end_date, sp.updated_at))
                 FROM sponsorships sp
                 WHERE sp.sponsor_id = s.id AND sp.status IN ('cancelled', 'paused')
             ) IS NULL
             OR (
                 SELECT MAX(COALESCE(sp2.pause_start_date, sp2.end_date, sp2.updated_at))
                 FROM sponsorships sp2
                 WHERE sp2.sponsor_id = s.id AND sp2.status IN ('cancelled', 'paused')
             ) < DATE_SUB(NOW(), INTERVAL 90 DAY)",
            [(int)$sponsorId]
        );
        if (!$eligible) {
            ak_api_json(409, ['ok' => false, 'error' => ['code' => 'sponsor_not_eligible', 'message' => 'The sponsor is not eligible for a new win-back case.']]);
        }

        dbExecute('INSERT INTO winback_campaigns (sponsor_id, handled_by) VALUES (?, ?)', [(int)$sponsorId, $userId]);
        $campaignId = (int)dbLastInsertId();
        ak_api_winback_audit($userId, 'OPEN', (int)$sponsorId, []);
        ak_api_json(201, ['ok' => true, 'data' => ['campaign_id' => $campaignId, 'status' => 'open']]);
    }

    if ($action === 'add_contact') {
        $campaignId = filter_var($input['campaign_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($campaignId === false || $campaignId <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_campaign_id', 'message' => 'A valid win-back case is required.']]);
        }
        $campaign = dbFetchOne('SELECT id, status FROM winback_campaigns WHERE id = ?', [(int)$campaignId]);
        if (!$campaign) {
            ak_api_winback_not_found();
        }
        if (!in_array((string)$campaign['status'], ['open', 'contacted'], true)) {
            ak_api_winback_invalid_transition();
        }

        $methodValue = trim((string)($input['method'] ?? 'phone'));
        if (!in_array($methodValue, ['phone', 'whatsapp', 'visit'], true)) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_contact_method', 'message' => 'Choose phone, WhatsApp, or visit.']]);
        }
        $outcome = trim((string)($input['outcome'] ?? 'no_answer')) ?: 'no_answer';
        if (!in_array($outcome, ['no_answer', 'reached', 'promised', 'refused'], true)) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_contact_outcome', 'message' => 'Choose a valid contact outcome.']]);
        }
        $notes = trim((string)($input['notes'] ?? ''));

        dbExecute(
            'INSERT INTO winback_contacts (campaign_id, contacted_by, method, outcome, notes) VALUES (?, ?, ?, ?, ?)',
            [(int)$campaignId, $userId, $methodValue, $outcome, $notes !== '' ? $notes : null]
        );
        dbExecute(
            "UPDATE winback_campaigns
             SET last_contact_at = NOW(), status = IF(status = 'open', 'contacted', status), handled_by = ?
             WHERE id = ?",
            [$userId, (int)$campaignId]
        );
        ak_api_winback_audit($userId, 'CONTACT', (int)$campaignId, ['method' => $methodValue, 'outcome' => $outcome]);
        ak_api_json(200, ['ok' => true, 'data' => ['campaign_id' => (int)$campaignId, 'status' => 'contacted']]);
    }

    if ($action === 'mark_returned') {
        $campaignId = filter_var($input['campaign_id'] ?? 0, FILTER_VALIDATE_INT);
        $reactivatedByUserId = filter_var($input['reactivated_by_user_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($campaignId === false || $campaignId <= 0 || $reactivatedByUserId === false || $reactivatedByUserId <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_reactivation_input', 'message' => 'Choose a valid case and actual reactivating employee.']]);
        }

        try {
            $result = wb_mark_returned(db(), (int)$campaignId, (int)$reactivatedByUserId, $userId);
        } catch (InvalidArgumentException $exception) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_reactivator', 'message' => $exception->getMessage()]]);
        }
        if ($result === null) {
            ak_api_winback_invalid_transition();
        }

        ak_api_winback_audit($userId, 'RETURNED', (int)$campaignId, [
            'sponsor_id' => $result['sponsor_id'],
            'reactivated_by_user_id' => $result['reactivated_by_user_id'],
            'reactivation_confirmed_by_user_id' => $result['reactivation_confirmed_by_user_id'],
            'reactivated_at' => $result['reactivated_at'],
        ]);
        try {
            hrIncentiveNotifyPendingActivity(db(), (int)$result['reactivated_by_user_id'], 'reactivation', (string)$result['reactivated_at']);
        } catch (Throwable $notificationError) {
            error_log('Immediate win-back incentive activity notice failed for campaign ' . (int)$campaignId . ': ' . $notificationError->getMessage());
        }

        ak_api_json(200, ['ok' => true, 'data' => ['campaign_id' => (int)$campaignId, 'status' => 'persuaded', 'reactivated' => $result]]);
    }

    if ($action === 'reopen_case') {
        $campaignId = filter_var($input['campaign_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($campaignId === false || $campaignId <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_campaign_id', 'message' => 'A valid win-back case is required.']]);
        }
        $campaign = dbFetchOne('SELECT id, sponsor_id, status FROM winback_campaigns WHERE id = ?', [(int)$campaignId]);
        if (!$campaign) {
            ak_api_winback_not_found();
        }
        if ((string)$campaign['status'] !== 'declined') {
            ak_api_winback_invalid_transition();
        }
        $changed = dbExecute(
            "UPDATE winback_campaigns SET status = 'open', closed_at = NULL, handled_by = ?
             WHERE id = ? AND status = 'declined'",
            [$userId, (int)$campaignId]
        );
        if ($changed !== 1) {
            ak_api_winback_invalid_transition();
        }
        ak_api_winback_audit($userId, 'REOPEN', (int)$campaignId, ['sponsor_id' => (int)$campaign['sponsor_id']]);
        ak_api_json(200, ['ok' => true, 'data' => ['campaign_id' => (int)$campaignId, 'status' => 'open']]);
    }

    if ($action === 'mark_declined') {
        $campaignId = filter_var($input['campaign_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($campaignId === false || $campaignId <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_campaign_id', 'message' => 'A valid win-back case is required.']]);
        }
        $campaign = dbFetchOne('SELECT id, status FROM winback_campaigns WHERE id = ?', [(int)$campaignId]);
        if (!$campaign) {
            ak_api_winback_not_found();
        }
        if (!in_array((string)$campaign['status'], ['open', 'contacted'], true)) {
            ak_api_winback_invalid_transition();
        }
        $changed = dbExecute(
            "UPDATE winback_campaigns SET status = 'declined', closed_at = NOW(), handled_by = ?
             WHERE id = ? AND status IN ('open', 'contacted')",
            [$userId, (int)$campaignId]
        );
        if ($changed !== 1) {
            ak_api_winback_invalid_transition();
        }
        ak_api_winback_audit($userId, 'DECLINED', (int)$campaignId, []);
        ak_api_json(200, ['ok' => true, 'data' => ['campaign_id' => (int)$campaignId, 'status' => 'declined']]);
    }

    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'unknown_action', 'message' => 'Unknown sponsor win-back action.']]);
} catch (Throwable $exception) {
    error_log('Mobile win-back action failed: ' . $exception->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'winback_action_failed', 'message' => 'The win-back action could not be completed.']]);
}
