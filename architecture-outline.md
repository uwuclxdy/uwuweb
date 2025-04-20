# uwuweb – Grade Management System

## 1 Purpose & Scope

A lightweight, role‑based grade and attendance tracker for a Slovenian high‑school, built **only** with HTML, CSS, JavaScript, PHP 8.2 and MySQL 8. Runs on XAMPP localhost but is portable to any LAMP stack.

---

## 2 User Roles & Permissions

|  Role           |  Key abilities                                                                                                 |
| --------------- | -------------------------------------------------------------------------------------------------------------- |
| Administrator   | Full CRUD on all data; manage academic structure; manage users & passwords; view school‑wide analytics         |
| Teacher         | Manage grades & per‑period attendance for assigned classes; see class averages; approve absence justifications |
| Student         | View own grades & attendance; submit absence justifications                                                    |
| Parent/Guardian | Read‑only view of linked student’s grades & attendance                                                         |

Role checks handled in **auth.php** with `requireRole($role)` helper.

---

## 3 Feature‑Access Matrix (YAML for AI consumption)

```yaml
features:
  grade_book:              {admin: write, teacher: write, student: none, parent: none}
  attendance_per_period:   {admin: write, teacher: write, student: read, parent: read}
  absence_justification:   {admin: none, teacher: approve, student: submit, parent: read}
  class_average_dashboard: {admin: view_all, teacher: view_own, student: view_own, parent: view_own}
  user_management:         {admin: full, teacher: none, student: none, parent: none}
  system_settings_terms:   {admin: full, teacher: none, student: none, parent: none}
```

---

## 4 Database Schema

```
-- users & roles
roles(role_id INT AUTO_INCREMENT PK, name VARCHAR(50) NOT NULL)

users(user_id INT AUTO_INCREMENT PK, username VARCHAR(50) NOT NULL UNIQUE, 
      pass_hash VARCHAR(255) NOT NULL, role_id INT NOT NULL FK roles, 
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

-- core entities
students(student_id INT AUTO_INCREMENT PK, user_id INT NOT NULL UNIQUE FK users, 
         first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, 
         dob DATE NOT NULL, class_code VARCHAR(10) NOT NULL)

parents(parent_id INT AUTO_INCREMENT PK, user_id INT NOT NULL UNIQUE FK users)

student_parent(student_id INT NOT NULL FK students, parent_id INT NOT NULL FK parents, 
               PRIMARY KEY(student_id, parent_id))

teachers(teacher_id INT AUTO_INCREMENT PK, user_id INT NOT NULL UNIQUE FK users)

subjects(subject_id INT AUTO_INCREMENT PK, name VARCHAR(100) NOT NULL)

terms(term_id INT AUTO_INCREMENT PK, name VARCHAR(100) NOT NULL, 
      start_date DATE NOT NULL, end_date DATE NOT NULL)

classes(class_id INT AUTO_INCREMENT PK, subject_id INT NOT NULL FK subjects, 
        teacher_id INT NOT NULL FK teachers, term_id INT NOT NULL FK terms, 
        title VARCHAR(100) NOT NULL)

-- enrollment & period structure
enrollments(enroll_id INT AUTO_INCREMENT PK, student_id INT NOT NULL FK students, 
            class_id INT NOT NULL FK classes, UNIQUE(student_id, class_id))

periods(period_id INT AUTO_INCREMENT PK, class_id INT NOT NULL FK classes, 
        period_date DATE NOT NULL, period_label VARCHAR(50) NOT NULL)

-- grades & attendance
grade_items(item_id INT AUTO_INCREMENT PK, class_id INT NOT NULL FK classes, 
            name VARCHAR(100) NOT NULL, max_points DECIMAL(5,2) NOT NULL, 
            weight DECIMAL(3,2) DEFAULT 1.00)

grades(grade_id INT AUTO_INCREMENT PK, enroll_id INT NOT NULL FK enrollments, 
       item_id INT NOT NULL FK grade_items, points DECIMAL(5,2) NOT NULL, 
       comment TEXT)

attendance(att_id INT AUTO_INCREMENT PK, enroll_id INT NOT NULL FK enrollments, 
           period_id INT NOT NULL FK periods, status ENUM('P','A','L') NOT NULL, 
           justification TEXT, approved BOOLEAN DEFAULT NULL, reject_reason TEXT, 
           justification_file VARCHAR(255) DEFAULT NULL, UNIQUE(enroll_id, period_id))
```

*P = Present, A = Absent, L = Late*

*All tables use InnoDB engine with utf8mb4 character set and utf8mb4_unicode_ci collation*

*All foreign keys use **``** for simplicity.*

---

## 5 Directory & File Layout

uwuweb/
├── index.php                 # login / redirect
├── dashboard.php             # single dynamic dashboard for all roles
├── create_user.php           # utility for user creation
├── db_test.php               # database connection test
├── /assets/
│   ├── css/
│   │   ├── style.css         # main stylesheet
│   └── js/
│       └── main.js
├── /includes/
│   ├── db.php                # PDO connection
│   ├── auth.php              # session + role helpers
│   ├── header.php
│   ├── footer.php
│   ├── functions.php         # common util functions
│   └── logout.php            # session termination
├── /admin/
│   ├── users.php             # add / edit users
│   └── settings.php          # terms, subjects
├── /teacher/
│   ├── gradebook.php
│   ├── attendance.php
│   └── justifications.php    # handle absence justifications
├── /student/
│   ├── grades.php            # view own grades
│   ├── attendance.php        # view own attendance
│   └── justification.php     # submit absence justifications
├── /parent/
│   ├── grades.php            # view child's grades
│   └── attendance.php        # view child's attendance
├── /api/                     # simple ajax endpoints (PHP returning JSON)
│   ├── grades.php
│   ├── attendance.php
│   └── justifications.php
├── /db/
│   ├── uwuweb.sql            # main schema
│   └── seed_demo.sql         # demo data
└── /tests/
    └── session_timeout_test.php

*`dashboard.php`*\* reads `` and loads widgets/links for that role.\*

---

## 6 Page Flow (Happy Path)

**Login → dashboard.php (role‑aware) → feature page → Save → dashboard.php**

---

## 7 Styling & Responsiveness

- Mobile‑first Flexbox/Grid layout.
- Single `style.css`; CSS variables for theme.
- No external CSS frameworks (Bootstrap prohibited).
- Progressive enhancement: core functions work without JS; vanilla JS `fetch()` adds inline edits.

---

## 8 Security Notes (MVP)

- Passwords hashed with `password_hash()` (bcrypt default).
- Prepared PDO statements everywhere.
- CSRF tokens in all forms.
- Session idle timeout 30 min.

---

## 9 Future Extension Hooks

- `/api/` endpoints isolated for easy REST upgrade.
- Add `mail()` helpers when notifications become in‑scope.

## 10 Comments
- NO inline comments.
- Every source file starts with the header block that: explains in short what that script does, lists all functions that are in that file and their short descriptions
