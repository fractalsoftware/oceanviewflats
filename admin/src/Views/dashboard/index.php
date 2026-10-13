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

<script>
function copyRegistryInvite(button) {
    if (!button) return;
    var inviteUrl = button.getAttribute('data-invite-url') || '';
    var guestName = button.getAttribute('data-guest-name') || 'Guest';
    var propId = button.getAttribute('data-prop-id') || '';
    
    var textToCopy = inviteUrl;
    if (inviteUrl && guestName) {
        textToCopy = "Hello " + guestName + ",\n\nPlease complete your guest registration for Apartment " + propId + " prior to arrival using this secure link:\n" + inviteUrl + "\n\nThank you!\nOcean View Flats Team";
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(textToCopy).then(function() {
            showCopyFeedback(button);
        }).catch(function() {
            fallbackCopy(textToCopy, button);
        });
    } else {
        fallbackCopy(textToCopy, button);
    }
}

function fallbackCopy(text, button) {
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        showCopyFeedback(button);
    } catch (err) {
        console.error('Unable to copy', err);
    }
    document.body.removeChild(textArea);
}

function showCopyFeedback(button) {
    var originalHtml = button.innerHTML;
    button.innerHTML = '<span>✓ Copied!</span>';
    button.classList.add('bg-emerald-600', 'text-white');
    button.classList.remove('bg-indigo-600');
    setTimeout(function() {
        button.innerHTML = originalHtml;
        button.classList.remove('bg-emerald-600');
        button.classList.add('bg-indigo-600');
    }, 2000);
}
</script>
