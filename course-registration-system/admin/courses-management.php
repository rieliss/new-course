<?php
include '../config.php';
require_admin();

// Handle course actions
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'toggle_status':
            $course_id = (int)$_POST['course_id'];
            $new_status = $_POST['new_status'] == 'open' ? 'open' : 'closed';
            
            $query = "UPDATE courses SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $new_status, $course_id);
            
            if ($stmt->execute()) {
                $action_desc = $new_status == 'open' ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร';
                log_activity($_SESSION['user_id'], 'course_status_change', "เปลี่ยนสถานะวิชาเป็น: $action_desc", $course_id);
                $message = "เปลี่ยนสถานะวิชาเรียบร้อยแล้ว";
            } else {
                $message = "เกิดข้อผิดพลาดในการเปลี่ยนสถานะ";
                $message_type = 'error';
            }
            $stmt->close();
            break;
    }
}

// Get courses data with enrollment counts
$search = $_GET['search'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(course_code LIKE ? OR course_name LIKE ? OR teacher_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_status)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT c.*, COUNT(e.id) as enrolled_count
          FROM courses c
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.enrollment_status = 'enrolled'
          $where_clause
          GROUP BY c.id
          ORDER BY c.created_at DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$courses_result = $stmt->get_result();
$stmt->close();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_courses,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
    (SELECT COUNT(*) FROM enrollments WHERE enrollment_status = 'enrolled') as total_enrollments
FROM courses";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📖 จัดการวิชา - <?php echo SITE_NAME; ?></title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label { color: #666; font-size: 14px; }
        
        .controls-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .controls-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover { background: #e0a800; }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover { background: #138496; }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .courses-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-open {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-closed {
            background: #ffeaa7;
            color: #d35400;
        }
        
        .action-buttons-cell {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
        
        .enrollment-count {
            padding: 4px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 8px;
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
            <h2>📖 จัดการวิชา</h2>
            <a href="dashboard.php" class="btn-back">← กลับ Dashboard</a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">📚 วิชาทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['open_count']; ?></div>
                <div class="stat-label">✅ เปิดรับสมัคร</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['closed_count']; ?></div>
                <div class="stat-label">⛔ ปิดรับสมัคร</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_enrollments']; ?></div>
                <div class="stat-label">👨‍🎓 การลงทะเบียนทั้งหมด</div>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="controls-section">
            <form method="GET" class="controls-row">
                <div class="search-box">
                    <input type="text" name="search" placeholder="🔍 ค้นหาวิชา..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="filter_status">
                        <option value="">📊 ทุกสถานะ</option>
                        <option value="open" <?php echo $filter_status == 'open' ? 'selected' : ''; ?>>✅ เปิดรับสมัคร</option>
                        <option value="closed" <?php echo $filter_status == 'closed' ? 'selected' : ''; ?>>⛔ ปิดรับสมัคร</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
                </div>
            </form>
            
            <div class="action-buttons">
                <button class="btn btn-success">➕ เพิ่มวิชาใหม่</button>
                <button class="btn btn-primary">📁 นำเข้า CSV</button>
                <button class="btn btn-warning">📤 ส่งออก CSV</button>
            </div>
        </div>
        
        <!-- Courses Table -->
        <div class="courses-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>อาจารย์</th>
                        <th>หน่วยกิต</th>
                        <th>ตารางเรียน</th>
                        <th>ที่นั่ง</th>
                        <th>ลงทะเบียน</th>
                        <th>สถานะ</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses_result->num_rows > 0): ?>
                        <?php while ($course = $courses_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                                <td><?php echo $course['credits']; ?> หน่วยกิต</td>
                                <td>
                                    <?php if ($course['schedule_day'] && $course['schedule_time']): ?>
                                        <?php echo htmlspecialchars($course['schedule_day']); ?><br>
                                        <small><?php echo htmlspecialchars($course['schedule_time']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $course['max_seats']; ?> ที่นั่ง</td>
                                <td>
                                    <span class="enrollment-count"><?php echo $course['enrolled_count']; ?> คน</span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $course['status']; ?>">
                                        <?php echo $course['status'] == 'open' ? '✅ เปิดรับสมัคร' : '⛔ ปิดรับสมัคร'; ?>
                                    </span>
                                </td>
                                <td class="action-buttons-cell">
                                    <a href="course-students.php?course_id=<?php echo $course['id']; ?>" class="btn btn-info btn-sm">👨‍🎓 นักเรียน</a>
                                    
                                    <button class="btn btn-primary btn-sm">✏️ แก้ไข</button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $course['status'] == 'open' ? 'closed' : 'open'; ?>">
                                        <button type="submit" class="btn <?php echo $course['status'] == 'open' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                            <?php echo $course['status'] == 'open' ? '⛔ ปิดรับสมัคร' : '✅ เปิดรับสมัคร'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                ไม่พบข้อมูลวิชา
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
