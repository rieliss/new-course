<?php
include '../config.php';
require_admin();

// Include progression functions
include '../functions/course-progression.php';

$course_id = (int)($_GET['course_id'] ?? 0);
$current_academic_year = getCurrentAcademicYear();
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

// Handle course-based promotion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'promote_for_course') {
        $student_ids = $_POST['student_ids'] ?? [];
        
        if (!empty($student_ids)) {
            $promoted_count = 0;
            $failed_count = 0;
            
            foreach ($student_ids as $student_id) {
                $student_id = (int)$student_id;
                
                // Get current enrollment info before promotion
                $current_enroll = "SELECT e.id, e.enrollment_status, u.class_room, u.full_name 
                                 FROM enrollments e
                                 JOIN users u ON e.student_id = u.id
                                 WHERE e.student_id = ? AND e.course_id = ? 
                                 AND e.visibility_status = 'current'";
                $ce_stmt = $conn->prepare($current_enroll);
                $ce_stmt->bind_param("ii", $student_id, $course_id);
                $ce_stmt->execute();
                $ce_result = $ce_stmt->get_result();
                
                if ($ce_result->num_rows > 0) {
                    $student_info = $ce_result->fetch_assoc();
                    $old_class = $student_info['class_room'];
                    
                    // Perform promotion
                    if (promoteStudentForCourse($conn, $student_id, $course_id, getCurrentAcademicYear())) {
                        $promoted_count++;
                        
                        // Get new class after promotion
                        $new_class_query = "SELECT class_room FROM users WHERE id = ?";
                        $nc_stmt = $conn->prepare($new_class_query);
                        $nc_stmt->bind_param("i", $student_id);
                        $nc_stmt->execute();
                        $new_class_result = $nc_stmt->get_result();
                        $new_class_row = $new_class_result->fetch_assoc();
                        $new_class = $new_class_row['class_room'];
                        $nc_stmt->close();
                        
                        log_activity(
                            $_SESSION['user_id'], 
                            'course_promotion', 
                            "เลื่อนชั้นวิชา: {$student_info['full_name']} ในวิชา {$course['course_name']} จาก {$old_class} → {$new_class}",
                            $student_id
                        );
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
                
                $ce_stmt->close();
            }
            
            if ($promoted_count > 0) {
                $success = "✅ เลื่อนชั้นนักเรียนเรียบร้อยแล้ว จำนวน $promoted_count คน";
                
                if ($failed_count > 0) {
                    $success .= " (ไม่สำเร็จ $failed_count คน)";
                }
            } else {
                $error = "❌ ไม่สามารถเลื่อนชั้นนักเรียนคนใดเลย";
            }
        } else {
            $error = "❌ กรุณาเลือกนักเรียนอย่างน้อย 1 คน";
        }
    }
}

// Get course enrollments
$enrollments = getCourseEnrollments($conn, $course_id, $course['academic_year']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลื่อนชั้นตามวิชา - <?php echo SITE_NAME; ?></title>
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
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #333;
        }
        
        .course-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .course-info h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .course-info p {
            color: #666;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .checkbox-cell {
            width: 40px;
        }
        
        .checkbox-cell input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .action-buttons {
            padding: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        
        .empty-state p {
            font-size: 18px;
            color: #666;
        }
        
        .checkbox-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .select-count {
            font-size: 12px;
            color: #666;
            margin-left: 10px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            table {
                font-size: 13px;
            }
            
            th, td {
                padding: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div style="font-size: 24px; font-weight: 700;">📚 <?php echo SITE_NAME; ?></div>
            <a href="course-students.php?course_id=<?php echo $course_id; ?>" class="btn-back">← กลับ</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>📈 เลื่อนชั้นตามวิชา</h2>
        </div>
        
        <div class="course-info">
            <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
            <p><strong>รหัสวิชา:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
            <p><strong>ครูผู้สอน:</strong> <?php echo htmlspecialchars($course['teacher_name']); ?></p>
            <p><strong>ปีการศึกษา:</strong> <?php echo $course['academic_year']; ?></p>
            <p><strong>ห้องเรียน:</strong> <?php echo htmlspecialchars($course['allowed_classes'] ?? '--'); ?></p>
        </div>
        
        <?php if ($success): ?>
            <div class="message success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="warning-box">
            <strong>⚠️ สำคัญ:</strong> เมื่อคุณเลื่อนชั้นนักเรียน ระบบจะ:<br>
            1) ตั้งค่าการลงทะเบียนปีนี้เป็น "สำเร็จ"<br>
            2) เปิดใช้งานการลงทะเบียนปีต่อไป (ก่อนหน้านี้ซ่อนอยู่)<br>
            3) อัปเดตห้องเรียนของนักเรียน<br>
            ⚠️ การกระทำนี้ไม่สามารถย้อนกลับได้!
        </div>
        
        <?php if ($enrollments->num_rows > 0): ?>
            <form method="POST" class="table-container" id="promotion-form">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>รหัสนักเรียน</th>
                            <th>ชื่อเต็ม</th>
                            <th>ห้องเรียนปัจจุบัน</th>
                            <th>ห้องเรียนถัดไป</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $enrollments->fetch_assoc()): 
                            $next_class = getNextClassRoom($row['class_room']);
                        ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="student_ids[]" value="<?php echo $row['id']; ?>" 
                                           class="student-checkbox" onchange="updateSelectCount()">
                                </td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['class_room']); ?></td>
                                <td>
                                    <strong>
                                        <?php 
                                        if ($next_class) {
                                            echo htmlspecialchars($next_class);
                                        } else {
                                            echo '<span style="color: #dc3545;">จบการศึกษา</span>';
                                        }
                                        ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="action-buttons">
                    <span class="select-count">
                        เลือก: <strong id="count">0</strong> / <?php echo $enrollments->num_rows; ?> คน
                    </span>
                    <input type="hidden" name="action" value="promote_for_course">
                    <button type="button" onclick="document.location='course-students.php?course_id=<?php echo $course_id; ?>'" 
                            class="btn btn-cancel">
                        ❌ ยกเลิก
                    </button>
                    <button type="submit" class="btn btn-primary" id="promote-btn" disabled>
                        🚀 เลื่อนชั้นที่เลือก
                    </button>
                </div>
            </form>
            
            <script>
                function toggleAllCheckboxes(source) {
                    const checkboxes = document.querySelectorAll('.student-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = source.checked;
                    });
                    updateSelectCount();
                }
                
                function updateSelectCount() {
                    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
                    const count = checkboxes.length;
                    document.getElementById('count').textContent = count;
                    document.getElementById('promote-btn').disabled = count === 0;
                    
                    // Update select-all checkbox state
                    const selectAllCheckbox = document.getElementById('select-all');
                    const allCheckboxes = document.querySelectorAll('.student-checkbox');
                    selectAllCheckbox.checked = allCheckboxes.length === checkboxes.length;
                }
                
                // Add confirmation before submitting
                document.getElementById('promotion-form').onsubmit = function(e) {
                    const count = document.querySelectorAll('.student-checkbox:checked').length;
                    return confirm(`คุณแน่ใจหรือไม่ว่าต้องการเลื่อนชั้นให้กับ ${count} คน? การกระทำนี้ไม่สามารถย้อนกลับได้`);
                };
            </script>
        <?php else: ?>
            <div class="empty-state">
                <p>😕 ไม่มีนักเรียนลงเรียนวิชานี้</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
