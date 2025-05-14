# uwuweb Function Documentation

This document provides a centralized reference for all functions in the uwuweb application, organized by file location
and functional category.

## /includes/db.php

Database connection management functions.

- `logDBError(string $error): void` - Logs database errors to a file
- `getDBConnection(): ?PDO` - Creates and returns a PDO database connection
- `safeGetDBConnection(string $context = '', bool $terminate = true): ?PDO` - Gets a PDO connection or terminates
  execution if it fails
- `testDBConnection(): string` - Tests database connectivity and returns status message

## /includes/auth.php

Authentication and session management functions.

- `isLoggedIn(): bool` - Checks if a user is currently logged in
- `checkSessionTimeout(): void` - Checks if the session has timed out due to inactivity
- `updateLastActivityTime(): void` - Updates the last activity timestamp
- `destroySession(string $reason = ''): void` - Destroys current session and redirects to login

### User Information

- `getUserRole(): int|null` - Returns the current user's role ID from session
- `getUserId(): int|null` - Returns the current user's ID from session
- `hasRole(int $roleId): bool` - Checks if current user has a specific role
- `getRoleName(?int $roleId): string` - Returns the name of a role by ID

### Access Control

- `requireRole(int $roleId): bool` - Restricts page access to users with specific role

### Security

- `generateCSRFToken(): string` - Creates a CSRF token for form security
- `verifyCSRFToken(string $token): bool` - Validates submitted CSRF token

## /includes/functions.php

Core utility functions library.

### User Management Functions

- `getUserInfo(int $userId): ?array` - Retrieves comprehensive user profile with role-specific data
- `getStudentId(): ?int` - Retrieves the student ID associated with the current user
- `getTeacherId(?int $userId = null): ?int` - Retrieves the teacher ID associated with a user
- `getParentId(): ?int` - Retrieves the parent ID associated with the current user
- `parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool` - Checks if a parent has access to a specific
  student's data
- `teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool` - Checks if a teacher has access
  to a specific class-subject combination

### Navigation & UI Functions

- `getNavItemsByRole(int $role): array` - Returns navigation menu items based on user role
- `getWidgetsByRole(int $role): array` - Returns dashboard widgets based on user role
- `renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string` - Renders a placeholder widget
  when data is unavailable
- `renderHeaderCard(string $title, string $description, string $role, ?string $roleText = null): void` - Renders a
  header card with title, description and role badge
- `renderRecentActivityWidget(): string` - Renders the recent activity widget

### Class/Subject Functions

- `getTeacherClasses(int $teacherId): array` - Retrieves all classes assigned to a teacher
- `getClassStudents(int $classId): array` - Gets all students enrolled in a specific class
- `getClassPeriods(int $classSubjectId): array` - Retrieves periods for a specific class-subject
- `getParentStudents(?int $parentId = null): array` - Retrieves students linked to a parent
- `getStudentClasses(int $studentId): array` - Gets classes and subjects for a specific student

### Attendance Functions

- `getAttendanceStatusLabel(string $status): string` - Translates attendance status code to readable label
- `calculateAttendanceStats(array $attendance): array` - Calculates statistics from attendance records
-
`getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null, bool $checkAccess = true): array` -
Gets attendance records for a student
- `getStudentAttendanceByDate(int $studentId, string $date): array` - Gets student attendance for a specific date
- `getPeriodAttendance(int $periodId): array` - Retrieves attendance for all students in a period
- `addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): int|false` - Creates a new period and
  initializes attendance records
- `saveAttendance(int $enrollId, int $periodId, string $status): bool` - Updates or creates an attendance record

### Grade Functions

- `getGradeItems(int $classSubjectId): array` - Gets grade items for a class-subject
- `getClassGrades(int $classSubjectId): array` - Gets grades for all students and grade items in a class-subject
- `addGradeItem(int $classSubjectId, string $name, float $maxPoints, string $date): int|false` - Creates a new grade
  item
- `updateGradeItem(int $itemId, string $name, float $maxPoints, string $date): bool` - Updates a grade item
- `deleteGradeItem(int $enrollId, int $itemId): bool` - Deletes a grade item
- `saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool` - Updates or creates a grade
  record
- `calculateAverage(array $grades): float` - Calculate average for a set of grades
- `calculateClassAverage(array $grades): float` - Calculate overall grade average for a class
- `getGradeLetter(float $percentage): string` - Converts a numerical percentage to a letter grade

### Justification Functions

- `getJustificationFileInfo(int $absenceId): ?string` - Gets information about a justification file
- `uploadJustification(int $absenceId, string $justification): bool` - Uploads a justification for an absence
- `validateJustificationFile(array $file): bool` - Validates an uploaded justification file
- `saveJustificationFile(array $file, int $absenceId): string|false` - Saves an uploaded justification file
- `getPendingJustifications(?int $teacherId = null): array` - Gets pending justifications for a teacher
- `getJustificationById(int $absenceId): ?array` - Gets detailed information about a justification
- `approveJustification(int $absenceId): bool` - Approves a justification
- `rejectJustification(int $absenceId, string $reason): bool` - Rejects a justification

### Utility Functions

- `validateDate(string $date): bool` - Validates a date format (YYYY-MM-DD)
- `formatDateDisplay(string $date): string` - Formats date for display (YYYY-MM-DD to DD.MM.YYYY)
- `formatDateTimeDisplay(string $datetime): string` - Formats datetime for display
- `formatFileSize(int $bytes): string` - Formats file size to human-readable string
- `sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never` - Sends a standardized
  JSON error response

## /admin/admin_functions.php

Administrative operation functions.

### User Management Functions

- `getAllUsers(): array` - Retrieves all users with their role information
- `displayUserList(): void` - Displays a table of all users with management actions
- `getUserDetails(int $userId): ?array` - Fetches detailed information about a specific user
- `createNewUser(array $userData): bool|int` - Creates a new user with specified role
- `updateUser(int $userId, array $userData): bool` - Updates an existing user's information
- `resetUserPassword(int $userId, string $newPassword): bool` - Resets a user's password
- `deleteUser(int $userId): bool` - Deletes a user if they have no dependencies
- `handleCreateUser(): void` - Creates a new user based on form data
- `handleUpdateUser(): void` - Updates an existing user based on form data
- `handleResetPassword(): void` - Resets a user's password
- `handleDeleteUser(): void` - Deletes a user after confirmation

### Subject Management Functions

- `getAllSubjects(): array` - Retrieves all subjects
- `displaySubjectsList(): void` - Displays a table of all subjects with management actions
- `getSubjectDetails(int $subjectId): ?array` - Fetches detailed information about a specific subject
- `createSubject(array $subjectData): bool|int` - Creates a new subject
- `updateSubject(int $subjectId, array $subjectData): bool` - Updates an existing subject
- `deleteSubject(int $subjectId): bool` - Deletes a subject if it's not in use

### Class Management Functions

- `getAllClasses(): array` - Retrieves all classes
- `displayClassesList(): void` - Displays a table of all classes with management actions
- `getClassDetails(int $classId): ?array` - Fetches detailed information about a specific class
- `createClass(array $classData): bool|int` - Creates a new class
- `updateClass(int $classId, array $classData): bool` - Updates an existing class
- `deleteClass(int $classId): bool` - Deletes a class if it has no dependencies

### Class-Subject Assignment Functions

- `assignSubjectToClass(array $assignmentData): bool|int` - Assigns a subject to a class with a specific teacher
- `updateClassSubjectAssignment(int $assignmentId, array $assignmentData): bool` - Updates a class-subject assignment
- `removeSubjectFromClass(int $assignmentId): bool` - Removes a subject assignment from a class
- `getAllClassSubjectAssignments(): array` - Gets all class-subject assignments
- `getAllTeachers(): array` - Gets all available teachers

### System Settings Functions

- `getSystemSettings(): array` - Retrieves system-wide settings
- `updateSystemSettings(array $settings): bool` - Updates system-wide settings

### Dashboard Widget Functions

- `renderAdminUserStatsWidget(): string` - Displays user statistics by role with counts and recent registrations
- `renderAdminSystemStatusWidget(): string` - Shows system status including database stats, active sessions, and PHP
  configuration
- `renderAdminAttendanceWidget(): string` - Visualizes school-wide attendance metrics with charts and highlights
  best-performing class

### Validation and Utility Functions

- `getAllStudentsBasicInfo(): array` - Retrieves basic information for all students
- `validateUserForm(array $userData): bool|string` - Validates user form data based on role
- `usernameExists(string $username, ?int $excludeUserId = null): bool` - Checks if username already exists
- `classCodeExists(string $classCode): bool` - Checks if class code exists
- `subjectExists(int $subjectId): bool` - Checks if subject exists
- `studentExists(int $studentId): bool` - Checks if student exists

## /api/admin.php

Administrative API endpoints.

- `handleGetClassDetails(): void` - Returns detailed information about a class including students and subjects
- `handleGetSubjectDetails(): void` - Returns detailed information about a subject including assigned classes
- `handleGetTeacherDetails(): void` - Returns detailed information about a teacher including classes and assignments
- `handleGetUserDetails(): void` - Returns detailed information about any user for the admin panel

## /api/attendance.php

Attendance management API endpoints.

- `handleAddPeriodApi(): void` - API handler for adding a new period
- `handleGetPeriodAttendanceApi(): void` - API handler for getting period attendance
- `handleGetStudentAttendanceApi(): void` - API handler for getting student attendance
- `handleSaveAttendanceApi(): void` - API handler for saving attendance record

## /api/grades.php

Grade management API endpoints.

- `handleGetGradeItemsApi(): void` - API handler for retrieving grade items
- `handleGetClassGradesApi(): void` - API handler for retrieving class grades
- `handleAddGradeItemApi(): void` - API handler for adding grade item
- `handleSaveGradeApi(): void` - API handler for saving grade

## /api/justifications.php

Justification management API endpoints.

- `handleSubmitJustificationApi(): void` - API handler for submitting justification
- `handleApproveJustificationApi(): void` - API handler for approving justification
- `handleRejectJustificationApi(): void` - API handler for rejecting justification
- `handleGetJustificationsApi(): void` - API handler for retrieving justifications
- `handleGetJustificationDetailsApi(): void` - API handler for getting justification details

## /parent/parent_functions.php

Parent-specific helper functions.

- `renderParentAttendanceWidget(): string` - Creates the HTML for the parent's attendance dashboard widget
- `renderParentChildClassAveragesWidget(): string` - Creates the HTML for the parent's view of their child's class
  averages

## /student/student_functions.php

Student-specific helper functions.

- `calculateGradeStatistics(array $grades): array` - Calculate grade statistics grouped by subject and class
- `renderStudentGradesWidget(): string` - Creates the HTML for the student's grades dashboard widget
- `renderStudentAttendanceWidget(): string` - Creates the HTML for the student's attendance dashboard widget
- `renderStudentClassAveragesWidget(): string` - Creates the HTML for the student's class averages dashboard widget
- `renderUpcomingClassesWidget(): string` - Creates the HTML for a student's upcoming classes widget

## /teacher/teacher_functions.php

Teacher-specific helper functions.

- `findClassSubjectById(array $teacherClasses, int $classSubjectId): ?array` - Finds a class-subject by ID in the
  teacher's classes
- `renderTeacherClassOverviewWidget(): string` - Creates the HTML for the teacher's class overview dashboard widget
- `renderTeacherAttendanceWidget(): string` - Shows attendance status for today's classes taught by the teacher
- `renderTeacherPendingJustificationsWidget(): string` - Shows absence justifications waiting for teacher approval
- `renderTeacherClassAveragesWidget(): string` - Creates the HTML for the teacher's class averages dashboard widget
