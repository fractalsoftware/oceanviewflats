<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Database;

use OceanViewFlats\Domain\Database\MigrationRunner;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    public function testRunsMigrationsAgainstSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $logs = MigrationRunner::run($pdo, 'memory_db');

        $this->assertNotEmpty($logs);
        $this->assertContains('Table `reservations` verified/created.', $logs);
        $this->assertContains('Table `admin_users` verified/created.', $logs);
        $this->assertContains('Table `calendar_blocks` verified/created.', $logs);
        $this->assertContains('Table `condominium_clearances` verified/created.', $logs);
        $this->assertContains('Table `operational_alert_dismissals` verified/created.', $logs);

        // Verify tables exist in SQLite master
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $this->assertContains('reservations', $tables);
        $this->assertContains('payment_idempotency', $tables);
        $this->assertContains('guest_registries', $tables);
        $this->assertContains('admin_users', $tables);
        $this->assertContains('admin_audit_logs', $tables);
        $this->assertContains('calendar_blocks', $tables);
        $this->assertContains('property_rates', $tables);
        $this->assertContains('reservation_refunds', $tables);
        $this->assertContains('condominium_clearances', $tables);
        $this->assertContains('operational_alert_dismissals', $tables);

        // Verify columns in operational_alert_dismissals table
        $oadColsStmt = $pdo->query("PRAGMA table_info(operational_alert_dismissals)");
        $oadCols = $oadColsStmt !== false ? array_column($oadColsStmt->fetchAll(PDO::FETCH_ASSOC), 'name') : [];
        $this->assertContains('alert_id', $oadCols);
        $this->assertContains('alert_type', $oadCols);
        $this->assertContains('property_id', $oadCols);
        $this->assertContains('reservation_uid', $oadCols);
        $this->assertContains('dismissed_by', $oadCols);
        $this->assertContains('dismissed_at', $oadCols);

        // Verify columns in condominium_clearances table
        $ccColsStmt = $pdo->query("PRAGMA table_info(condominium_clearances)");
        $ccCols = $ccColsStmt !== false ? array_column($ccColsStmt->fetchAll(PDO::FETCH_ASSOC), 'name') : [];
        $this->assertContains('reservation_uid', $ccCols);
        $this->assertContains('property_id', $ccCols);
        $this->assertContains('status', $ccCols);
        $this->assertContains('clearance_number', $ccCols);
        $this->assertContains('error_message', $ccCols);
        $this->assertContains('request_payload', $ccCols);
        $this->assertContains('attempts', $ccCols);
        $this->assertContains('last_attempt_at', $ccCols);
        $this->assertContains('synced_at', $ccCols);

        // Verify newly added columns in reservations table
        $colsStmt = $pdo->query("PRAGMA table_info(reservations)");
        $cols = $colsStmt !== false ? array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name') : [];
        $this->assertContains('external_confirmation_code', $cols);
        $this->assertContains('channel_block_uid', $cols);
        $this->assertContains('max_guests', $cols);
    }

    public function testMigrationsAreIdempotent(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $firstRunLogs = MigrationRunner::run($pdo, 'memory_db');
        $this->assertNotEmpty($firstRunLogs);

        // Second run must succeed with zero errors or schema conflicts
        $secondRunLogs = MigrationRunner::run($pdo, 'memory_db');
        $this->assertSame($firstRunLogs, $secondRunLogs);
    }

    public function testSelfHealsMissingColumnsOnPreexistingReservationsTable(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Pre-create reservations table without external_confirmation_code and channel_block_uid
        $pdo->exec('
            CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                guest_name TEXT NOT NULL,
                guest_email TEXT NOT NULL,
                guest_phone TEXT NOT NULL,
                check_in TEXT NOT NULL,
                check_out TEXT NOT NULL,
                total_price NUMERIC NOT NULL,
                status TEXT NOT NULL DEFAULT "pending_payment"
            );
        ');

        $logs = MigrationRunner::run($pdo, 'memory_db');

        $this->assertContains('Upgrading SQLite reservations: Added column `external_confirmation_code`.', $logs);
        $this->assertContains('Upgrading SQLite reservations: Added column `channel_block_uid`.', $logs);
        $this->assertContains('Upgrading SQLite reservations: Added column `max_guests`.', $logs);

        $colsStmt = $pdo->query("PRAGMA table_info(reservations)");
        $cols = $colsStmt !== false ? array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name') : [];
        $this->assertContains('external_confirmation_code', $cols);
        $this->assertContains('channel_block_uid', $cols);
        $this->assertContains('max_guests', $cols);
    }

    public function testConnectThrowsExpectedPdoExceptionForInvalidHost(): void
    {
        $this->expectException(PDOException::class);

        MigrationRunner::connect([
            'host' => '127.0.0.1',
            'port' => 65432, // Non-existent MySQL port
            'dbname' => 'nonexistent_db',
            'user' => 'bad_user',
            'pass' => 'bad_pass',
        ]);
    }
}
