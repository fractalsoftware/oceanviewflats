<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Repository;

use PDO;

/**
 * Repository providing paginated, filtered access to immutable audit log records.
 */
class AdminAuditLogRepository
{
    public const DEFAULT_PER_PAGE = 50;

    /**
     * Predefined action groupings for categorized filtering.
     */
    public const CATEGORY_MAP = [
        'cat:reservations' => [
            'reservation_manual_create',
            'reservation_updated',
            'reservation_cancelled',
            'guest_registry_manual_complete',
            'guest_registry_submitted',
            'door_code_override',
            'door_code_regenerate',
            'pin_override',
            'pin_regenerate',
            'condominium_clearance_sync',
            'condominium_clearance_retry',
            'reservation_created_confirmed',
            'reservation_created_pending',
            'reservation_confirmed',
            'direct_hold_created',
            'payment_approved_webhook',
            'airbnb_reservation_created',
            'manual_reservation_created',
        ],
        'cat:rates' => [
            'rate_tier_create',
            'rate_tier_update',
            'rate_tier_delete',
            'rates_seed_from_csv',
        ],
        'cat:blocks' => [
            'calendar_block_create',
            'calendar_block_delete',
            'channel_sync_executed',
        ],
        'cat:auth' => [
            'auth_login_success',
            'auth_login_failed',
            'auth_logout',
        ],
        'cat:system' => [
            'webhook_settlement',
            'webhook_refund',
            'refund_issued',
        ],
        'cat:expenses' => [
            'operational_expense_created',
            'operational_expense_updated',
            'operational_expense_deleted',
            'parafiscal_settled',
            'parafiscal_updated',
            'parafiscal_reverted',
        ],
    ];

    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Searches and paginates audit log records matching the specified criteria.
     *
     * @param array<string, mixed> $filters
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int
     * }
     */
    public function search(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        // 1. Search text (entity_id, ip_address, action, or admin user name)
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(l.entity_id LIKE :search OR l.ip_address LIKE :search OR l.action LIKE :search OR u.name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // 2. Action filter (single action or category prefix)
        $action = trim((string) ($filters['action'] ?? 'all'));
        if ($action !== '' && $action !== 'all') {
            if (isset(self::CATEGORY_MAP[$action])) {
                $categoryActions = self::CATEGORY_MAP[$action];
                $placeholders = [];
                foreach ($categoryActions as $idx => $catAction) {
                    $key = 'cat_act_' . $idx;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $catAction;
                }
                $conditions[] = 'l.action IN (' . implode(', ', $placeholders) . ')';
            } else {
                $conditions[] = 'l.action = :action';
                $params['action'] = $action;
            }
        }

        // 3. Entity type filter
        $entityType = trim((string) ($filters['entity_type'] ?? 'all'));
        if ($entityType !== '' && $entityType !== 'all') {
            $conditions[] = 'l.entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }

        // 4. Actor filter ('system', specific admin_user_id, or 'all')
        $actor = trim((string) ($filters['actor'] ?? 'all'));
        if ($actor !== '' && $actor !== 'all') {
            if ($actor === 'system') {
                $conditions[] = 'l.admin_user_id IS NULL';
            } elseif (is_numeric($actor)) {
                $conditions[] = 'l.admin_user_id = :actor_admin_user_id';
                $params['actor_admin_user_id'] = (int) $actor;
            }
        }

        // 5. Date range
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $conditions[] = 'l.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'l.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count total matching records
        $countSql = "
            SELECT COUNT(*) 
            FROM admin_audit_logs l
            LEFT JOIN admin_users u ON l.admin_user_id = u.id
            {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = (int) ceil(max(1, $total) / $perPage);
        if ($page > $totalPages && $total > 0) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        // Fetch paginated records
        $dataSql = "
            SELECT 
                l.id,
                l.admin_user_id,
                l.action,
                l.entity_type,
                l.entity_id,
                l.payload_before,
                l.payload_after,
                l.ip_address,
                l.user_agent,
                l.created_at,
                u.name AS admin_user_name,
                u.email AS admin_user_email,
                u.role AS admin_user_role
            FROM admin_audit_logs l
            LEFT JOIN admin_users u ON l.admin_user_id = u.id
            {$whereClause}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($dataSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->normalizeRow($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Finds a single audit log entry by ID, returning normalized details with parsed JSON.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                l.id,
                l.admin_user_id,
                l.action,
                l.entity_type,
                l.entity_id,
                l.payload_before,
                l.payload_after,
                l.ip_address,
                l.user_agent,
                l.created_at,
                u.name AS admin_user_name,
                u.email AS admin_user_email,
                u.role AS admin_user_role
            FROM admin_audit_logs l
            LEFT JOIN admin_users u ON l.admin_user_id = u.id
            WHERE l.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * Returns a list of all administrative users who can be filtered by, ordered by name.
     *
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    public function getAdminUsers(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, name, email, role 
            FROM admin_users 
            ORDER BY name ASC
        ');
        if ($stmt === false) {
            return [];
        }

        /** @var list<array{id: int, name: string, email: string, role: string}> $users */
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $users;
    }

    /**
     * Returns distinct observed entity types in admin_audit_logs.
     *
     * @return list<string>
     */
    public function getDistinctEntityTypes(): array
    {
        $stmt = $this->pdo->query('
            SELECT DISTINCT entity_type 
            FROM admin_audit_logs 
            ORDER BY entity_type ASC
        ');
        if ($stmt === false) {
            return [];
        }

        /** @var list<string> $types */
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $types;
    }

    /**
     * Normalizes a database row, converting types and JSON payloads.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $before = $row['payload_before'] ?? null;
        $after = $row['payload_after'] ?? null;

        return [
            'id' => (int) $row['id'],
            'admin_user_id' => $row['admin_user_id'] !== null ? (int) $row['admin_user_id'] : null,
            'action' => (string) $row['action'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => (string) $row['entity_id'],
            'payload_before' => is_string($before) ? json_decode($before, true) : $before,
            'payload_after' => is_string($after) ? json_decode($after, true) : $after,
            'raw_payload_before' => is_string($before) ? $before : ($before !== null ? json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null),
            'raw_payload_after' => is_string($after) ? $after : ($after !== null ? json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'user_agent' => $row['user_agent'] !== null ? (string) $row['user_agent'] : null,
            'created_at' => (string) $row['created_at'],
            'admin_user_name' => $row['admin_user_name'] !== null ? (string) $row['admin_user_name'] : null,
            'admin_user_email' => $row['admin_user_email'] !== null ? (string) $row['admin_user_email'] : null,
            'admin_user_role' => $row['admin_user_role'] !== null ? (string) $row['admin_user_role'] : null,
        ];
    }
}
