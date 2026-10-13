<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Admin Console - Ocean View Flats', ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- HTMX Configuration: Allow 422 Unprocessable Entity responses to swap into targets -->
    <meta name="htmx-config" content='{"responseHandling": [{"code": "204", "swap": false}, {"code": "[23]..", "swap": true}, {"code": "422", "swap": true}, {"code": "[45]..", "swap": false, "error": true}, {"code": "...", "swap": false}]}'>
    <!-- HTMX CDN -->
    <script src="https://unpkg.com/htmx.org@2.0.4"></script>
    <?php $effectiveCsrfToken = (string) ($csrfToken ?? ($_SESSION['csrf_token'] ?? '')); ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($effectiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator, .htmx-request.htmx-indicator { display: inline-flex; }

        .htmx-request:is(button, [type="submit"], a[hx-post], a[hx-delete], a[hx-put]),
        form.htmx-request button[type="submit"],
        form.htmx-request input[type="submit"] {
            pointer-events: none !important;
            opacity: 0.75 !important;
            cursor: wait !important;
        }

        @keyframes progress-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        #global-progress-bar.loading,
        body.htmx-request #global-progress-bar {
            background-image: linear-gradient(90deg, var(--color-indigo-600, #4f46e5) 0%, var(--color-indigo-400, #818cf8) 50%, var(--color-indigo-600, #4f46e5) 100%);
            background-size: 200% 100%;
            animation: progress-shimmer 1.5s infinite linear;
        }

        #global-progress-bar.bg-rose-500 {
            background-image: none !important;
            animation: none !important;
        }

        @media (prefers-reduced-motion: reduce) {
            #global-progress-bar {
                transition: none !important;
                animation: none !important;
            }
        }

        @media (max-width: 767px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body class="min-h-full flex flex-col font-sans text-gray-900 antialiased" hx-headers='{"HX-CSRF-Token": "<?= htmlspecialchars($effectiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>"}'>

<div id="global-progress-bar" class="fixed top-0 left-0 w-full h-1 bg-indigo-600 z-50 pointer-events-none transition-all duration-300 ease-out opacity-0" style="width: 0%;" role="status" aria-live="polite" aria-label="Loading"></div>
<div id="global-alert-container" class="fixed top-4 right-4 z-50 pointer-events-none space-y-2" role="alert" aria-live="assertive" data-msg-error="Network or server error occurred. Please try again." data-msg-dismiss="Dismiss alert"></div>

<?php if (!empty($currentUser)): ?>
<header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-8">
                <a href="/" class="flex items-center space-x-3 text-indigo-700 font-bold text-lg tracking-tight">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <span>Ocean View Flats <span class="text-xs bg-indigo-100 text-indigo-800 uppercase px-2 py-0.5 rounded font-semibold ml-1">Admin</span></span>
                </a>
                <nav class="hidden md:flex space-x-4">
                    <a href="/" class="px-3 py-2 rounded-md text-sm font-medium <?= ($currentRoute ?? '') === '/' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Dashboard
                    </a>
                    <a href="/reservations" class="px-3 py-2 rounded-md text-sm font-medium <?= ($currentRoute ?? '') === '/reservations' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Reservations
                    </a>
                    <a href="/rates" class="px-3 py-2 rounded-md text-sm font-medium <?= ($currentRoute ?? '') === '/rates' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Rates
                    </a>
                    <a href="/calendar-blocks" class="px-3 py-2 rounded-md text-sm font-medium <?= ($currentRoute ?? '') === '/calendar-blocks' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Calendar Blocks
                    </a>
                    <?php if (in_array($currentUser['role'] ?? '', ['admin', 'superadmin'], true)): ?>
                    <a href="/audit-logs" class="px-3 py-2 rounded-md text-sm font-medium <?= ($currentRoute ?? '') === '/audit-logs' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Audit Logs
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="hidden md:flex items-center space-x-4">
                <div class="flex items-center space-x-2 text-sm text-gray-700">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span class="font-medium"><?= htmlspecialchars((string) ($currentUser['name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-xs text-gray-500">(<?= htmlspecialchars((string) ($currentUser['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?>)</span>
                </div>
                <a href="/logout" class="text-sm font-medium text-gray-600 hover:text-red-600 px-3 py-1.5 rounded-md hover:bg-red-50 border border-transparent transition">
                    Sign Out
                </a>
            </div>
            <div class="flex items-center md:hidden">
                <button type="button"
                        id="mobile-menu-button"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 transition"
                        aria-controls="mobile-menu"
                        aria-expanded="false"
                        aria-label="Toggle navigation menu">
                    <svg id="mobile-menu-icon-open" class="block h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <svg id="mobile-menu-icon-close" class="hidden h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden border-t border-gray-200 bg-white">
        <nav class="px-4 pt-2 pb-3 space-y-1" aria-label="Mobile navigation">
            <a href="/" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Dashboard
            </a>
            <a href="/reservations" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/reservations' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Reservations
            </a>
            <a href="/rates" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/rates' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Rates
            </a>
            <a href="/calendar-blocks" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/calendar-blocks' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Calendar Blocks
            </a>
            <?php if (in_array($currentUser['role'] ?? '', ['admin', 'superadmin'], true)): ?>
            <a href="/audit-logs" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/audit-logs' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Audit Logs
            </a>
            <?php endif; ?>
        </nav>
        <div class="pt-3 pb-3 border-t border-gray-200 px-4 flex items-center justify-between">
            <div class="flex items-center space-x-2 text-sm text-gray-700">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                <span class="font-medium"><?= htmlspecialchars((string) ($currentUser['name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-xs text-gray-500">(<?= htmlspecialchars((string) ($currentUser['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?>)</span>
            </div>
            <a href="/logout" class="text-sm font-medium text-gray-600 hover:text-red-600 px-3 py-1.5 rounded-md hover:bg-red-50 border border-transparent transition">
                Sign Out
            </a>
        </div>
    </div>
</header>
<?php endif; ?>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <?php if (!empty($flashSuccess)): ?>
        <div class="mb-6 p-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center space-x-3">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="mb-6 p-4 rounded-md bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-3">
            <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
</main>

<footer class="bg-white border-t border-gray-200 mt-auto py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center text-xs text-gray-500">
        <span>&copy; <?= date('Y') ?> Ocean View Flats. Property Operations & Booking Engine.</span>
        <span>Isolated Subdomain Control &bull; PHP 8.3 &bull; HTMX 1.9</span>
    </div>
</footer>

<script>
// Global HTMX 422 validation response handler: ensures 422 validation errors swap into modal/form containers
document.body.addEventListener('htmx:beforeSwap', function(evt) {
    if (evt.detail && evt.detail.xhr && evt.detail.xhr.status === 422) {
        evt.detail.shouldSwap = true;
        evt.detail.isError = false;
    }
});

// Unified HTMX async loading indicator, button mutation locking, and error resiliency
(function() {
    var activeRequests = 0;
    var debounceTimer = null;
    var trickleTimers = [];
    var resetTimer = null;
    var errorHoldTimer = null;
    var errorResetTimer = null;
    var lockedElements = [];

    // --- Progress Bar & Alert Helpers ---
    function getProgressBar() {
        return document.getElementById('global-progress-bar');
    }

    function getAlertContainer() {
        return document.getElementById('global-alert-container');
    }

    function clearTrickleTimers() {
        for (var i = 0; i < trickleTimers.length; i++) {
            clearTimeout(trickleTimers[i]);
        }
        trickleTimers = [];
    }

    function showProgressBar() {
        var bar = getProgressBar();
        if (!bar || bar.classList.contains('bg-rose-500')) return;

        if (resetTimer) {
            clearTimeout(resetTimer);
            resetTimer = null;
        }
        clearTrickleTimers();

        bar.classList.add('loading');
        bar.classList.remove('opacity-0');
        bar.style.width = '25%';

        // Advance progress smoothly while requests remain active (>150ms)
        trickleTimers.push(setTimeout(function() {
            if (activeRequests > 0 && !bar.classList.contains('bg-rose-500')) {
                bar.style.width = '60%';
            }
        }, 250));

        trickleTimers.push(setTimeout(function() {
            if (activeRequests > 0 && !bar.classList.contains('bg-rose-500')) {
                bar.style.width = '85%';
            }
        }, 600));
    }

    function finishProgressBar() {
        var bar = getProgressBar();
        if (!bar || bar.classList.contains('bg-rose-500')) return;

        clearTrickleTimers();

        // Advance to 100%
        bar.style.width = '100%';

        // Fade out after completion transition
        resetTimer = setTimeout(function() {
            if (activeRequests > 0 || bar.classList.contains('bg-rose-500')) return;
            bar.classList.add('opacity-0');

            // Reset width to 0% after fade transition completes
            resetTimer = setTimeout(function() {
                if (activeRequests > 0 || bar.classList.contains('bg-rose-500')) return;
                bar.style.width = '0%';
                bar.classList.remove('loading');
                resetTimer = null;
            }, 300);
        }, 250);
    }

    function flashErrorProgressBar() {
        var bar = getProgressBar();
        if (!bar) return;

        if (errorHoldTimer) {
            clearTimeout(errorHoldTimer);
            errorHoldTimer = null;
        }
        if (errorResetTimer) {
            clearTimeout(errorResetTimer);
            errorResetTimer = null;
        }

        clearTrickleTimers();
        bar.classList.remove('loading');
        bar.classList.remove('bg-indigo-600');
        bar.classList.add('bg-rose-500');
        bar.classList.remove('opacity-0');
        bar.style.width = '100%';

        // Hold for ~1.2s, then fade out and reset color back to bg-indigo-600 and width to 0%
        errorHoldTimer = setTimeout(function() {
            bar.classList.add('opacity-0');
            errorResetTimer = setTimeout(function() {
                bar.style.width = '0%';
                bar.classList.remove('bg-rose-500');
                bar.classList.add('bg-indigo-600');
                errorHoldTimer = null;
                errorResetTimer = null;
            }, 300);
        }, 1200);
    }

    // --- Floating Alert Helpers (reads localized strings from DOM attributes) ---
    function showFloatingAlert(customMessage) {
        var container = getAlertContainer();
        if (!container) return;

        var defaultMsg = container.getAttribute('data-msg-error') || '';
        var dismissMsg = container.getAttribute('data-msg-dismiss') || '';
        var messageText = customMessage || defaultMsg;

        var toast = document.createElement('div');
        toast.className = 'bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm p-3.5 rounded-lg shadow-lg flex items-center justify-between space-x-3 pointer-events-auto transition-opacity duration-300';

        var content = document.createElement('div');
        content.className = 'flex items-center space-x-2.5';
        content.innerHTML = '<svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg><span></span>';
        content.querySelector('span').textContent = messageText;

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'text-rose-500 hover:text-rose-700 p-1 rounded-md focus:outline-none focus:ring-2 focus:ring-rose-400';
        closeBtn.setAttribute('aria-label', dismissMsg);
        closeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';

        var dismissTimer = null;
        function dismissToast() {
            if (dismissTimer) {
                clearTimeout(dismissTimer);
                dismissTimer = null;
            }
            toast.classList.add('opacity-0');
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        closeBtn.addEventListener('click', dismissToast);
        toast.appendChild(content);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        // Automatically fades out and dismisses after 4000ms
        dismissTimer = setTimeout(dismissToast, 4000);
    }

    // --- Mutation Locking Helpers ---
    function lockTarget(el) {
        if (!el) return;
        el.classList.add('htmx-request');
        el.setAttribute('aria-disabled', 'true');
        el.setAttribute('data-mutation-locked', 'true');
        if (lockedElements.indexOf(el) === -1) {
            lockedElements.push(el);
        }
    }

    function unlockTarget(el) {
        if (!el) return;
        el.classList.remove('htmx-request');
        el.removeAttribute('aria-disabled');
        el.removeAttribute('data-mutation-locked');
    }

    // Unified internal button unlocking helper used across all settle/error handlers
    function unlockAllButtons() {
        for (var i = 0; i < lockedElements.length; i++) {
            unlockTarget(lockedElements[i]);
        }
        lockedElements = [];
        var lingering = document.querySelectorAll('[data-mutation-locked="true"], .htmx-request:is(button, [type="submit"], a)');
        for (var j = 0; j < lingering.length; j++) {
            unlockTarget(lingering[j]);
        }
    }

    function isMutatingVerb(verb) {
        if (!verb) return false;
        var v = String(verb).toLowerCase();
        return v === 'post' || v === 'delete' || v === 'put';
    }

    // Unified mutating request detection helper
    function isMutatingElement(el) {
        if (!el) return false;
        if (el.hasAttribute && (el.hasAttribute('hx-post') || el.hasAttribute('hx-delete') || el.hasAttribute('hx-put'))) {
            return true;
        }
        if (el.tagName === 'FORM') {
            var m = (el.getAttribute('method') || '').toLowerCase();
            return isMutatingVerb(m) || el.hasAttribute('hx-post') || el.hasAttribute('hx-delete') || el.hasAttribute('hx-put');
        }
        if ((el.type === 'submit' || el.getAttribute('type') === 'submit') && el.form) {
            return isMutatingElement(el.form);
        }
        return false;
    }

    function isMutatingRequest(detail) {
        if (!detail) return false;
        if (detail.requestConfig && isMutatingVerb(detail.requestConfig.verb)) {
            return true;
        }
        return isMutatingElement(detail.elt);
    }

    function lockMutatingTriggers(elt) {
        if (!elt) return;
        if (elt.tagName === 'FORM') {
            lockTarget(elt);
            var submits = elt.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type]), [hx-post], [hx-delete], [hx-put]');
            for (var i = 0; i < submits.length; i++) {
                lockTarget(submits[i]);
            }
        } else {
            lockTarget(elt);
            if (elt.form) {
                lockTarget(elt.form);
                var siblingSubmits = elt.form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type]), [hx-post], [hx-delete], [hx-put]');
                for (var k = 0; k < siblingSubmits.length; k++) {
                    lockTarget(siblingSubmits[k]);
                }
            }
            if (elt.querySelectorAll) {
                var insideSubmits = elt.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type]), [hx-post], [hx-delete], [hx-put]');
                for (var j = 0; j < insideSubmits.length; j++) {
                    lockTarget(insideSubmits[j]);
                }
            }
        }
    }

    function unlockMutatingTriggers(elt) {
        if (!elt) {
            unlockAllButtons();
            return;
        }
        unlockTarget(elt);
        if (elt.form) {
            unlockTarget(elt.form);
            var siblingSubmits = elt.form.querySelectorAll('[data-mutation-locked="true"], button[type="submit"], input[type="submit"], button:not([type])');
            for (var k = 0; k < siblingSubmits.length; k++) {
                unlockTarget(siblingSubmits[k]);
                var sIdx = lockedElements.indexOf(siblingSubmits[k]);
                if (sIdx !== -1) lockedElements.splice(sIdx, 1);
            }
        }
        if (elt.querySelectorAll) {
            var submits = elt.querySelectorAll('[data-mutation-locked="true"], button[type="submit"], input[type="submit"], button:not([type])');
            for (var i = 0; i < submits.length; i++) {
                unlockTarget(submits[i]);
                var idx = lockedElements.indexOf(submits[i]);
                if (idx !== -1) {
                    lockedElements.splice(idx, 1);
                }
            }
        }
        var selfIdx = lockedElements.indexOf(elt);
        if (selfIdx !== -1) {
            lockedElements.splice(selfIdx, 1);
        }
    }

    // --- Active Request Tracking Helper ---
    function decrementActiveRequests(evt) {
        if (evt && evt.detail) {
            if (evt.detail._activeCounted) return false;
            evt.detail._activeCounted = true;
        }
        activeRequests = Math.max(0, activeRequests - 1);
        if (activeRequests === 0) {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }
            var bar = getProgressBar();
            if (bar && (!bar.classList.contains('opacity-0') || parseFloat(bar.style.width || '0') > 0)) {
                finishProgressBar();
            }
        }
        return true;
    }

    function handleAsyncError() {
        flashErrorProgressBar();
        showFloatingAlert();
        unlockAllButtons();
    }

    // Intercept click and submit events to provide 0ms locking before network latency
    document.body.addEventListener('click', function(evt) {
        var btn = evt.target && evt.target.closest('button, [type="submit"], a[hx-post], a[hx-delete], a[hx-put]');
        if (!btn) return;
        if (btn.hasAttribute('hx-confirm') || (btn.form && btn.form.hasAttribute('hx-confirm'))) return;
        if (isMutatingElement(btn)) {
            lockTarget(btn);
        }
    });

    document.body.addEventListener('submit', function(evt) {
        var form = evt.target;
        if (form && form.tagName === 'FORM' && isMutatingElement(form)) {
            lockMutatingTriggers(form);
        }
    });

    // Centralized HTMX lifecycle event listeners
    document.body.addEventListener('htmx:beforeRequest', function(evt) {
        activeRequests++;

        if (activeRequests === 1) {
            if (resetTimer) {
                clearTimeout(resetTimer);
                resetTimer = null;
            }
            // 150ms debounce threshold to prevent flashing on fast requests (<150ms)
            debounceTimer = setTimeout(function() {
                if (activeRequests > 0) {
                    showProgressBar();
                }
            }, 150);
        }

        // Lock mutating triggers, ensuring buttons with hx-confirm are locked upon confirmation / htmx:beforeRequest
        if (isMutatingRequest(evt.detail)) {
            lockMutatingTriggers(evt.detail && evt.detail.elt);
        }
    });

    document.body.addEventListener('htmx:afterRequest', function(evt) {
        decrementActiveRequests(evt);
        unlockMutatingTriggers(evt.detail && evt.detail.elt);
    });

    // Network drop/timeout: decrement activeRequests and trigger error resiliency
    document.body.addEventListener('htmx:sendError', function(evt) {
        decrementActiveRequests(evt);
        handleAsyncError();
    });

    // Server error (HTTP >= 500 or status 0): decrement activeRequests and trigger error resiliency
    document.body.addEventListener('htmx:responseError', function(evt) {
        decrementActiveRequests(evt);
        var status = evt.detail && evt.detail.xhr ? evt.detail.xhr.status : 0;
        if (status >= 500 || status === 0) {
            handleAsyncError();
        } else {
            unlockAllButtons();
        }
    });
})();

window.closeReservationDrawer = window.closeReservationDrawer || function() {
    var container = document.getElementById('drawer-container');
    if (container) {
        container.innerHTML = '';
    } else {
        window.location.href = '/reservations';
        return;
    }
    if (window.location.pathname.startsWith('/reservations/')) {
        window.history.pushState(null, '', '/reservations');
    }
};

// Mobile Hamburger Navigation Toggle
(function() {
    var menuBtn = document.getElementById('mobile-menu-button');
    var mobileMenu = document.getElementById('mobile-menu');
    var iconOpen = document.getElementById('mobile-menu-icon-open');
    var iconClose = document.getElementById('mobile-menu-icon-close');
    if (!menuBtn || !mobileMenu) return;

    function setMobileMenuOpen(isOpen) {
        menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            mobileMenu.classList.remove('hidden');
            if (iconOpen) iconOpen.classList.add('hidden');
            if (iconClose) iconClose.classList.remove('hidden');
        } else {
            mobileMenu.classList.add('hidden');
            if (iconOpen) iconOpen.classList.remove('hidden');
            if (iconClose) iconClose.classList.add('hidden');
        }
    }

    menuBtn.addEventListener('click', function() {
        var expanded = menuBtn.getAttribute('aria-expanded') === 'true';
        setMobileMenuOpen(!expanded);
    });

    document.addEventListener('keydown', function(evt) {
        if (evt.key === 'Escape' && menuBtn.getAttribute('aria-expanded') === 'true') {
            setMobileMenuOpen(false);
        }
    });

    document.addEventListener('click', function(evt) {
        if (menuBtn.getAttribute('aria-expanded') !== 'true') return;
        var header = menuBtn.closest('header');
        if (header && !header.contains(evt.target)) {
            setMobileMenuOpen(false);
        }
    });
})();
</script>

</body>
</html>
