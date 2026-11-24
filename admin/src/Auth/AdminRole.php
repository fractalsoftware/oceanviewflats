<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Auth;

/**
 * Canonical Admin Console role hierarchy with fail-closed session resolution.
 */
enum AdminRole: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case VIEWER = 'viewer';

    /**
     * Resolves the authenticated role from session state, failing closed to VIEWER when absent or invalid.
     *
     * @param array<string, mixed> $session
     */
    public static function fromSession(array $session): self
    {
        $raw = $session['admin_user_role'] ?? $session['admin_role'] ?? null;
        if (!is_string($raw)) {
            return self::VIEWER;
        }

        return self::tryFrom(strtolower(trim($raw))) ?? self::VIEWER;
    }

    /**
     * Returns true for administrator-tier roles (admin, superadmin).
     */
    public function isPrivileged(): bool
    {
        return $this === self::ADMIN || $this === self::SUPERADMIN;
    }

    /**
     * Returns true for management-tier roles permitted to modify day-to-day stay records (manager, admin, superadmin).
     */
    public function canManage(): bool
    {
        return $this->isPrivileged() || $this === self::MANAGER;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
