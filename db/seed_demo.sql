-- demo_seed.sql - Sample data for uwuweb
USE uwuweb;

-- Teachers
INSERT INTO users (username, pass_hash, role_id)
VALUES ('novak.j', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2),
       ('kovac.a', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2),
       ('horvat.m', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2),
       ('teacher', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2);

INSERT INTO teachers (user_id, first_name, last_name)
VALUES (2, 'Janez', 'Novak'),
       (3, 'Ana', 'Kovač'),
       (4, 'Matej', 'Horvat'),
       (5, 'Tina', 'Zupan');

-- Subjects
INSERT INTO subjects (name)
VALUES ('Matematika'),
       ('Slovenščina'),
       ('Angleščina'),
       ('Fizika'),
       ('Zgodovina');

-- Classes (with homeroom teachers)
INSERT INTO classes (class_code, title, homeroom_teacher_id)
VALUES ('1A', '1. A razred', 1),
       ('2B', '2. B razred', 2),
       ('3C', '3. C razred', 3);

-- Students
INSERT INTO users (username, pass_hash, role_id)
VALUES ('kranjc.m', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('polanc.p', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('zajc.l', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('vidmar.k', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('zupancic.j', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('kobal.n', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('golob.s', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('novak.e', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('kralj.a', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3),
       ('student', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (6, 'Maja', 'Kranjc', '2007-03-15', '1A'),
       (7, 'Peter', 'Polanc', '2007-05-22', '1A'),
       (8, 'Luka', 'Zajc', '2007-11-08', '1A'),
       (9, 'Katja', 'Vidmar', '2007-09-30', '1A'),
       (10, 'Jan', 'Zupančič', '2006-02-14', '2B'),
       (11, 'Nina', 'Kobal', '2006-07-19', '2B'),
       (12, 'Sara', 'Golob', '2006-04-05', '2B'),
       (13, 'Eva', 'Novak', '2005-12-10', '3C'),
       (14, 'Anže', 'Kralj', '2005-08-27', '3C'),
       (15, 'Zala', 'Kos', '2005-06-03', '3C');

-- Parents
INSERT INTO users (username, pass_hash, role_id)
VALUES ('kranjc.g', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4),
       ('polanc.j', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4),
       ('zajc.b', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4),
       ('kobal.m', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4),
       ('novak.i', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4),
       ('parent', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);

INSERT INTO parents (user_id)
VALUES (16),
       (17),
       (18),
       (19),
       (20),
       (21);

-- Student-Parent relationships
INSERT INTO student_parent (student_id, parent_id)
VALUES (1, 1), -- Maja Kranjc - Gregor Kranjc
       (2, 2), -- Peter Polanc - Jožica Polanc
       (3, 3), -- Luka Zajc - Bojan Zajc
       (6, 4), -- Nina Kobal - Mojca Kobal
       (8, 5), -- Eva Novak - Igor Novak
       (10, 6);
-- Zala Kos - Damjan Kos

-- Class-Subject-Teacher assignments
INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (1, 1, 1), -- 1A, Math, Novak
       (1, 2, 2), -- 1A, Slovenian, Kovač
       (1, 3, 3), -- 1A, English, Horvat
       (1, 5, 4), -- 1A, History, Zupan
       (2, 1, 1), -- 2B, Math, Novak
       (2, 2, 2), -- 2B, Slovenian, Kovač
       (2, 3, 3), -- 2B, English, Horvat
       (2, 4, 4), -- 2B, Physics, Zupan
       (3, 1, 1), -- 3C, Math, Novak
       (3, 2, 2), -- 3C, Slovenian, Kovač
       (3, 3, 3), -- 3C, English, Horvat
       (3, 4, 4);
-- 3C, Physics, Zupan

-- Enrollments
INSERT INTO enrollments (student_id, class_id)
VALUES (1, 1), -- Maja Kranjc in 1A
       (2, 1), -- Peter Polanc in 1A
       (3, 1), -- Luka Zajc in 1A
       (4, 1), -- Katja Vidmar in 1A
       (5, 2), -- Jan Zupančič in 2B
       (6, 2), -- Nina Kobal in 2B
       (7, 2), -- Sara Golob in 2B
       (8, 3), -- Eva Novak in 3C
       (9, 3), -- Anže Kralj in 3C
       (10, 3);
-- Zala Kos in 3C

-- Periods (for the last month)
INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES
-- 1A Math
(1, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '1. ura'),
(1, DATE_SUB(CURDATE(), INTERVAL 15 DAY), '3. ura'),
(1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '2. ura'),
(1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '4. ura'),
-- 1A Slovenian
(2, DATE_SUB(CURDATE(), INTERVAL 19 DAY), '2. ura'),
(2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '1. ura'),
(2, DATE_SUB(CURDATE(), INTERVAL 9 DAY), '3. ura'),
(2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '2. ura'),
-- 2B Math
(5, DATE_SUB(CURDATE(), INTERVAL 18 DAY), '3. ura'),
(5, DATE_SUB(CURDATE(), INTERVAL 13 DAY), '2. ura'),
(5, DATE_SUB(CURDATE(), INTERVAL 8 DAY), '4. ura'),
(5, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '1. ura'),
-- 3C English
(11, DATE_SUB(CURDATE(), INTERVAL 17 DAY), '4. ura'),
(11, DATE_SUB(CURDATE(), INTERVAL 12 DAY), '2. ura'),
(11, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '1. ura'),
(11, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '3. ura');

-- Grade Items
INSERT INTO grade_items (class_subject_id, name, max_points, weight)
VALUES
-- 1A Math
(1, 'Test 1: Osnovne operacije', 50.00, 1.50),
(1, 'Kontrolna naloga: Enačbe', 30.00, 1.00),
(1, 'Domača naloga: Geometrija', 10.00, 0.50),
-- 1A Slovenian
(2, 'Esej: Cankar', 40.00, 1.25),
(2, 'Test slovnice', 30.00, 1.00),
-- 2B Math
(5, 'Test: Funkcije', 50.00, 1.50),
(5, 'Kontrolna naloga: Trigonometrija', 30.00, 1.00),
-- 2B Physics
(8, 'Test: Gibanje', 40.00, 1.25),
(8, 'Laboratorijsko delo', 20.00, 0.75),
-- 3C English
(11, 'Written exam', 50.00, 1.50),
(11, 'Oral examination', 30.00, 1.00),
(11, 'Essay: My future', 20.00, 0.75);

-- Grades
INSERT INTO grades (enroll_id, item_id, points, comment)
VALUES
-- 1A Math - Maja Kranjc
(1, 1, 45.00, 'Zelo dobro razumevanje snovi'),
(1, 2, 28.00, 'Manjša napaka pri računanju'),
(1, 3, 9.50, NULL),
-- 1A Math - Peter Polanc
(2, 1, 38.00, 'Potrebno več vaje'),
(2, 2, 22.00, NULL),
(2, 3, 8.00, NULL),
-- 1A Slovenian - Maja Kranjc
(1, 4, 36.00, 'Odličen esej, bogat besedni zaklad'),
(1, 5, 27.00, NULL),
-- 1A Slovenian - Luka Zajc
(3, 4, 32.00, 'Dober esej, manjše napake'),
(3, 5, 25.00, NULL),
-- 2B Math - Jan Zupančič
(5, 6, 47.00, 'Zelo dobro razumevanje funkcij'),
(5, 7, 28.00, NULL),
-- 2B Physics - Nina Kobal
(6, 8, 38.00, 'Dobro razumevanje fizikalnih zakonov'),
(6, 9, 19.00, 'Natančno izvedeno delo'),
-- 3C English - Eva Novak
(8, 10, 48.00, 'Excellent knowledge of grammar and vocabulary'),
(8, 11, 29.00, 'Fluent speech with minor pronunciation issues'),
(8, 12, 18.00, 'Well-structured essay with some creative ideas');

-- Attendance
INSERT INTO attendance (enroll_id, period_id, status, justification, approved, reject_reason)
VALUES
-- 1A Math Periods - Various Students
(1, 1, 'P', NULL, NULL, NULL),                            -- Maja present
(2, 1, 'P', NULL, NULL, NULL),                            -- Peter present
(3, 1, 'P', NULL, NULL, NULL),                            -- Luka present
(4, 1, 'P', NULL, NULL, NULL),                            -- Katja present

(1, 2, 'P', NULL, NULL, NULL),                            -- Maja present
(2, 2, 'A', 'Zdravniški pregled', TRUE, NULL),            -- Peter absent, justified
(3, 2, 'P', NULL, NULL, NULL),                            -- Luka present
(4, 2, 'P', NULL, NULL, NULL),                            -- Katja present

(1, 3, 'P', NULL, NULL, NULL),                            -- Maja present
(2, 3, 'P', NULL, NULL, NULL),                            -- Peter present
(3, 3, 'L', 'Zamuda avtobusa', TRUE, NULL),               -- Luka late, justified
(4, 3, 'A', 'Bolezen', TRUE, NULL),                       -- Katja absent, justified

(1, 4, 'P', NULL, NULL, NULL),                            -- Maja present
(2, 4, 'P', NULL, NULL, NULL),                            -- Peter present
(3, 4, 'P', NULL, NULL, NULL),                            -- Luka present
(4, 4, 'P', NULL, NULL, NULL),                            -- Katja present

-- 1A Slovenian Periods
(1, 5, 'P', NULL, NULL, NULL),                            -- Maja present
(2, 5, 'P', NULL, NULL, NULL),                            -- Peter present
(3, 5, 'A', 'Zaspal', FALSE, 'Neopravičljiv razlog'),     -- Luka absent, unjustified
(4, 5, 'P', NULL, NULL, NULL),                            -- Katja present

-- 2B Math Periods - Jan Zupančič
(5, 9, 'P', NULL, NULL, NULL),
(5, 10, 'A', 'Športno tekmovanje', TRUE, NULL),
(5, 11, 'P', NULL, NULL, NULL),
(5, 12, 'P', NULL, NULL, NULL),

-- 3C English Periods - Various Students
(8, 13, 'P', NULL, NULL, NULL),                           -- Eva present
(9, 13, 'P', NULL, NULL, NULL),                           -- Anže present
(10, 13, 'A', 'Zdravniške težave', NULL, NULL),           -- Zala absent, pending justification

(8, 14, 'P', NULL, NULL, NULL),                           -- Eva present
(9, 14, 'L', 'Zadrževanje pri prejšnji uri', TRUE, NULL), -- Anže late, justified
(10, 14, 'P', NULL, NULL, NULL),                          -- Zala present

(8, 15, 'P', NULL, NULL, NULL),                           -- Eva present
(9, 15, 'P', NULL, NULL, NULL),                           -- Anže present
(10, 15, 'P', NULL, NULL, NULL); -- Zala present
