# 📚 ระบบลงทะเบียนวิชา (Course Registration System)

ระบบจัดการลงทะเบียนวิชาแบบออนไลน์สำหรับโรงเรียน/มหาวิทยาลัย

## 🎯 คุณสมบัติ

### สำหรับนักเรียน (Student)
- ✅ เข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน
- ✅ ค้นหาและเลือกวิชาที่ต้องการลงทะเบียน
- ✅ ดูรายวิชาที่ลงทะเบียนแล้ว
- ✅ ยกเลิกการลงทะเบียนวิชา
- ✅ ดูจำนวนที่นั่งเหลือในแต่ละวิชา

### สำหรับผู้ดูแลระบบ (Admin)
- ✅ จัดการรายวิชา (เพิ่ม/แก้ไข/ลบ)
- ✅ จัดการผู้ใช้ (เพิ่ม/แก้ไข/ลบ)
- ✅ นำเข้าข้อมูลจาก CSV
- ✅ ส่งออกรายชื่อนักเรียนเป็น Excel/PDF
- ✅ ดูรายการกิจกรรม (Activity Logs)
- ✅ ปิด/เปิดการลงทะเบียน
- ✅ Dashboard สำหรับดูสถิติ

## 📋 ข้อกำหนดเบื้องต้น

- PHP 7.4 หรือสูงกว่า
- MySQL 5.7 หรือสูงกว่า
- phpMyAdmin (ไม่บังคับ แต่ช่วยให้ง่ายขึ้น)

## 🚀 วิธีติดตั้ง

### 1. ดาวน์โหลดและแตกไฟล์

```bash
# แตกไฟล์ zip ไปยัง htdocs (สำหรับ XAMPP)
cd C:\xampp\htdocs
# หรือ /var/www/html (สำหรับ Linux)
unzip course-registration-system.zip
```

### 2. สร้างฐานข้อมูล

#### วิธีที่ 1: ใช้ phpMyAdmin
1. เปิด phpMyAdmin (http://localhost/phpmyadmin)
2. คลิก "ใหม่" เพื่อสร้างฐานข้อมูลใหม่
3. ตั้งชื่อเป็น `course_registration`
4. ไป Tab "นำเข้า" และเลือกไฟล์ `database.sql`
5. คลิก "ไป" เพื่อสร้างตาราทั้งหมด

#### วิธีที่ 2: ใช้ Command Line
```bash
mysql -u root -p < database.sql
```

### 3. ตั้งค่า Config

แก้ไขไฟล์ `config.php`:
```php
$db_host = 'localhost';      // Hostname ของ MySQL
$db_user = 'root';           // Username ของ MySQL
$db_pass = '';               // Password ของ MySQL (เว้นว่างถ้าไม่มี)
$db_name = 'course_registration'; // ชื่อฐานข้อมูล
```

### 4. เข้าสู่ระบบ

เปิด Browser และไปที่:
```
http://localhost/course-registration-system/login.php
```

## 🔐 บัญชีทดลอง

### Admin Account
```
Username: admin
Password: admin123
```

### Student Account
```
Username: student1
Password: 123456
```

## 📁 โครงสร้างโฟลเดอร์

```
course-registration-system/
├── config.php                 # ตั้งค่าฐานข้อมูล
├── database.sql              # Schema ฐานข้อมูล
├── login.php                 # หน้า Login
├── index.php                 # หน้าหลักนักเรียน
├── courses.php               # หน้าเลือกวิชา
├── enrollments.php           # หน้าวิชาที่ลงทะเบียน
├── logout.php                # Logout
├── admin/
│   ├── dashboard.php         # หน้า Dashboard
│   ├── courses-management.php # จัดการวิชา
│   ├── users-management.php  # จัดการผู้ใช้
│   └── activity-logs.php     # ดูกิจกรรม
└── api/
    └── get-student-stats.php # API สำหรับสถิติ
```

## 🎨 Theme & Style

- **Color Scheme**: Purple Gradient (#667eea - #764ba2)
- **Framework**: Pure CSS + Vanilla JavaScript
- **Responsive**: Mobile, Tablet, Desktop

## 📝 ฟีเจอร์เพิ่มเติม

- 🔔 ระบบแจ้งเตือน (Toast/Alert)
- 📊 Activity Logs
- 📥 Import CSV
- 📤 Export Excel/PDF
- 🔐 Password Hashing (bcrypt)
- 📱 Responsive Design
- 🌙 Dark Mode Support (Future)

## 🐛 ปัญหาทั่วไป

### ข้อผิดพลาด: "Connection Failed"
- ตรวจสอบว่า MySQL เปิดใช้งานแล้ว
- ตรวจสอบ config.php มีการตั้งค่า Username และ Password ถูกต้อง

### ข้อผิดพลาด: "Table doesn't exist"
- นำเข้าไฟล์ database.sql ไปยังฐานข้อมูล

### ไม่สามารถเข้าสู่ระบบ
- ลองใช้ username: `admin`, password: `admin123`
- ตรวจสอบการสะกดชื่อผู้ใช้

## 📞 การติดต่อและสนับสนุน

หากมีปัญหาหรือข้อเสนอแนะ กรุณาติดต่อทีมพัฒนา

## 📄 ใบอนุญาต

MIT License - สามารถใช้งานได้อย่างอิสระ

---

**สร้างด้วย ❤️ สำหรับการศึกษา**
