<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Http;

/**
 * Declarative RBAC access tier attached directly to routes registered in AdminApp.
 */
enum RouteAccess: string
{
    case PUBLIC = 'public';
    case AUTHENTICATED = 'authenticated';
    case MANAGER = 'manager';
    case PRIVILEGED = 'privileged';
}
