<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/sponsor_assignments.php';

if (!function_exists('ak_mobile_sponsor_assignment_options')) {
    function ak_mobile_sponsor_assignment_options(): array
    {
        $sponsors = dbFetchAll(
            'SELECT id, full_name, sponsor_code, phone FROM sponsors WHERE supervisor_id IS NULL ORDER BY id DESC LIMIT 100'
        );
        $supervisors = dbFetchAll(
            "SELECT u.id, u.full_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'supervisor' AND u.is_active = 1
             ORDER BY u.full_name"
        );

        return [
            'unassigned_sponsors' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'full_name' => (string)$row['full_name'],
                'sponsor_code' => (string)($row['sponsor_code'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
            ], $sponsors),
            'active_supervisors' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'full_name' => (string)$row['full_name'],
            ], $supervisors),
        ];
    }
}

if (!function_exists('ak_mobile_assign_sponsor')) {
    /**
     * Perform the same assignment write as modules/sponsors/assign.php.
     * The caller must own the transaction so rollback-only tests can verify it.
     */
    function ak_mobile_assign_sponsor(int $sponsorId, int $supervisorId, int $actorId): array
    {
        if (!db()->inTransaction()) {
            throw new LogicException('Sponsor assignment must run inside a transaction.');
        }
        if ($sponsorId < 1 || $supervisorId < 1 || $actorId < 1) {
            throw new InvalidArgumentException('A sponsor, supervisor, and actor are required.');
        }

        $sponsor = dbFetchOne('SELECT id, full_name, supervisor_id FROM sponsors WHERE id = ? FOR UPDATE', [$sponsorId]);
        if (!$sponsor) {
            throw new DomainException('Sponsor was not found.');
        }

        $supervisor = dbFetchOne(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND r.code = 'supervisor' AND u.is_active = 1
             FOR UPDATE",
            [$supervisorId]
        );
        if (!$supervisor) {
            throw new DomainException('An active supervisor must be selected.');
        }

        recordSponsorAssignment($sponsorId, $supervisorId, $actorId, 'manual_reassignment');
        dbExecute(
            'UPDATE sponsors SET supervisor_id = ?, is_manual_override = 1, assigned_by = ?, assigned_at = NOW(), updated_by = ? WHERE id = ?',
            [$supervisorId, $actorId, $actorId, $sponsorId]
        );

        return [
            'sponsor_id' => $sponsorId,
            'sponsor_name' => (string)$sponsor['full_name'],
            'supervisor_id' => $supervisorId,
        ];
    }
}
