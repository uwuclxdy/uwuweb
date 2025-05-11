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
├── admin/
│ ├── admin_functions.php # Centralized admin functions library
│ ├── manage_assignments.php # Class-subject-teacher assignment management
│ ├── manage_classes.php # Class/homeroom group management
│ ├── manage_subjects.php # Academic subject management
│ ├── settings.php # Settings redirect controller
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
│ └── main.js # Main JavaScript functionality
├── db/
│ ├── migrate_system_settings.sh # Settings migration script
│ ├── seed_demo.sql # Demo data population script
│ └── uwuweb.sql # Main database schema
├── design/
│ ├── modal-examples.php # Examples of modal dialog implementations
│ └── uwuweb-logo.png # Application logo
├── docs/
│ ├── backend-checklist.md # Backend implementation checklist
│ ├── css-readme.md # CSS architecture documentation
│ ├── frontend-checklist.md # Frontend implementation checklist
│ ├── local-setup.md # Local development setup guide
│ ├── modal-guidelines.md # Guidelines for implementing modals
│ ├── project-functions.md # Function reference documentation
│ └── project-outline.md # This file
├── includes/
│ ├── auth.php # Authentication functions and role management
│ ├── db.php # Database connection management
│ ├── footer.php # Common page footer
│ ├── functions.php # Core utility functions
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

### Admin Functions (admin_functions.php)

- **User Management**: getAllUsers, displayUserList, getUserDetails, createNewUser, updateUser, resetUserPassword,
  deleteUser
- **Subject Management**: getAllSubjects, displaySubjectsList, getSubjectDetails, createSubject, updateSubject,
  deleteSubject
- **Class Management**: getAllClasses, displayClassesList, getClassDetails, createClass, updateClass, deleteClass
- **Class-Subject Assignment**: assignSubjectToClass, updateClassSubjectAssignment, removeSubjectFromClass,
  getAllClassSubjectAssignments, getAllTeachers
- **System Settings**: getSystemSettings, updateSystemSettings
- **Dashboard Widgets**: renderAdminUserStatsWidget, renderAdminSystemStatusWidget, renderAdminAttendanceWidget
- **Validation**: getAllStudentsBasicInfo, validateUserForm, usernameExists, classCodeExists, subjectExists,
  studentExists

### Teacher Functions (teacher_functions.php)

- **Teacher Information**: getTeacherId, getTeacherClasses, teacherHasAccessToClassSubject
- **Class & Student Management**: getClassStudents, getClassPeriods
- **Attendance Management**: getPeriodAttendance, addPeriod, saveAttendance, getStudentAttendanceByDate
- **Grade Management**: getGradeItems, getClassGrades, addGradeItem, saveGrade
- **Justification Management**: functions for handling absence justifications

### Student Functions (student_functions.php)

- **Student Data**: getStudentId, getStudentAttendance, getStudentGrades, getClassAverage, getStudentAbsences,
  getStudentJustifications
- **Grade Analysis**: calculateWeightedAverage, calculateGradeStatistics
- **Absence Justifications**: uploadJustification, validateJustificationFile, saveJustificationFile,
  getJustificationFileInfo
- **Dashboard Widgets**: renderStudentGradesWidget, renderStudentAttendanceWidget

### Parent Functions (parent_functions.php)

- **Parent Information**: getParentId, getParentStudents, parentHasAccessToStudent
- **Student Data Access**: getStudentClasses, getClassGrades
- **Attendance and Justification**: getStudentAttendance, parentHasAccessToJustification, getJustificationDetails,
  getStudentJustifications
- **Dashboard Widgets**: renderParentAttendanceWidget, renderParentChildClassAveragesWidget

### Core Includes

- **auth.php**: Authentication, session management, role-based access control
- **db.php**: Database connection handling, error logging
- **functions.php**: Common utilities used across the application

### API Endpoints

- **admin.php**: API endpoints for admin operations such as retrieving class details
- **grades.php**: CRUD operations for grade data (teacher access)
- **attendance.php**: CRUD operations for attendance with role-based access
- **justifications.php**: Handling absence justifications submissions and approvals

---

## 8. Styling & Responsiveness

- Mobile‑first Flexbox/Grid layout.
- Single `style.css`; CSS variables for theme.
- No external CSS frameworks (Bootstrap prohibited).
- Progressive enhancement: core functions work without JS; vanilla JS `fetch()` adds inline edits.
- Use `css-readme.com` for CSS documentation and use as much css classes as possible to make the site look good and
  responsive.

---

## 9. Security Notes (MVP)

- Passwords hashed with `password_hash()` (bcrypt default).
- Prepared PDO statements everywhere.
- CSRF tokens in all forms.
- Session idle timeout 30 min.
- System-wide maintenance mode that restricts access to administrators only.

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
