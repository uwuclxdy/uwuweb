# uwuweb – Grade Management System

## 1. Purpose & Scope

A lightweight, role‑based grade and attendance tracker for a Slovenian high‑school, built **only** with HTML, CSS,
JavaScript, PHP 8.2 and MySQL 8. Runs on XAMPP localhost but is portable to any LAMP stack. All the text in the
application **must** be in Slovenian, but the code is in English. The system is designed to be simple and easy to use,
with a focus on code readability, maintainability and expandability.

---

## 2. User Roles & Permissions

| Role            | Key abilities                                                                                                  |
|-----------------|----------------------------------------------------------------------------------------------------------------|
| Administrator   | Full CRUD on all data; manage academic structure; manage users & passwords; view school‑wide analytics         |
| Teacher         | Manage grades & per‑period attendance for assigned classes; see class averages; approve absence justifications |
| Student         | View own grades & attendance; submit absence justifications                                                    |
| Parent/Guardian | Read‑only view of linked student's grades & attendance                                                         |

Role checks handled in **auth.php** with `requireRole($role)` helper.

---

## 3. Feature‑Access Matrix (YAML for AI consumption)

```yaml
features:
  grade_book:              {admin: write, teacher: write, student: none, parent: none}
  attendance_per_period:   {admin: write, teacher: write, student: read, parent: read}
  absence_justification:   {admin: none, teacher: approve, student: submit, parent: read}
  class_average_dashboard: {admin: none, teacher: view_own, student: view_own, parent: view_own}
  school_management:       {admin: full, teacher: none, student: none, parent: none}
```

---

## 4. Database Schema

Look at the file `db/uwuweb.sql` for database schema.

---

## 5. Directory & File Layout

uwuweb/
├── index.php # login / redirect
├── dashboard.php # single dynamic dashboard for all roles
├── create_user.php # utility for user creation
├── db_test.php # database connection test
├── /assets/
│ ├── css/
│ │ ├── style.css # main stylesheet
│ └── js/
│ └── main.js
├── /docs/ # project documentation
│ ├── project-outline.md # system architecture overview
│ ├── backend-checklist.md # backend implementation guidelines
│ ├── frontend-checklist.md # frontend implementation guidelines
│ ├── css-readme.md # CSS documentation and guidelines
| ├── project-functions.md # centralized function documentation
│ └── local-setup.md # local development setup instructions
├── /includes/
│ ├── db.php # PDO connection
│ ├── auth.php # session + role helpers
│ ├── header.php # common header - includes the css which can then be used in the html code for that page
│ ├── footer.php
│ ├── functions.php # common util functions
│ └── logout.php # session termination
├── /admin/
│ ├── admin_functions.php # centralized admin functions library
│ ├── users.php # user management
│ ├── settings.php # subjects, classes, periods, etc. management
│ └── class_subjects.php # class-subject management
├── /teacher/
│ ├── teacher_functions.php # centralized teacher functions library
│ ├── gradebook.php # manage grades
│ ├── attendance.php # manage attendance
│ └── justifications.php # handle absence justifications
├── /student/
│ ├── student_functions.php # centralized student functions library
│ ├── grades.php # view own grades
│ ├── attendance.php # view own attendance
│ └── justification.php # submit absence justifications
├── /parent/
│ ├── parent_functions.php # centralized parent functions library
│ ├── grades.php # view child's grades
│ └── attendance.php # view child's attendance
├── /api/ # simple ajax endpoints (PHP returning JSON)
│ ├── grades.php
│ ├── attendance.php
│ └── justifications.php
├── /db/
│ ├── uwuweb.sql # main schema
│ └── seed_demo.sql # demo data
└── /tests/
└── session_timeout_test.php

*`dashboard.php`* reads role and loads widgets/links for that role.

---

## 6. Page Flow (Happy Path)

**Login → dashboard.php (role‑aware) → feature page → Save → dashboard.php**

---

## 7. Styling & Responsiveness

- Mobile‑first Flexbox/Grid layout.
- Single `style.css`; CSS variables for theme.
- No external CSS frameworks (Bootstrap prohibited).
- Progressive enhancement: core functions work without JS; vanilla JS `fetch()` adds inline edits.
- Use `css-readme.com` for CSS documentation and use as much css classes as possible to make the site look good and
  responsive.

---

## 8. Security Notes (MVP)

- Passwords hashed with `password_hash()` (bcrypt default).
- Prepared PDO statements everywhere.
- CSRF tokens in all forms.
- Session idle timeout 30 min.

---

## 9. Future Extension Hooks

- `/api/` endpoints isolated for easy REST upgrade.
- Documentation of public functions in the `project-functions.md` file. This file exists so you don't need to read the
  code to understand what the functions do. It is a good idea to keep it up to date, so you don't have to read the code
  every time you want to understand what a function does.

## 10. Comments & Code Structure

- NO inline comments.
- Short description of each function in the function header (max 1-2 lines), including datatypes of function arguments
  and datatypes of what a function returns.
- Functions consolidated in role-specific `[subfolder]_functions.php` files (e.g., `admin_functions.php` for admin
  subfolder).
- Each page file includes the appropriate centralized functions file and contains only processing logic with function
  calls.
- Every source file starts with header block containing: purpose explanation, relative path to that file.
- Role-based function files include parameter descriptions and return values for each function.
- Separation of concerns:
    - Logic (functions) in centralized functions files that cannot be accessed via browser
    - Processing in page files (data retrieval, calculations, UI code - HTML)
- The `[subfolder]_functions.php` file serves as a complete API for that role's functionality.

## 11. Common Errors to Avoid

- `Missing function's return type declaration` when defining functions
- `Missing parameter's type declaration` when defining functions
- `Missing return type declaration` when defining functions
- `Column name must be either aggregated, or mentioned in GROUP BY clause` errors, caused by
  `ORDER BY s.name, gi.name";`
- Fix `Unhandled \JsonException` by using a predefined function - sendJsonErrorResponse(string $message, int $
  statusCode = 400, string $context = ''): never
- Fix `[EA] Null pointer exception may occur here.` by setting returning an error code, JSON response error message and
  terminating the script using a predefined function - sendJsonErrorResponse(string $message, int $statusCode = 400,
  string $context = ''): never
