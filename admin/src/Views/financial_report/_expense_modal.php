<?php
/**
 * Operational Expense Modal Partial (Create / Edit / Duplicate).
 *
 * @var bool $isEdit
 * @var bool $isDuplicate
 * @var int|null $expenseId
 * @var string|null $expenseUid
 * @var array<string, string> $form
 * @var list<string> $errors
 * @var array<string, string> $fieldErrors
 * @var array<string, string> $filters
 * @var string $csrfToken
 */

use OceanViewFlats\Domain\Finance\ExpenseCategory;

$isEdit = !empty($isEdit);
$isDuplicate = !empty($isDuplicate);
$expenseId = isset($expenseId) ? (int) $expenseId : null;
$expenseUid = isset($expenseUid) ? (string) $expenseUid : null;

$propertyId = (string) ($form['property_id'] ?? '1606');
$category = (string) ($form['category'] ?? 'cleaning');
$title = (string) ($form['title'] ?? '');
$amount = (string) ($form['amount'] ?? '');
$expenseDate = (string) ($form['expense_date'] ?? '');
$vendorName = (string) ($form['vendor_name'] ?? '');
$referenceCode = (string) ($form['reference_code'] ?? '');
$reservationUid = (string) ($form['reservation_uid'] ?? '');
$notes = (string) ($form['notes'] ?? '');

$actionUrl = ($isEdit && $expenseId !== null)
    ? '/financial-report/expenses/' . $expenseId
    : '/financial-report/expenses';

$modalHeading = $isEdit
    ? 'Edit Operational Expense' . ($expenseUid !== null && $expenseUid !== '' ? ' (' . $expenseUid . ')' : '')
    : ($isDuplicate ? 'Duplicate Operational Expense' : 'Record Operational Expense');
?>

<div id="expense-modal" class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-200 max-w-lg w-full overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 id="modal-title" class="text-base font-bold text-gray-900">
                    <?= htmlspecialchars($modalHeading, ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p class="text-xs text-gray-500">
                    All amounts recorded in Settlement Currency (COP). Logged to Audit Trail.
                </p>
            </div>
            <button type="button"
                    onclick="document.getElementById('modal-container').innerHTML = '';"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-md cursor-pointer"
                    aria-label="Close modal">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="expense-form"
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
                <div id="modal-error" class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 space-y-1">
                    <div class="font-bold">Validation Error:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="exp-property" class="block text-xs font-semibold text-gray-700 mb-1">Property Assignment *</label>
                    <select id="exp-property"
                            name="property_id"
                            required
                            class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['property_id']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs font-medium text-gray-900">
                        <option value="1606" <?= $propertyId === '1606' ? 'selected' : '' ?>>Property 1606 (100% Direct)</option>
                        <option value="1707" <?= $propertyId === '1707' ? 'selected' : '' ?>>Property 1707 (100% Direct)</option>
                        <option value="shared" <?= $propertyId === 'shared' ? 'selected' : '' ?>>Shared Portfolio (50/50 Split)</option>
                    </select>
                </div>
                <div>
                    <label for="exp-category" class="block text-xs font-semibold text-gray-700 mb-1">Expense Category *</label>
                    <select id="exp-category"
                            name="category"
                            required
                            class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['category']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs font-medium text-gray-900">
                        <?php foreach (ExpenseCategory::cases() as $catCase): ?>
                            <option value="<?= htmlspecialchars($catCase->value, ENT_QUOTES, 'UTF-8') ?>" <?= $category === $catCase->value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($catCase->label(), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="exp-title" class="block text-xs font-semibold text-gray-700 mb-1">Title / Description *</label>
                <input type="text"
                       id="exp-title"
                       name="title"
                       required
                       maxlength="160"
                       value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="e.g. Monthly HOA Administration — Oct 2026"
                       class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['title']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs text-gray-900" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="exp-amount" class="block text-xs font-semibold text-gray-700 mb-1">Amount (COP) *</label>
                    <input type="number"
                           id="exp-amount"
                           name="amount"
                           required
                           min="0.01"
                           step="any"
                           value="<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. 680000"
                           class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['amount']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs text-gray-900 tabular-nums" />
                </div>
                <div>
                    <label for="exp-date" class="block text-xs font-semibold text-gray-700 mb-1">Expense Date *</label>
                    <input type="date"
                           id="exp-date"
                           name="expense_date"
                           required
                           value="<?= htmlspecialchars($expenseDate, ENT_QUOTES, 'UTF-8') ?>"
                           class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['expense_date']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs text-gray-900" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="exp-vendor" class="block text-xs font-semibold text-gray-700 mb-1">Vendor / Payee (Optional)</label>
                    <input type="text"
                           id="exp-vendor"
                           name="vendor_name"
                           maxlength="120"
                           value="<?= htmlspecialchars($vendorName, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. Edificio Salguero Sunset"
                           class="block w-full py-2 px-3 bg-white border border-gray-300 rounded-lg text-xs text-gray-900" />
                </div>
                <div>
                    <label for="exp-ref" class="block text-xs font-semibold text-gray-700 mb-1">Invoice / Reference Code (Optional)</label>
                    <input type="text"
                           id="exp-ref"
                           name="reference_code"
                           maxlength="120"
                           value="<?= htmlspecialchars($referenceCode, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. FAC-2026-1094"
                           class="block w-full py-2 px-3 bg-white border border-gray-300 rounded-lg text-xs text-gray-900" />
                </div>
            </div>

            <div>
                <label for="exp-res-uid" class="block text-xs font-semibold text-gray-700 mb-1">Linked Reservation UID (Optional — must match Property)</label>
                <input type="text"
                       id="exp-res-uid"
                       name="reservation_uid"
                       maxlength="36"
                       value="<?= htmlspecialchars($reservationUid, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="e.g. ovf_91a4b2c8d1e0 or res-abnb-7c4e19a2"
                       class="block w-full py-2 px-3 bg-white border <?= isset($fieldErrors['reservation_uid']) ? 'border-rose-400' : 'border-gray-300' ?> rounded-lg text-xs font-mono text-gray-900" />
            </div>

            <div>
                <label for="exp-notes" class="block text-xs font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                <textarea id="exp-notes"
                          name="notes"
                          rows="2"
                          placeholder="Internal bookkeeping or reimbursement notes..."
                          class="block w-full py-2 px-3 bg-white border border-gray-300 rounded-lg text-xs text-gray-900"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="pt-3 border-t border-gray-200 flex items-center justify-end space-x-2">
                <button type="button"
                        onclick="document.getElementById('modal-container').innerHTML = '';"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50 cursor-pointer">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white shadow-xs cursor-pointer">
                    <?= $isEdit ? 'Update Operational Expense' : 'Save Operational Expense' ?>
                </button>
            </div>
        </form>
    </div>
</div>
