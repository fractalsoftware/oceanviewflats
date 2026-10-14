<?php
/**
 * @var array<string, mixed> $reservation
 * @var list<array<string, mixed>> $auditLogs
 * @var list<array<string, mixed>>|null $refunds
 * @var \OceanViewFlats\Domain\Fulfillment\CondominiumClearance|null $condominiumClearance
 * @var string $csrfToken
 * @var string $publicSiteUrl
 * @var \OceanViewFlats\Admin\Service\PublicUrlBuilder|null $urlBuilder
 */

$condominiumClearance = $condominiumClearance ?? null;
$urlBuilder = $urlBuilder ?? new \OceanViewFlats\Admin\Service\PublicUrlBuilder($publicSiteUrl);

$refunds = $refunds ?? [];
$uid = (string) ($reservation['reservation_uid'] ?? '');
$propertyId = (string) ($reservation['property_id'] ?? '');
$guestName = (string) ($reservation['guest_name'] ?? '');
$guestEmail = (string) ($reservation['guest_email'] ?? '');
$guestPhone = (string) ($reservation['guest_phone'] ?? '');
$checkIn = (string) ($reservation['check_in'] ?? '');
$checkOut = (string) ($reservation['check_out'] ?? '');
$nights = (int) max(1, (strtotime($checkOut) - strtotime($checkIn)) / 86400);
$totalPrice = (float) ($reservation['total_price'] ?? 0.0);
$refundedAmount = (float) ($reservation['refunded_amount'] ?? 0.0);
$status = (string) ($reservation['status'] ?? 'pending_payment');
$source = (string) ($reservation['source'] ?? 'web');
$registryCompleted = (int) ($reservation['registry_completed'] ?? 0) === 1;
$registryCompletedAt = (string) ($reservation['registry_completed_at'] ?? '');
$doorCode = (string) ($reservation['door_code'] ?? '');
$mpPaymentId = (string) ($reservation['mercadopago_payment_id'] ?? '');
$mpPrefId = (string) ($reservation['mercadopago_preference_id'] ?? '');
$paymentStatus = (string) ($reservation['payment_status'] ?? '');
$createdAt = (string) ($reservation['created_at'] ?? '');
$externalConfirmationCode = (string) ($reservation['external_confirmation_code'] ?? '');
$channelBlockUid = (string) ($reservation['channel_block_uid'] ?? '');
$isAirbnbReservation = (strtolower((string) $source) === 'airbnb' || str_starts_with($uid, 'res-abnb-'));
?>

<script>
window.closeReservationDrawer = window.closeReservationDrawer || function() {
    var container = document.getElementById('drawer-container');
    if (container) {
        container.innerHTML = '';
    } else {
        window.location.href = '/reservations';
        return;
    }
    if (window.location.pathname.startsWith('/reservations/')) {
        window.history.pushState(null, '', '/reservations');
    }
};

window.switchDispatchLang = window.switchDispatchLang || function(uid, lang) {
    var ta = document.getElementById('dispatch-snippet-' + uid);
    var btnEs = document.getElementById('btn-lang-es-' + uid);
    var btnEn = document.getElementById('btn-lang-en-' + uid);
    var copyText = document.getElementById('copy-text-' + uid);
    if (!ta) return;
    if (lang === 'es') {
        ta.value = ta.getAttribute('data-text-es') || '';
        ta.setAttribute('data-current-lang', 'es');
        if (btnEs) {
            btnEs.className = 'px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-gray-900 shadow-2xs font-bold';
        }
        if (btnEn) {
            btnEn.className = 'px-2.5 py-1 rounded-md transition cursor-pointer text-gray-500 hover:text-gray-900';
        }
        if (copyText) copyText.innerText = 'Copy ES Message';
    } else {
        ta.value = ta.getAttribute('data-text-en') || '';
        ta.setAttribute('data-current-lang', 'en');
        if (btnEn) {
            btnEn.className = 'px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-gray-900 shadow-2xs font-bold';
        }
        if (btnEs) {
            btnEs.className = 'px-2.5 py-1 rounded-md transition cursor-pointer text-gray-500 hover:text-gray-900';
        }
        if (copyText) copyText.innerText = 'Copy EN Message';
    }
};

window.copyDispatchSnippet = window.copyDispatchSnippet || function(uid) {
    var ta = document.getElementById('dispatch-snippet-' + uid);
    var copyText = document.getElementById('copy-text-' + uid);
    if (!ta) return;
    navigator.clipboard.writeText(ta.value).then(function() {
        if (copyText) {
            var orig = copyText.innerText;
            copyText.innerText = 'Copied to Clipboard!';
            setTimeout(function() { copyText.innerText = orig; }, 2000);
        }
    });
};
</script>

<div class="fixed inset-0 z-40 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs transition-opacity duration-300 cursor-pointer"
         onclick="closeReservationDrawer();"
         aria-hidden="true"></div>

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div class="w-screen max-w-xl bg-white shadow-2xl flex flex-col transform transition ease-in-out duration-300">
            
            <!-- Drawer Header -->
            <div class="px-6 py-5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $propertyId === '1606' ? 'bg-sky-100 text-sky-800' : 'bg-purple-100 text-purple-800' ?>">
                            Apto <?= htmlspecialchars($propertyId, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($status === 'confirmed'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                Confirmed
                            </span>
                        <?php elseif ($status === 'pending_payment'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                Pending Payment
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                Cancelled
                            </span>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900 mt-1" id="slide-over-title">
                        <?= htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="text-xs font-mono text-gray-500 select-all">
                        UID: <?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <button type="button"
                        onclick="closeReservationDrawer();"
                        class="rounded-lg p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition cursor-pointer">
                    <span class="sr-only">Close panel</span>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Drawer Scrollable Body -->
            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 divide-y divide-gray-100">
                
                <!-- 1. Guest & Contact -->
                <div class="space-y-3 pt-1">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Primary Guest</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm bg-gray-50/80 p-4 rounded-xl border border-gray-100">
                        <div>
                            <span class="block text-xs text-gray-500">Full Name</span>
                            <span class="font-medium text-gray-900"><?= htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">Email</span>
                            <a href="mailto:<?= htmlspecialchars($guestEmail, ENT_QUOTES, 'UTF-8') ?>" class="text-indigo-600 hover:underline font-mono text-xs">
                                <?= htmlspecialchars($guestEmail, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">Phone</span>
                            <a href="tel:<?= htmlspecialchars($guestPhone, ENT_QUOTES, 'UTF-8') ?>" class="text-indigo-600 hover:underline text-xs">
                                <?= htmlspecialchars($guestPhone, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">Language</span>
                            <span class="text-xs font-medium uppercase text-gray-700"><?= htmlspecialchars((string) ($reservation['lang'] ?? 'en'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>

                <!-- 2. Stay Details -->
                <div class="space-y-3 pt-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Stay & Accommodation</h3>
                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-xs text-gray-500">Check-in</span>
                            <span class="font-semibold text-gray-900 text-xs sm:text-sm"><?= htmlspecialchars($checkIn, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-xs text-gray-500">Check-out</span>
                            <span class="font-semibold text-gray-900 text-xs sm:text-sm"><?= htmlspecialchars($checkOut, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-xs text-gray-500">Duration</span>
                            <span class="font-semibold text-gray-900 text-xs sm:text-sm"><?= $nights ?> Nights</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500 px-1">
                        <span>Source: <strong class="text-gray-700 uppercase"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <span>Max Guests: <strong class="text-gray-700"><?= (int) ($reservation['max_guests'] ?? 6) ?></strong></span>
                        <span>Booked: <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if ($externalConfirmationCode !== ''): ?>
                        <div class="mt-2 text-xs bg-rose-50/70 border border-rose-200/60 rounded-lg p-2.5 flex items-center justify-between">
                            <div>
                                <span class="text-2xs font-bold uppercase text-rose-700 tracking-wider block">Airbnb Confirmation Code</span>
                                <span class="font-mono font-bold text-gray-900"><?= htmlspecialchars($externalConfirmationCode, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php if ($channelBlockUid !== ''): ?>
                                <div class="text-right">
                                    <span class="text-2xs font-bold uppercase text-gray-400 tracking-wider block">Channel Block</span>
                                    <span class="font-mono text-2xs text-gray-600 truncate max-w-[150px] inline-block" title="<?= htmlspecialchars($channelBlockUid, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($channelBlockUid, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. Financial & Payment Summary -->
                <div class="space-y-3 pt-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Financial Summary</h3>
                    <div class="bg-indigo-50/40 p-4 rounded-xl border border-indigo-100 space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-600">Total Price</span>
                            <span class="font-bold text-gray-900 text-base">$<?= number_format($totalPrice, 0, '.', ',') ?> COP</span>
                        </div>
                        <?php if ($refundedAmount > 0): ?>
                            <div class="flex justify-between items-center text-xs text-rose-600 font-medium">
                                <span>Refunded Amount</span>
                                <span>-$<?= number_format($refundedAmount, 0, '.', ',') ?> COP</span>
                            </div>
                        <?php endif; ?>
                        <div class="pt-2 border-t border-indigo-100/60 flex justify-between text-xs text-gray-500">
                            <span>Payment Status</span>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($paymentStatus !== '' ? $paymentStatus : 'None', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php if ($mpPaymentId !== ''): ?>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Mercado Pago ID</span>
                                <span class="font-mono text-gray-700"><?= htmlspecialchars($mpPaymentId, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($refunds)): ?>
                            <div class="pt-2 border-t border-indigo-100/60 space-y-1.5">
                                <span class="block text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Refund History</span>
                                <?php foreach ($refunds as $ref): ?>
                                    <div class="p-2 bg-white rounded-lg border border-indigo-100/60 text-[11px] space-y-0.5">
                                        <div class="flex justify-between font-semibold text-rose-700">
                                            <span>-$<?= number_format((float) ($ref['amount'] ?? 0), 0, '.', ',') ?> COP</span>
                                            <span class="text-gray-400 font-normal"><?= htmlspecialchars((string) ($ref['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="text-gray-600 flex justify-between">
                                            <span><?= htmlspecialchars((string) ($ref['reason'] ?? 'Refund'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-gray-400 text-[10px] font-mono uppercase"><?= htmlspecialchars((string) ($ref['source'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php if (!empty($ref['mercadopago_refund_id'])): ?>
                                            <div class="text-[10px] font-mono text-gray-400">
                                                Refund ID: <?= htmlspecialchars((string) $ref['mercadopago_refund_id'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4. Access Credentials & Guest Registry -->
                <div class="space-y-3 pt-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Access & Fulfillment</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Door Code Card -->
                        <div class="p-4 rounded-xl border border-gray-200 bg-white shadow-2xs flex flex-col justify-between">
                            <div>
                                <span class="block text-xs text-gray-500 font-medium mb-1">Access Credential (PIN)</span>
                                <?php if ($doorCode !== ''): ?>
                                    <span class="font-mono text-xl font-bold tracking-wider text-indigo-700">
                                        <?= htmlspecialchars($doorCode, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-amber-600 font-medium flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        Not Assigned
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($status === 'confirmed'): ?>
                                <div class="mt-3 pt-2.5 border-t border-gray-100 flex flex-col space-y-1.5">
                                    <button type="button"
                                            hx-post="/reservations/<?= urlencode($uid) ?>/door-code/regenerate"
                                            hx-target="#drawer-container"
                                            hx-confirm="Regenerate this door code? A fresh random PIN will be generated immediately."
                                            class="inline-flex items-center text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 transition cursor-pointer">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Regenerate PIN
                                    </button>

                                    <details class="text-[11px] group">
                                        <summary class="text-gray-500 hover:text-indigo-600 cursor-pointer font-medium select-none">
                                            Override PIN...
                                        </summary>
                                        <form hx-post="/reservations/<?= urlencode($uid) ?>/door-code/override"
                                              hx-target="#drawer-container"
                                              class="mt-1.5 flex items-center space-x-1.5">
                                            <input type="text"
                                                   name="door_code"
                                                   placeholder="0123456#"
                                                   pattern="^[0-9]{4,10}#?$"
                                                   required
                                                   class="w-24 px-2 py-0.5 text-xs border border-gray-300 rounded font-mono focus:ring-1 focus:ring-indigo-500">
                                            <button type="submit"
                                                    class="px-2 py-0.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition cursor-pointer">
                                                Save
                                            </button>
                                        </form>
                                    </details>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Registry Card -->
                        <div class="p-4 rounded-xl border border-gray-200 bg-white shadow-2xs flex flex-col justify-between">
                            <div>
                                <span class="block text-xs text-gray-500 font-medium mb-1">Guest Registry</span>
                                <?php if ($registryCompleted): ?>
                                    <span class="inline-flex items-center text-xs font-semibold text-emerald-700">
                                        <svg class="w-3.5 h-3.5 mr-1 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        Completed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center text-xs font-semibold text-amber-700">
                                        <svg class="w-3.5 h-3.5 mr-1 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                                        Pending Submission
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-2.5 border-t border-gray-100 flex flex-col space-y-1.5">
                                <button type="button"
                                        hx-get="/reservations/<?= urlencode($uid) ?>/registry"
                                        hx-target="#modal-container"
                                        hx-swap="innerHTML"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer text-left">
                                    <?= $registryCompleted ? 'Inspect Registry &rarr;' : 'View Pending Details &rarr;' ?>
                                </button>
                                <?php
                                $reservationLang = (string) ($reservation['lang'] ?? 'en');
                                $guideLink = $urlBuilder->buildGuideUrl($reservationLang, $uid);
                                ?>
                                <button type="button"
                                        onclick="navigator.clipboard.writeText('<?= htmlspecialchars($guideLink, ENT_QUOTES, 'UTF-8') ?>').then(() => { this.innerText = 'Guide Link Copied!'; setTimeout(() => this.innerText = 'Copy Guest Guide Link', 2000); });"
                                        class="inline-flex items-center text-[11px] font-semibold text-slate-700 hover:text-indigo-600 transition cursor-pointer text-left">
                                    <svg class="w-3 h-3 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    Copy Guest Guide Link
                                </button>
                                <?php if (!$registryCompleted): ?>
                                    <button type="button"
                                            hx-post="/reservations/<?= urlencode($uid) ?>/registry/complete"
                                            hx-target="#drawer-container"
                                            hx-confirm="Mark guest registry as manually verified? This generates an access credential if not already set."
                                            class="inline-flex items-center text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 transition cursor-pointer">
                                        <svg class="w-3 h-3 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Complete Manually
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Condominium Clearance Card -->
                    <div class="p-4 rounded-xl border border-gray-200 bg-white shadow-2xs space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="block text-xs text-gray-500 font-medium mb-0.5">Condominium Clearance</span>
                                <span class="text-[11px] text-gray-400">Condominium Administration Portal registration & building entry clearance</span>
                            </div>
                            <div>
                                <?php if ($condominiumClearance !== null && $condominiumClearance->isSynced()): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Synced
                                    </span>
                                <?php elseif ($condominiumClearance !== null && $condominiumClearance->isFailed()): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                        </svg>
                                        Failed
                                    </span>
                                <?php elseif ($condominiumClearance !== null && $condominiumClearance->isPending()): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Pending Sync
                                    </span>
                                <?php elseif ($registryCompleted): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Awaiting Initial Clearance Sync
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                        Awaiting Guest Registry
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($condominiumClearance !== null && $condominiumClearance->isSynced()): ?>
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-xs text-gray-600">
                                <div>
                                    <span class="text-gray-400 mr-1">Clearance #:</span>
                                    <span class="font-mono font-bold text-gray-900 text-sm">#<?= htmlspecialchars((string) $condominiumClearance->clearanceNumber, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php if ($condominiumClearance->syncedAt): ?>
                                    <div>
                                        <span class="text-gray-400 mr-1">Synced:</span>
                                        <span class="font-medium text-gray-700"><?= htmlspecialchars(date('M j, Y H:i', (int) strtotime($condominiumClearance->syncedAt)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($condominiumClearance->attempts > 1): ?>
                                    <div>
                                        <span class="text-gray-400 mr-1">Attempts:</span>
                                        <span class="text-gray-500"><?= (int) $condominiumClearance->attempts ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($condominiumClearance !== null && $condominiumClearance->isFailed()): ?>
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
                                    <div>
                                        <span class="text-gray-400 mr-1">Attempts:</span>
                                        <span class="font-semibold text-rose-600"><?= (int) $condominiumClearance->attempts ?></span>
                                    </div>
                                    <?php if ($condominiumClearance->lastAttemptAt): ?>
                                        <div>
                                            <span class="text-gray-400 mr-1">Last Attempt:</span>
                                            <span class="text-gray-700"><?= htmlspecialchars(date('M j, Y H:i', (int) strtotime($condominiumClearance->lastAttemptAt)), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 bg-rose-50/80 border border-rose-100 rounded-lg text-xs text-rose-700 font-mono break-all">
                                    <span class="block font-semibold uppercase tracking-wider text-[10px] text-rose-800 mb-0.5">Error details:</span>
                                    <?= htmlspecialchars((string) ($condominiumClearance->errorMessage ?? 'Unknown clearance synchronization error'), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        <?php elseif ($condominiumClearance !== null && $condominiumClearance->isPending()): ?>
                            <div class="text-xs text-gray-500">
                                Clearance is queued or awaiting response from Condominium Administration Portal.
                                <?php if ($condominiumClearance->attempts > 0): ?>
                                    Attempts: <?= (int) $condominiumClearance->attempts ?>.
                                <?php endif; ?>
                            </div>
                        <?php elseif ($registryCompleted): ?>
                            <div class="text-xs text-gray-500">
                                Guest registry is verified. Clearance can now be synchronized with Condominium Administration Portal.
                            </div>
                        <?php else: ?>
                            <div class="text-xs text-gray-400 italic">
                                Clearance synchronization is blocked until the guest completes their check-in registry.
                            </div>
                        <?php endif; ?>

                        <?php if ($registryCompleted && ($condominiumClearance === null || $condominiumClearance->isFailed() || $condominiumClearance->isPending())): ?>
                            <div class="pt-2 border-t border-gray-100 flex items-center justify-between">
                                <button type="button"
                                        hx-post="/reservations/<?= urlencode($uid) ?>/clearance-retry"
                                        hx-target="#drawer-container"
                                        hx-swap="innerHTML"
                                        hx-disabled-elt="this"
                                        hx-headers='{"X-CSRF-Token": "<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"}'
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition cursor-pointer disabled:opacity-50">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span><?= ($condominiumClearance !== null && $condominiumClearance->isFailed()) ? 'Retry Condominium Clearance' : 'Sync Condominium Clearance' ?></span>
                                </button>
                                <span class="text-[11px] text-gray-400">1-click clearance sync</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isAirbnbReservation): ?>
                    <?php
                    $guestFirstName = explode(' ', trim($guestName))[0];
                    $registryUrlEs = $urlBuilder->buildRegistryUrl('es', ['code' => $uid]);
                    $guideUrlEs = $urlBuilder->buildGuideUrl('es', $uid);
                    $registryUrlEn = $urlBuilder->buildRegistryUrl('en', ['code' => $uid]);
                    $guideUrlEn = $urlBuilder->buildGuideUrl('en', $uid);
                    $doorPin = $doorCode !== '' ? $doorCode : '(Generated upon completion)';

                    // Stage 1 vs Stage 2 text strings
                    $esText = $registryCompleted
                        ? "Hola {$guestFirstName}, tu registro ha sido verificado satisfactoriamente. Tu código digital de acceso para la cerradura inteligente es: {$doorPin}. Puedes consultar la guía de llegada, red Wi-Fi y normas del apartamento en este enlace: {$guideUrlEs}\n\n¡Que tengas una excelente estadía en Santa Marta!"
                        : "Hola {$guestFirstName}, ¡gracias por reservar en OceanViewFlats! Para autorizar tu ingreso en la portería del condominio en Playa Salguero y preparar tu llegada, por favor diligencia el registro obligatorio de huéspedes aquí: {$registryUrlEs}\n\nUna vez completado, recibirás de inmediato el código digital de la puerta y la guía completa del apartamento. ¡Quedamos muy atentos!";

                    $enText = $registryCompleted
                        ? "Hello {$guestFirstName}, your guest registration is verified! Your smart door lock access code is: {$doorPin}. You can view full arrival directions, Wi-Fi details, and apartment amenities in your guest guide here: {$guideUrlEn}\n\nEnjoy your stay in Santa Marta!"
                        : "Hello {$guestFirstName}, thank you for booking OceanViewFlats! To ensure security clearance at the Playa Salguero condominium reception and prepare your check-in, please complete our mandatory guest registration here: {$registryUrlEn}\n\nOnce completed, your temporal door code PIN and apartment arrival guide will unlock immediately. We look forward to hosting you!";
                    ?>
                    <!-- 4.1 Airbnb Chat Dispatch Card (ADR 0007 / Variant A) -->
                    <div class="space-y-3 pt-6" id="airbnb-chat-dispatch-section">
                        <div class="bg-white rounded-xl border border-rose-200 shadow-2xs overflow-hidden">
                            <!-- Card Header -->
                            <div class="p-4 bg-rose-50/50 border-b border-rose-100 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-[#FF385C] flex items-center justify-center font-bold">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">Airbnb Chat Dispatch</h3>
                                            <?php if ($registryCompleted): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-bold bg-emerald-100 text-emerald-800">
                                                    Stage 2: Access Dispatched
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-bold bg-amber-100 text-amber-800">
                                                    Stage 1: Registry Required
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-2xs text-gray-500">One-click copyable message for the Airbnb guest messenger inbox.</p>
                                    </div>
                                </div>

                                <!-- Language Segmented Pill Toggle -->
                                <div class="flex items-center bg-gray-100 p-0.5 rounded-lg border border-gray-200 text-2xs font-semibold">
                                    <button type="button"
                                            id="btn-lang-es-<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="switchDispatchLang('<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>', 'es')"
                                            class="px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-gray-900 shadow-2xs font-bold">
                                        ES
                                    </button>
                                    <button type="button"
                                            id="btn-lang-en-<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="switchDispatchLang('<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>', 'en')"
                                            class="px-2.5 py-1 rounded-md transition cursor-pointer text-gray-500 hover:text-gray-900">
                                        EN
                                    </button>
                                </div>
                            </div>

                            <!-- Pre-formatted Message Snippet Body -->
                            <div class="p-4 space-y-3">
                                <textarea id="dispatch-snippet-<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>"
                                          readonly
                                          rows="6"
                                          data-text-es="<?= htmlspecialchars($esText, ENT_QUOTES, 'UTF-8') ?>"
                                          data-text-en="<?= htmlspecialchars($enText, ENT_QUOTES, 'UTF-8') ?>"
                                          data-current-lang="es"
                                          class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-mono text-gray-800 whitespace-pre-wrap select-all leading-relaxed focus:outline-none focus:ring-1 focus:ring-rose-400"><?= htmlspecialchars($esText, ENT_QUOTES, 'UTF-8') ?></textarea>

                                <div class="flex items-center justify-between text-2xs">
                                    <span class="text-gray-500 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?php if ($registryCompleted): ?>
                                            ADR 0001: Registry complete. Door PIN and Guide are unlocked.
                                        <?php else: ?>
                                            ADR 0001: Door PIN and Guide are locked until registry is completed.
                                        <?php endif; ?>
                                    </span>
                                    <button type="button"
                                            id="btn-copy-dispatch-<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="copyDispatchSnippet('<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>')"
                                            class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-[#FF385C] hover:bg-[#E00B41] text-white font-bold shadow-2xs transition cursor-pointer">
                                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                        <span id="copy-text-<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">Copy ES Message</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 5. Audit Trail Timeline -->
                <div class="space-y-4 pt-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Operational Audit Trail</h3>
                    <?php if (empty($auditLogs)): ?>
                        <p class="text-xs text-gray-400 italic">No operational changes recorded for this reservation yet.</p>
                    <?php else: ?>
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                <?php foreach ($auditLogs as $idx => $log): ?>
                                    <?php
                                    $action = (string) ($log['action'] ?? 'update');
                                    $adminName = (string) ($log['admin_user_name'] ?? 'System / Automated');
                                    $timestamp = (string) ($log['created_at'] ?? '');
                                    $isLast = $idx === count($auditLogs) - 1;
                                    ?>
                                    <li>
                                        <div class="relative pb-8">
                                            <?php if (!$isLast): ?>
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            <?php endif; ?>
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center ring-8 ring-white">
                                                        <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-xs text-gray-900 font-medium">
                                                            <span class="inline-block px-1.5 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-800 rounded mr-1 uppercase">
                                                                <?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                            by <span class="font-semibold text-gray-800"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span>
                                                        </p>
                                                        <?php if (!empty($log['payload_after'])): ?>
                                                            <details class="mt-1 text-[11px] text-gray-500">
                                                                <summary class="cursor-pointer hover:text-indigo-600">View Payload Details</summary>
                                                                <pre class="mt-1 p-2 bg-gray-50 rounded text-[10px] font-mono text-gray-700 overflow-x-auto border border-gray-100"><?= htmlspecialchars((string) $log['payload_after'], ENT_QUOTES, 'UTF-8') ?></pre>
                                                            </details>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-right text-[11px] whitespace-nowrap text-gray-400">
                                                        <?= htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Drawer Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                <div>
                    <?php if ($status !== 'cancelled'): ?>
                        <button type="button"
                                hx-get="/reservations/<?= urlencode($uid) ?>/cancel-modal"
                                hx-target="#modal-container"
                                hx-swap="innerHTML"
                                class="px-3.5 py-2 border border-rose-200 rounded-lg shadow-2xs text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 hover:border-rose-300 transition cursor-pointer flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancel Reservation...
                        </button>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                            Reservation Cancelled
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button"
                        onclick="closeReservationDrawer();"
                        class="px-4 py-2 border border-gray-300 rounded-lg shadow-2xs text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
