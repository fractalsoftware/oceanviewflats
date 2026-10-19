<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Database;

use OceanViewFlats\Domain\Security\PayloadEncryptor;
use PDO;
use PDOException;

/**
 * Executes idempotent database schema creation and updates.
 *
 * Supports both direct database connection (for restricted cPanel shared hosting users)
 * and server-level fallback with CREATE DATABASE (for local development).
 */
final class MigrationRunner
{
    /**
     * Connects to MySQL/MariaDB, attempting direct connection to target database first.
     * Falls back to server connection + CREATE DATABASE only if database doesn't exist.
     *
     * @param array<string, mixed> $config Database configuration
     * @return PDO Active PDO connection
     * @throws PDOException If connection fails
     */
    public static function connect(array $config): PDO
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = isset($config['port']) ? (int) $config['port'] : 3306;
        $dbname = (string) ($config['dbname'] ?? 'oceanviewflats_db');
        $user = (string) ($config['user'] ?? 'root');
        $pass = (string) ($config['pass'] ?? '');

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // 1. Attempt direct connection to target database (cPanel shared hosting model)
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $directException) {
            // 2. Fall back to server connection and CREATE DATABASE (local development)
            try {
                $serverDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $pdo = new PDO($serverDsn, $user, $pass, $options);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbname}`");
                return $pdo;
            } catch (PDOException) {
                // If server-level also fails, re-throw the original direct exception
                throw $directException;
            }
        }
    }

    /**
     * Executes all schema migrations idempotently and returns a log report.
     *
     * @param PDO $pdo Active PDO connection
     * @param string $dbName Database name
     * @return list<string> Migration execution logs
     */
    public static function run(PDO $pdo, string $dbName): array
    {
        $logs = [];
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            self::runSqliteMigrations($pdo, $logs);
            return $logs;
        }

        self::runMysqlMigrations($pdo, $dbName, $logs);

        return $logs;
    }

    /**
     * @param PDO $pdo
     * @param string $dbName
     * @param list<string> $logs
     */
    private static function runMysqlMigrations(PDO $pdo, string $dbName, array &$logs): void
    {
        // 1. reservations table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `reservations` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `reservation_uid` VARCHAR(36) NOT NULL UNIQUE,
              `property_id` VARCHAR(10) NOT NULL,
              `guest_name` VARCHAR(120) NOT NULL,
              `guest_email` VARCHAR(100) NOT NULL,
              `guest_phone` VARCHAR(25) NOT NULL,
              `check_in` DATE NOT NULL,
              `check_out` DATE NOT NULL,
              `total_price` DECIMAL(10, 2) NOT NULL,
              `refunded_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
              `source` VARCHAR(30) NOT NULL DEFAULT 'web',
              `external_confirmation_code` VARCHAR(64) DEFAULT NULL,
              `channel_block_uid` VARCHAR(128) DEFAULT NULL,
              `mercadopago_preference_id` VARCHAR(255) DEFAULT NULL,
              `mercadopago_payment_id` VARCHAR(255) DEFAULT NULL,
              `payment_status` VARCHAR(50) DEFAULT NULL,
              `payment_method_id` VARCHAR(50) DEFAULT NULL,
              `payment_detail` TEXT DEFAULT NULL,
              `status` ENUM('pending_payment', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending_payment',
              `lang` VARCHAR(5) NOT NULL DEFAULT 'en',
              `registry_completed` TINYINT(1) NOT NULL DEFAULT 0,
              `registry_completed_at` DATETIME DEFAULT NULL,
              `door_code` VARCHAR(20) DEFAULT NULL,
              `notes` TEXT DEFAULT NULL,
              `max_guests` TINYINT UNSIGNED NOT NULL DEFAULT 6,
              `privacy_policy_version` VARCHAR(16) DEFAULT NULL,
              `privacy_consent_at` DATETIME DEFAULT NULL,
              `privacy_consent_ip` VARCHAR(45) DEFAULT NULL,
              `pii_redacted_at` DATETIME DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_property_dates` (`property_id`, `check_in`, `check_out`),
              INDEX `idx_status` (`status`),
              INDEX `idx_registry_completed` (`registry_completed`),
              INDEX `idx_source` (`source`),
              INDEX `idx_external_code` (`external_confirmation_code`),
              INDEX `idx_channel_block_uid` (`channel_block_uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `reservations` verified/created.";

        // Upgrade existing table columns (Self-healing columns)
        $reservationColumns = [
            'mercadopago_payment_id' => 'VARCHAR(255) DEFAULT NULL',
            'payment_status' => 'VARCHAR(50) DEFAULT NULL',
            'payment_method_id' => 'VARCHAR(50) DEFAULT NULL',
            'payment_detail' => 'TEXT DEFAULT NULL',
            'lang' => "VARCHAR(5) NOT NULL DEFAULT 'en'",
            'registry_completed' => "TINYINT(1) NOT NULL DEFAULT 0",
            'registry_completed_at' => "DATETIME DEFAULT NULL",
            'door_code' => "VARCHAR(20) DEFAULT NULL",
            'refunded_amount' => "DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'source' => "VARCHAR(30) NOT NULL DEFAULT 'web'",
            'external_confirmation_code' => "VARCHAR(64) DEFAULT NULL",
            'channel_block_uid' => "VARCHAR(128) DEFAULT NULL",
            'notes' => "TEXT DEFAULT NULL",
            'max_guests' => "TINYINT UNSIGNED NOT NULL DEFAULT 6",
            'privacy_policy_version' => 'VARCHAR(16) DEFAULT NULL',
            'privacy_consent_at' => 'DATETIME DEFAULT NULL',
            'privacy_consent_ip' => 'VARCHAR(45) DEFAULT NULL',
            'pii_redacted_at' => 'DATETIME DEFAULT NULL',
        ];

        foreach ($reservationColumns as $col => $type) {
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = :dbname 
                  AND TABLE_NAME = 'reservations' 
                  AND COLUMN_NAME = :col
            ");
            $stmt->execute(['dbname' => $dbName, 'col' => $col]);
            $exists = $stmt->fetch();

            if (!$exists) {
                $pdo->exec("ALTER TABLE `reservations` ADD COLUMN `{$col}` {$type}");
                $logs[] = "Upgrading reservations schema: Added column `{$col}`.";
            }
        }

        // Self-healing indexes for reservations
        $reservationIndexes = [
            'idx_external_code' => '(`external_confirmation_code`)',
            'idx_channel_block_uid' => '(`channel_block_uid`)',
        ];

        foreach ($reservationIndexes as $idxName => $idxCols) {
            $stmt = $pdo->prepare("
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = :dbname 
                  AND TABLE_NAME = 'reservations' 
                  AND INDEX_NAME = :idx
            ");
            $stmt->execute(['dbname' => $dbName, 'idx' => $idxName]);
            $exists = $stmt->fetch();

            if (!$exists) {
                $pdo->exec("ALTER TABLE `reservations` ADD INDEX `{$idxName}` {$idxCols}");
                $logs[] = "Upgrading reservations schema: Added index `{$idxName}`.";
            }
        }

        // 2. payment_idempotency table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `payment_idempotency` (
              `idempotency_key` VARCHAR(100) NOT NULL PRIMARY KEY,
              `payment_id` VARCHAR(100) NOT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `payment_idempotency` verified/created.";

        // 3. guest_registries table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `guest_registries` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `reservation_uid` VARCHAR(36) NOT NULL,
              `property_id` VARCHAR(10) NOT NULL,
              `check_in` DATE NOT NULL,
              `check_out` DATE NOT NULL,
              `guest_count` INT UNSIGNED NOT NULL DEFAULT 1,
              `guests_payload` JSON NOT NULL,
              `car_plates` VARCHAR(20) DEFAULT NULL,
              `car_model` VARCHAR(100) DEFAULT NULL,
              `ip_address` VARCHAR(45) DEFAULT NULL,
              `privacy_policy_version` VARCHAR(16) DEFAULT NULL,
              `privacy_consent_at` DATETIME DEFAULT NULL,
              `privacy_consent_ip` VARCHAR(45) DEFAULT NULL,
              `minor_guardian_consent` TINYINT(1) NOT NULL DEFAULT 0,
              `pii_redacted_at` DATETIME DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_reg_reservation` (`reservation_uid`),
              INDEX `idx_reg_property_dates` (`property_id`, `check_in`, `check_out`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `guest_registries` verified/created.";
        self::encryptLegacyGuestRegistries($pdo, $logs);

        $guestRegistryColumns = [
            'privacy_policy_version' => 'VARCHAR(16) DEFAULT NULL',
            'privacy_consent_at' => 'DATETIME DEFAULT NULL',
            'privacy_consent_ip' => 'VARCHAR(45) DEFAULT NULL',
            'minor_guardian_consent' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'pii_redacted_at' => 'DATETIME DEFAULT NULL',
        ];

        foreach ($guestRegistryColumns as $col => $type) {
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = :dbname 
                  AND TABLE_NAME = 'guest_registries' 
                  AND COLUMN_NAME = :col
            ");
            $stmt->execute(['dbname' => $dbName, 'col' => $col]);
            $exists = $stmt->fetch();

            if (!$exists) {
                $pdo->exec("ALTER TABLE `guest_registries` ADD COLUMN `{$col}` {$type}");
                $logs[] = "Upgrading guest_registries schema: Added column `{$col}`.";
            }
        }

        // 4. admin_users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admin_users` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `email` VARCHAR(120) NOT NULL UNIQUE,
              `password_hash` VARCHAR(255) NOT NULL,
              `name` VARCHAR(100) NOT NULL,
              `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
              `locked_until` DATETIME DEFAULT NULL,
              `last_login_at` DATETIME DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_admin_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `admin_users` verified/created.";

        // 5. admin_audit_logs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `admin_user_id` INT UNSIGNED DEFAULT NULL,
              `action` VARCHAR(50) NOT NULL,
              `entity_type` VARCHAR(30) NOT NULL,
              `entity_id` VARCHAR(50) NOT NULL,
              `payload_before` JSON DEFAULT NULL,
              `payload_after` JSON DEFAULT NULL,
              `ip_address` VARCHAR(45) NOT NULL,
              `user_agent` VARCHAR(255) DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
              INDEX `idx_audit_admin` (`admin_user_id`),
              INDEX `idx_audit_action` (`action`),
              INDEX `idx_audit_created` (`created_at`),
              CONSTRAINT `fk_audit_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `admin_audit_logs` verified/created.";

        // 6. calendar_blocks table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `calendar_blocks` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `property_id` VARCHAR(10) NOT NULL,
              `start_date` DATE NOT NULL,
              `end_date` DATE NOT NULL,
              `reason` VARCHAR(255) NOT NULL,
              `created_by` INT UNSIGNED DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_cb_property_dates` (`property_id`, `start_date`, `end_date`),
              INDEX `idx_cb_created_by` (`created_by`),
              CONSTRAINT `fk_cb_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `calendar_blocks` verified/created.";

        // 7. property_rates table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `property_rates` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `property_id` VARCHAR(10) NOT NULL,
              `start_date` DATE NOT NULL,
              `end_date` DATE NOT NULL,
              `season_name` VARCHAR(100) NOT NULL,
              `price_per_night` DECIMAL(10, 2) NOT NULL,
              `min_stay` INT UNSIGNED NOT NULL DEFAULT 2,
              `cleaning_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
              `resort_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
              `created_by` INT UNSIGNED DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_pr_property_dates` (`property_id`, `start_date`, `end_date`),
              INDEX `idx_pr_property` (`property_id`),
              CONSTRAINT `fk_pr_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `property_rates` verified/created.";

        // 8. reservation_refunds table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `reservation_refunds` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `reservation_uid` VARCHAR(36) NOT NULL,
              `mercadopago_refund_id` VARCHAR(100) DEFAULT NULL UNIQUE,
              `mercadopago_payment_id` VARCHAR(100) NOT NULL,
              `amount` DECIMAL(10, 2) NOT NULL,
              `status` VARCHAR(30) NOT NULL DEFAULT 'approved',
              `reason` VARCHAR(255) DEFAULT NULL,
              `source` VARCHAR(30) NOT NULL DEFAULT 'admin',
              `admin_user_id` INT UNSIGNED DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_ref_res_uid` (`reservation_uid`),
              INDEX `idx_ref_payment_id` (`mercadopago_payment_id`),
              INDEX `idx_ref_admin` (`admin_user_id`),
              CONSTRAINT `fk_ref_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `reservation_refunds` verified/created.";

        // 9. condominium_clearances table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `condominium_clearances` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `reservation_uid` VARCHAR(36) NOT NULL UNIQUE,
              `property_id` VARCHAR(16) NOT NULL,
              `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
              `clearance_number` VARCHAR(32) DEFAULT NULL,
              `error_message` TEXT DEFAULT NULL,
              `request_payload` TEXT DEFAULT NULL,
              `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
              `last_attempt_at` DATETIME NOT NULL,
              `synced_at` DATETIME DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_cc_reservation_uid` (`reservation_uid`),
              INDEX `idx_cc_status` (`status`),
              INDEX `idx_cc_property_id` (`property_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `condominium_clearances` verified/created.";

        // 10. operational_alert_dismissals table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `operational_alert_dismissals` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `alert_id` VARCHAR(160) NOT NULL UNIQUE,
              `alert_type` VARCHAR(64) NOT NULL,
              `property_id` VARCHAR(16) NOT NULL,
              `reservation_uid` VARCHAR(36) DEFAULT NULL,
              `dismissed_by` INT UNSIGNED DEFAULT NULL,
              `dismissed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_alert_type` (`alert_type`),
              INDEX `idx_reservation_uid` (`reservation_uid`),
              INDEX `idx_property_id` (`property_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `operational_alert_dismissals` verified/created.";

        // 11. operational_expenses table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `operational_expenses` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `expense_uid` VARCHAR(36) NOT NULL UNIQUE,
              `property_id` VARCHAR(10) NOT NULL,
              `category` VARCHAR(32) NOT NULL,
              `title` VARCHAR(160) NOT NULL,
              `amount` DECIMAL(12, 2) NOT NULL,
              `expense_date` DATE NOT NULL,
              `vendor_name` VARCHAR(120) DEFAULT NULL,
              `reference_code` VARCHAR(120) DEFAULT NULL,
              `reservation_uid` VARCHAR(36) DEFAULT NULL,
              `notes` TEXT DEFAULT NULL,
              `created_by` INT UNSIGNED DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_oe_property_date` (`property_id`, `expense_date`),
              INDEX `idx_oe_expense_date` (`expense_date`),
              INDEX `idx_oe_category` (`category`),
              INDEX `idx_oe_reservation_uid` (`reservation_uid`),
              INDEX `idx_oe_created_by` (`created_by`),
              CONSTRAINT `fk_oe_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = "Table `operational_expenses` verified/created.";
    }

    /**
     * @param PDO $pdo
     * @param list<string> $logs
     */
    private static function runSqliteMigrations(PDO $pdo, array &$logs): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                guest_name TEXT NOT NULL,
                guest_email TEXT NOT NULL,
                guest_phone TEXT NOT NULL,
                check_in TEXT NOT NULL,
                check_out TEXT NOT NULL,
                total_price NUMERIC NOT NULL,
                refunded_amount NUMERIC NOT NULL DEFAULT 0.00,
                source TEXT NOT NULL DEFAULT "web",
                external_confirmation_code TEXT DEFAULT NULL,
                channel_block_uid TEXT DEFAULT NULL,
                mercadopago_preference_id TEXT DEFAULT NULL,
                mercadopago_payment_id TEXT DEFAULT NULL,
                payment_status TEXT DEFAULT NULL,
                payment_method_id TEXT DEFAULT NULL,
                payment_detail TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT "pending_payment",
                lang TEXT NOT NULL DEFAULT "en",
                registry_completed INTEGER NOT NULL DEFAULT 0,
                registry_completed_at TEXT DEFAULT NULL,
                door_code TEXT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                max_guests INTEGER NOT NULL DEFAULT 6,
                privacy_policy_version TEXT DEFAULT NULL,
                privacy_consent_at TEXT DEFAULT NULL,
                privacy_consent_ip TEXT DEFAULT NULL,
                pii_redacted_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $logs[] = "Table `reservations` verified/created.";

        // Self-healing columns for SQLite
        $sqliteColumns = [
            'external_confirmation_code' => 'TEXT DEFAULT NULL',
            'channel_block_uid' => 'TEXT DEFAULT NULL',
            'max_guests' => 'INTEGER NOT NULL DEFAULT 6',
            'privacy_policy_version' => 'TEXT DEFAULT NULL',
            'privacy_consent_at' => 'TEXT DEFAULT NULL',
            'privacy_consent_ip' => 'TEXT DEFAULT NULL',
            'pii_redacted_at' => 'TEXT DEFAULT NULL',
        ];
        $existingCols = [];
        $colsStmt = $pdo->query("PRAGMA table_info(reservations)");
        if ($colsStmt !== false) {
            while ($colRow = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingCols[] = (string) ($colRow['name'] ?? '');
            }
        }
        foreach ($sqliteColumns as $col => $colDef) {
            if (!in_array($col, $existingCols, true)) {
                $pdo->exec("ALTER TABLE reservations ADD COLUMN `{$col}` {$colDef}");
                $logs[] = "Upgrading SQLite reservations: Added column `{$col}`.";
            }
        }

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS payment_idempotency (
                idempotency_key TEXT PRIMARY KEY,
                payment_id TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $logs[] = "Table `payment_idempotency` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS guest_registries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL,
                property_id TEXT NOT NULL,
                check_in TEXT NOT NULL,
                check_out TEXT NOT NULL,
                guest_count INTEGER NOT NULL DEFAULT 1,
                guests_payload TEXT NOT NULL,
                car_plates TEXT DEFAULT NULL,
                car_model TEXT DEFAULT NULL,
                ip_address TEXT DEFAULT NULL,
                privacy_policy_version TEXT DEFAULT NULL,
                privacy_consent_at TEXT DEFAULT NULL,
                privacy_consent_ip TEXT DEFAULT NULL,
                minor_guardian_consent INTEGER NOT NULL DEFAULT 0,
                pii_redacted_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $logs[] = "Table `guest_registries` verified/created.";
        self::encryptLegacyGuestRegistries($pdo, $logs);

        // Self-healing columns for SQLite guest_registries
        $sqliteGuestRegistryColumns = [
            'privacy_policy_version' => 'TEXT DEFAULT NULL',
            'privacy_consent_at' => 'TEXT DEFAULT NULL',
            'privacy_consent_ip' => 'TEXT DEFAULT NULL',
            'minor_guardian_consent' => 'INTEGER NOT NULL DEFAULT 0',
            'pii_redacted_at' => 'TEXT DEFAULT NULL',
        ];
        $existingRegCols = [];
        $regColsStmt = $pdo->query("PRAGMA table_info(guest_registries)");
        if ($regColsStmt !== false) {
            while ($regColRow = $regColsStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingRegCols[] = (string) ($regColRow['name'] ?? '');
            }
        }
        foreach ($sqliteGuestRegistryColumns as $col => $colDef) {
            if (!in_array($col, $existingRegCols, true)) {
                $pdo->exec("ALTER TABLE guest_registries ADD COLUMN `{$col}` {$colDef}");
                $logs[] = "Upgrading SQLite guest_registries: Added column `{$col}`.";
            }
        }

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL DEFAULT "",
                name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "admin",
                is_active INTEGER NOT NULL DEFAULT 1,
                failed_login_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT DEFAULT NULL,
                last_login_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $logs[] = "Table `admin_users` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER DEFAULT NULL,
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                payload_before TEXT DEFAULT NULL,
                payload_after TEXT DEFAULT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
            );
        ');
        $logs[] = "Table `admin_audit_logs` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS calendar_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES admin_users (id) ON DELETE SET NULL
            );
        ');
        $logs[] = "Table `calendar_blocks` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS property_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                season_name TEXT NOT NULL,
                price_per_night NUMERIC NOT NULL,
                min_stay INTEGER NOT NULL DEFAULT 2,
                cleaning_fee NUMERIC NOT NULL DEFAULT 0.00,
                resort_fee NUMERIC NOT NULL DEFAULT 0.00,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES admin_users (id) ON DELETE SET NULL
            );
        ');
        $logs[] = "Table `property_rates` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS reservation_refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL,
                mercadopago_refund_id TEXT DEFAULT NULL UNIQUE,
                mercadopago_payment_id TEXT NOT NULL,
                amount NUMERIC NOT NULL,
                status TEXT NOT NULL DEFAULT "approved",
                reason TEXT DEFAULT NULL,
                source TEXT NOT NULL DEFAULT "admin",
                admin_user_id INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
            );
        ');
        $logs[] = "Table `reservation_refunds` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS condominium_clearances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                clearance_number TEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                request_payload TEXT DEFAULT NULL,
                attempts INTEGER NOT NULL DEFAULT 1,
                last_attempt_at TEXT NOT NULL,
                synced_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cc_status ON condominium_clearances (status);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cc_property_id ON condominium_clearances (property_id);');
        $logs[] = "Table `condominium_clearances` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS operational_alert_dismissals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                alert_id TEXT NOT NULL UNIQUE,
                alert_type TEXT NOT NULL,
                property_id TEXT NOT NULL,
                reservation_uid TEXT DEFAULT NULL,
                dismissed_by INTEGER DEFAULT NULL,
                dismissed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_alert_type ON operational_alert_dismissals (alert_type);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reservation_uid ON operational_alert_dismissals (reservation_uid);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_property_id ON operational_alert_dismissals (property_id);');
        $logs[] = "Table `operational_alert_dismissals` verified/created.";

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS operational_expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                expense_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                category TEXT NOT NULL,
                title TEXT NOT NULL,
                amount NUMERIC NOT NULL,
                expense_date TEXT NOT NULL,
                vendor_name TEXT DEFAULT NULL,
                reference_code TEXT DEFAULT NULL,
                reservation_uid TEXT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES admin_users (id) ON DELETE SET NULL
            );
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oe_property_date ON operational_expenses (property_id, expense_date);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oe_expense_date ON operational_expenses (expense_date);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oe_category ON operational_expenses (category);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oe_reservation_uid ON operational_expenses (reservation_uid);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oe_created_by ON operational_expenses (created_by);');
        $logs[] = "Table `operational_expenses` verified/created.";
    }

    /**
     * Encrypts any existing plaintext guest_registries.guests_payload rows in-place (ADR 0012).
     *
     * @param PDO $pdo
     * @param list<string> $logs
     */
    private static function encryptLegacyGuestRegistries(PDO $pdo, array &$logs): void
    {
        $stmt = $pdo->query('SELECT id, guests_payload FROM guest_registries');
        if ($stmt === false) {
            return;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $encryptedCount = 0;

        $updateStmt = $pdo->prepare('UPDATE guest_registries SET guests_payload = :payload WHERE id = :id');

        foreach ($rows as $row) {
            $rawPayload = (string) ($row['guests_payload'] ?? '');
            $trimmed = trim($rawPayload);
            if ($trimmed === '' || $trimmed === '[]') {
                continue;
            }

            if (PayloadEncryptor::isEncryptedEnvelope($trimmed)) {
                continue;
            }

            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded) || $decoded === []) {
                continue;
            }

            $encrypted = PayloadEncryptor::encrypt($decoded);
            $updateStmt->execute([
                ':payload' => $encrypted,
                ':id' => (int) $row['id'],
            ]);
            $encryptedCount++;
        }

        if ($encryptedCount > 0) {
            $logs[] = "Encrypted {$encryptedCount} legacy guest_registries row(s).";
        }
    }
}
