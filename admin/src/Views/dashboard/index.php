<?php
/**
 * Master Template: Admin Operational Dashboard Hub
 *
 * @var array<string, mixed> $currentUser
 * @var string $csrfToken
 * @var string $propertyId
 * @var string $hubContentHtml
 */
?>
<div class="space-y-6">
    <!-- Reactive Operational Hub Container (Swapped by HTMX on filter change) -->
    <?= $hubContentHtml ?>

    <!-- Modal and Drawer Insertion Targets -->
    <div id="modal-container"></div>
    <div id="drawer-container"></div>
</div>
