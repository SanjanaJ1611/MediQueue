-- ===================================================
-- MediQueue - Hospital Appointment & Virtual Queue System
-- Database Schema & Realistic Seed Data
-- ===================================================

CREATE DATABASE IF NOT EXISTS `mediqueue` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mediqueue`;

-- Disable foreign key checks during drop & recreate
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `visitors`;
DROP TABLE IF EXISTS `queues`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `doctor_availability`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------
-- 1. Table: users
-- ---------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
  `phone` VARCHAR(30) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 2. Table: departments
-- ---------------------------------------------------
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-stethoscope',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 3. Table: doctors
-- ---------------------------------------------------
CREATE TABLE `doctors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `specialization` VARCHAR(150) NOT NULL,
  `qualification` VARCHAR(150) NOT NULL,
  `experience_years` INT DEFAULT 5,
  `room_number` VARCHAR(50) DEFAULT 'Room 101',
  `consultation_fee` DECIMAL(10,2) DEFAULT 50.00,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doctors_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 4. Table: patients
-- ---------------------------------------------------
CREATE TABLE `patients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `date_of_birth` DATE NULL,
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
  `blood_group` VARCHAR(10) DEFAULT 'O+',
  `address` TEXT NULL,
  `emergency_contact` VARCHAR(30) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 5. Table: doctor_availability
-- ---------------------------------------------------
CREATE TABLE `doctor_availability` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `day_of_week` VARCHAR(20) DEFAULT 'All Days',
  `available_date` DATE NULL,
  `start_time` TIME NOT NULL DEFAULT '09:00:00',
  `end_time` TIME NOT NULL DEFAULT '17:00:00',
  `slot_duration` INT DEFAULT 30,
  `status` ENUM('available', 'busy', 'unavailable') DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_avail_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 6. Table: appointments
-- ---------------------------------------------------
CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `appointment_number` VARCHAR(50) NOT NULL UNIQUE,
  `status` ENUM('pending', 'confirmed', 'in_queue', 'completed', 'cancelled', 'no_show') DEFAULT 'confirmed',
  `reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_appoint_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appoint_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  INDEX `idx_doc_date_time` (`doctor_id`, `appointment_date`, `appointment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 7. Table: queues
-- ---------------------------------------------------
CREATE TABLE `queues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `queue_number` VARCHAR(50) NOT NULL,
  `queue_position` INT NOT NULL DEFAULT 1,
  `estimated_waiting_time` INT NOT NULL DEFAULT 15,
  `status` ENUM('waiting', 'called', 'in_consultation', 'completed', 'cancelled', 'no_show') DEFAULT 'waiting',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `called_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `fk_queue_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_queue_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_queue_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  INDEX `idx_queue_doc_status` (`doctor_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 8. Table: visitors
-- ---------------------------------------------------
CREATE TABLE `visitors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `visitor_name` VARCHAR(150) NOT NULL,
  `relationship` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `visit_date` DATE NOT NULL,
  `purpose` TEXT NULL,
  `entry_time` TIME NULL,
  `exit_time` TIME NULL,
  `status` ENUM('expected', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'expected',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_visitors_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 9. Table: notifications
-- ---------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEED DATA INSERTION
-- ===================================================

-- 1. Departments
INSERT INTO `departments` (`id`, `department_name`, `description`, `icon`) VALUES
(1, 'Cardiology', 'Heart health, cardiovascular diseases, hypertension, and preventive cardiac care.', 'fa-heart-pulse'),
(2, 'General Medicine', 'Primary care, diagnosis of common ailments, fever, routine medical wellness.', 'fa-stethoscope'),
(3, 'Dermatology', 'Skin, hair, nail disorders, cosmetic treatments, and allergy evaluations.', 'fa-hand-dots'),
(4, 'Orthopedics', 'Musculoskeletal system, bone fractures, arthritis, and joint replacements.', 'fa-bone'),
(5, 'Pediatrics', 'Comprehensive infant, child, and adolescent healthcare and immunizations.', 'fa-baby'),
(6, 'Neurology', 'Brain, spinal cord, nerve disorders, migraine, and neurological therapy.', 'fa-brain');

-- 2. Users
-- Default passwords:
-- admin@mediqueue.com    -> admin123
-- doctor@mediqueue.com   -> doctor123
-- patient@mediqueue.com  -> patient123
-- Hash: $2y$10$QO0h9XyR7Q2k8bE0N6U9Oed8V.gB4i2mJ8j0D0aX1v9q3z6k8t5yW
-- In auth.php, fallback plain passwords ('admin123', 'doctor123', 'patient123') will automatically hash on first login.
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `status`) VALUES
-- Admin / Staff
(1, 'System Administrator', 'admin@mediqueue.com', '$2y$12$hdTW0vxb7Yw.KDA/Y3WFWuoLe03F2X0Bl8XVN8H04NKNi6PIZB0N6', 'admin', '+1 (555) 019-2831', 'active'),
(2, 'Front Desk Staff - Michael', 'staff@mediqueue.com', '$2y$12$hdTW0vxb7Yw.KDA/Y3WFWuoLe03F2X0Bl8XVN8H04NKNi6PIZB0N6', 'admin', '+1 (555) 019-2832', 'active'),

-- Doctors
(3, 'Dr. Sarah Jenkins', 'doctor@mediqueue.com', '$2y$12$0yLH.Iv3ptA1CMe3ioI0SOBmQ0ycf2J2nVOlXSLarH1uvPlvGTBMO', 'doctor', '+1 (555) 201-8841', 'active'),
(4, 'Dr. Marcus Vance', 'marcus.vance@mediqueue.com', '$2y$12$0yLH.Iv3ptA1CMe3ioI0SOBmQ0ycf2J2nVOlXSLarH1uvPlvGTBMO', 'doctor', '+1 (555) 201-8842', 'active'),
(5, 'Dr. Elena Rostova', 'elena.rostova@mediqueue.com', '$2y$12$0yLH.Iv3ptA1CMe3ioI0SOBmQ0ycf2J2nVOlXSLarH1uvPlvGTBMO', 'doctor', '+1 (555) 201-8843', 'active'),
(6, 'Dr. David Chen', 'david.chen@mediqueue.com', '$2y$12$0yLH.Iv3ptA1CMe3ioI0SOBmQ0ycf2J2nVOlXSLarH1uvPlvGTBMO', 'doctor', '+1 (555) 201-8844', 'active'),
(7, 'Dr. Priya Sharma', 'priya.sharma@mediqueue.com', '$2y$12$0yLH.Iv3ptA1CMe3ioI0SOBmQ0ycf2J2nVOlXSLarH1uvPlvGTBMO', 'doctor', '+1 (555) 201-8845', 'active'),
(8, 'Dr. Arthur Mitchell', 'arthur.mitchell@mediqueue.com', '$2y$12$0yLH.Iv3ptA1CMe3ioI0SOBmQ0ycf2J2nVOlXSLarH1uvPlvGTBMO', 'doctor', '+1 (555) 201-8846', 'active'),

-- Patients
(9, 'John Doe', 'patient@mediqueue.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9901', 'active'),
(10, 'Emily Watson', 'emily.watson@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9902', 'active'),
(11, 'Robert Miller', 'robert.miller@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9903', 'active'),
(12, 'Sophia Martinez', 'sophia.m@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9904', 'active'),
(13, 'James Wilson', 'jwilson@yahoo.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9905', 'active'),
(14, 'Olivia Taylor', 'olivia.t@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9906', 'active'),
(15, 'William Anderson', 'wanderson@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9907', 'active'),
(16, 'Ava Thomas', 'ava.thomas@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9908', 'active'),
(17, 'Liam Jackson', 'liam.j@yahoo.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9909', 'active'),
(18, 'Mia White', 'mia.white@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9910', 'active'),
(19, 'Noah Harris', 'noah.harris@gmail.com', '$2y$12$1TDWZ77VwH8oHwaS8p9oEOfUmhoDL0Xp9n8dCrb8nN3WUG1c9kVCi', 'patient', '+1 (555) 782-9911', 'active');

-- 3. Doctors Table Data
INSERT INTO `doctors` (`id`, `user_id`, `department_id`, `specialization`, `qualification`, `experience_years`, `room_number`, `consultation_fee`, `status`) VALUES
(1, 3, 1, 'Interventional Cardiology & Hypertension', 'MD, FACC, Harvard Medical', 14, 'Suite 201-A', 85.00, 'active'),
(2, 4, 2, 'Primary Care & Chronic Disease Management', 'MBBS, MD (Internal Med), Johns Hopkins', 10, 'Suite 105', 45.00, 'active'),
(3, 5, 3, 'Clinical & Cosmetic Dermatology', 'MD, FAAD, Stanford School of Med', 8, 'Suite 310', 70.00, 'active'),
(4, 6, 4, 'Orthopedic Surgery & Sports Rehabilitation', 'MS (Ortho), FRCS, Oxford Univ', 16, 'Suite 214-B', 95.00, 'active'),
(5, 7, 5, 'General Pediatrics & Neonatal Care', 'MD (Pediatrics), DCH, London', 9, 'Suite 102-C', 50.00, 'active'),
(6, 8, 6, 'Neurology & Stroke Rehabilitation', 'DM (Neurology), Mayo Clinic Fellow', 12, 'Suite 405', 110.00, 'active');

-- 4. Patients Table Data
INSERT INTO `patients` (`id`, `user_id`, `date_of_birth`, `gender`, `blood_group`, `address`, `emergency_contact`) VALUES
(1, 9, '1988-04-12', 'Male', 'O+', '742 Evergreen Terrace, Springfield', '+1 (555) 782-9999'),
(2, 10, '1992-08-25', 'Female', 'A+', '128 Elm Street, Boston, MA', '+1 (555) 782-9998'),
(3, 11, '1975-11-03', 'Male', 'B+', '404 Beacon St, Brookline, MA', '+1 (555) 782-9997'),
(4, 12, '1995-01-19', 'Female', 'AB+', '88 Pine Street, Cambridge, MA', '+1 (555) 782-9996'),
(5, 13, '1982-06-30', 'Male', 'O-', '220 Oak Ave, Somerville, MA', '+1 (555) 782-9995'),
(6, 14, '2000-09-14', 'Female', 'A-', '55 Maple Blvd, Newton, MA', '+1 (555) 782-9994'),
(7, 15, '1968-12-05', 'Male', 'B-', '12 Harbor Road, Quincy, MA', '+1 (555) 782-9993'),
(8, 16, '2004-03-22', 'Female', 'O+', '310 River Way, Boston, MA', '+1 (555) 782-9992'),
(9, 17, '1990-07-18', 'Male', 'A+', '92 Central Street, Waltham, MA', '+1 (555) 782-9991'),
(10, 18, '2018-05-10', 'Female', 'B+', '17 Highland Ave, Medford, MA', '+1 (555) 782-9990'),
(11, 19, '1985-02-14', 'Male', 'AB-', '64 Washington St, Brookline, MA', '+1 (555) 782-9989');

-- 5. Doctor Availability
INSERT INTO `doctor_availability` (`id`, `doctor_id`, `day_of_week`, `available_date`, `start_time`, `end_time`, `slot_duration`, `status`) VALUES
(1, 1, 'All Days', NULL, '09:00:00', '17:00:00', 30, 'available'),
(2, 2, 'All Days', NULL, '08:30:00', '16:30:00', 20, 'available'),
(3, 3, 'All Days', NULL, '10:00:00', '18:00:00', 30, 'busy'),
(4, 4, 'All Days', NULL, '09:00:00', '15:00:00', 30, 'available'),
(5, 5, 'All Days', NULL, '08:00:00', '14:00:00', 20, 'available'),
(6, 6, 'All Days', NULL, '11:00:00', '19:00:00', 40, 'unavailable');

-- 6. Appointments
INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `appointment_number`, `status`, `reason`) VALUES
-- Today's appointments (active queue demonstration)
(1, 1, 1, CURDATE(), '09:00:00', 'MQ-2026-1001', 'in_queue', 'Routine heart checkup, palpitations monitoring'),
(2, 2, 1, CURDATE(), '09:30:00', 'MQ-2026-1002', 'in_queue', 'Blood pressure fluctuation review'),
(3, 3, 1, CURDATE(), '10:00:00', 'MQ-2026-1003', 'in_queue', 'Post-stent 6-month evaluation'),
(4, 4, 1, CURDATE(), '10:30:00', 'MQ-2026-1004', 'in_queue', 'ECG evaluation and chest tightness check'),
(5, 5, 2, CURDATE(), '09:00:00', 'MQ-2026-1005', 'in_queue', 'Severe seasonal allergy and persistent cough'),
(6, 6, 2, CURDATE(), '09:20:00', 'MQ-2026-1006', 'in_queue', 'Type 2 Diabetes routine blood glucose check'),
(7, 7, 3, CURDATE(), '10:30:00', 'MQ-2026-1007', 'confirmed', 'Rash treatment & prescription renewal'),
(8, 8, 4, CURDATE(), '11:00:00', 'MQ-2026-1008', 'confirmed', 'Right knee pain after sports workout'),
(9, 10, 5, CURDATE(), '09:30:00', 'MQ-2026-1009', 'completed', 'Routine pediatric developmental milestone review'),
(10, 11, 2, CURDATE(), '10:00:00', 'MQ-2026-1010', 'completed', 'Annual general physical checkup'),

-- Future Appointments
(11, 1, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', 'MQ-2026-1011', 'confirmed', 'Skin check for suspicious mole'),
(12, 2, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 'MQ-2026-1012', 'confirmed', 'Shoulder joint mobility consultation'),
(13, 3, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', 'MQ-2026-1013', 'confirmed', 'Prescription renewal & follow up'),
(14, 4, 5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', 'MQ-2026-1014', 'confirmed', 'Vaccination consultation'),

-- Past Appointments
(15, 5, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'MQ-2026-1015', 'completed', 'Cardiovascular stress test consultation'),
(16, 6, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '14:00:00', 'MQ-2026-1016', 'completed', 'Holter monitor review'),
(17, 7, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '15:30:00', 'MQ-2026-1017', 'cancelled', 'Patient requested cancellation due to travel'),
(18, 8, 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:00:00', 'MQ-2026-1018', 'no_show', 'Patient did not arrive for scheduled slot');

-- 7. Virtual Queue (Active Queue for Today)
INSERT INTO `queues` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `queue_number`, `queue_position`, `estimated_waiting_time`, `status`, `joined_at`, `called_at`) VALUES
(1, 1, 1, 1, 'CAR-101', 1, 0, 'called', NOW(), NOW()),
(2, 2, 2, 1, 'CAR-102', 2, 15, 'waiting', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NULL),
(3, 3, 3, 1, 'CAR-103', 3, 30, 'waiting', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NULL),
(4, 4, 4, 1, 'CAR-104', 4, 45, 'waiting', DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL),
(5, 5, 5, 2, 'GEN-201', 1, 0, 'in_consultation', DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(6, 6, 6, 2, 'GEN-202', 2, 15, 'waiting', NOW(), NULL);

-- 8. Visitors
INSERT INTO `visitors` (`id`, `patient_id`, `visitor_name`, `relationship`, `phone`, `visit_date`, `purpose`, `entry_time`, `exit_time`, `status`) VALUES
(1, 1, 'Mary Doe', 'Spouse', '+1 (555) 782-0011', CURDATE(), 'Accompanying patient for cardiology evaluation', '08:45:00', NULL, 'checked_in'),
(2, 2, 'David Watson', 'Brother', '+1 (555) 782-0012', CURDATE(), 'Bringing medical test reports', '09:15:00', NULL, 'checked_in'),
(3, 3, 'Carol Miller', 'Daughter', '+1 (555) 782-0013', CURDATE(), 'Consultation assistance', NULL, NULL, 'expected'),
(4, 10, 'Jessica White', 'Mother', '+1 (555) 782-0014', CURDATE(), 'Pediatric appointment companion', '09:20:00', '10:05:00', 'checked_out'),
(5, 7, 'George Jackson', 'Friend', '+1 (555) 782-0015', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Inpatient visit', '14:00:00', '16:00:00', 'checked_out');

-- 9. Notifications
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 9, 'Doctor Called Your Turn!', 'Dr. Sarah Jenkins is ready to see you! Please proceed to Suite 201-A immediately.', 'success', 0, NOW()),
(2, 9, 'Appointment Confirmed', 'Your appointment with Dr. Sarah Jenkins (Cardiology) is confirmed for today at 09:00 AM.', 'info', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 3, 'Queue Active', 'You have 4 patients waiting in your cardiology virtual queue.', 'info', 0, NOW()),
(4, 1, 'New Daily Appointments', '10 appointments scheduled for today across all hospital departments.', 'info', 0, NOW()),
(5, 10, 'Appointment Scheduled', 'Your appointment with Dr. Sarah Jenkins has been scheduled for tomorrow at 10:00 AM.', 'info', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));
