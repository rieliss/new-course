<?php
/**
 * Database Verification and Repair Script
 * ใช้สำหรับตรวจสอบและแก้ไขปัญหาฐานข้อมูล
 */

// การตั้งค่าฐานข้อมูล (ปรับแก้ตามการตั้งค่าของคุณ)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'course_registration';

echo "🔍 ตรวจสอบฐานข้อมูล Course Registration System\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // เชื่อมต่อฐานข้อมูล
    $conn = new mysqli($db_host, $db_user, $db_pass);
    
    if ($conn->connect_error) {
        die("❌ ไม่สามารถเชื่อมต่อ MySQL ได้: " . $conn->connect_error . "\n");
    }
    
    echo "✅ เชื่อมต่อ MySQL สำเร็จ\n\n";
    
    // ตรวจสอบว่ามีฐานข้อมูลหรือไม่
    $result = $conn->query("SHOW DATABASES LIKE '$db_name'");
    if ($result->num_rows == 0) {
        echo "⚠️  ไม่พบฐานข้อมูล '$db_name'\n";
        echo "🔨 กำลังสร้างฐานข้อมูล...\n";
        
        if ($conn->query("CREATE DATABASE $db_name")) {
            echo "✅ สร้างฐานข้อมูล '$db_name' สำเร็จ\n\n";
        } else {
            die("❌ ไม่สามารถสร้างฐานข้อมูลได้: " . $conn->error . "\n");
        }
    } else {
        echo "✅ พบฐานข้อมูล '$db_name'\n\n";
    }
    
    // เลือกฐานข้อมูล
    $conn->select_db($db_name);
    
    // ตรวจสอบตาราง users
    echo "🔍 ตรวจสอบตาราง users...\n";
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    
    if ($result->num_rows == 0) {
        echo "⚠️  ไม่พบตาราง users\n";
        echo "🔨 กำลังสร้างตาราง users...\n";
        createUsersTable($conn);
    } else {
        echo "✅ พบตาราง users\n";
        
        // ตรวจสอบโครงสร้างของตาราง
        echo "🔍 ตรวจสอบโครงสร้างตาราง users...\n";
        $result = $conn->query("DESCRIBE users");
        
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        $required_columns = ['id', 'student_id', 'username', 'password', 'full_name', 'role', 'status'];
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            echo "❌ ตารางไม่ถูกต้อง ขาดคอลัมน์: " . implode(', ', $missing_columns) . "\n";
            echo "🔨 กำลังลบและสร้างตารางใหม่...\n";
            
            $conn->query("DROP TABLE IF EXISTS users");
            createUsersTable($conn);
        } else {
            echo "✅ โครงสร้างตาราง users ถูกต้อง\n";
        }
    }
    
    // ตรวจสอบข้อมูลผู้ใช้
    echo "\n🔍 ตรวจสอบข้อมูลผู้ใช้...\n";
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo "⚠️  ไม่มีข้อมูลผู้ใช้\n";
        echo "🔨 กำลังเพิ่มข้อมูลผู้ใช้เริ่มต้น...\n";
        insertDefaultUsers($conn);
    } else {
        echo "✅ พบข้อมูลผู้ใช้ " . $row['count'] . " คน\n";
        
        // ตรวจสอบ admin user
        $result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
        if ($result->num_rows == 0) {
            echo "⚠️  ไม่พบ admin user\n";
            echo "🔨 กำลังเพิ่ม admin user...\n";
            insertAdminUser($conn);
        } else {
            echo "✅ พบ admin user\n";
        }
    }
    
    // สร้างตารางอื่นๆ ถ้าจำเป็น
    createOtherTables($conn);
    
    echo "\n🎉 การตรวจสอบเสร็จสมบูรณ์!\n";
    echo "📋 ข้อมูลสำหรับ Login:\n";
    echo "   Admin: username=admin, password=admin123\n";
    echo "   Student: username=student1, password=123456\n\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
}

function createUsersTable($conn) {
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) UNIQUE NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        class_room VARCHAR(50),
        class_number INT,
        email VARCHAR(100),
        role ENUM('student', 'admin') DEFAULT 'student',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id),
        INDEX idx_username (username),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "✅ สร้างตาราง users สำเร็จ\n";
        insertDefaultUsers($conn);
    } else {
        die("❌ ไม่สามารถสร้างตาราง users ได้: " . $conn->error . "\n");
    }
}

function insertAdminUser($conn) {
    $admin_hash = '$2y$10$EY7NKIRoow4SfHJ9C7WAe.c81pLd0Olc8hdT7/N/cUfwZXL2Egb0y'; // admin123
    
    $sql = "INSERT INTO users (student_id, username, password, full_name, role, status) 
            VALUES ('ADM001', 'admin', '$admin_hash', 'ผู้ดูแลระบบ', 'admin', 'active')";
    
    if ($conn->query($sql)) {
        echo "✅ เพิ่ม admin user สำเร็จ\n";
    } else {
        echo "❌ ไม่สามารถเพิ่ม admin user ได้: " . $conn->error . "\n";
    }
}

function insertDefaultUsers($conn) {
    $admin_hash = '$2y$10$EY7NKIRoow4SfHJ9C7WAe.c81pLd0Olc8hdT7/N/cUfwZXL2Egb0y'; // admin123
    $student_hash = '$2y$10$XO0YcaxlqlL5i7IkZ9.FjOnCQNdmeINbGvsi0HHKqZpwtsqBACvni'; // 123456
    
    $users = [
        "('ADM001', 'admin', '$admin_hash', 'ผู้ดูแลระบบ', '', 0, 'admin', 'active')",
        "('ST001', 'student1', '$student_hash', 'สมเด็จ พระเจ้า', 'ม.4/1', 1, 'student', 'active')",
        "('ST002', 'student2', '$student_hash', 'สม.หญิง นกเขา', 'ม.4/2', 5, 'student', 'active')",
        "('ST003', 'student3', '$student_hash', 'นาย ต้นไม้', 'ม.5/1', 10, 'student', 'active')"
    ];
    
    $sql = "INSERT INTO users (student_id, username, password, full_name, class_room, class_number, role, status) VALUES " . implode(',', $users);
    
    if ($conn->query($sql)) {
        echo "✅ เพิ่มข้อมูลผู้ใช้เริ่มต้นสำเร็จ\n";
    } else {
        echo "❌ ไม่สามารถเพิ่มข้อมูลผู้ใช้ได้: " . $conn->error . "\n";
    }
}

function createOtherTables($conn) {
    // สร้างตาราง courses
    $courses_sql = "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) UNIQUE NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        teacher_name VARCHAR(150) NOT NULL,
        credits INT NOT NULL,
        schedule_day VARCHAR(100),
        schedule_time VARCHAR(50),
        max_seats INT NOT NULL DEFAULT 30,
        allowed_classes TEXT,
        status ENUM('open', 'closed') DEFAULT 'open',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_course_code (course_code),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($courses_sql);
    
    // สร้างตาราง enrollments
    $enrollments_sql = "CREATE TABLE IF NOT EXISTS enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        enrollment_status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_enrollment (student_id, course_id),
        INDEX idx_student (student_id),
        INDEX idx_course (course_id),
        INDEX idx_status (enrollment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($enrollments_sql);
    
    // สร้างตาราง activity_logs
    $activity_logs_sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(50),
        description VARCHAR(255),
        related_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($activity_logs_sql);
    
    echo "✅ สร้างตารางเสริมทั้งหมดเรียบร้อย\n";
}
?>
