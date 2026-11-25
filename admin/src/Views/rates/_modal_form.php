<?php
/**
 * Create / Edit Seasonal Rate Tier Modal.
 *
 * @var bool $isEdit
 * @var array<string, mixed> $rate
 * @var string $propertyId
 * @var int $year
 * @var string $csrfToken
 * @var string|null $errorMessage
 * @var array<string, string> $fieldErrors
 */

$rateId = (int) ($rate['id'] ?? 0);
$selectedPropertyId = (string) ($rate['property_id'] ?? $propertyId);
$seasonName = (string) ($rate['season_name'] ?? '');
$startDate = (string) ($rate['start_date'] ?? '');
$endDate = (string) ($rate['end_date'] ?? '');
$pricePerNight = isset($rate['price_per_night']) ? (float) $rate['price_per_night'] : 350000.0;
$minStay = isset($rate['min_stay']) ? (int) $rate['min_stay'] : 2;

$formAction = $isEdit ? "/rates/{$rateId}" : '/rates';
?>

<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="rate-modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
         data-action="close-modal"
         aria-hidden="true"></div>

    <div class="min-h-full flex items-center justify-center p-4 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-gray-200">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900" id="rate-modal-title">
                            <?= $isEdit ? 'Edit Seasonal Rate Tier' : 'Create Seasonal Rate Tier' ?>
                        </h3>
                        <p class="text-xs text-gray-500">
                            Property <?= htmlspecialchars($selectedPropertyId, ENT_QUOTES, 'UTF-8') ?> &bull; Non-overlapping seasonal pricing interval
                        </p>
                    </div>
                </div>
                <button type="button"
                        data-action="close-modal"
                        class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="rate-tier-form"
                  hx-post="<?= $formAction ?>"
                  hx-target="#modal-container"
                  hx-swap="innerHTML"
                  class="p-6 space-y-4">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="year" value="<?= $year ?>">

                <!-- Error Alert Banner -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-start space-x-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Property Selection -->
                <div>
                    <label for="rate-property-id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        Property Unit
                    </label>
                    <?php if ($isEdit): ?>
                        <input type="text"
                               readonly
                               value="Apartment <?= htmlspecialchars($selectedPropertyId, ENT_QUOTES, 'UTF-8') ?>"
                               class="block w-full px-3 py-2 text-xs bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium">
                        <input type="hidden" name="property_id" value="<?= htmlspecialchars($selectedPropertyId, ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <select id="rate-property-id"
                                name="property_id"
                                class="block w-full px-3 py-2 text-xs bg-white border border-gray-300 rounded-lg shadow-2xs focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="1606" <?= $selectedPropertyId === '1606' ? 'selected' : '' ?>>Apartment 1606</option>
                            <option value="1707" <?= $selectedPropertyId === '1707' ? 'selected' : '' ?>>Apartment 1707</option>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Season Name -->
                <div>
                    <label for="rate-season-name" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        Season Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="rate-season-name"
                           name="season_name"
                           required
                           placeholder="e.g., Semana Santa, High Season Dec-Jan"
                           value="<?= htmlspecialchars($seasonName, ENT_QUOTES, 'UTF-8') ?>"
                           class="block w-full px-3 py-2 text-xs bg-white border <?= isset($fieldErrors['season_name']) ? 'border-rose-400 focus:ring-rose-500' : 'border-gray-300 focus:ring-indigo-500' ?> rounded-lg shadow-2xs focus:outline-none focus:ring-1 focus:border-indigo-500">
                    <?php if (isset($fieldErrors['season_name'])): ?>
                        <p class="text-[11px] text-rose-600 mt-1"><?= htmlspecialchars($fieldErrors['season_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <!-- Date Range Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="rate-start-date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Start Date <span class="text-rose-500">*</span>
                        </label>
                        <input type="date"
                               id="rate-start-date"
                               name="start_date"
                               required
                               value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>"
                               class="block w-full px-3 py-2 text-xs bg-white border <?= isset($fieldErrors['start_date']) ? 'border-rose-400 focus:ring-rose-500' : 'border-gray-300 focus:ring-indigo-500' ?> rounded-lg shadow-2xs focus:outline-none focus:ring-1 focus:border-indigo-500 font-mono">
                        <?php if (isset($fieldErrors['start_date'])): ?>
                            <p class="text-[11px] text-rose-600 mt-1"><?= htmlspecialchars($fieldErrors['start_date'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="rate-end-date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            End Date <span class="text-rose-500">*</span>
                        </label>
                        <input type="date"
                               id="rate-end-date"
                               name="end_date"
                               required
                               value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>"
                               class="block w-full px-3 py-2 text-xs bg-white border <?= isset($fieldErrors['end_date']) ? 'border-rose-400 focus:ring-rose-500' : 'border-gray-300 focus:ring-indigo-500' ?> rounded-lg shadow-2xs focus:outline-none focus:ring-1 focus:border-indigo-500 font-mono">
                        <?php if (isset($fieldErrors['end_date'])): ?>
                            <p class="text-[11px] text-rose-600 mt-1"><?= htmlspecialchars($fieldErrors['end_date'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rate & Minimum Stay Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="rate-price" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Price / Night (COP) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-lg shadow-2xs">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500 text-xs font-bold">
                                $
                            </div>
                            <input type="number"
                                   id="rate-price"
                                   name="price_per_night"
                                   required
                                   min="50000"
                                   step="5000"
                                   value="<?= (int) $pricePerNight ?>"
                                   class="block w-full pl-7 pr-3 py-2 text-xs bg-white border <?= isset($fieldErrors['price_per_night']) ? 'border-rose-400 focus:ring-rose-500' : 'border-gray-300 focus:ring-indigo-500' ?> rounded-lg focus:outline-none focus:ring-1 focus:border-indigo-500 font-medium">
                        </div>
                        <?php if (isset($fieldErrors['price_per_night'])): ?>
                            <p class="text-[11px] text-rose-600 mt-1"><?= htmlspecialchars($fieldErrors['price_per_night'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="rate-min-stay" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Minimum Stay (Nights) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               id="rate-min-stay"
                               name="min_stay"
                               required
                               min="1"
                               max="30"
                               value="<?= $minStay ?>"
                               class="block w-full px-3 py-2 text-xs bg-white border <?= isset($fieldErrors['min_stay']) ? 'border-rose-400 focus:ring-rose-500' : 'border-gray-300 focus:ring-indigo-500' ?> rounded-lg shadow-2xs focus:outline-none focus:ring-1 focus:border-indigo-500 font-medium">
                        <?php if (isset($fieldErrors['min_stay'])): ?>
                            <p class="text-[11px] text-rose-600 mt-1"><?= htmlspecialchars($fieldErrors['min_stay'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Guidance Note -->
                <div class="text-[11px] text-gray-500 border-t border-gray-100 pt-3">
                    <strong>Rule:</strong> Interval dates must not overlap with any existing seasonal tier for Property <?= htmlspecialchars($selectedPropertyId, ENT_QUOTES, 'UTF-8') ?>. Both start and end dates are included in the coverage.
                </div>

                <!-- Form Actions -->
                <div class="pt-2 flex justify-end space-x-2 border-t border-gray-200">
                    <button type="button"
                            data-action="close-modal"
                            class="px-3.5 py-2 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition cursor-pointer flex items-center">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <?= $isEdit ? 'Save Changes' : 'Create Seasonal Tier' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
