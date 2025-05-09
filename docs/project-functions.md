# uwuweb Function Documentation

This file contains centralized documentation for all functions across the uwuweb application.

## /includes/functions.php

Common Utility Functions Library

### User and Role Management:

- `getUserInfo(int $userId): ?array` - Retrieves comprehensive user profile with role-specific data (teacher_id, student
  details, parent_id with children).

### Security Functions:

- `sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never` - Sends standardized JSON
  error with status code and logs error with context before terminating execution.
- `validateDate(string $date): bool` - Validates date format (YYYY-MM-DD). Returns true if valid.

### Formatting Functions:

- `formatDateDisplay(string $date): string` - Formats date from YYYY-MM-DD to DD.MM.YYYY.
- `formatDateTimeDisplay(string $datetime): string` - Formats datetime to DD.MM.YYYY.

### Navigation and Widgets:

- `getNavItemsByRole(int $role): array` - Returns role-specific navigation menu items with title, URL and icon.
- `getWidgetsByRole(int $role): array` - Returns dashboard widgets configuration appropriate for user role.
- `renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string` - Creates a simple placeholder
  card for widgets without data.

### Activity Widgets:

- `renderRecentActivityWidget(): string` - Generates role-appropriate recent activity summary with different content
  based on user role.

### Attendance Utilities:

- `getAttendanceStatusLabel(string $status): string` - Translates single-letter status code (P/A/L) to human-readable
  label.
- `calculateAttendanceStats(array $attendance): array` - Computes attendance metrics including counts and percentages
  from attendance records.
- `calculateClassAverage(array $grades): float` - Calculates overall grade average for a class.
- `getGradeLetter(float $percentage): string` - Converts percentage to Slovenian letter grade.
- `getJustificationFileInfo(int $absenceId): ?string` - Returns justification file metadata.

## /student/student_functions.php

Student Functions Library - Provides functions for retrieving and managing student grades, attendance, and absence
justifications.

### Student Data Retrieval:

- `getStudentId(): ?int` - Returns student ID for current user, with caching for optimization.
- `getStudentAttendance(int $studentId): array` - Returns student's attendance records.
- `getStudentGrades(int $studentId): array` - Returns student's grades.
- `getClassAverage(int $classId): array` - Returns academic averages for class.
- `getStudentAbsences(int $studentId): array` - Returns student's absence records.
- `getStudentJustifications(int $studentId): array` - Gets justifications submitted by a student.

### Grade Analysis:

- `calculateWeightedAverage(array $grades): float|int` - Computes weighted grade average.
- `calculateGradeStatistics(array $grades): array` - Analyzes grades by subject and class.

### Absence Justifications:

- `uploadJustification(int $absenceId, string $justification): bool` - Stores absence explanation text.
- `validateJustificationFile(array $file): bool` - Checks justification file validity.
- `saveJustificationFile(array $file, int $absenceId): bool|string` - Stores justification file securely.
- `getJustificationFileInfo(int $absenceId): ?string` - Returns justification file metadata.

### Dashboard Widgets:

- `renderStudentGradesWidget(): string` - Displays student's recent grades and subject performance statistics.
- `renderStudentAttendanceWidget(): string` - Shows attendance summary with statistics and recent attendance records.
- `renderStudentClassAveragesWidget(): string` - Compares student's performance against class averages across subjects.
- `renderUpcomingClassesWidget(): string` - Lists student's scheduled classes for the next week organized by day.

## /teacher/teacher_functions.php

Teacher Functions Library - Provides centralized functions for teacher operations including grade management, attendance
tracking, and justification processing.

### Teacher Information Functions:

- `getTeacherId(?int $userId = null): ?int` - Gets teacher_id from user_id or current session user if null.
- `getTeacherClasses(int $teacherId): array` - Returns classes taught by teacher with code, title and subject info.
- `teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool` - Verifies teacher access to
  class-subject. Uses current teacher if $teacherId null.

### Class & Student Management:

- `getClassStudents(int $classId): array` - Returns students enrolled in a class.
- `getClassPeriods(int $classSubjectId): array` - Returns periods for a class-subject.

### Attendance Management:

- `getPeriodAttendance(int $periodId): array` - Returns attendance records for a period.
- `addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): bool|int` - Creates new period for class.
  Returns period_id or false.
- `saveAttendance(int $enrollId, int $periodId, string $status): bool` - Records student attendance status.
- `getStudentAttendanceByDate(int $studentId, string $date): array` - Gets attendance records for a student on a
  specific date.

### Grade Management:

- `getGradeItems(int $classSubjectId): array` - Returns grade items for class-subject after permission check.
- `getClassGrades(int $classSubjectId): array` - Returns all grades for students in a class.
- `addGradeItem(int $classSubjectId, string $name, float $maxPoints, float $weight = 1.00): bool|int` - Creates grade
  item. Returns item_id or false.
- `saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool` - Creates or updates student
  grade.

### Justification Management:

- `getPendingJustifications(?int $teacherId = null): array` - Returns pending justifications for teacher or all if
  admin.
- `getJustificationById(int $absenceId): ?array` - Returns justification details with student info and attachments.
- `approveJustification(int $absenceId): bool` - Approves justification and updates attendance.
- `rejectJustification(int $absenceId, string $reason): bool` - Rejects justification with reason.

### Dashboard Widgets:

- `renderTeacherClassOverviewWidget(): string` - Displays teacher's assigned classes with subject and student count
  information.
- `renderTeacherAttendanceWidget(): string` - Shows today's classes with attendance recording status and quick action
  links.
- `renderTeacherPendingJustificationsWidget(): string` - Lists pending absence justifications awaiting teacher approval.
- `renderTeacherClassAveragesWidget(): string` - Visualizes academic performance averages across teacher's classes.

## /parent/parent_functions.php

Parent Functions Library - Provides centralized functions for parent-specific functionality in the uwuweb system.

### Parent Information Functions:

- `getParentId(): ?int` - Returns parent_id for current user.
- `getParentStudents(?int $parentId = null): array` - Returns linked students for parent.
- `parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool` - Verifies parent's student access.

### Student Data Access Functions:

- `getStudentClasses(int $studentId): array` - Returns student's enrolled classes with subjects.
- `getClassGrades(int $studentId, int $classId): array` - Returns student's grades by subject.

### Attendance and Justification Functions:

- `getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null): array` - Returns student
  attendance with optional date range.
- `parentHasAccessToJustification(int $attId): bool` - Verifies parent's justification access.
- `getJustificationDetails(int $attId): ?array` - Returns justification details for parent.
- `getStudentJustifications(int $studentId): array` - Returns all justifications submitted for a student.

### Dashboard Widgets:

- `renderParentAttendanceWidget(): string` - Displays attendance summary for each child with statistics and recent
  absences.
- `renderParentChildClassAveragesWidget(): string` - Shows academic performance for each child compared to class
  averages.

## /admin/admin_functions.php

Admin Functions Library - Provides centralized functions for administrative operations including user management, system
settings, and class-subject assignments.

### User Management Functions:

- `getAllUsers(): array` - Returns all users with role information.
- `displayUserList(): void` - Renders user management table with action buttons.
- `getUserDetails(int $userId): ?array` - Returns detailed user information with role-specific data.
- `createNewUser(array $userData): bool|int` - Creates user with role. Returns user_id or false.
- `updateUser(int $userId, array $userData): bool` - Updates user information. Returns success status.
- `resetUserPassword(int $userId, string $newPassword): bool` - Sets new user password. Returns success status.
- `deleteUser(int $userId): bool` - Removes user if no dependencies exist. Returns success status.

### Subject Management Functions:

- `getAllSubjects(): array` - Returns all subjects from the database.
- `displaySubjectsList(): void` - Renders subject management table with actions.
- `getSubjectDetails(int $subjectId): ?array` - Returns detailed subject information or null if not found.
- `createSubject(array $subjectData): bool|int` - Creates subject. Returns subject_id or false.
- `updateSubject(int $subjectId, array $subjectData): bool` - Updates subject information. Returns success status.
- `deleteSubject(int $subjectId): bool` - Removes subject if not in use. Returns success status.

### Class Management Functions:

- `getAllClasses(): array` - Returns all classes with homeroom teacher information.
- `displayClassesList(): void` - Renders class management table with actions.
- `getClassDetails(int $classId): ?array` - Returns detailed class information or null if not found.
- `createClass(array $classData): bool|int` - Creates class. Returns class_id or false.
- `updateClass(int $classId, array $classData): bool` - Updates class information. Returns success status.
- `deleteClass(int $classId): bool` - Removes class if no dependencies exist. Returns success status.

### Class-Subject Assignment Functions:

- `assignSubjectToClass(array $assignmentData): bool|int` - Assigns subject to class with teacher. Returns assignment_id
  or false.
- `updateClassSubjectAssignment(int $assignmentId, array $assignmentData): bool` - Updates class-subject assignment.
  Returns success status.
- `removeSubjectFromClass(int $assignmentId): bool` - Removes subject assignment from class. Returns success status.
- `getAllClassSubjectAssignments(): array` - Returns all class-subject assignments with related information.
- `getAllTeachers(): array` - Returns all teachers with their basic information.

### System Settings Functions:

- `getSystemSettings(): array` - Retrieves system-wide settings from the database.
- `updateSystemSettings(array $settings): bool` - Updates system-wide settings. Returns success status.

### Dashboard Widget Functions:

- `renderAdminUserStatsWidget(): string` - Displays user statistics by role with counts and recent registrations.
- `renderAdminSystemStatusWidget(): string` - Shows system status including database stats, active sessions, and PHP
  configuration.
- `renderAdminAttendanceWidget(): string` - Visualizes school-wide attendance metrics with charts and highlights
  best-performing class.

### Validation and Utility Functions:

- `getAllStudentsBasicInfo(): array` - Retrieves basic information for all students.
- `validateUserForm(array $userData): bool|string` - Validates user form data based on role. Returns true or error
  message.
- `usernameExists(string $username, ?int $excludeUserId = null): bool` - Checks if username already exists, optionally
  excluding a user.
- `classCodeExists(string $classCode): bool` - Checks if class code exists. Returns true if found.
- `subjectExists(int $subjectId): bool` - Checks if subject exists. Returns true if found.
- `studentExists(int $studentId): bool` - Checks if student exists. Returns true if found.

## /includes/db.php

Database Connection Handler - Establishes and provides a PDO connection to the uwuweb database. Used by all data access
operations throughout the application.

### Connection Management:

- `getDBConnection(): PDO|null` - Returns PDO connection or null on failure.
- `safeGetDBConnection(string $context = '', bool $terminate = true): PDO|null` - Returns PDO connection or terminates
  with error. Context for logging.
- `testDBConnection(): string` - Tests connection and returns status with MySQL info.

### Error Handling:

- `logDBError(string $error): void` - Logs DB errors to file or PHP error log.

## /includes/auth.php

Authentication and Session Management - Provides functions for user authentication, session management, and role-based
access control.

### Authentication and Session:

- `isLoggedIn(): bool` - Checks if a user is currently logged in based on session data.
- `getUserRole(): int|null` - Returns current user's role ID from session or null if not set.
- `getUserId(): int|null` - Returns current user's ID from session or null if not set.
- `hasRole(int $roleId): bool` - Checks if current user has the specified role.
- `requireRole(int $roleId): bool` - Restricts page access to users with specific role, redirects if unauthorized.
- `checkSessionTimeout(): void` - Checks if the session has timed out due to inactivity and logs out if needed.
- `updateLastActivityTime(): void` - Updates the last activity timestamp in the current session.
- `destroySession(string $reason = ''): void` - Destroys the current session, clears cookies, and redirects to login
  page.

### Security Functions:

- `generateCSRFToken(): string` - Creates a CSRF token for form security and stores in session.
- `verifyCSRFToken(string $token): bool` - Validates submitted CSRF token against the one stored in session.

### Role Management:

- `getRoleName(int $roleId): string` - Returns the name of a role by ID, falls back to database lookup if not in
  predefined list.

## /api/justifications.php

Justifications API Endpoint - Handles CRUD operations for absence justifications via AJAX requests. Returns JSON
responses for client-side processing. Access control based on user role: students can submit, teachers can approve.

### Core API Functions:

- `submitJustification(): void` - Processes student-submitted justification with text and optional file upload.
  Validates student ownership and absence status before recording justification.
- `handleApproveJustification(): void` - Processes teacher approval/rejection of justification with optional rejection
  reason. Performs role and access verification before updating status.
- `getJustifications(): void` - Returns role-filtered justifications with formatted dates and status labels. Results are
  tailored based on user role (all for admin, class-specific for teachers, own for students, children's for parents).
- `getJustificationDetails(): void` - Retrieves detailed justification information by ID. Implements role-based access
  control and formats dates and status labels for display.

### Access Control Functions:

- `teacherHasAccessToJustification(int $attId): bool` - Verifies if current teacher has permission to access a specific
  justification based on class-subject assignment. Returns true if authorized.
- `studentOwnsJustification(int $attId): bool` - Verifies if current student is the owner of a specific attendance
  record/justification. Returns true if student owns the record.

## /api/attendance.php

Attendance API Endpoint - Handles CRUD operations for attendance data via AJAX requests.

### Period Management:

- `handleAddPeriod(): void` - Creates a new period for a class with initial attendance records using POST data (
  class_subject_id, period_date, period_label)
- `handleUpdatePeriod(): void` - Updates date and label information for an existing period using POST data (period_id,
  period_date, period_label)
- `handleDeletePeriod(): void` - Deletes a period and all associated attendance records using POST data (period_id)

### Attendance Recording:

- `handleSaveAttendance(): void` - Saves attendance status ('P', 'A', 'L') for a single student using POST data (
  enroll_id, period_id, status)
- `handleBulkAttendance(): void` - Saves attendance status for multiple students at once using POST data (period_id,
  attendance_data array)

### Justification Management:

- `handleJustifyAbsence(): void` - Records or approves absence justification based on user role using POST data (att_id,
  justification, approved, reject_reason)
- `handleGetStudentAttendance(): void` - Retrieves attendance summary and statistics for a student using POST data (
  student_id, optional date_from, date_to)

## /api/grades.php

Handles CRUD operations for grade data via AJAX requests. Returns JSON responses for client-side processing.

### Grade Item Management:

- `addGradeItem(): void` - Creates a new grade item for a specific class-subject using JSON request data
- `updateGradeItem(): void` - Updates name, max points, and weight for an existing grade item
- `deleteGradeItem(): void` - Removes a grade item and all associated grades
- `saveGrade(): void` - Creates or updates a grade for a student on a specific grade item

### Authorization Functions:

- `teacherHasAccessToClass(int $classId): bool` - Verifies if the current teacher is assigned to the given class
- `teacherHasAccessToGradeItem(int $itemId, int $teacherId): bool` - Verifies if the teacher is authorized to modify the
  given grade item
- `teacherHasAccessToEnrollment(int $enrollId): bool` - Verifies if the current teacher is authorized to modify grades
  for the given enrollment

## /api/admin.php

Handles administrative API functions for the uwuweb system. Returns JSON responses for client-side processing.

### Class and Subject Management:

- `getClassDetails(): void` - Retrieves detailed information about a class including enrolled students and assigned
  subjects
- `getSubjectDetails(): void` - Retrieves detailed information about a subject including assigned classes
- `getTeacherDetails(): void` - Retrieves detailed information about a teacher including assigned classes and subjects
