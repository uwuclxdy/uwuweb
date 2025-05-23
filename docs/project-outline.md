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

## 5. Grading System

Gradable items can have different amount of points, but the grade is always calculated with percentage.
| Grade | Percentage |
|-------|------------|
| 1 | 0% - 49% |
| 2 | 50% - 60% |
| 3 | 61% - 74% |
| 4 | 75% - 88% |
| 5 | 89% - 100% |

---

## 4. Database Schema

Look at the file `db/uwuweb.sql` for database schema.

---

## 5. Directory & File Layout

uwuweb/
├── admin/
│ ├── admin_functions.php # Centralized admin functions library
│ ├── manage_assignments.php # Class-subject-teacher assignment management
│ ├── manage_classes.php # Class/homeroom group management
│ ├── manage_subjects.php # Academic subject management
│ ├── system_settings.php # School-wide system settings
│ └── users.php # User management interface
├── api/
│ ├── admin.php # Admin API endpoints
│ ├── attendance.php # Attendance data API endpoints
│ ├── grades.php # Grades data API endpoints
│ └── justifications.php # Absence justification API endpoints
├── assets/
│ ├── css/
│ │ └── style.css # Main stylesheet
│ └── js/
│ └──── main.js # Main JavaScript functionality
├── db/
│ ├── seed_demo.sql # Demo data population script
│ └── uwuweb.sql # Main database schema
├── design/
│ ├── modal-examples.php # Examples of modal dialog implementations
│ ├── uwuweb-logo-old.png # Old application logo
│ └── uwuweb-logo.png # Current application logo
├── docs/
│ ├── alert-guidelines.md # Guidelines for implementing alerts
│ ├── backend-checklist.md # Backend implementation checklist
│ ├── css-readme.md # CSS architecture documentation
│ ├── frontend-checklist.md # Frontend implementation checklist
│ ├── local-setup.md # Local development setup guide
│ ├── modal-guidelines.md # Guidelines for implementing modals
│ ├── project-functions.md # Function reference documentation
│ ├── project-outline.md # This file
│ └── teacher-checklist.md # Teacher view implementation checklist
├── includes/
│ ├── auth.php # Authentication functions and role management
│ ├── db.php # Database connection management
│ ├── footer.php # Common page footer
│ ├── functions.php # Core utility functions for all roles
│ ├── header.php # Common page header
│ └── logout.php # Session termination
├── parent/
│ ├── attendance.php # Parent view of student attendance
│ ├── grades.php # Parent view of student grades
│ └── parent_functions.php # Parent role functions library
├── student/
│ ├── attendance.php # Student attendance view
│ ├── grades.php # Student grades view
│ ├── justification.php # Absence justification submission
│ └── student_functions.php # Student role functions library
├── teacher/
│ ├── attendance.php # Attendance management interface
│ ├── download_justification.php # Secure file download handler
│ ├── gradebook.php # Grade management interface
│ ├── justifications.php # Justification approval interface
│ └── teacher_functions.php # Teacher role functions library
├── create_user.php # User creation script
├── dashboard.php # Role-aware main dashboard
├── db_test.php # Database connection testing
├── index.php # Main application entry point
├── temp.php # Temporary file for development testing
└── qodana.yaml # Code quality configuration

*`dashboard.php`* reads role and loads widgets/links for that role.

---

## 6. Page Flow (Happy Path)

**Login → dashboard.php (role‑aware) → feature page → Save → dashboard.php**

---

## 7. Core File Functions and APIs

Check out the `project-functions.md` file for a comprehensive list of functions and APIs before checking individual
files.

---

## 8. Styling & Responsiveness

- Mobile‑first Flexbox/Grid layout.
- Single `style.css`; CSS variables for theme.
- No external CSS frameworks (Bootstrap prohibited).
- Progressive enhancement: core functions work without JS; vanilla JS `fetch()` adds inline edits.
- Alert styling and implementation should follow `alert-guidelines.md`.
- Modal dialogs should follow patterns outlined in `modal-guidelines.md`.

---

## 9. Security Notes (MVP)

- Passwords hashed with `password_hash()` (bcrypt default).
- Prepared PDO statements everywhere.
- CSRF tokens in all forms.
- Session idle timeout 30 min.
- System-wide maintenance mode that restricts access to administrators only.
- Secure file downloads with proper authorization checks.

---

## 10. Future Extension Hooks

- `/api/` endpoints isolated for easy REST upgrade.
- Documentation of public functions in the `project-functions.md` file. This file exists so you don't need to read the
  code to understand what the functions do. It is a good idea to keep it up to date, so you don't have to read the code
  every time you want to understand what a function does.

## 11. Comments & Code Structure

- NO inline comments.
- Short description of each function in the function header (max 1-2 lines), including datatypes of function arguments
  and datatypes of what a function returns.
- Simple comment above each larger code section
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
- Core functionality for attendance, grades, and justifications is centralized in `/includes/functions.php` with
  role-specific functions in their respective files.

## 12. Common Errors to Avoid

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
