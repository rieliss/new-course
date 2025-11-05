-- ========================================
-- Course Registration System Database
-- WITH COURSE-BASED PROGRESSION
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
-- Courses Table (WITH ACADEMIC YEAR SUPPORT)
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
    allowed_classes TEXT,
    academic_year INT NOT NULL DEFAULT 2024,
    continuation_course_id INT,
    status ENUM('open', 'closed') DEFAULT 'open',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_course_year (course_code, academic_year),
    FOREIGN KEY (continuation_course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_course_code (course_code),
    INDEX idx_status (status),
    INDEX idx_academic_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Enrollments Table (WITH VISIBILITY & PROGRESSION)
-- ========================================
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    academic_year INT NOT NULL DEFAULT 2024,
    visibility_status ENUM('current', 'future') DEFAULT 'current',
    linked_enrollment_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_enrollment_id) REFERENCES enrollments(id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_status (enrollment_status),
    INDEX idx_academic_year (academic_year),
    INDEX idx_visibility (visibility_status)
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
-- Insert Sample Courses (YEAR 2024)
-- ========================================
INSERT INTO courses (course_code, course_name, teacher_name, credits, schedule_day, schedule_time, max_seats, allowed_classes, academic_year, status)
VALUES 
('CS101', 'วิทยาการคำนวณเบื้องต้น', 'อ.สมชาย', 3, 'วันจันทร์-พุธ', '09:00-10:30', 30, 'ม.4/1,ม.4/2,ม.4/3', 2024, 'open'),
('MATH201', 'แคลคูลัส 1', 'อ.อรดา', 4, 'วันอังคาร-พฤหัสบดี', '10:30-12:00', 25, 'ม.5/1,ม.5/2', 2024, 'open'),
('ENG301', 'ภาษาอังกฤษ Advanced', 'อ.วิไล', 3, 'วันจันทร์-ศุกร์', '13:00-14:30', 28, 'ม.4/1,ม.5/1', 2024, 'open'),
('SCI102', 'ฟิสิกส์เบื้องต้น', 'อ.ณัฐ', 4, 'วันพุธ-ศุกร์', '10:00-11:30', 30, 'ม.4/2,ม.4/3', 2024, 'open'),
('BIO103', 'ชีววิทยา', 'อ.นิตยา', 3, 'วันจันทร์-พฤหัสบดี', '14:30-16:00', 25, 'ม.5/1,ม.5/2', 2024, 'open');

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
-- Sample Enrollments (COURSE-BASED PROGRESSION)
-- ========================================
-- Student 1 (สมเด็จ) enrolls in CS101 (2024)
-- System will create future enrollments for 2025 and 2026
-- INSERT INTO enrollments (student_id, course_id, enrollment_status, academic_year, visibility_status)
-- VALUES 
-- (1, 1, 'enrolled', 2024, 'current');

-- ========================================
-- Log: System Ready
-- ========================================
-- Database is now ready for use with course-based progression!
-- Tables created with:
-- ✓ academic_year support
-- ✓ visibility_status (current/future)
-- ✓ linked_enrollment_id (for progression tracking)
-- ✓ continuation_course_id (for course linking)

-- ========================================
-- Test: Verify tables created successfully
-- ========================================
-- Run these queries to verify:
-- SELECT * FROM users;
-- SELECT * FROM courses;
-- SELECT * FROM enrollments;
-- SELECT * FROM activity_logs;

-- ========================================
-- Ready to use!
-- Admin login: admin / admin123
-- ========================================
