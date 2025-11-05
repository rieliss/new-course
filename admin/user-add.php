<?php
include '../config.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $class_room = trim($_POST['class_room'] ?? '');
    $class_number = $_POST['class_number'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($student_id) || empty($username) || empty($password) || empty($full_name)) {
        $error = '❌ กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif ($password !== $confirm_password) {
        $error = '❌ รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 5) {
        $error = '❌ รหัสผ่านต้องมีอย่างน้อย 5 ตัวอักษร';
    } else {
        // Check if student_id or username already exists
        $check_query = "SELECT id FROM users WHERE student_id = ? OR username = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $student_id, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = '❌ รหัสนักเรียนหรือชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (student_id, username, password, full_name, class_room, class_number, email, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssssisss", $student_id, $username, $hashed_password, $full_name, $class_room, $class_number, $email, $role, $status);
            
            if ($insert_stmt->execute()) {
                $new_user_id = $conn->insert_id;
                log_activity($_SESSION['user_id'], 'user_create', "สร้างผู้ใช้ใหม่: $full_name", $new_user_id);
                $success = '✅ เพิ่มผู้ใช้เรียบร้อยแล้ว';
                
                // Clear form data
                $student_id = $username = $full_name = $class_room = $class_number = $email = '';
                $role = 'student';
                $status = 'active';
            } else {
                $error = '❌ เกิดข้อผิดพลาดในการเพิ่มผู้ใช้: ' . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ เพิ่มผู้ใช้ - <?php echo SITE_NAME; ?></title>
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
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .required {
            color: #dc3545;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover { background: #5a6268; }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-actions {
                flex-direction: column;
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
            <h2>➕ เพิ่มผู้ใช้ใหม่</h2>
            <a href="users-management.php" class="btn-back">← กลับหน้าจัดการผู้ใช้</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label for="student_id">🆔 รหัสนักเรียน <span class="required">*</span></label>
                            <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($student_id ?? ''); ?>" required>
                            <small>รหัสนักเรียนต้องไม่ซ้ำกัน</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">👤 ชื่อผู้ใช้ <span class="required">*</span></label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                            <small>ชื่อผู้ใช้สำหรับเข้าสู่ระบบ</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">🔐 รหัสผ่าน <span class="required">*</span></label>
                            <input type="password" id="password" name="password" required>
                            <small>รหัสผ่านต้องมีอย่างน้อย 5 ตัวอักษร</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">🔐 ยืนยันรหัสผ่าน <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <small>กรอกรหัสผ่านอีกครั้งเพื่อยืนยัน</small>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="full_name">📝 ชื่อเต็ม <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                            <small>ชื่อจริงของผู้ใช้</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="class_room">🏫 ห้องเรียน</label>
                            <input type="text" id="class_room" name="class_room" value="<?php echo htmlspecialchars($class_room ?? ''); ?>" placeholder="เช่น ม.4/1">
                            <small>ห้องเรียนที่สังกัด (สำหรับนักเรียน)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="class_number">🔢 เลขที่</label>
                            <input type="number" id="class_number" name="class_number" value="<?php echo htmlspecialchars($class_number ?? ''); ?>" min="1" max="99">
                            <small>เลขที่ในห้องเรียน</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">📧 อีเมล</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                            <small>อีเมลสำหรับติดต่อ (ไม่บังคับ)</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="role">👨‍💼 สิทธิ์ผู้ใช้</label>
                        <select id="role" name="role">
                            <option value="student" <?php echo ($role ?? 'student') == 'student' ? 'selected' : ''; ?>>👨‍🎓 นักเรียน</option>
                            <option value="admin" <?php echo ($role ?? '') == 'admin' ? 'selected' : ''; ?>>👨‍💼 ผู้ดูแลระบบ</option>
                        </select>
                        <small>กำหนดสิทธิ์การใช้งานระบบ</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">📊 สถานะบัญชี</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($status ?? 'active') == 'active' ? 'selected' : ''; ?>>✅ ใช้งานอยู่</option>
                            <option value="inactive" <?php echo ($status ?? '') == 'inactive' ? 'selected' : ''; ?>>⛔ ปิดใช้งาน</option>
                        </select>
                        <small>สถานะการใช้งานบัญชี</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">✅ เพิ่มผู้ใช้</button>
                    <a href="users-management.php" class="btn btn-secondary">❌ ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('รหัสผ่านไม่ตรงกัน');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Generate username from student_id
        document.getElementById('student_id').addEventListener('input', function() {
            const studentId = this.value.toLowerCase();
            const usernameField = document.getElementById('username');
            
            if (usernameField.value === '' && studentId !== '') {
                usernameField.value = studentId;
            }
        });
    </script>
</body>
</html>
