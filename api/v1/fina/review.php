<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/fina_lib.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_transaction_review.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
$role = (string)($user['role_code'] ?? '');
$canReview = ak_transaction_review_is_fm($role);
$canView = in_array($role, ['financial_manager', 'supervisor', 'admin', 'vice_general_manager'], true);
if ($method === 'GET' && !$canView) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Fina review access is not permitted.']]);
}
if ($method === 'POST' && !$canReview) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Only Finance Manager or Administrator can review Fina collections.']]);
}

try {
    fina_ensure_tables();

    if ($method === 'GET') {
        $supervisorOwnOnly = $role === 'supervisor';
        $pendingWhere = "fc.status = 'pending'";
        $pendingParams = [];
        if ($supervisorOwnOnly) {
            $pendingWhere .= ' AND fc.created_by = ?';
            $pendingParams[] = (int)$user['id'];
        }

        $pending = dbFetchAll(
            "SELECT fc.id,fc.amount,fc.currency_code,fc.payment_method,fc.collection_date,fc.receipt_path,
                    fc.purpose_note,fc.description,fc.status,fc.created_by,fc.reviewed_by,fc.reviewed_at,
                    fc.return_note,fc.accounting_journal_id,
                    fs.source_type,fs.source_name,fs.source_phone,fs.source_alt_phone,fs.source_email,
                    fs.source_address,fs.source_id_number,fs.source_reference,fs.contact_person,fs.source_details,
                    s.full_name sponsor_name,s.sponsor_code,u.full_name creator_name
             FROM fina_collections fc
             JOIN fina_sources fs ON fs.id=fc.fina_source_id
             LEFT JOIN sponsors s ON s.id=fs.sponsor_id
             JOIN users u ON u.id=fc.created_by
             WHERE {$pendingWhere}
             ORDER BY fc.collection_date ASC,fc.id ASC",
            $pendingParams
        );

        $historyWhere = "fc.status IN ('approved','returned')";
        $historyParams = [];
        if ($supervisorOwnOnly) {
            $historyWhere .= ' AND fc.created_by = ?';
            $historyParams[] = (int)$user['id'];
        }
        $history = dbFetchAll(
            "SELECT fc.id,fc.amount,fc.currency_code,fc.payment_method,fc.collection_date,fc.receipt_path,
                    fc.purpose_note,fc.description,fc.status,fc.created_by,fc.reviewed_by,fc.reviewed_at,
                    fc.return_note,fc.accounting_journal_id,
                    fs.source_type,fs.source_name,s.full_name sponsor_name,s.sponsor_code,
                    u.full_name creator_name,r.full_name reviewer_name
             FROM fina_collections fc
             JOIN fina_sources fs ON fs.id=fc.fina_source_id
             LEFT JOIN sponsors s ON s.id=fs.sponsor_id
             JOIN users u ON u.id=fc.created_by
             LEFT JOIN users r ON r.id=fc.reviewed_by
             WHERE {$historyWhere}
             ORDER BY fc.reviewed_at DESC,fc.id DESC
             LIMIT 50",
            $historyParams
        );

        ak_api_json(200, ['ok'=>true,'data'=>[
            'can_review'=>$canReview,
            'current_user_id'=>(int)$user['id'],
            'pending'=>$pending,
            'history'=>$history,
        ]]);
    }

    $input = ak_api_read_json();
    $id = (int)($input['id'] ?? 0);
    $action = trim((string)($input['action'] ?? ''));
    if ($id < 1) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_collection_id','message'=>'A valid collection ID is required.']]);
    }
    if (!in_array($action, ['approve','return'], true)) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Action must be approve or return.']]);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($action === 'approve') {
            $collection = dbFetchOne(
                "SELECT fc.*,fs.source_type,fs.sponsor_id,fs.source_name,fs.source_phone,fs.source_alt_phone,
                        fs.source_email,fs.source_address,fs.source_id_number,fs.source_reference,fs.contact_person,
                        fs.source_details,s.full_name sponsor_name,s.sponsor_code
                 FROM fina_collections fc
                 JOIN fina_sources fs ON fs.id=fc.fina_source_id
                 LEFT JOIN sponsors s ON s.id=fs.sponsor_id
                 WHERE fc.id=? AND fc.status='pending'
                 FOR UPDATE",
                [$id]
            );
            if (!$collection) {
                throw new DomainException('Collection does not exist or has already been reviewed.');
            }
            if ((int)$collection['created_by'] === (int)$user['id']) {
                throw new DomainException('You cannot approve a collection you created.');
            }
            $journalId = fina_post_collection_journal($collection, (int)$user['id']);
            if (dbExecute(
                "UPDATE fina_collections
                 SET status='approved',reviewed_by=?,reviewed_at=NOW(),accounting_journal_id=?
                 WHERE id=? AND status='pending'",
                [(int)$user['id'], $journalId, $id]
            ) !== 1) {
                throw new RuntimeException('Collection approval update failed.');
            }
            $pdo->commit();
            ak_api_json(200, ['ok'=>true,'data'=>['id'=>$id,'status'=>'approved','accounting_journal_id'=>$journalId]]);
        }

        $note = trim((string)($input['return_note'] ?? ''));
        if ($note === '') {
            throw new DomainException('A return reason is required.');
        }
        $collection = dbFetchOne(
            "SELECT id,created_by FROM fina_collections WHERE id=? AND status='pending' FOR UPDATE",
            [$id]
        );
        if (!$collection) {
            throw new DomainException('Collection does not exist or has already been reviewed.');
        }
        if ((int)$collection['created_by'] === (int)$user['id']) {
            throw new DomainException('You cannot return a collection you created.');
        }
        if (dbExecute(
            "UPDATE fina_collections SET status='returned',reviewed_by=?,reviewed_at=NOW(),return_note=?
             WHERE id=? AND status='pending'",
            [(int)$user['id'], $note, $id]
        ) !== 1) {
            throw new RuntimeException('Collection return update failed.');
        }
        $pdo->commit();
        ak_api_json(200, ['ok'=>true,'data'=>['id'=>$id,'status'=>'returned','return_note'=>$note]]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
} catch (DomainException $e) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'review_rejected','message'=>$e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile Fina review failed: '.$e->getMessage());
    ak_api_json(500, ['ok'=>false,'error'=>['code'=>'fina_review_failed','message'=>'Fina review action could not be completed.']]);
}
