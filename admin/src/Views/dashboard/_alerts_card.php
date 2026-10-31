<?php
/**
 * HTMX Partial: Dashboard Operational Alerts Card
 *
 * @var \OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData $viewData
 * @var string $propertyId
 * @var string|null $csrfToken
 */
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;

$alerts = $viewData->alerts;
$alertCount = count($alerts);
$csrfTokenValue = $csrfToken ?? '';
?>
<div class="bg-white rounded-xl border <?= $alertCount > 0 ? 'border-rose-200' : 'border-gray-200' ?> shadow-2xs overflow-hidden">
    <!-- Card Header -->
    <div class="px-5 py-3.5 <?= $alertCount > 0 ? 'bg-rose-50/80 border-b border-rose-100' : 'bg-gray-50/70 border-b border-gray-200' ?> flex items-center justify-between">
        <h3 class="text-xs font-bold <?= $alertCount > 0 ? 'text-rose-900' : 'text-gray-900' ?> uppercase tracking-wider flex items-center gap-2">
            <?php if ($alertCount > 0): ?>
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
            <?php endif; ?>
            Operational Attention
        </h3>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full <?= $alertCount > 0 ? 'bg-rose-200 text-rose-800' : 'bg-gray-200 text-gray-700' ?>">
            <?= $alertCount ?> <?= $alertCount === 1 ? 'item' : 'items' ?>
        </span>
    </div>

    <!-- Alerts List -->
    <div class="p-4 space-y-3">
        <?php if ($alertCount === 0): ?>
            <div class="p-4 text-center">
                <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <p class="text-xs font-medium text-gray-900">All Operations Clear</p>
                <p class="text-[11px] text-gray-500 mt-0.5">No missing guest registries or pending channel blocks.</p>
            </div>
        <?php else: ?>
            <?php foreach ($alerts as $alert): ?>
                <?php
                $isCritical = $alert->severity === AlertSeverity::CRITICAL;
                $isWarning = $alert->severity === AlertSeverity::WARNING;
                $cardClasses = $isCritical
                    ? 'bg-rose-50/70 border-rose-200'
                    : ($isWarning ? 'bg-amber-50/70 border-amber-200' : 'bg-indigo-50/70 border-indigo-200');
                $titleClasses = $isCritical
                    ? 'text-rose-900'
                    : ($isWarning ? 'text-amber-900' : 'text-indigo-900');
                ?>
                <div class="p-3.5 rounded-xl border <?= $cardClasses ?> text-xs space-y-2.5 shadow-2xs">
                    <!-- Top Row: Title & Property Badge -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-1.5 flex-1 min-w-0">
                            <?php if ($isCritical): ?>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shrink-0">CRITICAL</span>
                            <?php elseif ($isWarning): ?>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white shrink-0">WARNING</span>
                            <?php else: ?>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-600 text-white shrink-0">INFO</span>
                            <?php endif; ?>
                            <span class="font-bold <?= $titleClasses ?> truncate"><?= htmlspecialchars($alert->title, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold shrink-0 <?= $alert->propertyId === '1606' ? 'bg-sky-100 text-sky-800 border border-sky-200' : 'bg-purple-100 text-purple-800 border border-purple-200' ?>">
                            Apt <?= htmlspecialchars($alert->propertyId, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <!-- Description & Metadata -->
                    <p class="text-[11px] text-gray-700 leading-relaxed">
                        <?= htmlspecialchars($alert->description, ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <!-- Quick Action Buttons -->
                    <div class="pt-1 flex items-center gap-2">
                        <?php if ($alert->type === AlertType::INCOMPLETE_GUEST_REGISTRY && !empty($alert->reservationUid)): ?>
                            <a href="/reservations/<?= urlencode($alert->reservationUid) ?>"
                               hx-get="/reservations/<?= urlencode($alert->reservationUid) ?>"
                               hx-target="#drawer-container"
                               hx-swap="innerHTML"
                               role="button"
                               class="px-2.5 py-1 bg-white border border-gray-300 text-gray-700 font-semibold rounded-md shadow-2xs hover:bg-gray-50 text-[11px] transition">
                                Inspect
                            </a>
                            <button type="button"
                                    data-invite-url="<?= htmlspecialchars((string) ($alert->actionPayload['registryUrl'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-guest-name="<?= htmlspecialchars((string) ($alert->guestName ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-prop-id="<?= htmlspecialchars($alert->propertyId, ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="copyRegistryInvite(this)"
                                    class="px-2.5 py-1 bg-indigo-600 text-white font-semibold rounded-md shadow-2xs hover:bg-indigo-700 text-[11px] transition inline-flex items-center space-x-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                <span>Copy Invite</span>
                            </button>
                        <?php elseif ($alert->type === AlertType::FAILED_CONDOMINIUM_CLEARANCE && !empty($alert->reservationUid)): ?>
                            <a href="/reservations/<?= urlencode($alert->reservationUid) ?>"
                               hx-get="/reservations/<?= urlencode($alert->reservationUid) ?>"
                               hx-target="#drawer-container"
                               hx-swap="innerHTML"
                               role="button"
                               class="px-2.5 py-1 bg-white border border-gray-300 text-gray-700 font-semibold rounded-md shadow-2xs hover:bg-gray-50 text-[11px] transition">
                                Inspect
                            </a>
                        <?php elseif ($alert->type === AlertType::UNONBOARDED_CHANNEL_BLOCK): ?>
                            <?php
                            $startDate = (string) ($alert->actionPayload['start_date'] ?? $alert->actionPayload['startDate'] ?? '');
                            $endDate = (string) ($alert->actionPayload['end_date'] ?? $alert->actionPayload['endDate'] ?? '');
                            $onboardUrl = '/reservations/new?property_id=' . urlencode($alert->propertyId)
                                . '&check_in=' . urlencode($startDate)
                                . '&check_out=' . urlencode($endDate)
                                . '&source=airbnb';
                            ?>
                            <a href="<?= htmlspecialchars($onboardUrl, ENT_QUOTES, 'UTF-8') ?>"
                               hx-get="<?= htmlspecialchars($onboardUrl, ENT_QUOTES, 'UTF-8') ?>"
                               hx-target="#modal-container"
                               hx-swap="innerHTML"
                               role="button"
                               class="flex-1 text-center px-3 py-1 bg-rose-600 text-white font-semibold rounded-md shadow-2xs hover:bg-rose-700 text-[11px] transition">
                                Onboard Airbnb Guest &rarr;
                            </a>
                        <?php elseif ($alert->type === AlertType::PENDING_PARAFISCAL_SETTLEMENT): ?>
                            <?php
                            $parafiscalTargetUrl = (string) ($alert->actionPayload['target_url'] ?? '/financial-report#fontur-parafiscal-section');
                            ?>
                            <a href="<?= htmlspecialchars($parafiscalTargetUrl, ENT_QUOTES, 'UTF-8') ?>"
                               role="button"
                               class="flex-1 text-center px-3 py-1 <?= $isCritical ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white' ?> font-semibold rounded-md shadow-2xs text-[11px] transition">
                                Review &amp; Settle in Financial Report &rarr;
                            </a>
                        <?php endif; ?>
                        <form method="POST"
                              action="/dashboard/alerts/dismiss"
                              hx-post="/dashboard/alerts/dismiss"
                              hx-target="#dashboard-hub-content"
                              hx-swap="outerHTML"
                              hx-headers='{"X-CSRF-Token": "<?= htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') ?>"}'
                              class="inline ml-auto">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="alert_id" value="<?= htmlspecialchars($alert->id, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="alert_type" value="<?= htmlspecialchars($alert->type->value, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="property_id" value="<?= htmlspecialchars($alert->propertyId, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="filter_property_id" value="<?= htmlspecialchars($propertyId, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (!empty($alert->reservationUid)): ?>
                                <input type="hidden" name="reservation_uid" value="<?= htmlspecialchars($alert->reservationUid, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?>
                            <button type="submit"
                                    hx-post="/dashboard/alerts/dismiss"
                                    hx-target="#dashboard-hub-content"
                                    hx-swap="outerHTML"
                                    hx-headers='{"X-CSRF-Token": "<?= htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') ?>"}'
                                    class="px-2.5 py-1 bg-white border border-gray-300 text-gray-600 font-semibold rounded-md shadow-2xs hover:bg-gray-100 hover:text-gray-900 text-[11px] transition cursor-pointer">
                                Dismiss
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
