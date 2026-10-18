<?php
/**
 * @var array<string, mixed> $reservation
 * @var float $refundableBalance
 * @var bool $isOnlinePayment
 * @var string|null $errorMessage
 * @var array<string, mixed> $oldInput
 * @var string $csrfToken
 * @var \OceanViewFlats\Domain\Reservation\CancellationPreview|null $preview
 */

$preview = $preview ?? null;
$uid = (string) ($reservation['reservation_uid'] ?? '');
$propertyId = (string) ($reservation['property_id'] ?? '');
$guestName = (string) ($reservation['guest_name'] ?? '');
$guestEmail = (string) ($reservation['guest_email'] ?? '');
$checkIn = (string) ($reservation['check_in'] ?? '');
$checkOut = (string) ($reservation['check_out'] ?? '');
$totalPrice = (float) ($reservation['total_price'] ?? 0.0);
$refundedAmount = (float) ($reservation['refunded_amount'] ?? 0.0);
$mpPaymentId = (string) ($reservation['mercadopago_payment_id'] ?? '');

$selectedRefundType = (string) ($oldInput['refund_type'] ?? ($refundableBalance > 0 ? 'full' : 'none'));
$inputRefundAmount = (float) ($oldInput['refund_amount'] ?? $refundableBalance);
$inputReason = (string) ($oldInput['reason'] ?? '');
$sendCancellationEmail = !empty($oldInput)
    ? !empty($oldInput['send_cancellation_email'])
    : true;
?>

<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
         onclick="document.getElementById('modal-container').innerHTML = '';"
         aria-hidden="true"></div>

    <div class="min-h-full flex items-center justify-center p-4 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-xl sm:w-full border border-gray-200">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900" id="modal-title">
                            Cancel Reservation
                        </h3>
                        <p class="text-xs text-rose-700 font-mono">
                            <?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?> &bull; Unit <?= htmlspecialchars($propertyId, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
                <button type="button"
                        onclick="document.getElementById('modal-container').innerHTML = '';"
                        class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-rose-100/50 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form id="cancel-reservation-form"
                  hx-post="/reservations/<?= urlencode($uid) ?>/cancel"
                  hx-target="#modal-container"
                  hx-swap="innerHTML"
                  class="p-6 space-y-4">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <?php if (!empty($errorMessage)): ?>
                    <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-start space-x-2">
                        <svg class="w-4 h-4 text-rose-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <span class="font-bold block">Cancellation Failed</span>
                            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Booking Context Banner -->
                <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 text-xs space-y-1.5">
                    <div class="flex justify-between text-gray-700">
                        <span>Guest: <strong class="text-gray-900"><?= htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($guestEmail, ENT_QUOTES, 'UTF-8') ?>)</span>
                        <span class="text-gray-500"><?= htmlspecialchars($checkIn, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($checkOut, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="flex justify-between items-center pt-1.5 border-t border-gray-200 text-gray-600">
                        <span>Total: <strong>$<?= number_format($totalPrice, 0, '.', ',') ?> COP</strong></span>
                        <?php if ($refundedAmount > 0): ?>
                            <span>Prior Refunds: <span class="text-rose-600 font-medium">-$<?= number_format($refundedAmount, 0, '.', ',') ?> COP</span></span>
                        <?php endif; ?>
                        <span>Refundable Balance: <strong class="text-emerald-700 font-mono text-sm">$<?= number_format($refundableBalance, 0, '.', ',') ?> COP</strong></span>
                    </div>
                </div>

                <?php if ($preview !== null): ?>
                    <!-- Cancellation Policy Evaluation (Lifecycle Engine) -->
                    <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl text-xs space-y-1.5">
                        <div class="flex items-center justify-between text-blue-900 font-semibold">
                            <span class="flex items-center space-x-1.5">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Policy Evaluation (<?= $preview->daysUntilCheckIn ?> days until check-in)</span>
                            </span>
                            <span class="font-mono text-[11px] text-blue-700">
                                Suggested Max Refund: $<?= number_format($preview->suggestedMaxRefundCop, 0, '.', ',') ?> COP
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-[11px] text-blue-800 pt-1 border-t border-blue-100">
                            <span>Policy Retention: <strong>$<?= number_format($preview->suggestedPolicyRetentionCop, 0, '.', ',') ?> COP</strong></span>
                            <span>Remaining Refundable: <strong>$<?= number_format($preview->refundableBalanceCop, 0, '.', ',') ?> COP</strong></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Payment Channel Context -->
                <?php if (!$isOnlinePayment): ?>
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-start space-x-2">
                        <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <span class="font-bold">Offline / Direct Booking</span>
                            <p class="mt-0.5 text-[11px] text-amber-700">
                                This reservation has no Mercado Pago payment ID. Cancellation will not make external gateway calls. Any refund entered below will be recorded as a manual internal refund.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-indigo-50 border border-indigo-100 rounded-xl text-xs text-indigo-800 flex items-start space-x-2">
                        <svg class="w-4 h-4 text-indigo-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <span class="font-bold">Mercado Pago Gateway Payment</span>
                            <p class="mt-0.5 text-[11px] text-indigo-700">
                                Payment ID <code class="font-mono"><?= htmlspecialchars($mpPaymentId, ENT_QUOTES, 'UTF-8') ?></code>. Executing a refund will directly contact Mercado Pago with strict idempotency.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Cancellation Reason -->
                <div>
                    <label for="cancel-reason" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        Cancellation Reason <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="cancel-reason"
                              name="reason"
                              rows="2"
                              required
                              placeholder="e.g. Guest requested emergency cancellation, flight schedule change, no-show..."
                              class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-rose-500 focus:border-rose-500"><?= htmlspecialchars($inputReason, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Refund Options -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">
                        Refund Treatment
                    </label>
                    <div class="space-y-2 text-xs">
                        <!-- Full Refund Option -->
                        <label class="flex items-start p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition">
                            <input type="radio"
                                   name="refund_type"
                                   value="full"
                                   <?= $selectedRefundType === 'full' ? 'checked' : '' ?>
                                   <?= $refundableBalance <= 0 ? 'disabled' : '' ?>
                                   onchange="document.getElementById('partial-amount-container').classList.add('hidden'); document.getElementById('partial-refund-amount').disabled = true; document.getElementById('partial-refund-amount').required = false;"
                                   class="mt-0.5 text-rose-600 focus:ring-rose-500 h-4 w-4 border-gray-300">
                            <div class="ml-3">
                                <span class="block font-semibold text-gray-900">
                                    Full Refund ($<?= number_format($refundableBalance, 0, '.', ',') ?> COP)
                                </span>
                                <span class="block text-gray-500 text-[11px]">
                                    Issue 100% refund of the remaining balance to the original payment method.
                                </span>
                            </div>
                        </label>

                        <!-- Partial Refund Option -->
                        <label class="flex items-start p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition">
                            <input type="radio"
                                   name="refund_type"
                                   value="partial"
                                   <?= $selectedRefundType === 'partial' ? 'checked' : '' ?>
                                   <?= $refundableBalance <= 0 ? 'disabled' : '' ?>
                                   onchange="document.getElementById('partial-amount-container').classList.remove('hidden'); document.getElementById('partial-refund-amount').disabled = false; document.getElementById('partial-refund-amount').required = true;"
                                   class="mt-0.5 text-rose-600 focus:ring-rose-500 h-4 w-4 border-gray-300">
                            <div class="ml-3 flex-1">
                                <span class="block font-semibold text-gray-900">
                                    Partial Refund
                                </span>
                                <span class="block text-gray-500 text-[11px]">
                                    Refund a custom amount. The remainder is retained per policy.
                                </span>
                            </div>
                        </label>

                        <!-- Partial Amount Input Container -->
                        <div id="partial-amount-container" class="ml-7 <?= $selectedRefundType === 'partial' ? '' : 'hidden' ?>">
                            <label for="partial-refund-amount" class="block text-[11px] font-semibold text-gray-600 mb-1">
                                Refund Amount (COP) - Max $<?= number_format($refundableBalance, 0, '.', ',') ?>
                            </label>
                            <div class="relative rounded-md shadow-2xs w-48">
                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400 text-xs">$</span>
                                <input type="number"
                                       id="partial-refund-amount"
                                       name="refund_amount"
                                       min="<?= $refundableBalance > 0 ? 1 : 0 ?>"
                                       max="<?= max(0, (int) $refundableBalance) ?>"
                                       step="any"
                                       value="<?= (int) $inputRefundAmount ?>"
                                       <?= $selectedRefundType === 'partial' ? 'required' : 'disabled' ?>
                                       class="pl-6 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg w-full font-mono focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                            </div>
                        </div>

                        <!-- No Refund Option -->
                        <label class="flex items-start p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition">
                            <input type="radio"
                                   name="refund_type"
                                   value="none"
                                   <?= $selectedRefundType === 'none' ? 'checked' : '' ?>
                                   onchange="document.getElementById('partial-amount-container').classList.add('hidden'); document.getElementById('partial-refund-amount').disabled = true; document.getElementById('partial-refund-amount').required = false;"
                                   class="mt-0.5 text-rose-600 focus:ring-rose-500 h-4 w-4 border-gray-300">
                            <div class="ml-3">
                                <span class="block font-semibold text-gray-900">
                                    No Refund (Policy Retention)
                                </span>
                                <span class="block text-gray-500 text-[11px]">
                                    Cancel reservation dates and retain the full payment per cancellation policy.
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Warning Notice -->
                <div class="text-[11px] text-gray-500 border-t border-gray-100 pt-3">
                    <strong>Notice:</strong> Cancellation is terminal and cannot be reversed. Calendar dates will be immediately freed for new bookings.
                </div>

                <!-- Guest Notification Checkbox -->
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 flex items-start space-x-3">
                    <input type="checkbox"
                           id="send_cancellation_email"
                           name="send_cancellation_email"
                           value="1"
                           <?= $sendCancellationEmail ? 'checked' : '' ?>
                           class="mt-0.5 rounded text-rose-600 focus:ring-rose-500 h-4 w-4 border-gray-300">
                    <label for="send_cancellation_email" class="text-xs text-gray-700 cursor-pointer select-none">
                        <span class="font-semibold block text-gray-900">Send cancellation notice to guest</span>
                        <span class="text-gray-500 block text-[11px]">Dispatches a localized email with the refund breakdown to <code><?= htmlspecialchars($guestEmail) ?></code></span>
                    </label>
                </div>

                <!-- Modal Actions -->
                <div class="pt-2 flex justify-end space-x-2 border-t border-gray-200">
                    <button type="button"
                            onclick="document.getElementById('modal-container').innerHTML = '';"
                            class="px-3.5 py-2 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                        Keep Reservation
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition cursor-pointer flex items-center">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
