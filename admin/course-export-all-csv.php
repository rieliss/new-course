<?php
include '../config.php';
require_admin();

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(course_code LIKE ? OR course_name LIKE ? OR teacher_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get courses
$query = "SELECT c.*, COUNT(e.id) as enrolled_count
          FROM courses c
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.enrollment_status = 'enrolled'
          $where_clause
          GROUP BY c.id
          ORDER BY c.course_code";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$courses_result = $stmt->get_result();

// Generate CSV filename
$filename = 'courses_' . date('Y-m-d_His') . '.csv';

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8-sig');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create CSV output
$output = fopen('php://output', 'w');

// Write BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header
$headers = ['รหัสวิชา', 'ชื่อวิชา', 'อาจารย์', 'หน่วยกิต', 'วันเรียน', 'เวลาเรียน', 'ที่นั่ง', 'ลงทะเบียน', 'ห้องที่อนุญาต', 'คำอธิบาย', 'สถานะ', 'วันที่สร้าง', 'วันที่แก้ไข'];
fputcsv($output, $headers, ',', '"', "\\");

// Write data
while ($course = $courses_result->fetch_assoc()) {
    $status_text = $course['status'] == 'open' ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร';
    $created_date = date('d/m/Y H:i', strtotime($course['created_at']));
    $updated_date = date('d/m/Y H:i', strtotime($course['updated_at']));
    
    fputcsv($output, [
        $course['course_code'],
        $course['course_name'],
        $course['teacher_name'],
        $course['credits'],
        $course['schedule_day'],
        $course['schedule_time'],
        $course['max_seats'],
        $course['enrolled_count'],
        $course['allowed_classes'],
        $course['description'],
        $status_text,
        $created_date,
        $updated_date
    ], ',', '"', "\\");
}

// Log activity
log_activity($_SESSION['user_id'], 'export_courses_csv', 
    "ส่งออก CSV วิชาทั้งหมด", null);

$stmt->close();

fclose($output);
exit();
?>
