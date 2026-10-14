<?php
/**
 * @var string|null $errorMessage
 * @var string|null $propertyId
 * @var string|null $checkIn
 * @var string|null $checkOut
 * @var string|null $source
 * @var string|null $channelBlockUid
 * @var string|null $externalConfirmationCode
 * @var string|null $guestName
 * @var string|null $guestEmail
 * @var string|null $guestPhone
 * @var string|null $totalPrice
 * @var string|null $notes
 * @var bool|null $preMarkRegistry
 * @var bool|null $sendConfirmationEmail
 * @var int|string|null $maxGuests
 */
$propIdVal = (!empty($propertyId) && in_array($propertyId, ['1606', '1707'], true)) ? $propertyId : '1606';
$sourceVal = (!empty($source)) ? $source : 'manual_override';
$isAirbnb = (strtolower($sourceVal) === 'airbnb');
$sendEmailVal = $sendConfirmationEmail ?? (!$isAirbnb);
$maxGuestsVal = (isset($maxGuests) && (string) $maxGuests !== '') ? (string) $maxGuests : '6';
$minCheckOut = '';
if (!empty($checkIn)) {
    try {
        $minCheckOut = (new \DateTimeImmutable((string) $checkIn))->modify('+1 day')->format('Y-m-d');
    } catch (\Throwable) {
        $minCheckOut = '';
    }
}
$submitLabel = $isAirbnb ? 'Onboard & Generate Dispatch' : 'Create Reservation';
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
                    <div class="w-8 h-8 rounded-lg <?= $isAirbnb ? 'bg-rose-100 text-[#FF385C]' : 'bg-indigo-100 text-indigo-700' ?> flex items-center justify-center">
                        <?php if ($isAirbnb): ?>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900" id="modal-title">
                            <?= $isAirbnb ? 'Onboard Airbnb Reservation' : 'Create Manual Reservation' ?>
                        </h3>
                        <p class="text-xs text-gray-500">
                            <?= $isAirbnb ? 'Convert external Airbnb channel booking into first-class reservation & dispatch guides.' : 'Book direct stays, bank wires, or owner occupancies with payment gateway bypass.' ?>
                        </p>
                    </div>
                </div>
                <button type="button"
                        onclick="document.getElementById('modal-container').innerHTML = '';"
                        class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form id="create-reservation-form"
                  hx-post="/reservations/create-manual"
                  hx-target="#modal-container"
                  hx-swap="innerHTML"
                  class="p-6 space-y-4">

                <?php if (!empty($errorMessage)): ?>
                    <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-rose-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Property & Channel Source -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="create-property-id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Property *
                        </label>
                        <select name="property_id"
                                id="create-property-id"
                                required
                                hx-post="/reservations/quote-preview"
                                hx-trigger="change"
                                hx-target="#quote-preview-container"
                                hx-include="#create-reservation-form"
                                class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1606" <?= $propIdVal === '1606' ? 'selected' : '' ?>>Oceanview Deluxe 1606 (16th Fl)</option>
                            <option value="1707" <?= $propIdVal === '1707' ? 'selected' : '' ?>>Oceanview Grand 1707 (17th Fl)</option>
                        </select>
                    </div>

                    <div>
                        <label for="create-source" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Booking Source *
                        </label>
                        <select name="source"
                                id="create-source"
                                required
                                onchange="var airbnbContainer = document.getElementById('airbnb-fields-container'); if(this.value === 'airbnb') { airbnbContainer.classList.remove('hidden'); } else { airbnbContainer.classList.add('hidden'); }"
                                hx-post="/reservations/quote-preview"
                                hx-trigger="change"
                                hx-target="#quote-preview-container"
                                hx-include="#create-reservation-form"
                                class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="airbnb" <?= $isAirbnb ? 'selected' : '' ?>>Airbnb (External Reservation)</option>
                            <option value="bank_transfer" <?= $sourceVal === 'bank_transfer' ? 'selected' : '' ?>>Direct Bank Transfer / Wire</option>
                            <option value="cash" <?= $sourceVal === 'cash' ? 'selected' : '' ?>>Cash / In-Person</option>
                            <option value="owner_stay" <?= $sourceVal === 'owner_stay' ? 'selected' : '' ?>>Owner Occupancy ($0.00)</option>
                            <option value="manual_override" <?= $sourceVal === 'manual_override' ? 'selected' : '' ?>>Administrative Manual Override</option>
                        </select>
                    </div>
                </div>

                <!-- Airbnb Required Fields Container (ADR 0007 / Variant A) -->
                <div id="airbnb-fields-container" class="<?= $isAirbnb ? '' : 'hidden' ?> bg-rose-50/70 border border-rose-100 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-rose-900 flex items-center">
                            <svg class="w-4 h-4 mr-1 text-[#FF385C]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                            Airbnb Reservation Details
                        </span>
                        <span class="text-[10px] text-rose-600 bg-rose-100/70 px-2 py-0.5 rounded font-medium">ADR 0007 External Sync</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="create-airbnb-code" class="block text-2xs font-bold uppercase tracking-wider text-rose-800 mb-1">
                                Airbnb Confirmation Code *
                            </label>
                            <input type="text"
                                   name="external_confirmation_code"
                                   id="create-airbnb-code"
                                   value="<?= htmlspecialchars((string) ($externalConfirmationCode ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. HM4XYZ1234"
                                   class="w-full text-xs font-mono uppercase bg-white border border-rose-200 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#FF385C]">
                            <p class="text-[10px] text-gray-500 mt-0.5">Found in your Airbnb Host Inbox / reservation details.</p>
                        </div>

                        <div>
                            <label for="create-channel-block-uid" class="block text-2xs font-bold uppercase tracking-wider text-rose-800 mb-1">
                                Linked Channel Block UID
                            </label>
                            <input type="text"
                                   name="channel_block_uid"
                                   id="create-channel-block-uid"
                                   value="<?= htmlspecialchars((string) ($channelBlockUid ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Optional or auto-detected"
                                   class="w-full text-xs font-mono bg-white border border-rose-200 rounded-lg px-3 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF385C]">
                            <p class="text-[10px] text-gray-500 mt-0.5">Absorbs matching iCal block from external feed.</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-rose-800/80 bg-rose-100/50 p-2 rounded-lg flex items-start">
                        <svg class="w-3.5 h-3.5 mr-1.5 text-rose-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        <span>Host Payout in COP can be recorded below (defaults to $0 COP if pending settlement). Primary guest dispatch will be generated in Airbnb chat format after creation.</span>
                    </div>
                </div>

                <!-- Check-in & Check-out Dates -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="create-check-in" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Check-in Date *
                        </label>
                        <input type="date"
                               name="check_in"
                               id="create-check-in"
                               required
                               value="<?= htmlspecialchars((string) ($checkIn ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               hx-post="/reservations/quote-preview"
                               hx-trigger="change, load"
                               hx-target="#quote-preview-container"
                               hx-include="#create-reservation-form"
                               class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="create-check-out" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Check-out Date *
                        </label>
                        <input type="date"
                               name="check_out"
                               id="create-check-out"
                               required
                               <?= $minCheckOut !== '' ? 'min="' . htmlspecialchars($minCheckOut, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                               value="<?= htmlspecialchars((string) ($checkOut ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               hx-post="/reservations/quote-preview"
                               hx-trigger="change"
                               hx-target="#quote-preview-container"
                               hx-include="#create-reservation-form"
                               class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Real-time Quote & Availability Preview Container -->
                <div id="quote-preview-container" class="transition-all">
                    <p class="text-[11px] text-gray-400 italic">Select property and dates above to check live availability and rate quote.</p>
                </div>

                <!-- Total Price (Editable Override / Host Payout) & Occupancy Override -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <div class="flex justify-between items-center mb-1">
                            <label for="manual-total-price" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Total Price / Host Payout (COP)
                            </label>
                            <span class="text-[10px] text-gray-400">Pre-calculated via QuoteEngine, editable (defaults to $0 COP for Airbnb)</span>
                        </div>
                        <div class="relative rounded-md shadow-2xs">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-xs font-bold">
                                $
                            </div>
                            <input type="number"
                                   step="1000"
                                   min="0"
                                   name="total_price"
                                   id="manual-total-price"
                                   value="<?= htmlspecialchars((string) ($totalPrice ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="0"
                                   oninput="this.dataset.autoFilled = 'false';"
                                   class="w-full pl-7 pr-16 py-2 text-xs bg-gray-50 border border-gray-300 rounded-lg text-gray-900 font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-[11px]">
                                COP
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-1">
                        <div class="flex justify-between items-center mb-1">
                            <label for="create-max-guests" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Max Guests *
                            </label>
                            <span class="text-[10px] text-gray-400">1–12 (default 6)</span>
                        </div>
                        <input type="number"
                               min="1"
                               max="12"
                               step="1"
                               name="max_guests"
                               id="create-max-guests"
                               required
                               value="<?= htmlspecialchars($maxGuestsVal, ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-300 rounded-lg text-gray-900 font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Primary Guest Details -->
                <div class="pt-2 border-t border-gray-200">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Guest Information</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-1">
                            <label for="create-guest-name" class="block text-xs text-gray-600 mb-1 font-medium">Full Name *</label>
                            <input type="text"
                                   name="guest_name"
                                   id="create-guest-name"
                                   required
                                   value="<?= htmlspecialchars((string) ($guestName ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. Maria Gonzalez"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="sm:col-span-1">
                            <label for="create-guest-email" class="block text-xs text-gray-600 mb-1 font-medium">
                                Email Address <span class="text-gray-400 font-normal">(Optional for Airbnb)</span>
                            </label>
                            <input type="email"
                                   name="guest_email"
                                   id="create-guest-email"
                                   value="<?= htmlspecialchars((string) ($guestEmail ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="guest@example.com"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="sm:col-span-1">
                            <label for="create-guest-phone" class="block text-xs text-gray-600 mb-1 font-medium">
                                Phone Number <span class="text-gray-400 font-normal">(Optional for Airbnb)</span>
                            </label>
                            <input type="tel"
                                   name="guest_phone"
                                   id="create-guest-phone"
                                   value="<?= htmlspecialchars((string) ($guestPhone ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="+57 300 123 4567"
                                   class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Operational Notes -->
                <div>
                    <label for="create-notes" class="block text-xs text-gray-600 mb-1 font-medium">Internal Operational Notes</label>
                    <textarea name="notes"
                              id="create-notes"
                              rows="2"
                              placeholder="Airbnb thread link, special guest arrival notes, or instructions..."
                              class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg p-2.5 text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars((string) ($notes ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Operational Toggles -->
                <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                    <label class="flex items-start space-x-2.5 cursor-pointer">
                        <input type="checkbox"
                               name="pre_mark_registry"
                               value="1"
                               <?= !empty($preMarkRegistry) ? 'checked' : '' ?>
                               class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                        <div class="text-xs">
                            <span class="font-medium text-gray-900">Pre-mark Guest Registry as completed (ADR 0001)</span>
                            <p class="text-[11px] text-gray-500">Check only if guest identity was physically verified. Generates Stage 2 Access Dispatch immediately.</p>
                        </div>
                    </label>

                    <label class="flex items-start space-x-2.5 cursor-pointer">
                        <input type="checkbox"
                               name="send_confirmation_email"
                               value="1"
                               <?= $sendEmailVal ? 'checked' : '' ?>
                               class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                        <div class="text-xs">
                            <span class="font-medium text-gray-900">Send confirmation email to guest</span>
                            <p class="text-[11px] text-gray-500">Dispatches localized confirmation email. (For Airbnb, primary dispatch is via Airbnb messenger).</p>
                        </div>
                    </label>
                </div>

                <!-- Modal Footer -->
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button"
                                onclick="document.getElementById('modal-container').innerHTML = '';"
                                class="px-4 py-2 border border-gray-300 rounded-lg shadow-2xs text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                id="create-submit-btn"
                                disabled
                                aria-disabled="true"
                                data-default-text="<?= htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8') ?>"
                                class="px-5 py-2 border border-transparent rounded-lg shadow-2xs text-xs font-semibold text-white <?= $isAirbnb ? 'bg-[#FF385C] hover:bg-[#E00B41]' : 'bg-indigo-600 hover:bg-indigo-700' ?> transition opacity-50 cursor-not-allowed flex items-center">
                            <span class="htmx-indicator mr-2 hidden">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span id="create-submit-text"><?= $submitLabel ?></span>
                        </button>
                    </div>
                    <p id="create-submit-helper" class="text-xs text-amber-600 dark:text-amber-400 mt-2 text-right">Select available dates to enable creation</p>
                </div>

            </form>

            <script>
            (function() {
                var form = document.getElementById('create-reservation-form');
                var checkInInput = document.getElementById('create-check-in');
                var checkOutInput = document.getElementById('create-check-out');
                var propertySelect = document.getElementById('create-property-id');
                var sourceSelect = document.getElementById('create-source');
                var airbnbCodeInput = document.getElementById('create-airbnb-code');
                var priceInput = document.getElementById('manual-total-price');
                var guestNameInput = document.getElementById('create-guest-name');
                var guestEmailInput = document.getElementById('create-guest-email');
                var guestPhoneInput = document.getElementById('create-guest-phone');
                var submitBtn = document.getElementById('create-submit-btn');
                var submitBtnText = document.getElementById('create-submit-text');
                var helperEl = document.getElementById('create-submit-helper');

                // Availability state management
                var isAvailable = false;
                var isCheckingAvailability = false;

                function syncAvailabilityFromDom() {
                    var resultEl = document.getElementById('quote-preview-result');
                    if (resultEl) {
                        isAvailable = (resultEl.getAttribute('data-available') === 'true');
                    } else {
                        isAvailable = false;
                    }
                }

                // 1. Date Range Auto-Synchronization
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

                // 2. Submit button default text management for booking source
                function getSubmitDefaultText() {
                    if (sourceSelect && sourceSelect.value === 'airbnb') {
                        return 'Onboard & Generate Dispatch';
                    }
                    return 'Create Reservation';
                }

                // 3. Client-Side Reactive Validation Matrix
                function getUnmetRequirement() {
                    if (isCheckingAvailability) {
                        return 'Checking availability...';
                    }

                    // Property: must be selected (1606 or 1707)
                    var propVal = propertySelect ? propertySelect.value : '';
                    if (propVal !== '1606' && propVal !== '1707') {
                        return 'Select a valid property';
                    }

                    // Dates: check_in and check_out present and check_out > check_in
                    var checkInVal = checkInInput ? checkInInput.value : '';
                    var checkOutVal = checkOutInput ? checkOutInput.value : '';
                    if (!checkInVal || !checkOutVal) {
                        return 'Select available dates to enable creation';
                    }
                    if (checkOutVal <= checkInVal) {
                        return 'Check-out date must be after check-in date';
                    }

                    // Availability: availability state must be true
                    if (!isAvailable) {
                        return 'Resolve date conflict above to proceed';
                    }

                    // Price: total_price present, numeric, and >= 0
                    var priceVal = priceInput ? priceInput.value.trim() : '';
                    if (priceVal === '' || isNaN(Number(priceVal)) || Number(priceVal) < 0) {
                        if (priceVal !== '' && !isNaN(Number(priceVal)) && Number(priceVal) < 0) {
                            return 'Total price cannot be negative';
                        }
                        return 'Total price must be zero or greater';
                    }

                    // Max guests: 1 to 12
                    var maxGuestsInput = document.getElementById('create-max-guests');
                    if (maxGuestsInput) {
                        var maxGuestsVal = maxGuestsInput.value.trim();
                        var maxGuestsNum = Number(maxGuestsVal);
                        if (maxGuestsVal === '' || isNaN(maxGuestsNum) || !Number.isInteger(maxGuestsNum) || maxGuestsNum < 1 || maxGuestsNum > 12) {
                            return 'Max guests must be between 1 and 12';
                        }
                    }

                    // Guest name: required for all reservations
                    var nameVal = guestNameInput ? guestNameInput.value.trim() : '';
                    if (!nameVal) {
                        return 'Guest name is required';
                    }

                    // Source-specific rules
                    var currentSource = sourceSelect ? sourceSelect.value : 'manual_override';
                    if (currentSource === 'airbnb') {
                        var airbnbCode = airbnbCodeInput ? airbnbCodeInput.value.trim() : '';
                        if (!airbnbCode) {
                            return 'Airbnb confirmation code required';
                        }
                        var sendEmailCheckbox = document.getElementById('create-send-email');
                        var emailVal = guestEmailInput ? guestEmailInput.value.trim() : '';
                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (sendEmailCheckbox && sendEmailCheckbox.checked) {
                            if (!emailVal || !emailRegex.test(emailVal)) {
                                return 'Valid guest email required when sending confirmation';
                            }
                        }
                    } else {
                        var emailVal = guestEmailInput ? guestEmailInput.value.trim() : '';
                        var phoneVal = guestPhoneInput ? guestPhoneInput.value.trim() : '';
                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                        if (!emailVal || !emailRegex.test(emailVal)) {
                            return 'Guest name and email required';
                        }
                        if (phoneVal.length < 7 || phoneVal.length > 25) {
                            return 'Guest phone number must be 7-25 characters';
                        }
                    }

                    return null;
                }

                function validateForm() {
                    var unmetRequirement = getUnmetRequirement();
                    var btn = document.getElementById('create-submit-btn');
                    var helper = document.getElementById('create-submit-helper');

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

                // 4. Source selection change listener
                if (sourceSelect) {
                    sourceSelect.addEventListener('change', function() {
                        var text = getSubmitDefaultText();
                        if (submitBtn) {
                            submitBtn.dataset.defaultText = text;
                            if (sourceSelect.value === 'airbnb') {
                                submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                                submitBtn.classList.add('bg-[#FF385C]', 'hover:bg-[#E00B41]');
                            } else {
                                submitBtn.classList.remove('bg-[#FF385C]', 'hover:bg-[#E00B41]');
                                submitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                            }
                        }
                        if (submitBtnText && submitBtnText.textContent !== 'Checking availability...') {
                            submitBtnText.textContent = text;
                        }
                        validateForm();
                    });
                }

                // 5. Date inputs change listeners
                if (checkInInput) {
                    checkInInput.addEventListener('change', function() {
                        syncMinEndDate();
                        validateForm();
                    });
                    checkInInput.addEventListener('input', function() {
                        syncMinEndDate();
                        validateForm();
                    });
                    if (checkInInput.value) {
                        syncMinEndDate();
                    }
                }

                if (checkOutInput) {
                    checkOutInput.addEventListener('change', function() {
                        validateForm();
                    });
                    checkOutInput.addEventListener('input', function() {
                        validateForm();
                    });
                }

                // 6. HTMX Lifecycle Listeners for in-flight quote requests
                function isQuotePreviewRequest(evt) {
                    var target = evt.detail && evt.detail.target;
                    var quoteContainer = document.getElementById('quote-preview-container');
                    if (target && (target.id === 'quote-preview-container' || target === quoteContainer)) {
                        return true;
                    }
                    var path = evt.detail && (
                        (evt.detail.requestConfig && evt.detail.requestConfig.path) ||
                        (evt.detail.pathInfo && evt.detail.pathInfo.requestPath) ||
                        evt.detail.url
                    );
                    return typeof path === 'string' && path.indexOf('/reservations/quote-preview') !== -1;
                }

                function setCheckingState() {
                    isCheckingAvailability = true;
                    var btn = document.getElementById('create-submit-btn');
                    var btnText = document.getElementById('create-submit-text');
                    var helper = document.getElementById('create-submit-helper');
                    if (!btn) return;

                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.classList.remove('cursor-pointer');
                    if (btnText) {
                        btnText.textContent = 'Checking availability...';
                    }
                    if (helper) {
                        helper.textContent = 'Checking availability...';
                        helper.classList.remove('hidden');
                    }
                }

                function removeCheckingState() {
                    isCheckingAvailability = false;
                    var btn = document.getElementById('create-submit-btn');
                    var btnText = document.getElementById('create-submit-text');
                    if (!btn) return;

                    if (btnText) {
                        var defaultText = btn.dataset.defaultText || getSubmitDefaultText();
                        btnText.textContent = defaultText;
                    }
                    validateForm();
                }

                if (form) {
                    form.addEventListener('htmx:beforeRequest', function(evt) {
                        if (isQuotePreviewRequest(evt)) {
                            setCheckingState();
                        }
                    });

                    form.addEventListener('htmx:afterRequest', function(evt) {
                        if (isQuotePreviewRequest(evt)) {
                            removeCheckingState();
                        }
                    });

                    form.addEventListener('htmx:sendError', function(evt) {
                        if (isQuotePreviewRequest(evt)) {
                            removeCheckingState();
                        }
                    });

                    form.addEventListener('htmx:responseError', function(evt) {
                        if (isQuotePreviewRequest(evt)) {
                            removeCheckingState();
                        }
                    });

                    // 7. Form-wide event listeners for reactive matrix
                    form.addEventListener('input', function() {
                        validateForm();
                    });

                    form.addEventListener('change', function() {
                        validateForm();
                    });

                    form.addEventListener('availabilityChecked', function(evt) {
                        if (evt.detail && typeof evt.detail.available === 'boolean') {
                            isAvailable = evt.detail.available;
                        } else {
                            syncAvailabilityFromDom();
                        }
                        validateForm();
                    });

                    form.addEventListener('htmx:afterSwap', function(evt) {
                        syncAvailabilityFromDom();
                        validateForm();
                    });

                    // 8. Prevent submission / Enter keypress if validation fails
                    form.addEventListener('keydown', function(evt) {
                        if (evt.key === 'Enter') {
                            if (evt.target && evt.target.tagName === 'TEXTAREA') {
                                return;
                            }
                            if (!validateForm()) {
                                evt.preventDefault();
                            }
                        }
                    });

                    form.addEventListener('submit', function(evt) {
                        if (!validateForm()) {
                            evt.preventDefault();
                            evt.stopPropagation();
                        }
                    });
                }

                // 9. Initial validator run
                syncAvailabilityFromDom();
                validateForm();
            })();
            </script>

        </div>
    </div>
</div>
