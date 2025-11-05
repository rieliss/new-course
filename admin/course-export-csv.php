<?php
include '../config.php';
require_admin();

$course_id = (int)($_GET['course_id'] ?? 0);

if ($course_id <= 0) {
    die("❌ ไม่พบรหัสวิชา");
}

// Get course information
$course_query = "SELECT * FROM courses WHERE id = ?";
$course_stmt = $conn->prepare($course_query);
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

if ($course_result->num_rows == 0) {
    die("❌ ไม่พบวิชา");
}

$course = $course_result->fetch_assoc();
$course_stmt->close();

// Get enrolled students
$students_query = "SELECT u.id, u.student_id, u.full_name, u.class_room, u.email, u.status, e.enrolled_at
                   FROM users u
                   JOIN enrollments e ON u.id = e.student_id
                   WHERE e.course_id = ? AND e.enrollment_status = 'enrolled' AND u.role = 'student'
                   ORDER BY u.student_id";

$students_stmt = $conn->prepare($students_query);
$students_stmt->bind_param("i", $course_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

// Generate CSV headers and content
$filename = 'course_' . $course['course_code'] . '_' . date('Y-m-d_His') . '.csv';

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8-sig');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create CSV output
$output = fopen('php://output', 'w');

// Write BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write course information
fputcsv($output, ['รายงานนักเรียนที่ลงทะเบียน'], ',', '"', "\\");
fputcsv($output, [], ',', '"', "\\");

fputcsv($output, ['รหัสวิชา:', $course['course_code']], ',', '"', "\\");
fputcsv($output, ['ชื่อวิชา:', $course['course_name']], ',', '"', "\\");
fputcsv($output, ['อาจารย์:', $course['teacher_name']], ',', '"', "\\");
fputcsv($output, ['หน่วยกิต:', $course['credits']], ',', '"', "\\");
fputcsv($output, ['วัน-เวลา:', $course['schedule_day'] . ' ' . $course['schedule_time']], ',', '"', "\\");
fputcsv($output, ['วันที่ออกรายงาน:', date('d/m/Y H:i:s')], ',', '"', "\\");
fputcsv($output, [], ',', '"', "\\");

// Write headers
$headers = ['ลำดับที่', 'รหัสนักเรียน', 'ชื่อ-สกุล', 'ห้องเรียน', 'อีเมล', 'สถานะ', 'วันที่ลงทะเบียน'];
fputcsv($output, $headers, ',', '"', "\\");

// Write student data
$count = 1;
while ($student = $students_result->fetch_assoc()) {
    $enrolled_date = date('d/m/Y H:i', strtotime($student['enrolled_at']));
    fputcsv($output, [
        $count,
        $student['student_id'],
        $student['full_name'],
        $student['class_room'],
        $student['email'] ?? '-',
        $student['status'] == 'active' ? 'ใช้งานอยู่' : 'ระบายน้ำ',
        $enrolled_date
    ], ',', '"', "\\");
    $count++;
}

// Write summary
fputcsv($output, [], ',', '"', "\\");
fputcsv($output, ['จำนวนนักเรียนทั้งหมด:', $students_result->num_rows], ',', '"', "\\");

// Log activity
log_activity($_SESSION['user_id'], 'export_csv', 
    "ส่งออก CSV นักเรียนวิชา: {$course['course_name']} ({$course['course_code']})", 
    $course_id);

$students_stmt->close();

fclose($output);
exit();
?>
