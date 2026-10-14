<?php
/**
 * @var array<string, mixed> $reservation
 * @var array<string, mixed>|null $registry
 * @var string $publicSiteUrl
 * @var \OceanViewFlats\Admin\Service\PublicUrlBuilder $urlBuilder
 */

$urlBuilder = (isset($urlBuilder) && $urlBuilder instanceof \OceanViewFlats\Admin\Service\PublicUrlBuilder)
    ? $urlBuilder
    : new \OceanViewFlats\Admin\Service\PublicUrlBuilder($publicSiteUrl);

$uid = (string) ($reservation['reservation_uid'] ?? '');
$propertyId = (string) ($reservation['property_id'] ?? '');
$guestName = (string) ($reservation['guest_name'] ?? '');
$guestEmail = (string) ($reservation['guest_email'] ?? '');
$guestPhone = (string) ($reservation['guest_phone'] ?? '');

$registryCompleted = (int) ($reservation['registry_completed'] ?? 0) === 1 && $registry !== null;
$guestsPayload = [];
if ($registry !== null) {
    if (is_array($registry['guests_payload'] ?? null)) {
        $guestsPayload = $registry['guests_payload'];
    } elseif (is_string($registry['guests_payload'] ?? null)) {
        $decoded = json_decode($registry['guests_payload'], true);
        if (is_array($decoded)) {
            $guestsPayload = $decoded;
        }
    }
}
$primaryGuestEmail = '';
foreach ($guestsPayload as $i => $g) {
    if (!empty($g['is_primary']) || $i === 0) {
        $primaryGuestEmail = (string) ($g['email'] ?? '');
        break;
    }
}
if ($primaryGuestEmail === '') {
    $primaryGuestEmail = $guestEmail;
}
$carPlates = (string) ($registry['car_plates'] ?? '');
$carModel = (string) ($registry['car_model'] ?? '');
$submittedAt = (string) ($registry['created_at'] ?? '');
$ipAddress = (string) ($registry['ip_address'] ?? '');

$checkIn = (string) ($reservation['check_in'] ?? '');
$checkOut = (string) ($reservation['check_out'] ?? '');
$status = (string) ($reservation['status'] ?? 'pending_payment');
$lang = (string) ($reservation['lang'] ?? 'es');
if ($lang === '') {
    $lang = 'es';
}

$isConcluded = false;
if ($status === 'confirmed' && $checkOut !== '') {
    try {
        $tzCot = new \DateTimeZone('America/Bogota');
        $nowCot = new \DateTimeImmutable('now', $tzCot);
        $concludedThreshold = new \DateTimeImmutable($checkOut . ' 23:59:59', $tzCot);
        $isConcluded = $nowCot > $concludedThreshold;
    } catch (\Throwable) {
        $isConcluded = false;
    }
}
$isEditable = ($status === 'confirmed' && !$isConcluded);

$regParams = ['property' => $propertyId];
if ($checkIn !== '') {
    $regParams['check_in'] = $checkIn;
}
if ($checkOut !== '') {
    $regParams['check_out'] = $checkOut;
}
$regParams['code'] = $uid;

$registryUrl = $urlBuilder->buildRegistryUrl($lang, $regParams);
$guideUrl = $urlBuilder->buildGuideUrl($lang, $uid);
?>

<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
         onclick="document.getElementById('modal-container').innerHTML = '';"
         aria-hidden="true"></div>

    <div class="min-h-full flex items-center justify-center p-4 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-2xl sm:w-full border border-gray-200">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?= $registryCompleted ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                        <?php if ($registryCompleted): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        <?php else: ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900" id="modal-title">
                            <?= $registryCompleted ? 'Guest Registry Dossier' : 'Guest Registry Pending' ?>
                        </h3>
                        <p class="text-xs text-gray-500 font-mono">
                            Reservation UID: <?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
                <button type="button"
                        onclick="document.getElementById('modal-container').innerHTML = '';"
                        class="text-gray-400 hover:text-gray-600 rounded-lg p-1.5 hover:bg-gray-100 transition cursor-pointer">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="px-6 py-6 space-y-6">

                <?php if ($registryCompleted): ?>
                    <!-- COMPLETED DOSSIER -->
                    
                    <!-- Submission Metadata Bar -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs bg-gray-50 p-3 rounded-xl border border-gray-200">
                        <div>
                            <span class="block text-gray-400">Total Guests</span>
                            <span class="font-bold text-gray-800"><?= count($guestsPayload) ?> Occupants</span>
                        </div>
                        <div>
                            <span class="block text-gray-400">Submitted</span>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div>
                            <span class="block text-gray-400">IP Address</span>
                            <span class="font-mono text-gray-800"><?= htmlspecialchars($ipAddress !== '' ? $ipAddress : 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div>
                            <span class="block text-gray-400">Vehicle</span>
                            <span class="font-medium text-gray-800"><?= $carPlates !== '' ? htmlspecialchars($carPlates . ' (' . $carModel . ')', ENT_QUOTES, 'UTF-8') : 'None' ?></span>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <span class="block text-gray-400">Primary Email</span>
                            <?php if ($primaryGuestEmail !== ''): ?>
                                <span class="inline-flex items-center text-[11px] font-mono font-medium text-emerald-700 truncate" title="<?= htmlspecialchars($primaryGuestEmail, ENT_QUOTES, 'UTF-8') ?>">
                                    <svg class="w-3 h-3 mr-1 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="truncate"><?= htmlspecialchars($primaryGuestEmail, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">None</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Occupants List -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Registered Occupants</h4>
                        <div class="space-y-3">
                            <?php foreach ($guestsPayload as $index => $guest): ?>
                                <?php
                                $firstName = trim((string) ($guest['first_name'] ?? $guest['firstName'] ?? ''));
                                $lastName = trim((string) ($guest['last_name'] ?? $guest['lastName'] ?? ''));
                                $combinedName = trim($firstName . ' ' . $lastName);
                                $fullName = trim((string) ($guest['full_name'] ?? $guest['name'] ?? ($combinedName !== '' ? $combinedName : 'Occupant ' . ($index + 1))));
                                $docType = (string) ($guest['doc_type'] ?? $guest['docType'] ?? 'ID');
                                $docNumber = (string) ($guest['doc_number'] ?? $guest['doc_num'] ?? $guest['docNum'] ?? '');
                                $country = trim((string) ($guest['country'] ?? $guest['nationality'] ?? ''));
                                $countryDisplay = $country !== '' ? $country : 'Not specified';
                                $age = $guest['age'] ?? null;
                                $hasAge = ($age !== null && $age !== '' && is_numeric($age));
                                $phone = trim((string) ($guest['phone'] ?? ''));
                                $isPrimary = !empty($guest['is_primary']) || $index === 0 || ((int) ($guest['index'] ?? 0) === 1);
                                $occupantEmail = (string) ($guest['email'] ?? ($isPrimary ? $primaryGuestEmail : ''));
                                ?>
                                <div class="p-3.5 rounded-xl border border-gray-200 bg-white hover:border-indigo-200 transition">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                            <span class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($isPrimary): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 uppercase">
                                                    Primary Guest
                                                </span>
                                                <?php if ($occupantEmail !== ''): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200" title="Verified Primary Guest Email">
                                                        <svg class="w-3 h-3 mr-1 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span class="font-mono"><?= htmlspecialchars($occupantEmail, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="ml-1 text-[9px] uppercase tracking-wider font-semibold text-emerald-600">Verified</span>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 uppercase">
                                                    Companion
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs font-mono text-gray-700">
                                            <span class="font-semibold text-gray-500"><?= htmlspecialchars($docType, ENT_QUOTES, 'UTF-8') ?>:</span> <?= htmlspecialchars($docNumber, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 flex items-center space-x-4 flex-wrap gap-y-1">
                                        <?php if ($isPrimary && $occupantEmail !== ''): ?>
                                            <span>Email: <a href="mailto:<?= htmlspecialchars($occupantEmail, ENT_QUOTES, 'UTF-8') ?>" class="text-indigo-600 hover:underline font-mono"><?= htmlspecialchars($occupantEmail, ENT_QUOTES, 'UTF-8') ?></a></span>
                                        <?php endif; ?>
                                        <?php if ($phone !== ''): ?>
                                            <span>Phone: <strong class="text-gray-700"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></strong></span>
                                        <?php endif; ?>
                                        <span>Country: <strong class="text-gray-700"><?= htmlspecialchars($countryDisplay, ENT_QUOTES, 'UTF-8') ?></strong></span>
                                        <?php if ($hasAge): ?>
                                            <span>Age: <strong class="text-gray-700"><?= (int) $age ?></strong></span>
                                        <?php endif; ?>
                                        <?php if (!empty($guest['birthdate'])): ?>
                                            <span>Birthdate: <strong class="text-gray-700"><?= htmlspecialchars((string) $guest['birthdate'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Copyable Guest Guide Link (Completed / Unlocked) -->
                        <div class="space-y-1.5 pt-2 border-t border-gray-100">
                            <label for="guide-link-input-completed" class="block text-xs font-semibold text-gray-700">
                                Direct Guest Guide Link (Unlocked Access):
                            </label>
                            <div class="flex rounded-lg shadow-2xs">
                                <input id="guide-link-input-completed"
                                       type="text"
                                       readonly
                                       value="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       class="flex-1 block w-full px-3 py-2 text-xs font-mono bg-gray-50 border border-gray-300 rounded-l-lg select-all focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <button type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('guide-link-input-completed').value).then(() => { this.innerText = 'Copied!'; setTimeout(() => this.innerText = 'Copy Guide Link', 2000); });"
                                        class="inline-flex items-center px-4 py-2 border border-l-0 border-indigo-600 text-xs font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition cursor-pointer">
                                    Copy Guide Link
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-400">
                                Direct link for the guest to access apartment and Wi-Fi credentials.
                            </p>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- PENDING STATE -->
                    <div class="text-center py-4">
                        <div class="mx-auto w-12 h-12 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h4 class="text-base font-semibold text-gray-900">Mandatory Guest Registry Not Submitted</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-md mx-auto">
                            Per condominium regulations and ADR 0001, Access Credentials cannot be dispatched until all staying occupants are registered.
                        </p>
                    </div>

                    <!-- Guest Contact Details -->
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 text-xs space-y-2">
                        <div class="font-bold text-gray-700 uppercase tracking-wider text-[11px]">Primary Guest Contact</div>
                        <div class="flex justify-between text-gray-600">
                            <span>Name:</span>
                            <span class="font-semibold text-gray-900"><?= htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Email:</span>
                            <span class="font-mono text-gray-800"><?= htmlspecialchars($guestEmail, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Phone:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($guestPhone, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <!-- Copyable Registration & Guide Links -->
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label for="registry-link-input" class="block text-xs font-semibold text-gray-700">
                                Direct Guest Registry Link:
                            </label>
                            <div class="flex rounded-lg shadow-2xs">
                                <input id="registry-link-input"
                                       type="text"
                                       readonly
                                       value="<?= htmlspecialchars($registryUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       class="flex-1 block w-full px-3 py-2 text-xs font-mono bg-gray-50 border border-gray-300 rounded-l-lg select-all focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <button type="button"
                                        id="copy-registry-btn"
                                        onclick="navigator.clipboard.writeText(document.getElementById('registry-link-input').value).then(() => { this.innerText = 'Copied!'; setTimeout(() => this.innerText = 'Copy Link', 2000); });"
                                        class="inline-flex items-center px-4 py-2 border border-l-0 border-indigo-600 text-xs font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition cursor-pointer">
                                    Copy Link
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-400">
                                Send this secure pre-filled link to the Primary Guest via WhatsApp or SMS to expedite their registration.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="guide-link-input-pending" class="block text-xs font-semibold text-gray-700">
                                Direct Guest Guide Link:
                            </label>
                            <div class="flex rounded-lg shadow-2xs">
                                <input id="guide-link-input-pending"
                                       type="text"
                                       readonly
                                       value="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       class="flex-1 block w-full px-3 py-2 text-xs font-mono bg-gray-50 border border-gray-300 rounded-l-lg select-all focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <button type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('guide-link-input-pending').value).then(() => { this.innerText = 'Copied!'; setTimeout(() => this.innerText = 'Copy Guide Link', 2000); });"
                                        class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 text-xs font-medium rounded-r-lg text-gray-700 bg-gray-100 hover:bg-gray-200 transition cursor-pointer">
                                    Copy Guide Link
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-400">
                                Note: Access credentials remain locked until the guest completes the registry.
                            </p>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between gap-3">
                <div>
                    <?php if ($isEditable): ?>
                        <button type="button"
                                hx-get="/reservations/<?= urlencode($uid) ?>/edit"
                                hx-target="#modal-container"
                                hx-swap="innerHTML"
                                class="px-4 py-2 border border-transparent rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition cursor-pointer inline-flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit Reservation &amp; Guests
                        </button>
                    <?php endif; ?>
                </div>
                <button type="button"
                        onclick="document.getElementById('modal-container').innerHTML = '';"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                    Dismiss
                </button>
            </div>

        </div>
    </div>
</div>
