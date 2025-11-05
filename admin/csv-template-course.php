<?php
include '../config.php';
require_admin();

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="course_import_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Add BOM for proper UTF-8 encoding in Excel
echo "\xEF\xBB\xBF";

// Create file pointer
$output = fopen('php://output', 'w');

// Write CSV header (optional - users can skip this row)
$header = [
    'รหัสวิชา',
    'ชื่อวิชา',
    'ชื่ออาจารย์',
    'หน่วยกิต',
    'วันที่เรียน',
    'เวลาเรียน',
    'จำนวนที่นั่ง',
    'ห้องที่อนุญาต',
    'คำอธิบาย',
    'สถานะ'
];
fputcsv($output, $header);

// Write sample data (realistic Thai examples)
$sample_data = [
    [
        'CS101',
        'วิทยาการคำนวณเบื้องต้น',
        'อ.สมชาย ใจเรียน',
        '3',
        'วันจันทร์-พุธ',
        '09:00-10:30',
        '30',
        'ม.4/1,ม.4/2,ม.4/3',
        'บทนำสู่การเขียนโปรแกรม',
        'open'
    ],
    [
        'MATH201',
        'แคลคูลัส 1',
        'อ.อรดา สูงชาญ',
        '4',
        'วันอังคาร-พฤหัสบดี',
        '10:30-12:00',
        '25',
        'ม.5/1,ม.5/2',
        'ศึกษาความต่อเนื่องและอนุพันธ์',
        'open'
    ],
    [
        'ENG301',
        'ภาษาอังกฤษ Advanced',
        'อ.วิไล สมบูรณ์',
        '3',
        'วันจันทร์-ศุกร์',
        '13:00-14:30',
        '28',
        'ม.4/1,ม.5/1',
        'ทักษะภาษาอังกฤษขั้นสูง',
        'open'
    ],
    [
        'SCI102',
        'ฟิสิกส์เบื้องต้น',
        'อ.ณัฐ มีความสุข',
        '4',
        'วันพุธ-ศุกร์',
        '10:00-11:30',
        '30',
        'ม.4/2,ม.4/3',
        'บทนำสู่ฟิสิกส์แบบคลาสสิก',
        'open'
    ],
    [
        'BIO103',
        'ชีววิทยา',
        'อ.นิตยา จันทร์สว่าง',
        '3',
        'วันจันทร์-พฤหัสบดี',
        '14:30-16:00',
        '25',
        'ม.5/1,ม.5/2',
        'ศึกษาชีวิตและโครงสร้างของสิ่งมีชีวิต',
        'open'
    ],
    [
        'HIST401',
        'ประวัติศาสตร์ไทย',
        'อ.สมเด็จ พระเจ้า',
        '2',
        'วันศุกร์',
        '15:00-16:30',
        '35',
        '',
        'ศึกษาประวัติศาสตร์ของประเทศไทยตั้งแต่อดีต',
        'closed'
    ]
];

foreach ($sample_data as $row) {
    fputcsv($output, $row);
}

// Log the template download
log_activity($_SESSION['user_id'], 'template_download', "ดาวน์โหลดแม่แบบ CSV สำหรับนำเข้าวิชา", null);

fclose($output);
exit();
?>
