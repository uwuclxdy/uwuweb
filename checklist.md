# uwuweb Task Checklist (auto‑updated by AI)

- [x] Import `db/uwuweb.sql` and confirm PDO connection (`includes/db.php`).
- [x] Build `dashboard.php` role switcher:
  - [x] Detect role via `$_SESSION['role_id']`.
  - [x] Load matching widgets/nav links.
- [x] Teacher grade book – Skeleton HTML (`teacher/gradebook.php`).
- [x] Teacher grade book – JS inline edit (calls `/api/grades.php`).
- [x] API: `api/grades.php` save logic with validation.
- [x] Teacher attendance – Form page (`teacher/attendance.php`).
- [x] API: `api/attendance.php` CRUD.
- [x] Show attendance summary widgets on dashboard.
- [x] Student absence justification upload (`student/justification.php`).
- [x] Teacher justification approval path.
- [x] Admin user CRUD (`admin/users.php`, reset password).
- [x] Admin settings CRUD (`admin/settings.php`).
- [x] Responsive styling tweaks.
- [x] CSRF tokens on all forms.
- [x] Input sanitization helpers in `includes/functions.php`.
- [x] Seed demo data script (`db/seed_demo.sql`).

## Architecture Alignment Tasks

- [ ] Verify header documentation in all source files follows the format specified in architecture-outline.md section 10 (short description, function list with descriptions).
- [ ] Ensure dashboard.php correctly displays role-specific widgets and links based on the user's role as specified in architecture-outline.md section 5.
- [ ] Verify all class_average_dashboard feature implementations match the access levels in the Feature-Access Matrix (section 3).
- [ ] Check all forms to ensure they include CSRF tokens as per security notes in section 8.
- [ ] Audit all database access to ensure prepared PDO statements are used consistently throughout the codebase.
- [ ] Review student/parent views to ensure they match read-only access levels defined in the Feature-Access Matrix.
- [ ] Test and verify session timeout functionality works as expected (30 min as per section 8).
- [ ] Document the additional features not explicitly mentioned in architecture-outline.md (justification system files).

## Additional Tasks for Architecture Compliance

- [ ] Update architecture-outline.md to document the parent/ directory and student/ directory structure, which were implemented but not originally listed in the directory layout.
- [ ] Verify that the navigation between pages works properly.
- [ ] Audit style.css to ensure it follows the styling guidelines in section 7 (mobile-first, no external frameworks, progressive enhancement).
- [ ] Check if main.js implements the progressive enhancement approach mentioned in section 7 where core functions work without JS.
- [ ] Verify the database schema in uwuweb.sql matches exactly what's defined in section 4 of the architecture document.