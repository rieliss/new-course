<?php
include '../config.php';
require_admin();

$message = '';
$message_type = '';

// Get current config
$config_query = "SELECT config_key, config_value FROM admin_config";
$config_stmt = $conn->prepare($config_query);
$config_stmt->execute();
$config_result = $config_stmt->get_result();
$config = [];
while ($row = $config_result->fetch_assoc()) {
    $config[$row['config_key']] = $row['config_value'];
}
$config_stmt->close();

$current_year = $config['current_academic_year'] ?? 2024;
$current_semester = $config['current_semester'] ?? 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_year = intval($_POST['academic_year'] ?? 2024);
    $new_semester = intval($_POST['semester'] ?? 1);
    
    if ($new_semester < 1 || $new_semester > 2) {
        $message = '❌ ภาคการศึกษาต้องเป็น 1 หรือ 2';
        $message_type = 'error';
    } else {
        // Update academic year
        $update_query = "INSERT INTO admin_config (config_key, config_value) VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE config_value = ?";
        $update_stmt = $conn->prepare($update_query);
        
        // Update year
        $update_stmt->bind_param("sss", $key1, $value1, $value1);
        $key1 = 'current_academic_year';
        $value1 = (string)$new_year;
        $update_stmt->execute();
        
        // Update semester
        $key1 = 'current_semester';
        $value1 = (string)$new_semester;
        $update_stmt->execute();
        $update_stmt->close();
        
        $message = '✅ บันทึกการตั้งค่าสำเร็จ!';
        $message_type = 'success';
        
        // Refresh config values
        $current_year = $new_year;
        $current_semester = $new_semester;
        
        log_activity($_SESSION['user_id'], 'config_update', 'อัปเดตการตั้งค่าภาคการศึกษา', null);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ การตั้งค่าระบบ - <?php echo SITE_NAME; ?></title>
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
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #333;
        }
        
        .config-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
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
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .btn-submit,
        .btn-cancel {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-cancel:hover {
            background: #d0d0d0;
        }
        
        .info-box {
            background: #ecfdf5;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
            margin-top: 20px;
            color: #065f46;
            font-size: 13px;
        }
        
        .info-box strong {
            color: #065f46;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .config-form {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-submit,
            .btn-cancel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div style="font-size: 24px; font-weight: 700;">⚙️ <?php echo SITE_NAME; ?></div>
            <a href="dashboard.php" class="btn-back">← กลับ</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>⚙️ การตั้งค่าระบบ</h2>
            <p style="color: #666; margin-top: 5px;">จัดการภาคการศึกษาและปีการศึกษา</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="config-form">
            <form method="POST">
                <div class="form-group">
                    <label for="academic_year">📅 ปีการศึกษา</label>
                    <input type="number" id="academic_year" name="academic_year" value="<?php echo $current_year; ?>" min="2020" max="2099" required>
                    <small style="color: #666;">ตัวอย่าง: 2024</small>
                </div>
                
                <div class="form-group">
                    <label for="semester">📚 ภาคการศึกษา</label>
                    <select id="semester" name="semester" required>
                        <option value="1" <?php echo $current_semester == 1 ? 'selected' : ''; ?>>ภาคที่ 1</option>
                        <option value="2" <?php echo $current_semester == 2 ? 'selected' : ''; ?>>ภาคที่ 2</option>
                    </select>
                </div>
                
                <div class="alert alert-info">
                    ℹ️ <strong>ผลกระทบ:</strong> การเปลี่ยนแปลงการตั้งค่านี้จะส่งผลให้นักเรียนเห็นวิชาที่สอดคล้องกับภาคการศึกษาที่ระบุ และการลงทะเบียนใหม่จะบันทึกข้อมูลภาคการศึกษาที่ระบุนี้
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">💾 บันทึกการตั้งค่า</button>
                    <a href="dashboard.php" class="btn-cancel">ยกเลิก</a>
                </div>
            </form>
            
            <div class="info-box">
                <strong>📊 สถานะปัจจุบัน:</strong><br>
                ปีการศึกษา: <strong><?php echo $current_year; ?></strong><br>
                ภาคการศึกษา: <strong>ภาคที่ <?php echo $current_semester; ?></strong><br>
                เวลาอัปเดตล่าสุด: <strong><?php echo date('d/m/Y H:i:s'); ?></strong>
            </div>
        </div>
    </div>
</body>
</html>
