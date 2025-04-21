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

look at the file `db/uwuweb.sql` for that.


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
