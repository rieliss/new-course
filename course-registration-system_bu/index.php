<?php
include 'config.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 หน้าหลัก - <?php echo SITE_NAME; ?></title>
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
        
        .user-info {
            font-size: 14px;
        }
        
        .btn-logout {
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
        
        .btn-logout:hover {
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
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #333;
        }
        
        .menu-buttons {
            display: flex;
            gap: 10px;
        }
        
        .menu-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
            font-size: 32px;
            font-weight: 700;
            color: #333;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        
        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .menu-buttons {
                width: 100%;
            }
            
            .menu-btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">📚 <?php echo SITE_NAME; ?></div>
            <div class="navbar-user">
                <div class="user-info">
                    👤 <?php echo $_SESSION['full_name']; ?>
                </div>
                <a href="logout.php" class="btn-logout">🚪 ออกจากระบบ</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🎓 ยินดีต้อนรับ <?php echo $_SESSION['full_name']; ?></h2>
                <p style="color: #666; margin-top: 5px;">เลือกเมนูด้านล่างเพื่อเริ่มต้น</p>
            </div>
            <div class="menu-buttons">
                <a href="courses.php" class="menu-btn">📖 ลงทะเบียนวิชา</a>
                <a href="enrollments.php" class="menu-btn">✅ วิชาที่ลงทะเบียนแล้ว</a>
            </div>
        </div>
        
        <div class="alert alert-info">
            ℹ️ ระบบลงทะเบียนวิชาอนุญาตให้นักเรียนสามารถลงทะเบียนและจัดการวิชาเรียนของตนเองได้ตามความต้องการ
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📚 วิชาทั้งหมด</div>
                <div class="stat-value" id="total-courses">--</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ วิชาที่ลงทะเบียน</div>
                <div class="stat-value" id="enrolled-count">--</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">⭐ รวมหน่วยกิต</div>
                <div class="stat-value" id="total-credits">--</div>
            </div>
        </div>
    </div>
    
    <script>
        // Load stats
        fetch('api/get-student-stats.php')
            .then(r => r.json())
            .then(data => {
                document.getElementById('total-courses').textContent = data.total_courses;
                document.getElementById('enrolled-count').textContent = data.enrolled_count;
                document.getElementById('total-credits').textContent = data.total_credits;
            });
    </script>
</body>
</html>
