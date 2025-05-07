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
    username  VARCHAR(50)  NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    role_id   INT          NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles (role_id)
);

-- Students
CREATE TABLE students
(
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL,
    dob        DATE         NOT NULL,
    class_code VARCHAR(10)  NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- Parents
CREATE TABLE parents
(
    parent_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- Student-Parent relationship (many-to-many)
CREATE TABLE student_parent
(
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    PRIMARY KEY (student_id, parent_id),
    FOREIGN KEY (student_id) REFERENCES students (student_id),
    FOREIGN KEY (parent_id) REFERENCES parents (parent_id)
);

-- Teachers
CREATE TABLE teachers
(
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
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
    class_code          VARCHAR(10)  NOT NULL UNIQUE,
    title               VARCHAR(100) NOT NULL,
    homeroom_teacher_id INT          NOT NULL,
    FOREIGN KEY (homeroom_teacher_id) REFERENCES teachers (teacher_id)
);

-- Class-Subject-Teacher relationship
CREATE TABLE class_subjects
(
    class_subject_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id   INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes (class_id),
    FOREIGN KEY (subject_id) REFERENCES subjects (subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers (teacher_id),
    UNIQUE KEY (class_id, subject_id)
);

-- Enrollments
CREATE TABLE enrollments
(
    enroll_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id  INT NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students (student_id),
    FOREIGN KEY (class_id) REFERENCES classes (class_id),
    UNIQUE KEY (student_id, class_id)
);

-- Periods (individual class sessions)
CREATE TABLE periods
(
    period_id        INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_id INT         NOT NULL,
    period_date      DATE        NOT NULL,
    period_label     VARCHAR(50) NOT NULL,
    FOREIGN KEY (class_subject_id) REFERENCES class_subjects (class_subject_id)
);

-- Grade Items
CREATE TABLE grade_items
(
    item_id          INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_id INT           NOT NULL,
    name             VARCHAR(100)  NOT NULL,
    max_points       DECIMAL(5, 2) NOT NULL,
    weight           DECIMAL(3, 2) DEFAULT 1.00,
    FOREIGN KEY (class_subject_id) REFERENCES class_subjects (class_subject_id)
);

-- Grades
CREATE TABLE grades
(
    grade_id  INT AUTO_INCREMENT PRIMARY KEY,
    enroll_id INT           NOT NULL,
    item_id   INT           NOT NULL,
    points    DECIMAL(5, 2) NOT NULL,
    comment   TEXT,
    FOREIGN KEY (enroll_id) REFERENCES enrollments (enroll_id),
    FOREIGN KEY (item_id) REFERENCES grade_items (item_id)
);

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
    FOREIGN KEY (enroll_id) REFERENCES enrollments (enroll_id),
    FOREIGN KEY (period_id) REFERENCES periods (period_id),
    UNIQUE KEY (enroll_id, period_id)
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
