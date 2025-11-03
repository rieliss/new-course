<?php
include 'config.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_id = $_SESSION['user_id'];

// Get user's class room
$user_query = "SELECT class_room FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_class = $user_data['class_room'];
$user_stmt->close();

// Get all courses with search and class filter
$query = "SELECT c.id, c.course_code, c.course_name, c.teacher_name, c.credits, c.schedule_day, c.schedule_time, c.max_seats, c.status, c.allowed_classes,
          COUNT(e.id) as enrolled_count
          FROM courses c
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.enrollment_status = 'enrolled'
          WHERE (c.course_name LIKE ? OR c.course_code LIKE ?)
          AND (c.allowed_classes IS NULL OR c.allowed_classes = '' OR FIND_IN_SET(?, CONCAT(c.allowed_classes, ',')))
          GROUP BY c.id
          ORDER BY c.course_code";

$search_param = "%$search%";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $search_param, $search_param, $user_class);
$stmt->execute();
$courses = $stmt->get_result();

// Get enrolled courses
$enrolled_query = "SELECT course_id FROM enrollments WHERE student_id = ? AND enrollment_status = 'enrolled'";
$enrolled_stmt = $conn->prepare($enrolled_query);
$enrolled_stmt->bind_param("i", $user_id);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();
$enrolled_courses = [];
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_courses[] = $row['course_id'];
}

// Handle enrollment/unenrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $course_id = intval($_POST['course_id']);
    $action = $_POST['action'];
    
    if ($action === 'enroll') {
        $insert_query = "INSERT INTO enrollments (student_id, course_id, enrollment_status) VALUES (?, ?, 'enrolled')";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $user_id, $course_id);
        
        if ($insert_stmt->execute()) {
            log_activity($user_id, 'enroll', 'ลงทะเบียนวิชา', $course_id);
            show_message('✅ ลงทะเบียนสำเร็จ!', 'success');
        } else {
            show_message('❌ ไม่สามารถลงทะเบียนได้', 'error');
        }
        $insert_stmt->close();
    } elseif ($action === 'unenroll') {
        $delete_query = "DELETE FROM enrollments WHERE student_id = ? AND course_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("ii", $user_id, $course_id);
        
        if ($delete_stmt->execute()) {
            log_activity($user_id, 'unenroll', 'ยกเลิกการลงทะเบียน', $course_id);
            show_message('✅ ยกเลิกการลงทะเบียนสำเร็จ!', 'success');
        }
        $delete_stmt->close();
    }
    
    header("Location: courses.php" . ($search ? "?search=" . urlencode($search) : ""));
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 เลือกวิชา - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        
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
        
        .btn-back {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid white;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #333;
        }
        
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .search-bar input {
            flex: 1;
            min-width: 250px;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .search-bar button {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .search-bar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: none;
            border-left: 4px solid;
        }
        
        .alert.show {
            display: block;
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
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .course-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .course-code {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            font-weight: 700;
        }
        
        .course-name {
            font-size: 18px;
            font-weight: 700;
            margin: 8px 0;
            line-height: 1.3;
        }
        
        .course-body {
            padding: 20px;
        }
        
        .course-info {
            margin-bottom: 12px;
            font-size: 14px;
            color: #555;
        }
        
        .course-info strong {
            color: #333;
            margin-right: 10px;
        }
        
        .seats-info {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 13px;
            border-left: 4px solid #667eea;
        }
        
        .seats-available {
            color: #28a745;
            font-weight: 700;
        }
        
        .seats-full {
            color: #dc3545;
            font-weight: 700;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-enroll {
            background: #28a745;
            color: white;
        }
        
        .btn-enroll:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-unenroll {
            background: #dc3545;
            color: white;
        }
        
        .btn-unenroll:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-courses {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        
        .no-courses p {
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div style="font-size: 24px; font-weight: 700;">📚 <?php echo SITE_NAME; ?></div>
            <a href="index.php" class="btn-back">← กลับหน้าหลัก</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>📖 เลือกวิชาที่ต้องการลงทะเบียน</h2>
        </div>
        
        <div class="search-bar">
            <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                <input type="text" name="search" placeholder="🔍 ค้นหาวิชา..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔎 ค้นหา</button>
            </form>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert show alert-<?php echo $_SESSION['message_type']; ?>">
                <?php echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="courses-grid">
            <?php
            if ($courses->num_rows > 0):
                while ($course = $courses->fetch_assoc()):
                    $available_seats = $course['max_seats'] - $course['enrolled_count'];
                    $is_enrolled = in_array($course['id'], $enrolled_courses);
                    $can_enroll = $available_seats > 0 && !$is_enrolled && $course['status'] === 'open';
            ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-code"><?php echo $course['course_code']; ?></div>
                            <div class="course-name"><?php echo $course['course_name']; ?></div>
                        </div>
                        <div class="course-body">
                            <div class="course-info">
                                <strong>👨‍🏫 ครู:</strong> <?php echo $course['teacher_name']; ?>
                            </div>
                            <div class="course-info">
                                <strong>📅 วัน:</strong> <?php echo $course['schedule_day']; ?>
                            </div>
                            <div class="course-info">
                                <strong>⏰ เวลา:</strong> <?php echo $course['schedule_time']; ?>
                            </div>
                            <div class="course-info">
                                <strong>⭐ หน่วยกิต:</strong> <?php echo $course['credits']; ?>
                            </div>
                            
                            <div class="seats-info">
                                <?php if ($available_seats > 0): ?>
                                    <span class="seats-available">✅ มี <?php echo $available_seats; ?> ที่เหลือ</span>
                                <?php else: ?>
                                    <span class="seats-full">❌ ไม่มีที่ว่าง</span>
                                <?php endif; ?>
                                <br><small>(ลงทะเบียนแล้ว <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_seats']; ?>)</small>
                            </div>
                            
                            <div class="action-buttons">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <?php if ($is_enrolled): ?>
                                        <button type="submit" name="action" value="unenroll" class="btn btn-unenroll">ยกเลิกการลงทะเบียน</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="enroll" class="btn btn-enroll" <?php echo $can_enroll ? '' : 'disabled'; ?>>
                                            <?php echo $can_enroll ? 'ลงทะเบียน' : ($course['status'] === 'closed' ? 'ปิดลงทะเบียน' : 'เต็ม'); ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
            <?php 
                endwhile;
            else:
            ?>
                <div class="no-courses" style="grid-column: 1 / -1;">
                    <p>😕 ไม่พบวิชาที่ตรงกับการค้นหา</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
