<?php
include '../config.php';
require_admin();

// Get statistics
$courses_query = "SELECT COUNT(*) as count FROM courses";
$courses_count = $conn->query($courses_query)->fetch_assoc()['count'];

$users_query = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
$users_count = $conn->query($users_query)->fetch_assoc()['count'];

$enrollments_query = "SELECT COUNT(*) as count FROM enrollments WHERE enrollment_status = 'enrolled'";
$enrollments_count = $conn->query($enrollments_query)->fetch_assoc()['count'];

$logs_query = "SELECT COUNT(*) as count FROM activity_logs";
$logs_count = $conn->query($logs_query)->fetch_assoc()['count'];

// Top courses
$top_courses_query = "SELECT c.course_name, c.course_code, COUNT(e.id) as enrolled
                      FROM courses c
                      LEFT JOIN enrollments e ON c.id = e.course_id AND e.enrollment_status = 'enrolled'
                      GROUP BY c.id
                      ORDER BY enrolled DESC
                      LIMIT 5";
$top_courses = $conn->query($top_courses_query);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
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
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .menu-btn {
            padding: 20px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .menu-btn:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
            transform: translateY(-4px);
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 30px;
        }
        
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f9f9f9;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">📚 <?php echo SITE_NAME; ?> - Admin</div>
            <div class="navbar-user">
                <div style="font-size: 14px;">👤 <?php echo $_SESSION['full_name']; ?></div>
                <a href="../logout.php" class="btn-logout">🚪 ออกจากระบบ</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>⚙️ Dashboard ผู้ดูแลระบบ</h2>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📚 จำนวนวิชาทั้งหมด</div>
                <div class="stat-value"><?php echo $courses_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">👥 นักเรียนทั้งหมด</div>
                <div class="stat-value"><?php echo $users_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ ลงทะเบียนทั้งหมด</div>
                <div class="stat-value"><?php echo $enrollments_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📝 กิจกรรมทั้งหมด</div>
                <div class="stat-value"><?php echo $logs_count; ?></div>
            </div>
        </div>
        
        <!-- Management Menu -->
        <div style="margin-bottom: 30px;">
            <h3 style="color: #333; margin-bottom: 15px;">🔧 เมนูจัดการระบบ</h3>
            <div class="menu-grid">
                <a href="courses-management.php" class="menu-btn">📖 จัดการวิชา</a>
                <a href="users-management.php" class="menu-btn">👥 จัดการผู้ใช้</a>
                <a href="activity-logs.php" class="menu-btn">📝 ดูกิจกรรม</a>
                <a href="../index.php" class="menu-btn">← กลับหน้าหลัก</a>
            </div>
        </div>
        
        <!-- Top Courses -->
        <div class="table-container">
            <div class="table-header">🏆 วิชายอดนิยม</div>
            <table>
                <thead>
                    <tr>
                        <th>รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>ผู้ลงทะเบียน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $top_courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['course_code']; ?></td>
                            <td><?php echo $row['course_name']; ?></td>
                            <td><?php echo $row['enrolled']; ?> คน</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
