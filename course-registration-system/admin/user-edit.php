<?php
include '../config.php';
require_admin();

$user_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($user_id <= 0) {
    header('Location: users-management.php');
    exit();
}

// Fetch user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: users-management.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Prevent editing main admin
if ($user['id'] == 1 && $user['role'] == 'admin') {
    $is_main_admin = true;
} else {
    $is_main_admin = false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $class_room = trim($_POST['class_room'] ?? '');
    $class_number = $_POST['class_number'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $status = $_POST['status'] ?? 'active';
    $change_password = isset($_POST['change_password']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($student_id) || empty($username) || empty($full_name)) {
        $error = '❌ กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif ($change_password && (empty($new_password) || strlen($new_password) < 6)) {
        $error = '❌ รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($change_password && $new_password !== $confirm_password) {
        $error = '❌ รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        // Check if student_id or username already exists (except current user)
        $check_query = "SELECT id FROM users WHERE (student_id = ? OR username = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ssi", $student_id, $username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = '❌ รหัสนักเรียนหรือชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
        } else {
            // Prevent changing main admin role
            if ($is_main_admin && $role !== 'admin') {
                $error = '❌ ไม่สามารถเปลี่ยนสิทธิ์ของ admin หลักได้';
            } else {
                // Update user data
                if ($change_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET student_id = ?, username = ?, password = ?, full_name = ?, class_room = ?, class_number = ?, email = ?, role = ?, status = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("sssssisssi", $student_id, $username, $hashed_password, $full_name, $class_room, $class_number, $email, $role, $status, $user_id);
                } else {
                    $update_query = "UPDATE users SET student_id = ?, username = ?, full_name = ?, class_room = ?, class_number = ?, email = ?, role = ?, status = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ssssisssi", $student_id, $username, $full_name, $class_room, $class_number, $email, $role, $status, $user_id);
                }
                
                if ($update_stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'user_update', "แก้ไขข้อมูลผู้ใช้: $full_name", $user_id);
                    $success = '✅ แก้ไขข้อมูลผู้ใช้เรียบร้อยแล้ว';
                    
                    // Refresh user data
                    $user['student_id'] = $student_id;
                    $user['username'] = $username;
                    $user['full_name'] = $full_name;
                    $user['class_room'] = $class_room;
                    $user['class_number'] = $class_number;
                    $user['email'] = $email;
                    $user['role'] = $role;
                    $user['status'] = $status;
                } else {
                    $error = '❌ เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $conn->error;
                }
                $update_stmt->close();
            }
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
    <title>✏️ แก้ไขผู้ใช้ - <?php echo SITE_NAME; ?></title>
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
        
        .user-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .user-info-card h3 {
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .user-info-card p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
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
        
        .form-group input:disabled {
            background: #f8f9fa;
            color: #6c757d;
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
        
        .password-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .password-section h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .password-fields {
            display: none;
        }
        
        .password-fields.show {
            display: block;
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
            <h2>✏️ แก้ไขผู้ใช้</h2>
            <a href="users-management.php" class="btn-back">← กลับหน้าจัดการผู้ใช้</a>
        </div>
        
        <div class="user-info-card">
            <h3>📝 ข้อมูลผู้ใช้ปัจจุบัน</h3>
            <p><strong>ID:</strong> <?php echo $user['id']; ?></p>
            <p><strong>รหัสนักเรียน:</strong> <?php echo htmlspecialchars($user['student_id']); ?></p>
            <p><strong>ชื่อผู้ใช้:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>ชื่อเต็ม:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
            <p><strong>สิทธิ์:</strong> <?php echo $user['role'] == 'admin' ? '👨‍💼 ผู้ดูแลระบบ' : '👨‍🎓 นักเรียน'; ?></p>
            <p><strong>สถานะ:</strong> <?php echo $user['status'] == 'active' ? '✅ ใช้งานอยู่' : '⛔ ปิดใช้งาน'; ?></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($is_main_admin): ?>
            <div class="alert alert-warning">
                ⚠️ นี่คือบัญชี admin หลัก ไม่สามารถเปลี่ยนสิทธิ์ได้
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label for="student_id">🆔 รหัสนักเรียน <span class="required">*</span></label>
                            <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($user['student_id']); ?>" required>
                            <small>รหัสนักเรียนต้องไม่ซ้ำกัน</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">👤 ชื่อผู้ใช้ <span class="required">*</span></label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            <small>ชื่อผู้ใช้สำหรับเข้าสู่ระบบ</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">📝 ชื่อเต็ม <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            <small>ชื่อจริงของผู้ใช้</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">📧 อีเมล</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                            <small>อีเมลสำหรับติดต่อ</small>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="class_room">🏫 ห้องเรียน</label>
                            <input type="text" id="class_room" name="class_room" value="<?php echo htmlspecialchars($user['class_room']); ?>" placeholder="เช่น ม.4/1">
                            <small>ห้องเรียนที่สังกัด (สำหรับนักเรียน)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="class_number">🔢 เลขที่</label>
                            <input type="number" id="class_number" name="class_number" value="<?php echo htmlspecialchars($user['class_number']); ?>" min="1" max="99">
                            <small>เลขที่ในห้องเรียน</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">👨‍💼 สิทธิ์ผู้ใช้</label>
                            <select id="role" name="role" <?php echo $is_main_admin ? 'disabled' : ''; ?>>
                                <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>👨‍🎓 นักเรียน</option>
                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>👨‍💼 ผู้ดูแลระบบ</option>
                            </select>
                            <?php if ($is_main_admin): ?>
                                <input type="hidden" name="role" value="admin">
                            <?php endif; ?>
                            <small>กำหนดสิทธิ์การใช้งานระบบ</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">📊 สถานะบัญชี</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>✅ ใช้งานอยู่</option>
                                <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>⛔ ปิดใช้งาน</option>
                            </select>
                            <small>สถานะการใช้งานบัญชี</small>
                        </div>
                    </div>
                </div>
                
                <div class="password-section">
                    <h4>🔐 เปลี่ยนรหัสผ่าน</h4>
                    <div class="checkbox-group">
                        <input type="checkbox" id="change_password" name="change_password">
                        <label for="change_password">ต้องการเปลี่ยนรหัสผ่าน</label>
                    </div>
                    
                    <div class="password-fields" id="password-fields">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="new_password">🔐 รหัสผ่านใหม่</label>
                                <input type="password" id="new_password" name="new_password">
                                <small>รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">🔐 ยืนยันรหัสผ่านใหม่</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                                <small>กรอกรหัสผ่านใหม่อีกครั้งเพื่อยืนยัน</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">✅ บันทึกการแก้ไข</button>
                    <a href="users-management.php" class="btn btn-secondary">❌ ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password fields
        document.getElementById('change_password').addEventListener('change', function() {
            const passwordFields = document.getElementById('password-fields');
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            if (this.checked) {
                passwordFields.classList.add('show');
                newPasswordField.required = true;
                confirmPasswordField.required = true;
            } else {
                passwordFields.classList.remove('show');
                newPasswordField.required = false;
                confirmPasswordField.required = false;
                newPasswordField.value = '';
                confirmPasswordField.value = '';
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('รหัสผ่านไม่ตรงกัน');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
