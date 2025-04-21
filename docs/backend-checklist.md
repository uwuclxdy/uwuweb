# Backend Implementation Checklist

## Core Files
- [ ] `/includes/functions.php` - Core utility functions
- [ ] `/includes/db.php` - Database connection functions
- [ ] `/includes/auth.php` - Authentication functions

## API Endpoints
- [ ] `/api/grades.php` - Grades API endpoint
- [ ] `/api/attendance.php` - Attendance API endpoint
- [ ] `/api/justifications.php` - Justifications API endpoint

## Role-Specific Function Files
- [ ] `/admin/admin_functions.php` - Admin-specific functions
- [ ] `/teacher/teacher_functions.php` - Teacher-specific functions
- [ ] `/student/student_functions.php` - Student-specific functions
- [ ] `/parent/parent_functions.php` - Parent-specific functions

## Implementation Order Recommendation
1. Start with core utilities (`functions.php`)
2. Implement database and authentication modules
3. Build API endpoints
4. Create role-specific function files

## Key Requirements
- Follow all the instructions from project-outline.md
- Use PHP 8.2 and MySQL 8
- PDO with prepared statements for all database queries
- CSRF protection for all forms
- Role-based access control
- Error logging instead of display
- Input sanitization and validation
- Optimized for maintainability and readability
- I previously had in mind to have "terms" but I changed my mind, so remove any mention of "terms" from the code and documentation if you find it.
