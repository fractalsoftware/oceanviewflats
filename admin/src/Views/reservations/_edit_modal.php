<?php
/**
 * @var array<string, mixed> $reservation
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

$registeredCount = (int) ($registeredGuestCount ?? 0);
$minMaxGuests = max(1, $registeredCount);

$minCheckOut = '';
if ($checkInVal !== '') {
    try {
        $minCheckOut = (new \DateTimeImmutable($checkInVal))->modify('+1 day')->format('Y-m-d');
    } catch (\Throwable) {
        $minCheckOut = '';
    }
}
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
                        <div class="flex items-center space-x-2">
                            <h3 class="text-base font-bold text-gray-900" id="modal-title">
                                Reservation Editor
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-emerald-100 text-emerald-800">
                                Confirmed
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold uppercase bg-gray-200 text-gray-700">
                                <?= htmlspecialchars($sourceVal, ENT_QUOTES, 'UTF-8') ?>
                            </span>
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
                  class="p-6 space-y-5">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="exclude_reservation_uid" value="<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="edit_mode" value="1">
                <input type="hidden" name="source" id="edit-source" value="<?= htmlspecialchars($sourceVal, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="edit-registered-guest-count" value="<?= $registeredCount ?>">

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
                                <span class="text-[10px] text-gray-400">1–12<?= $registeredCount > 0 ? " (min {$registeredCount} registered)" : '' ?></span>
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
                var form = document.getElementById('edit-reservation-form');
                var checkInInput = document.getElementById('edit-check-in');
                var checkOutInput = document.getElementById('edit-check-out');
                var propertySelect = document.getElementById('edit-property-id');
                var sourceInput = document.getElementById('edit-source');
                var externalCodeInput = document.getElementById('edit-external-code');
                var priceInput = document.getElementById('edit-total-price');
                var maxGuestsInput = document.getElementById('edit-max-guests');
                var registeredGuestsInput = document.getElementById('edit-registered-guest-count');
                var guestNameInput = document.getElementById('edit-guest-name');
                var guestEmailInput = document.getElementById('edit-guest-email');
                var guestPhoneInput = document.getElementById('edit-guest-phone');
                var submitBtn = document.getElementById('edit-submit-btn');
                var submitBtnText = document.getElementById('edit-submit-text');
                var helperEl = document.getElementById('edit-submit-helper');

                var isAvailable = true;
                var isCheckingAvailability = false;

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

                    var registeredCount = registeredGuestsInput ? parseInt(registeredGuestsInput.value, 10) || 0 : 0;
                    if (maxGuestsInput) {
                        var maxGuestsVal = maxGuestsInput.value.trim();
                        var maxGuestsNum = Number(maxGuestsVal);
                        if (maxGuestsVal === '' || isNaN(maxGuestsNum) || !Number.isInteger(maxGuestsNum) || maxGuestsNum < 1 || maxGuestsNum > 12) {
                            return 'Max guests must be between 1 and 12';
                        }
                        if (registeredCount > 0 && maxGuestsNum < registeredCount) {
                            return 'Cannot lower Max Guests below ' + registeredCount + ' currently registered guests';
                        }
                    }

                    var nameVal = guestNameInput ? guestNameInput.value.trim() : '';
                    if (!nameVal) {
                        return 'Guest name is required';
                    }

                    var currentSource = sourceInput ? sourceInput.value.toLowerCase() : 'web';
                    if (currentSource === 'airbnb') {
                        var extCode = externalCodeInput ? externalCodeInput.value.trim() : '';
                        if (!extCode) {
                            return 'Airbnb confirmation code is required';
                        }
                    } else {
                        var emailVal = guestEmailInput ? guestEmailInput.value.trim() : '';
                        var phoneVal = guestPhoneInput ? guestPhoneInput.value.trim() : '';
                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailVal || !emailRegex.test(emailVal)) {
                            return 'Valid guest email is required';
                        }
                        if (phoneVal.length < 7 || phoneVal.length > 25) {
                            return 'Guest phone number must be 7-25 characters';
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

                syncAvailabilityFromDom();
                validateForm();
            })();
            </script>

        </div>
    </div>
</div>
