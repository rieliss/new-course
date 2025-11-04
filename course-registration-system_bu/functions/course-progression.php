<?php
/**
 * Course Progression Functions
 * Handles automatic creation of future course enrollments and course-based grade progression
 */

/**
 * Get the next academic year
 */
function getNextAcademicYear($current_year) {
    return $current_year + 1;
}

/**
 * Get the next class room based on current class
 * ม.4/1 -> ม.5/1, ม.5/1 -> ม.6/1, ม.6/1 -> ป.1/1
 */
function getNextClassRoom($current_class) {
    if (empty($current_class)) return '';
    
    // Pattern: ม.4/1 or ป.1/1
    // Split by / first
    $parts = explode('/', $current_class);
    if (count($parts) !== 2) return null;
    
    $level_part = $parts[0]; // e.g., "ม.4" or "ป.1"
    $section = $parts[1];    // e.g., "1"
    
    // Split level_part by dot
    $dot_pos = strpos($level_part, '.');
    if ($dot_pos === false) return null;
    
    $level_type = substr($level_part, 0, $dot_pos); // e.g., "ม" or "ป"
    $current_level = (int)substr($level_part, $dot_pos + 1); // e.g., 4 or 1
    
    // ม.6 -> ป.1
    if ($level_type === 'ม' && $current_level == 6) {
        return "ป.1/$section";
    }
    // ป.6 -> no next (จบการศึกษา)
    elseif ($level_type === 'ป' && $current_level == 6) {
        return null;
    }
    // Normal progression: ม.4/1 -> ม.5/1
    else {
        $next_level = $current_level + 1;
        return "{$level_type}.{$next_level}/{$section}";
    }
}

/**
 * Create future enrollments when student enrolls in a course
 * This automatically creates continuation courses for the next 2 years
 */
function createFutureEnrollments($conn, $student_id, $course_id, $current_academic_year) {
    try {
        // Get current course info
        $course_query = "SELECT id, course_code, course_name, teacher_name, credits, 
                        schedule_day, schedule_time, max_seats, allowed_classes 
                        FROM courses WHERE id = ?";
        $course_stmt = $conn->prepare($course_query);
        $course_stmt->bind_param("i", $course_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        
        if ($course_result->num_rows == 0) {
            $course_stmt->close();
            return false;
        }
        
        $current_course = $course_result->fetch_assoc();
        $course_stmt->close();
        
        // Get student current class
        $student_query = "SELECT class_room FROM users WHERE id = ?";
        $student_stmt = $conn->prepare($student_query);
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows == 0) {
            $student_stmt->close();
            return false;
        }
        
        $student = $student_result->fetch_assoc();
        $student_stmt->close();
        
        // Start transaction
        $conn->begin_transaction();
        
        // Create future enrollments for next 2 years
        $current_year = $current_academic_year;
        $prev_enrollment_id = null;
        
        for ($i = 1; $i <= 2; $i++) {
            $next_year = $current_year + $i;
            $next_class = getNextClassRoom($student->class_room);
            
            // Skip if we've reached the end of schooling
            if ($next_class === null) {
                break;
            }
            
            // Check if continuation course already exists for next year
            // If not, create it
            $continuation_course_id = findOrCreateContinuationCourse(
                $conn,
                $current_course,
                $next_year,
                $next_class
            );
            
            if (!$continuation_course_id) {
                throw new Exception("Failed to create continuation course for year $next_year");
            }
            
            // Create future enrollment
            $enroll_query = "INSERT INTO enrollments 
                           (student_id, course_id, academic_year, visibility_status, linked_enrollment_id)
                           VALUES (?, ?, ?, 'future', ?)";
            $enroll_stmt = $conn->prepare($enroll_query);
            $enroll_stmt->bind_param("iiii", $student_id, $continuation_course_id, $next_year, $prev_enrollment_id);
            
            if (!$enroll_stmt->execute()) {
                throw new Exception("Failed to create future enrollment: " . $enroll_stmt->error);
            }
            
            $prev_enrollment_id = $enroll_stmt->insert_id;
            $enroll_stmt->close();
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Find or create a continuation course for the next year
 */
function findOrCreateContinuationCourse($conn, $course, $next_year, $next_class) {
    try {
        // Check if course already exists for next year with same code
        $find_query = "SELECT id FROM courses 
                      WHERE course_code = ? AND academic_year = ?";
        $find_stmt = $conn->prepare($find_query);
        $find_stmt->bind_param("si", $course['course_code'], $next_year);
        $find_stmt->execute();
        $find_result = $find_stmt->get_result();
        
        if ($find_result->num_rows > 0) {
            $existing = $find_result->fetch_assoc();
            $find_stmt->close();
            return $existing['id'];
        }
        
        $find_stmt->close();
        
        // Create new continuation course
        $insert_query = "INSERT INTO courses 
                       (course_code, course_name, teacher_name, credits, 
                        schedule_day, schedule_time, max_seats, allowed_classes, academic_year, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')";
        
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param(
            "sssiisssi",
            $course['course_code'],
            $course['course_name'],
            $course['teacher_name'],
            $course['credits'],
            $course['schedule_day'],
            $course['schedule_time'],
            $course['max_seats'],
            $next_class,
            $next_year
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to insert continuation course: " . $insert_stmt->error);
        }
        
        $new_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        return $new_id;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Promote student for a specific course
 * This marks current enrollment as 'completed' and activates the next year enrollment
 * Also updates student's class room
 * ✅ Admin just needs to update class_room first!
 */
function promoteStudentForCourse($conn, $student_id, $course_id, $current_academic_year = null) {
    try {
        $conn->begin_transaction();
        
        // Find current enrollment (linked to future enrollment)
        $current_enroll_query = "SELECT id FROM enrollments 
                                WHERE student_id = ? AND course_id = ? 
                                AND visibility_status = 'current'
                                AND linked_enrollment_id IS NOT NULL";
        $current_stmt = $conn->prepare($current_enroll_query);
        $current_stmt->bind_param("ii", $student_id, $course_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        if ($current_result->num_rows == 0) {
            throw new Exception("Current enrollment not found");
        }
        
        $current_enrollment = $current_result->fetch_assoc();
        $current_enrollment_id = $current_enrollment['id'];
        $current_stmt->close();
        
        // Mark current enrollment as completed
        $mark_query = "UPDATE enrollments SET enrollment_status = 'completed' WHERE id = ?";
        $mark_stmt = $conn->prepare($mark_query);
        $mark_stmt->bind_param("i", $current_enrollment_id);
        
        if (!$mark_stmt->execute()) {
            throw new Exception("Failed to mark enrollment as completed");
        }
        $mark_stmt->close();
        
        // Find next year enrollment linked to this one
        $next_enroll_query = "SELECT e.id, e.course_id, c.course_code 
                             FROM enrollments e
                             JOIN courses c ON e.course_id = c.id
                             WHERE e.linked_enrollment_id = ? 
                             AND e.visibility_status = 'future' 
                             LIMIT 1";
        $next_stmt = $conn->prepare($next_enroll_query);
        $next_stmt->bind_param("i", $current_enrollment_id);
        $next_stmt->execute();
        $next_result = $next_stmt->get_result();
        
        if ($next_result->num_rows > 0) {
            $next_enrollment = $next_result->fetch_assoc();
            
            // Activate the next year enrollment
            $activate_query = "UPDATE enrollments SET visibility_status = 'current', 
                             enrollment_status = 'enrolled' WHERE id = ?";
            $activate_stmt = $conn->prepare($activate_query);
            $activate_stmt->bind_param("i", $next_enrollment['id']);
            
            if (!$activate_stmt->execute()) {
                throw new Exception("Failed to activate next year enrollment");
            }
            $activate_stmt->close();
        }
        
        $next_stmt->close();
        
        // Update student's class room to next year
        $current_class_query = "SELECT class_room FROM users WHERE id = ?";
        $current_class_stmt = $conn->prepare($current_class_query);
        $current_class_stmt->bind_param("i", $student_id);
        $current_class_stmt->execute();
        $current_class_result = $current_class_stmt->get_result();
        
        if ($current_class_result->num_rows > 0) {
            $current_student = $current_class_result->fetch_assoc();
            $next_class = getNextClassRoom($current_student['class_room']);
            
            if ($next_class !== null) {
                $update_class_query = "UPDATE users SET class_room = ? WHERE id = ?";
                $update_class_stmt = $conn->prepare($update_class_query);
                $update_class_stmt->bind_param("si", $next_class, $student_id);
                
                if (!$update_class_stmt->execute()) {
                    throw new Exception("Failed to update student class room");
                }
                $update_class_stmt->close();
            }
        }
        
        $current_class_stmt->close();
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Get current year enrollments only (visible to student)
 */
function getStudentCurrentEnrollments($conn, $student_id) {
    $query = "SELECT c.id, c.course_code, c.course_name, c.teacher_name, c.credits, 
              c.schedule_day, c.schedule_time, e.enrolled_at, e.academic_year
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE e.student_id = ? AND e.enrollment_status = 'enrolled' 
              AND e.visibility_status = 'current'
              ORDER BY e.enrolled_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    
    return $stmt->get_result();
}

/**
 * Get enrollments for a specific course (for admin - course-based promotion)
 */
function getCourseEnrollments($conn, $course_id, $academic_year) {
    $query = "SELECT u.id, u.student_id, u.full_name, u.class_room, 
              e.id as enrollment_id, e.academic_year
              FROM enrollments e
              JOIN users u ON e.student_id = u.id
              WHERE e.course_id = ? AND e.academic_year = ? 
              AND e.enrollment_status = 'enrolled' AND e.visibility_status = 'current'
              ORDER BY u.full_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $course_id, $academic_year);
    $stmt->execute();
    
    return $stmt->get_result();
}

/**
 * Check if student can be promoted for a course
 * (i.e., has a current enrollment and future enrollment ready)
 * ✅ Admin just needs to update the student's class_room
 */
function canPromoteForCourse($conn, $student_id, $course_id) {
    // Check if student has current enrollment for this course
    $query = "SELECT e.id FROM enrollments e
              WHERE e.student_id = ? AND e.course_id = ?
              AND e.visibility_status = 'current'
              AND e.enrollment_status = 'enrolled'
              AND e.linked_enrollment_id IS NOT NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $can_promote = $result->num_rows > 0;
    $stmt->close();
    
    return $can_promote;
}

/**
 * Get current academic year from system
 */
function getCurrentAcademicYear() {
    $current_date = new DateTime();
    $year = (int)$current_date->format('Y');
    
    // Thai academic year starts June
    // So if current month is June-December, it's the same year
    // If January-May, it's the previous year
    $month = (int)$current_date->format('m');
    
    if ($month < 6) {
        return $year - 1;
    }
    
    return $year;
}

?>
