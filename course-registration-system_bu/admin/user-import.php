<?php
include '../config.php';
require_admin();

$error = '';
$success = '';
$import_results = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $csv_file = $_FILES['csv_file'];
    
    // Validate file
    if ($csv_file['error'] !== UPLOAD_ERR_OK) {
        $error = '❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
    } elseif ($csv_file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $error = '❌ ไฟล์ใหญ่เกินไป (สูงสุด 5MB)';
    } elseif (pathinfo($csv_file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $error = '❌ กรุณาเลือกไฟล์ CSV เท่านั้น';
    } else {
        // Process CSV file
        $temp_file = $csv_file['tmp_name'];
        $handle = fopen($temp_file, 'r');
        
        if ($handle === false) {
            $error = '❌ ไม่สามารถเปิดไฟล์ CSV ได้';
        } else {
            $row_count = 0;
            $success_count = 0;
            $error_count = 0;
            $skip_header = isset($_POST['skip_header']);
            
            // Read CSV line by line
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row_count++;
                
                // Skip header row if requested
                if ($skip_header && $row_count == 1) {
                    continue;
                }
                
                // Check if we have enough columns
                if (count($data) < 4) {
                    $import_results[] = [
                        'row' => $row_count,
                        'status' => 'error',
                        'message' => 'ข้อมูลไม่ครบ (ต้องมีอย่างน้อย 4 คอลัมน์)'
                    ];
                    $error_count++;
                    continue;
                }
                
                // Map CSV data to variables
                $student_id = trim($data[0] ?? '');
                $username = trim($data[1] ?? '');
                $full_name = trim($data[2] ?? '');
                $password = trim($data[3] ?? '');
                $class_room = trim($data[4] ?? '');
                $class_number = trim($data[5] ?? '');
                $email = trim($data[6] ?? '');
                $role = trim($data[7] ?? 'student');
                $status = trim($data[8] ?? 'active');
                
                // Validate required fields
                if (empty($student_id) || empty($username) || empty($full_name) || empty($password)) {
                    $import_results[] = [
                        'row' => $row_count,
                        'status' => 'error',
                        'message' => 'ข้อมูลที่จำเป็นไม่ครบ (student_id, username, full_name, password)'
                    ];
                    $error_count++;
                    continue;
                }
                
                // Validate role
                if (!in_array($role, ['student', 'admin'])) {
                    $role = 'student';
                }
                
                // Validate status
                if (!in_array($status, ['active', 'inactive'])) {
                    $status = 'active';
                }
                
                // Check if user already exists
                $check_query = "SELECT id FROM users WHERE student_id = ? OR username = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("ss", $student_id, $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $import_results[] = [
                        'row' => $row_count,
                        'status' => 'error',
                        'message' => 'รหัสนักเรียนหรือชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว'
                    ];
                    $error_count++;
                    $check_stmt->close();
                    continue;
                }
                $check_stmt->close();
                
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO users (student_id, username, password, full_name, class_room, class_number, email, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("sssssisss", $student_id, $username, $hashed_password, $full_name, $class_room, $class_number, $email, $role, $status);
                
                if ($insert_stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    log_activity($_SESSION['user_id'], 'user_import', "นำเข้าผู้ใช้: $full_name", $new_user_id);
                    
                    $import_results[] = [
                        'row' => $row_count,
                        'status' => 'success',
                        'message' => "นำเข้าเรียบร้อย: $full_name"
                    ];
                    $success_count++;
                } else {
                    $import_results[] = [
                        'row' => $row_count,
                        'status' => 'error',
                        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error
                    ];
                    $error_count++;
                }
                $insert_stmt->close();
            }
            
            fclose($handle);
            
            if ($success_count > 0) {
                $success = "✅ นำเข้าข้อมูลเรียบร้อย $success_count รายการ";
                if ($error_count > 0) {
                    $success .= " (มีข้อผิดพลาด $error_count รายการ)";
                }
            } elseif ($error_count > 0) {
                $error = "❌ ไม่สามารถนำเข้าข้อมูลได้ มีข้อผิดพลาด $error_count รายการ";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📁 นำเข้าผู้ใช้จาก CSV - <?php echo SITE_NAME; ?></title>
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
        
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        
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
        
        .instructions-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .instructions-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .instructions-card p {
            color: #666;
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .csv-format {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        
        .csv-format h4 {
            margin-bottom: 10px;
            color: #333;
            font-family: inherit;
        }
        
        .sample-data {
            background: #e8f5e8;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
            margin-top: 10px;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
        
        .file-input-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .file-input {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: #667eea;
            color: white;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-label:hover {
            background: #5568d3;
        }
        
        .file-info {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            color: #666;
            display: none;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover { background: #218838; }
        
        .results-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .results-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .results-header h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .results-body {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .result-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .result-success {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .result-error {
            background: #ffeaa7;
            color: #d35400;
        }
        
        .result-row {
            font-weight: 600;
            color: #666;
            min-width: 60px;
        }
        
        .result-message {
            flex: 1;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .csv-format {
                font-size: 12px;
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
            <h2>📁 นำเข้าผู้ใช้จาก CSV</h2>
            <a href="users-management.php" class="btn-back">← กลับหน้าจัดการผู้ใช้</a>
        </div>
        
        <div class="instructions-card">
            <h3>📋 คำแนะนำการใช้งาน</h3>
            <p>1. เตรียมไฟล์ CSV ที่มีข้อมูลผู้ใช้ตามรูปแบบที่กำหนด</p>
            <p>2. ไฟล์ต้องเป็นนามสกุล .csv และมีขนาดไม่เกิน 5MB</p>
            <p>3. สามารถมีหรือไม่มีแถวหัวตารางก็ได้</p>
            <p>4. ระบบจะตรวจสอบข้อมูลซ้ำและแสดงผลลัพธ์การนำเข้า</p>
            
            <div class="csv-format">
                <h4>🗂️ รูปแบบไฟล์ CSV:</h4>
                <div>คอลัมน์ที่ 1: รหัสนักเรียน (บังคับ)</div>
                <div>คอลัมน์ที่ 2: ชื่อผู้ใช้ (บังคับ)</div>
                <div>คอลัมน์ที่ 3: ชื่อเต็ม (บังคับ)</div>
                <div>คอลัมน์ที่ 4: รหัสผ่าน (บังคับ)</div>
                <div>คอลัมน์ที่ 5: ห้องเรียน (ไม่บังคับ)</div>
                <div>คอลัมน์ที่ 6: เลขที่ (ไม่บังคับ)</div>
                <div>คอลัมน์ที่ 7: อีเมล (ไม่บังคับ)</div>
                <div>คอลัมน์ที่ 8: สิทธิ์ (student/admin, ค่าเริ่มต้น: student)</div>
                <div>คอลัมน์ที่ 9: สถานะ (active/inactive, ค่าเริ่มต้น: active)</div>
                
                <div class="sample-data">
                    <strong>📄 ตัวอย่างข้อมูล:</strong><br>
                    ST001,student1,นายสมชาย ใจดี,123456,ม.4/1,1,somchai@email.com,student,active<br>
                    ST002,student2,นางสาววิไล สวยงาม,password,ม.4/2,5,,student,active<br>
                    ADM001,admin2,นายผู้จัดการ,admin123,,,admin@school.com,admin,active
                </div>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>📄 เลือกไฟล์ CSV</label>
                    <div class="file-input-container">
                        <input type="file" id="csv_file" name="csv_file" class="file-input" accept=".csv" required>
                        <label for="csv_file" class="file-input-label">
                            📁 เลือกไฟล์ CSV
                        </label>
                    </div>
                    <div class="file-info" id="file-info"></div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="skip_header" name="skip_header" checked>
                    <label for="skip_header">ข้ามแถวแรก (หากมีหัวตาราง)</label>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">📤 นำเข้าข้อมูล</button>
                    <a href="users-management.php" class="btn btn-secondary">❌ ยกเลิก</a>
                    <a href="csv-template.php" class="btn btn-success">📋 ดาวน์โหลดแม่แบบ CSV</a>
                </div>
            </form>
        </div>
        
        <?php if (!empty($import_results)): ?>
            <div class="results-container">
                <div class="results-header">
                    <h3>📊 ผลลัพธ์การนำเข้าข้อมูล</h3>
                    <p>ทั้งหมด <?php echo count($import_results); ?> รายการ | 
                    สำเร็จ <?php echo $success_count; ?> รายการ | 
                    ผิดพลาด <?php echo $error_count; ?> รายการ</p>
                </div>
                <div class="results-body">
                    <?php foreach ($import_results as $result): ?>
                        <div class="result-item">
                            <div class="result-row">แถว <?php echo $result['row']; ?></div>
                            <div class="result-status result-<?php echo $result['status']; ?>">
                                <?php echo $result['status'] == 'success' ? '✅ สำเร็จ' : '❌ ผิดพลาด'; ?>
                            </div>
                            <div class="result-message"><?php echo htmlspecialchars($result['message']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Show file info when file is selected
        document.getElementById('csv_file').addEventListener('change', function() {
            const fileInfo = document.getElementById('file-info');
            const file = this.files[0];
            
            if (file) {
                const size = (file.size / 1024).toFixed(2);
                fileInfo.innerHTML = `
                    <strong>ไฟล์ที่เลือก:</strong> ${file.name}<br>
                    <strong>ขนาด:</strong> ${size} KB<br>
                    <strong>ประเภท:</strong> ${file.type}
                `;
                fileInfo.style.display = 'block';
            } else {
                fileInfo.style.display = 'none';
            }
        });
    </script>
</body>
</html>
