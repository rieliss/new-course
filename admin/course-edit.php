<?php
include '../config.php';
require_admin();

$course_id = (int)($_GET['course_id'] ?? 0);
$message = '';
$message_type = 'success';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_code = trim($_POST['course_code'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $credits = (int)($_POST['credits'] ?? 0);
    $schedule_day = trim($_POST['schedule_day'] ?? '');
    $schedule_time = trim($_POST['schedule_time'] ?? '');
    $max_seats = (int)($_POST['max_seats'] ?? 30);
    $grade_level = (int)($_POST['grade_level'] ?? 4);
    $semester = (int)($_POST['semester'] ?? 1);
    $classroom = trim($_POST['classroom'] ?? '');
    $max_enrollments = (int)($_POST['max_enrollments'] ?? 999);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'open';

    // Validation
    $errors = [];
    if (empty($course_code)) $errors[] = 'กรุณากรอกรหัสวิชา';
    if (empty($course_name)) $errors[] = 'กรุณากรอกชื่อวิชา';
    if (empty($teacher_name)) $errors[] = 'กรุณากรอกชื่ออาจารย์';
    if ($credits <= 0) $errors[] = 'กรุณากรอกหน่วยกิตให้ถูกต้อง';
    if ($max_seats <= 0) $errors[] = 'กรุณากรอกจำนวนที่นั่งให้ถูกต้อง';
    if ($grade_level < 1 || $grade_level > 6) $errors[] = 'ชั้นปีต้องเป็นตั้งแต่ 1-6';
    if ($semester < 1 || $semester > 2) $errors[] = 'ภาคการศึกษาต้องเป็น 1 หรือ 2';
    if (empty($classroom)) $errors[] = 'กรุณากรอกห้องเรียน';
    if ($max_enrollments <= 0) $errors[] = 'จำนวนวิชาสูงสุดต้องมากกว่า 0';
    if (!in_array($status, ['open', 'closed'])) $errors[] = 'สถานะไม่ถูกต้อง';

    if (empty($errors)) {
        $update_query = "UPDATE courses SET course_code = ?, course_name = ?, teacher_name = ?, 
                        credits = ?, schedule_day = ?, schedule_time = ?, max_seats = ?, 
                        grade_level = ?, semester = ?, classroom = ?, max_enrollments = ?, 
                        description = ?, status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssissiiisissi", 
            $course_code, $course_name, $teacher_name, $credits, 
            $schedule_day, $schedule_time, $max_seats, $grade_level, $semester,
            $classroom, $max_enrollments, $description, $status, $course_id);
        
        if ($update_stmt->execute()) {
            log_activity($_SESSION['user_id'], 'course_edit', 
                "แก้ไขวิชา: $course_name ($course_code)", $course_id);
            $message = "✅ แก้ไขวิชาเรียบร้อยแล้ว";
            $message_type = 'success';
            
            // Refresh course data
            $course_query = "SELECT * FROM courses WHERE id = ?";
            $course_stmt = $conn->prepare($course_query);
            $course_stmt->bind_param("i", $course_id);
            $course_stmt->execute();
            $course = $course_stmt->get_result()->fetch_assoc();
            $course_stmt->close();
        } else {
            $message = "❌ เกิดข้อผิดพลาดในการแก้ไข: " . $update_stmt->error;
            $message_type = 'error';
        }
        $update_stmt->close();
    } else {
        $message = "❌ " . implode(", ", $errors);
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ แก้ไขวิชา - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        
        .navbar-brand { font-size: 24px; font-weight: 700; }
        
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
        
        .btn-back:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 { font-size: 28px; color: #333; }
        
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
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
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">📚 <?php echo SITE_NAME; ?></div>
            <a href="javascript:history.back()" class="btn-back">← ย้อนกลับ</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>✏️ แก้ไขวิชา</h2>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>📌 รหัสวิชา</label>
                        <input type="text" name="course_code" value="<?php echo htmlspecialchars($course['course_code']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>⭐ หน่วยกิต</label>
                        <input type="number" name="credits" value="<?php echo $course['credits']; ?>" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>📚 ชื่อวิชา</label>
                    <input type="text" name="course_name" value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>👨‍🏫 ชื่ออาจารย์</label>
                    <input type="text" name="teacher_name" value="<?php echo htmlspecialchars($course['teacher_name']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 วันที่เรียน</label>
                        <input type="text" name="schedule_day" value="<?php echo htmlspecialchars($course['schedule_day']); ?>" placeholder="เช่น วันจันทร์-พุธ">
                    </div>
                    <div class="form-group">
                        <label>⏰ เวลาเรียน</label>
                        <input type="text" name="schedule_time" value="<?php echo htmlspecialchars($course['schedule_time']); ?>" placeholder="เช่น 09:00-10:30">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>🪑 จำนวนที่นั่ง</label>
                        <input type="number" name="max_seats" value="<?php echo $course['max_seats']; ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>🎓 ชั้นปี</label>
                        <select name="grade_level" required>
                            <option value="4" <?php echo $course['grade_level'] == 4 ? 'selected' : ''; ?>>ม.4</option>
                            <option value="5" <?php echo $course['grade_level'] == 5 ? 'selected' : ''; ?>>ม.5</option>
                            <option value="6" <?php echo $course['grade_level'] == 6 ? 'selected' : ''; ?>>ม.6</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>📚 ภาคการศึกษา</label>
                        <select name="semester" required>
                            <option value="1" <?php echo $course['semester'] == 1 ? 'selected' : ''; ?>>ภาคที่ 1</option>
                            <option value="2" <?php echo $course['semester'] == 2 ? 'selected' : ''; ?>>ภาคที่ 2</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏫 ห้องเรียน</label>
                        <input type="text" name="classroom" value="<?php echo htmlspecialchars($course['classroom']); ?>" placeholder="เช่น ม.4/1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>📋 จำนวนวิชาสูงสุด (Block Course)</label>
                    <input type="number" name="max_enrollments" value="<?php echo $course['max_enrollments']; ?>" min="1">
                    <div class="help-text">จำนวนวิชาที่นักเรียนสามารถลงทะเบียนได้สูงสุดในภาคการศึกษา (ตั้งค่า 999 สำหรับไม่จำกัด)</div>
                </div>
                
                <div class="form-group">
                    <label>📝 คำอธิบาย</label>
                    <textarea name="description" placeholder="รายละเอียดเพิ่มเติมเกี่ยวกับวิชา..."><?php echo htmlspecialchars($course['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>🔔 สถานะ</label>
                    <select name="status" required>
                        <option value="open" <?php echo $course['status'] == 'open' ? 'selected' : ''; ?>>✅ เปิดรับสมัคร</option>
                        <option value="closed" <?php echo $course['status'] == 'closed' ? 'selected' : ''; ?>>⛔ ปิดรับสมัคร</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <a href="courses-management.php" class="btn btn-secondary">❌ ยกเลิก</a>
                    <button type="submit" class="btn btn-primary">💾 บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
