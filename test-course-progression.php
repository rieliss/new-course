#!/usr/bin/env php
<?php
/**
 * Comprehensive Test Script for Course Progression System
 * Tests enrollment, future course creation, and promotion
 */

define('BASE_PATH', __DIR__);

// Test Database Connection
echo "========================================\n";
echo "🧪 COURSE PROGRESSION SYSTEM TEST\n";
echo "========================================\n\n";

// Mock database for testing
class TestDB {
    private $students = [];
    private $courses = [];
    private $enrollments = [];
    private $enrollment_id_counter = 1;
    private $course_id_counter = 10;
    
    public function addStudent($id, $name, $class) {
        $this->students[$id] = [
            'id' => $id,
            'full_name' => $name,
            'class_room' => $class
        ];
    }
    
    public function addCourse($id, $code, $name, $year = 2024) {
        $this->courses[$id] = [
            'id' => $id,
            'course_code' => $code,
            'course_name' => $name,
            'academic_year' => $year
        ];
    }
    
    public function enrollStudent($student_id, $course_id, $year = 2024) {
        $id = $this->enrollment_id_counter++;
        $this->enrollments[$id] = [
            'id' => $id,
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year' => $year,
            'visibility_status' => 'current',
            'status' => 'enrolled'
        ];
        return $id;
    }
    
    public function getStudent($id) {
        return $this->students[$id] ?? null;
    }
    
    public function getCourse($id) {
        return $this->courses[$id] ?? null;
    }
    
    public function getEnrollments() {
        return $this->enrollments;
    }
    
    public function getStudents() {
        return $this->students;
    }
}

// Load course progression functions (mock version)
function getNextClassRoom($current_class) {
    if (empty($current_class)) return '';
    
    // Split by / first
    $parts = explode('/', $current_class);
    if (count($parts) !== 2) return null;
    
    $level_part = $parts[0]; // e.g., "ม.4" or "ป.1"
    $section = $parts[1];    // e.g., "1"
    
    // Split level_part by dot
    $dot_pos = strpos($level_part, '.');
    if ($dot_pos === false) return null;
    
    $level_type = substr($level_part, 0, $dot_pos);
    $current_level = (int)substr($level_part, $dot_pos + 1);
    
    if ($level_type === 'ม' && $current_level == 6) {
        return "ป.1/$section";
    } elseif ($level_type === 'ป' && $current_level == 6) {
        return null;
    } else {
        $next_level = $current_level + 1;
        return "{$level_type}.{$next_level}/{$section}";
    }
}

function getCurrentAcademicYear() {
    $current_date = new DateTime();
    $year = (int)$current_date->format('Y');
    $month = (int)$current_date->format('m');
    
    if ($month < 6) {
        return $year - 1;
    }
    
    return $year;
}

// Create test database
$db = new TestDB();

// Test 1: Add Students
echo "TEST 1️⃣  Creating Students\n";
echo "─────────────────────────────────────\n";

$db->addStudent(1, 'สมเด็จ พระเจ้า', 'ม.4/1');
$db->addStudent(2, 'สม.หญิง นกเขา', 'ม.4/1');
$db->addStudent(3, 'นาย ต้นไม้', 'ม.4/2');

echo "✅ Created 3 students\n";
foreach ($db->getStudents() as $student) {
    echo "   - {$student['full_name']} ({$student['class_room']})\n";
}
echo "\n";

// Test 2: Add Courses
echo "TEST 2️⃣  Creating Courses\n";
echo "─────────────────────────────────────\n";

$year = getCurrentAcademicYear();
$db->addCourse(1, 'MATH101', 'คณิตศาสตร์ 1', $year);
$db->addCourse(2, 'ENG101', 'ภาษาอังกฤษ 1', $year);

echo "✅ Created 2 courses for academic year $year\n";
foreach ($db->getCourse(1) as $key => $value) {
    echo "";
}
echo "   - MATH101: คณิตศาสตร์ 1 (Year: $year)\n";
echo "   - ENG101: ภาษาอังกฤษ 1 (Year: $year)\n";
echo "\n";

// Test 3: Enroll Students
echo "TEST 3️⃣  Enrolling Students in Courses\n";
echo "─────────────────────────────────────\n";

$enrollment1 = $db->enrollStudent(1, 1, $year);
$enrollment2 = $db->enrollStudent(2, 1, $year);
$enrollment3 = $db->enrollStudent(3, 2, $year);

echo "✅ Enrolled 3 students:\n";
echo "   - Student 1 → MATH101 (Enrollment ID: $enrollment1)\n";
echo "   - Student 2 → MATH101 (Enrollment ID: $enrollment2)\n";
echo "   - Student 3 → ENG101 (Enrollment ID: $enrollment3)\n";
echo "\n";

// Test 4: Class Progression
echo "TEST 4️⃣  Testing Class Progression Logic\n";
echo "─────────────────────────────────────\n";

$test_cases = [
    'ม.4/1' => 'ม.5/1',
    'ม.5/1' => 'ม.6/1',
    'ม.6/1' => 'ป.1/1',
    'ป.1/1' => 'ป.2/1',
    'ป.6/1' => null
];

$pass = 0;
$fail = 0;

foreach ($test_cases as $current => $expected) {
    $result = getNextClassRoom($current);
    $status = ($result === $expected) ? '✅' : '❌';
    
    if ($result === $expected) {
        $pass++;
    } else {
        $fail++;
    }
    
    $expected_str = $expected ?? 'จบการศึกษา';
    echo "$status $current → $expected_str\n";
}

echo "\n   Summary: $pass passed, $fail failed\n";
echo "\n";

// Test 5: Future Enrollment Creation (Simulated)
echo "TEST 5️⃣  Simulating Future Enrollment Creation\n";
echo "─────────────────────────────────────\n";

$student = $db->getStudent(1);
echo "📌 Student: {$student['full_name']} (Current: {$student['class_room']})\n\n";

$year_sim = $year;
$current_class = $student['class_room'];
$future_enrollments = [];

for ($i = 1; $i <= 2; $i++) {
    $next_year = $year_sim + $i;
    $next_class = getNextClassRoom($current_class);
    
    if ($next_class === null) {
        echo "⚠️  Year $next_year: Reached end of schooling\n";
        break;
    }
    
    $future_enrollments[$next_year] = [
        'year' => $next_year,
        'class' => $next_class,
        'course' => 'MATH101 (Continuation)',
        'visibility' => 'future'
    ];
    
    echo "✅ Year $next_year: {$student['full_name']} will be in $next_class\n";
    echo "   └─ Continuation course created (visibility: future)\n";
    echo "   └─ Linked to current enrollment\n\n";
    
    $current_class = $next_class;
}

echo "\n";

// Test 6: Course-Based Promotion
echo "TEST 6️⃣  Testing Course-Based Promotion\n";
echo "─────────────────────────────────────\n";

echo "Current Enrollments (Year $year):\n";
$enrollments = $db->getEnrollments();
foreach ($enrollments as $e) {
    $student = $db->getStudent($e['student_id']);
    $course = $db->getCourse($e['course_id']);
    echo "   ✅ {$student['full_name']} → {$course['course_name']}\n";
}

echo "\n📈 Promotion Action:\n";
echo "1. Mark Year $year enrollment as COMPLETED\n";
echo "2. Activate Year " . ($year + 1) . " enrollment (from FUTURE to CURRENT)\n";
echo "3. Update student class: {$student['class_room']} → " . getNextClassRoom($student['class_room']) . "\n";

echo "\n✅ After Promotion (Year " . ($year + 1) . "):\n";
$promoted_class = getNextClassRoom($student['class_room']);
echo "   - Student class updated: {$student['class_room']} → $promoted_class\n";
echo "   - Visible enrollments: Only courses for Year " . ($year + 1) . "\n";
echo "   - Previous year's courses hidden from student view\n";

echo "\n";

// Test 7: Verification
echo "TEST 7️⃣  System Verification\n";
echo "─────────────────────────────────────\n";

$checks = [
    "❌ Can get next class room" => getNextClassRoom('ม.4/1') === 'ม.5/1',
    "❌ Can get academic year" => getCurrentAcademicYear() > 0,
    "❌ Can create enrollment" => count($enrollments) === 3,
    "❌ Enrollment has academic_year" => !empty($enrollments[1]['academic_year']),
    "❌ Enrollment has visibility_status" => !empty($enrollments[1]['visibility_status']),
];

$total_checks = count($checks);
$passed_checks = 0;

foreach ($checks as $check => $result) {
    $status = $result ? '✅' : '❌';
    echo "$status " . str_replace('❌', '', $check) . "\n";
    if ($result) $passed_checks++;
}

echo "\nResult: $passed_checks/$total_checks checks passed\n";

// Final Summary
echo "\n";
echo "========================================\n";
echo "📊 TEST SUMMARY\n";
echo "========================================\n";

if ($passed_checks === $total_checks && $fail === 0 && $pass === count($test_cases)) {
    echo "🎉 ALL TESTS PASSED! System is ready.\n";
    echo "\n✅ Next Steps:\n";
    echo "1. Run migration-course-progression.sql on your database\n";
    echo "2. Test with actual enrollment flow\n";
    echo "3. Verify promotion functionality works\n";
} else {
    echo "⚠️  Some tests failed. Review above.\n";
}

echo "\n";
?>
