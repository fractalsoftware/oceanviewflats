<?php
/**
 * @var string $tableHtml
 * @var string|null $drawerHtml
 * @var array<string, mixed> $filters
 * @var list<array{id: int, name: string, email: string, role: string}> $adminUsers
 * @var list<string> $entityTypes
 * @var array<string, mixed> $currentUser
 */

$search = (string) ($filters['search'] ?? '');
$selectedAction = (string) ($filters['action'] ?? 'all');
$selectedEntityType = (string) ($filters['entity_type'] ?? 'all');
$selectedActor = (string) ($filters['actor'] ?? 'all');
$dateFrom = (string) ($filters['date_from'] ?? '');
$dateTo = (string) ($filters['date_to'] ?? '');
?>

<div class="space-y-6">

    <!-- Page Title & Header Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Audit Logs</h1>
            <p class="text-xs text-gray-500 mt-1">
                Immutable operational trail capturing administrative overrides, pricing updates, calendar holds, and system events.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center text-xs text-gray-500 bg-white border border-gray-200 px-3 py-1.5 rounded-lg shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> Immutable Audit Trail Active
            </span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-2xs">
        <form id="audit-filter-form"
              action="/audit-logs"
              method="GET"
              hx-get="/audit-logs"
              hx-target="#audit-table-container"
              hx-push-url="true"
              hx-trigger="submit, keyup changed delay:300ms from:#search-input, change from:select, change from:input[type=date]"
              class="space-y-4">

            <!-- Primary Search Bar -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text"
                       id="search-input"
                       name="search"
                       value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Search by entity UID (e.g. res-man-*, ovf_*), action, operator name, or IP address..."
                       class="block w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
            </div>

            <!-- Filter Controls Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

                <!-- Action Dropdown with Optgroups -->
                <div>
                    <label for="action-filter" class="block text-2xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Action Category</label>
                    <select id="action-filter"
                            name="action"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="all" <?= $selectedAction === 'all' ? 'selected' : '' ?>>All Actions</option>

                        <optgroup label="Categories">
                            <option value="cat:reservations" <?= $selectedAction === 'cat:reservations' ? 'selected' : '' ?>>All Reservations & Access</option>
                            <option value="cat:rates" <?= $selectedAction === 'cat:rates' ? 'selected' : '' ?>>All Rates & Pricing</option>
                            <option value="cat:blocks" <?= $selectedAction === 'cat:blocks' ? 'selected' : '' ?>>All Calendar Holds</option>
                            <option value="cat:auth" <?= $selectedAction === 'cat:auth' ? 'selected' : '' ?>>All Authentication</option>
                            <option value="cat:system" <?= $selectedAction === 'cat:system' ? 'selected' : '' ?>>All System & Webhooks</option>
                        </optgroup>

                        <optgroup label="Reservations & Access">
                            <option value="reservation_manual_create" <?= $selectedAction === 'reservation_manual_create' ? 'selected' : '' ?>>Manual Reservation Created</option>
                            <option value="reservation_updated" <?= $selectedAction === 'reservation_updated' ? 'selected' : '' ?>>Reservation Updated</option>
                            <option value="reservation_cancelled" <?= $selectedAction === 'reservation_cancelled' ? 'selected' : '' ?>>Reservation Cancelled</option>
                            <option value="guest_registry_manual_complete" <?= $selectedAction === 'guest_registry_manual_complete' ? 'selected' : '' ?>>Guest Registry Completed</option>
                            <option value="guest_registry_submitted" <?= $selectedAction === 'guest_registry_submitted' ? 'selected' : '' ?>>Guest Registry Submitted</option>
                            <option value="door_code_override" <?= $selectedAction === 'door_code_override' ? 'selected' : '' ?>>Door Code Overridden</option>
                            <option value="door_code_regenerate" <?= $selectedAction === 'door_code_regenerate' ? 'selected' : '' ?>>Door Code Regenerated</option>
                            <option value="condominium_clearance_sync" <?= $selectedAction === 'condominium_clearance_sync' ? 'selected' : '' ?>>Condominium Clearance Sync</option>
                            <option value="condominium_clearance_retry" <?= $selectedAction === 'condominium_clearance_retry' ? 'selected' : '' ?>>Condominium Clearance Retry</option>
                        </optgroup>

                        <optgroup label="Rates & Pricing">
                            <option value="rate_tier_create" <?= $selectedAction === 'rate_tier_create' ? 'selected' : '' ?>>Rate Tier Created</option>
                            <option value="rate_tier_update" <?= $selectedAction === 'rate_tier_update' ? 'selected' : '' ?>>Rate Tier Updated</option>
                            <option value="rate_tier_delete" <?= $selectedAction === 'rate_tier_delete' ? 'selected' : '' ?>>Rate Tier Deleted</option>
                            <option value="rates_seed_from_csv" <?= $selectedAction === 'rates_seed_from_csv' ? 'selected' : '' ?>>Rates Seeded from CSV</option>
                        </optgroup>

                        <optgroup label="Calendar Holds">
                            <option value="calendar_block_create" <?= $selectedAction === 'calendar_block_create' ? 'selected' : '' ?>>Calendar Block Created</option>
                            <option value="calendar_block_delete" <?= $selectedAction === 'calendar_block_delete' ? 'selected' : '' ?>>Calendar Block Deleted</option>
                            <option value="channel_sync_executed" <?= $selectedAction === 'channel_sync_executed' ? 'selected' : '' ?>>Channel Sync Executed</option>
                        </optgroup>

                        <optgroup label="Authentication">
                            <option value="auth_login_success" <?= $selectedAction === 'auth_login_success' ? 'selected' : '' ?>>Login Success</option>
                            <option value="auth_login_failed" <?= $selectedAction === 'auth_login_failed' ? 'selected' : '' ?>>Login Failed</option>
                            <option value="auth_logout" <?= $selectedAction === 'auth_logout' ? 'selected' : '' ?>>Logout</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Entity Type Dropdown -->
                <div>
                    <label for="entity-type-filter" class="block text-2xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Entity Type</label>
                    <select id="entity-type-filter"
                            name="entity_type"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="all" <?= $selectedEntityType === 'all' ? 'selected' : '' ?>>All Entities</option>
                        <?php foreach ($entityTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedEntityType === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type)), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Actor Dropdown -->
                <div>
                    <label for="actor-filter" class="block text-2xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Actor / User</label>
                    <select id="actor-filter"
                            name="actor"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="all" <?= $selectedActor === 'all' ? 'selected' : '' ?>>All Actors</option>
                        <option value="system" <?= $selectedActor === 'system' ? 'selected' : '' ?>>System / Automated</option>
                        <?php foreach ($adminUsers as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" <?= $selectedActor === (string) $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label for="date-from-filter" class="block text-2xs font-semibold text-gray-500 uppercase tracking-wider mb-1">From Date</label>
                    <input type="date"
                           id="date-from-filter"
                           name="date_from"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>"
                           class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                </div>

                <!-- Date To & Reset -->
                <div>
                    <label for="date-to-filter" class="block text-2xs font-semibold text-gray-500 uppercase tracking-wider mb-1">To Date</label>
                    <div class="flex items-center space-x-2">
                        <input type="date"
                               id="date-to-filter"
                               name="date_to"
                               value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>"
                               class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                        <?php if ($search !== '' || $selectedAction !== 'all' || $selectedEntityType !== 'all' || $selectedActor !== 'all' || $dateFrom !== '' || $dateTo !== ''): ?>
                            <a href="/audit-logs"
                               hx-get="/audit-logs"
                               hx-target="#audit-table-container"
                               hx-push-url="true"
                               title="Reset all filters"
                               class="inline-flex items-center p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- Audit Log Table Partial Container -->
    <div id="audit-table-container">
        <?= $tableHtml ?>
    </div>

    <!-- Inspector Drawer Container -->
    <div id="drawer-container">
        <?= $drawerHtml ?? '' ?>
    </div>

</div>
