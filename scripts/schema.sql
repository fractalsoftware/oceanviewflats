-- =============================================================================
-- OceanViewFlats Database Setup and Migration Schema
-- 
-- This script initializes the database and table structure for direct booking
-- and PMS administrative operations.
-- Execute this on your remote MySQL server to complete manual setup or upgrades.
-- =============================================================================

-- 1. Create Database
CREATE DATABASE IF NOT EXISTS `oceanviewflats_db` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `oceanviewflats_db`;

-- 2. Create Reservations Log Table
-- Declares InnoDB engine to support row-level locking on date ranges
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
  -- Checkout Pro preference tracking
  `mercadopago_preference_id` VARCHAR(255) DEFAULT NULL,
  -- Checkout Bricks (Custom API) payment tracking
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Optimized indexes for calendar availability checks and status updates
  INDEX `idx_property_dates` (`property_id`, `check_in`, `check_out`),
  INDEX `idx_status` (`status`),
  INDEX `idx_registry_completed` (`registry_completed`),
  INDEX `idx_source` (`source`),
  INDEX `idx_external_code` (`external_confirmation_code`),
  INDEX `idx_channel_block_uid` (`channel_block_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Payment Idempotency Log Table
-- Double-charge prevention for API integrations
CREATE TABLE IF NOT EXISTS `payment_idempotency` (
  `idempotency_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `payment_id` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Guest Registries Log Table (ADR 0001 Compliance)
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reg_reservation` (`reservation_uid`),
  INDEX `idx_reg_property_dates` (`property_id`, `check_in`, `check_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Admin Users Table (ADR 0005 Compliance)
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

-- 6. Create Admin Audit Logs Table (ADR 0005 Compliance)
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

-- 7. Create Calendar Blocks Table (Maintenance & Unavailability Holds)
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

-- 8. Create Property Rates Table (Database-Backed Pricing Engine)
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

-- 9. Create Reservation Refunds Table (Mercado Pago Gateway Reconciliation)
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

-- 10. Create Condominium Clearances Table (ADR 0008 Compliance)
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

-- =============================================================================
-- Verification Query: SHOW TABLES;
-- =============================================================================
