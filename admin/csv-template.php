<?php
include '../config.php';
require_admin();

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="user_import_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Add BOM for proper UTF-8 encoding in Excel
echo "\xEF\xBB\xBF";

// Create file pointer
$output = fopen('php://output', 'w');

// Write CSV header (optional - users can skip this row)
$header = [
    'รหัสนักเรียน',
    'ชื่อผู้ใช้',
    'ชื่อเต็ม',
    'รหัสผ่าน',
    'ห้องเรียน',
    'เลขที่',
    'อีเมล',
    'สิทธิ์',
    'สถานะ'
];
fputcsv($output, $header);

// Write sample data
$sample_data = [
    [
        'ST001',
        'student1',
        'นายสมชาย ใจดี',
        '123456',
        'ม.4/1',
        '1',
        'somchai@email.com',
        'student',
        'active'
    ],
    [
        'ST002',
        'student2',
        'นางสาววิไล สวยงาม',
        'password',
        'ม.4/2',
        '5',
        '',
        'student',
        'active'
    ],
    [
        'ST003',
        'teacher1',
        'อาจารย์สมศักดิ์ การเรียน',
        'teacher123',
        '',
        '',
        'teacher@school.com',
        'admin',
        'active'
    ]
];

foreach ($sample_data as $row) {
    fputcsv($output, $row);
}

// Log the template download
log_activity($_SESSION['user_id'], 'template_download', "ดาวน์โหลดแม่แบบ CSV สำหรับนำเข้าผู้ใช้", null);

fclose($output);
exit();
?>
