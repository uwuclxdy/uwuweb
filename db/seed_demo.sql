-- seed_demo.sql - Sample data for uwuweb
USE uwuweb;

-- Teachers
INSERT INTO users (username, pass_hash, role_id)
VALUES ('novak.j', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2);
SET @novak_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('kovac.a', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2);
SET @kovac_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('horvat.m', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2);
SET @horvat_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('teacher', '$2y$10$cWdPksSP0u0R4Jn4mRJaVuJX6ZNKkgEXV82JZJwL7pZU8VYlv35uu', 2);
SET @teacher_user_id = LAST_INSERT_ID();

INSERT INTO teachers (user_id, first_name, last_name)
VALUES (@novak_user_id, 'Janez', 'Novak');
SET @teacher_novak_id = LAST_INSERT_ID();

INSERT INTO teachers (user_id, first_name, last_name)
VALUES (@kovac_user_id, 'Ana', 'Kovač');
SET @teacher_kovac_id = LAST_INSERT_ID();

INSERT INTO teachers (user_id, first_name, last_name)
VALUES (@horvat_user_id, 'Matej', 'Horvat');
SET @teacher_horvat_id = LAST_INSERT_ID();

INSERT INTO teachers (user_id, first_name, last_name)
VALUES (@teacher_user_id, 'Tina', 'Zupan');
SET @teacher_zupan_id = LAST_INSERT_ID();

-- Subjects
INSERT INTO subjects (name)
VALUES ('Matematika');
SET @subject_math_id = LAST_INSERT_ID();

INSERT INTO subjects (name)
VALUES ('Slovenščina');
SET @subject_slovenian_id = LAST_INSERT_ID();

INSERT INTO subjects (name)
VALUES ('Angleščina');
SET @subject_english_id = LAST_INSERT_ID();

INSERT INTO subjects (name)
VALUES ('Fizika');
SET @subject_physics_id = LAST_INSERT_ID();

INSERT INTO subjects (name)
VALUES ('Zgodovina');
SET @subject_history_id = LAST_INSERT_ID();

-- Classes (with homeroom teachers)
INSERT INTO classes (class_code, title, homeroom_teacher_id)
VALUES ('1A', '1. A razred', @teacher_novak_id);
SET @class_1a_id = LAST_INSERT_ID();

INSERT INTO classes (class_code, title, homeroom_teacher_id)
VALUES ('2B', '2. B razred', @teacher_kovac_id);
SET @class_2b_id = LAST_INSERT_ID();

INSERT INTO classes (class_code, title, homeroom_teacher_id)
VALUES ('3C', '3. C razred', @teacher_horvat_id);
SET @class_3c_id = LAST_INSERT_ID();

-- Students
INSERT INTO users (username, pass_hash, role_id)
VALUES ('kranjc.m', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @kranjc_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('polanc.p', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @polanc_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('zajc.l', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @zajc_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('vidmar.k', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @vidmar_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('zupancic.j', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @zupancic_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('kobal.n', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @kobal_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('golob.s', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @golob_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('novak.e', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @novak_e_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('kralj.a', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @kralj_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('student', '$2y$10$Yz0lLAypzHHK7Hn6dJ0See.KJutt8TwMLWbd53tQxCCI6e.0XC1/m', 3);
SET @student_user_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@kranjc_user_id, 'Maja', 'Kranjc', '2007-03-15', '1A');
SET @student_kranjc_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@polanc_user_id, 'Peter', 'Polanc', '2007-05-22', '1A');
SET @student_polanc_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@zajc_user_id, 'Luka', 'Zajc', '2007-11-08', '1A');
SET @student_zajc_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@vidmar_user_id, 'Katja', 'Vidmar', '2007-09-30', '1A');
SET @student_vidmar_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@zupancic_user_id, 'Jan', 'Zupančič', '2006-02-14', '2B');
SET @student_zupancic_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@kobal_user_id, 'Nina', 'Kobal', '2006-07-19', '2B');
SET @student_kobal_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@golob_user_id, 'Sara', 'Golob', '2006-04-05', '2B');
SET @student_golob_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@novak_e_user_id, 'Eva', 'Novak', '2005-12-10', '3C');
SET @student_novak_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@kralj_user_id, 'Anže', 'Kralj', '2005-08-27', '3C');
SET @student_kralj_id = LAST_INSERT_ID();

INSERT INTO students (user_id, first_name, last_name, dob, class_code)
VALUES (@student_user_id, 'Zala', 'Kos', '2005-06-03', '3C');
SET @student_kos_id = LAST_INSERT_ID();

-- Parents
INSERT INTO users (username, pass_hash, role_id)
VALUES ('kranjc.g', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);
SET @kranjc_p_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('polanc.j', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);
SET @polanc_p_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('zajc.b', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);
SET @zajc_p_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('kobal.m', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);
SET @kobal_p_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('novak.i', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);
SET @novak_p_user_id = LAST_INSERT_ID();

INSERT INTO users (username, pass_hash, role_id)
VALUES ('parent', '$2y$10$kqtgZ/QQrORdpA3K65P.5OyWgYxyF5ZJt.pW/oC7SC7mLK.gUvXj6', 4);
SET @parent_user_id = LAST_INSERT_ID();

INSERT INTO parents (user_id)
VALUES (@kranjc_p_user_id);
SET @parent_kranjc_id = LAST_INSERT_ID();

INSERT INTO parents (user_id)
VALUES (@polanc_p_user_id);
SET @parent_polanc_id = LAST_INSERT_ID();

INSERT INTO parents (user_id)
VALUES (@zajc_p_user_id);
SET @parent_zajc_id = LAST_INSERT_ID();

INSERT INTO parents (user_id)
VALUES (@kobal_p_user_id);
SET @parent_kobal_id = LAST_INSERT_ID();

INSERT INTO parents (user_id)
VALUES (@novak_p_user_id);
SET @parent_novak_id = LAST_INSERT_ID();

INSERT INTO parents (user_id)
VALUES (@parent_user_id);
SET @parent_kos_id = LAST_INSERT_ID();

-- Student-Parent relationships
INSERT INTO student_parent (student_id, parent_id)
VALUES (@student_kranjc_id, @parent_kranjc_id),
       (@student_polanc_id, @parent_polanc_id),
       (@student_zajc_id, @parent_zajc_id),
       (@student_kobal_id, @parent_kobal_id),
       (@student_novak_id, @parent_novak_id),
       (@student_kos_id, @parent_kos_id);

-- Class-Subject-Teacher assignments
INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_1a_id, @subject_math_id, @teacher_novak_id);
SET @cs_1a_math_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_1a_id, @subject_slovenian_id, @teacher_kovac_id);
SET @cs_1a_slovenian_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_1a_id, @subject_english_id, @teacher_horvat_id);
SET @cs_1a_english_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_1a_id, @subject_history_id, @teacher_zupan_id);
SET @cs_1a_history_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_2b_id, @subject_math_id, @teacher_novak_id);
SET @cs_2b_math_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_2b_id, @subject_slovenian_id, @teacher_kovac_id);
SET @cs_2b_slovenian_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_2b_id, @subject_english_id, @teacher_horvat_id);
SET @cs_2b_english_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_2b_id, @subject_physics_id, @teacher_zupan_id);
SET @cs_2b_physics_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_3c_id, @subject_math_id, @teacher_novak_id);
SET @cs_3c_math_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_3c_id, @subject_slovenian_id, @teacher_kovac_id);
SET @cs_3c_slovenian_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_3c_id, @subject_english_id, @teacher_horvat_id);
SET @cs_3c_english_id = LAST_INSERT_ID();

INSERT INTO class_subjects (class_id, subject_id, teacher_id)
VALUES (@class_3c_id, @subject_physics_id, @teacher_zupan_id);
SET @cs_3c_physics_id = LAST_INSERT_ID();

-- Enrollments
INSERT INTO enrollments (student_id, class_id)
VALUES (@student_kranjc_id, @class_1a_id);
SET @enroll_kranjc_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_polanc_id, @class_1a_id);
SET @enroll_polanc_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_zajc_id, @class_1a_id);
SET @enroll_zajc_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_vidmar_id, @class_1a_id);
SET @enroll_vidmar_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_zupancic_id, @class_2b_id);
SET @enroll_zupancic_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_kobal_id, @class_2b_id);
SET @enroll_kobal_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_golob_id, @class_2b_id);
SET @enroll_golob_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_novak_id, @class_3c_id);
SET @enroll_novak_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_kralj_id, @class_3c_id);
SET @enroll_kralj_id = LAST_INSERT_ID();

INSERT INTO enrollments (student_id, class_id)
VALUES (@student_kos_id, @class_3c_id);
SET @enroll_kos_id = LAST_INSERT_ID();

-- Periods (for the last month)
INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES
-- 1A Math
(@cs_1a_math_id, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '1. ura');
SET @period_1a_math_1_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_math_id, DATE_SUB(CURDATE(), INTERVAL 15 DAY), '3. ura');
SET @period_1a_math_2_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_math_id, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '2. ura');
SET @period_1a_math_3_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_math_id, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '4. ura');
SET @period_1a_math_4_id = LAST_INSERT_ID();

-- 1A Slovenian
INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_slovenian_id, DATE_SUB(CURDATE(), INTERVAL 19 DAY), '2. ura');
SET @period_1a_slovenian_1_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_slovenian_id, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '1. ura');
SET @period_1a_slovenian_2_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_slovenian_id, DATE_SUB(CURDATE(), INTERVAL 9 DAY), '3. ura');
SET @period_1a_slovenian_3_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_1a_slovenian_id, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '2. ura');
SET @period_1a_slovenian_4_id = LAST_INSERT_ID();

-- 2B Math
INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_2b_math_id, DATE_SUB(CURDATE(), INTERVAL 18 DAY), '3. ura');
SET @period_2b_math_1_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_2b_math_id, DATE_SUB(CURDATE(), INTERVAL 13 DAY), '2. ura');
SET @period_2b_math_2_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_2b_math_id, DATE_SUB(CURDATE(), INTERVAL 8 DAY), '4. ura');
SET @period_2b_math_3_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_2b_math_id, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '1. ura');
SET @period_2b_math_4_id = LAST_INSERT_ID();

-- 3C English
INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_3c_english_id, DATE_SUB(CURDATE(), INTERVAL 17 DAY), '4. ura');
SET @period_3c_english_1_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_3c_english_id, DATE_SUB(CURDATE(), INTERVAL 12 DAY), '2. ura');
SET @period_3c_english_2_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_3c_english_id, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '1. ura');
SET @period_3c_english_3_id = LAST_INSERT_ID();

INSERT INTO periods (class_subject_id, period_date, period_label)
VALUES (@cs_3c_english_id, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '3. ura');
SET @period_3c_english_4_id = LAST_INSERT_ID();

-- Grade Items
INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES
-- 1A Math
(@cs_1a_math_id, 'Test 1: Osnovne operacije', 50.00);
SET @item_1a_math_1_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_1a_math_id, 'Kontrolna naloga: Enačbe', 30.00);
SET @item_1a_math_2_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_1a_math_id, 'Domača naloga: Geometrija', 10.00);
SET @item_1a_math_3_id = LAST_INSERT_ID();

-- 1A Slovenian
INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_1a_slovenian_id, 'Esej: Cankar', 40.00);
SET @item_1a_slovenian_1_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_1a_slovenian_id, 'Test slovnice', 30.00);
SET @item_1a_slovenian_2_id = LAST_INSERT_ID();

-- 2B Math
INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_2b_math_id, 'Test: Funkcije', 50.00);
SET @item_2b_math_1_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_2b_math_id, 'Kontrolna naloga: Trigonometrija', 30.00);
SET @item_2b_math_2_id = LAST_INSERT_ID();

-- 2B Physics
INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_2b_physics_id, 'Test: Gibanje', 40.00);
SET @item_2b_physics_1_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_2b_physics_id, 'Laboratorijsko delo', 20.00);
SET @item_2b_physics_2_id = LAST_INSERT_ID();

-- 3C English
INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_3c_english_id, 'Written exam', 50.00);
SET @item_3c_english_1_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_3c_english_id, 'Oral examination', 30.00);
SET @item_3c_english_2_id = LAST_INSERT_ID();

INSERT INTO grade_items (class_subject_id, name, max_points)
VALUES (@cs_3c_english_id, 'Essay: My future', 20.00);
SET @item_3c_english_3_id = LAST_INSERT_ID();

-- Grades
INSERT INTO grades (enroll_id, item_id, points, comment)
VALUES
-- 1A Math - Maja Kranjc
(@enroll_kranjc_id, @item_1a_math_1_id, 45.00, 'Zelo dobro razumevanje snovi'),
(@enroll_kranjc_id, @item_1a_math_2_id, 28.00, 'Manjša napaka pri računanju'),
(@enroll_kranjc_id, @item_1a_math_3_id, 9.50, NULL),
-- 1A Math - Peter Polanc
(@enroll_polanc_id, @item_1a_math_1_id, 38.00, 'Potrebno več vaje'),
(@enroll_polanc_id, @item_1a_math_2_id, 22.00, NULL),
(@enroll_polanc_id, @item_1a_math_3_id, 8.00, NULL),
-- 1A Slovenian - Maja Kranjc
(@enroll_kranjc_id, @item_1a_slovenian_1_id, 36.00, 'Odličen esej, bogat besedni zaklad'),
(@enroll_kranjc_id, @item_1a_slovenian_2_id, 27.00, NULL),
-- 1A Slovenian - Luka Zajc
(@enroll_zajc_id, @item_1a_slovenian_1_id, 32.00, 'Dober esej, manjše napake'),
(@enroll_zajc_id, @item_1a_slovenian_2_id, 25.00, NULL),
-- 2B Math - Jan Zupančič
(@enroll_zupancic_id, @item_2b_math_1_id, 47.00, 'Zelo dobro razumevanje funkcij'),
(@enroll_zupancic_id, @item_2b_math_2_id, 28.00, NULL),
-- 2B Physics - Nina Kobal
(@enroll_kobal_id, @item_2b_physics_1_id, 38.00, 'Dobro razumevanje fizikalnih zakonov'),
(@enroll_kobal_id, @item_2b_physics_2_id, 19.00, 'Natančno izvedeno delo'),
-- 3C English - Eva Novak
(@enroll_novak_id, @item_3c_english_1_id, 48.00, 'Excellent knowledge of grammar and vocabulary'),
(@enroll_novak_id, @item_3c_english_2_id, 29.00, 'Fluent speech with minor pronunciation issues'),
(@enroll_novak_id, @item_3c_english_3_id, 18.00, 'Well-structured essay with some creative ideas');

-- Attendance
INSERT INTO attendance (enroll_id, period_id, status, justification, approved, reject_reason)
VALUES
-- 1A Math Periods - Various Students
(@enroll_kranjc_id, @period_1a_math_1_id, 'P', NULL, NULL, NULL),                             -- Maja present
(@enroll_polanc_id, @period_1a_math_1_id, 'P', NULL, NULL, NULL),                             -- Peter present
(@enroll_zajc_id, @period_1a_math_1_id, 'P', NULL, NULL, NULL),                               -- Luka present
(@enroll_vidmar_id, @period_1a_math_1_id, 'P', NULL, NULL, NULL),                             -- Katja present

(@enroll_kranjc_id, @period_1a_math_2_id, 'P', NULL, NULL, NULL),                             -- Maja present
(@enroll_polanc_id, @period_1a_math_2_id, 'A', 'Zdravniški pregled', TRUE, NULL),             -- Peter absent, justified
(@enroll_zajc_id, @period_1a_math_2_id, 'P', NULL, NULL, NULL),                               -- Luka present
(@enroll_vidmar_id, @period_1a_math_2_id, 'P', NULL, NULL, NULL),                             -- Katja present

(@enroll_kranjc_id, @period_1a_math_3_id, 'P', NULL, NULL, NULL),                             -- Maja present
(@enroll_polanc_id, @period_1a_math_3_id, 'P', NULL, NULL, NULL),                             -- Peter present
(@enroll_zajc_id, @period_1a_math_3_id, 'L', 'Zamuda avtobusa', TRUE, NULL),                  -- Luka late, justified
(@enroll_vidmar_id, @period_1a_math_3_id, 'A', 'Bolezen', TRUE, NULL),                        -- Katja absent, justified

(@enroll_kranjc_id, @period_1a_math_4_id, 'P', NULL, NULL, NULL),                             -- Maja present
(@enroll_polanc_id, @period_1a_math_4_id, 'P', NULL, NULL, NULL),                             -- Peter present
(@enroll_zajc_id, @period_1a_math_4_id, 'P', NULL, NULL, NULL),                               -- Luka present
(@enroll_vidmar_id, @period_1a_math_4_id, 'P', NULL, NULL, NULL),                             -- Katja present

-- 1A Slovenian Periods
(@enroll_kranjc_id, @period_1a_slovenian_1_id, 'P', NULL, NULL, NULL),                        -- Maja present
(@enroll_polanc_id, @period_1a_slovenian_1_id, 'P', NULL, NULL, NULL),                        -- Peter present
(@enroll_zajc_id, @period_1a_slovenian_1_id, 'A', 'Zaspal', FALSE, 'Neopravičljiv razlog'),   -- Luka absent, unjustified
(@enroll_vidmar_id, @period_1a_slovenian_1_id, 'P', NULL, NULL, NULL),                        -- Katja present

-- 2B Math Periods - Jan Zupančič
(@enroll_zupancic_id, @period_2b_math_1_id, 'P', NULL, NULL, NULL),
(@enroll_zupancic_id, @period_2b_math_2_id, 'A', 'Športno tekmovanje', TRUE, NULL),
(@enroll_zupancic_id, @period_2b_math_3_id, 'P', NULL, NULL, NULL),
(@enroll_zupancic_id, @period_2b_math_4_id, 'P', NULL, NULL, NULL),

-- 3C English Periods - Various Students
(@enroll_novak_id, @period_3c_english_1_id, 'P', NULL, NULL, NULL),                           -- Eva present
(@enroll_kralj_id, @period_3c_english_1_id, 'P', NULL, NULL, NULL),                           -- Anže present
(@enroll_kos_id, @period_3c_english_1_id, 'A', 'Zdravniške težave', NULL, NULL),              -- Zala absent, pending justification

(@enroll_novak_id, @period_3c_english_2_id, 'P', NULL, NULL, NULL),                           -- Eva present
(@enroll_kralj_id, @period_3c_english_2_id, 'L', 'Zadrževanje pri prejšnji uri', TRUE, NULL), -- Anže late, justified
(@enroll_kos_id, @period_3c_english_2_id, 'P', NULL, NULL, NULL),                             -- Zala present

(@enroll_novak_id, @period_3c_english_3_id, 'P', NULL, NULL, NULL),                           -- Eva present
(@enroll_kralj_id, @period_3c_english_3_id, 'P', NULL, NULL, NULL),                           -- Anže present
(@enroll_kos_id, @period_3c_english_3_id, 'P', NULL, NULL, NULL); -- Zala present
