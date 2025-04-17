# uwuweb – Grade Management System

---

## 1 Purpose & Scope

A lightweight, role‑based grade and attendance tracker for a Slovenian high‑school, built **only** with HTML, CSS, JavaScript, PHP 8+ and MySQL 8. Runs on XAMPP localhost but is portable to any LAMP stack.

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

## 4 Database Schema (single .sql file)

```
-- users & roles
roles(role_id PK, name)
users(user_id PK, username, pass_hash, role_id FK roles, created_at)

-- core entities
students(student_id PK, user_id FK users, first_name, last_name, dob, class_code)
parents(parent_id PK, user_id FK users)
student_parent(student_id FK, parent_id FK, PRIMARY KEY(student_id,parent_id))
teachers(teacher_id PK, user_id FK users)

subjects(subject_id PK, name)
terms(term_id PK, name, start_date, end_date)
classes(class_id PK, subject_id FK, teacher_id FK, term_id FK, title)

-- enrollment & period structure
enrollments(enroll_id PK, student_id FK, class_id FK)
periods(period_id PK, class_id FK, period_date DATE, period_label)

-- grades & attendance
grade_items(item_id PK, class_id FK, name, max_points, weight DEFAULT 1)
grades(grade_id PK, enroll_id FK, item_id FK, points, comment TEXT)
attendance(att_id PK, enroll_id FK, period_id FK, status ENUM('P','A','L'), justification TEXT NULL)
```

*All foreign keys use **``** for simplicity.*

---

## 5 Directory & File Layout

```
uwuweb/
├── index.php            # login / redirect
├── dashboard.php        # single dynamic dashboard for all roles
├── /assets/
│   ├── css/style.css
│   └── js/main.js
├── /includes/
│   ├── db.php           # PDO connection
│   ├── auth.php         # session + role helpers
│   ├── header.php / footer.php
│   └── functions.php    # common util functions
├── /admin/
│   ├── users.php        # add / edit users
│   └── settings.php     # terms, subjects
├── /teacher/
│   ├── gradebook.php
│   └── attendance.php
└── /api/                # simple ajax endpoints (PHP returning JSON)
    ├── grades.php
    └── attendance.php
```

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

---

## 10 Reusable AI Prompt Template

> **System**: You are ChatGPT, helping extend “uwuweb,” a PHP/MySQL grade‑management app. The current architecture is described below.
>
> **Context**:
>
> ```
> {insert this document’s key sections: roles, DB schema, directory layout}
> ```
>
> **Task**: {clear, atomic request, e.g., “Add CSV export for class averages.”}
>
> **Constraints**: Only use HTML, CSS, JS, PHP, MySQL. Keep code minimal and match existing style.
>
> **Output**: Provide updated file list and code snippets to insert.
