-- Demo data for uwuweb
USE uwuweb;

-- Clear existing data (except admin user and roles)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE attendance;
TRUNCATE TABLE grades;
TRUNCATE TABLE grade_items;
TRUNCATE TABLE periods;
TRUNCATE TABLE enrollments;
TRUNCATE TABLE class_subjects;
TRUNCATE TABLE classes;
TRUNCATE TABLE subjects;
TRUNCATE TABLE student_parent;
TRUNCATE TABLE parents;
TRUNCATE TABLE teachers;
TRUNCATE TABLE students;
DELETE
FROM users
WHERE user_id > 1; -- Keep admin user
SET FOREIGN_KEY_CHECKS = 1;

-- Add users (admin user already exists)
INSERT INTO users (username, pass_hash, role_id)
VALUES ('teacher', '$2y$10$DKGGjDuuPBUCvRtJal7D0exxxdI.ppSxuwDInrUJvSahSEUs2ZgNy', 2),  -- Teacher
       ('teacher2', '$2y$10$DKGGjDuuPBUCvRtJal7D0exxxdI.ppSxuwDInrUJvSahSEUs2ZgNy', 2), -- Teacher
       ('teacher3', '$2y$10$DKGGjDuuPBUCvRtJal7D0exxxdI.ppSxuwDInrUJvSahSEUs2ZgNy', 2), -- Teacher
       ('student', '$2y$10$oU9BG1pHyKk7xcBd0Lc5Hu/I3IdR/rbzzeCKzSG66/ahSwH2a/Tcm', 3),  -- Student
       ('student2', '$2y$10$oU9BG1pHyKk7xcBd0Lc5Hu/I3IdR/rbzzeCKzSG66/ahSwH2a/Tcm', 3), -- Student
       ('student3', '$2y$10$oU9BG1pHyKk7xcBd0Lc5Hu/I3IdR/rbzzeCKzSG66/ahSwH2a/Tcm', 3), -- Student
       ('student4', '$2y$10$oU9BG1pHyKk7xcBd0Lc5Hu/I3IdR/rbzzeCKzSG66/ahSwH2a/Tcm', 3), -- Student
       ('student5', '$2y$10$oU9BG1pHyKk7xcBd0Lc5Hu/I3IdR/rbzzeCKzSG66/ahSwH2a/Tcm', 3), -- Student
       ('parent', '$2y$10$5PEvGKuui1hEZXVabZmk9Orr.FYsLunLmfh0g/XkwTUgulrTJltqW', 4),   -- Parent
       ('parent2', '$2y$10$5PEvGKuui1hEZXVabZmk9Orr.FYsLunLmfh0g/XkwTUgulrTJltqW', 4);
-- Parent

-- Add teachers
INSERT INTO teachers (user_id, first_name, last_name)
VALUES (2, 'Marija', 'Novak'), -- Math teacher, homeroom for 1.A
       (3, 'Anton', 'Kovač'),  -- Slovenian teacher, homeroom for 2.B
       (4, 'Ana', 'Zupančič');
-- English teacher, homeroom for 3.C

-- Add students
INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (5, 'Luka', 'Horvat', '2006-05-15', '3.A'),   -- 17-18 years old, 3rd year
       (6, 'Maja', 'Krajnc', '2007-02-22', '2.B'),   -- 16-17 years old, 2nd year
       (7, 'Jan', 'Kovačič', '2006-11-08', '3.A'),   -- 17-18 years old, 3rd year
       (8, 'Nina', 'Potočnik', '2008-09-30', '1.A'), -- 15-16 years old, 1st year
       (9, 'Tilen', 'Vidmar', '2008-04-12', '1.A');
-- 15-16 years old, 1st year

-- Add parents
INSERT INTO parents (user_id)
VALUES (10), -- First parent
       (11);
-- Second parent

-- Link students to parents (student_parent table)
INSERT INTO student_parent (student_id, parent_id)
VALUES (1, 1), -- Luka's parent is "stars"
       (2, 2), -- Maja's parent is "stars2"
       (3, 1), -- Jan's parent is also "stars"
       (4, 2), -- Nina's parent is "stars2"
       (5, 2);
-- Tilen's parent is "stars2"

-- Add subjects
INSERT INTO subjects (name)
VALUES ('Matematika'),  -- Mathematics
       ('Slovenščina'), -- Slovenian
       ('Angleščina'),  -- English
       ('Fizika'),      -- Physics
       ('Kemija'),      -- Chemistry
       ('Zgodovina'),   -- History
       ('Geografija'),  -- Geography
       ('Informatika'), -- Computer Science
       ('Športna vzgoja');
-- Physical Education

-- Add classes (homeroom groups)
INSERT INTO classes (class_code, title, homeroom_teacher_id)
VALUES ('1.A', '1. letnik, skupina A', 1), -- 1st year, group A - Math teacher as homeroom
       ('2.B', '2. letnik, skupina B', 2), -- 2nd year, group B - Slovenian teacher as homeroom
       ('3.A', '3. letnik, skupina A', 3);
-- 3rd year, group A - English teacher as homeroom

-- Assign subjects to classes with teachers (class_subjects)
INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES
-- 1.A class subjects
(1, 1, 1), -- Mathematics taught by Marija
(1, 2, 2), -- Slovenian taught by Anton
(1, 3, 3), -- English taught by Ana
(1, 4, 1), -- Physics taught by Marija
(1, 9, 2), -- PE taught by Anton

-- 2.B class subjects
(2, 1, 1), -- Mathematics taught by Marija
(2, 2, 2), -- Slovenian taught by Anton
(2, 3, 3), -- English taught by Ana
(2, 5, 1), -- Chemistry taught by Marija
(2, 6, 2), -- History taught by Anton
(2, 9, 2), -- PE taught by Anton

-- 3.A class subjects
(3, 1, 1), -- Mathematics taught by Marija
(3, 2, 2), -- Slovenian taught by Anton
(3, 3, 3), -- English taught by Ana
(3, 7, 3), -- Geography taught by Ana
(3, 8, 1), -- Computer Science taught by Marija
(3, 9, 2);
-- PE taught by Anton

-- Enroll students in classes
INSERT INTO enrollments (student_id, class_id)
VALUES (1, 3), -- Luka in 3.A
       (2, 2), -- Maja in 2.B
       (3, 3), -- Jan in 3.A
       (4, 1), -- Nina in 1.A
       (5, 1);
-- Tilen in 1.A

-- Create periods (class sessions)
-- Current date for reference
SET @today = CURDATE();
SET @yesterday = DATE_SUB(@today, INTERVAL 1 DAY);
SET @lastWeek = DATE_SUB(@today, INTERVAL 7 DAY);
SET @twoWeeksAgo = DATE_SUB(@today, INTERVAL 14 DAY);
SET @threeWeeksAgo = DATE_SUB(@today, INTERVAL 21 DAY);
SET @oneMonthAgo = DATE_SUB(@today, INTERVAL 30 DAY);

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES
-- Mathematics for 1.A
(1, @yesterday, '1. ura'),     -- Yesterday, 1st period
(1, @lastWeek, '3. ura'),      -- Last week, 3rd period
(1, @twoWeeksAgo, '2. ura'),   -- Two weeks ago, 2nd period

-- Slovenian for 1.A
(2, @yesterday, '2. ura'),     -- Yesterday, 2nd period
(2, @lastWeek, '4. ura'),      -- Last week, 4th period

-- English for 1.A
(3, @today, '2. ura'),         -- Today, 2nd period
(3, @lastWeek, '5. ura'),      -- Last week, 5th period

-- Mathematics for 2.B
(7, @today, '1. ura'),         -- Today, 1st period
(7, @lastWeek, '2. ura'),      -- Last week, 2nd period
(7, @threeWeeksAgo, '3. ura'), -- Three weeks ago, 3rd period

-- Slovenian for 2.B
(8, @yesterday, '3. ura'),     -- Yesterday, 3rd period
(8, @lastWeek, '1. ura'),      -- Last week, 1st period

-- Mathematics for 3.A
(13, @today, '3. ura'),        -- Today, 3rd period
(13, @oneMonthAgo, '1. ura'),  -- One month ago, 1st period

-- Computer Science for 3.A
(17, @today, '4. ura'),        -- Today, 4th period
(17, @yesterday, '5. ura');
-- Yesterday, 5th period

-- Create grade items
INSERT INTO grade_items (class_subject_id, name, max_points, date)
VALUES
-- Mathematics 1.A grade items
(1, 'Test 1 - Osnove algebre', 30.00, DATE_SUB(@today, INTERVAL 20 DAY)),
(1, 'Test 2 - Linearne funkcije', 40.00, DATE_SUB(@today, INTERVAL 10 DAY)),
(1, 'Domača naloga 1', 10.00, DATE_SUB(@today, INTERVAL 25 DAY)),

-- Slovenian 1.A grade items
(2, 'Pisni izdelek - Opis osebe', 20.00, DATE_SUB(@today, INTERVAL 15 DAY)),
(2, 'Test - Slovnica', 30.00, DATE_SUB(@today, INTERVAL 8 DAY)),

-- English 1.A grade items
(3, 'Vocabulary Test', 25.00, DATE_SUB(@today, INTERVAL 12 DAY)),
(3, 'Reading Comprehension', 20.00, DATE_SUB(@today, INTERVAL 5 DAY)),

-- Mathematics 2.B grade items
(7, 'Test - Kvadratne funkcije', 40.00, DATE_SUB(@today, INTERVAL 14 DAY)),
(7, 'Ustno ocenjevanje', 20.00, DATE_SUB(@today, INTERVAL 7 DAY)),

-- Slovenian 2.B grade items
(8, 'Test - Književnost', 35.00, DATE_SUB(@today, INTERVAL 18 DAY)),
(8, 'Govorni nastop', 25.00, DATE_SUB(@today, INTERVAL 9 DAY)),

-- Mathematics 3.A grade items
(13, 'Test - Trigonometrija', 45.00, DATE_SUB(@today, INTERVAL 16 DAY)),
(13, 'Seminarska naloga', 30.00, DATE_SUB(@today, INTERVAL 4 DAY)),

-- Computer Science 3.A grade items
(17, 'Projektno delo - Spletna stran', 50.00, DATE_SUB(@today, INTERVAL 13 DAY)),
(17, 'Test - Algoritmi', 35.00, DATE_SUB(@today, INTERVAL 3 DAY));

-- Insert grades
-- Get enrollment IDs for reference
SET @enroll_luka = 1; -- Luka in 3.A
SET @enroll_maja = 2; -- Maja in 2.B
SET @enroll_jan = 3; -- Jan in 3.A
SET @enroll_nina = 4; -- Nina in 1.A
SET @enroll_tilen = 5; -- Tilen in 1.A

INSERT INTO grades (enroll_id, item_id, points, comment)
VALUES
-- Grades for Nina (1.A)
(@enroll_nina, 1, 25.00, 'Dobro razumevanje snovi.'),                           -- Math Test 1
(@enroll_nina, 2, 32.00, 'Nekaj manjših napak pri izračunih.'),                 -- Math Test 2
(@enroll_nina, 3, 9.50, 'Zelo dobro izdelana domača naloga.'),                  -- Math Homework
(@enroll_nina, 4, 16.00, 'Lepo strukturiran opis.'),                            -- Slovenian Essay
(@enroll_nina, 5, 28.00, 'Odlično znanje slovnice.'),                           -- Slovenian Grammar
(@enroll_nina, 6, 20.00, 'Dobro znanje besedišča.'),                            -- English Vocabulary
(@enroll_nina, 7, 16.00, 'Nekaj težav pri razumevanju besedila.'),              -- English Reading

-- Grades for Tilen (1.A)
(@enroll_tilen, 1, 18.00, 'Potrebno je utrditi osnove.'),                       -- Math Test 1
(@enroll_tilen, 2, 28.00, 'Izboljšanje v primerjavi s prvim testom.'),          -- Math Test 2
(@enroll_tilen, 3, 7.00, 'Nepopolna domača naloga.'),                           -- Math Homework
(@enroll_tilen, 4, 14.00, 'Pomanjkljiv opis, a dobra struktura.'),              -- Slovenian Essay
(@enroll_tilen, 5, 20.00, 'Osnovno znanje slovnice.'),                          -- Slovenian Grammar
(@enroll_tilen, 6, 19.00, 'Solidno znanje besedišča.'),                         -- English Vocabulary
(@enroll_tilen, 7, 12.00, 'Težave pri razumevanju kompleksnejših besedil.'),    -- English Reading

-- Grades for Maja (2.B)
(@enroll_maja, 8, 38.00, 'Odlično razumevanje kvadratnih funkcij.'),            -- Math Quadratic Functions
(@enroll_maja, 9, 18.00, 'Suvereno odgovarjanje na vprašanja.'),                -- Math Oral Exam
(@enroll_maja, 10, 30.00, 'Dobro poznavanje literarnih del.'),                  -- Slovenian Literature
(@enroll_maja, 11, 22.00, 'Zelo dober govorni nastop.'),                        -- Slovenian Presentation

-- Grades for Luka (3.A)
(@enroll_luka, 12, 40.00, 'Izjemno znanje trigonometrije.'),                    -- Math Trigonometry
(@enroll_luka, 13, 27.00, 'Dobra seminarska naloga z nekaj pomanjkljivostmi.'), -- Math Seminar
(@enroll_luka, 14, 48.00, 'Izjemen projekt.'),                                  -- CS Project
(@enroll_luka, 15, 30.00, 'Dobro poznavanje algoritmov.'),                      -- CS Algorithms

-- Grades for Jan (3.A)
(@enroll_jan, 12, 35.00, 'Dobro znanje s prostorom za izboljšave.'),            -- Math Trigonometry
(@enroll_jan, 13, 22.00, 'Osnovna seminarska naloga.'),                         -- Math Seminar
(@enroll_jan, 14, 40.00, 'Dober projekt z nekaj tehničnimi težavami.'),         -- CS Project
(@enroll_jan, 15, 25.00, 'Potrebno je utrditi znanje algoritmov.');
-- CS Algorithms

-- Insert attendance records
-- For Nina (1.A)
INSERT INTO attendance (enroll_id, period_id, status)
VALUES (@enroll_nina, 1, 'P'), -- Mathematics yesterday - Present
       (@enroll_nina, 2, 'P'), -- Mathematics last week - Present
       (@enroll_nina, 3, 'A'), -- Mathematics two weeks ago - Absent (not justified)
       (@enroll_nina, 4, 'P'), -- Slovenian yesterday - Present
       (@enroll_nina, 5, 'P'), -- Slovenian last week - Present
       (@enroll_nina, 6, 'P'), -- English today - Present
       (@enroll_nina, 7, 'L');
-- English last week - Late

-- Update absence record with justification
UPDATE attendance
SET justification      = 'Bolezen - prehlad',
    justification_file = 'nina_opravicilo.pdf',
    approved           = 1
WHERE enroll_id = @enroll_nina
  AND period_id = 3;

-- For Tilen (1.A)
INSERT INTO attendance (enroll_id, period_id, status)
VALUES (@enroll_tilen, 1, 'P'), -- Mathematics yesterday - Present
       (@enroll_tilen, 2, 'P'), -- Mathematics last week - Present
       (@enroll_tilen, 3, 'P'), -- Mathematics two weeks ago - Present
       (@enroll_tilen, 4, 'A'), -- Slovenian yesterday - Absent (pending justification)
       (@enroll_tilen, 5, 'P'), -- Slovenian last week - Present
       (@enroll_tilen, 6, 'P'), -- English today - Present
       (@enroll_tilen, 7, 'P');
-- English last week - Present

-- Update absence record with pending justification
UPDATE attendance
SET justification      = 'Zdravniški pregled',
    justification_file = 'tilen_opravicilo.pdf'
WHERE enroll_id = @enroll_tilen
  AND period_id = 4;

-- For Maja (2.B)
INSERT INTO attendance (enroll_id, period_id, status)
VALUES (@enroll_maja, 8, 'P'),  -- Mathematics today - Present
       (@enroll_maja, 9, 'P'),  -- Mathematics last week - Present
       (@enroll_maja, 10, 'A'), -- Mathematics three weeks ago - Absent (justified)
       (@enroll_maja, 11, 'L'), -- Slovenian yesterday - Late
       (@enroll_maja, 12, 'P');
-- Slovenian last week - Present

-- Update absence record with approved justification
UPDATE attendance
SET justification = 'Družinske obveznosti',
    approved      = 1
WHERE enroll_id = @enroll_maja
  AND period_id = 10;

-- For Luka (3.A)
INSERT INTO attendance (enroll_id, period_id, status)
VALUES (@enroll_luka, 13, 'P'), -- Mathematics today - Present
       (@enroll_luka, 14, 'A'), -- Mathematics one month ago - Absent (rejected justification)
       (@enroll_luka, 15, 'P'), -- Computer Science today - Present
       (@enroll_luka, 16, 'P');
-- Computer Science yesterday - Present

-- Update absence record with rejected justification
UPDATE attendance
SET justification = 'Prometna gneča',
    approved      = 0,
    reject_reason = 'Ni veljavna opravičitev za odsotnost.'
WHERE enroll_id = @enroll_luka
  AND period_id = 14;

-- For Jan (3.A)
INSERT INTO attendance (enroll_id, period_id, status)
VALUES (@enroll_jan, 13, 'P'), -- Mathematics today - Present
       (@enroll_jan, 14, 'L'), -- Mathematics one month ago - Late
       (@enroll_jan, 15, 'A'), -- Computer Science today - Absent (not justified yet)
       (@enroll_jan, 16, 'P'); -- Computer Science yesterday - Present
