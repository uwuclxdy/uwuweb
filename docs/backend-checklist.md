# Backend Implementation Checklist

## Core Files

- [x] `/includes/functions.php` - Core utility functions
- [x] `/includes/db.php` - Database connection functions
- [x] `/includes/auth.php` - Authentication functions

## API Endpoints

- [x] `/api/grades.php` - Grades API endpoint
- [x] `/api/attendance.php` - Attendance API endpoint
- [x] `/api/justifications.php` - Justifications API endpoint

## Role-Specific Function Files

- [x] `/admin/admin_functions.php` - Admin-specific functions
- [x] `/teacher/teacher_functions.php` - Teacher-specific functions
- [x] `/student/student_functions.php` - Student-specific functions
- [x] `/parent/parent_functions.php` - Parent-specific functions

Start by implementing core files, continue with API endpoints and Role-Specific Function Files at the end.

## Key Requirements

- Follow all the instructions from project-outline.md
- Use PHP 8.2 and MySQL 8
- PDO with prepared statements for all database queries
- CSRF protection for all forms
- Role-based access control
- Error logging instead of display
- Input sanitization and validation
- Optimized for maintainability and readability
- I previously had in mind to have "terms" but I changed my mind, so remove any mention of "terms" from the code and
  documentation if you find it.
