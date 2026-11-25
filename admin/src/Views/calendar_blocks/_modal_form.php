<?php
/**
 * Create Maintenance Calendar Block Modal Form Partial.
 *
 * @var string $propertyId
 * @var string $startDate
 * @var string $endDate
 * @var string $reason
 * @var string $csrfToken
 * @var list<string> $errors
 * @var string|null $infoMessage
 * @var string $filter
 */

$propertyId = !empty($propertyId) ? $propertyId : '1606';
$errors = !empty($errors) ? $errors : [];
$infoMessage = $infoMessage ?? null;
$filter = !empty($filter) ? $filter : 'upcoming';
?>

<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
         data-action="close-modal"
         aria-hidden="true"></div>

    <div class="min-h-full flex items-center justify-center p-4 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-gray-200">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900" id="modal-title">
                            Add Maintenance Hold
                        </h3>
                        <p class="text-xs text-gray-500">
                            Block dates for repairs, painting, or host private use
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
            <form hx-post="/calendar-blocks"
                  hx-target="#modal-container"
                  hx-swap="innerHTML"
                  class="p-6 space-y-4">
                
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="current_property_id" value="<?= htmlspecialchars($currentPropertyId ?? $propertyId, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Error Alert Banner (HTTP 422) -->
                <?php if (!empty($errors)): ?>
                    <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs space-y-1">
                        <div class="font-bold flex items-center">
                            <svg class="w-4 h-4 mr-1.5 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <span>Validation and Conflict Error:</span>
                        </div>
                        <ul class="list-disc list-inside ml-5 space-y-0.5">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Informational Alert Banner -->
                <?php if ($infoMessage !== null): ?>
                    <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span><?= htmlspecialchars($infoMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Property Selection -->
                <div>
                    <label for="block-property-id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        Property *
                    </label>
                    <select id="block-property-id" name="property_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="1606" <?= $propertyId === '1606' ? 'selected' : '' ?>>Apartment 1606</option>
                        <option value="1707" <?= $propertyId === '1707' ? 'selected' : '' ?>>Apartment 1707</option>
                    </select>
                </div>

                <!-- Date Range (Half-Open Interval [start_date, end_date)) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="block-start-date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Start Date (Check-in) *
                        </label>
                        <input type="date" id="block-start-date" name="start_date" required
                               value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="block-end-date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            End Date (Release Date) *
                        </label>
                        <input type="date" id="block-end-date" name="end_date" required
                               value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <p class="text-[11px] text-gray-500 italic">
                    Calendar holds use standard night-based intervals [start, end). The property becomes available for check-in on the afternoon of the end date.
                </p>

                <!-- Reason / Operational Note -->
                <div>
                    <label for="block-reason" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        Reason / Operational Justification *
                    </label>
                    <input type="text" id="block-reason" name="reason" required maxlength="255"
                           placeholder="e.g. AC compressor replacement, painting, host family stay"
                           value="<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-[11px] text-gray-500 mt-1">
                        Internal note. Never exported to external public calendar feeds (ADR 0006).
                    </p>
                </div>

                <!-- Modal Actions -->
                <div class="pt-4 border-t border-gray-200 flex items-center justify-end space-x-3">
                    <button type="button"
                            data-action="close-modal"
                            class="px-4 py-2 border border-gray-300 text-xs font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 border border-transparent text-xs font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-2xs transition cursor-pointer">
                        Save Maintenance Hold
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
