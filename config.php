<?php
// Database Configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'course_registration';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection Failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Define constants
define('BASE_URL', 'http://localhost/course-registration-system/');
define('SITE_NAME', 'ระบบลงทะเบียนวิชา');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Function to check if user is admin
function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: index.php');
        exit();
    }
}

// Function to log activity
function log_activity($user_id, $action, $description, $related_id = null) {
    global $conn;
    $query = "INSERT INTO activity_logs (user_id, action, description, related_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issi", $user_id, $action, $description, $related_id);
    $stmt->execute();
    $stmt->close();
}

// Function to show message
function show_message($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

// Function to get user role
function get_user_role() {
    return $_SESSION['role'] ?? 'student';
}

// Function to format date (Thai)
function format_date_thai($date) {
    $thai_months = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน',
        'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
        'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    $date_obj = new DateTime($date);
    $year = $date_obj->format('Y') + 543;
    $month = (int)$date_obj->format('m') - 1;
    $day = $date_obj->format('d');
    
    return $day . ' ' . $thai_months[$month] . ' ' . $year;
}
?>
