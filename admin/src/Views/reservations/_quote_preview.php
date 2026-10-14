<?php
/**
 * @var bool $isAvailable
 * @var list<string> $conflictReasons
 * @var \OceanViewFlats\Domain\Quote\Quote|null $quote
 * @var string $source
 * @var float $defaultPrice
 * @var bool|null $isEditMode
 */

$editModeActive = !empty($isEditMode);
$isFullyAvailable = ($isAvailable === true && $quote !== null);
$reasons = $conflictReasons;
if (!$isFullyAvailable && empty($reasons)) {
    $reasons = ['Selected dates are not available.'];
}

$calculatedTotalCop = $quote !== null ? (float) $quote->totalCop : (float) $defaultPrice;
$displayPrice = $editModeActive ? $calculatedTotalCop : (float) $defaultPrice;

$eventDetail = [
    'available' => $isFullyAvailable,
    'conflictReasons' => $reasons,
    'defaultPrice' => $isFullyAvailable ? (float) $defaultPrice : null,
    'calculatedQuote' => $isFullyAvailable ? $calculatedTotalCop : null,
];
?>

<div id="quote-preview-result" data-available="<?= $isFullyAvailable ? 'true' : 'false' ?>" data-calculated-quote="<?= $isFullyAvailable ? (int) $calculatedTotalCop : '' ?>" class="w-full">
    <?php if (!$isAvailable || $quote === null): ?>
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 space-y-1.5 shadow-2xs">
            <div class="font-bold flex items-center text-rose-900">
                <svg class="w-4 h-4 mr-1.5 text-rose-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                Selected Dates Are Unavailable
            </div>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-rose-700 pl-1">
                <?php foreach ($reasons as $reason): ?>
                    <?php
                    // Turn direct reservation UID mentions into clickable admin links
                    $formattedReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                    $linkedReason = preg_replace_callback(
                        '/\b(res-[a-zA-Z0-9_-]+)\b/',
                        fn($m) => sprintf(
                            '<a href="/reservations/%s" target="_blank" class="underline font-semibold hover:text-rose-950">%s ↗</a>',
                            urlencode($m[1]),
                            $m[1]
                        ),
                        $formattedReason
                    );
                    ?>
                    <li><?= $linkedReason ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900 space-y-2.5 shadow-2xs">
            <div class="flex justify-between items-center font-bold text-emerald-800 flex-wrap gap-2">
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1.5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    Available (<?= $quote->nightsCount ?> <?= $quote->nightsCount === 1 ? 'Night' : 'Nights' ?>)
                </span>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-extrabold text-emerald-900">
                        $<?= number_format($displayPrice, 0, '.', ',') ?> COP
                    </span>
                    <?php if ($editModeActive): ?>
                        <button type="button"
                                id="btn-apply-calculated-quote"
                                data-quote-amount="<?= (int) $calculatedTotalCop ?>"
                                onclick="var priceEl = document.getElementById('edit-total-price'); if (priceEl) { priceEl.value = '<?= (int) $calculatedTotalCop ?>'; priceEl.dispatchEvent(new Event('input', { bubbles: true })); }"
                                class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold shadow-2xs transition cursor-pointer">
                            Apply Calculated Quote
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-emerald-200/70 text-[11px] text-emerald-700">
                <div>
                    <span class="block text-emerald-600 text-[10px] uppercase font-semibold">Nightly Stay</span>
                    <span class="font-medium">$<?= number_format($quote->accommodationTotalCop, 0, '.', ',') ?></span>
                </div>
                <div>
                    <span class="block text-emerald-600 text-[10px] uppercase font-semibold">Cleaning Fee</span>
                    <span class="font-medium">$<?= number_format($quote->cleaningFeeCop, 0, '.', ',') ?></span>
                </div>
                <div>
                    <span class="block text-emerald-600 text-[10px] uppercase font-semibold">Building Fee</span>
                    <span class="font-medium">$<?= number_format($quote->resortFeeCop, 0, '.', ',') ?></span>
                </div>
            </div>

            <?php if (!$editModeActive && $source === 'airbnb'): ?>
                <p class="text-[10px] text-emerald-800 italic font-medium">
                    * Airbnb booking absorbs overlapping Airbnb channel blocks. Pricing defaults to $0.00 COP (Host Payout).
                </p>
            <?php elseif (!$editModeActive && $source === 'owner_stay'): ?>
                <p class="text-[10px] text-emerald-800 italic font-medium">
                    * Owner Stay automatically zeroes total pricing ($0.00 COP).
                </p>
            <?php elseif ($editModeActive): ?>
                <p class="text-[10px] text-emerald-800 italic font-medium">
                    * Standard rate Quote shown above. Existing total price is preserved unless you click "Apply Calculated Quote" or edit it manually.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        (function() {
            var form = document.getElementById('create-reservation-form') || document.getElementById('edit-reservation-form');
            var target = form || document;
            var detail = <?= json_encode($eventDetail, JSON_THROW_ON_ERROR) ?>;
            target.dispatchEvent(new CustomEvent('availabilityChecked', {
                bubbles: true,
                detail: detail
            }));

            <?php if ($isFullyAvailable && !$editModeActive): ?>
            var priceInput = document.getElementById('manual-total-price');
            if (priceInput && (!priceInput.value || priceInput.dataset.autoFilled === 'true')) {
                priceInput.value = '<?= (int) $defaultPrice ?>';
                priceInput.dataset.autoFilled = 'true';
            }
            <?php endif; ?>
        })();
    </script>
</div>
