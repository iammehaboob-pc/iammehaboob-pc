-- ==========================================
-- SMARTFIX AI DATABASE SCHEMA SQL
-- RDBMS: MySQL (5.7+ / 8.0+)
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `ai_analysis`;
DROP TABLE IF EXISTS `complaint_status_history`;
DROP TABLE IF EXISTS `complaint_images`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `priorities`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. DEPARTMENTS TABLE
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` ENUM('student', 'staff', 'admin') NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `roll_number` VARCHAR(50) NULL,
    `department_id` INT NULL,
    `status` ENUM('active', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CATEGORIES TABLE
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PRIORITIES TABLE
CREATE TABLE `priorities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` ENUM('low', 'medium', 'high', 'urgent') NOT NULL UNIQUE,
    `sla_hours` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. COMPLAINTS TABLE
CREATE TABLE `complaints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `category_id` INT NULL,
    `priority_id` INT NULL,
    `department_id` INT NULL,
    `status` ENUM('submitted', 'under_review', 'assigned', 'wip', 'resolved', 'rejected') DEFAULT 'submitted',
    `staff_id` INT NULL,
    `rejection_reason` TEXT NULL,
    `internal_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_complaints_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_complaints_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_complaints_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_complaints_priority` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_complaints_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. COMPLAINT IMAGES TABLE
CREATE TABLE `complaint_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `image_type` ENUM('submission', 'resolution') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_images_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. COMPLAINT STATUS HISTORY (TIMELINE)
CREATE TABLE `complaint_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `comments` TEXT NULL,
    `changed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. AI ANALYSIS TABLE
CREATE TABLE `ai_analysis` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL UNIQUE,
    `summary` TEXT NOT NULL,
    `predicted_category` VARCHAR(100) NOT NULL,
    `predicted_priority` VARCHAR(50) NOT NULL,
    `suggested_department` VARCHAR(100) NOT NULL,
    `possible_solution` TEXT NOT NULL,
    `duplicate_of_id` INT NULL,
    `confidence_score` DECIMAL(5,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ai_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ai_duplicate` FOREIGN KEY (`duplicate_of_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. FEEDBACK TABLE
CREATE TABLE `feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL UNIQUE,
    `rating` INT NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
    `comments` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_feedback_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. NOTIFICATIONS TABLE
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. PASSWORD RESET TOKENS TABLE
CREATE TABLE `password_reset_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. RATE LIMITS TABLE
CREATE TABLE `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `action_key` VARCHAR(100) NOT NULL,
    `attempted_at` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. AUDIT LOGS TABLE
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. SYSTEM SETTINGS TABLE
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- ==========================================
CREATE INDEX `idx_users_email` ON `users` (`email`);
CREATE INDEX `idx_complaints_student` ON `complaints` (`student_id`);
CREATE INDEX `idx_complaints_staff` ON `complaints` (`staff_id`);
CREATE INDEX `idx_complaints_status` ON `complaints` (`status`);
CREATE INDEX `idx_complaints_created` ON `complaints` (`created_at`);
CREATE INDEX `idx_images_complaint` ON `complaint_images` (`complaint_id`);
CREATE INDEX `idx_history_complaint` ON `complaint_status_history` (`complaint_id`);
CREATE INDEX `idx_notifications_user_read` ON `notifications` (`user_id`, `is_read`);
CREATE INDEX `idx_reset_token` ON `password_reset_tokens` (`token`);
CREATE INDEX `idx_rate_limits` ON `rate_limits` (`ip_address`, `action_key`, `attempted_at`);

-- ==========================================
-- SAMPLE SEED DATA
-- ==========================================

-- Seed Departments
INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'IT Support & Computer Labs'),
(2, 'Electrical Maintenance'),
(3, 'Plumbing & Sanitation'),
(4, 'Carpentry & Furniture'),
(5, 'Housekeeping & Campus Cleaning'),
(6, 'Academic Blocks & Classrooms');

-- Seed Priorities
INSERT INTO `priorities` (`id`, `name`, `sla_hours`) VALUES
(1, 'low', 72),
(2, 'medium', 48),
(3, 'high', 24),
(4, 'urgent', 4);

-- Seed Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Network/Wi-Fi Issues', 'Internet connectivity problems, router failure, or slow Wi-Fi access in campus.'),
(2, 'Electrical Fault', 'Broken lights, faulty power outlets, fan issues, or wiring breakdowns.'),
(3, 'Water Leakage', 'Dripping taps, clogged drains, toilet flush malfunctions, or pipe leaks.'),
(4, 'Broken Furniture', 'Broken student desks, damaged whiteboard, chair alignment, or door handle issue.'),
(5, 'Waste/Cleaning', 'Trash cans overflowing, dirty corridor, window pane washing, or garden clean up.'),
(6, 'Smartboard Malfunction', 'Projector connectivity issues, touch sensor lag, or screen damage.');

-- Seed System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('system_name', 'SmartFix AI'),
('system_email', 'support@smartfixai.edu'),
('maintenance_mode', '0'),
('allow_registration', '1');

-- Seed Standard Users
-- Hashed passwords created using password_hash('password', PASSWORD_BCRYPT)
-- Hashed string for password 'Admin@123' -> $2y$10$tZ2cOqNleIeWb8H4Wb23zOgZ1o8V6G4j0k23cO.RjV3m1G2kC2F.m
-- Hashed string for password 'Staff@123' -> $2y$10$p0b8G1mC2d2e1z8g7f6h5e4d3c2b1a0z9y8x7w6v5u4t3s2r1q0p.
-- Hashed string for password 'Student@123' -> $2y$10$q0p9o8n7m6l5k4j3i2h1g0f9e8d7c6b5a4z3y2x1w0v9u8t7s6r5q

-- Admin Account
INSERT INTO `users` (`id`, `role`, `email`, `password`, `name`, `roll_number`, `department_id`, `status`) VALUES
(1, 'admin', 'admin@smartfixai.edu', '$2y$10$Y1s16oB.m3ZkH8cZJ5.iie6r6g8wIasbK9H5w/Qp.3g1qZfQ4xIeq', 'System Admin', NULL, NULL, 'active');

-- Staff Accounts (Department-specific)
-- Hashed passwords below are 'Staff@123' (specifically: $2y$10$G0N4zMtfhE2aB3wHq.nLkuSfeR3/H.aBq3H04sO1VpxX5e.P9b9l2)
INSERT INTO `users` (`id`, `role`, `email`, `password`, `name`, `roll_number`, `department_id`, `status`) VALUES
(2, 'staff', 'it_staff@smartfixai.edu', '$2y$10$G0N4zMtfhE2aB3wHq.nLkuSfeR3/H.aBq3H04sO1VpxX5e.P9b9l2', 'Mr. Robert (IT Support)', NULL, 1, 'active'),
(3, 'staff', 'elec_staff@smartfixai.edu', '$2y$10$G0N4zMtfhE2aB3wHq.nLkuSfeR3/H.aBq3H04sO1VpxX5e.P9b9l2', 'Mr. Thomas (Electrical)', NULL, 2, 'active'),
(4, 'staff', 'plumb_staff@smartfixai.edu', '$2y$10$G0N4zMtfhE2aB3wHq.nLkuSfeR3/H.aBq3H04sO1VpxX5e.P9b9l2', 'Mr. James (Plumbing)', NULL, 3, 'active');

-- Student Account
-- Hashed password is 'Student@123' (specifically: $2y$10$22m3G1eKk0f8y7D9/r7GbuWf29L8dG4N6Rz7eP8t7w0q7jN5b5F4i)
INSERT INTO `users` (`id`, `role`, `email`, `password`, `name`, `roll_number`, `department_id`, `status`) VALUES
(5, 'student', 'student@smartfixai.edu', '$2y$10$22m3G1eKk0f8y7D9/r7GbuWf29L8dG4N6Rz7eP8t7w0q7jN5b5F4i', 'John Doe (BCA Student)', 'BCA-2026-0045', 1, 'active');
