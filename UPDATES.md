# 🎉 Course Registration System - Updates

## ✨ New Features Added

### 1. 🎯 Filter Courses by Classroom
**File: `courses.php` (Modified)**
- Students can now only see and register for courses that are available for their classroom
- The system automatically filters courses based on the student's `class_room` and the course's `allowed_classes` field
- Uses `FIND_IN_SET()` database function for flexible comma-separated classroom matching
- Example: Student in ม.4/1 will only see courses allowed for ม.4/1, ม.4/2, etc.

**How it works:**
```
When a student visits the course page, the query now includes:
- Student's class_room is checked against course's allowed_classes
- Multiple allowed classes are stored as comma-separated values (e.g., "ม.4/1,ม.4/2,ม.4/3")
- Empty or NULL allowed_classes means the course is available to all classrooms
```

---

### 2. ✏️ Edit Course Information
**File: `admin/course-edit.php` (New)**
- Admin users can now edit all course details
- Fields that can be edited:
  - Course code (รหัสวิชา)
  - Course name (ชื่อวิชา)
  - Teacher name (ชื่ออาจารย์)
  - Credits (หน่วยกิต)
  - Schedule day (วันที่เรียน)
  - Schedule time (เวลาเรียน)
  - Max seats (จำนวนที่นั่ง)
  - Allowed classrooms (ห้องที่อนุญาต)
  - Description (คำอธิบาย)
  - Status (สถานะ - เปิด/ปิด)
- All changes are logged in the activity logs
- Form validation ensures data integrity
- Prevents duplicate course codes

**Access:** Admin Dashboard → Manage Courses → Click "✏️ Edit" button on any course

---

### 3. 📥 Export Students to CSV
**File: `admin/course-export-csv.php` (New)**
- Admins can export all enrolled students for a specific course to CSV format
- Exported file includes:
  - Course information (code, name, teacher, credits, schedule)
  - Student list with:
    - Sequential number (ลำดับที่)
    - Student ID (รหัสนักเรียน)
    - Full name (ชื่อ-สกุล)
    - Classroom (ห้องเรียน)
    - Email
    - Status (active/inactive)
    - Enrollment date
  - Summary statistics (total number of students)
- File is automatically named with course code and timestamp
- UTF-8 BOM encoding for proper Thai character support in Excel
- Exportable at any time without affecting the system

**Access:** Admin Dashboard → Manage Courses → Click "📥 Export CSV" button

**File naming format:** `course_[COURSE_CODE]_YYYY-MM-DD_HHmmss.csv`
Example: `course_CS101_2024-11-03_143022.csv`

---

### 4. 🎓 Promote Students to Next Level (Already Exists)
**File: `admin/course-students.php` (Verified)**
- This feature was already in the system
- Button: "🎓 Promote All Students"
- Functionality:
  - Automatically advances all enrolled students in a course to the next level
  - ม.4 → ม.5, ม.5 → ม.6, ม.6 → ป.1, ป.1 → ป.2, etc.
  - ป.6 (completed) - no promotion
  - All promotions are logged with student names and details
  - Transaction-based: all-or-nothing operation

**Access:** Admin Dashboard → Manage Courses → Click "👨‍🎓 Students" → "🎓 Promote All Students"

---

## 📝 Updated Files

1. **`courses.php`**
   - Added classroom filtering using FIND_IN_SET()
   - Students see only courses available to their classroom
   - Line 22-35: Updated SQL query with class filtering

2. **`admin/courses-management.php`**
   - Updated action buttons for each course
   - Added "✏️ Edit" button → links to course-edit.php
   - Added "📥 Export CSV" button → links to course-export-csv.php
   - Line 487-499: Updated action buttons HTML

3. **`admin/course-edit.php`** (New)
   - Complete course editing interface
   - Form validation and error handling
   - Activity logging for all edits
   - 371 lines of code

4. **`admin/course-export-csv.php`** (New)
   - CSV export functionality
   - Proper UTF-8 encoding with BOM
   - Course and student information
   - 95 lines of code

---

## 🔍 Testing Checklist

### Test 1: Classroom Filtering
- [ ] Login as student in ม.4/1
- [ ] Check that only courses with "ม.4/1" in allowed_classes are shown
- [ ] Login as student in ม.5/1
- [ ] Check that only courses with "ม.5/1" in allowed_classes are shown
- [ ] Verify courses with empty allowed_classes show to all students

### Test 2: Edit Course
- [ ] Login as admin
- [ ] Go to Manage Courses
- [ ] Click "✏️ Edit" on any course
- [ ] Modify course details (e.g., teacher name, schedule)
- [ ] Click "Save"
- [ ] Verify changes appear in course list
- [ ] Check activity logs for the edit action

### Test 3: Export CSV
- [ ] Enroll some students in a course as students
- [ ] Login as admin
- [ ] Go to Manage Courses
- [ ] Click "📥 Export CSV"
- [ ] Verify CSV file downloads with proper name
- [ ] Open CSV in Excel and verify:
  - Thai characters display correctly
  - Student data is present
  - Summary shows correct student count

### Test 4: Promote Students
- [ ] Enroll students in a course
- [ ] Go to Manage Courses → Students
- [ ] Click "🎓 Promote All Students"
- [ ] Verify students' class_room is updated in database
- [ ] Check activity logs for promotion records

---

## 🛠️ Technical Details

### Database Requirements
- Uses existing `allowed_classes` field in courses table
- No new tables required
- No schema changes needed
- Supports comma-separated classroom codes

### PHP Features Used
- MySQLi prepared statements (security)
- Transaction handling (data integrity)
- CSV generation with proper encoding
- Session-based access control
- Activity logging integration

### Security
- All inputs are validated and sanitized
- Admin-only access to edit and export functions
- Database transactions for bulk operations
- SQL injection prevention via prepared statements

---

## 📦 Installation

1. Replace existing files:
   - `courses.php`
   - `admin/courses-management.php`

2. Add new files:
   - `admin/course-edit.php`
   - `admin/course-export-csv.php`

3. No database migration needed - uses existing schema

4. Clear browser cache if needed

---

## 🎯 Usage Examples

### Setting Allowed Classrooms for a Course

In course management, you can set the `allowed_classes` field to:
- `ม.4/1,ม.4/2,ม.4/3` - Only available to 3 classrooms
- `ม.5/1,ม.5/2` - Only available to 2 classrooms
- Leave empty - Available to all classrooms

### CSV Export Uses

- **Attendance Report:** Export and use for marking attendance
- **Class List:** Print out before first class
- **Parent Communication:** Get student contact info
- **Grade Recording:** Transfer grades to another system
- **Audit Trail:** Keep records of who was enrolled when

---

## 📞 Support

For issues or questions about these new features, please contact the system administrator.

---

## 🔄 Version History

- **v2.1** - Added 3 new features (Classroom Filtering, Edit Course, Export CSV)
- **v2.0** - Initial release with promotion feature
