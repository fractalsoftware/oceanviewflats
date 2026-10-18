<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Support;

use PDO;

final class AdminDatabaseTestHelper
{
    /**
     * Creates an in-memory SQLite database initialized with all authoritative tables
     * matching scripts/schema.sql.
     */
    public static function createDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::initializeSchema($pdo);

        return $pdo;
    }

    /**
     * Creates an in-memory SQLite database initialized with all authoritative tables
     * and seeds a default admin user (id: 1, name: 'Manuel Admin', email: 'admin@oceanviewflats.com').
     */
    public static function createDatabaseWithDefaultAdmin(): PDO
    {
        $pdo = self::createDatabase();
        self::seedDefaultAdmin($pdo);

        return $pdo;
    }

    public static function initializeSchema(PDO $pdo): void
    {
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

            CREATE TABLE IF NOT EXISTS admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER DEFAULT NULL,
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                payload_before TEXT DEFAULT NULL,
                payload_after TEXT DEFAULT NULL,
                ip_address TEXT NOT NULL DEFAULT "",
                user_agent TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS reservation_refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL,
                mercadopago_refund_id TEXT DEFAULT NULL UNIQUE,
                mercadopago_payment_id TEXT NOT NULL DEFAULT "offline",
                amount NUMERIC NOT NULL,
                status TEXT NOT NULL DEFAULT "approved",
                reason TEXT DEFAULT NULL,
                source TEXT NOT NULL DEFAULT "admin_pms",
                admin_user_id INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS property_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                season_name TEXT NOT NULL,
                price_per_night REAL NOT NULL,
                min_stay INTEGER NOT NULL DEFAULT 2,
                cleaning_fee REAL NOT NULL DEFAULT 0.0,
                resort_fee REAL NOT NULL DEFAULT 0.0,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS calendar_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS payment_idempotency (
                idempotency_key TEXT PRIMARY KEY,
                payment_id TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS condominium_clearances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT UNIQUE NOT NULL,
                property_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                clearance_number TEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                request_payload TEXT DEFAULT NULL,
                attempts INTEGER DEFAULT 0,
                last_attempt_at TEXT DEFAULT NULL,
                synced_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS operational_alert_dismissals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                alert_id TEXT NOT NULL UNIQUE,
                alert_type TEXT NOT NULL,
                property_id TEXT NOT NULL,
                reservation_uid TEXT DEFAULT NULL,
                dismissed_by INTEGER DEFAULT NULL,
                dismissed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

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
            CREATE INDEX IF NOT EXISTS idx_oe_property_date ON operational_expenses (property_id, expense_date);
            CREATE INDEX IF NOT EXISTS idx_oe_expense_date ON operational_expenses (expense_date);
            CREATE INDEX IF NOT EXISTS idx_oe_category ON operational_expenses (category);
            CREATE INDEX IF NOT EXISTS idx_oe_reservation_uid ON operational_expenses (reservation_uid);
            CREATE INDEX IF NOT EXISTS idx_oe_created_by ON operational_expenses (created_by);
        ');
    }

    public static function seedDefaultAdmin(PDO $pdo): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO admin_users (id, email, password_hash, name, role, is_active)
            VALUES (1, :email, :hash, :name, :role, 1)
            ON CONFLICT(id) DO UPDATE SET email = :email, name = :name
        ');
        $stmt->execute([
            ':email' => 'admin@oceanviewflats.com',
            ':hash' => password_hash('secret123', PASSWORD_DEFAULT),
            ':name' => 'Manuel Admin',
            ':role' => 'admin',
        ]);
    }
}
