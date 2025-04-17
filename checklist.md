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
- [ ] Teacher justification approval path.
- [ ] Admin user CRUD (`admin/users.php`, reset password).
- [ ] Admin settings CRUD (`admin/settings.php`).
- [ ] Responsive styling tweaks.
- [ ] CSRF tokens on all forms.
- [ ] Input sanitization helpers in `includes/functions.php`.
- [ ] Seed demo data script (`db/seed_demo.sql`).