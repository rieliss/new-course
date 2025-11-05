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

// Handle bulk promotion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'bulk_promote') {
        $student_ids = $_POST['student_ids'] ?? [];
        $new_classes = $_POST['new_classes'] ?? [];
        
        if (!empty($student_ids) && !empty($new_classes)) {
            $promoted_count = 0;
            $conn->begin_transaction();
            
            try {
                foreach ($student_ids as $student_id) {
                    $student_id = (int)$student_id;
                    $new_class = trim($new_classes[$student_id] ?? '');
                    
                    if (!empty($new_class)) {
                        // Get student info for logging
                        $student_query = "SELECT full_name, class_room FROM users WHERE id = ?";
                        $student_stmt = $conn->prepare($student_query);
                        $student_stmt->bind_param("i", $student_id);
                        $student_stmt->execute();
                        $student_result = $student_stmt->get_result();
                        $student_info = $student_result->fetch_assoc();
                        $student_stmt->close();
                        
                        // Update class room
                        $update_query = "UPDATE users SET class_room = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("si", $new_class, $student_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        $promoted_count++;
                        
                        log_activity($_SESSION['user_id'], 'student_promotion', 
                            "เลื่อนชั้นแบบเลือก: {$student_info['full_name']} จาก {$student_info['class_room']} เป็น $new_class", 
                            $student_id);
                    }
                }
                
                $conn->commit();
                $success = "✅ เลื่อนชั้นนักเรียนเรียบร้อยแล้ว จำนวน $promoted_count คน";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "❌ เกิดข้อผิดพลาดในการเลื่อนชั้น: " . $e->getMessage();
            }
        } else {
            $error = "❌ กรุณาเลือกนักเรียนและระบุห้องเรียนใหม่";
        }
    }
}

// Function to promote class room
function promoteClassRoom($current_class) {
    if (empty($current_class)) return '';
    
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
            return $current_class; // คงเดิม
        }
        // เลื่อนชั้นปกติ
        else {
            $new_level = $current_level + 1;
            return "$level_type.$new_level/$section";
        }
    }
    
    return $current_class; // คงเดิม
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ จัดการเลื่อนชั้นแบบละเอียด - <?php echo SITE_NAME; ?></title>
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
            margin-bottom: 10px;
        }
        
        .controls-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .controls-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover { background: #5a6268; }
        
        .students-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
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
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .students-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }
        
        .students-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .class-input {
            width: 120px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .class-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .class-current {
            padding: 4px 8px;
            background: #ffc107;
            color: #212529;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .checkbox-cell {
            text-align: center;
        }
        
        .checkbox-cell input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        .form-actions {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .selected-info {
            color: #666;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .students-table {
                font-size: 12px;
            }
            
            .students-table th,
            .students-table td {
                padding: 8px;
            }
            
            .class-input {
                width: 100px;
            }
            
            .form-actions {
                flex-direction: column;
                align-items: stretch;
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
            <h2>⚙️ จัดการเลื่อนชั้นแบบละเอียด</h2>
            <a href="course-students.php?course_id=<?php echo $course_id; ?>" class="btn-back">← กลับหน้านักเรียนในรายวิชา</a>
        </div>
        
        <div class="course-info-card">
            <h3>📖 <?php echo htmlspecialchars($course['course_name']); ?></h3>
            <p>รหัสวิชา: <?php echo htmlspecialchars($course['course_code']); ?> | 
            อาจารย์: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            💡 <strong>คำแนะนำ:</strong> เลือกนักเรียนที่ต้องการเลื่อนชั้น แล้วปรับแก้ไขห้องเรียนใหม่ตามต้องการ 
            คุณสามารถแก้ไขห้องเรียนได้อย่างอิสระ หรือใช้ปุ่ม "🎯 เติมห้องที่แนะนำ" เพื่อให้ระบบเติมอัตโนมัติ
        </div>
        
        <div class="controls-section">
            <div class="controls-row">
                <button type="button" onclick="selectAll()" class="btn btn-primary">☑️ เลือกทั้งหมด</button>
                <button type="button" onclick="selectNone()" class="btn btn-secondary">☐ ยกเลิกการเลือก</button>
                <button type="button" onclick="fillSuggestedClasses()" class="btn btn-primary">🎯 เติมห้องที่แนะนำ</button>
                <button type="button" onclick="clearAllClasses()" class="btn btn-secondary">🧹 ล้างทั้งหมด</button>
            </div>
        </div>
        
        <?php if (!empty($enrolled_students)): ?>
            <form method="POST" id="promotionForm">
                <input type="hidden" name="action" value="bulk_promote">
                
                <div class="students-table-container">
                    <div class="table-header">
                        👨‍🎓 รายชื่อนักเรียน (<?php echo count($enrolled_students); ?> คน)
                    </div>
                    
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                </th>
                                <th>รหัสนักเรียน</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ห้องปัจจุบัน</th>
                                <th>ห้องใหม่</th>
                                <th>ห้องที่แนะนำ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolled_students as $student): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" 
                                               name="student_ids[]" 
                                               value="<?php echo $student['id']; ?>" 
                                               class="student-checkbox"
                                               onchange="updateSelectedCount()">
                                    </td>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td>
                                        <span class="class-current">
                                            <?php echo htmlspecialchars($student['class_room'] ?: 'ไม่ระบุ'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="new_classes[<?php echo $student['id']; ?>]" 
                                               class="class-input new-class-input"
                                               placeholder="ห้องใหม่"
                                               data-student-id="<?php echo $student['id']; ?>">
                                    </td>
                                    <td style="color: #28a745; font-weight: 600;">
                                        <?php 
                                        $suggested = promoteClassRoom($student['class_room']);
                                        echo htmlspecialchars($suggested ?: 'ไม่มีคำแนะนำ');
                                        ?>
                                        <input type="hidden" 
                                               class="suggested-class" 
                                               data-student-id="<?php echo $student['id']; ?>" 
                                               value="<?php echo htmlspecialchars($suggested); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="form-actions">
                        <div class="selected-info">
                            เลือกแล้ว: <span id="selectedCount">0</span> คน
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success" onclick="return confirmPromotion()">
                                🎓 เลื่อนชั้นนักเรียนที่เลือก
                            </button>
                            <a href="course-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-secondary">
                                ❌ ยกเลิก
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="students-table-container">
                <div style="text-align: center; padding: 40px; color: #666;">
                    ไม่มีนักเรียนลงทะเบียนในรายวิชานี้
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            checkboxes.forEach(cb => {
                cb.checked = true;
            });
            selectAllCheckbox.checked = true;
            updateSelectedCount();
        }
        
        function selectNone() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
            });
            selectAllCheckbox.checked = false;
            updateSelectedCount();
        }
        
        function fillSuggestedClasses() {
            const inputs = document.querySelectorAll('.new-class-input');
            const suggestedInputs = document.querySelectorAll('.suggested-class');
            
            suggestedInputs.forEach(suggested => {
                const studentId = suggested.dataset.studentId;
                const classInput = document.querySelector(`input[name="new_classes[${studentId}]"]`);
                if (classInput && suggested.value) {
                    classInput.value = suggested.value;
                }
            });
        }
        
        function clearAllClasses() {
            const inputs = document.querySelectorAll('.new-class-input');
            inputs.forEach(input => {
                input.value = '';
            });
        }
        
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            document.getElementById('selectedCount').textContent = checkedBoxes.length;
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.student-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            if (checkedBoxes.length === allCheckboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedBoxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }
        
        function confirmPromotion() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('❌ กรุณาเลือกนักเรียนที่ต้องการเลื่อนชั้น');
                return false;
            }
            
            // Check if selected students have new class rooms
            let hasNewClasses = false;
            checkedBoxes.forEach(checkbox => {
                const studentId = checkbox.value;
                const classInput = document.querySelector(`input[name="new_classes[${studentId}]"]`);
                if (classInput && classInput.value.trim()) {
                    hasNewClasses = true;
                }
            });
            
            if (!hasNewClasses) {
                alert('❌ กรุณาระบุห้องเรียนใหม่สำหรับนักเรียนที่เลือก');
                return false;
            }
            
            return confirm(`⚠️ คุณแน่ใจหรือไม่ที่จะเลื่อนชั้นนักเรียน ${checkedBoxes.length} คน?\n\nการกระทำนี้ไม่สามารถย้อนกลับได้!`);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            
            // Add change listeners to all checkboxes
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
        });
    </script>
</body>
</html>
