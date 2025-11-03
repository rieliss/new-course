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

// Handle promotion action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'promote_all') {
        // Get all enrolled students
        $students_query = "SELECT u.id, u.full_name, u.class_room, u.class_number 
                          FROM users u 
                          JOIN enrollments e ON u.id = e.student_id 
                          WHERE e.course_id = ? AND e.enrollment_status = 'enrolled' AND u.role = 'student'";
        $students_stmt = $conn->prepare($students_query);
        $students_stmt->bind_param("i", $course_id);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
        
        $promoted_count = 0;
        $conn->begin_transaction();
        
        try {
            while ($student = $students_result->fetch_assoc()) {
                $new_class_room = promoteClassRoom($student['class_room']);
                if ($new_class_room) {
                    $update_query = "UPDATE users SET class_room = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("si", $new_class_room, $student['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    $promoted_count++;
                    
                    log_activity($_SESSION['user_id'], 'student_promotion', 
                        "เลื่อนชั้น: {$student['full_name']} จาก {$student['class_room']} เป็น $new_class_room", 
                        $student['id']);
                }
            }
            
            $conn->commit();
            $success = "✅ เลื่อนชั้นนักเรียนเรียบร้อยแล้ว จำนวน $promoted_count คน";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ เกิดข้อผิดพลาดในการเลื่อนชั้น: " . $e->getMessage();
        }
        
        $students_stmt->close();
    }
}

// Function to promote class room
function promoteClassRoom($current_class) {
    if (empty($current_class)) return null;
    
    // Pattern: ม.4/1 -> ม.5/1, ป.1/1 -> ป.2/1, etc.
    if (preg_match('/^([ม]|[ป])\.(\d+)\/(.+)$/', $current_class, $matches)) {
        $level_type = $matches[1]; // ม. หรือ ป.
        $current_level = (int)$matches[2];
        $section = $matches[3];
        
        // ม.6 ไปเป็น ป.1
        if ($level_type == 'ม' && $current_level == 6) {
            return "ป.1/$section";
        }
        // ป.6 จบการศึกษา - ไม่เลื่อน
        elseif ($level_type == 'ป' && $current_level == 6) {
            return null;
        }
        // เลื่อนชั้นปกติ
        else {
            $new_level = $current_level + 1;
            return "$level_type.$new_level/$section";
        }
    }
    
    return null; // ไม่สามารถเลื่อนได้
}

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
        
        .promotion-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .promotion-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .promotion-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .promotion-preview h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .promotion-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 8px 0;
            padding: 8px;
            background: white;
            border-radius: 6px;
        }
        
        .class-from {
            padding: 4px 8px;
            background: #ffc107;
            color: #212529;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .arrow {
            color: #667eea;
            font-weight: bold;
        }
        
        .class-to {
            padding: 4px 8px;
            background: #28a745;
            color: white;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
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
        
        .promotion-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .can-promote {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .cannot-promote {
            background: #ffeaa7;
            color: #d35400;
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
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $can_promote = 0;
                    foreach ($enrolled_students as $student) {
                        if (promoteClassRoom($student['class_room'])) $can_promote++;
                    }
                    echo $can_promote;
                    ?>
                </div>
                <div class="stat-label">🎓 เลื่อนชั้นได้</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($enrolled_students) - $can_promote; ?></div>
                <div class="stat-label">⚠️ ไม่สามารถเลื่อนได้</div>
            </div>
        </div>
        
        <!-- Promotion Section -->
        <!-- <?php if (!empty($enrolled_students)): ?>
            <div class="promotion-section">
                <h3>🎓 การเลื่อนชั้น</h3>
                <p style="color: #666; margin-bottom: 15px;">
                    ระบบจะเลื่อนชั้นนักเรียนที่ลงทะเบียนในวิชานี้ไปยังชั้นปีถัดไป
                </p>
                
                <div class="promotion-preview">
                    <h4>📋 ตัวอย่างการเลื่อนชั้น:</h4>
                    <?php 
                    $preview_classes = array_unique(array_column($enrolled_students, 'class_room'));
                    foreach (array_slice($preview_classes, 0, 5) as $class):
                        $promoted_class = promoteClassRoom($class);
                    ?>
                        <div class="promotion-item">
                            <span class="class-from"><?php echo htmlspecialchars($class ?: 'ไม่ระบุ'); ?></span>
                            <span class="arrow">→</span>
                            <?php if ($promoted_class): ?>
                                <span class="class-to"><?php echo htmlspecialchars($promoted_class); ?></span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: 600;">ไม่สามารถเลื่อนได้</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($can_promote > 0): ?>
                    <div class="alert alert-warning">
                        ⚠️ การเลื่อนชั้นจะส่งผลกระทบต่อข้อมูลนักเรียน <?php echo $can_promote; ?> คน กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะเลื่อนชั้นนักเรียนทั้งหมดในรายวิชานี้?\n\nการกระทำนี้ไม่สามารถย้อนกลับได้!');">
                        <input type="hidden" name="action" value="promote_all">
                        <button type="submit" class="btn btn-success">🎓 เลื่อนชั้นนักเรียนทั้งหมด (<?php echo $can_promote; ?> คน)</button>
                        <a href="bulk-promotion.php?course_id=<?php echo $course_id; ?>" class="btn btn-warning">⚙️ จัดการเลื่อนชั้นแบบละเอียด</a>
                        <a href="course-promotion.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">📈 เลื่อนชั้นตามวิชา (Course-based)</a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        ℹ️ ไม่มีนักเรียนที่สามารถเลื่อนชั้นได้ในรายวิชานี้
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?> -->
        
        <!-- Students List -->
        <?php if (!empty($students_by_class)): ?>
            <div class="students-container">
                <?php foreach ($students_by_class as $class_name => $students): ?>
                    <div class="class-section">
                        <div class="class-header">
                            🏫 ห้อง <?php echo htmlspecialchars($class_name); ?> 
                            (<?php echo count($students); ?> คน)
                            <?php 
                            $promoted_class = promoteClassRoom($class_name);
                            if ($promoted_class): ?>
                                → เลื่อนเป็น <strong><?php echo htmlspecialchars($promoted_class); ?></strong>
                            <?php endif; ?>
                        </div>
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>เลขที่</th>
                                    <th>รหัสนักเรียน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>ชื่อผู้ใช้</th>
                                    <th>วันที่ลงทะเบียน</th>
                                    <th>สถานะการเลื่อนชั้น</th>
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
                                        <td>
                                            <?php if (promoteClassRoom($student['class_room'])): ?>
                                                <span class="promotion-badge can-promote">✅ เลื่อนชั้นได้</span>
                                            <?php else: ?>
                                                <span class="promotion-badge cannot-promote">⚠️ ไม่สามารถเลื่อนได้</span>
                                            <?php endif; ?>
                                        </td>
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
