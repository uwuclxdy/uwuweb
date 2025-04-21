-- uwuweb Demo Data
-- Seed script to populate the database with realistic test data
-- Run this after uwuweb.sql to set up a complete demo environment

USE uwuweb;

-- Clear any existing data first (except admin user and roles)
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM attendance;
DELETE FROM grades;
DELETE FROM grade_items;
DELETE FROM periods;
DELETE FROM enrollments;
DELETE FROM class_subjects;
DELETE FROM classes;
DELETE FROM subjects;
DELETE FROM student_parent;
DELETE FROM parents;
DELETE FROM students;
DELETE FROM teachers;
DELETE FROM users WHERE role_id != 1; -- Keep admin users
SET FOREIGN_KEY_CHECKS = 1;

-- Demo Teachers
INSERT INTO users (username, pass_hash, role_id) VALUES
    ('janez.novak', '$2y$10$OZIwHNqgCgQCYpZ3iUy9xejJ8Jg9nDjB0C37fRB1UjQT8Jrp8G9.6', 2), -- Password: Teacher123!
    ('maja.kovac', '$2y$10$OZIwHNqgCgQCYpZ3iUy9xejJ8Jg9nDjB0C37fRB1UjQT8Jrp8G9.6', 2),
    ('andrej.zupan', '$2y$10$OZIwHNqgCgQCYpZ3iUy9xejJ8Jg9nDjB0C37fRB1UjQT8Jrp8G9.6', 2);

INSERT INTO teachers (user_id) VALUES
    ((SELECT user_id FROM users WHERE username = 'janez.novak')),
    ((SELECT user_id FROM users WHERE username = 'maja.kovac')),
    ((SELECT user_id FROM users WHERE username = 'andrej.zupan'));

-- Create classes with homeroom teachers
INSERT INTO classes (class_code, title, homeroom_teacher_id) VALUES
    ('R3A', 'Class R3A', (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'janez.novak')),
    ('R3B', 'Class R3B', (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'maja.kovac'));

-- Demo Students (20 students)
INSERT INTO users (username, pass_hash, role_id) VALUES
    ('ana.horvat', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3), -- Password: Student123!
    ('miha.petek', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('nina.kovac', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('luka.zajc', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('sara.kastelic', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('nik.logar', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('zala.novak', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('jan.penca', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('eva.kranjc', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('tim.bizjak', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('maja.zupancic', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('jure.kralj', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('lea.vidic', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('mark.rozman', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('pia.kos', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('alex.zagar', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('tina.bogataj', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('david.mlakar', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('klara.zupan', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3),
    ('nejc.kobal', '$2y$10$TzKo0xVVyQ81hJvTxL9c1efimpgLxj2Pc.cQv9r1XcL4Qu3yZ2wp2', 3);

-- Insert student records (split into two class codes: R3A and R3B)
INSERT INTO students (user_id, first_name, last_name, dob, class_code) VALUES
    ((SELECT user_id FROM users WHERE username = 'ana.horvat'), 'Ana', 'Horvat', '2007-05-12', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'miha.petek'), 'Miha', 'Petek', '2007-03-25', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'nina.kovac'), 'Nina', 'Kovač', '2007-08-17', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'luka.zajc'), 'Luka', 'Zajc', '2007-01-30', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'sara.kastelic'), 'Sara', 'Kastelic', '2007-09-03', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'nik.logar'), 'Nik', 'Logar', '2007-06-22', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'zala.novak'), 'Zala', 'Novak', '2007-11-14', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'jan.penca'), 'Jan', 'Penca', '2007-04-09', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'eva.kranjc'), 'Eva', 'Kranjc', '2007-07-28', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'tim.bizjak'), 'Tim', 'Bizjak', '2007-02-17', 'R3A'),
    ((SELECT user_id FROM users WHERE username = 'maja.zupancic'), 'Maja', 'Zupančič', '2007-12-05', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'jure.kralj'), 'Jure', 'Kralj', '2007-10-19', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'lea.vidic'), 'Lea', 'Vidic', '2007-01-23', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'mark.rozman'), 'Mark', 'Rozman', '2007-06-30', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'pia.kos'), 'Pia', 'Kos', '2007-09-15', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'alex.zagar'), 'Alex', 'Žagar', '2007-04-02', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'tina.bogataj'), 'Tina', 'Bogataj', '2007-08-27', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'david.mlakar'), 'David', 'Mlakar', '2007-03-11', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'klara.zupan'), 'Klara', 'Zupan', '2007-11-29', 'R3B'),
    ((SELECT user_id FROM users WHERE username = 'nejc.kobal'), 'Nejc', 'Kobal', '2007-05-18', 'R3B');

-- Demo Parents
INSERT INTO users (username, pass_hash, role_id) VALUES
    ('marko.horvat', '$2y$10$2/dNB1GZ5VPSBFJR5vZlLeYEDcVLVlX07bunmCvGT4kjsHdM3CZ8i', 4), -- Password: Parent123!
    ('petra.horvat', '$2y$10$2/dNB1GZ5VPSBFJR5vZlLeYEDcVLVlX07bunmCvGT4kjsHdM3CZ8i', 4),
    ('tomaz.petek', '$2y$10$2/dNB1GZ5VPSBFJR5vZlLeYEDcVLVlX07bunmCvGT4kjsHdM3CZ8i', 4),
    ('katja.kovac', '$2y$10$2/dNB1GZ5VPSBFJR5vZlLeYEDcVLVlX07bunmCvGT4kjsHdM3CZ8i', 4),
    ('boris.zajc', '$2y$10$2/dNB1GZ5VPSBFJR5vZlLeYEDcVLVlX07bunmCvGT4kjsHdM3CZ8i', 4);

INSERT INTO parents (user_id) VALUES
    ((SELECT user_id FROM users WHERE username = 'marko.horvat')),
    ((SELECT user_id FROM users WHERE username = 'petra.horvat')),
    ((SELECT user_id FROM users WHERE username = 'tomaz.petek')),
    ((SELECT user_id FROM users WHERE username = 'katja.kovac')),
    ((SELECT user_id FROM users WHERE username = 'boris.zajc'));

-- Link parents to students
INSERT INTO student_parent (student_id, parent_id) VALUES
    ((SELECT student_id FROM students JOIN users ON students.user_id = users.user_id WHERE username = 'ana.horvat'),
     (SELECT parent_id FROM parents JOIN users ON parents.user_id = users.user_id WHERE username = 'marko.horvat')),
    ((SELECT student_id FROM students JOIN users ON students.user_id = users.user_id WHERE username = 'ana.horvat'),
     (SELECT parent_id FROM parents JOIN users ON parents.user_id = users.user_id WHERE username = 'petra.horvat')),
    ((SELECT student_id FROM students JOIN users ON students.user_id = users.user_id WHERE username = 'miha.petek'),
     (SELECT parent_id FROM parents JOIN users ON parents.user_id = users.user_id WHERE username = 'tomaz.petek')),
    ((SELECT student_id FROM students JOIN users ON students.user_id = users.user_id WHERE username = 'nina.kovac'),
     (SELECT parent_id FROM parents JOIN users ON parents.user_id = users.user_id WHERE username = 'katja.kovac')),
    ((SELECT student_id FROM students JOIN users ON students.user_id = users.user_id WHERE username = 'luka.zajc'),
     (SELECT parent_id FROM parents JOIN users ON parents.user_id = users.user_id WHERE username = 'boris.zajc'));

-- Subjects
INSERT INTO subjects (name) VALUES
    ('Matka'),
    ('Slovka'),
    ('Ang'),
    ('Fiska'),
    ('Kemija'),
    ('Zgodovina'),
    ('Comp sci 1'),
    ('Comp sci 2');

-- Define class-subject-teacher relationships
INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES
    -- Class R3A subjects
    ((SELECT class_id FROM classes WHERE class_code = 'R3A'),
     (SELECT subject_id FROM subjects WHERE name = 'Matka'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'janez.novak')),
    
    ((SELECT class_id FROM classes WHERE class_code = 'R3A'),
     (SELECT subject_id FROM subjects WHERE name = 'Slovka'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'maja.kovac')),
     
    ((SELECT class_id FROM classes WHERE class_code = 'R3A'),
     (SELECT subject_id FROM subjects WHERE name = 'Ang'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'andrej.zupan')),
     
    ((SELECT class_id FROM classes WHERE class_code = 'R3A'),
     (SELECT subject_id FROM subjects WHERE name = 'Fiska'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'janez.novak')),

    ((SELECT class_id FROM classes WHERE class_code = 'R3A'),
     (SELECT subject_id FROM subjects WHERE name = 'Comp sci 1'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'andrej.zupan')),
     
    -- Class R3B subjects
    ((SELECT class_id FROM classes WHERE class_code = 'R3B'),
     (SELECT subject_id FROM subjects WHERE name = 'Matka'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'janez.novak')),
     
    ((SELECT class_id FROM classes WHERE class_code = 'R3B'),
     (SELECT subject_id FROM subjects WHERE name = 'Slovka'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'maja.kovac')),
     
    ((SELECT class_id FROM classes WHERE class_code = 'R3B'),
     (SELECT subject_id FROM subjects WHERE name = 'Ang'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'andrej.zupan')),
     
    ((SELECT class_id FROM classes WHERE class_code = 'R3B'),
     (SELECT subject_id FROM subjects WHERE name = 'Fiska'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'janez.novak')),
     
    ((SELECT class_id FROM classes WHERE class_code = 'R3B'),
     (SELECT subject_id FROM subjects WHERE name = 'Comp sci 1'),
     (SELECT teacher_id FROM teachers JOIN users ON teachers.user_id = users.user_id WHERE username = 'andrej.zupan'));

-- Enroll students in their respective classes
INSERT INTO enrollments (student_id, class_id)
SELECT s.student_id, c.class_id
FROM students s
JOIN classes c ON s.class_code = c.class_code;

-- Create periods (class sessions) for Math R3A
INSERT INTO periods (class_subject_id, period_date, period_label)
SELECT 
    cs.class_subject_id,
    date,
    CONCAT('Period ', (ROW_NUMBER() OVER (ORDER BY date)))
FROM 
    (
    -- Monday sessions for 4 weeks
    SELECT '2024-09-02' as date UNION ALL
    SELECT '2024-09-09' UNION ALL
    SELECT '2024-09-16' UNION ALL
    SELECT '2024-09-23' UNION ALL
    -- Thursday sessions for 4 weeks
    SELECT '2024-09-05' UNION ALL
    SELECT '2024-09-12' UNION ALL
    SELECT '2024-09-19' UNION ALL
    SELECT '2024-09-26'
    ) AS dates,
    class_subjects cs
WHERE 
    cs.class_id = (SELECT class_id FROM classes WHERE class_code = 'R3A')
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka');

-- Create periods (class sessions) for Math R3B
INSERT INTO periods (class_subject_id, period_date, period_label)
SELECT 
    cs.class_subject_id,
    date,
    CONCAT('Period ', (ROW_NUMBER() OVER (ORDER BY date)))
FROM 
    (
    -- Tuesday sessions for 4 weeks
    SELECT '2024-09-03' as date UNION ALL
    SELECT '2024-09-10' UNION ALL
    SELECT '2024-09-17' UNION ALL
    SELECT '2024-09-24' UNION ALL
    -- Friday sessions for 4 weeks
    SELECT '2024-09-06' UNION ALL
    SELECT '2024-09-13' UNION ALL
    SELECT '2024-09-20' UNION ALL
    SELECT '2024-09-27'
    ) AS dates,
    class_subjects cs
WHERE 
    cs.class_id = (SELECT class_id FROM classes WHERE class_code = 'R3B')
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka');

-- Grade items for Mathematics R3A
INSERT INTO grade_items (class_subject_id, name, max_points, weight)
SELECT 
    cs.class_subject_id,
    item_name,
    max_points,
    weight
FROM 
    (
        SELECT 'Quiz 1' AS item_name, 20.00 AS max_points, 1.00 AS weight UNION ALL
        SELECT 'Homework 1', 10.00, 0.50 UNION ALL
        SELECT 'Midterm Exam', 50.00, 2.00
    ) AS items,
    class_subjects cs
WHERE 
    cs.class_id = (SELECT class_id FROM classes WHERE class_code = 'R3A')
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka');

-- Grade items for Mathematics R3B
INSERT INTO grade_items (class_subject_id, name, max_points, weight)
SELECT 
    cs.class_subject_id,
    item_name,
    max_points,
    weight
FROM 
    (
        SELECT 'Quiz 1' AS item_name, 20.00 AS max_points, 1.00 AS weight UNION ALL
        SELECT 'Homework 1', 10.00, 0.50 UNION ALL
        SELECT 'Midterm Exam', 50.00, 2.00
    ) AS items,
    class_subjects cs
WHERE 
    cs.class_id = (SELECT class_id FROM classes WHERE class_code = 'R3B')
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka');

-- Add grades for Mathematics R3A students - Quiz 1
INSERT INTO grades (enroll_id, item_id, points, comment)
SELECT 
    e.enroll_id,
    gi.item_id,
    FLOOR(10 + RAND() * 10), -- Random grade between 10-20
    CASE
        WHEN RAND() > 0.7 THEN 'Good work!'
        WHEN RAND() > 0.4 THEN 'Could improve on formula applications'
        ELSE NULL
    END
FROM 
    enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN grade_items gi ON cs.class_subject_id = gi.class_subject_id
WHERE 
    c.class_code = 'R3A'
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka')
    AND gi.name = 'Quiz 1';

-- Add grades for Mathematics R3A students - Homework 1
INSERT INTO grades (enroll_id, item_id, points, comment)
SELECT 
    e.enroll_id,
    gi.item_id,
    FLOOR(5 + RAND() * 5), -- Random grade between 5-10
    CASE
        WHEN RAND() > 0.7 THEN 'Complete'
        WHEN RAND() > 0.4 THEN 'Missing some parts'
        ELSE NULL
    END
FROM 
    enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN grade_items gi ON cs.class_subject_id = gi.class_subject_id
WHERE 
    c.class_code = 'R3A'
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka')
    AND gi.name = 'Homework 1';

-- Add grades for Mathematics R3B students - Quiz 1
INSERT INTO grades (enroll_id, item_id, points, comment)
SELECT 
    e.enroll_id,
    gi.item_id,
    FLOOR(10 + RAND() * 10), -- Random grade between 10-20
    CASE
        WHEN RAND() > 0.7 THEN 'Good work!'
        WHEN RAND() > 0.4 THEN 'Could improve on formula applications'
        ELSE NULL
    END
FROM 
    enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN grade_items gi ON cs.class_subject_id = gi.class_subject_id
WHERE 
    c.class_code = 'R3B'
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka')
    AND gi.name = 'Quiz 1';

-- Add grades for Mathematics R3B students - Homework 1
INSERT INTO grades (enroll_id, item_id, points, comment)
SELECT 
    e.enroll_id,
    gi.item_id,
    FLOOR(5 + RAND() * 5), -- Random grade between 5-10
    CASE
        WHEN RAND() > 0.7 THEN 'Complete'
        WHEN RAND() > 0.4 THEN 'Missing some parts'
        ELSE NULL
    END
FROM 
    enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN grade_items gi ON cs.class_subject_id = gi.class_subject_id
WHERE 
    c.class_code = 'R3B'
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka')
    AND gi.name = 'Homework 1';

-- Add attendance records for R3A Math periods
INSERT INTO attendance (enroll_id, period_id, status, justification, approved)
SELECT
    e.enroll_id,
    p.period_id,
    CASE
        WHEN RAND() > 0.9 THEN 'A' -- 10% absent
        WHEN RAND() > 0.8 THEN 'L' -- 10% late
        ELSE 'P' -- 80% present
    END,
    CASE
        WHEN RAND() > 0.8 THEN 'Medical appointment'
        WHEN RAND() > 0.6 THEN 'Family emergency'
        ELSE NULL
    END,
    CASE
        WHEN RAND() > 0.7 THEN 1  -- 70% approved
        WHEN RAND() > 0.4 THEN 0  -- 30% rejected
        ELSE NULL                 -- rest pending
    END
FROM 
    enrollments e
    JOIN classes c ON e.class_id = c.class_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN periods p ON cs.class_subject_id = p.class_subject_id
WHERE 
    c.class_code = 'R3A'
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka');

-- Add attendance records for R3B Math periods
INSERT INTO attendance (enroll_id, period_id, status, justification, approved)
SELECT
    e.enroll_id,
    p.period_id,
    CASE
        WHEN RAND() > 0.9 THEN 'A' -- 10% absent
        WHEN RAND() > 0.8 THEN 'L' -- 10% late
        ELSE 'P' -- 80% present
    END,
    CASE
        WHEN RAND() > 0.8 THEN 'Medical appointment'
        WHEN RAND() > 0.6 THEN 'Family emergency'
        ELSE NULL
    END,
    CASE
        WHEN RAND() > 0.7 THEN 1  -- 70% approved
        WHEN RAND() > 0.4 THEN 0  -- 30% rejected
        ELSE NULL                 -- rest pending
    END
FROM 
    enrollments e
    JOIN classes c ON e.class_id = c.class_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN periods p ON cs.class_subject_id = p.class_subject_id
WHERE 
    c.class_code = 'R3B'
    AND cs.subject_id = (SELECT subject_id FROM subjects WHERE name = 'Matka');

-- Add some pending absence justifications (without text for some absences)
UPDATE attendance
SET justification = NULL
WHERE status = 'A' AND RAND() > 0.5;

-- Add reject reasons for rejected justifications
UPDATE attendance
SET reject_reason = CASE
    WHEN RAND() > 0.6 THEN 'Insufficient documentation provided'
    WHEN RAND() > 0.3 THEN 'Reason not acceptable per school policy'
    ELSE 'Late submission'
    END
WHERE approved = 0;
