<?php
/**
 * @var array<string, mixed> $reservation
 * @var array<string, mixed>|null $registry
 * @var list<array<string, mixed>>|null $occupants
 * @var string|null $carPlates
 * @var string|null $carModel
 * @var \OceanViewFlats\Domain\Fulfillment\CondominiumClearance|null $condominiumClearance
 * @var bool|null $clearanceModifiedAfterSync
 * @var bool|null $syncCondominiumClearance
 * @var bool|null $sendAccessDispatch
 * @var bool|null $resetGuestRegistry
 * @var string|null $errorMessage
 * @var string|null $propertyId
 * @var string|null $checkIn
 * @var string|null $checkOut
 * @var string|null $source
 * @var string|null $externalConfirmationCode
 * @var string|null $channelBlockUid
 * @var string|null $guestName
 * @var string|null $guestEmail
 * @var string|null $guestPhone
 * @var string|null $lang
 * @var string|null $totalPrice
 * @var string|null $notes
 * @var int|string|null $maxGuests
 * @var int|null $registeredGuestCount
 * @var string|null $quotePreviewHtml
 * @var string $csrfToken
 */

$registry = $registry ?? null;
$condominiumClearance = $condominiumClearance ?? null;

$uid = (string) ($reservation['reservation_uid'] ?? '');
$propIdVal = (isset($propertyId) && $propertyId !== '')
    ? (string) $propertyId
    : (string) ($reservation['property_id'] ?? '1606');
$sourceVal = (isset($source) && $source !== '')
    ? (string) $source
    : (string) ($reservation['source'] ?? 'web');
$isAirbnb = (strtolower($sourceVal) === 'airbnb' || str_starts_with($uid, 'res-abnb-'));

$checkInVal = isset($checkIn) ? (string) $checkIn : (string) ($reservation['check_in'] ?? '');
$checkOutVal = isset($checkOut) ? (string) $checkOut : (string) ($reservation['check_out'] ?? '');
$totalPriceVal = isset($totalPrice) && (string) $totalPrice !== ''
    ? (string) $totalPrice
    : (string) ($reservation['total_price'] ?? '0');
$maxGuestsVal = isset($maxGuests) && (string) $maxGuests !== ''
    ? (string) $maxGuests
    : (string) ($reservation['max_guests'] ?? 6);
$guestNameVal = isset($guestName) ? (string) $guestName : (string) ($reservation['guest_name'] ?? '');
$guestEmailVal = isset($guestEmail) ? (string) $guestEmail : (string) ($reservation['guest_email'] ?? '');
$guestPhoneVal = isset($guestPhone) ? (string) $guestPhone : (string) ($reservation['guest_phone'] ?? '');
$langVal = isset($lang) && $lang !== '' ? (string) $lang : (string) ($reservation['lang'] ?? 'es');
$externalConfirmationCodeVal = isset($externalConfirmationCode)
    ? (string) $externalConfirmationCode
    : (string) ($reservation['external_confirmation_code'] ?? '');
$channelBlockUidVal = isset($channelBlockUid)
    ? (string) $channelBlockUid
    : (string) ($reservation['channel_block_uid'] ?? '');
$notesVal = isset($notes) ? (string) $notes : (string) ($reservation['notes'] ?? '');

$carPlatesVal = isset($carPlates) ? (string) $carPlates : (string) ($registry['car_plates'] ?? '');
$carModelVal = isset($carModel) ? (string) $carModel : (string) ($registry['car_model'] ?? '');

$occupantsList = [];
if (isset($occupants)) {
    $occupantsList = $occupants;
} elseif ($registry !== null) {
    $rawPayload = $registry['guests_payload'] ?? [];
    if (is_string($rawPayload)) {
        $decoded = json_decode($rawPayload, true);
        $rawPayload = is_array($decoded) ? $decoded : [];
    }
    if (is_array($rawPayload)) {
        $occupantsList = array_values($rawPayload);
    }
}

$resetRegistryVal = !empty($resetGuestRegistry);
if ($resetRegistryVal) {
    $occupantsList = [];
}

$currentOccupantCount = min(12, count($occupantsList));
$registeredCount = isset($registeredGuestCount) ? (int) $registeredGuestCount : $currentOccupantCount;
$minMaxGuests = max(1, $currentOccupantCount);

$registryCompleted = (int) ($reservation['registry_completed'] ?? 0) === 1;
$doorCode = (string) ($reservation['door_code'] ?? '');

$hasSyncedClearance = $condominiumClearance !== null && ($condominiumClearance->isSynced() || ($condominiumClearance->clearanceNumber ?? '') !== '');
$clearanceNumber = $condominiumClearance !== null ? $condominiumClearance->clearanceNumber : null;
$isClearanceModified = !empty($clearanceModifiedAfterSync);
$syncClearanceChecked = !empty($syncCondominiumClearance);
$sendAccessDispatchChecked = !empty($sendAccessDispatch);

$minCheckOut = '';
if ($checkInVal !== '') {
    try {
        $minCheckOut = (new \DateTimeImmutable($checkInVal))->modify('+1 day')->format('Y-m-d');
    } catch (\Throwable) {
        $minCheckOut = '';
    }
}

$portalCountries = [
    'COLOMBIA' => 'Colombia',
    'ESTADOS UNIDOS' => 'United States (Estados Unidos)',
    'CANADA' => 'Canada',
    'ESPANA' => 'Spain (España)',
    'MEXICO' => 'Mexico (México)',
    'ARGENTINA' => 'Argentina',
    'CHILE' => 'Chile',
    'BRASIL' => 'Brazil (Brasil)',
    'PERU' => 'Peru (Perú)',
    'ECUADOR' => 'Ecuador',
    'PANAMA' => 'Panama (Panamá)',
    'VENEZUELA' => 'Venezuela',
    'ALEMANIA' => 'Germany (Alemania)',
    'FRANCIA' => 'France (Francia)',
    'ITALIA' => 'Italy (Italia)',
    'REINO UNIDO' => 'United Kingdom (Reino Unido)',
    'PAISES BAJOS' => 'Netherlands (Países Bajos)',
    'SUIZA' => 'Switzerland (Suiza)',
    'JAPON' => 'Japan (Japón)',
    'AUSTRALIA' => 'Australia',
    'OTRO' => 'Other (Otro)',
];

$docTypes = [
    'Passport' => 'Passport (PA)',
    'Cédula de Ciudadanía' => 'Cédula de Ciudadanía (CC)',
    'Cédula de Extranjería' => 'Cédula de Extranjería (CE)',
    'Tarjeta de Identidad' => 'Tarjeta de Identidad (TI)',
    'Registro Civil' => 'Registro Civil (RC)',
    'National ID' => 'National ID (DE)',
    'Driver License' => 'Driver License (DE)',
    'Other ID' => 'Other ID (DE)',
];

$docCodeMap = [
    'PA' => 'Passport',
    'CC' => 'Cédula de Ciudadanía',
    'CE' => 'Cédula de Extranjería',
    'TI' => 'Tarjeta de Identidad',
    'RC' => 'Registro Civil',
    'DE' => 'National ID',
    'DL' => 'Driver License',
];
?>

<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
         onclick="document.getElementById('modal-container').innerHTML = '';"
         aria-hidden="true"></div>

    <div class="min-h-full flex items-center justify-center p-4 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-4xl sm:w-full border border-gray-200">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl <?= $isAirbnb ? 'bg-rose-100 text-[#FF385C]' : 'bg-indigo-100 text-indigo-700' ?> flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                            <h3 class="text-base font-bold text-gray-900" id="modal-title">
                                Reservation Editor
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-emerald-100 text-emerald-800">
                                Confirmed
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold uppercase bg-gray-200 text-gray-700">
                                <?= htmlspecialchars($sourceVal, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($registryCompleted): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-indigo-100 text-indigo-800">
                                    Registry Completed<?= $doorCode !== '' ? ' · PIN ' . htmlspecialchars($doorCode, ENT_QUOTES, 'UTF-8') : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-amber-100 text-amber-800">
                                    Registry Pending
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 font-mono">
                            UID: <?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
                <button type="button"
                        onclick="document.getElementById('modal-container').innerHTML = '';"
                        class="text-gray-400 hover:text-gray-600 rounded-lg p-1.5 hover:bg-gray-100 transition cursor-pointer">
                    <span class="sr-only">Close</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form id="edit-reservation-form"
                  hx-post="/reservations/<?= urlencode($uid) ?>/edit"
                  hx-target="#modal-container"
                  hx-swap="innerHTML"
                  data-has-synced-clearance="<?= $hasSyncedClearance ? 'true' : 'false' ?>"
                  class="p-6 space-y-6 max-h-[82vh] overflow-y-auto">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="exclude_reservation_uid" value="<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="edit_mode" value="1">
                <input type="hidden" name="source" id="edit-source" value="<?= htmlspecialchars($sourceVal, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="edit-registered-guest-count" value="<?= $registeredCount ?>">
                <input type="hidden" name="guest_count" id="edit-guest-count" value="<?= $currentOccupantCount ?>">
                <input type="hidden" name="reset_guest_registry" id="edit-reset-guest-registry" value="<?= $resetRegistryVal ? '1' : '0' ?>">

                <?php if (!empty($errorMessage)): ?>
                    <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-start space-x-2">
                        <svg class="w-4 h-4 mr-1 text-rose-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <span class="font-bold block">Update Failed</span>
                            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 1. Stay & Accommodation Section -->
                <div class="space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Stay & Accommodation</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="edit-property-id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                Property *
                            </label>
                            <select name="property_id"
                                    id="edit-property-id"
                                    required
                                    hx-post="/reservations/quote-preview"
                                    hx-trigger="change"
                                    hx-target="#quote-preview-container"
                                    hx-include="#edit-reservation-form"
                                    class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="1606" <?= $propIdVal === '1606' ? 'selected' : '' ?>>Oceanview Deluxe 1606 (16th Fl)</option>
                                <option value="1707" <?= $propIdVal === '1707' ? 'selected' : '' ?>>Oceanview Grand 1707 (17th Fl)</option>
                            </select>
                        </div>

                        <div>
                            <label for="edit-check-in" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                Check-in Date *
                            </label>
                            <input type="date"
                                   name="check_in"
                                   id="edit-check-in"
                                   required
                                   value="<?= htmlspecialchars($checkInVal, ENT_QUOTES, 'UTF-8') ?>"
                                   hx-post="/reservations/quote-preview"
                                   hx-trigger="change"
                                   hx-target="#quote-preview-container"
                                   hx-include="#edit-reservation-form"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="edit-check-out" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                Check-out Date *
                            </label>
                            <input type="date"
                                   name="check_out"
                                   id="edit-check-out"
                                   required
                                   <?= $minCheckOut !== '' ? 'min="' . htmlspecialchars($minCheckOut, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                   value="<?= htmlspecialchars($checkOutVal, ENT_QUOTES, 'UTF-8') ?>"
                                   hx-post="/reservations/quote-preview"
                                   hx-trigger="change"
                                   hx-target="#quote-preview-container"
                                   hx-include="#edit-reservation-form"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <!-- Live Availability & Standard Quote Preview Container -->
                    <div id="quote-preview-container" class="transition-all">
                        <?php if (!empty($quotePreviewHtml)): ?>
                            <?= $quotePreviewHtml ?>
                        <?php else: ?>
                            <div id="quote-preview-result" data-available="true" class="w-full">
                                <p class="text-[11px] text-gray-400 italic">Change property or dates above to verify live calendar availability and view the calculated standard Quote.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Total Price & Occupancy Override (max_guests) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <div class="flex justify-between items-center mb-1">
                                <label for="edit-total-price" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Quote Total Price (COP) *
                                </label>
                                <span class="text-[10px] text-gray-400">Preserved on date change unless manually edited or applied from Quote</span>
                            </div>
                            <div class="relative rounded-md shadow-2xs">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-xs font-bold">
                                    $
                                </div>
                                <input type="number"
                                       step="any"
                                       min="0"
                                       name="total_price"
                                       id="edit-total-price"
                                       required
                                       value="<?= htmlspecialchars($totalPriceVal, ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="0"
                                       class="w-full pl-7 pr-16 py-2 text-xs bg-gray-50 border border-gray-300 rounded-lg text-gray-900 font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-[11px]">
                                    COP
                                </div>
                            </div>
                        </div>

                        <div class="sm:col-span-1">
                            <div class="flex justify-between items-center mb-1">
                                <label for="edit-max-guests" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Occupancy Override (Max Guests) *
                                </label>
                                <span id="edit-max-guests-hint" class="text-[10px] text-gray-400">1–12<?= $currentOccupantCount > 0 ? " (min {$currentOccupantCount} registered)" : '' ?></span>
                            </div>
                            <input type="number"
                                   min="<?= $minMaxGuests ?>"
                                   max="12"
                                   step="1"
                                   name="max_guests"
                                   id="edit-max-guests"
                                   required
                                   value="<?= htmlspecialchars($maxGuestsVal, ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-300 rounded-lg text-gray-900 font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- 2. Primary Guest Contact & Language Section -->
                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Primary Guest</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div class="sm:col-span-1">
                            <label for="edit-guest-name" class="block text-xs text-gray-600 mb-1 font-medium">Full Name *</label>
                            <input type="text"
                                   name="guest_name"
                                   id="edit-guest-name"
                                   required
                                   value="<?= htmlspecialchars($guestNameVal, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. Maria Gonzalez"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="sm:col-span-1">
                            <label for="edit-guest-email" class="block text-xs text-gray-600 mb-1 font-medium">
                                Email Address <?= $isAirbnb ? '<span class="text-gray-400 font-normal">(Optional)</span>' : '*' ?>
                            </label>
                            <input type="email"
                                   name="guest_email"
                                   id="edit-guest-email"
                                   value="<?= htmlspecialchars($guestEmailVal, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="guest@example.com"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="sm:col-span-1">
                            <label for="edit-guest-phone" class="block text-xs text-gray-600 mb-1 font-medium">
                                Phone Number <?= $isAirbnb ? '<span class="text-gray-400 font-normal">(Optional)</span>' : '*' ?>
                            </label>
                            <input type="tel"
                                   name="guest_phone"
                                   id="edit-guest-phone"
                                   value="<?= htmlspecialchars($guestPhoneVal, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="+57 300 123 4567"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="sm:col-span-1">
                            <label for="edit-lang" class="block text-xs text-gray-600 mb-1 font-medium">Preferred Language *</label>
                            <select name="lang"
                                    id="edit-lang"
                                    required
                                    class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="es" <?= $langVal === 'es' ? 'selected' : '' ?>>Español (ES)</option>
                                <option value="en" <?= $langVal === 'en' ? 'selected' : '' ?>>English (EN)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. External Platform Reference & Notes Section -->
                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">External Reference & Administrative Notes</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="edit-external-code" class="block text-xs text-gray-600 mb-1 font-medium">
                                External Confirmation Code <?= $isAirbnb ? '*' : '<span class="text-gray-400 font-normal">(Optional)</span>' ?>
                            </label>
                            <input type="text"
                                   name="external_confirmation_code"
                                   id="edit-external-code"
                                   value="<?= htmlspecialchars($externalConfirmationCodeVal, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. HM4XYZ1234"
                                   class="w-full text-xs font-mono uppercase bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="edit-notes" class="block text-xs text-gray-600 mb-1 font-medium">Internal Operational Notes</label>
                            <textarea name="notes"
                                      id="edit-notes"
                                      rows="2"
                                      placeholder="Special guest arrival notes, payment wire references, or operational instructions..."
                                      class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg p-2.5 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($notesVal, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 4. Vehicle Registration Section -->
                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Vehicle Registration</h4>
                            <p class="text-[11px] text-gray-400">Assigned Parking Space clearance at Edificio Salguero Sunset reception (Bay 87 for 1606, Bay 95 for 1707)</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="edit-car-plates" class="block text-xs text-gray-600 mb-1 font-medium">
                                License Plates (`car_plates`) <span class="text-gray-400 font-normal">(Optional)</span>
                            </label>
                            <input type="text"
                                   name="car_plates"
                                   id="edit-car-plates"
                                   value="<?= htmlspecialchars($carPlatesVal, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. ABC-123"
                                   class="w-full text-xs font-mono uppercase bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="edit-car-model" class="block text-xs text-gray-600 mb-1 font-medium">
                                Vehicle Make / Model / Color (`car_model`) <span class="text-gray-400 font-normal">(Optional)</span>
                            </label>
                            <input type="text"
                                   name="car_model"
                                   id="edit-car-model"
                                   value="<?= htmlspecialchars($carModelVal, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. Mazda CX-30 White"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- 5. Guest Registry Occupant Roster Section -->
                <div class="pt-4 border-t border-gray-200 space-y-4" id="edit-guest-registry-section">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <div class="flex items-center space-x-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Guest Registry Roster</h4>
                                <span id="edit-roster-count-badge" class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <?= $currentOccupantCount ?> / <?= (int) $maxGuestsVal ?> Guests
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-400">
                                Manage staying Guests (up to 12). Guest #1 serves as Primary Guest (`TITULAR`). Adding Guests beyond current capacity automatically bumps Max Guests.
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button type="button"
                                    id="edit-clear-registry-btn"
                                    class="<?= $currentOccupantCount === 0 && !$registryCompleted ? 'hidden ' : '' ?>px-3 py-1.5 border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-semibold transition cursor-pointer inline-flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Clear / Reset Guest Registry
                            </button>
                            <button type="button"
                                    id="edit-add-guest-btn"
                                    class="<?= $currentOccupantCount >= 12 ? 'hidden ' : '' ?>px-3.5 py-1.5 border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-semibold transition cursor-pointer inline-flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Guest
                            </button>
                        </div>
                    </div>

                    <!-- Empty State Banner when 0 Guests -->
                    <div id="edit-registry-empty-state" class="<?= $currentOccupantCount > 0 ? 'hidden ' : '' ?>p-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 text-center space-y-2">
                        <p class="text-xs font-semibold text-gray-700">No Guests Registered in Guest Registry</p>
                        <p class="text-[11px] text-gray-500 max-w-lg mx-auto">
                            Click <strong>Add Guest</strong> to create the Guest Registry from scratch (Guest #1 will automatically pre-fill from the Primary Guest details above). Saving with 0 Guests keeps or resets the Guest Registry to pending (<code>registry_completed = 0</code>).
                        </p>
                    </div>

                    <!-- Guest Cards 1..12 -->
                    <div class="space-y-3" id="edit-guest-cards-container">
                        <?php for ($num = 1; $num <= 12; $num++): ?>
                            <?php
                            $occ = $occupantsList[$num - 1] ?? [];
                            $isActiveCard = ($num <= $currentOccupantCount);

                            $rawFn = trim((string) ($occ['first_name'] ?? $occ['firstName'] ?? $occ['guest_first_name'] ?? ''));
                            $rawLn = trim((string) ($occ['last_name'] ?? $occ['lastName'] ?? $occ['guest_last_name'] ?? ''));
                            $rawFullName = trim((string) ($occ['name'] ?? $occ['full_name'] ?? $occ['guest_name'] ?? ''));

                            if ($rawFn === '' && $rawLn === '' && $rawFullName !== '') {
                                $parts = preg_split('/\s+/', $rawFullName) ?: [];
                                if (count($parts) >= 2) {
                                    $rawLn = (string) array_pop($parts);
                                    $rawFn = implode(' ', $parts);
                                } elseif (count($parts) === 1) {
                                    $rawFn = $parts[0];
                                    $rawLn = $parts[0];
                                }
                            }
                            if ($rawFullName === '' && ($rawFn !== '' || $rawLn !== '')) {
                                $rawFullName = trim($rawFn . ' ' . $rawLn);
                            }

                            $rawOccEmail = trim((string) ($occ['email'] ?? $occ['guest_email'] ?? ''));
                            $rawOccPhone = trim((string) ($occ['phone'] ?? $occ['guest_phone'] ?? ''));
                            $rawOccCountry = strtoupper(trim((string) ($occ['country'] ?? $occ['nationality'] ?? $occ['guest_country'] ?? 'COLOMBIA')));
                            if ($rawOccCountry === '') {
                                $rawOccCountry = 'COLOMBIA';
                            }
                            $rawOccAge = isset($occ['age']) && (string) $occ['age'] !== ''
                                ? (string) $occ['age']
                                : (isset($occ['guest_age']) && (string) $occ['guest_age'] !== '' ? (string) $occ['guest_age'] : '');

                            $rawOccDocType = trim((string) ($occ['doc_type'] ?? $occ['docType'] ?? $occ['guest_doc_type'] ?? 'Passport'));
                            if (isset($docCodeMap[strtoupper($rawOccDocType)])) {
                                $rawOccDocType = $docCodeMap[strtoupper($rawOccDocType)];
                            }
                            if ($rawOccDocType === '') {
                                $rawOccDocType = 'Passport';
                            }

                            $rawOccDocNum = trim((string) ($occ['doc_num'] ?? $occ['doc_number'] ?? $occ['docNum'] ?? $occ['guest_doc_num'] ?? ''));
                            $disabledAttr = $isActiveCard ? '' : 'disabled';
                            ?>
                            <div id="edit-guest-card-<?= $num ?>"
                                 data-guest-index="<?= $num ?>"
                                 class="<?= $isActiveCard ? '' : 'hidden ' ?>p-4 rounded-xl border <?= $num === 1 ? 'border-indigo-200 bg-indigo-50/30' : 'border-gray-200 bg-gray-50/50' ?> space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs font-bold text-gray-900">Guest #<?= $num ?></span>
                                        <?php if ($num === 1): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 uppercase">
                                                Primary Guest (TITULAR)
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-200 text-gray-700 uppercase">
                                                Companion (ACOMPAÑANTE)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button"
                                            data-remove-guest="<?= $num ?>"
                                            class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:bg-rose-50 px-2 py-1 rounded-md transition cursor-pointer inline-flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Remove Guest
                                    </button>
                                </div>

                                <input type="hidden"
                                       name="guest_name_<?= $num ?>"
                                       id="edit-guest-name-<?= $num ?>"
                                       value="<?= htmlspecialchars($rawFullName, ENT_QUOTES, 'UTF-8') ?>"
                                       <?= $disabledAttr ?>>

                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    <div>
                                        <label for="edit-guest-first-name-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            First Name *
                                        </label>
                                        <input type="text"
                                               name="guest_first_name_<?= $num ?>"
                                               id="edit-guest-first-name-<?= $num ?>"
                                               value="<?= htmlspecialchars($rawFn, ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="First name"
                                               <?= $disabledAttr ?>
                                               class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>

                                    <div>
                                        <label for="edit-guest-last-name-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            Last Name *
                                        </label>
                                        <input type="text"
                                               name="guest_last_name_<?= $num ?>"
                                               id="edit-guest-last-name-<?= $num ?>"
                                               value="<?= htmlspecialchars($rawLn, ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="Last name"
                                               <?= $disabledAttr ?>
                                               class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>

                                    <div>
                                        <label for="edit-guest-doc-type-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            Document Type *
                                        </label>
                                        <select name="guest_doc_type_<?= $num ?>"
                                                id="edit-guest-doc-type-<?= $num ?>"
                                                <?= $disabledAttr ?>
                                                class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <?php foreach ($docTypes as $val => $label): ?>
                                                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $rawOccDocType === $val ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="edit-guest-doc-num-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            Document Number *
                                        </label>
                                        <input type="text"
                                               name="guest_doc_num_<?= $num ?>"
                                               id="edit-guest-doc-num-<?= $num ?>"
                                               value="<?= htmlspecialchars($rawOccDocNum, ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="2–50 chars"
                                               <?= $disabledAttr ?>
                                               class="w-full text-xs font-mono bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>

                                    <div>
                                        <label for="edit-guest-age-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            Age (0–120) *
                                        </label>
                                        <input type="number"
                                               min="0"
                                               max="120"
                                               name="guest_age_<?= $num ?>"
                                               id="edit-guest-age-<?= $num ?>"
                                               value="<?= htmlspecialchars($rawOccAge, ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="Age"
                                               <?= $disabledAttr ?>
                                               class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>

                                    <div>
                                        <label for="edit-guest-country-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            Country / Nationality *
                                        </label>
                                        <select name="guest_country_<?= $num ?>"
                                                id="edit-guest-country-<?= $num ?>"
                                                <?= $disabledAttr ?>
                                                class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <?php if (!isset($portalCountries[$rawOccCountry])): ?>
                                                <option value="<?= htmlspecialchars($rawOccCountry, ENT_QUOTES, 'UTF-8') ?>" selected>
                                                    <?= htmlspecialchars($rawOccCountry, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endif; ?>
                                            <?php foreach ($portalCountries as $code => $label): ?>
                                                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $rawOccCountry === $code ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if ($num === 1): ?>
                                        <div>
                                            <label for="edit-guest-email-1" class="block text-[11px] font-medium text-gray-600 mb-1">
                                                Primary Guest Email *
                                            </label>
                                            <input type="email"
                                                   name="guest_email_1"
                                                   id="edit-guest-email-1"
                                                   value="<?= htmlspecialchars($rawOccEmail, ENT_QUOTES, 'UTF-8') ?>"
                                                   placeholder="guest@example.com"
                                                   <?= $disabledAttr ?>
                                                   class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        </div>
                                    <?php endif; ?>

                                    <div class="<?= $num === 1 ? '' : 'sm:col-span-2' ?>">
                                        <label for="edit-guest-phone-<?= $num ?>" class="block text-[11px] font-medium text-gray-600 mb-1">
                                            Phone Number <?= $num === 1 ? '*' : '<span class="text-gray-400 font-normal">(Optional)</span>' ?>
                                        </label>
                                        <input type="tel"
                                               name="guest_phone_<?= $num ?>"
                                               id="edit-guest-phone-<?= $num ?>"
                                               value="<?= htmlspecialchars($rawOccPhone, ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="+57 300 123 4567"
                                               <?= $disabledAttr ?>
                                               class="w-full text-xs bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- 6. Opt-In Downstream Fulfillment Section -->
                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Downstream Fulfillment (Opt-In on Save)</h4>
                        <div id="clearance-modified-indicator"
                             class="<?= $isClearanceModified ? '' : 'hidden ' ?>inline-flex items-center px-2.5 py-1 rounded-full text-2xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                            <svg class="w-3.5 h-3.5 mr-1 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Guest Registry modified after previous Condominium Clearance Sync<?= $clearanceNumber !== null && $clearanceNumber !== '' ? ' (#' . htmlspecialchars($clearanceNumber, ENT_QUOTES, 'UTF-8') . ')' : '' ?></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label for="edit-sync-clearance" class="flex items-start space-x-3 p-3 rounded-xl border border-gray-200 bg-gray-50/70 hover:bg-gray-50 cursor-pointer transition">
                            <input type="checkbox"
                                   name="sync_condominium_clearance"
                                   id="edit-sync-clearance"
                                   value="1"
                                   <?= $syncClearanceChecked ? 'checked' : '' ?>
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs">
                                <span class="font-semibold text-gray-900 block">Trigger Condominium Clearance Sync</span>
                                <span class="text-[11px] text-gray-500">Transmit stay parameters, vehicle plates, and Guest Registry roster to the Condominium Administration Portal upon save.</span>
                            </span>
                        </label>

                        <label for="edit-send-access-dispatch" class="flex items-start space-x-3 p-3 rounded-xl border border-gray-200 bg-gray-50/70 hover:bg-gray-50 cursor-pointer transition">
                            <input type="checkbox"
                                   name="send_access_dispatch"
                                   id="edit-send-access-dispatch"
                                   value="1"
                                   <?= $sendAccessDispatchChecked ? 'checked' : '' ?>
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs">
                                <span class="font-semibold text-gray-900 block">Send Access Dispatch Email to Primary Guest</span>
                                <span class="text-[11px] text-gray-500">Email the unlocked Guest Guide link and smart-lock Access Credential (PIN) to the Primary Guest when Registry is complete.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button"
                                onclick="document.getElementById('modal-container').innerHTML = '';"
                                class="px-4 py-2 border border-gray-300 rounded-lg shadow-2xs text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                id="edit-submit-btn"
                                data-default-text="Save Changes"
                                class="px-5 py-2 border border-transparent rounded-lg shadow-2xs text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition cursor-pointer flex items-center">
                            <span id="edit-submit-text">Save Changes</span>
                        </button>
                    </div>
                    <p id="edit-submit-helper" class="text-xs text-amber-600 mt-2 text-right hidden"></p>
                </div>

            </form>

            <script>
            (function() {
                var ABSOLUTE_MAX_GUESTS = 12;
                var form = document.getElementById('edit-reservation-form');
                var checkInInput = document.getElementById('edit-check-in');
                var checkOutInput = document.getElementById('edit-check-out');
                var propertySelect = document.getElementById('edit-property-id');
                var sourceInput = document.getElementById('edit-source');
                var externalCodeInput = document.getElementById('edit-external-code');
                var priceInput = document.getElementById('edit-total-price');
                var maxGuestsInput = document.getElementById('edit-max-guests');
                var maxGuestsHint = document.getElementById('edit-max-guests-hint');
                var registeredGuestsInput = document.getElementById('edit-registered-guest-count');
                var guestCountInput = document.getElementById('edit-guest-count');
                var resetRegistryInput = document.getElementById('edit-reset-guest-registry');
                var guestNameInput = document.getElementById('edit-guest-name');
                var guestEmailInput = document.getElementById('edit-guest-email');
                var guestPhoneInput = document.getElementById('edit-guest-phone');
                var carPlatesInput = document.getElementById('edit-car-plates');
                var carModelInput = document.getElementById('edit-car-model');
                var addGuestBtn = document.getElementById('edit-add-guest-btn');
                var clearRegistryBtn = document.getElementById('edit-clear-registry-btn');
                var emptyStateEl = document.getElementById('edit-registry-empty-state');
                var rosterCountBadge = document.getElementById('edit-roster-count-badge');
                var clearanceModifiedIndicator = document.getElementById('clearance-modified-indicator');
                var hasSyncedClearance = form && form.getAttribute('data-has-synced-clearance') === 'true';

                var currentGuestCount = guestCountInput ? (parseInt(guestCountInput.value, 10) || 0) : 0;
                var isAvailable = true;
                var isCheckingAvailability = false;

                function markRegistryModified() {
                    if (hasSyncedClearance && clearanceModifiedIndicator) {
                        clearanceModifiedIndicator.classList.remove('hidden');
                    }
                }

                function updateGuestCards() {
                    for (var num = 1; num <= ABSOLUTE_MAX_GUESTS; num++) {
                        var card = document.getElementById('edit-guest-card-' + num);
                        if (!card) continue;
                        var inputs = card.querySelectorAll('input, select');
                        if (num <= currentGuestCount) {
                            card.classList.remove('hidden');
                            inputs.forEach(function(inp) {
                                inp.removeAttribute('disabled');
                            });
                        } else {
                            card.classList.add('hidden');
                            inputs.forEach(function(inp) {
                                inp.setAttribute('disabled', 'disabled');
                                if (inp.tagName === 'INPUT' && inp.type !== 'hidden') {
                                    inp.value = '';
                                } else if (inp.type === 'hidden') {
                                    inp.value = '';
                                }
                            });
                        }
                    }

                    if (guestCountInput) {
                        guestCountInput.value = String(currentGuestCount);
                    }
                    if (registeredGuestsInput) {
                        registeredGuestsInput.value = String(currentGuestCount);
                    }

                    var minCap = Math.max(1, currentGuestCount);
                    if (maxGuestsInput) {
                        maxGuestsInput.min = String(minCap);
                        var currentMax = parseInt(maxGuestsInput.value, 10) || 6;
                        if (currentGuestCount > currentMax) {
                            maxGuestsInput.value = String(currentGuestCount);
                            currentMax = currentGuestCount;
                        }
                        if (rosterCountBadge) {
                            rosterCountBadge.textContent = currentGuestCount + ' / ' + currentMax + ' Guests';
                        }
                    }

                    if (maxGuestsHint) {
                        maxGuestsHint.textContent = currentGuestCount > 0
                            ? '1–12 (min ' + currentGuestCount + ' registered)'
                            : '1–12';
                    }

                    if (emptyStateEl) {
                        if (currentGuestCount === 0) {
                            emptyStateEl.classList.remove('hidden');
                        } else {
                            emptyStateEl.classList.add('hidden');
                        }
                    }

                    if (addGuestBtn) {
                        if (currentGuestCount >= ABSOLUTE_MAX_GUESTS) {
                            addGuestBtn.classList.add('hidden');
                        } else {
                            addGuestBtn.classList.remove('hidden');
                        }
                    }

                    if (clearRegistryBtn) {
                        if (currentGuestCount === 0) {
                            clearRegistryBtn.classList.add('hidden');
                        } else {
                            clearRegistryBtn.classList.remove('hidden');
                        }
                    }
                }

                if (addGuestBtn) {
                    addGuestBtn.addEventListener('click', function() {
                        if (currentGuestCount >= ABSOLUTE_MAX_GUESTS) return;
                        var wasEmpty = (currentGuestCount === 0);
                        currentGuestCount++;
                        if (resetRegistryInput) {
                            resetRegistryInput.value = '0';
                        }
                        updateGuestCards();

                        if (wasEmpty) {
                            var primaryName = guestNameInput ? guestNameInput.value.trim() : '';
                            var primaryEmail = guestEmailInput ? guestEmailInput.value.trim() : '';
                            var primaryPhone = guestPhoneInput ? guestPhoneInput.value.trim() : '';
                            var fn1 = document.getElementById('edit-guest-first-name-1');
                            var ln1 = document.getElementById('edit-guest-last-name-1');
                            var full1 = document.getElementById('edit-guest-name-1');
                            var em1 = document.getElementById('edit-guest-email-1');
                            var ph1 = document.getElementById('edit-guest-phone-1');

                            if (primaryName && fn1 && !fn1.value && ln1 && !ln1.value) {
                                var parts = primaryName.split(/\s+/);
                                if (parts.length > 1) {
                                    fn1.value = parts.slice(0, -1).join(' ');
                                    ln1.value = parts[parts.length - 1];
                                } else if (parts.length === 1) {
                                    fn1.value = parts[0];
                                }
                                if (full1) full1.value = primaryName;
                            }
                            if (primaryEmail && em1 && !em1.value) {
                                em1.value = primaryEmail;
                            }
                            if (primaryPhone && primaryPhone.toUpperCase() !== 'N/A' && ph1 && !ph1.value) {
                                ph1.value = primaryPhone;
                            }
                        }

                        markRegistryModified();
                        validateForm();
                    });
                }

                if (clearRegistryBtn) {
                    clearRegistryBtn.addEventListener('click', function() {
                        currentGuestCount = 0;
                        if (resetRegistryInput) {
                            resetRegistryInput.value = '1';
                        }
                        for (var n = 1; n <= ABSOLUTE_MAX_GUESTS; n++) {
                            var fn = document.getElementById('edit-guest-first-name-' + n);
                            var ln = document.getElementById('edit-guest-last-name-' + n);
                            var nm = document.getElementById('edit-guest-name-' + n);
                            var ph = document.getElementById('edit-guest-phone-' + n);
                            var ag = document.getElementById('edit-guest-age-' + n);
                            var dn = document.getElementById('edit-guest-doc-num-' + n);
                            if (fn) fn.value = '';
                            if (ln) ln.value = '';
                            if (nm) nm.value = '';
                            if (ph) ph.value = '';
                            if (ag) ag.value = '';
                            if (dn) dn.value = '';
                        }
                        var em1 = document.getElementById('edit-guest-email-1');
                        if (em1) em1.value = '';
                        updateGuestCards();
                        markRegistryModified();
                        validateForm();
                    });
                }

                if (form) {
                    form.addEventListener('click', function(e) {
                        var removeBtn = e.target.closest('[data-remove-guest]');
                        if (!removeBtn) return;
                        var numToRemove = parseInt(removeBtn.getAttribute('data-remove-guest'), 10);
                        if (!numToRemove || numToRemove < 1 || numToRemove > currentGuestCount) return;

                        var previousPhone1 = document.getElementById('edit-guest-phone-1') ? document.getElementById('edit-guest-phone-1').value : '';

                        for (var i = numToRemove; i < currentGuestCount; i++) {
                            var curFn = document.getElementById('edit-guest-first-name-' + i);
                            var nxtFn = document.getElementById('edit-guest-first-name-' + (i + 1));
                            var curLn = document.getElementById('edit-guest-last-name-' + i);
                            var nxtLn = document.getElementById('edit-guest-last-name-' + (i + 1));
                            var curNm = document.getElementById('edit-guest-name-' + i);
                            var nxtNm = document.getElementById('edit-guest-name-' + (i + 1));
                            var curPh = document.getElementById('edit-guest-phone-' + i);
                            var nxtPh = document.getElementById('edit-guest-phone-' + (i + 1));
                            var curCo = document.getElementById('edit-guest-country-' + i);
                            var nxtCo = document.getElementById('edit-guest-country-' + (i + 1));
                            var curAg = document.getElementById('edit-guest-age-' + i);
                            var nxtAg = document.getElementById('edit-guest-age-' + (i + 1));
                            var curDt = document.getElementById('edit-guest-doc-type-' + i);
                            var nxtDt = document.getElementById('edit-guest-doc-type-' + (i + 1));
                            var curDn = document.getElementById('edit-guest-doc-num-' + i);
                            var nxtDn = document.getElementById('edit-guest-doc-num-' + (i + 1));

                            if (curFn && nxtFn) curFn.value = nxtFn.value;
                            if (curLn && nxtLn) curLn.value = nxtLn.value;
                            if (curNm && nxtNm) curNm.value = nxtNm.value;
                            if (curPh && nxtPh) {
                                curPh.value = (i === 1 && !nxtPh.value.trim()) ? previousPhone1 : nxtPh.value;
                            }
                            if (curCo && nxtCo) curCo.value = nxtCo.value;
                            if (curAg && nxtAg) curAg.value = nxtAg.value;
                            if (curDt && nxtDt) curDt.value = nxtDt.value;
                            if (curDn && nxtDn) curDn.value = nxtDn.value;
                        }

                        currentGuestCount--;
                        if (currentGuestCount === 0 && resetRegistryInput) {
                            resetRegistryInput.value = '1';
                        }
                        updateGuestCards();
                        markRegistryModified();
                        validateForm();
                    });
                }

                function syncAvailabilityFromDom() {
                    var resultEl = document.getElementById('quote-preview-result');
                    if (resultEl && resultEl.hasAttribute('data-available')) {
                        isAvailable = (resultEl.getAttribute('data-available') === 'true');
                    } else {
                        isAvailable = true;
                    }
                }

                function syncMinEndDate() {
                    if (!checkInInput || !checkOutInput) return;
                    if (!checkInInput.value) {
                        checkOutInput.removeAttribute('min');
                        return;
                    }
                    var parts = checkInInput.value.split('-');
                    if (parts.length !== 3) return;
                    var start = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    if (isNaN(start.getTime())) return;

                    var minEnd = new Date(start);
                    minEnd.setDate(minEnd.getDate() + 1);
                    var y = minEnd.getFullYear();
                    var m = String(minEnd.getMonth() + 1).padStart(2, '0');
                    var d = String(minEnd.getDate()).padStart(2, '0');
                    var minEndStr = y + '-' + m + '-' + d;
                    checkOutInput.min = minEndStr;

                    if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
                        checkOutInput.value = minEndStr;
                    }
                }

                function getUnmetRequirement() {
                    if (isCheckingAvailability) {
                        return 'Checking availability...';
                    }

                    var propVal = propertySelect ? propertySelect.value : '';
                    if (propVal !== '1606' && propVal !== '1707') {
                        return 'Select a valid property';
                    }

                    var checkInVal = checkInInput ? checkInInput.value : '';
                    var checkOutVal = checkOutInput ? checkOutInput.value : '';
                    if (!checkInVal || !checkOutVal) {
                        return 'Select valid stay dates';
                    }
                    if (checkOutVal <= checkInVal) {
                        return 'Check-out date must be after check-in date';
                    }

                    if (!isAvailable) {
                        return 'Resolve date conflict above to proceed';
                    }

                    var priceVal = priceInput ? priceInput.value.trim() : '';
                    if (priceVal === '' || isNaN(Number(priceVal)) || Number(priceVal) < 0) {
                        return 'Total price must be zero or greater';
                    }

                    if (maxGuestsInput) {
                        var maxGuestsVal = maxGuestsInput.value.trim();
                        var maxGuestsNum = Number(maxGuestsVal);
                        if (maxGuestsVal === '' || isNaN(maxGuestsNum) || !Number.isInteger(maxGuestsNum) || maxGuestsNum < 1 || maxGuestsNum > 12) {
                            return 'Max guests must be between 1 and 12';
                        }
                        if (currentGuestCount > 0 && maxGuestsNum < currentGuestCount) {
                            return 'Cannot lower Max Guests below ' + currentGuestCount + ' currently registered guests';
                        }
                        if (rosterCountBadge) {
                            rosterCountBadge.textContent = currentGuestCount + ' / ' + maxGuestsNum + ' Guests';
                        }
                    }

                    var nameVal = guestNameInput ? guestNameInput.value.trim() : '';
                    if (!nameVal) {
                        return 'Guest name is required';
                    }

                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    var currentSource = sourceInput ? sourceInput.value.toLowerCase() : 'web';
                    if (currentSource === 'airbnb') {
                        var extCode = externalCodeInput ? externalCodeInput.value.trim() : '';
                        if (!extCode) {
                            return 'Airbnb confirmation code is required';
                        }
                    } else {
                        var emailVal = guestEmailInput ? guestEmailInput.value.trim() : '';
                        var phoneVal = guestPhoneInput ? guestPhoneInput.value.trim() : '';
                        if (!emailVal || !emailRegex.test(emailVal)) {
                            return 'Valid guest email is required';
                        }
                        if (phoneVal.length < 7 || phoneVal.length > 25) {
                            return 'Guest phone number must be 7-25 characters';
                        }
                    }

                    for (var g = 1; g <= currentGuestCount; g++) {
                        var gFn = document.getElementById('edit-guest-first-name-' + g);
                        var gLn = document.getElementById('edit-guest-last-name-' + g);
                        var gNm = document.getElementById('edit-guest-name-' + g);
                        var gDn = document.getElementById('edit-guest-doc-num-' + g);
                        var gAg = document.getElementById('edit-guest-age-' + g);
                        var gCo = document.getElementById('edit-guest-country-' + g);
                        var gPh = document.getElementById('edit-guest-phone-' + g);

                        var fnVal = gFn ? gFn.value.trim() : '';
                        var lnVal = gLn ? gLn.value.trim() : '';
                        if (!fnVal || !lnVal) {
                            return 'Guest #' + g + ' requires both First Name and Last Name';
                        }
                        if (gNm) {
                            gNm.value = (fnVal + ' ' + lnVal).trim();
                        }

                        var dnVal = gDn ? gDn.value.trim() : '';
                        if (dnVal.length < 2 || dnVal.length > 50) {
                            return 'Guest #' + g + ' document number must be 2–50 characters';
                        }

                        var agVal = gAg ? gAg.value.trim() : '';
                        var agNum = Number(agVal);
                        if (agVal === '' || isNaN(agNum) || !Number.isInteger(agNum) || agNum < 0 || agNum > 120) {
                            return 'Guest #' + g + ' age must be between 0 and 120';
                        }

                        var coVal = gCo ? gCo.value.trim() : '';
                        if (!coVal) {
                            return 'Guest #' + g + ' requires a country';
                        }

                        var phVal = gPh ? gPh.value.trim() : '';
                        if (g === 1) {
                            var gEm1 = document.getElementById('edit-guest-email-1');
                            var em1Val = gEm1 ? gEm1.value.trim() : '';
                            if (!em1Val || !emailRegex.test(em1Val)) {
                                return 'Guest #1 requires a valid email address';
                            }
                            if (phVal.length < 6 || phVal.length > 30) {
                                return 'Guest #1 phone must be 6–30 characters';
                            }
                        } else if (phVal !== '' && (phVal.length < 6 || phVal.length > 30)) {
                            return 'Guest #' + g + ' phone must be 6–30 characters';
                        }
                    }

                    return null;
                }

                function validateForm() {
                    var unmetRequirement = getUnmetRequirement();
                    var btn = document.getElementById('edit-submit-btn');
                    var helper = document.getElementById('edit-submit-helper');

                    if (unmetRequirement) {
                        if (btn) {
                            btn.disabled = true;
                            btn.setAttribute('aria-disabled', 'true');
                            btn.classList.add('opacity-50', 'cursor-not-allowed');
                            btn.classList.remove('cursor-pointer');
                        }
                        if (helper) {
                            helper.textContent = unmetRequirement;
                            helper.classList.remove('hidden');
                        }
                        return false;
                    } else {
                        if (btn) {
                            btn.disabled = false;
                            btn.setAttribute('aria-disabled', 'false');
                            btn.classList.remove('opacity-50', 'cursor-not-allowed');
                            btn.classList.add('cursor-pointer');
                        }
                        if (helper) {
                            helper.textContent = '';
                            helper.classList.add('hidden');
                        }
                        return true;
                    }
                }

                if (checkInInput) {
                    checkInInput.addEventListener('change', function() {
                        syncMinEndDate();
                        validateForm();
                    });
                    checkInInput.addEventListener('input', function() {
                        syncMinEndDate();
                        validateForm();
                    });
                }

                if (checkOutInput) {
                    checkOutInput.addEventListener('change', validateForm);
                    checkOutInput.addEventListener('input', validateForm);
                }

                if (carPlatesInput) {
                    carPlatesInput.addEventListener('input', markRegistryModified);
                }
                if (carModelInput) {
                    carModelInput.addEventListener('input', markRegistryModified);
                }

                var cardsContainer = document.getElementById('edit-guest-cards-container');
                if (cardsContainer) {
                    cardsContainer.addEventListener('input', markRegistryModified);
                    cardsContainer.addEventListener('change', markRegistryModified);
                }

                if (form) {
                    form.addEventListener('input', validateForm);
                    form.addEventListener('change', validateForm);

                    form.addEventListener('availabilityChecked', function(evt) {
                        if (evt.detail && typeof evt.detail.available === 'boolean') {
                            isAvailable = evt.detail.available;
                        } else {
                            syncAvailabilityFromDom();
                        }
                        validateForm();
                    });

                    form.addEventListener('htmx:afterSwap', function() {
                        syncAvailabilityFromDom();
                        validateForm();
                    });
                }

                updateGuestCards();
                syncAvailabilityFromDom();
                validateForm();
            })();
            </script>

        </div>
    </div>
</div>
