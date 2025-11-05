<?php
include '../config.php';
require_admin();

$course_id = (int)($_GET['course_id'] ?? 0);
$error = '';
$success = '';

if ($course_id <= 0) {
    header('Location: courses-management.php');
    exit();
}

// Get course information
$course_query = "SELECT * FROM courses WHERE id = ?";
$course_stmt = $conn->prepare($course_query);
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

if ($course_result->num_rows == 0) {
    header('Location: courses-management.php');
    exit();
}

$course = $course_result->fetch_assoc();
$course_stmt->close();

// Promotion features removed - use User Management page for bulk promotion

// Get enrolled students
$students_query = "SELECT u.id, u.student_id, u.username, u.full_name, u.class_room, u.class_number, e.enrolled_at
                   FROM users u 
                   JOIN enrollments e ON u.id = e.student_id 
                   WHERE e.course_id = ? AND e.enrollment_status = 'enrolled' AND u.role = 'student'
                   ORDER BY u.class_room, u.class_number";
$students_stmt = $conn->prepare($students_query);
$students_stmt->bind_param("i", $course_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

$enrolled_students = [];
while ($student = $students_result->fetch_assoc()) {
    $enrolled_students[] = $student;
}
$students_stmt->close();

// Group students by class
$students_by_class = [];
foreach ($enrolled_students as $student) {
    $class = $student['class_room'] ?: 'ไม่ระบุ';
    if (!isset($students_by_class[$class])) {
        $students_by_class[$class] = [];
    }
    $students_by_class[$class][] = $student;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👨‍🎓 นักเรียนในรายวิชา - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand { font-size: 24px; font-weight: 700; }
        
        .btn-logout {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 { font-size: 28px; color: #333; }
        
        .btn-back {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover { background: #5568d3; }
        
        .course-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .course-info-card h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .course-info-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
        }
        
        .course-info-item strong {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover { background: #5568d3; }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover { background: #218838; }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover { background: #e0a800; }
        
        .students-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .class-section {
            border-bottom: 1px solid #e0e0e0;
        }
        
        .class-header {
            background: #f8f9fa;
            padding: 15px 20px;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .students-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .students-table th,
        .students-table td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .students-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .students-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label { color: #666; font-size: 14px; }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .course-info-grid {
                grid-template-columns: 1fr;
            }
            
            .students-table {
                font-size: 14px;
            }
            
            .students-table th,
            .students-table td {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">📚 <?php echo SITE_NAME; ?></div>
            <a href="../logout.php" class="btn-logout">🚪 ออกจากระบบ</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>👨‍🎓 นักเรียนในรายวิชา</h2>
            <a href="courses-management.php" class="btn-back">← กลับจัดการวิชา</a>
        </div>
        
        <div class="course-info-card">
            <h3>📖 <?php echo htmlspecialchars($course['course_name']); ?></h3>
            <div class="course-info-grid">
                <div class="course-info-item">
                    <strong>รหัสวิชา</strong>
                    <?php echo htmlspecialchars($course['course_code']); ?>
                </div>
                <div class="course-info-item">
                    <strong>อาจารย์ผู้สอน</strong>
                    <?php echo htmlspecialchars($course['teacher_name']); ?>
                </div>
                <div class="course-info-item">
                    <strong>หน่วยกิต</strong>
                    <?php echo $course['credits']; ?> หน่วยกิต
                </div>
                <div class="course-info-item">
                    <strong>ตารางเรียน</strong>
                    <?php echo htmlspecialchars($course['schedule_day'] . ' ' . $course['schedule_time']); ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($enrolled_students); ?></div>
                <div class="stat-label">👨‍🎓 นักเรียนทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($students_by_class); ?></div>
                <div class="stat-label">🏫 จำนวนห้องเรียน</div>
            </div>
        </div>
        
        <!-- Students List -->
        <?php if (!empty($students_by_class)): ?>
            <div class="students-container">
                <?php foreach ($students_by_class as $class_name => $students): ?>
                    <div class="class-section">
                        <div class="class-header">
                            🏫 ห้อง <?php echo htmlspecialchars($class_name); ?> 
                            (<?php echo count($students); ?> คน)
                        </div>
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>เลขที่</th>
                                    <th>รหัสนักเรียน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>ชื่อผู้ใช้</th>
                                    <th>วันที่ลงทะเบียน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['class_number'] ?: '-'; ?></td>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo format_date_thai($student['enrolled_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="students-container">
                <div style="text-align: center; padding: 40px; color: #666;">
                    ไม่มีนักเรียนลงทะเบียนในรายวิชานี้
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
