<?php
/**
 * Inner rates content partial: flash banner, visual timeline, and table.
 * Swapped directly by HTMX requests.
 *
 * @var string $propertyId
 * @var int $year
 * @var string|null $flashMessage
 * @var string|null $flashType
 * @var string $timelineHtml
 * @var string $tableHtml
 */
?>

<div class="space-y-6">

    <!-- Flash Message Banner -->
    <?php if (!empty($flashMessage)): ?>
        <?php
        $isSuccess = ($flashType ?? 'success') === 'success';
        $bgClass = $isSuccess ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900';
        $iconClass = $isSuccess ? 'text-emerald-600' : 'text-rose-600';
        ?>
        <div class="p-3.5 border rounded-xl text-xs flex items-center justify-between <?= $bgClass ?> shadow-2xs">
            <div class="flex items-center space-x-2">
                <svg class="w-4 h-4 <?= $iconClass ?> shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($isSuccess): ?>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    <?php else: ?>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    <?php endif; ?>
                </svg>
                <span class="font-medium"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button"
                    data-action="dismiss-parent"
                    class="text-gray-400 hover:text-gray-600 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    <?php endif; ?>

    <!-- Visual Chronological Timeline -->
    <?= $timelineHtml ?>

    <!-- Tabular Rates Table -->
    <?= $tableHtml ?>

</div>
