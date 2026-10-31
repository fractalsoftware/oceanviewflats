<?php
/**
 * @var array<string, mixed> $log
 * @var array<string, mixed> $currentUser
 */

$id = (int) ($log['id'] ?? 0);
$action = (string) ($log['action'] ?? '');
$entityType = (string) ($log['entity_type'] ?? '');
$entityId = (string) ($log['entity_id'] ?? '');
$createdAt = (string) ($log['created_at'] ?? '');
$ipAddress = (string) ($log['ip_address'] ?? '');
$userAgent = (string) ($log['user_agent'] ?? '');

$actorName = $log['admin_user_name'] ?? null;
$actorEmail = $log['admin_user_email'] ?? null;
$actorRole = $log['admin_user_role'] ?? null;

$payloadBefore = is_array($log['payload_before'] ?? null) ? $log['payload_before'] : [];
$payloadAfter = is_array($log['payload_after'] ?? null) ? $log['payload_after'] : [];
$hasBefore = !empty($payloadBefore);
$hasAfter = !empty($payloadAfter);

$rawBefore = (string) ($log['raw_payload_before'] ?? '');
$rawAfter = (string) ($log['raw_payload_after'] ?? '');

$isReservation = ($entityType === 'reservation');
$isOperationalExpense = ($entityType === 'operational_expense');
$isParafiscalSettlement = ($entityType === 'parafiscal_settlement');

/**
 * Computes a key-by-key delta between before and after payloads.
 *
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 * @return array<string, array{type: 'changed'|'added'|'removed'|'unchanged', before: mixed, after: mixed}>
 */
$diffKeys = [];
$allKeys = array_unique(array_merge(array_keys($payloadBefore), array_keys($payloadAfter)));
sort($allKeys);

foreach ($allKeys as $key) {
    $inBefore = array_key_exists($key, $payloadBefore);
    $inAfter = array_key_exists($key, $payloadAfter);

    if ($inBefore && $inAfter) {
        if ($payloadBefore[$key] !== $payloadAfter[$key]) {
            $diffKeys[$key] = [
                'type' => 'changed',
                'before' => $payloadBefore[$key],
                'after' => $payloadAfter[$key],
            ];
        } else {
            $diffKeys[$key] = [
                'type' => 'unchanged',
                'before' => $payloadBefore[$key],
                'after' => $payloadAfter[$key],
            ];
        }
    } elseif ($inBefore) {
        $diffKeys[$key] = [
            'type' => 'removed',
            'before' => $payloadBefore[$key],
            'after' => null,
        ];
    } else {
        $diffKeys[$key] = [
            'type' => 'added',
            'before' => null,
            'after' => $payloadAfter[$key],
        ];
    }
}

if (!function_exists('formatDiffValue')) {
    /**
     * Formats mixed value for compact display.
     */
    function formatDiffValue(mixed $val): string {
        if ($val === null) return 'null';
        if (is_bool($val)) return $val ? 'true' : 'false';
        if (is_array($val)) return json_encode($val, JSON_UNESCAPED_SLASHES) ?: '[]';
        return (string) $val;
    }
}
?>

<script>
window.closeAuditDrawer = window.closeAuditDrawer || function() {
    var container = document.getElementById('drawer-container');
    if (container) {
        container.innerHTML = '';
    } else {
        window.location.href = '/audit-logs';
        return;
    }
    if (window.location.pathname.startsWith('/audit-logs/')) {
        window.history.pushState(null, '', '/audit-logs');
    }
};

window.copyAuditJson = window.copyAuditJson || function(btnId, targetId) {
    var elem = document.getElementById(targetId);
    if (!elem) return;
    var text = elem.innerText || elem.textContent;
    navigator.clipboard.writeText(text).then(function() {
        var btn = document.getElementById(btnId);
        if (btn) {
            var orig = btn.innerText;
            btn.innerText = 'Copied!';
            btn.classList.add('text-emerald-600');
            setTimeout(function() {
                btn.innerText = orig;
                btn.classList.remove('text-emerald-600');
            }, 2000);
        }
    });
};
</script>

<!-- Backdrop overlay -->
<div class="fixed inset-0 z-40 bg-gray-900/40 backdrop-blur-xs transition-opacity"
     onclick="closeAuditDrawer();"></div>

<!-- Slide-Over Drawer Container -->
<div class="fixed inset-y-0 right-0 z-50 flex max-w-full pl-10">
    <div class="w-screen max-w-2xl bg-white shadow-2xl flex flex-col transform transition ease-in-out duration-300">

        <!-- Drawer Header -->
        <div class="px-6 py-5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm shadow-2xs">
                    #<?= $id ?>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900 leading-tight">Audit Log Inspector</h2>
                    <p class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <button type="button"
                    onclick="closeAuditDrawer();"
                    class="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-200/60 rounded-lg transition cursor-pointer"
                    title="Close drawer (Esc)">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Drawer Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Entity & Action Overview Card -->
            <div class="bg-gray-50/75 rounded-xl p-4 border border-gray-200 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-2xs font-semibold text-gray-400 uppercase tracking-wider">Operational Action</span>
                    <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded bg-white border border-gray-200 text-gray-800 shadow-2xs">
                        <?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-2xs font-semibold text-gray-400 uppercase tracking-wider">Target Entity</span>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-2xs uppercase tracking-wider font-semibold text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded">
                            <?= htmlspecialchars($entityType, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($isReservation): ?>
                            <a href="/reservations?search=<?= urlencode($entityId) ?>"
                               target="_blank"
                               title="Open reservation ledger"
                               class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center">
                                <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        <?php elseif ($isOperationalExpense): ?>
                            <a href="/financial-report?tab=expenses&amp;search=<?= urlencode($entityId) ?>"
                               target="_blank"
                               title="Open operational expense ledger"
                               class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center">
                                <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        <?php elseif ($isParafiscalSettlement): ?>
                            <a href="/financial-report#fontur-parafiscal-section"
                               target="_blank"
                               title="Open FONTUR Parafiscal settlements in Financial Report"
                               class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center">
                                <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <span class="font-mono text-xs font-bold text-gray-800">
                                <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actor Attribution & Client Metadata Card -->
            <div class="bg-white rounded-xl p-4 border border-gray-200 space-y-3 shadow-2xs">
                <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">Actor Attribution & Network</h3>
                
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-2xs text-gray-400 block font-medium">Actor</span>
                        <?php if ($actorName !== null): ?>
                            <span class="font-medium text-gray-900"><?= htmlspecialchars((string) $actorName, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-2xs text-gray-500 block"><?= htmlspecialchars((string) ($actorEmail ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="inline-block mt-0.5 text-2xs px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 font-semibold uppercase">
                                <?= htmlspecialchars((string) ($actorRole ?? 'admin'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center text-xs text-gray-700 font-medium">
                                <span class="w-2 h-2 rounded-full bg-gray-400 mr-1.5"></span>
                                System / Automated Webhook
                            </span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <span class="text-2xs text-gray-400 block font-medium">IP Address</span>
                        <span class="font-mono text-xs text-gray-800">
                            <?= htmlspecialchars($ipAddress !== '' ? $ipAddress : '—', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <?php if ($userAgent !== ''): ?>
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-2xs text-gray-400 block font-medium">User Agent</span>
                        <p class="font-mono text-2xs text-gray-600 break-all bg-gray-50 p-2 rounded border border-gray-200">
                            <?= htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- State Transition Visual Diff -->
            <div class="bg-white rounded-xl p-4 border border-gray-200 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">State Transition Diff</h3>
                    <span class="text-2xs text-gray-400 font-medium">
                        <?= count($diffKeys) ?> property keys
                    </span>
                </div>

                <?php if (empty($diffKeys)): ?>
                    <div class="text-center py-6 text-gray-400 text-xs italic">
                        No payload parameters recorded for this operation.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden text-xs">
                        <?php foreach ($diffKeys as $key => $diff): ?>
                            <?php if ($diff['type'] === 'changed'): ?>
                                <div class="p-3 bg-amber-50/40">
                                    <div class="font-mono font-semibold text-gray-800 text-2xs mb-1">
                                        <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?> <span class="text-amber-700 text-3xs font-normal uppercase">(Modified)</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-2xs font-mono">
                                        <div class="bg-rose-50 text-rose-800 p-1.5 rounded border border-rose-200 break-all">
                                            <span class="text-3xs text-rose-500 uppercase block font-sans">Before:</span>
                                            <?= htmlspecialchars(formatDiffValue($diff['before']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="bg-emerald-50 text-emerald-800 p-1.5 rounded border border-emerald-200 break-all">
                                            <span class="text-3xs text-emerald-500 uppercase block font-sans">After:</span>
                                            <?= htmlspecialchars(formatDiffValue($diff['after']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($diff['type'] === 'added'): ?>
                                <div class="p-3 bg-emerald-50/30">
                                    <div class="font-mono font-semibold text-gray-800 text-2xs mb-1">
                                        <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?> <span class="text-emerald-700 text-3xs font-normal uppercase">(Added)</span>
                                    </div>
                                    <div class="bg-emerald-50 text-emerald-800 p-1.5 rounded border border-emerald-200 font-mono text-2xs break-all">
                                        <?= htmlspecialchars(formatDiffValue($diff['after']), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            <?php elseif ($diff['type'] === 'removed'): ?>
                                <div class="p-3 bg-rose-50/30">
                                    <div class="font-mono font-semibold text-gray-800 text-2xs mb-1">
                                        <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?> <span class="text-rose-700 text-3xs font-normal uppercase">(Removed)</span>
                                    </div>
                                    <div class="bg-rose-50 text-rose-800 p-1.5 rounded border border-rose-200 font-mono text-2xs break-all">
                                        <?= htmlspecialchars(formatDiffValue($diff['before']), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-2.5 bg-white flex items-center justify-between text-2xs">
                                    <span class="font-mono text-gray-500"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="font-mono text-gray-700 truncate max-w-xs"><?= htmlspecialchars(formatDiffValue($diff['after']), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Collapsible Raw JSON Payloads -->
            <?php if ($hasBefore || $hasAfter): ?>
                <div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
                    <details class="group">
                        <summary class="px-4 py-3 bg-gray-50 flex items-center justify-between cursor-pointer select-none">
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Raw JSON Payloads</span>
                            <span class="text-xs text-indigo-600 group-open:rotate-180 transition-transform">▼</span>
                        </summary>

                        <div class="p-4 space-y-4">
                            <?php if ($hasBefore): ?>
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-2xs font-semibold text-gray-500 uppercase tracking-wider">Payload Before</span>
                                        <button type="button"
                                                id="btn-copy-before"
                                                onclick="copyAuditJson('btn-copy-before', 'raw-json-before');"
                                                class="text-3xs font-semibold text-gray-500 hover:text-indigo-600 px-2 py-0.5 rounded border border-gray-200 bg-white shadow-2xs cursor-pointer">
                                            Copy JSON
                                        </button>
                                    </div>
                                    <pre id="raw-json-before" class="p-3 bg-gray-900 text-gray-100 rounded-lg text-2xs font-mono overflow-x-auto max-h-48 leading-relaxed"><?= htmlspecialchars($rawBefore, ENT_QUOTES, 'UTF-8') ?></pre>
                                </div>
                            <?php endif; ?>

                            <?php if ($hasAfter): ?>
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-2xs font-semibold text-gray-500 uppercase tracking-wider">Payload After</span>
                                        <button type="button"
                                                id="btn-copy-after"
                                                onclick="copyAuditJson('btn-copy-after', 'raw-json-after');"
                                                class="text-3xs font-semibold text-gray-500 hover:text-indigo-600 px-2 py-0.5 rounded border border-gray-200 bg-white shadow-2xs cursor-pointer">
                                            Copy JSON
                                        </button>
                                    </div>
                                    <pre id="raw-json-after" class="p-3 bg-gray-900 text-gray-100 rounded-lg text-2xs font-mono overflow-x-auto max-h-48 leading-relaxed"><?= htmlspecialchars($rawAfter, ENT_QUOTES, 'UTF-8') ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

        </div>

        <!-- Drawer Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end">
            <button type="button"
                    onclick="closeAuditDrawer();"
                    class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-100 text-gray-700 text-xs font-medium rounded-lg shadow-2xs transition cursor-pointer">
                Close Inspector
            </button>
        </div>

    </div>
</div>
