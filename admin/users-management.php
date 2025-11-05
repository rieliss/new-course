<?php
include '../config.php';
require_admin();

// Handle POST actions
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'toggle_status':
            $user_id = (int)$_POST['user_id'];
            $new_status = $_POST['new_status'] == 'active' ? 'active' : 'inactive';
            
            $query = "UPDATE users SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $new_status, $user_id);
            
            if ($stmt->execute()) {
                $action_desc = $new_status == 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
                log_activity($_SESSION['user_id'], 'user_status_change', "เปลี่ยนสถานะผู้ใช้เป็น: $action_desc", $user_id);
                $message = "เปลี่ยนสถานะผู้ใช้เรียบร้อยแล้ว";
            } else {
                $message = "เกิดข้อผิดพลาดในการเปลี่ยนสถานะ";
                $message_type = 'error';
            }
            $stmt->close();
            break;
            
        case 'reset_password':
            $user_id = (int)$_POST['user_id'];
            $new_password = 'password123'; // Default password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'password_reset', "รีเซตรหัสผ่านผู้ใช้", $user_id);
                $message = "รีเซตรหัสผ่านเรียบร้อยแล้ว รหัสผ่านใหม่: password123";
            } else {
                $message = "เกิดข้อผิดพลาดในการรีเซตรหัสผ่าน";
                $message_type = 'error';
            }
            $stmt->close();
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            // ตรวจสอบไม่ให้ลบ admin เองและ admin user id 1
            if ($user_id == $_SESSION['user_id'] || $user_id == 1) {
                $message = "ไม่สามารถลบ admin หลักหรือบัญชีของตนเองได้";
                $message_type = 'error';
            } else {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'user_delete', "ลบผู้ใช้", $user_id);
                    $message = "ลบผู้ใช้เรียบร้อยแล้ว";
                } else {
                    $message = "เกิดข้อผิดพลาดในการลบผู้ใช้";
                    $message_type = 'error';
                }
                $stmt->close();
            }
            break;
    }
}

// Get users data
$search = $_GET['search'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR student_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_role)) {
    $where_conditions[] = "role = ?";
    $params[] = $filter_role;
    $param_types .= 's';
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT id, student_id, username, full_name, class_room, class_number, role, status, created_at FROM users $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$users_result = $stmt->get_result();
$stmt->close();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as student_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
FROM users";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 จัดการผู้ใช้ - <?php echo SITE_NAME; ?></title>
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover { background: #c82333; }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .users-table {
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
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-admin {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .role-student {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-inactive {
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 300px;
            text-align: center;
        }
        
        .modal-content h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .modal-content p {
            margin-bottom: 20px;
            color: #666;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
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
            <h2>👥 จัดการผู้ใช้</h2>
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
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">👥 ผู้ใช้ทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['admin_count']; ?></div>
                <div class="stat-label">👨‍💼 ผู้ดูแลระบบ</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['student_count']; ?></div>
                <div class="stat-label">👨‍🎓 นักเรียน</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_count']; ?></div>
                <div class="stat-label">✅ ใช้งานอยู่</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['inactive_count']; ?></div>
                <div class="stat-label">⛔ ปิดใช้งาน</div>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="controls-section">
            <form method="GET" class="controls-row">
                <div class="search-box">
                    <input type="text" name="search" placeholder="🔍 ค้นหาผู้ใช้..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="filter_role">
                        <option value="">👤 ทุกสิทธิ์</option>
                        <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>👨‍💼 ผู้ดูแลระบบ</option>
                        <option value="student" <?php echo $filter_role == 'student' ? 'selected' : ''; ?>>👨‍🎓 นักเรียน</option>
                    </select>
                    
                    <select name="filter_status">
                        <option value="">📊 ทุกสถานะ</option>
                        <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>✅ ใช้งานอยู่</option>
                        <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>⛔ ปิดใช้งาน</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
                </div>
            </form>
            
            <div class="action-buttons">
                <a href="user-add.php" class="btn btn-success">➕ เพิ่มผู้ใช้</a>
                <a href="user-import.php" class="btn btn-primary">📁 นำเข้า CSV</a>
                <a href="user-export.php" class="btn btn-warning">📤 ส่งออก CSV</a>
                <a href="bulk-user-promotion.php" class="btn btn-info">🎓 เลื่อนชั้นแบบกลุ่ม</a>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="users-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>รหัสนักเรียน</th>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อเต็ม</th>
                        <th>ห้อง</th>
                        <th>สิทธิ์</th>
                        <th>สถานะ</th>
                        <th>วันที่สร้าง</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users_result->num_rows > 0): ?>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <?php if ($user['class_room']): ?>
                                        <?php echo htmlspecialchars($user['class_room']); ?>
                                        <?php if ($user['class_number']): ?>
                                            เลขที่ <?php echo $user['class_number']; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo $user['role'] == 'admin' ? '👨‍💼 ผู้ดูแลระบบ' : '👨‍🎓 นักเรียน'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo $user['status'] == 'active' ? '✅ ใช้งานอยู่' : '⛔ ปิดใช้งาน'; ?>
                                    </span>
                                </td>
                                <td><?php echo format_date_thai($user['created_at']); ?></td>
                                <td class="action-buttons-cell">
                                    <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">✏️ แก้ไข</a>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $user['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn <?php echo $user['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                            <?php echo $user['status'] == 'active' ? '⛔ ปิดใช้งาน' : '✅ เปิดใช้งาน'; ?>
                                        </button>
                                    </form>
                                    
                                    <button class="btn btn-warning btn-sm" onclick="resetPassword(<?php echo $user['id']; ?>)">🔑 รีเซตรหัสผ่าน</button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id'] && $user['id'] != 1): ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">🗑️ ลบ</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                ไม่พบข้อมูลผู้ใช้
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle"></h3>
            <p id="modalMessage"></p>
            <div class="modal-buttons">
                <button class="btn btn-danger" onclick="confirmAction()">ยืนยัน</button>
                <button class="btn btn-primary" onclick="closeModal()">ยกเลิก</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentAction = null;
        let currentUserId = null;
        
        function resetPassword(userId) {
            currentAction = 'reset_password';
            currentUserId = userId;
            
            document.getElementById('modalTitle').textContent = '🔑 รีเซตรหัสผ่าน';
            document.getElementById('modalMessage').textContent = 'คุณต้องการรีเซตรหัสผ่านของผู้ใช้นี้หรือไม่? รหัสผ่านจะถูกเปลี่ยนเป็น "password123"';
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        function deleteUser(userId, userName) {
            currentAction = 'delete_user';
            currentUserId = userId;
            
            document.getElementById('modalTitle').textContent = '🗑️ ลบผู้ใช้';
            document.getElementById('modalMessage').textContent = `คุณต้องการลบผู้ใช้ "${userName}" หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้`;
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        function confirmAction() {
            if (currentAction && currentUserId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = currentAction;
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = currentUserId;
                
                form.appendChild(actionInput);
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
            currentAction = null;
            currentUserId = null;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
