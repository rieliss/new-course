<?php
include '../config.php';
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Total courses
$total_query = "SELECT COUNT(*) as count FROM courses";
$total_result = $conn->query($total_query);
$total_courses = $total_result->fetch_assoc()['count'];

// Enrolled courses
$enrolled_query = "SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND enrollment_status = 'enrolled'";
$enrolled_stmt = $conn->prepare($enrolled_query);
$enrolled_stmt->bind_param("i", $user_id);
$enrolled_stmt->execute();
$enrolled_count = $enrolled_stmt->get_result()->fetch_assoc()['count'];

// Total credits
$credits_query = "SELECT SUM(c.credits) as total FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND e.enrollment_status = 'enrolled'";
$credits_stmt = $conn->prepare($credits_query);
$credits_stmt->bind_param("i", $user_id);
$credits_stmt->execute();
$credits_data = $credits_stmt->get_result()->fetch_assoc();
$total_credits = $credits_data['total'] ?? 0;

echo json_encode([
    'total_courses' => $total_courses,
    'enrolled_count' => $enrolled_count,
    'total_credits' => $total_credits
]);
?>
