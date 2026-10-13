<?php

declare(strict_types=1);

/**
 * OceanViewFlats - Web Setup & Migration Utility
 *
 * Provides a secure, browser-based bootstrap utility to execute database migrations
 * and provision the initial super-administrator when SSH/CLI access is unavailable.
 *
 * Security Requirements:
 * 1. Protected by pre-shared setup token (?token=... or X-Setup-Token header).
 * 2. Permanent auto-lockout once any administrator account exists in admin_users.
 * 3. Strict HTTPS POST requirement for administrator credential provisioning.
 */

$autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    $autoloader = dirname(__DIR__) . '/vendor/autoload.php';
}

if (file_exists($autoloader)) {
    require_once $autoloader;
}

use OceanViewFlats\Admin\Setup\SetupRunner;

// Handle requests
try {
    $runner = SetupRunner::create();
    $result = $runner->handleRequest($_SERVER, $_GET, $_POST);
} catch (Throwable $e) {
    $result = [
        'status' => 500,
        'data' => [
            'success' => false,
            'error' => 'Database or configuration failure: ' . $e->getMessage(),
        ],
    ];
}

$isJson = (isset($_GET['format']) && $_GET['format'] === 'json')
    || (isset($_POST['format']) && $_POST['format'] === 'json')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

http_response_code($result['status']);

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Render HTML interface
header('Content-Type: text/html; charset=utf-8');
$token = htmlspecialchars((string) ($_GET['token'] ?? $_POST['token'] ?? ''), ENT_QUOTES, 'UTF-8');
$status = $result['status'];
$data = $result['data'];
$isSuccess = $status === 200 && ($data['success'] ?? false);
$errorMessage = $data['error'] ?? null;
$logs = $data['logs'] ?? [];
$createdUser = $data['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Setup & Migrations — OceanViewFlats Admin</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260614">
  <link rel="icon" type="image/png" href="/favicon-96x96.png?v=20260614" sizes="96x96">
  <link rel="shortcut icon" href="/favicon.ico?v=20260614">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260614">
  <meta name="theme-color" content="#c084fc">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --bg: #0f172a;
      --card-bg: #1e293b;
      --card-border: #334155;
      --text: #f8fafc;
      --text-muted: #94a3b8;
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background-color: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .container {
      width: 100%;
      max-width: 640px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 1rem;
      padding: 2.25rem;
      box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5), 0 8px 10px -6px rgba(0,0,0,0.5);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }
    .brand-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 0.5rem;
      background: linear-gradient(135deg, #38bdf8, #0284c7);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
    }
    h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    p.subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; }
    .alert {
      padding: 1rem 1.25rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
      line-height: 1.4;
    }
    .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5; }
    .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #6ee7b7; }
    .alert-warning { background: rgba(245, 158, 11, 0.15); border: 1px solid var(--warning); color: #fcd34d; }
    .card-section {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid var(--card-border);
      border-radius: 0.75rem;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .card-section h2 {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .badge {
      display: inline-block;
      font-size: 0.75rem;
      padding: 0.2rem 0.5rem;
      border-radius: 9999px;
      font-weight: 600;
      background: #334155;
      color: #94a3b8;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.75rem 1.25rem;
      font-size: 0.95rem;
      font-weight: 600;
      border-radius: 0.5rem;
      cursor: pointer;
      width: 100%;
      transition: background 0.2s ease;
      text-decoration: none;
    }
    .btn:hover { background: var(--primary-hover); }
    .btn-secondary {
      background: #334155;
      color: #f8fafc;
    }
    .btn-secondary:hover { background: #475569; }
    .form-group {
      margin-bottom: 1rem;
    }
    label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.35rem;
      color: #cbd5e1;
    }
    input[type="text"], input[type="email"], input[type="password"] {
      width: 100%;
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 0.5rem;
      padding: 0.65rem 0.85rem;
      color: white;
      font-size: 0.95rem;
      outline: none;
    }
    input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus {
      border-color: var(--primary);
    }
    .log-box {
      background: #020617;
      border: 1px solid #1e293b;
      border-radius: 0.5rem;
      padding: 0.75rem 1rem;
      font-family: monospace;
      font-size: 0.85rem;
      max-height: 200px;
      overflow-y: auto;
      margin-top: 1rem;
      color: #38bdf8;
    }
    .log-entry { margin-bottom: 0.25rem; }
  </style>
</head>
<body>
  <div class="container">
    <div class="brand">
      <div class="brand-icon">OVF</div>
      <div>
        <h1>OceanViewFlats</h1>
        <p class="subtitle">System Bootstrap & Migration Tool</p>
      </div>
    </div>

    <?php if ($status === 403 && str_contains($errorMessage ?? '', 'locked')): ?>
      <div class="alert alert-warning">
        <strong>Setup Locked:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <p style="margin-bottom: 1.5rem; font-size: 0.95rem; color: var(--text-muted);">
        An administrator account is already provisioned in the database. For security, setup and migration execution via this web interface is permanently locked.
      </p>
      <a href="./" class="btn">Proceed to Admin Login</a>

    <?php elseif ($status === 403): ?>
      <div class="alert alert-danger">
        <strong>Access Denied (403):</strong> <?= htmlspecialchars($errorMessage ?? 'Unauthorized', ENT_QUOTES, 'UTF-8') ?>
      </div>
      <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
        To access this utility, append your pre-shared secret token in the URL:
        <br>
        <code>https://admin.oceanviewflats.com/setup.php?token=YOUR_SECRET_TOKEN</code>
        <br><br>
        Acceptable tokens include your <code>OVF_SETUP_TOKEN</code>, <code>OVF_ADMIN_SESSION_SECRET</code>, or <code>DB_PASS</code>.
      </p>

    <?php else: ?>

      <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
          <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php elseif (!empty($createdUser)): ?>
        <div class="alert alert-success">
          <strong>Setup Complete:</strong> Administrator <?= htmlspecialchars($createdUser['email'], ENT_QUOTES, 'UTF-8') ?> created. Setup is now locked.
        </div>
        <a href="./" class="btn" style="margin-bottom: 1.5rem;">Proceed to Admin Login</a>
      <?php elseif (!empty($logs)): ?>
        <div class="alert alert-success">
          <strong>Success:</strong> Database migrations completed.
        </div>
      <?php endif; ?>

      <?php if (empty($createdUser)): ?>
        <!-- Section 1: Schema Migrations -->
        <div class="card-section">
          <h2>
            <span>1. Database Migrations</span>
            <span class="badge">Idempotent</span>
          </h2>
          <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">
            Verifies and creates all required tables (reservations, idempotency, audit logs, admin users, calendar blocks, and rates).
          </p>

          <form method="POST" action="setup.php?token=<?= $token ?>">
            <input type="hidden" name="action" value="migrate">
            <input type="hidden" name="token" value="<?= $token ?>">
            <button type="submit" class="btn btn-secondary">Run Database Migrations</button>
          </form>

          <?php if (!empty($logs)): ?>
            <div class="log-box">
              <?php foreach ($logs as $log): ?>
                <div class="log-entry">✓ <?= htmlspecialchars($log, ENT_QUOTES, 'UTF-8') ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Section 2: Administrator Account Creation -->
        <div class="card-section">
          <h2>
            <span>2. Provision Super Administrator</span>
            <span class="badge">Argon2id Secured</span>
          </h2>
          <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Create the primary administrator account. Once created, setup will be permanently locked against further execution.
          </p>

          <form method="POST" action="setup.php?token=<?= $token ?>">
            <input type="hidden" name="action" value="create_admin">
            <input type="hidden" name="token" value="<?= $token ?>">

            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" id="name" name="name" required placeholder="e.g. Property Admin">
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" required placeholder="admin@oceanviewflats.com">
            </div>

            <div class="form-group">
              <label for="password">Password (minimum 8 characters)</label>
              <input type="password" id="password" name="password" required minlength="8" placeholder="••••••••••••">
            </div>

            <button type="submit" class="btn">Create Administrator & Lock Setup</button>
          </form>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</body>
</html>
