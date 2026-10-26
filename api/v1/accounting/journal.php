<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['admin', 'financial_manager', 'general_manager', 'vice_general_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Journal access is not allowed for this account.',
    ]]);
}

if ($method === 'POST') {
    if (!in_array((string)$user['role_code'], ['admin', 'financial_manager'], true)) {
        ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only administrators and the Financial Manager can change manual journal entries.']]);
    }
    $input = ak_api_read_json();
    $action = (string)($input['action'] ?? '');

    if ($action === 'create_manual_entry') {
        $date = trim((string)($input['entry_date'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_entry_date', 'message' => 'The accounting date is invalid.']]);
        }
        if ($description === '' || mb_strlen($description, 'UTF-8') > 255) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_description', 'message' => 'A journal description of at most 255 characters is required.']]);
        }
        if (!is_array($input['lines'] ?? null)) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_lines', 'message' => 'Journal lines are required.']]);
        }

        $activeAccounts = $pdo = db();
        $accountRows = $pdo->query('SELECT id FROM accounts WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
        $accountMap = array_fill_keys(array_map('intval', $accountRows), true);
        $usedAccounts = [];
        $lines = [];
        $sumDebitCents = 0;
        $sumCreditCents = 0;
        $validAmount = static fn(string $value): bool => $value !== ''
            && preg_match('/^(?:0|[1-9]\\d{0,11})(?:\\.\\d{1,2})?$/', $value) === 1;

        foreach ($input['lines'] as $line) {
            if (!is_array($line)) {
                ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_line', 'message' => 'Each journal line must be an object.']]);
            }
            $rawAccountId = $line['account_id'] ?? '';
            $rawDebit = trim(str_replace(',', '', (string)($line['debit'] ?? '')));
            $rawCredit = trim(str_replace(',', '', (string)($line['credit'] ?? '')));
            if ((string)$rawAccountId === '' && $rawDebit === '' && $rawCredit === '') {
                continue;
            }
            $accountId = filter_var($rawAccountId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($accountId === false || !isset($accountMap[(int)$accountId])) {
                ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_account', 'message' => 'A selected account is invalid or inactive.']]);
            }
            if (isset($usedAccounts[(int)$accountId])) {
                ak_api_json(422, ['ok' => false, 'error' => ['code' => 'duplicate_account', 'message' => 'An account may appear only once in a manual journal entry.']]);
            }
            $debitValid = $rawDebit === '' || $validAmount($rawDebit);
            $creditValid = $rawCredit === '' || $validAmount($rawCredit);
            if (!$debitValid || !$creditValid) {
                ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_amount', 'message' => 'Amounts must be non-negative numbers with at most two decimal places.']]);
            }
            $debitCents = $rawDebit === '' ? 0 : (int)round(((float)$rawDebit) * 100);
            $creditCents = $rawCredit === '' ? 0 : (int)round(((float)$rawCredit) * 100);
            if ($debitCents < 0 || $creditCents < 0 || ($debitCents > 0 && $creditCents > 0) || ($debitCents === 0 && $creditCents === 0)) {
                ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_line_amount', 'message' => 'Each line must contain a positive amount on exactly one side.']]);
            }
            $lineDescription = trim((string)($line['description'] ?? ''));
            if (mb_strlen($lineDescription, 'UTF-8') > 255) {
                ak_api_json(422, ['ok' => false, 'error' => ['code' => 'line_description_too_long', 'message' => 'A line description cannot exceed 255 characters.']]);
            }
            $usedAccounts[(int)$accountId] = true;
            $lines[] = [
                (int)$accountId,
                number_format($debitCents / 100, 2, '.', ''),
                number_format($creditCents / 100, 2, '.', ''),
                $lineDescription,
            ];
            $sumDebitCents += $debitCents;
            $sumCreditCents += $creditCents;
        }
        if (count($lines) < 2) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'minimum_lines', 'message' => 'A manual journal entry requires at least two lines.']]);
        }
        if ($sumDebitCents <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'empty_amounts', 'message' => 'Enter a positive journal amount.']]);
        }
        if ($sumDebitCents !== $sumCreditCents) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'unbalanced_entry', 'message' => 'Total debit must equal total credit.']]);
        }

        $lockName = 'ahl_el_kheir.manual_journal_code';
        $lockAcquired = false;
        $result = null;
        $failure = null;
        try {
            $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lockStmt->execute([$lockName]);
            $lockAcquired = (int)$lockStmt->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new RuntimeException('The journal numbering lock is busy. Try again.');
            }
            $pdo->beginTransaction();
            $last = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(entry_code, 4) AS UNSIGNED)), 0) FROM journal_entries WHERE entry_code REGEXP '^JE-[0-9]+$'")->fetchColumn();
            $entryCode = 'JE-' . str_pad((string)($last + 1), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (?, ?, ?, 'manual', NULL, 'posted', ?)");
            $stmt->execute([$entryCode, $date, $description, (int)$user['id']]);
            $entryId = (int)$pdo->lastInsertId();
            if ($entryId <= 0) throw new RuntimeException('The journal entry could not be created.');
            $lineInsert = $pdo->prepare('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)');
            foreach ($lines as $line) $lineInsert->execute([$entryId, $line[0], $line[1], $line[2], $line[3]]);
            $check = $pdo->prepare('SELECT COUNT(*) line_count, COALESCE(SUM(debit), 0) debit_total, COALESCE(SUM(credit), 0) credit_total FROM journal_lines WHERE entry_id = ?');
            $check->execute([$entryId]);
            $totals = $check->fetch(PDO::FETCH_ASSOC);
            if ((int)$totals['line_count'] !== count($lines)
                || (int)round((float)$totals['debit_total'] * 100) !== $sumDebitCents
                || (int)round((float)$totals['credit_total'] * 100) !== $sumCreditCents) {
                throw new RuntimeException('The saved journal entry failed its balance verification.');
            }
            $pdo->commit();
            $result = ['id' => $entryId, 'entry_code' => $entryCode, 'entry_date' => $date, 'description' => $description,
                'total_debit' => round($sumDebitCents / 100, 2), 'total_credit' => round($sumCreditCents / 100, 2),
                'line_count' => count($lines), 'is_balanced' => true];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failure = $e;
        } finally {
            if ($lockAcquired) {
                try {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (Throwable $releaseError) {
                }
            }
        }
        if ($failure !== null) {
            error_log('Mobile manual journal creation failed: ' . $failure->getMessage());
            ak_api_json(500, ['ok' => false, 'error' => ['code' => 'journal_create_failed', 'message' => 'The journal entry was not saved. No partial entry was committed.']]);
        }
        ak_api_json(201, ['ok' => true, 'data' => ['entry' => $result]]);
    }

    if ($action === 'void_manual_entry') {
        $entryId = filter_var($input['entry_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = trim((string)($input['reason'] ?? ''));
        if ($entryId === false || $entryId === null) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_entry_id', 'message' => 'A valid journal entry ID is required.']]);
        }
        if ($reason === '') $reason = 'تم إبطال القيد المحاسبي';
        if (mb_strlen($reason, 'UTF-8') > 255) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_void_reason', 'message' => 'The void reason cannot exceed 255 characters.']]);
        }

        $pdo = db();
        $result = null;
        $failure = null;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, entry_code, entry_date, description, reference_type, reference_id, created_by, status, voided_at FROM journal_entries WHERE id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$entryId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$entry) throw new OutOfBoundsException('Journal entry not found.');
            $automatedTypes = ['transaction', 'transaction_void', 'disbursement', 'disbursement_return', 'disbursement_void', 'item_return', 'voucher', 'voucher_void', 'payroll', 'manual_void'];
            if (in_array((string)$entry['reference_type'], $automatedTypes, true)) throw new DomainException('Automated journal entries cannot be voided from the manual journal workflow.');
            if ((string)$entry['status'] !== 'posted' || !empty($entry['voided_at'])) throw new DomainException('Only a posted, not-yet-voided manual journal entry can be voided.');
            if ((int)$entry['created_by'] === (int)$user['id']) throw new DomainException('The creator cannot void their own journal entry. A different authorized user must review it.');
            $stmt = $pdo->prepare('SELECT account_id, debit, credit, description FROM journal_lines WHERE entry_id = ? ORDER BY id');
            $stmt->execute([(int)$entryId]);
            $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($lines) < 2) throw new DomainException('The journal entry does not contain enough lines to void.');
            $sumDebit = 0.0;
            $sumCredit = 0.0;
            foreach ($lines as $line) {
                $debit = round((float)$line['debit'], 2);
                $credit = round((float)$line['credit'], 2);
                if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) throw new DomainException('The original journal contains an invalid line.');
                $sumDebit += $debit;
                $sumCredit += $credit;
            }
            if (round($sumDebit, 2) !== round($sumCredit, 2) || round($sumDebit, 2) <= 0) throw new DomainException('The original journal is unbalanced or zero.');
            $stmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'manual_void' AND reference_id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([(int)$entryId]);
            if ($stmt->fetchColumn()) throw new DomainException('A reversal already exists for this journal entry.');

            $stmt = $pdo->prepare("UPDATE journal_entries SET voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ? AND status = 'posted' AND voided_at IS NULL");
            $stmt->execute([(int)$user['id'], $reason, (int)$entryId]);
            if ($stmt->rowCount() !== 1) throw new DomainException('The original journal void metadata could not be saved.');

            $reversalCode = 'JE-VOID-MANUAL-' . (int)$entryId;
            $stmt = $pdo->prepare('SELECT id FROM journal_entries WHERE entry_code = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$reversalCode]);
            if ($stmt->fetchColumn()) throw new DomainException('A reversal code already exists for this entry.');
            $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (?, ?, ?, 'manual_void', ?, 'posted', ?)");
            $stmt->execute([$reversalCode, (string)$entry['entry_date'], 'عكس القيد المحاسبي ' . (string)$entry['entry_code'] . ' بسبب الإبطال', (int)$entryId, (int)$user['id']]);
            $reversalId = (int)$pdo->lastInsertId();
            $insertLine = $pdo->prepare('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)');
            foreach ($lines as $line) {
                $insertLine->execute([$reversalId, (int)$line['account_id'], round((float)$line['credit'], 2), round((float)$line['debit'], 2), 'عكس: ' . (string)($line['description'] ?? '')]);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) line_count, COALESCE(SUM(debit), 0) debit_total, COALESCE(SUM(credit), 0) credit_total FROM journal_lines WHERE entry_id = ?');
            $stmt->execute([$reversalId]);
            $reversalTotals = $stmt->fetch(PDO::FETCH_ASSOC);
            if ((int)$reversalTotals['line_count'] !== count($lines)
                || round((float)$reversalTotals['debit_total'], 2) !== round((float)$reversalTotals['credit_total'], 2)
                || round((float)$reversalTotals['debit_total'], 2) !== round($sumDebit, 2)) {
                throw new RuntimeException('The reversal failed its balance verification.');
            }
            $pdo->commit();
            $result = ['entry_id' => (int)$entryId, 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => $reason,
                'reversal_entry_id' => $reversalId, 'reversal_entry_code' => $reversalCode,
                'total_debit' => round($sumDebit, 2), 'total_credit' => round($sumCredit, 2)];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failure = $e;
        }
        if ($failure !== null) {
            if ($failure instanceof OutOfBoundsException) {
                ak_api_json(404, ['ok' => false, 'error' => ['code' => 'journal_entry_not_found', 'message' => $failure->getMessage()]]);
            }
            if ($failure instanceof DomainException) {
                ak_api_json(409, ['ok' => false, 'error' => ['code' => 'journal_void_conflict', 'message' => $failure->getMessage()]]);
            }
            error_log('Mobile manual journal void failed: ' . $failure->getMessage());
            ak_api_json(500, ['ok' => false, 'error' => ['code' => 'journal_void_failed', 'message' => 'The journal entry was not voided. No partial reversal was committed.']]);
        }
        ak_api_json(200, ['ok' => true, 'data' => ['result' => $result]]);
    }

    if ($action === 'list_accounts') {
        if (!in_array((string)$user['role_code'], ['admin', 'financial_manager'], true)) {
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only administrators and the Financial Manager can create manual journal entries.']]);
        }
        $accounts = $pdo->query('SELECT id, code, name_ar, name_en FROM accounts WHERE is_active = 1 ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($accounts as &$account) $account['id'] = (int)$account['id'];
        unset($account);
        ak_api_json(200, ['ok' => true, 'data' => ['accounts' => $accounts]]);
    }

    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported journal action.']]);
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
foreach (['from' => $from, 'to' => $to] as $field => $date) {
    if ($date !== '' && (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
    )) {
        ak_api_json(422, ['ok' => false, 'error' => [
            'code' => 'invalid_date',
            'message' => $field . ' must use YYYY-MM-DD format.',
        ]]);
    }
}
if ($from !== '' && $to !== '' && $from > $to) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_date_range',
        'message' => 'The start date must not be after the end date.',
    ]]);
}

$viewId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT);
if (isset($_GET['view']) && (!$viewId || $viewId < 1)) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_entry_id',
        'message' => 'A valid journal entry ID is required.',
    ]]);
}

$pdo = db();
if ($viewId) {
    $stmt = $pdo->prepare(
        'SELECT je.*, u.full_name AS creator
         FROM journal_entries je
         LEFT JOIN users u ON u.id = je.created_by
         WHERE je.id = ? LIMIT 1'
    );
    $stmt->execute([(int)$viewId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) {
        ak_api_json(404, ['ok' => false, 'error' => [
            'code' => 'journal_entry_not_found',
            'message' => 'The journal entry was not found.',
        ]]);
    }

    $stmt = $pdo->prepare(
        'SELECT jl.id, jl.account_id, a.code AS account_code,
                a.name_ar AS account_name_ar, a.name_en AS account_name_en,
                jl.debit, jl.credit, jl.description
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE jl.entry_id = ?
         ORDER BY jl.id ASC'
    );
    $stmt->execute([(int)$viewId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debit = 0.0;
    $credit = 0.0;
    foreach ($lines as &$line) {
        $line['id'] = (int)$line['id'];
        $line['account_id'] = (int)$line['account_id'];
        $line['debit'] = round((float)$line['debit'], 2);
        $line['credit'] = round((float)$line['credit'], 2);
        $debit += $line['debit'];
        $credit += $line['credit'];
    }
    unset($line);

    $entry['id'] = (int)$entry['id'];
    $entry['created_by'] = $entry['created_by'] === null ? null : (int)$entry['created_by'];
    $entry['reference_id'] = $entry['reference_id'] === null ? null : (int)$entry['reference_id'];
    $entry['total_debit'] = round($debit, 2);
    $entry['total_credit'] = round($credit, 2);
    $entry['line_count'] = count($lines);
    $entry['is_balanced'] = count($lines) >= 2 && round($debit, 2) === round($credit, 2) && $debit > 0;

    ak_api_json(200, ['ok' => true, 'data' => ['entry' => $entry, 'lines' => $lines]]);
}

$sql = 'SELECT je.id, je.entry_code, je.entry_date, je.description,
               je.reference_type, je.reference_id, je.status, je.created_by,
               je.voided_at, je.voided_by, je.void_reason,
               u.full_name AS creator,
               (SELECT COALESCE(SUM(jl.debit), 0) FROM journal_lines jl WHERE jl.entry_id = je.id) AS total_debit,
               (SELECT COALESCE(SUM(jl.credit), 0) FROM journal_lines jl WHERE jl.entry_id = je.id) AS total_credit,
               (SELECT COUNT(*) FROM journal_lines jl WHERE jl.entry_id = je.id) AS line_count
        FROM journal_entries je
        LEFT JOIN users u ON u.id = je.created_by
        WHERE 1=1';
$params = [];
if ($from !== '') {
    $sql .= ' AND je.entry_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $sql .= ' AND je.entry_date <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY je.entry_date DESC, je.id DESC LIMIT 300';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($entries as &$entry) {
        $entry['id'] = (int)$entry['id'];
        $entry['created_by'] = $entry['created_by'] === null ? null : (int)$entry['created_by'];
        $entry['reference_id'] = $entry['reference_id'] === null ? null : (int)$entry['reference_id'];
        $entry['total_debit'] = round((float)$entry['total_debit'], 2);
        $entry['total_credit'] = round((float)$entry['total_credit'], 2);
        $entry['line_count'] = (int)$entry['line_count'];
        $entry['is_balanced'] = $entry['line_count'] >= 2
            && $entry['total_debit'] === $entry['total_credit']
            && $entry['total_debit'] > 0;
    }
    unset($entry);
    ak_api_json(200, ['ok' => true, 'data' => ['entries' => $entries, 'limit' => 300]]);
} catch (Throwable $e) {
    error_log('Mobile journal list failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'journal_list_failed',
        'message' => 'The journal entries could not be loaded.',
    ]]);
}
