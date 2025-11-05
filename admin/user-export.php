<?php
include '../config.php';
require_admin();

// Get filter parameters
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($filter_role)) {
    $where_conditions[] = "role = ?";
    $params[] = $filter_role;
    $param_types .= 's';
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

// Fetch users
$query = "SELECT student_id, username, full_name, class_room, class_number, email, role, status, created_at FROM users $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Generate filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = "users_export_$timestamp.csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Add BOM for proper UTF-8 encoding in Excel
echo "\xEF\xBB\xBF";

// Create file pointer
$output = fopen('php://output', 'w');

// Write CSV header
$header = [
    'รหัสนักเรียน',
    'ชื่อผู้ใช้',
    'ชื่อเต็ม',
    'ห้องเรียน',
    'เลขที่',
    'อีเมล',
    'สิทธิ์',
    'สถานะ',
    'วันที่สร้าง'
];
fputcsv($output, $header);

// Write data rows
while ($user = $result->fetch_assoc()) {
    $row = [
        $user['student_id'],
        $user['username'],
        $user['full_name'],
        $user['class_room'] ?? '',
        $user['class_number'] ?? '',
        $user['email'] ?? '',
        $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 'นักเรียน',
        $user['status'] == 'active' ? 'ใช้งานอยู่' : 'ปิดใช้งาน',
        format_date_thai($user['created_at'])
    ];
    fputcsv($output, $row);
}

// Log the export activity
log_activity($_SESSION['user_id'], 'user_export', "ส่งออกข้อมูลผู้ใช้ ($filename)", null);

fclose($output);
$stmt->close();
exit();
?>
