<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

if (!function_exists('ak_mobile_toggle_supervisor_status')) {
    /**
     * Toggle the existing VGM supervisor active flag.
     * The caller owns the transaction to support rollback-only verification.
     */
    function ak_mobile_toggle_supervisor_status(int $supervisorId, int $actorId, string $ipAddress = '', string $userAgent = ''): array
    {
        if (!db()->inTransaction()) {
            throw new LogicException('Supervisor status change must run inside a transaction.');
        }
        if ($supervisorId < 1 || $actorId < 1) {
            throw new InvalidArgumentException('A supervisor and acting user are required.');
        }

        $target = dbFetchOne(
            "SELECT u.id, u.full_name, u.is_active, u.legacy_status
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND r.code = 'supervisor'
             FOR UPDATE",
            [$supervisorId]
        );
        if (!$target) {
            throw new DomainException('Supervisor was not found.');
        }
        if ((string)($target['legacy_status'] ?? '') === 'deleted') {
            throw new DomainException('An archived supervisor cannot be reactivated.');
        }

        $oldActive = (int)$target['is_active'];
        $newActive = $oldActive === 1 ? 0 : 1;
        dbExecute('UPDATE users SET is_active = ? WHERE id = ?', [$newActive, $supervisorId]);

        try {
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)",
                [
                    $actorId,
                    $supervisorId,
                    json_encode(['is_active' => $oldActive], JSON_UNESCAPED_UNICODE),
                    json_encode(['is_active' => $newActive], JSON_UNESCAPED_UNICODE),
                    $ipAddress,
                    $userAgent,
                ]
            );
        } catch (Throwable $e) {
            // Match the existing VGM web workflow: audit failure must not block status toggling.
            error_log('Mobile supervisor status audit failed: ' . $e->getMessage());
        }

        return [
            'supervisor_id' => $supervisorId,
            'full_name' => (string)$target['full_name'],
            'is_active' => $newActive === 1,
        ];
    }
}
