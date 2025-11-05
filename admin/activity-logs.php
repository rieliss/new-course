<?php
include '../config.php';
require_admin();

$logs_query = "SELECT u.full_name, l.action, l.description, l.created_at
              FROM activity_logs l
              JOIN users u ON l.user_id = u.id
              ORDER BY l.created_at DESC
              LIMIT 100";
$logs = $conn->query($logs_query);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📝 กิจกรรม - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar-brand { font-size: 24px; font-weight: 700; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; border: 1px solid white; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { font-size: 28px; color: #333; }
        .btn-back { padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
        .table-container { background: white; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
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
            <h2>📝 ประวัติกิจกรรม</h2>
            <a href="dashboard.php" class="btn-back">← กลับ Dashboard</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อผู้ใช้</th>
                        <th>กิจกรรม</th>
                        <th>รายละเอียด</th>
                        <th>เวลา</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $log['full_name']; ?></td>
                            <td><?php echo $log['action']; ?></td>
                            <td><?php echo $log['description']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
