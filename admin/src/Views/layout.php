<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Admin Console - Ocean View Flats', ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Favicons & Touch Icons -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260614">
    <link rel="icon" type="image/png" href="/favicon-96x96.png?v=20260614" sizes="96x96">
    <link rel="shortcut icon" href="/favicon.ico?v=20260614">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260614">
    <!-- PWA & Mobile Web App Metadata -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="OVF Admin">
    <meta name="theme-color" content="#c084fc">
    <link rel="manifest" href="/site.webmanifest?v=20260614">
    <!-- Local Compiled Tailwind CSS & Admin Styles -->
    <link rel="stylesheet" href="/assets/admin.css">
    <!-- HTMX Configuration: Allow 422 Unprocessable Entity responses to swap into targets; enforce strict CSP-compatible flags -->
    <meta name="htmx-config" content='{"allowEval": false, "allowScriptTags": false, "includeIndicatorStyles": false, "responseHandling": [{"code": "204", "swap": false}, {"code": "[23]..", "swap": true}, {"code": "422", "swap": true}, {"code": "[45]..", "swap": false, "error": true}, {"code": "...", "swap": false}]}'>
    <!-- Local Vendored HTMX 2.0.4 -->
    <script src="/assets/htmx.min.js"></script>
    <?php $effectiveCsrfToken = (string) ($csrfToken ?? ($_SESSION['csrf_token'] ?? '')); ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($effectiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
                    <?php if (\OceanViewFlats\Admin\Auth\AdminRole::tryFrom((string) ($currentUser['role'] ?? ''))?->isPrivileged() === true): ?>
                    <a href="/financial-report" class="px-3 py-2 rounded-md text-sm font-medium <?= ($currentRoute ?? '') === '/financial-report' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Financial Report
                    </a>
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
                    <span class="text-xs text-gray-500">(<?= htmlspecialchars((string) ($currentUser['role'] ?? 'viewer'), ENT_QUOTES, 'UTF-8') ?>)</span>
                </div>
                <form method="POST" action="/logout" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($effectiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="text-sm font-medium text-gray-600 hover:text-red-600 px-3 py-1.5 rounded-md hover:bg-red-50 border border-transparent transition cursor-pointer">
                        Sign Out
                    </button>
                </form>
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
            <?php if (\OceanViewFlats\Admin\Auth\AdminRole::tryFrom((string) ($currentUser['role'] ?? ''))?->isPrivileged() === true): ?>
            <a href="/financial-report" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/financial-report' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Financial Report
            </a>
            <a href="/audit-logs" class="block px-3 py-2 rounded-md text-base font-medium <?= ($currentRoute ?? '') === '/audit-logs' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                Audit Logs
            </a>
            <?php endif; ?>
        </nav>
        <div class="pt-3 pb-3 border-t border-gray-200 px-4 flex items-center justify-between">
            <div class="flex items-center space-x-2 text-sm text-gray-700">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                <span class="font-medium"><?= htmlspecialchars((string) ($currentUser['name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-xs text-gray-500">(<?= htmlspecialchars((string) ($currentUser['role'] ?? 'viewer'), ENT_QUOTES, 'UTF-8') ?>)</span>
            </div>
            <form method="POST" action="/logout" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($effectiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="text-sm font-medium text-gray-600 hover:text-red-600 px-3 py-1.5 rounded-md hover:bg-red-50 border border-transparent transition cursor-pointer">
                    Sign Out
                </button>
            </form>
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

    <script src="/assets/admin.js"></script>

</body>
</html>
