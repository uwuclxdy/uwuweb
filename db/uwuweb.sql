# noinspection IncorrectFormattingForFile
# noinspection SpellCheckingInspectionForFile

-- uwuweb Database Schema
DROP DATABASE IF EXISTS uwuweb;
CREATE DATABASE uwuweb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uwuweb;

-- User Roles
CREATE TABLE roles
(
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- Users
CREATE TABLE users
(
    user_id   INT AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(50)  NOT NULL,
    pass_hash VARCHAR(255) NOT NULL,
    role_id   INT          NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT username UNIQUE (username),
    CONSTRAINT users_ibfk_1 FOREIGN KEY (role_id) REFERENCES roles (role_id)
);

CREATE INDEX role_id ON users (role_id);

-- Students
CREATE TABLE students
(
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL,
    dob        DATE         NOT NULL,
    class_code VARCHAR(10)  NOT NULL,
    CONSTRAINT user_id UNIQUE (user_id),
    CONSTRAINT students_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- Parents
CREATE TABLE parents
(
    parent_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    CONSTRAINT user_id UNIQUE (user_id),
    CONSTRAINT parents_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- Student-Parent relationship (many-to-many)
CREATE TABLE student_parent
(
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    PRIMARY KEY (student_id, parent_id),
    CONSTRAINT student_parent_ibfk_1 FOREIGN KEY (student_id) REFERENCES students (student_id),
    CONSTRAINT student_parent_ibfk_2 FOREIGN KEY (parent_id) REFERENCES parents (parent_id)
);

CREATE INDEX parent_id ON student_parent (parent_id);

-- Teachers
CREATE TABLE teachers
(
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL,
    CONSTRAINT user_id UNIQUE (user_id),
    CONSTRAINT teachers_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- Subjects
CREATE TABLE subjects
(
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Classes (now represents homeroom classes)
CREATE TABLE classes
(
    class_id            INT AUTO_INCREMENT PRIMARY KEY,
    class_code VARCHAR(10) NOT NULL,
    title               VARCHAR(100) NOT NULL,
    homeroom_teacher_id INT          NOT NULL,
    CONSTRAINT class_code UNIQUE (class_code),
    CONSTRAINT classes_ibfk_1 FOREIGN KEY (homeroom_teacher_id) REFERENCES teachers (teacher_id)
);

CREATE INDEX homeroom_teacher_id ON classes (homeroom_teacher_id);

-- Class-Subject-Teacher relationship
CREATE TABLE class_subjects
(
    class_subject_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id   INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    CONSTRAINT class_id UNIQUE (class_id, subject_id),
    CONSTRAINT class_subjects_ibfk_1 FOREIGN KEY (class_id) REFERENCES classes (class_id),
    CONSTRAINT class_subjects_ibfk_2 FOREIGN KEY (subject_id) REFERENCES subjects (subject_id),
    CONSTRAINT class_subjects_ibfk_3 FOREIGN KEY (teacher_id) REFERENCES teachers (teacher_id)
);

CREATE INDEX subject_id ON class_subjects (subject_id);
CREATE INDEX teacher_id ON class_subjects (teacher_id);

-- Enrollments
CREATE TABLE enrollments
(
    enroll_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id  INT NOT NULL,
    CONSTRAINT student_id UNIQUE (student_id, class_id),
    CONSTRAINT enrollments_ibfk_1 FOREIGN KEY (student_id) REFERENCES students (student_id),
    CONSTRAINT enrollments_ibfk_2 FOREIGN KEY (class_id) REFERENCES classes (class_id)
);

CREATE INDEX class_id ON enrollments (class_id);

-- Periods (individual class sessions)
CREATE TABLE periods
(
    period_id        INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_id INT         NOT NULL,
    period_date      DATE        NOT NULL,
    period_label     VARCHAR(50) NOT NULL,
    CONSTRAINT periods_ibfk_1 FOREIGN KEY (class_subject_id) REFERENCES class_subjects (class_subject_id)
);

CREATE INDEX class_subject_id ON periods (class_subject_id);

-- Grade Items
CREATE TABLE grade_items
(
    item_id          INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_id INT           NOT NULL,
    name             VARCHAR(100)  NOT NULL,
    max_points       DECIMAL(5, 2) NOT NULL,
    date DATE NULL,
    CONSTRAINT grade_items_ibfk_1 FOREIGN KEY (class_subject_id) REFERENCES class_subjects (class_subject_id)
);

CREATE INDEX class_subject_id ON grade_items (class_subject_id);

-- Grades
CREATE TABLE grades
(
    grade_id  INT AUTO_INCREMENT PRIMARY KEY,
    enroll_id INT           NOT NULL,
    item_id   INT           NOT NULL,
    points    DECIMAL(5, 2) NOT NULL,
    comment   TEXT,
    CONSTRAINT grades_ibfk_1 FOREIGN KEY (enroll_id) REFERENCES enrollments (enroll_id),
    CONSTRAINT grades_ibfk_2 FOREIGN KEY (item_id) REFERENCES grade_items (item_id)
);

CREATE INDEX enroll_id ON grades (enroll_id);
CREATE INDEX item_id ON grades (item_id);

-- Attendance
CREATE TABLE attendance
(
    att_id        INT AUTO_INCREMENT PRIMARY KEY,
    enroll_id     INT                  NOT NULL,
    period_id     INT                  NOT NULL,
    status        ENUM ('P', 'A', 'L') NOT NULL, -- Present, Absent, Late
    justification TEXT,
    approved      BOOLEAN DEFAULT NULL,
    reject_reason TEXT,
    justification_file VARCHAR(255) DEFAULT NULL,
    CONSTRAINT enroll_id UNIQUE (enroll_id, period_id),
    CONSTRAINT attendance_ibfk_1 FOREIGN KEY (enroll_id) REFERENCES enrollments (enroll_id),
    CONSTRAINT attendance_ibfk_2 FOREIGN KEY (period_id) REFERENCES periods (period_id)
);

CREATE INDEX period_id ON attendance (period_id);

-- System Settings
CREATE TABLE system_settings
(
    id               INT AUTO_INCREMENT PRIMARY KEY,
    school_name      VARCHAR(100) NOT NULL DEFAULT 'ŠCC Celje',
    current_year     VARCHAR(20)  NOT NULL DEFAULT '2024/2025',
    school_address   TEXT,
    session_timeout  INT          NOT NULL DEFAULT 30,
    grade_scale      VARCHAR(20)  NOT NULL DEFAULT '1-5',
    maintenance_mode BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (name)
VALUES ('Administrator'),
       ('Teacher'),
       ('Student'),
       ('Parent');

-- Create default admin user (username: admin, password: admin)
INSERT INTO users (username, pass_hash, role_id)
VALUES ('admin', '$2y$10$oDaW.izk.8ZDF74wKHJ7ZueMsp68jFMPzZ1WUyeMLrQpwBCC7Pe2i', 1);

-- Insert default settings
INSERT INTO system_settings (school_name, current_year, school_address, session_timeout, grade_scale, maintenance_mode)
VALUES ('ŠCC Celje', '2024/2025', '', 30, '1-5', FALSE);
