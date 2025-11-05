<?php
include '../config.php';
require_admin();

$message = '';
$message_type = 'success';

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
        $insert_query = "INSERT INTO courses (course_code, course_name, teacher_name, credits, 
                        schedule_day, schedule_time, max_seats, grade_level, semester, classroom, 
                        max_enrollments, description, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("sssissiisiss", 
            $course_code, $course_name, $teacher_name, $credits, 
            $schedule_day, $schedule_time, $max_seats, $grade_level, $semester, 
            $classroom, $max_enrollments, $description, $status);
        
        if ($insert_stmt->execute()) {
            log_activity($_SESSION['user_id'], 'course_add', 
                "เพิ่มวิชาใหม่: $course_name ($course_code)", $conn->insert_id);
            $message = "✅ เพิ่มวิชาเรียบร้อยแล้ว!";
            $message_type = 'success';
            
            // Reset form
            $course_code = $course_name = $teacher_name = $schedule_day = $schedule_time = '';
            $classroom = $description = '';
            $credits = 3;
            $max_seats = 30;
            $grade_level = 4;
            $semester = 1;
            $max_enrollments = 999;
            $status = 'open';
        } else {
            $message = "❌ เกิดข้อผิดพลาด: " . $insert_stmt->error;
            $message_type = 'error';
        }
        $insert_stmt->close();
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
    <title>➕ เพิ่มวิชาใหม่ - <?php echo SITE_NAME; ?></title>
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
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
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
            <a href="courses-management.php" class="btn-back">← กลับจัดการวิชา</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>➕ เพิ่มวิชาใหม่</h2>
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
                        <input type="text" name="course_code" value="<?php echo htmlspecialchars($course_code ?? ''); ?>" placeholder="เช่น CS101" required>
                    </div>
                    <div class="form-group">
                        <label>⭐ หน่วยกิต</label>
                        <input type="number" name="credits" value="<?php echo $credits ?? 3; ?>" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>📚 ชื่อวิชา</label>
                    <input type="text" name="course_name" value="<?php echo htmlspecialchars($course_name ?? ''); ?>" placeholder="เช่น วิทยาการคำนวณเบื้องต้น" required>
                </div>
                
                <div class="form-group">
                    <label>👨‍🏫 ชื่ออาจารย์</label>
                    <input type="text" name="teacher_name" value="<?php echo htmlspecialchars($teacher_name ?? ''); ?>" placeholder="เช่น อ.สมชาย" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 วันที่เรียน</label>
                        <input type="text" name="schedule_day" value="<?php echo htmlspecialchars($schedule_day ?? ''); ?>" placeholder="เช่น วันจันทร์-พุธ">
                    </div>
                    <div class="form-group">
                        <label>⏰ เวลาเรียน</label>
                        <input type="text" name="schedule_time" value="<?php echo htmlspecialchars($schedule_time ?? ''); ?>" placeholder="เช่น 09:00-10:30">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>🎓 ชั้นปี</label>
                        <select name="grade_level" required>
                            <option value="4" <?php echo ($grade_level ?? 4) == 4 ? 'selected' : ''; ?>>ม.4</option>
                            <option value="5" <?php echo ($grade_level ?? 4) == 5 ? 'selected' : ''; ?>>ม.5</option>
                            <option value="6" <?php echo ($grade_level ?? 4) == 6 ? 'selected' : ''; ?>>ม.6</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📚 ภาคการศึกษา</label>
                        <select name="semester" required>
                            <option value="1" <?php echo ($semester ?? 1) == 1 ? 'selected' : ''; ?>>ภาคที่ 1</option>
                            <option value="2" <?php echo ($semester ?? 1) == 2 ? 'selected' : ''; ?>>ภาคที่ 2</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>🏫 ห้องเรียน</label>
                        <input type="text" name="classroom" value="<?php echo htmlspecialchars($classroom ?? ''); ?>" placeholder="เช่น ม.4/1" required>
                    </div>
                    <div class="form-group">
                        <label>🪑 จำนวนที่นั่ง</label>
                        <input type="number" name="max_seats" value="<?php echo $max_seats ?? 30; ?>" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>📋 จำนวนวิชาสูงสุด (Block Course)</label>
                    <input type="number" name="max_enrollments" value="<?php echo $max_enrollments ?? 999; ?>" min="1">
                    <div class="help-text">จำนวนวิชาที่นักเรียนสามารถลงทะเบียนได้สูงสุดในภาคการศึกษา (ตั้งค่า 999 สำหรับไม่จำกัด)</div>
                </div>
                
                <div class="form-group">
                    <label>📝 คำอธิบาย</label>
                    <textarea name="description" placeholder="รายละเอียดเพิ่มเติมเกี่ยวกับวิชา..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>🔔 สถานะ</label>
                    <select name="status" required>
                        <option value="open" <?php echo ($status ?? 'open') == 'open' ? 'selected' : ''; ?>>✅ เปิดรับสมัคร</option>
                        <option value="closed" <?php echo ($status ?? 'open') == 'closed' ? 'selected' : ''; ?>>⛔ ปิดรับสมัคร</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <a href="courses-management.php" class="btn btn-secondary">❌ ยกเลิก</a>
                    <button type="submit" class="btn btn-primary">✅ สร้างวิชา</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
