-- ============================================================================
-- INVESTHOOD PLATFORM: ADDITIVE COMPREHENSIVE DEMO DATA EXPANSION
-- Built for the supplied investhood_platform SQL dump (MariaDB 10.4).
--
-- Adds, without deleting or overwriting existing records:
--   * 200 synthetic candidate accounts and their profiles / related records
--   * 10 new programmes and 50 new cohorts (5 per new programme)
--   * 200 cohort memberships, 200 applications and 10 opportunities
--   * assessment outcomes, criteria, attempts, evidence and decisions
--   * attendance, progress, notifications, programme plans and demo finance rows
--
-- DEMO ONLY. Candidate names/contact details and financial figures are synthetic.
-- Emails use the reserved .test domain. Consent rows are clearly marked as demo.
-- Do not import synthetic consent, financial or candidate data into production.
--
-- Back up the database first. Run this file against investhood_platform.
-- Safe to re-run: generated rows are keyed by SEED prefixes and guarded against
-- duplicates. Existing records are not deleted or intentionally updated.
-- ============================================================================
SET NAMES utf8mb4;
SET time_zone = '+02:00';
START TRANSACTION;

-- 1) Add 200 synthetic candidate accounts.
INSERT IGNORE INTO users
 (role_id, first_name, last_name, username, email, phone, date_of_birth, gender, province,
  employment_status, qualification_level, professional_title, password_hash, status, email_verified_at)
SELECT r.id, d.first_name, d.last_name, d.username, d.email, d.phone, d.dob, d.gender, d.province,
       d.employment_status, d.qualification_level, d.professional_title,
       '$2y$12$ex9l4SK8rWiAeI5KzgqfuuyT515R2CrQRi5wpM.2mLY1E9yMLkGmy', 'active', NOW()
FROM roles r
JOIN (
 SELECT 'Ayanda' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_001' AS username, 'seed.candidate.001@demo.investhood.test' AS email, '0000000001' AS phone, '1990-02-02' AS dob, 'male' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_002' AS username, 'seed.candidate.002@demo.investhood.test' AS email, '0000000002' AS phone, '1991-03-03' AS dob, 'other' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_003' AS username, 'seed.candidate.003@demo.investhood.test' AS email, '0000000003' AS phone, '1992-04-04' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_004' AS username, 'seed.candidate.004@demo.investhood.test' AS email, '0000000004' AS phone, '1993-05-05' AS dob, 'male' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_005' AS username, 'seed.candidate.005@demo.investhood.test' AS email, '0000000005' AS phone, '1994-06-06' AS dob, 'other' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_006' AS username, 'seed.candidate.006@demo.investhood.test' AS email, '0000000006' AS phone, '1995-07-07' AS dob, 'female' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_007' AS username, 'seed.candidate.007@demo.investhood.test' AS email, '0000000007' AS phone, '1996-08-08' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_008' AS username, 'seed.candidate.008@demo.investhood.test' AS email, '0000000008' AS phone, '1997-09-09' AS dob, 'other' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_009' AS username, 'seed.candidate.009@demo.investhood.test' AS email, '0000000009' AS phone, '1998-10-10' AS dob, 'female' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_010' AS username, 'seed.candidate.010@demo.investhood.test' AS email, '0000000010' AS phone, '1999-11-11' AS dob, 'male' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_011' AS username, 'seed.candidate.011@demo.investhood.test' AS email, '0000000011' AS phone, '2000-12-12' AS dob, 'other' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_012' AS username, 'seed.candidate.012@demo.investhood.test' AS email, '0000000012' AS phone, '2001-01-13' AS dob, 'female' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_013' AS username, 'seed.candidate.013@demo.investhood.test' AS email, '0000000013' AS phone, '2002-02-14' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_014' AS username, 'seed.candidate.014@demo.investhood.test' AS email, '0000000014' AS phone, '2003-03-15' AS dob, 'other' AS gender, 'western cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_015' AS username, 'seed.candidate.015@demo.investhood.test' AS email, '0000000015' AS phone, '2004-04-16' AS dob, 'female' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_016' AS username, 'seed.candidate.016@demo.investhood.test' AS email, '0000000016' AS phone, '1989-05-17' AS dob, 'male' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_017' AS username, 'seed.candidate.017@demo.investhood.test' AS email, '0000000017' AS phone, '1990-06-18' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_018' AS username, 'seed.candidate.018@demo.investhood.test' AS email, '0000000018' AS phone, '1991-07-19' AS dob, 'female' AS gender, 'free state' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_019' AS username, 'seed.candidate.019@demo.investhood.test' AS email, '0000000019' AS phone, '1992-08-20' AS dob, 'male' AS gender, 'north west' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Mokoena' AS last_name, 'seed_candidate_020' AS username, 'seed.candidate.020@demo.investhood.test' AS email, '0000000020' AS phone, '1993-09-21' AS dob, 'other' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_021' AS username, 'seed.candidate.021@demo.investhood.test' AS email, '0000000021' AS phone, '1994-10-22' AS dob, 'female' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_022' AS username, 'seed.candidate.022@demo.investhood.test' AS email, '0000000022' AS phone, '1995-11-23' AS dob, 'male' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_023' AS username, 'seed.candidate.023@demo.investhood.test' AS email, '0000000023' AS phone, '1996-12-24' AS dob, 'other' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_024' AS username, 'seed.candidate.024@demo.investhood.test' AS email, '0000000024' AS phone, '1997-01-25' AS dob, 'female' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_025' AS username, 'seed.candidate.025@demo.investhood.test' AS email, '0000000025' AS phone, '1998-02-26' AS dob, 'male' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_026' AS username, 'seed.candidate.026@demo.investhood.test' AS email, '0000000026' AS phone, '1999-03-27' AS dob, 'other' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_027' AS username, 'seed.candidate.027@demo.investhood.test' AS email, '0000000027' AS phone, '2000-04-01' AS dob, 'female' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_028' AS username, 'seed.candidate.028@demo.investhood.test' AS email, '0000000028' AS phone, '2001-05-02' AS dob, 'male' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_029' AS username, 'seed.candidate.029@demo.investhood.test' AS email, '0000000029' AS phone, '2002-06-03' AS dob, 'other' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_030' AS username, 'seed.candidate.030@demo.investhood.test' AS email, '0000000030' AS phone, '2003-07-04' AS dob, 'female' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_031' AS username, 'seed.candidate.031@demo.investhood.test' AS email, '0000000031' AS phone, '2004-08-05' AS dob, 'male' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_032' AS username, 'seed.candidate.032@demo.investhood.test' AS email, '0000000032' AS phone, '1989-09-06' AS dob, 'other' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_033' AS username, 'seed.candidate.033@demo.investhood.test' AS email, '0000000033' AS phone, '1990-10-07' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_034' AS username, 'seed.candidate.034@demo.investhood.test' AS email, '0000000034' AS phone, '1991-11-08' AS dob, 'male' AS gender, 'western cape' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_035' AS username, 'seed.candidate.035@demo.investhood.test' AS email, '0000000035' AS phone, '1992-12-09' AS dob, 'other' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_036' AS username, 'seed.candidate.036@demo.investhood.test' AS email, '0000000036' AS phone, '1993-01-10' AS dob, 'female' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_037' AS username, 'seed.candidate.037@demo.investhood.test' AS email, '0000000037' AS phone, '1994-02-11' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_038' AS username, 'seed.candidate.038@demo.investhood.test' AS email, '0000000038' AS phone, '1995-03-12' AS dob, 'other' AS gender, 'free state' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_039' AS username, 'seed.candidate.039@demo.investhood.test' AS email, '0000000039' AS phone, '1996-04-13' AS dob, 'female' AS gender, 'north west' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Dlamini' AS last_name, 'seed_candidate_040' AS username, 'seed.candidate.040@demo.investhood.test' AS email, '0000000040' AS phone, '1997-05-14' AS dob, 'male' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_041' AS username, 'seed.candidate.041@demo.investhood.test' AS email, '0000000041' AS phone, '1998-06-15' AS dob, 'other' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_042' AS username, 'seed.candidate.042@demo.investhood.test' AS email, '0000000042' AS phone, '1999-07-16' AS dob, 'female' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_043' AS username, 'seed.candidate.043@demo.investhood.test' AS email, '0000000043' AS phone, '2000-08-17' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_044' AS username, 'seed.candidate.044@demo.investhood.test' AS email, '0000000044' AS phone, '2001-09-18' AS dob, 'other' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_045' AS username, 'seed.candidate.045@demo.investhood.test' AS email, '0000000045' AS phone, '2002-10-19' AS dob, 'female' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_046' AS username, 'seed.candidate.046@demo.investhood.test' AS email, '0000000046' AS phone, '2003-11-20' AS dob, 'male' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_047' AS username, 'seed.candidate.047@demo.investhood.test' AS email, '0000000047' AS phone, '2004-12-21' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_048' AS username, 'seed.candidate.048@demo.investhood.test' AS email, '0000000048' AS phone, '1989-01-22' AS dob, 'female' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_049' AS username, 'seed.candidate.049@demo.investhood.test' AS email, '0000000049' AS phone, '1990-02-23' AS dob, 'male' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_050' AS username, 'seed.candidate.050@demo.investhood.test' AS email, '0000000050' AS phone, '1991-03-24' AS dob, 'other' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_051' AS username, 'seed.candidate.051@demo.investhood.test' AS email, '0000000051' AS phone, '1992-04-25' AS dob, 'female' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_052' AS username, 'seed.candidate.052@demo.investhood.test' AS email, '0000000052' AS phone, '1993-05-26' AS dob, 'male' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_053' AS username, 'seed.candidate.053@demo.investhood.test' AS email, '0000000053' AS phone, '1994-06-27' AS dob, 'other' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_054' AS username, 'seed.candidate.054@demo.investhood.test' AS email, '0000000054' AS phone, '1995-07-01' AS dob, 'female' AS gender, 'western cape' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_055' AS username, 'seed.candidate.055@demo.investhood.test' AS email, '0000000055' AS phone, '1996-08-02' AS dob, 'male' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_056' AS username, 'seed.candidate.056@demo.investhood.test' AS email, '0000000056' AS phone, '1997-09-03' AS dob, 'other' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_057' AS username, 'seed.candidate.057@demo.investhood.test' AS email, '0000000057' AS phone, '1998-10-04' AS dob, 'female' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_058' AS username, 'seed.candidate.058@demo.investhood.test' AS email, '0000000058' AS phone, '1999-11-05' AS dob, 'male' AS gender, 'free state' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_059' AS username, 'seed.candidate.059@demo.investhood.test' AS email, '0000000059' AS phone, '2000-12-06' AS dob, 'other' AS gender, 'north west' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Nkosi' AS last_name, 'seed_candidate_060' AS username, 'seed.candidate.060@demo.investhood.test' AS email, '0000000060' AS phone, '2001-01-07' AS dob, 'female' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Molefe' AS last_name, 'seed_candidate_061' AS username, 'seed.candidate.061@demo.investhood.test' AS email, '0000000061' AS phone, '2002-02-08' AS dob, 'male' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Molefe' AS last_name, 'seed_candidate_062' AS username, 'seed.candidate.062@demo.investhood.test' AS email, '0000000062' AS phone, '2003-03-09' AS dob, 'other' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Molefe' AS last_name, 'seed_candidate_063' AS username, 'seed.candidate.063@demo.investhood.test' AS email, '0000000063' AS phone, '2004-04-10' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Molefe' AS last_name, 'seed_candidate_064' AS username, 'seed.candidate.064@demo.investhood.test' AS email, '0000000064' AS phone, '1989-05-11' AS dob, 'male' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Molefe' AS last_name, 'seed_candidate_065' AS username, 'seed.candidate.065@demo.investhood.test' AS email, '0000000065' AS phone, '1990-06-12' AS dob, 'other' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Molefe' AS last_name, 'seed_candidate_066' AS username, 'seed.candidate.066@demo.investhood.test' AS email, '0000000066' AS phone, '1991-07-13' AS dob, 'female' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Molefe' AS last_name, 'seed_candidate_067' AS username, 'seed.candidate.067@demo.investhood.test' AS email, '0000000067' AS phone, '1992-08-14' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Molefe' AS last_name, 'seed_candidate_068' AS username, 'seed.candidate.068@demo.investhood.test' AS email, '0000000068' AS phone, '1993-09-15' AS dob, 'other' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Molefe' AS last_name, 'seed_candidate_069' AS username, 'seed.candidate.069@demo.investhood.test' AS email, '0000000069' AS phone, '1994-10-16' AS dob, 'female' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Molefe' AS last_name, 'seed_candidate_070' AS username, 'seed.candidate.070@demo.investhood.test' AS email, '0000000070' AS phone, '1995-11-17' AS dob, 'male' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Molefe' AS last_name, 'seed_candidate_071' AS username, 'seed.candidate.071@demo.investhood.test' AS email, '0000000071' AS phone, '1996-12-18' AS dob, 'other' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Molefe' AS last_name, 'seed_candidate_072' AS username, 'seed.candidate.072@demo.investhood.test' AS email, '0000000072' AS phone, '1997-01-19' AS dob, 'female' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Molefe' AS last_name, 'seed_candidate_073' AS username, 'seed.candidate.073@demo.investhood.test' AS email, '0000000073' AS phone, '1998-02-20' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Molefe' AS last_name, 'seed_candidate_074' AS username, 'seed.candidate.074@demo.investhood.test' AS email, '0000000074' AS phone, '1999-03-21' AS dob, 'other' AS gender, 'western cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Molefe' AS last_name, 'seed_candidate_075' AS username, 'seed.candidate.075@demo.investhood.test' AS email, '0000000075' AS phone, '2000-04-22' AS dob, 'female' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Molefe' AS last_name, 'seed_candidate_076' AS username, 'seed.candidate.076@demo.investhood.test' AS email, '0000000076' AS phone, '2001-05-23' AS dob, 'male' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Molefe' AS last_name, 'seed_candidate_077' AS username, 'seed.candidate.077@demo.investhood.test' AS email, '0000000077' AS phone, '2002-06-24' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Molefe' AS last_name, 'seed_candidate_078' AS username, 'seed.candidate.078@demo.investhood.test' AS email, '0000000078' AS phone, '2003-07-25' AS dob, 'female' AS gender, 'free state' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Molefe' AS last_name, 'seed_candidate_079' AS username, 'seed.candidate.079@demo.investhood.test' AS email, '0000000079' AS phone, '2004-08-26' AS dob, 'male' AS gender, 'north west' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Molefe' AS last_name, 'seed_candidate_080' AS username, 'seed.candidate.080@demo.investhood.test' AS email, '0000000080' AS phone, '1989-09-27' AS dob, 'other' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_081' AS username, 'seed.candidate.081@demo.investhood.test' AS email, '0000000081' AS phone, '1990-10-01' AS dob, 'female' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_082' AS username, 'seed.candidate.082@demo.investhood.test' AS email, '0000000082' AS phone, '1991-11-02' AS dob, 'male' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_083' AS username, 'seed.candidate.083@demo.investhood.test' AS email, '0000000083' AS phone, '1992-12-03' AS dob, 'other' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_084' AS username, 'seed.candidate.084@demo.investhood.test' AS email, '0000000084' AS phone, '1993-01-04' AS dob, 'female' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_085' AS username, 'seed.candidate.085@demo.investhood.test' AS email, '0000000085' AS phone, '1994-02-05' AS dob, 'male' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_086' AS username, 'seed.candidate.086@demo.investhood.test' AS email, '0000000086' AS phone, '1995-03-06' AS dob, 'other' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_087' AS username, 'seed.candidate.087@demo.investhood.test' AS email, '0000000087' AS phone, '1996-04-07' AS dob, 'female' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_088' AS username, 'seed.candidate.088@demo.investhood.test' AS email, '0000000088' AS phone, '1997-05-08' AS dob, 'male' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_089' AS username, 'seed.candidate.089@demo.investhood.test' AS email, '0000000089' AS phone, '1998-06-09' AS dob, 'other' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_090' AS username, 'seed.candidate.090@demo.investhood.test' AS email, '0000000090' AS phone, '1999-07-10' AS dob, 'female' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_091' AS username, 'seed.candidate.091@demo.investhood.test' AS email, '0000000091' AS phone, '2000-08-11' AS dob, 'male' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_092' AS username, 'seed.candidate.092@demo.investhood.test' AS email, '0000000092' AS phone, '2001-09-12' AS dob, 'other' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_093' AS username, 'seed.candidate.093@demo.investhood.test' AS email, '0000000093' AS phone, '2002-10-13' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_094' AS username, 'seed.candidate.094@demo.investhood.test' AS email, '0000000094' AS phone, '2003-11-14' AS dob, 'male' AS gender, 'western cape' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_095' AS username, 'seed.candidate.095@demo.investhood.test' AS email, '0000000095' AS phone, '2004-12-15' AS dob, 'other' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_096' AS username, 'seed.candidate.096@demo.investhood.test' AS email, '0000000096' AS phone, '1989-01-16' AS dob, 'female' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_097' AS username, 'seed.candidate.097@demo.investhood.test' AS email, '0000000097' AS phone, '1990-02-17' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_098' AS username, 'seed.candidate.098@demo.investhood.test' AS email, '0000000098' AS phone, '1991-03-18' AS dob, 'other' AS gender, 'free state' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_099' AS username, 'seed.candidate.099@demo.investhood.test' AS email, '0000000099' AS phone, '1992-04-19' AS dob, 'female' AS gender, 'north west' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Khumalo' AS last_name, 'seed_candidate_100' AS username, 'seed.candidate.100@demo.investhood.test' AS email, '0000000100' AS phone, '1993-05-20' AS dob, 'male' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_101' AS username, 'seed.candidate.101@demo.investhood.test' AS email, '0000000101' AS phone, '1994-06-21' AS dob, 'other' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_102' AS username, 'seed.candidate.102@demo.investhood.test' AS email, '0000000102' AS phone, '1995-07-22' AS dob, 'female' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_103' AS username, 'seed.candidate.103@demo.investhood.test' AS email, '0000000103' AS phone, '1996-08-23' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_104' AS username, 'seed.candidate.104@demo.investhood.test' AS email, '0000000104' AS phone, '1997-09-24' AS dob, 'other' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_105' AS username, 'seed.candidate.105@demo.investhood.test' AS email, '0000000105' AS phone, '1998-10-25' AS dob, 'female' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_106' AS username, 'seed.candidate.106@demo.investhood.test' AS email, '0000000106' AS phone, '1999-11-26' AS dob, 'male' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_107' AS username, 'seed.candidate.107@demo.investhood.test' AS email, '0000000107' AS phone, '2000-12-27' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_108' AS username, 'seed.candidate.108@demo.investhood.test' AS email, '0000000108' AS phone, '2001-01-01' AS dob, 'female' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_109' AS username, 'seed.candidate.109@demo.investhood.test' AS email, '0000000109' AS phone, '2002-02-02' AS dob, 'male' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_110' AS username, 'seed.candidate.110@demo.investhood.test' AS email, '0000000110' AS phone, '2003-03-03' AS dob, 'other' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_111' AS username, 'seed.candidate.111@demo.investhood.test' AS email, '0000000111' AS phone, '2004-04-04' AS dob, 'female' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_112' AS username, 'seed.candidate.112@demo.investhood.test' AS email, '0000000112' AS phone, '1989-05-05' AS dob, 'male' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_113' AS username, 'seed.candidate.113@demo.investhood.test' AS email, '0000000113' AS phone, '1990-06-06' AS dob, 'other' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_114' AS username, 'seed.candidate.114@demo.investhood.test' AS email, '0000000114' AS phone, '1991-07-07' AS dob, 'female' AS gender, 'western cape' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_115' AS username, 'seed.candidate.115@demo.investhood.test' AS email, '0000000115' AS phone, '1992-08-08' AS dob, 'male' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_116' AS username, 'seed.candidate.116@demo.investhood.test' AS email, '0000000116' AS phone, '1993-09-09' AS dob, 'other' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_117' AS username, 'seed.candidate.117@demo.investhood.test' AS email, '0000000117' AS phone, '1994-10-10' AS dob, 'female' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_118' AS username, 'seed.candidate.118@demo.investhood.test' AS email, '0000000118' AS phone, '1995-11-11' AS dob, 'male' AS gender, 'free state' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_119' AS username, 'seed.candidate.119@demo.investhood.test' AS email, '0000000119' AS phone, '1996-12-12' AS dob, 'other' AS gender, 'north west' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Ndlovu' AS last_name, 'seed_candidate_120' AS username, 'seed.candidate.120@demo.investhood.test' AS email, '0000000120' AS phone, '1997-01-13' AS dob, 'female' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_121' AS username, 'seed.candidate.121@demo.investhood.test' AS email, '0000000121' AS phone, '1998-02-14' AS dob, 'male' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_122' AS username, 'seed.candidate.122@demo.investhood.test' AS email, '0000000122' AS phone, '1999-03-15' AS dob, 'other' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_123' AS username, 'seed.candidate.123@demo.investhood.test' AS email, '0000000123' AS phone, '2000-04-16' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_124' AS username, 'seed.candidate.124@demo.investhood.test' AS email, '0000000124' AS phone, '2001-05-17' AS dob, 'male' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_125' AS username, 'seed.candidate.125@demo.investhood.test' AS email, '0000000125' AS phone, '2002-06-18' AS dob, 'other' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_126' AS username, 'seed.candidate.126@demo.investhood.test' AS email, '0000000126' AS phone, '2003-07-19' AS dob, 'female' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_127' AS username, 'seed.candidate.127@demo.investhood.test' AS email, '0000000127' AS phone, '2004-08-20' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_128' AS username, 'seed.candidate.128@demo.investhood.test' AS email, '0000000128' AS phone, '1989-09-21' AS dob, 'other' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_129' AS username, 'seed.candidate.129@demo.investhood.test' AS email, '0000000129' AS phone, '1990-10-22' AS dob, 'female' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_130' AS username, 'seed.candidate.130@demo.investhood.test' AS email, '0000000130' AS phone, '1991-11-23' AS dob, 'male' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_131' AS username, 'seed.candidate.131@demo.investhood.test' AS email, '0000000131' AS phone, '1992-12-24' AS dob, 'other' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_132' AS username, 'seed.candidate.132@demo.investhood.test' AS email, '0000000132' AS phone, '1993-01-25' AS dob, 'female' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_133' AS username, 'seed.candidate.133@demo.investhood.test' AS email, '0000000133' AS phone, '1994-02-26' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_134' AS username, 'seed.candidate.134@demo.investhood.test' AS email, '0000000134' AS phone, '1995-03-27' AS dob, 'other' AS gender, 'western cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_135' AS username, 'seed.candidate.135@demo.investhood.test' AS email, '0000000135' AS phone, '1996-04-01' AS dob, 'female' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_136' AS username, 'seed.candidate.136@demo.investhood.test' AS email, '0000000136' AS phone, '1997-05-02' AS dob, 'male' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_137' AS username, 'seed.candidate.137@demo.investhood.test' AS email, '0000000137' AS phone, '1998-06-03' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_138' AS username, 'seed.candidate.138@demo.investhood.test' AS email, '0000000138' AS phone, '1999-07-04' AS dob, 'female' AS gender, 'free state' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_139' AS username, 'seed.candidate.139@demo.investhood.test' AS email, '0000000139' AS phone, '2000-08-05' AS dob, 'male' AS gender, 'north west' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Mahlangu' AS last_name, 'seed_candidate_140' AS username, 'seed.candidate.140@demo.investhood.test' AS email, '0000000140' AS phone, '2001-09-06' AS dob, 'other' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_141' AS username, 'seed.candidate.141@demo.investhood.test' AS email, '0000000141' AS phone, '2002-10-07' AS dob, 'female' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_142' AS username, 'seed.candidate.142@demo.investhood.test' AS email, '0000000142' AS phone, '2003-11-08' AS dob, 'male' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_143' AS username, 'seed.candidate.143@demo.investhood.test' AS email, '0000000143' AS phone, '2004-12-09' AS dob, 'other' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_144' AS username, 'seed.candidate.144@demo.investhood.test' AS email, '0000000144' AS phone, '1989-01-10' AS dob, 'female' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_145' AS username, 'seed.candidate.145@demo.investhood.test' AS email, '0000000145' AS phone, '1990-02-11' AS dob, 'male' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_146' AS username, 'seed.candidate.146@demo.investhood.test' AS email, '0000000146' AS phone, '1991-03-12' AS dob, 'other' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_147' AS username, 'seed.candidate.147@demo.investhood.test' AS email, '0000000147' AS phone, '1992-04-13' AS dob, 'female' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_148' AS username, 'seed.candidate.148@demo.investhood.test' AS email, '0000000148' AS phone, '1993-05-14' AS dob, 'male' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_149' AS username, 'seed.candidate.149@demo.investhood.test' AS email, '0000000149' AS phone, '1994-06-15' AS dob, 'other' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_150' AS username, 'seed.candidate.150@demo.investhood.test' AS email, '0000000150' AS phone, '1995-07-16' AS dob, 'female' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_151' AS username, 'seed.candidate.151@demo.investhood.test' AS email, '0000000151' AS phone, '1996-08-17' AS dob, 'male' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_152' AS username, 'seed.candidate.152@demo.investhood.test' AS email, '0000000152' AS phone, '1997-09-18' AS dob, 'other' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_153' AS username, 'seed.candidate.153@demo.investhood.test' AS email, '0000000153' AS phone, '1998-10-19' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_154' AS username, 'seed.candidate.154@demo.investhood.test' AS email, '0000000154' AS phone, '1999-11-20' AS dob, 'male' AS gender, 'western cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_155' AS username, 'seed.candidate.155@demo.investhood.test' AS email, '0000000155' AS phone, '2000-12-21' AS dob, 'other' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_156' AS username, 'seed.candidate.156@demo.investhood.test' AS email, '0000000156' AS phone, '2001-01-22' AS dob, 'female' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_157' AS username, 'seed.candidate.157@demo.investhood.test' AS email, '0000000157' AS phone, '2002-02-23' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_158' AS username, 'seed.candidate.158@demo.investhood.test' AS email, '0000000158' AS phone, '2003-03-24' AS dob, 'other' AS gender, 'free state' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_159' AS username, 'seed.candidate.159@demo.investhood.test' AS email, '0000000159' AS phone, '2004-04-25' AS dob, 'female' AS gender, 'north west' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Mthembu' AS last_name, 'seed_candidate_160' AS username, 'seed.candidate.160@demo.investhood.test' AS email, '0000000160' AS phone, '1989-05-26' AS dob, 'male' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Sithole' AS last_name, 'seed_candidate_161' AS username, 'seed.candidate.161@demo.investhood.test' AS email, '0000000161' AS phone, '1990-06-27' AS dob, 'other' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Sithole' AS last_name, 'seed_candidate_162' AS username, 'seed.candidate.162@demo.investhood.test' AS email, '0000000162' AS phone, '1991-07-01' AS dob, 'female' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Sithole' AS last_name, 'seed_candidate_163' AS username, 'seed.candidate.163@demo.investhood.test' AS email, '0000000163' AS phone, '1992-08-02' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Sithole' AS last_name, 'seed_candidate_164' AS username, 'seed.candidate.164@demo.investhood.test' AS email, '0000000164' AS phone, '1993-09-03' AS dob, 'other' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Sithole' AS last_name, 'seed_candidate_165' AS username, 'seed.candidate.165@demo.investhood.test' AS email, '0000000165' AS phone, '1994-10-04' AS dob, 'female' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Sithole' AS last_name, 'seed_candidate_166' AS username, 'seed.candidate.166@demo.investhood.test' AS email, '0000000166' AS phone, '1995-11-05' AS dob, 'male' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Sithole' AS last_name, 'seed_candidate_167' AS username, 'seed.candidate.167@demo.investhood.test' AS email, '0000000167' AS phone, '1996-12-06' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Sithole' AS last_name, 'seed_candidate_168' AS username, 'seed.candidate.168@demo.investhood.test' AS email, '0000000168' AS phone, '1997-01-07' AS dob, 'female' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Sithole' AS last_name, 'seed_candidate_169' AS username, 'seed.candidate.169@demo.investhood.test' AS email, '0000000169' AS phone, '1998-02-08' AS dob, 'male' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Sithole' AS last_name, 'seed_candidate_170' AS username, 'seed.candidate.170@demo.investhood.test' AS email, '0000000170' AS phone, '1999-03-09' AS dob, 'other' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Sithole' AS last_name, 'seed_candidate_171' AS username, 'seed.candidate.171@demo.investhood.test' AS email, '0000000171' AS phone, '2000-04-10' AS dob, 'female' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Sithole' AS last_name, 'seed_candidate_172' AS username, 'seed.candidate.172@demo.investhood.test' AS email, '0000000172' AS phone, '2001-05-11' AS dob, 'male' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Sithole' AS last_name, 'seed_candidate_173' AS username, 'seed.candidate.173@demo.investhood.test' AS email, '0000000173' AS phone, '2002-06-12' AS dob, 'other' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Sithole' AS last_name, 'seed_candidate_174' AS username, 'seed.candidate.174@demo.investhood.test' AS email, '0000000174' AS phone, '2003-07-13' AS dob, 'female' AS gender, 'western cape' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Sithole' AS last_name, 'seed_candidate_175' AS username, 'seed.candidate.175@demo.investhood.test' AS email, '0000000175' AS phone, '2004-08-14' AS dob, 'male' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Sithole' AS last_name, 'seed_candidate_176' AS username, 'seed.candidate.176@demo.investhood.test' AS email, '0000000176' AS phone, '1989-09-15' AS dob, 'other' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Sithole' AS last_name, 'seed_candidate_177' AS username, 'seed.candidate.177@demo.investhood.test' AS email, '0000000177' AS phone, '1990-10-16' AS dob, 'female' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'degree' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Sithole' AS last_name, 'seed_candidate_178' AS username, 'seed.candidate.178@demo.investhood.test' AS email, '0000000178' AS phone, '1991-11-17' AS dob, 'male' AS gender, 'free state' AS province, 'student' AS employment_status, 'certificate' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Sithole' AS last_name, 'seed_candidate_179' AS username, 'seed.candidate.179@demo.investhood.test' AS email, '0000000179' AS phone, '1992-12-18' AS dob, 'other' AS gender, 'north west' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Sithole' AS last_name, 'seed_candidate_180' AS username, 'seed.candidate.180@demo.investhood.test' AS email, '0000000180' AS phone, '1993-01-19' AS dob, 'female' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Ayanda' AS first_name, 'Mabena' AS last_name, 'seed_candidate_181' AS username, 'seed.candidate.181@demo.investhood.test' AS email, '0000000181' AS phone, '1994-02-20' AS dob, 'male' AS gender, 'gauteng' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Thabo' AS first_name, 'Mabena' AS last_name, 'seed_candidate_182' AS username, 'seed.candidate.182@demo.investhood.test' AS email, '0000000182' AS phone, '1995-03-21' AS dob, 'other' AS gender, 'gauteng' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Lerato' AS first_name, 'Mabena' AS last_name, 'seed_candidate_183' AS username, 'seed.candidate.183@demo.investhood.test' AS email, '0000000183' AS phone, '1996-04-22' AS dob, 'female' AS gender, 'kwazulu-natal' AS province, 'employed' AS employment_status, 'diploma' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Kabelo' AS first_name, 'Mabena' AS last_name, 'seed_candidate_184' AS username, 'seed.candidate.184@demo.investhood.test' AS email, '0000000184' AS phone, '1997-05-23' AS dob, 'male' AS gender, 'western cape' AS province, 'unemployed' AS employment_status, 'degree' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Naledi' AS first_name, 'Mabena' AS last_name, 'seed_candidate_185' AS username, 'seed.candidate.185@demo.investhood.test' AS email, '0000000185' AS phone, '1998-06-24' AS dob, 'other' AS gender, 'eastern cape' AS province, 'recent-graduate' AS employment_status, 'certificate' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Sibusiso' AS first_name, 'Mabena' AS last_name, 'seed_candidate_186' AS username, 'seed.candidate.186@demo.investhood.test' AS email, '0000000186' AS phone, '1999-07-25' AS dob, 'female' AS gender, 'limpopo' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Amahle' AS first_name, 'Mabena' AS last_name, 'seed_candidate_187' AS username, 'seed.candidate.187@demo.investhood.test' AS email, '0000000187' AS phone, '2000-08-26' AS dob, 'male' AS gender, 'mpumalanga' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Sipho' AS first_name, 'Mabena' AS last_name, 'seed_candidate_188' AS username, 'seed.candidate.188@demo.investhood.test' AS email, '0000000188' AS phone, '2001-09-27' AS dob, 'other' AS gender, 'free state' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Thandeka' AS first_name, 'Mabena' AS last_name, 'seed_candidate_189' AS username, 'seed.candidate.189@demo.investhood.test' AS email, '0000000189' AS phone, '2002-10-01' AS dob, 'female' AS gender, 'north west' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Bongani' AS first_name, 'Mabena' AS last_name, 'seed_candidate_190' AS username, 'seed.candidate.190@demo.investhood.test' AS email, '0000000190' AS phone, '2003-11-02' AS dob, 'male' AS gender, 'northern cape' AS province, 'student' AS employment_status, 'diploma' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Zanele' AS first_name, 'Mabena' AS last_name, 'seed_candidate_191' AS username, 'seed.candidate.191@demo.investhood.test' AS email, '0000000191' AS phone, '2004-12-03' AS dob, 'other' AS gender, 'gauteng' AS province, 'employed' AS employment_status, 'degree' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Lindokuhle' AS first_name, 'Mabena' AS last_name, 'seed_candidate_192' AS username, 'seed.candidate.192@demo.investhood.test' AS email, '0000000192' AS phone, '1989-01-04' AS dob, 'female' AS gender, 'gauteng' AS province, 'unemployed' AS employment_status, 'certificate' AS qualification_level, 'Junior Software Developer' AS professional_title
 UNION ALL SELECT 'Mpho' AS first_name, 'Mabena' AS last_name, 'seed_candidate_193' AS username, 'seed.candidate.193@demo.investhood.test' AS email, '0000000193' AS phone, '1990-02-05' AS dob, 'male' AS gender, 'kwazulu-natal' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'IT Support Technician' AS professional_title
 UNION ALL SELECT 'Refilwe' AS first_name, 'Mabena' AS last_name, 'seed_candidate_194' AS username, 'seed.candidate.194@demo.investhood.test' AS email, '0000000194' AS phone, '1991-03-06' AS dob, 'other' AS gender, 'western cape' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Data Analyst Trainee' AS professional_title
 UNION ALL SELECT 'Karabo' AS first_name, 'Mabena' AS last_name, 'seed_candidate_195' AS username, 'seed.candidate.195@demo.investhood.test' AS email, '0000000195' AS phone, '1992-04-07' AS dob, 'female' AS gender, 'eastern cape' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Business Analyst Intern' AS professional_title
 UNION ALL SELECT 'Nokuthula' AS first_name, 'Mabena' AS last_name, 'seed_candidate_196' AS username, 'seed.candidate.196@demo.investhood.test' AS email, '0000000196' AS phone, '1993-05-08' AS dob, 'male' AS gender, 'limpopo' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Cybersecurity Trainee' AS professional_title
 UNION ALL SELECT 'Tshepo' AS first_name, 'Mabena' AS last_name, 'seed_candidate_197' AS username, 'seed.candidate.197@demo.investhood.test' AS email, '0000000197' AS phone, '1994-06-09' AS dob, 'other' AS gender, 'mpumalanga' AS province, 'recent-graduate' AS employment_status, 'diploma' AS qualification_level, 'UI/UX Design Intern' AS professional_title
 UNION ALL SELECT 'Palesa' AS first_name, 'Mabena' AS last_name, 'seed_candidate_198' AS username, 'seed.candidate.198@demo.investhood.test' AS email, '0000000198' AS phone, '1995-07-10' AS dob, 'female' AS gender, 'free state' AS province, 'student' AS employment_status, 'degree' AS qualification_level, 'Cloud Support Associate' AS professional_title
 UNION ALL SELECT 'Tumelo' AS first_name, 'Mabena' AS last_name, 'seed_candidate_199' AS username, 'seed.candidate.199@demo.investhood.test' AS email, '0000000199' AS phone, '1996-08-11' AS dob, 'male' AS gender, 'north west' AS province, 'employed' AS employment_status, 'certificate' AS qualification_level, 'Digital Marketing Trainee' AS professional_title
 UNION ALL SELECT 'Nomsa' AS first_name, 'Mabena' AS last_name, 'seed_candidate_200' AS username, 'seed.candidate.200@demo.investhood.test' AS email, '0000000200' AS phone, '1997-09-12' AS dob, 'other' AS gender, 'northern cape' AS province, 'unemployed' AS employment_status, 'diploma' AS qualification_level, 'Junior Software Developer' AS professional_title
) d ON 1=1
WHERE r.slug = 'candidate';

-- Temporary maps resolve generated IDs without assuming AUTO_INCREMENT values.
CREATE TEMPORARY TABLE tmp_seed_candidates (seq INT PRIMARY KEY, user_id INT UNSIGNED NOT NULL UNIQUE) ENGINE=MEMORY;
INSERT INTO tmp_seed_candidates (seq, user_id)
SELECT CAST(SUBSTRING(username, 16) AS UNSIGNED), id FROM users
WHERE username REGEXP '^seed_candidate_[0-9]{3}$';

-- 2) Candidate profiles, qualifications, skills, experience, certifications, consent and preferences.
INSERT IGNORE INTO candidate_profiles
 (user_id, professional_title, professional_summary, career_interests, employment_status, availability_status_id,
  availability_date, address, city, completion_percent, is_active)
SELECT u.id, u.professional_title,
 CONCAT('Synthetic demo profile for ', u.first_name, ' ', u.last_name, '. Candidate profile data for testing recruitment and programme workflows.'),
 CASE MOD(c.seq,5) WHEN 0 THEN 'Software development, databases and application support' WHEN 1 THEN 'IT support, networking and cybersecurity' WHEN 2 THEN 'Data analysis, reporting and business intelligence' WHEN 3 THEN 'Business analysis, project coordination and operations' ELSE 'Digital marketing, product design and mobile development' END,
 u.employment_status, (MOD(c.seq,3)+1), DATE_ADD('2026-11-11', INTERVAL MOD(c.seq,45) DAY),
 CONCAT('Demo Address ', c.seq, ', ', u.province),
 CASE u.province WHEN 'gauteng' THEN CASE MOD(c.seq,2) WHEN 0 THEN 'Johannesburg' ELSE 'Pretoria' END WHEN 'kwazulu-natal' THEN 'Durban' WHEN 'western cape' THEN 'Cape Town' WHEN 'eastern cape' THEN 'Gqeberha' WHEN 'limpopo' THEN 'Polokwane' WHEN 'mpumalanga' THEN 'Mbombela' WHEN 'free state' THEN 'Bloemfontein' WHEN 'north west' THEN 'Rustenburg' ELSE 'Kimberley' END,
 55 + MOD(c.seq,46), 1
FROM tmp_seed_candidates c JOIN users u ON u.id=c.user_id
WHERE NOT EXISTS (SELECT 1 FROM candidate_profiles cp WHERE cp.user_id=u.id);

INSERT INTO qualifications (user_id, name, institution, year_completed, level, verification_status)
SELECT u.id,
 CASE MOD(c.seq,7) WHEN 0 THEN 'Diploma in Information Technology' WHEN 1 THEN 'Bachelor of Information Technology' WHEN 2 THEN 'National Certificate: IT Systems Support' WHEN 3 THEN 'Diploma in Business Information Systems' WHEN 4 THEN 'Bachelor of Commerce' WHEN 5 THEN 'Higher Certificate in Data Analytics' ELSE 'Diploma in Software Development' END,
 CASE MOD(c.seq,7) WHEN 0 THEN 'Tshwane University of Technology' WHEN 1 THEN 'University of Johannesburg' WHEN 2 THEN 'TVET College' WHEN 3 THEN 'University of South Africa' WHEN 4 THEN 'University of Pretoria' WHEN 5 THEN 'Online Skills Academy' ELSE 'Cape Peninsula University of Technology' END,
 2019 + MOD(c.seq,7),
 CASE MOD(c.seq,7) WHEN 2 THEN 'Certificate' WHEN 5 THEN 'Certificate' WHEN 1 THEN 'Degree' WHEN 4 THEN 'Degree' ELSE 'Diploma' END,
 CASE WHEN MOD(c.seq,5)=0 THEN 'pending' ELSE 'unverified' END
FROM tmp_seed_candidates c JOIN users u ON u.id=c.user_id
WHERE NOT EXISTS (SELECT 1 FROM qualifications qf WHERE qf.user_id=u.id AND qf.name LIKE '%Information Technology%' OR qf.user_id=u.id AND qf.name LIKE '%Data Analytics%' OR qf.user_id=u.id AND qf.name LIKE '%Business%' OR qf.user_id=u.id AND qf.name LIKE '%Systems Support%' OR qf.user_id=u.id AND qf.name LIKE '%Commerce%' OR qf.user_id=u.id AND qf.name LIKE '%Software Development%');

INSERT IGNORE INTO candidate_skills (user_id, skill_id, proficiency, verification_status)
SELECT c.user_id, s.id,
 CASE WHEN s.category='soft' THEN 'intermediate' WHEN MOD(c.seq+s.id,4)=0 THEN 'advanced' WHEN MOD(c.seq+s.id,4)=1 THEN 'beginner' ELSE 'intermediate' END,
 CASE WHEN MOD(c.seq+s.id,9)=0 THEN 'pending' ELSE 'unverified' END
FROM tmp_seed_candidates c
JOIN skills s ON s.name IN ('PHP','JavaScript','MySQL','Python','SQL','HTML','CSS','Git','Communication','Teamwork','Problem Solving','Time Management');

INSERT INTO work_experience (user_id, job_title, company, start_date, end_date, is_current, description, sort_order)
SELECT c.user_id,
 CASE MOD(c.seq,4) WHEN 0 THEN 'IT Support Intern' WHEN 1 THEN 'Junior Developer Intern' WHEN 2 THEN 'Data Capturing Assistant' ELSE 'Digital Operations Trainee' END,
 'Demo Training Organisation', DATE_SUB('2026-01-01', INTERVAL (6 + MOD(c.seq,18)) MONTH),
 DATE_SUB('2026-01-01', INTERVAL MOD(c.seq,6) MONTH), 0,
 'Synthetic work experience record for testing candidate profile and recruitment workflows.', 1
FROM tmp_seed_candidates c
WHERE NOT EXISTS (SELECT 1 FROM work_experience we WHERE we.user_id=c.user_id AND we.company='Demo Training Organisation');

INSERT INTO certifications (user_id, name, issuing_organisation, year_obtained, verification_status)
SELECT c.user_id,
 CASE MOD(c.seq,4) WHEN 0 THEN 'Introduction to Cybersecurity' WHEN 1 THEN 'Data Analytics Foundations' WHEN 2 THEN 'Cloud Fundamentals' ELSE 'Professional Communication Skills' END,
 'Demo Learning Academy', 2023 + MOD(c.seq,4), 'unverified'
FROM tmp_seed_candidates c
WHERE MOD(c.seq,2)=0 AND NOT EXISTS (SELECT 1 FROM certifications ce WHERE ce.user_id=c.user_id AND ce.issuing_organisation='Demo Learning Academy');

INSERT INTO candidate_consents (candidate_id, consent_type, status, granted_at, expires_at, consent_source)
SELECT c.user_id, x.consent_type, 'consented', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'SYNTHETIC DEMO DATA - NOT REAL CONSENT'
FROM tmp_seed_candidates c
JOIN (SELECT 'recruiter_discovery' AS consent_type UNION ALL SELECT 'client_sharing') x
WHERE NOT EXISTS (SELECT 1 FROM candidate_consents cc WHERE cc.candidate_id=c.user_id AND cc.consent_type=x.consent_type AND cc.consent_source='SYNTHETIC DEMO DATA - NOT REAL CONSENT');

INSERT IGNORE INTO notification_preferences (user_id, category, enabled, email_enabled)
SELECT c.user_id, x.category, 1, 1 FROM tmp_seed_candidates c
JOIN (SELECT 'programme_updates' AS category UNION ALL SELECT 'application_updates' UNION ALL SELECT 'assessment_updates') x;

-- 3) Add ten new programmes. Existing programmes are untouched.
INSERT INTO programmes
 (name, type, description, objectives, duration, start_date, end_date, status, created_by, programme_manager_id, programme_officer_id)
SELECT d.name, d.type, d.description, d.objectives, d.duration, d.start_date, d.end_date, 'active', 1, 2, 3
FROM (
 SELECT 'SEED-2026-01 | Digital Marketing and E-Commerce Learnership' AS name, 'learnership' AS type, 'A practical learnership covering digital campaigns, e-commerce operations, content planning, analytics and customer engagement.' AS description, 'Develop digital marketing capability, campaign execution, online merchandising and performance reporting.' AS objectives, '12 months' AS duration, '2027-01-01' AS start_date, '2028-01-28' AS end_date
 UNION ALL SELECT 'SEED-2026-02 | Cybersecurity Operations Graduate Programme', 'graduate_programme', 'A graduate programme introducing security operations, risk awareness, incident response, access management and security monitoring.', 'Build entry-level cybersecurity operations skills and responsible security practices.', '12 months', '2027-02-01', '2028-02-28'
 UNION ALL SELECT 'SEED-2026-03 | Data Analytics and Business Intelligence Programme', 'skills_development', 'A structured programme in data preparation, SQL, spreadsheet modelling, dashboard design and business intelligence.', 'Develop practical data analysis, reporting and evidence-based decision-making skills.', '9 months', '2027-03-01', '2028-03-28'
 UNION ALL SELECT 'SEED-2026-04 | Cloud Computing and DevOps Internship', 'internship', 'An internship focused on cloud fundamentals, deployment pipelines, containers, monitoring and collaborative software delivery.', 'Develop practical cloud and DevOps skills through supervised projects.', '12 months', '2027-04-01', '2028-04-28'
 UNION ALL SELECT 'SEED-2026-05 | IT Support and Network Administration Learnership', 'learnership', 'A workplace-oriented learnership in service desk operations, hardware support, operating systems, networking and customer service.', 'Prepare participants for entry-level IT support and network administration roles.', '12 months', '2027-05-01', '2028-05-28'
 UNION ALL SELECT 'SEED-2026-06 | Business Analysis and Project Coordination WIL', 'wil', 'Work-integrated learning in requirements gathering, process mapping, stakeholder communication, documentation and project coordination.', 'Apply business analysis and project coordination methods in a professional setting.', '6 months', '2027-06-01', '2028-06-28'
 UNION ALL SELECT 'SEED-2026-07 | Financial Technology and Digital Finance Programme', 'graduate_programme', 'A graduate programme covering digital financial services, data handling, process controls, customer onboarding and technology-enabled finance.', 'Build practical understanding of fintech operations and digital finance workflows.', '12 months', '2027-07-01', '2028-07-28'
 UNION ALL SELECT 'SEED-2026-08 | UI/UX and Product Design Internship', 'internship', 'An internship in user research, wireframing, prototyping, accessibility, design systems and usability evaluation.', 'Develop user-centred product design and prototyping skills.', '9 months', '2027-08-01', '2028-08-28'
 UNION ALL SELECT 'SEED-2026-09 | Mobile Application Development Programme', 'skills_development', 'A practical programme covering mobile application architecture, interface development, APIs, testing and release preparation.', 'Develop skills to design, build and test mobile applications.', '9 months', '2027-09-01', '2028-09-28'
 UNION ALL SELECT 'SEED-2026-10 | Renewable Energy Technology Support Learnership', 'learnership', 'A learnership introducing digital monitoring, technical support, data capture, safety awareness and basic renewable energy systems.', 'Develop technical support and digital operations skills for renewable energy environments.', '12 months', '2027-10-01', '2028-10-28'
) d
WHERE NOT EXISTS (SELECT 1 FROM programmes p WHERE p.name=d.name);

CREATE TEMPORARY TABLE tmp_seed_programmes (seq INT PRIMARY KEY, programme_id INT UNSIGNED NOT NULL UNIQUE) ENGINE=MEMORY;
INSERT INTO tmp_seed_programmes (seq, programme_id)
SELECT CAST(SUBSTRING(name,11,2) AS UNSIGNED), id FROM programmes
WHERE name REGEXP '^SEED-2026-[0-9]{2} \\|';

-- Programme skills and eligibility.
INSERT IGNORE INTO programme_skills (programme_id, skill_name, skill_category)
SELECT p.programme_id, x.skill_name, x.skill_category
FROM tmp_seed_programmes p
JOIN (SELECT 'Digital Literacy' AS skill_name, 'required_technical' AS skill_category UNION ALL SELECT 'Communication','required_soft' UNION ALL SELECT 'Problem Solving','required_soft' UNION ALL SELECT 'Project Management','preferred_technical') x;

INSERT INTO programme_eligibility
 (programme_id, qualification_level, qualification_name, field_of_study, min_completion_year, max_completion_year, min_experience, max_experience, province, availability, citizenship_residency, programme_specific)
SELECT p.programme_id, 'Certificate / Diploma / Degree', 'Relevant post-school qualification',
 'Information Technology, Business, Engineering or a related field', 2018, 2026, 0, 2,
 CASE MOD(p.seq,3) WHEN 0 THEN 'Gauteng' WHEN 1 THEN 'KwaZulu-Natal' ELSE 'All Provinces' END,
 'Available to participate in the programme schedule', 'South African citizen or legally eligible resident',
 'Synthetic eligibility criteria for demo and workflow testing'
FROM tmp_seed_programmes p
WHERE NOT EXISTS (SELECT 1 FROM programme_eligibility pe WHERE pe.programme_id=p.programme_id);

-- 4) Add exactly fifty cohorts: five cohorts for each new programme.
INSERT INTO cohorts
 (programme_id, supervisor_id, name, description, start_date, end_date, application_open_date, application_close_date,
  max_capacity, applications_count, location, province, delivery_mode, status, created_by)
SELECT p.programme_id, 5,
 CONCAT('SEED-COHORT-', LPAD((p.seq-1)*5+n.cohort_no,3,'0'), ' | ', n.city, ' Intake ', n.cohort_no),
 CONCAT('Synthetic cohort ', (p.seq-1)*5+n.cohort_no, ' for ', pr.name, '. Created for dashboard, recruitment, attendance and assessment testing.'),
 DATE_ADD('2026-09-01', INTERVAL (n.cohort_no-1) MONTH),
 DATE_ADD('2027-03-01', INTERVAL (n.cohort_no-1) MONTH),
 '2026-08-01', DATE_ADD('2026-10-15', INTERVAL (n.cohort_no-1) MONTH),
 30, 0, n.city, n.province,
 CASE MOD(n.cohort_no,3) WHEN 0 THEN 'remote' WHEN 1 THEN 'hybrid' ELSE 'on_site' END,
 CASE n.cohort_no WHEN 1 THEN 'active' WHEN 2 THEN 'active' ELSE 'open' END, 1
FROM tmp_seed_programmes p
JOIN programmes pr ON pr.id=p.programme_id
JOIN (SELECT 1 AS cohort_no,'Johannesburg' AS city,'Gauteng' AS province UNION ALL SELECT 2,'Pretoria','Gauteng' UNION ALL SELECT 3,'Durban','KwaZulu-Natal' UNION ALL SELECT 4,'Cape Town','Western Cape' UNION ALL SELECT 5,'Polokwane','Limpopo') n
WHERE NOT EXISTS (SELECT 1 FROM cohorts c WHERE c.programme_id=p.programme_id AND c.name=CONCAT('SEED-COHORT-', LPAD((p.seq-1)*5+n.cohort_no,3,'0'), ' | ', n.city, ' Intake ', n.cohort_no));

CREATE TEMPORARY TABLE tmp_seed_cohorts (seq INT PRIMARY KEY, cohort_id INT UNSIGNED NOT NULL UNIQUE, programme_id INT UNSIGNED NOT NULL) ENGINE=MEMORY;
INSERT INTO tmp_seed_cohorts (seq, cohort_id, programme_id)
SELECT CAST(SUBSTRING(name,13,3) AS UNSIGNED), id, programme_id FROM cohorts
WHERE name REGEXP '^SEED-COHORT-[0-9]{3} \\|';

-- Cohort configuration: skills, eligibility, workflow and document requirements.
INSERT IGNORE INTO cohort_skills (cohort_id, skill_name, skill_category)
SELECT c.cohort_id, x.skill_name, x.skill_category FROM tmp_seed_cohorts c
JOIN (SELECT 'SQL' AS skill_name,'required_technical' AS skill_category UNION ALL SELECT 'Communication','required_soft' UNION ALL SELECT 'Digital Literacy','preferred_technical') x;

INSERT INTO cohort_eligibility
 (cohort_id, qualification_level, qualification_name, field_of_study, min_completion_year, max_completion_year, min_experience, max_experience, province, availability, citizenship_residency, programme_specific)
SELECT c.cohort_id, 'Certificate / Diploma / Degree', 'Relevant qualification', 'IT, Business or related field', 2018, 2026, 0, 2,
 CASE WHEN MOD(c.seq,5)=0 THEN 'Limpopo' WHEN MOD(c.seq,5)=1 THEN 'Gauteng' WHEN MOD(c.seq,5)=2 THEN 'KwaZulu-Natal' WHEN MOD(c.seq,5)=3 THEN 'Western Cape' ELSE 'All Provinces' END,
 'Available for the advertised cohort dates', 'South African citizen or legally eligible resident', 'Synthetic cohort eligibility for testing'
FROM tmp_seed_cohorts c
WHERE NOT EXISTS (SELECT 1 FROM cohort_eligibility ce WHERE ce.cohort_id=c.cohort_id);

INSERT IGNORE INTO cohort_workflow (cohort_id, stage, is_active, sort_order)
SELECT c.cohort_id, x.stage, 1, x.sort_order FROM tmp_seed_cohorts c
JOIN (SELECT 'application' AS stage,1 AS sort_order UNION ALL SELECT 'eligibility_review',2 UNION ALL SELECT 'screening',3 UNION ALL SELECT 'assessment',4 UNION ALL SELECT 'interview',5 UNION ALL SELECT 'selection',6 UNION ALL SELECT 'onboarding',7 UNION ALL SELECT 'active_participant',8) x;

INSERT IGNORE INTO cohort_documents (cohort_id, document_name, is_required, verification_required, expiry_required)
SELECT c.cohort_id, x.document_name, 1, 1, 0 FROM tmp_seed_cohorts c
JOIN (SELECT 'Curriculum Vitae' AS document_name UNION ALL SELECT 'Identity Document' UNION ALL SELECT 'Qualification Certificate' UNION ALL SELECT 'Academic Transcript') x;

-- 5) Assign four of the new candidates to each of the fifty cohorts.
INSERT IGNORE INTO cohort_participants (cohort_id, user_id, status, selected_at, onboarded_at)
SELECT ch.cohort_id, ca.user_id,
 CASE MOD(ch.seq-1,5) WHEN 0 THEN 'active' WHEN 1 THEN 'active' WHEN 2 THEN 'onboarded' ELSE 'selected' END,
 DATE_SUB(NOW(), INTERVAL MOD(ca.seq,30) DAY),
 CASE WHEN MOD(ch.seq-1,5) IN (0,1,2) THEN DATE_SUB(NOW(), INTERVAL MOD(ca.seq,20) DAY) ELSE NULL END
FROM tmp_seed_candidates ca
JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1;

-- 6) Ten opportunities, two hundred applications and status history.
INSERT INTO opportunities
 (programme_id, cohort_id, title, type, organisation, short_description, full_description,
  application_open_date, application_close_date, start_date, end_date, available_positions, applications_count,
  min_age, max_age, province, city, physical_location, work_arrangement, status, created_by)
SELECT p.programme_id,
 (SELECT c.cohort_id FROM tmp_seed_cohorts c WHERE c.programme_id=p.programme_id ORDER BY c.seq LIMIT 1),
 CONCAT('SEED Opportunity ', LPAD(p.seq,2,'0'), ' - ', SUBSTRING(pr.name,17)),
 pr.type, 'Investhood IT - Demo Employer Network',
 CONCAT('Synthetic opportunity for ', pr.name), pr.description,
 '2026-11-15', '2027-01-15', pr.start_date, pr.end_date, 30, 0, 18, 35,
 'Gauteng', 'Johannesburg', 'Demo training location', 'hybrid', 'published', 1
FROM tmp_seed_programmes p JOIN programmes pr ON pr.id=p.programme_id
WHERE NOT EXISTS (SELECT 1 FROM opportunities o WHERE o.programme_id=p.programme_id AND o.title LIKE 'SEED Opportunity %');

CREATE TEMPORARY TABLE tmp_seed_opportunities (seq INT PRIMARY KEY, opportunity_id INT UNSIGNED NOT NULL UNIQUE) ENGINE=MEMORY;
INSERT INTO tmp_seed_opportunities (seq, opportunity_id)
SELECT p.seq, o.id FROM tmp_seed_programmes p JOIN opportunities o ON o.programme_id=p.programme_id AND o.title LIKE 'SEED Opportunity %';

INSERT INTO applications (application_reference, candidate_id, opportunity_id, status, submitted_at)
SELECT CONCAT('SEED-APP-', LPAD(c.seq,4,'0')), c.user_id, o.opportunity_id,
 CASE MOD(c.seq,8) WHEN 0 THEN 'submitted' WHEN 1 THEN 'under_review' WHEN 2 THEN 'shortlisted' WHEN 3 THEN 'assessment' WHEN 4 THEN 'interview' WHEN 5 THEN 'selected' WHEN 6 THEN 'waitlisted' ELSE 'rejected' END,
 DATE_SUB(NOW(), INTERVAL MOD(c.seq,45) DAY)
FROM tmp_seed_candidates c JOIN tmp_seed_opportunities o ON o.seq=MOD(c.seq-1,10)+1
WHERE NOT EXISTS (SELECT 1 FROM applications a WHERE a.application_reference=CONCAT('SEED-APP-', LPAD(c.seq,4,'0')));

INSERT INTO application_status_history (application_id, previous_status, new_status, changed_by, change_reason)
SELECT a.id, NULL, a.status, 4, 'Synthetic demo application status history.'
FROM applications a WHERE a.application_reference REGEXP '^SEED-APP-[0-9]{4}$'
AND NOT EXISTS (SELECT 1 FROM application_status_history h WHERE h.application_id=a.id AND h.change_reason='Synthetic demo application status history.');

-- Refresh counts only on the newly generated seed opportunities/cohorts.
UPDATE opportunities o
JOIN tmp_seed_opportunities so ON so.opportunity_id=o.id
SET o.applications_count=(SELECT COUNT(*) FROM applications a WHERE a.opportunity_id=o.id);

UPDATE cohorts co
JOIN tmp_seed_cohorts ch ON ch.cohort_id=co.id
SET co.applications_count=(
    SELECT COUNT(*) FROM applications a
    JOIN opportunities o ON o.id=a.opportunity_id
    WHERE o.cohort_id=co.id
);

-- 7) Candidate progress, attendance, candidate notifications and supervisor notices.
INSERT INTO candidate_progress_history
 (candidate_id, participation_id, cohort_id, programme_id, cohort_name, programme_name, previous_status, new_status, progress_stage, notes, updated_by, updated_by_name, created_at)
SELECT ca.user_id, cp.id, ch.cohort_id, ch.programme_id, co.name, pr.name, NULL, cp.status,
 CASE cp.status WHEN 'selected' THEN 'selection' WHEN 'onboarded' THEN 'onboarding' WHEN 'active' THEN 'programme_delivery' ELSE 'completion' END,
 'Synthetic demo progress history record.', 3, 'Programme Officer', DATE_SUB(NOW(), INTERVAL MOD(ca.seq,30) DAY)
FROM tmp_seed_candidates ca
JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1
JOIN cohort_participants cp ON cp.cohort_id=ch.cohort_id AND cp.user_id=ca.user_id
JOIN cohorts co ON co.id=ch.cohort_id JOIN programmes pr ON pr.id=ch.programme_id
WHERE NOT EXISTS (SELECT 1 FROM candidate_progress_history h WHERE h.candidate_id=ca.user_id AND h.cohort_id=ch.cohort_id AND h.notes='Synthetic demo progress history record.');

INSERT IGNORE INTO candidate_attendance (cohort_id, user_id, session_date, session_title, status, notes, recorded_by, created_at, updated_at)
SELECT ch.cohort_id, ca.user_id, DATE_SUB('2026-11-10', INTERVAL x.session_no*7 DAY),
 CASE x.session_no WHEN 1 THEN 'Digital Skills Workshop' ELSE 'Practical Cohort Session' END,
 CASE MOD(ca.seq+x.session_no,10) WHEN 0 THEN 'absent' WHEN 1 THEN 'late' ELSE 'present' END,
 'Synthetic attendance record for dashboard testing.', 3, NOW(), NOW()
FROM tmp_seed_candidates ca JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1
JOIN (SELECT 1 AS session_no UNION ALL SELECT 2) x;

INSERT IGNORE INTO candidate_notifications (user_id, category, title, message, module, dedupe_key)
SELECT ca.user_id, 'programme', 'Programme demo update',
 'A synthetic programme update has been added to your candidate portal for testing.', 'programmes',
 CONCAT('SEED-CANDIDATE-UPDATE-', LPAD(ca.seq,3,'0'))
FROM tmp_seed_candidates ca;

INSERT INTO supervisor_notifications (supervisor_id, title, message, type, link)
SELECT 5, CONCAT('Cohort update: ', co.name),
 CONCAT('Demo cohort ', co.name, ' has candidate records available for review.'), 'cohort',
 CONCAT('cohort_view.php?id=', co.id)
FROM tmp_seed_cohorts ch JOIN cohorts co ON co.id=ch.cohort_id
WHERE NOT EXISTS (SELECT 1 FROM supervisor_notifications sn WHERE sn.supervisor_id=5 AND CONVERT(sn.title USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(CONCAT('Cohort update: ',co.name) USING utf8mb4) COLLATE utf8mb4_general_ci);

-- 8) Programme delivery, milestones, outcomes, risks, announcements and demo finance.
INSERT INTO programme_budgets (programme_id, cohort_id, fiscal_period, allocated_amount, notes, created_by)
SELECT p.programme_id, NULL, 'FY2026/2027', 750000.00,
 'SYNTHETIC DEMO BUDGET - not an approved allocation or payment instruction.', 1
FROM tmp_seed_programmes p
WHERE NOT EXISTS (SELECT 1 FROM programme_budgets b WHERE b.programme_id=p.programme_id AND b.notes LIKE 'SYNTHETIC DEMO BUDGET%');

INSERT INTO programme_expenditures (programme_id, cohort_id, expense_date, category, description, amount, reference_number, recorded_by)
SELECT p.programme_id, ch.cohort_id, '2026-11-10', x.category, x.description, x.amount,
 CONCAT('SEED-EXP-', LPAD(p.seq,2,'0'), '-', x.line_no), 7
FROM tmp_seed_programmes p
JOIN tmp_seed_cohorts ch ON ch.programme_id=p.programme_id AND ch.seq=(p.seq-1)*5+1
JOIN (SELECT 1 AS line_no,'Training Materials' AS category,'Demo training material expense' AS description,12500.00 AS amount UNION ALL SELECT 2,'Learner Support','Demo learner support expense',8500.00) x
WHERE NOT EXISTS (SELECT 1 FROM programme_expenditures e WHERE e.reference_number=CONCAT('SEED-EXP-', LPAD(p.seq,2,'0'), '-', x.line_no));

INSERT INTO programme_milestones (programme_id, cohort_id, created_by, title, description, due_date, status, priority, progress)
SELECT p.programme_id, ch.cohort_id, 1, x.title, x.description, DATE_ADD('2027-03-01', INTERVAL x.milestone_no*30 DAY),
 CASE x.milestone_no WHEN 1 THEN 'planned' WHEN 2 THEN 'in_progress' ELSE 'planned' END,
 CASE x.milestone_no WHEN 3 THEN 'high' ELSE 'normal' END, CASE x.milestone_no WHEN 2 THEN 25 ELSE 0 END
FROM tmp_seed_programmes p
JOIN tmp_seed_cohorts ch ON ch.programme_id=p.programme_id AND ch.seq=(p.seq-1)*5+1
JOIN (SELECT 1 AS milestone_no,'Programme induction' AS title,'Complete induction and participant onboarding.' AS description UNION ALL SELECT 2,'Core training delivery','Deliver the core learning and practical activities.' UNION ALL SELECT 3,'Final assessment and close-out','Complete assessment, moderation and programme close-out.') x
WHERE NOT EXISTS (SELECT 1 FROM programme_milestones m WHERE m.programme_id=p.programme_id AND m.title=x.title);

INSERT INTO programme_outcomes (programme_id, cohort_id, candidate_id, outcome_type, outcome_title, details, achieved_on, verified, verification_reference, recorded_by)
SELECT ch.programme_id, ch.cohort_id, ca.user_id, 'achievement', 'Demo learning milestone recorded',
 'Synthetic programme outcome for testing reporting. This is not a verified real-world achievement.', '2026-11-10', 0,
 CONCAT('SEED-OUTCOME-', LPAD(ca.seq,3,'0')), 1
FROM tmp_seed_candidates ca JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1
WHERE MOD(ca.seq,5)=0 AND NOT EXISTS (SELECT 1 FROM programme_outcomes po WHERE po.verification_reference=CONCAT('SEED-OUTCOME-', LPAD(ca.seq,3,'0')));

INSERT INTO programme_risks (programme_id, cohort_id, title, description, category, likelihood, impact, mitigation_plan, owner_name, due_date, status, created_by)
SELECT p.programme_id, ch.cohort_id, 'Demo participant engagement risk',
 'Synthetic risk record for testing programme risk registers and dashboards.', 'Operational', 3, 3,
 'Monitor attendance, contact participants and escalate support needs through the programme team.',
 'Programme Officer', '2027-03-31', 'monitoring', 1
FROM tmp_seed_programmes p JOIN tmp_seed_cohorts ch ON ch.programme_id=p.programme_id AND ch.seq=(p.seq-1)*5+1
WHERE NOT EXISTS (SELECT 1 FROM programme_risks r WHERE r.programme_id=p.programme_id AND r.title='Demo participant engagement risk');

INSERT INTO programme_announcements (author_id, title, message, category, priority, target_role, programme_id, cohort_id, status, published_at)
SELECT 3, CONCAT('Programme information: ', pr.name),
 CONCAT('This is a synthetic announcement for ', pr.name, '. Use it to test programme communications.'),
 'general', 'normal', 'all', p.programme_id, ch.cohort_id, 'published', NOW()
FROM tmp_seed_programmes p JOIN programmes pr ON pr.id=p.programme_id
JOIN tmp_seed_cohorts ch ON ch.programme_id=p.programme_id AND ch.seq=(p.seq-1)*5+1
WHERE NOT EXISTS (SELECT 1 FROM programme_announcements a WHERE a.programme_id=p.programme_id AND a.title=CONCAT('Programme information: ',pr.name));

INSERT INTO programme_officer_activity_log (programme_officer_id, action, description, entity_type, entity_id, ip_address, user_agent)
SELECT 3, 'demo_data_seeded', CONCAT('Synthetic expansion records added for ', pr.name, '.'), 'programme', p.programme_id, '127.0.0.1', 'SQL demo seed'
FROM tmp_seed_programmes p JOIN programmes pr ON pr.id=p.programme_id
WHERE NOT EXISTS (SELECT 1 FROM programme_officer_activity_log l WHERE l.programme_officer_id=3 AND l.action='demo_data_seeded' AND l.entity_type='programme' AND l.entity_id=p.programme_id);

-- 9) Assessor data for all 200 seeded candidates.
INSERT IGNORE INTO assessment_outcomes (code, title, description, is_active) VALUES
 ('SEED-ASSESS-01','Digital and Web Development Assessment','Assess practical web development, programming and database skills.',1),
 ('SEED-ASSESS-02','IT Support and Networking Assessment','Assess troubleshooting, user support and networking fundamentals.',1),
 ('SEED-ASSESS-03','Data Analysis and Reporting Assessment','Assess data preparation, analysis, SQL and reporting.',1),
 ('SEED-ASSESS-04','Cybersecurity Awareness Assessment','Assess security fundamentals, risk awareness and safe operational practices.',1),
 ('SEED-ASSESS-05','Business and Project Skills Assessment','Assess requirements gathering, communication and project coordination.',1);

CREATE TEMPORARY TABLE tmp_seed_assessments (seq INT PRIMARY KEY, outcome_id INT UNSIGNED NOT NULL UNIQUE) ENGINE=MEMORY;
INSERT INTO tmp_seed_assessments (seq, outcome_id)
SELECT CAST(SUBSTRING(code,13,2) AS UNSIGNED), id FROM assessment_outcomes WHERE code REGEXP '^SEED-ASSESS-[0-9]{2}$';

INSERT INTO assessment_criteria (outcome_id, code, title, description, sort_order, is_active)
SELECT a.outcome_id, CONCAT('SEED-CRIT-', LPAD(a.seq,2,'0'), '-', x.criterion_no), x.title, x.description, x.criterion_no, 1
FROM tmp_seed_assessments a
JOIN (SELECT 1 AS criterion_no,'Technical knowledge' AS title,'Demonstrates relevant technical concepts and terminology.' AS description UNION ALL SELECT 2,'Practical application','Applies an appropriate method to the practical task.' UNION ALL SELECT 3,'Communication and documentation','Explains the approach and documents the result clearly.') x
WHERE NOT EXISTS (SELECT 1 FROM assessment_criteria ac WHERE ac.code=CONCAT('SEED-CRIT-', LPAD(a.seq,2,'0'), '-', x.criterion_no));

INSERT INTO assessment_attempts (participant_id, outcome_id, attempt_number, assessor_id, status, assessor_result, moderation_status, moderator_result, submitted_at, assessed_at, moderated_at, final_approved_at)
SELECT cp.id, a.outcome_id, 1, 6,
 CASE MOD(ca.seq,6) WHEN 0 THEN 'submitted' WHEN 1 THEN 'in_review' WHEN 2 THEN 'assessed' WHEN 3 THEN 'awaiting_moderation' WHEN 4 THEN 'final_approved' ELSE 'resubmission_required' END,
 CASE WHEN MOD(ca.seq,6) IN (0,1) THEN NULL WHEN MOD(ca.seq,6)=5 THEN 'not_yet_competent' ELSE 'competent' END,
 CASE WHEN MOD(ca.seq,6)=4 THEN 'approved' WHEN MOD(ca.seq,6)=5 THEN 'changes_requested' ELSE 'pending' END,
 CASE WHEN MOD(ca.seq,6)=4 THEN 'approved' WHEN MOD(ca.seq,6)=5 THEN 'changes_requested' ELSE NULL END,
 DATE_SUB(NOW(), INTERVAL MOD(ca.seq,40) DAY),
 CASE WHEN MOD(ca.seq,6) IN (2,3,4,5) THEN DATE_SUB(NOW(), INTERVAL MOD(ca.seq,25) DAY) ELSE NULL END,
 CASE WHEN MOD(ca.seq,6)=4 THEN DATE_SUB(NOW(), INTERVAL MOD(ca.seq,20) DAY) ELSE NULL END,
 CASE WHEN MOD(ca.seq,6)=4 THEN DATE_SUB(NOW(), INTERVAL MOD(ca.seq,18) DAY) ELSE NULL END
FROM tmp_seed_candidates ca
JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1
JOIN cohort_participants cp ON cp.cohort_id=ch.cohort_id AND cp.user_id=ca.user_id
JOIN tmp_seed_assessments a ON a.seq=MOD(ch.seq-1,5)+1
WHERE NOT EXISTS (SELECT 1 FROM assessment_attempts aa WHERE aa.participant_id=cp.id AND aa.outcome_id=a.outcome_id AND aa.attempt_number=1);

INSERT INTO assessment_evidence (attempt_id, evidence_type, title, description, file_name, file_path, submitted_at)
SELECT aa.id, 'document', CONCAT('Demo assessment evidence - candidate ', LPAD(ca.seq,3,'0')),
 'Synthetic evidence placeholder only. No real file is attached; upload genuine evidence when testing file handling.', NULL, NULL, aa.submitted_at
FROM tmp_seed_candidates ca
JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1
JOIN cohort_participants cp ON cp.cohort_id=ch.cohort_id AND cp.user_id=ca.user_id
JOIN tmp_seed_assessments a ON a.seq=MOD(ch.seq-1,5)+1
JOIN assessment_attempts aa ON aa.participant_id=cp.id AND aa.outcome_id=a.outcome_id AND aa.attempt_number=1
WHERE NOT EXISTS (SELECT 1 FROM assessment_evidence ev WHERE ev.attempt_id=aa.id AND ev.title=CONCAT('Demo assessment evidence - candidate ', LPAD(ca.seq,3,'0')));

INSERT INTO assessment_decisions (attempt_id, criterion_id, result, feedback, evidence_id, assessor_id)
SELECT aa.id, ac.id, aa.assessor_result,
 CASE WHEN aa.assessor_result='competent' THEN 'Synthetic demo feedback: criterion met for testing purposes.' ELSE 'Synthetic demo feedback: further practice and resubmission are required.' END,
 ev.id, 6
FROM tmp_seed_candidates ca
JOIN tmp_seed_cohorts ch ON ch.seq=FLOOR((ca.seq-1)/4)+1
JOIN cohort_participants cp ON cp.cohort_id=ch.cohort_id AND cp.user_id=ca.user_id
JOIN tmp_seed_assessments a ON a.seq=MOD(ch.seq-1,5)+1
JOIN assessment_attempts aa ON aa.participant_id=cp.id AND aa.outcome_id=a.outcome_id AND aa.attempt_number=1
JOIN assessment_criteria ac ON ac.outcome_id=a.outcome_id AND ac.code LIKE CONCAT('SEED-CRIT-',LPAD(a.seq,2,'0'),'-%')
LEFT JOIN assessment_evidence ev ON ev.attempt_id=aa.id
WHERE aa.assessor_result IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM assessment_decisions ad WHERE ad.attempt_id=aa.id AND ad.criterion_id=ac.id);

INSERT INTO assessor_activity_log (assessor_id, action, description, entity_type, entity_id, ip_address, user_agent)
SELECT 6, 'demo_assessment_recorded', 'Synthetic assessment record created for portal testing.', 'assessment_attempt', aa.id, '127.0.0.1', 'SQL demo seed'
FROM assessment_attempts aa
JOIN cohort_participants cp ON cp.id=aa.participant_id
JOIN tmp_seed_candidates ca ON ca.user_id=cp.user_id
WHERE aa.assessor_id=6 AND NOT EXISTS (SELECT 1 FROM assessor_activity_log l WHERE l.assessor_id=6 AND l.action='demo_assessment_recorded' AND l.entity_id=aa.id);

-- 10) Verification counts. These report total records carrying the SEED prefix.
COMMIT;

SELECT 'New candidate accounts' AS record_type, COUNT(*) AS total FROM users WHERE username REGEXP '^seed_candidate_[0-9]{3}$'
UNION ALL SELECT 'New candidate profiles', COUNT(*) FROM candidate_profiles cp JOIN users u ON u.id=cp.user_id WHERE u.username REGEXP '^seed_candidate_[0-9]{3}$'
UNION ALL SELECT 'New programmes', COUNT(*) FROM programmes WHERE name REGEXP '^SEED-2026-[0-9]{2} \\|'
UNION ALL SELECT 'New cohorts', COUNT(*) FROM cohorts WHERE name REGEXP '^SEED-COHORT-[0-9]{3} \\|'
UNION ALL SELECT 'New cohort memberships', COUNT(*) FROM cohort_participants cp JOIN users u ON u.id=cp.user_id WHERE u.username REGEXP '^seed_candidate_[0-9]{3}$'
UNION ALL SELECT 'New opportunities', COUNT(*) FROM opportunities WHERE title LIKE 'SEED Opportunity %'
UNION ALL SELECT 'New applications', COUNT(*) FROM applications WHERE application_reference REGEXP '^SEED-APP-[0-9]{4}$'
UNION ALL SELECT 'New assessment attempts', COUNT(*) FROM assessment_attempts aa JOIN cohort_participants cp ON cp.id=aa.participant_id JOIN users u ON u.id=cp.user_id WHERE u.username REGEXP '^seed_candidate_[0-9]{3}$';
