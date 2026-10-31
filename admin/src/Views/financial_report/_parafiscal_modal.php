<?php
/**
 * FONTUR Parafiscal Settlement Modal Partial (Mark as Paid / Edit Settlement).
 *
 * @var \OceanViewFlats\Domain\Finance\ParafiscalPeriod $parafiscalPeriod
 * @var bool $isEdit
 * @var array<string, string> $form
 * @var list<string> $errors
 * @var array<string, string> $fieldErrors
 * @var array<string, string> $filters
 * @var string $csrfToken
 */

$isEdit = !empty($isEdit);
$periodKey = $parafiscalPeriod->periodKey;
$propertyId = $parafiscalPeriod->propertyId;
$rntNumber = $parafiscalPeriod->rntNumber;
$settlement = $parafiscalPeriod->settlement;

$settledAmount = (string) ($form['settled_amount'] ?? '');
$paymentDate = (string) ($form['payment_date'] ?? '');
$referenceCode = (string) ($form['reference_code'] ?? '');
$notes = (string) ($form['notes'] ?? '');

$actionUrl = sprintf('/financial-report/parafiscal/%s/%s/settle', urlencode($periodKey), urlencode($propertyId));
$revertUrl = sprintf('/financial-report/parafiscal/%s/%s/revert', urlencode($periodKey), urlencode($propertyId));

$modalHeading = $isEdit
    ? sprintf('Edit FONTUR Parafiscal Settlement — %s (Property %s · RNT #%s)', $periodKey, $propertyId, $rntNumber)
    : sprintf('Settle FONTUR Parafiscal Contribution — %s (Property %s · RNT #%s)', $periodKey, $propertyId, $rntNumber);

if (!function_exists('formatModalCopCurrency')) {
    function formatModalCopCurrency(float $amount): string
    {
        return '$ ' . number_format(round($amount), 0, ',', '.') . ' COP';
    }
}
?>

<div id="parafiscal-modal" class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="parafiscal-modal-title">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-200 max-w-lg w-full overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 id="parafiscal-modal-title" class="text-base font-bold text-gray-900">
                    <?= htmlspecialchars($modalHeading, ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p class="text-xs text-gray-500">
                    <?= htmlspecialchars($parafiscalPeriod->periodLabel, ENT_QUOTES, 'UTF-8') ?> &middot; Statutory Due Date: <strong class="font-mono text-gray-700"><?= htmlspecialchars($parafiscalPeriod->dueDate, ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
            </div>
            <button type="button"
                    onclick="var m = document.getElementById('modal-container'); if (m) { m.innerHTML = ''; }"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-md cursor-pointer"
                    aria-label="Close modal">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="parafiscal-settlement-form"
              action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>"
              method="POST"
              hx-post="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              class="p-6 space-y-4">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_property_id" value="<?= htmlspecialchars((string) ($filters['property_id'] ?? 'all'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_period" value="<?= htmlspecialchars((string) ($filters['period'] ?? 'this_month'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_date_from" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_date_to" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_compare" value="<?= htmlspecialchars((string) ($filters['compare'] ?? 'prev_period'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_source" value="<?= htmlspecialchars((string) ($filters['source'] ?? 'all'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_category" value="<?= htmlspecialchars((string) ($filters['category'] ?? 'all'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_tab" value="<?= htmlspecialchars((string) ($filters['tab'] ?? 'expenses'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="current_search" value="<?= htmlspecialchars((string) ($filters['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

            <?php if (!empty($errors)): ?>
                <div id="parafiscal-modal-error" class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 space-y-1">
                    <div class="font-bold">Validation Error:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Statutory Assessment Breakdown Summary Card -->
            <div class="rounded-xl bg-indigo-50/50 border border-indigo-200 p-3.5 text-xs space-y-1.5 tabular-nums">
                <div class="flex items-center justify-between text-gray-600">
                    <span>Commercial Stay Revenue (<?= htmlspecialchars($parafiscalPeriod->periodStart, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($parafiscalPeriod->periodEnd, ENT_QUOTES, 'UTF-8') ?>)</span>
                    <span class="font-semibold text-gray-900"><?= formatModalCopCurrency($parafiscalPeriod->activeStayRevenue) ?></span>
                </div>
                <div class="flex items-center justify-between text-gray-600">
                    <span>Prorated Direct Cleaning &amp; Resort Fees Deducted</span>
                    <span class="font-medium text-rose-700">- <?= formatModalCopCurrency($parafiscalPeriod->deductedDirectFees) ?></span>
                </div>
                <div class="pt-1.5 border-t border-indigo-200/70 flex items-center justify-between font-bold text-gray-900">
                    <span>Parafiscal Taxable Base</span>
                    <span><?= formatModalCopCurrency($parafiscalPeriod->taxableBase) ?></span>
                </div>
                <div class="flex items-center justify-between font-extrabold text-emerald-700 text-sm pt-0.5">
                    <span>Calculated Contribution (0.25% Rounded to 1 COP)</span>
                    <span><?= formatModalCopCurrency($parafiscalPeriod->calculatedContribution) ?></span>
                </div>
                <?php if ($parafiscalPeriod->hasRecalculationVariance && $settlement !== null): ?>
                    <div class="pt-1.5 border-t border-amber-200 flex items-center justify-between text-[11px] text-amber-800 font-semibold">
                        <span>Recalculated Variance vs. Settled Snapshot</span>
                        <span>Snapshot: <?= formatModalCopCurrency($settlement->settledCalculatedAmount ?? 0.0) ?> (Delta: <?= ($parafiscalPeriod->recalculationVarianceDelta >= 0 ? '+' : '') . formatModalCopCurrency($parafiscalPeriod->recalculationVarianceDelta) ?>)</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="pfs-settled-amount" class="block text-xs font-semibold text-gray-700 mb-1">Settled Amount (COP) *</label>
                    <input type="number"
                           id="pfs-settled-amount"
                           name="settled_amount"
                           required
                           min="0"
                           step="any"
                           value="<?= htmlspecialchars($settledAmount, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. 10000 or 0"
                           class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['settled_amount']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs text-gray-900 font-semibold tabular-nums" />
                    <p class="text-[10px] text-gray-500 mt-1">
                        Enter <code>0</code> for a zero-revenue quarter (<em>declaraci&oacute;n en cero</em>, no Operational Expense created). Amounts &ge; 1 COP auto-sync a <code>taxes_insurance</code> expense.
                    </p>
                </div>
                <div>
                    <label for="pfs-payment-date" class="block text-xs font-semibold text-gray-700 mb-1">Payment / Filing Date *</label>
                    <input type="date"
                           id="pfs-payment-date"
                           name="payment_date"
                           required
                           value="<?= htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8') ?>"
                           class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['payment_date']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs text-gray-900" />
                    <p class="text-[10px] text-gray-500 mt-1">
                        Used as <code>expense_date</code> on the linked FONTUR Operational Expense.
                    </p>
                </div>
            </div>

            <div>
                <label for="pfs-reference-code" class="block text-xs font-semibold text-gray-700 mb-1">FONTUR Declaration / Receipt Reference (Optional)</label>
                <input type="text"
                       id="pfs-reference-code"
                       name="reference_code"
                       maxlength="120"
                       value="<?= htmlspecialchars($referenceCode, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="e.g. FNT-2026-Q1-270413"
                       class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['reference_code']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs font-mono text-gray-900" />
            </div>

            <div>
                <label for="pfs-notes" class="block text-xs font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                <textarea id="pfs-notes"
                          name="notes"
                          rows="2"
                          placeholder="Optional filing details, bank payment confirmation, or interest adjustment notes..."
                          class="block w-full py-2 px-3 bg-white border border-gray-300 rounded-lg text-xs text-gray-900"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="pt-3 border-t border-gray-200 flex items-center justify-between gap-2">
                <div>
                    <?php if ($isEdit): ?>
                        <button type="button"
                                hx-post="<?= htmlspecialchars($revertUrl, ENT_QUOTES, 'UTF-8') ?>"
                                hx-vals='<?= htmlspecialchars((string) json_encode([
                                    'csrf_token' => $csrfToken,
                                    'current_property_id' => $filters['property_id'] ?? 'all',
                                    'current_period' => $filters['period'] ?? 'this_month',
                                    'current_date_from' => $filters['date_from'] ?? '',
                                    'current_date_to' => $filters['date_to'] ?? '',
                                    'current_compare' => $filters['compare'] ?? 'prev_period',
                                    'current_source' => $filters['source'] ?? 'all',
                                    'current_category' => $filters['category'] ?? 'all',
                                    'current_tab' => $filters['tab'] ?? 'expenses',
                                    'current_search' => $filters['search'] ?? '',
                                ], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'
                                hx-target="#financial-report-content"
                                hx-swap="outerHTML"
                                hx-confirm="Are you sure you want to revert this Parafiscal Settlement back to Pending? Any linked FONTUR Operational Expense will be deleted."
                                class="px-3 py-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold cursor-pointer">
                            Revert to Pending
                        </button>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button"
                            onclick="var m = document.getElementById('modal-container'); if (m) { m.innerHTML = ''; }"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white shadow-xs cursor-pointer">
                        <?= $isEdit ? 'Update Parafiscal Settlement' : 'Confirm & Record Settlement' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
