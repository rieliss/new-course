-- Migration Script: Add Course Progression Fields
-- This script updates existing database with new fields for course-based grade progression

-- Add new columns to enrollments table
ALTER TABLE enrollments ADD COLUMN academic_year INT NOT NULL DEFAULT 2024 AFTER enrollment_status;
ALTER TABLE enrollments ADD COLUMN visibility_status ENUM('current', 'future') DEFAULT 'current' AFTER academic_year;
ALTER TABLE enrollments ADD COLUMN linked_enrollment_id INT AFTER visibility_status;

-- Add foreign key for linked_enrollment_id
ALTER TABLE enrollments ADD FOREIGN KEY (linked_enrollment_id) REFERENCES enrollments(id) ON DELETE SET NULL;

-- Remove UNIQUE constraint on (student_id, course_id)
-- Note: This might need manual handling if the constraint prevents deletion
-- ALTER TABLE enrollments DROP INDEX unique_enrollment;

-- Add new indexes
ALTER TABLE enrollments ADD INDEX idx_academic_year (academic_year);
ALTER TABLE enrollments ADD INDEX idx_visibility (visibility_status);

-- Add new columns to courses table
ALTER TABLE courses ADD COLUMN academic_year INT NOT NULL DEFAULT 2024 AFTER status;
ALTER TABLE courses ADD COLUMN continuation_course_id INT AFTER academic_year;

-- Remove UNIQUE constraint from course_code
-- ALTER TABLE courses DROP INDEX course_code; 
-- This must be done carefully - the current UNIQUE constraint needs to be replaced with a regular INDEX

-- Add foreign key for continuation_course_id
ALTER TABLE courses ADD FOREIGN KEY (continuation_course_id) REFERENCES courses(id) ON DELETE SET NULL;

-- Add new indexes
ALTER TABLE courses ADD INDEX idx_academic_year (academic_year);

-- Update existing enrollments to be marked as 'current' visibility
UPDATE enrollments SET visibility_status = 'current' WHERE visibility_status IS NULL OR visibility_status = '';

-- Set academic year based on current date
-- Assuming Thai academic year (starts June)
UPDATE courses SET academic_year = YEAR(CURDATE()) WHERE MONTH(CURDATE()) >= 6;
UPDATE courses SET academic_year = YEAR(CURDATE()) - 1 WHERE MONTH(CURDATE()) < 6;

UPDATE enrollments SET academic_year = YEAR(e.enrolled_at) 
FROM enrollments e;
