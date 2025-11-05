<?php
include 'config.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_query = "SELECT full_name, class_room, class_number FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Get current semester from admin config
$config_query = "SELECT config_value FROM admin_config WHERE config_key = 'current_semester'";
$config_stmt = $conn->prepare($config_query);
$config_stmt->execute();
$config_result = $config_stmt->get_result()->fetch_assoc();
$current_semester = $config_result ? (int)$config_result['config_value'] : 1;
$config_stmt->close();

// Get current academic year from admin config
$year_query = "SELECT config_value FROM admin_config WHERE config_key = 'current_academic_year'";
$year_stmt = $conn->prepare($year_query);
$year_stmt->execute();
$year_result = $year_stmt->get_result()->fetch_assoc();
$current_year = $year_result ? (int)$year_result['config_value'] : 2024;
$year_stmt->close();

// Get enrolled courses for current semester
$query = "SELECT c.id, c.course_code, c.course_name, c.teacher_name, c.credits, c.schedule_day, c.schedule_time, c.semester, c.grade_level,
          e.enrolled_at
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          WHERE e.student_id = ? AND e.enrollment_status = 'enrolled' AND e.academic_year = ? AND c.semester = ?
          ORDER BY c.semester, e.enrolled_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $current_year, $current_semester);
$stmt->execute();
$enrollments = $stmt->get_result();
$total_credits = 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ วิชาที่ลงทะเบียน - <?php echo SITE_NAME; ?></title>
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
            max-width: 1000px;
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
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        
        .empty-state p {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .btn-enroll {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-enroll:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header h2 {
                font-size: 24px;
            }

            table {
                font-size: 13px;
            }
            
            th, td {
                padding: 10px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 24px;
            }
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
            <div>
                <h2>✅ วิชาที่ลงทะเบียนแล้ว</h2>
                <div style="color: #666; font-size: 14px; margin-top: 10px;">
                    👤 <?php echo htmlspecialchars($user_data['full_name']); ?> 
                    | 🏫 <?php echo htmlspecialchars($user_data['class_room']); ?> 
                    | เลขที่ <?php echo $user_data['class_number']; ?>
                </div>
            </div>
        </div>
        
        <?php if ($enrollments->num_rows > 0): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">📚 จำนวนวิชา</div>
                    <div class="stat-value"><?php echo $enrollments->num_rows; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">⭐ รวมหน่วยกิต</div>
                    <div class="stat-value" id="total-credits">--</div>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>รหัสวิชา</th>
                            <th>ชื่อวิชา</th>
                            <th>ครูผู้สอน</th>
                            <th>เวลา</th>
                            <th>ภาค</th>
                            <th>หน่วยกิต</th>
                            <th>วันที่ลงทะเบียน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $enrollments->data_seek(0);
                        $total = 0;
                        while ($row = $enrollments->fetch_assoc()):
                            $total += $row['credits'];
                        ?>
                            <tr>
                                <td><?php echo $row['course_code']; ?></td>
                                <td><?php echo $row['course_name']; ?></td>
                                <td><?php echo $row['teacher_name']; ?></td>
                                <td><?php echo $row['schedule_time']; ?></td>
                                <td><?php echo $row['semester']; ?></td>
                                <td><?php echo $row['credits']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['enrolled_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                document.getElementById('total-credits').textContent = <?php echo $total; ?>;
            </script>
        <?php else: ?>
            <div class="empty-state">
                <p>😕 ยังไม่มีวิชาที่ลงทะเบียน</p>
                <a href="courses.php" class="btn-enroll">📖 ไปลงทะเบียนวิชา</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
