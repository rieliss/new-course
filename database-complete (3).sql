-- ========================================
-- Course Registration System Database
-- WITH SEMESTER, GRADE LEVEL, AND ENROLLMENT BLOCKS
-- ========================================
-- Complete Installation Script
-- Ready to import: mysql -u root course_registration < database-complete.sql

-- Create Database
CREATE DATABASE IF NOT EXISTS course_registration;
USE course_registration;

-- Drop existing tables (clean installation)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS admin_config;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- Users Table
-- ========================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    class_room VARCHAR(50),
    class_number INT,
    email VARCHAR(100),
    role ENUM('student', 'admin') DEFAULT 'student',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Admin Config Table (FOR MANAGING ACTIVE SEMESTER)
-- ========================================
CREATE TABLE admin_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Courses Table (WITH SEMESTER & GRADE LEVEL SUPPORT)
-- ========================================
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    teacher_name VARCHAR(150) NOT NULL,
    credits INT NOT NULL,
    schedule_day VARCHAR(100),
    schedule_time VARCHAR(50),
    max_seats INT NOT NULL DEFAULT 30,
    grade_level INT NOT NULL COMMENT '4, 5, 6 (ชั้นปี)',
    semester INT NOT NULL COMMENT '1, 2',
    classroom VARCHAR(50) NOT NULL COMMENT 'ห้องเรียน เช่น ม.4/1',
    max_enrollments INT NOT NULL DEFAULT 999 COMMENT 'Block course: จำนวนวิชาสูงสุด',
    academic_year INT NOT NULL DEFAULT 2024,
    status ENUM('open', 'closed') DEFAULT 'open',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_course_period (course_code, grade_level, semester, classroom, academic_year),
    INDEX idx_course_code (course_code),
    INDEX idx_grade_level (grade_level),
    INDEX idx_semester (semester),
    INDEX idx_classroom (classroom),
    INDEX idx_status (status),
    INDEX idx_academic_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Enrollments Table
-- ========================================
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    academic_year INT NOT NULL DEFAULT 2024,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id, academic_year),
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_status (enrollment_status),
    INDEX idx_academic_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Activity Logs Table
-- ========================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50),
    description VARCHAR(255),
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Insert Default Admin User
-- Username: admin
-- Password: admin123 (bcrypt hashed)
-- ========================================
INSERT INTO users (student_id, username, password, full_name, role, status) 
VALUES ('ADM001', 'admin', '$2y$10$EY7NKIRoow4SfHJ9C7WAe.c81pLd0Olc8hdT7/N/cUfwZXL2Egb0y', 'ผู้ดูแลระบบ', 'admin', 'active')
ON DUPLICATE KEY UPDATE username=username;

-- ========================================
-- Insert Default Config (Current Semester)
-- ========================================
INSERT INTO admin_config (config_key, config_value) VALUES
('current_academic_year', '2024'),
('current_semester', '1')
ON DUPLICATE KEY UPDATE config_value=config_value;

-- ========================================
-- Insert Sample Courses
-- Grade 4, Semester 1: 3 courses, can select max 2
-- Grade 5, Semester 1: 2 courses, can select max 1
-- ========================================
INSERT INTO courses (course_code, course_name, teacher_name, credits, schedule_day, schedule_time, max_seats, grade_level, semester, classroom, max_enrollments, academic_year, status)
VALUES 
-- Grade 4, Semester 1 - Block course (ให้เลือก 2 วิชา)
('CS101', 'วิทยาการคำนวณเบื้องต้น', 'อ.สมชาย', 3, 'วันจันทร์-พุธ', '09:00-10:30', 30, 4, 1, 'ม.4/1', 2, 2024, 'open'),
('MATH201', 'แคลคูลัส 1', 'อ.อรดา', 4, 'วันอังคาร-พฤหัสบดี', '10:30-12:00', 25, 4, 1, 'ม.4/1', 2, 2024, 'open'),
('ENG301', 'ภาษาอังกฤษ Advanced', 'อ.วิไล', 3, 'วันจันทร์-ศุกร์', '13:00-14:30', 28, 4, 1, 'ม.4/1', 2, 2024, 'open'),

-- Grade 4, Semester 1 - Class ม.4/2
('CS101', 'วิทยาการคำนวณเบื้องต้น', 'อ.สมชาย', 3, 'วันจันทร์-พุธ', '09:00-10:30', 30, 4, 1, 'ม.4/2', 2, 2024, 'open'),
('MATH201', 'แคลคูลัส 1', 'อ.อรดา', 4, 'วันอังคาร-พฤหัสบดี', '10:30-12:00', 25, 4, 1, 'ม.4/2', 2, 2024, 'open'),
('SCI102', 'ฟิสิกส์เบื้องต้น', 'อ.ณัฐ', 4, 'วันพุธ-ศุกร์', '10:00-11:30', 30, 4, 1, 'ม.4/2', 2, 2024, 'open'),

-- Grade 5, Semester 1 - Block course (ให้เลือก 1 วิชา)
('BIO103', 'ชีววิทยา', 'อ.นิตยา', 3, 'วันจันทร์-พฤหัสบดี', '14:30-16:00', 25, 5, 1, 'ม.5/1', 1, 2024, 'open'),
('CHEM104', 'เคมีเบื้องต้น', 'อ.ประยุทธ', 4, 'วันอังคาร-ศุกร์', '11:00-12:30', 22, 5, 1, 'ม.5/1', 1, 2024, 'open');

-- ========================================
-- Insert Sample Students
-- Password: password123 (bcrypt hashed)
-- ========================================
INSERT INTO users (student_id, username, password, full_name, class_room, class_number, role, status)
VALUES 
('ST001', 'student1', '$2y$10$XO0YcaxlqlL5i7IkZ9.FjOnCQNdmeINbGvsi0HHKqZpwtsqBACvni', 'สมเด็จ พระเจ้า', 'ม.4/1', 1, 'student', 'active'),
('ST002', 'student2', '$2y$10$XO0YcaxlqlL5i7IkZ9.FjOnCQNdmeINbGvsi0HHKqZpwtsqBACvni', 'สม.หญิง นกเขา', 'ม.4/2', 5, 'student', 'active'),
('ST003', 'student3', '$2y$10$XO0YcaxlqlL5i7IkZ9.FjOnCQNdmeINbGvsi0HHKqZpwtsqBACvni', 'นาย ต้นไม้', 'ม.5/1', 10, 'student', 'active')
ON DUPLICATE KEY UPDATE username=username;

-- ========================================
-- Log: System Ready
-- ========================================
-- Database is now ready for use with semester support!
-- Tables created with:
-- ✓ grade_level & semester support
-- ✓ max_enrollments (block course)
-- ✓ classroom assignment
-- ✓ admin_config for current period

-- ========================================
-- Test: Verify tables created successfully
-- ========================================
-- Run these queries to verify:
-- SELECT * FROM users;
-- SELECT * FROM courses;
-- SELECT * FROM enrollments;
-- SELECT * FROM activity_logs;
-- SELECT * FROM admin_config;

-- ========================================
-- Ready to use!
-- Admin login: admin / admin123
-- Student logins: 
--   - student1 / password123 (ม.4/1)
--   - student2 / password123 (ม.4/2)
--   - student3 / password123 (ม.5/1)
-- ========================================
