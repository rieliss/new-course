<?php
include '../config.php';
require_admin();

$message = '';
$message_type = 'success';
$import_results = [];
$summary = ['total' => 0, 'success' => 0, 'error' => 0];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $skip_header = isset($_POST['skip_header']);

    // Validation
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $message = '❌ กรุณาเลือกไฟล์ CSV';
        $message_type = 'error';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '❌ เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
        $message_type = 'error';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
        $message = '❌ ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)';
        $message_type = 'error';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $message = '❌ กรุณาเลือกไฟล์ CSV เท่านั้น';
        $message_type = 'error';
    } else {
        // Process CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $message = '❌ ไม่สามารถเปิดไฟล์ได้';
            $message_type = 'error';
        } else {
            $row_count = 0;
            $success_count = 0;
            $error_count = 0;

            $conn->begin_transaction();
            try {
                while (($data = fgetcsv($handle, 2000, ',')) !== FALSE) {
                    $row_count++;

                    // Skip header row if requested
                    if ($skip_header && $row_count == 1) {
                        continue;
                    }

                    // Check if we have minimum columns
                    if (count($data) < 3) {
                        $import_results[] = [
                            'row' => $row_count,
                            'code' => trim($data[0] ?? ''),
                            'status' => 'error',
                            'message' => '❌ ข้อมูลไม่ครบ (ต้องมีอย่างน้อย 3 คอลัมน์: code, name, teacher)'
                        ];
                        $error_count++;
                        continue;
                    }

                    // Map CSV data to variables
                    $course_code = trim($data[0] ?? '');
                    $course_name = trim($data[1] ?? '');
                    $teacher_name = trim($data[2] ?? '');
                    $credits = (int)($data[3] ?? 3);
                    $schedule_day = trim($data[4] ?? '');
                    $schedule_time = trim($data[5] ?? '');
                    $max_seats = (int)($data[6] ?? 30);
                    $grade_level = (int)($data[7] ?? 4);
                    $semester = (int)($data[8] ?? 1);
                    $classroom = trim($data[9] ?? '');
                    $max_enrollments = (int)($data[10] ?? 999);
                    $description = trim($data[11] ?? '');
                    $status = trim($data[12] ?? 'open');

                    // Validate required fields
                    if (empty($course_code) || empty($course_name) || empty($teacher_name) || empty($classroom)) {
                        $import_results[] = [
                            'row' => $row_count,
                            'code' => $course_code,
                            'status' => 'error',
                            'message' => '❌ ข้อมูลที่จำเป็นไม่ครบ (รหัสวิชา, ชื่อวิชา, ชื่ออาจารย์, ห้องเรียน)'
                        ];
                        $error_count++;
                        continue;
                    }

                    // Validate credits
                    if ($credits <= 0) {
                        $credits = 3;
                    }

                    // Validate max_seats
                    if ($max_seats <= 0) {
                        $max_seats = 30;
                    }

                    // Validate status
                    if (!in_array($status, ['open', 'closed'])) {
                        $status = 'open';
                    }

                    // Check for duplicate
                    $check_query = "SELECT id FROM courses WHERE course_code = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bind_param("s", $course_code);
                    $check_stmt->execute();

                    if ($check_stmt->get_result()->num_rows > 0) {
                        $import_results[] = [
                            'row' => $row_count,
                            'code' => $course_code,
                            'status' => 'error',
                            'message' => '⚠️ รหัสวิชานี้มีอยู่ในระบบแล้ว'
                        ];
                        $error_count++;
                        $check_stmt->close();
                        continue;
                    }
                    $check_stmt->close();

                    // Insert course
                    $insert_query = "INSERT INTO courses (course_code, course_name, teacher_name, credits, 
                                    schedule_day, schedule_time, max_seats, grade_level, semester, classroom, 
                                    max_enrollments, description, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("sssissiiisiss", 
                        $course_code, $course_name, $teacher_name, $credits, 
                        $schedule_day, $schedule_time, $max_seats, $grade_level, $semester,
                        $classroom, $max_enrollments, $description, $status);

                    if ($insert_stmt->execute()) {
                        $import_results[] = [
                            'row' => $row_count,
                            'code' => $course_code,
                            'status' => 'success',
                            'message' => '✅ เพิ่มสำเร็จ'
                        ];
                        $success_count++;
                    } else {
                        $import_results[] = [
                            'row' => $row_count,
                            'code' => $course_code,
                            'status' => 'error',
                            'message' => '❌ ไม่สามารถเพิ่มได้: ' . $insert_stmt->error
                        ];
                        $error_count++;
                    }
                    $insert_stmt->close();
                }

                $conn->commit();

                // Log activity
                log_activity($_SESSION['user_id'], 'course_import_csv', 
                    "นำเข้าวิชาจาก CSV: สำเร็จ $success_count, ผิดพลาด $error_count", null);

                // Create summary message
                $summary['total'] = $success_count + $error_count;
                $summary['success'] = $success_count;
                $summary['error'] = $error_count;

                if ($success_count > 0) {
                    $message = "✅ นำเข้าสำเร็จ $success_count วิชา";
                    if ($error_count > 0) {
                        $message .= " (" . $error_count . " ผิดพลาด)";
                    }
                    $message_type = 'success';
                } else if ($error_count > 0) {
                    $message = "❌ นำเข้าผิดพลาด $error_count แถว";
                    $message_type = 'error';
                }

            } catch (Exception $e) {
                $conn->rollback();
                $message = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
                $message_type = 'error';
            }

            fclose($handle);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📁 นำเข้า CSV - <?php echo SITE_NAME; ?></title>
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
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-back:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .page-header h2 { font-size: 28px; color: #333; margin-bottom: 30px; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .upload-form {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
        }
        
        .upload-form input[type="file"] {
            display: none;
        }
        
        .upload-label {
            cursor: pointer;
            display: inline-block;
        }
        
        .upload-label:hover {
            opacity: 0.8;
        }
        
        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            cursor: pointer;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
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
            width: 100%;
            justify-content: center;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .btn-download {
            background: #28a745;
            color: white;
            width: 100%;
            justify-content: center;
        }
        
        .btn-download:hover {
            background: #218838;
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
        
        .template-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .template-info h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .template-info ul {
            margin-left: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .template-info li {
            margin-bottom: 8px;
        }
        
        .filename-display {
            margin-top: 15px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            word-break: break-all;
        }
        
        .results-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 30px;
        }
        
        .results-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-success {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: 600;
        }
        
        .summary-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .summary-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .summary-item .number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .summary-item .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .success-box .number { color: #28a745; }
        .error-box .number { color: #dc3545; }
        .total-box .number { color: #667eea; }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-box {
                grid-template-columns: 1fr;
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
            <h2>📁 นำเข้าวิชาจาก CSV</h2>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
            
            <?php if (!empty($import_results)): ?>
                <div class="summary-box">
                    <div class="summary-item total-box">
                        <div class="number"><?php echo $summary['total']; ?></div>
                        <div class="label">ทั้งหมด</div>
                    </div>
                    <div class="summary-item success-box">
                        <div class="number"><?php echo $summary['success']; ?></div>
                        <div class="label">สำเร็จ</div>
                    </div>
                    <div class="summary-item error-box">
                        <div class="number"><?php echo $summary['error']; ?></div>
                        <div class="label">ผิดพลาด</div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Upload Form -->
            <div class="card">
                <h3 style="margin-bottom: 20px; color: #333;">📤 อัพโหลดไฟล์</h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-form">
                        <label for="csv_file" class="upload-label">
                            <div class="upload-icon">📊</div>
                            <div class="upload-text">คลิกเพื่อเลือกไฟล์ CSV หรือลากไฟล์มาวาง</div>
                            <div style="font-size: 12px; color: #999;">สูงสุด 5MB, เฉพาะไฟล์ .csv</div>
                        </label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="checkbox-group" style="margin-top: 20px;">
                        <input type="checkbox" id="skip_header" name="skip_header" value="1" checked>
                        <label for="skip_header">✓ ข้ามแถวแรก (Header)</label>
                    </div>
                    
                    <div class="filename-display" id="filename" style="display: none;"></div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;">✅ นำเข้าข้อมูล</button>
                </form>
            </div>
            
            <!-- Template Info -->
            <div class="card">
                <h3 style="margin-bottom: 20px; color: #333;">📋 ตัวอย่างไฟล์</h3>
                
                <a href="csv-template-course.php" class="btn btn-download" style="margin-bottom: 20px;">📥 ดาวน์โหลดตัวอย่าง CSV</a>
                
                <div class="template-info">
                    <h3>คอลัมน์ที่ต้องการ:</h3>
                    <ul>
                        <li><strong>1. รหัสวิชา</strong> - course_code (ต้องไม่ซ้ำ)</li>
                        <li><strong>2. ชื่อวิชา</strong> - course_name</li>
                        <li><strong>3. ชื่ออาจารย์</strong> - teacher_name</li>
                        <li><strong>4. หน่วยกิต</strong> - credits (ค่าเริ่มต้น: 3)</li>
                        <li><strong>5. วันที่เรียน</strong> - schedule_day</li>
                        <li><strong>6. เวลาเรียน</strong> - schedule_time</li>
                        <li><strong>7. จำนวนที่นั่ง</strong> - max_seats (ค่าเริ่มต้น: 30)</li>
                        <li><strong>8. ชั้นปี</strong> - grade_level (4, 5, 6)</li>
                        <li><strong>9. ภาคการศึกษา</strong> - semester (1 หรือ 2)</li>
                        <li><strong>10. ห้องเรียน</strong> - classroom (เช่น ม.4/1, ม.5/1)</li>
                        <li><strong>11. จำนวนวิชาสูงสุด</strong> - max_enrollments (ค่าเริ่มต้น: 999)</li>
                        <li><strong>12. คำอธิบาย</strong> - description</li>
                        <li><strong>13. สถานะ</strong> - status (open/closed)</li>
                    </ul>
                </div>
                
                <div style="background: #e3f2fd; border-left: 4px solid #1976d2; padding: 15px; border-radius: 4px; margin-top: 15px;">
                    <p style="font-size: 13px; color: #0d47a1; margin-bottom: 10px;">
                        💡 <strong>เคล็ดลับ:</strong>
                    </p>
                    <p style="font-size: 12px; color: #1565c0;">
                        • ความจำเป็นอย่างน้อย 4 คอลัมน์: รหัส, ชื่อ, อาจารย์, ห้องเรียน<br>
                        • คอลัมน์อื่น ๆ จะใช้ค่าเริ่มต้น<br>
                        • ดาวน์โหลดตัวอย่างเพื่อดูรูปแบบที่ถูกต้อง
                    </p>
                </div>
            </div>
        </div>
        
        <?php if (!empty($import_results)): ?>
            <div class="results-table">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 8%;">แถว</th>
                            <th style="width: 15%;">รหัสวิชา</th>
                            <th style="width: 12%;">สถานะ</th>
                            <th style="width: 65%;">ข้อความ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($import_results as $result): ?>
                            <tr>
                                <td><?php echo $result['row']; ?></td>
                                <td><?php echo htmlspecialchars($result['code']); ?></td>
                                <td>
                                    <span class="<?php echo $result['status'] == 'success' ? 'status-success' : 'status-error'; ?>">
                                        <?php echo $result['status'] == 'success' ? '✅ สำเร็จ' : '❌ ผิดพลาด'; ?>
                                    </span>
                                </td>
                                <td><?php echo $result['message']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        const fileInput = document.getElementById('csv_file');
        const filenameDisplay = document.getElementById('filename');
        
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                filenameDisplay.textContent = '📄 ' + this.files[0].name;
                filenameDisplay.style.display = 'block';
            } else {
                filenameDisplay.style.display = 'none';
            }
        });
    </script>
</body>
</html>
