-- ============================================================
--  MUSCLE WORKSHOP GAURADAHA - Normalized Database Schema
--  Unified members/bookings structure for data consistency.
--
--  Design principle:
--    The `members` table is the single "Registry" of active
--    athletes. The `bookings` table mirrors the member columns
--    1:1 (plus a status flag) so that admin approval is a clean,
--    transactional move: SELECT from bookings -> INSERT into
--    members -> DELETE from bookings. Column names match exactly,
--    which eliminates the data-mapping errors that occurred when
--    the public "Join" form and the admin manual-entry form used
--    different field names (client_name vs full_name, etc.).
--
--  Due amount is NEVER stored as a static column. It is always
--    calculated on the fly:
--       $due_amount = $row['package_cost'] - $row['amount_paid'];
--    This keeps payment status correct even after partial payments.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table: trainers
-- Staff / coaches that can be assigned to a member.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trainers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `role` VARCHAR(60) NOT NULL DEFAULT 'Trainer',
  `specialization` VARCHAR(160) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: members  (THE REGISTRY)
-- Single normalized structure for every approved athlete.
-- Required columns: id, full_name, address, phone_no, shift,
--   starting_date, subscription_months, package_cost,
--   amount_paid, trainer_id, created_at
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(160) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone_no` VARCHAR(40) NOT NULL,
  `shift` ENUM('Morning','Evening') NOT NULL DEFAULT 'Morning',
  `starting_date` DATE NOT NULL,
  `subscription_months` INT UNSIGNED NOT NULL DEFAULT 1,
  `package_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `trainer_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_members_shift` (`shift`),
  KEY `idx_members_trainer` (`trainer_id`),
  CONSTRAINT `fk_members_trainer`
    FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: bookings  (PENDING APPLICATIONS)
-- Mirrors the members columns exactly (minus id/created_at,
-- plus status) so approval is a direct column-to-column copy.
-- The public "Join" page writes here; admin approval moves a
-- row from here into `members` and deletes it from here.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(160) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone_no` VARCHAR(40) NOT NULL,
  `shift` ENUM('Morning','Evening') NOT NULL DEFAULT 'Morning',
  `starting_date` DATE NOT NULL,
  `subscription_months` INT UNSIGNED NOT NULL DEFAULT 1,
  `package_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `trainer_id` INT DEFAULT NULL,
  `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_bookings_status` (`status`),
  KEY `idx_bookings_trainer` (`trainer_id`),
  CONSTRAINT `fk_bookings_trainer`
    FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: payments
-- Append-only ledger of every payment credited to a member.
-- amount_paid on the member row is the running total of these.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_payments_member` (`member_id`),
  CONSTRAINT `fk_payments_member`
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: admins
-- Lightweight admin credentials for the dashboard gate.
-- (password stored in plain text only to preserve the existing
--  stateless auth model; migrate to password_hash() later.)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default admin account (change the password after first login).
INSERT INTO `admins` (`username`, `password`) VALUES ('admin', 'admin123')
  ON DUPLICATE KEY UPDATE `username` = `username`;

-- --------------------------------------------------------
-- Table: gym_events
-- Public announcements / campaign banners shown on the home page.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gym_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
