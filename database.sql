CREATE DATABASE IF NOT EXISTS university_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE university_portal;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. USERS (central auth table — all roles)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NULL,             -- bcrypt hash (NULL allowed for Google-only accounts)
    role          ENUM('student','faculty','admin') NOT NULL,
    profile_photo VARCHAR(255)  DEFAULT 'default.png',
    phone         VARCHAR(15)   DEFAULT NULL,
    google_id     VARCHAR(255)  DEFAULT NULL UNIQUE, -- Google account "sub" id, once linked
    is_active     TINYINT(1)    DEFAULT 1,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 1b. PASSWORD RESETS (for "Forgot password" flow)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,   -- sha256 hash of the token sent by email
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 2. DEPARTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS departments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    code       VARCHAR(10)  NOT NULL UNIQUE,
    hod_id     INT          DEFAULT NULL,        -- FK to users (faculty)
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. STUDENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL UNIQUE,
    enrollment_no   VARCHAR(20)  NOT NULL UNIQUE,
    department_id   INT          NOT NULL,
    semester        TINYINT      DEFAULT 1,
    batch_year      YEAR         DEFAULT NULL,
    dob             DATE         DEFAULT NULL,
    gender          ENUM('Male','Female','Other') DEFAULT 'Male',
    address         TEXT         DEFAULT NULL,
    guardian_name   VARCHAR(100) DEFAULT NULL,
    guardian_phone  VARCHAR(15)  DEFAULT NULL,
    admission_date  DATE         DEFAULT NULL,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);

-- ============================================================
-- 4. FACULTY
-- ============================================================
CREATE TABLE IF NOT EXISTS faculty (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL UNIQUE,
    employee_id     VARCHAR(20)  NOT NULL UNIQUE,
    department_id   INT          NOT NULL,
    designation     VARCHAR(100) DEFAULT 'Assistant Professor',
    qualification   VARCHAR(150) DEFAULT NULL,
    joining_date    DATE         DEFAULT NULL,
    specialization  VARCHAR(150) DEFAULT NULL,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);

-- ============================================================
-- 5. COURSES / SUBJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS courses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    code          VARCHAR(20)  NOT NULL UNIQUE,
    department_id INT          NOT NULL,
    semester      TINYINT      NOT NULL,
    credits       TINYINT      DEFAULT 4,
    type          ENUM('Theory','Practical','Elective') DEFAULT 'Theory',
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);

-- ============================================================
-- 6. CLASS ASSIGNMENTS (faculty assigned to course+semester)
-- ============================================================
CREATE TABLE IF NOT EXISTS class_assignments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id    INT  NOT NULL,
    course_id     INT  NOT NULL,
    academic_year VARCHAR(9) NOT NULL,           -- e.g. 2024-2025
    UNIQUE KEY unique_assign (faculty_id, course_id, academic_year),
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE
);

-- ============================================================
-- 7. ENROLLMENTS (student enrolled in course)
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT  NOT NULL,
    course_id     INT  NOT NULL,
    academic_year VARCHAR(9) NOT NULL,
    enrolled_at   DATETIME   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enroll (student_id, course_id, academic_year),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE
);

-- ============================================================
-- 8. ATTENDANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT  NOT NULL,
    course_id     INT  NOT NULL,
    faculty_id    INT  NOT NULL,
    date          DATE NOT NULL,
    status        ENUM('Present','Absent','Late') DEFAULT 'Present',
    remarks       VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY unique_att (student_id, course_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)  ON DELETE CASCADE
);

-- ============================================================
-- 9. ASSIGNMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS assignments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    course_id     INT          NOT NULL,
    faculty_id    INT          NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         DEFAULT NULL,
    file_path     VARCHAR(255) DEFAULT NULL,
    max_marks     DECIMAL(5,2) DEFAULT 100,
    due_date      DATETIME     NOT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)  ON DELETE CASCADE
);

-- ============================================================
-- 10. ASSIGNMENT SUBMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id   INT          NOT NULL,
    student_id      INT          NOT NULL,
    file_path       VARCHAR(255) DEFAULT NULL,
    submitted_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    marks_obtained  DECIMAL(5,2) DEFAULT NULL,
    feedback        TEXT         DEFAULT NULL,
    status          ENUM('Submitted','Graded','Late') DEFAULT 'Submitted',
    UNIQUE KEY unique_sub (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE CASCADE
);

-- ============================================================
-- 11. EXAMS
-- ============================================================
CREATE TABLE IF NOT EXISTS exams (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    course_id     INT          NOT NULL,
    title         VARCHAR(200) NOT NULL,
    exam_type     ENUM('Internal','Mid-Term','End-Term','Practical') DEFAULT 'Internal',
    exam_date     DATE         NOT NULL,
    start_time    TIME         DEFAULT NULL,
    duration_min  INT          DEFAULT 60,
    max_marks     DECIMAL(5,2) DEFAULT 100,
    room          VARCHAR(50)  DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ============================================================
-- 12. GRADES / RESULTS
-- ============================================================
CREATE TABLE IF NOT EXISTS grades (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT          NOT NULL,
    exam_id         INT          NOT NULL,
    marks_obtained  DECIMAL(5,2) DEFAULT NULL,
    grade           VARCHAR(5)   DEFAULT NULL,
    remarks         VARCHAR(255) DEFAULT NULL,
    entered_by      INT          NOT NULL,       -- faculty user_id
    entered_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_grade (student_id, exam_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id)    REFERENCES exams(id)    ON DELETE CASCADE
);

-- ============================================================
-- 13. TIMETABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    course_id     INT          NOT NULL,
    faculty_id    INT          NOT NULL,
    day_of_week   ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time    TIME         NOT NULL,
    end_time      TIME         NOT NULL,
    room          VARCHAR(50)  DEFAULT NULL,
    academic_year VARCHAR(9)   NOT NULL,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)  ON DELETE CASCADE
);

-- ============================================================
-- 14. STUDY MATERIALS / RESOURCES
-- ============================================================
CREATE TABLE IF NOT EXISTS resources (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    course_id     INT          NOT NULL,
    faculty_id    INT          NOT NULL,
    title         VARCHAR(200) NOT NULL,
    type          ENUM('Notes','E-Book','Video','Other') DEFAULT 'Notes',
    file_path     VARCHAR(255) DEFAULT NULL,
    url           VARCHAR(500) DEFAULT NULL,
    description   TEXT         DEFAULT NULL,
    uploaded_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)  ON DELETE CASCADE
);

-- ============================================================
-- 15. MESSAGES / CHAT
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT  NOT NULL,
    receiver_id INT  NOT NULL,
    subject     VARCHAR(200) DEFAULT NULL,
    body        TEXT         NOT NULL,
    is_read     TINYINT(1)   DEFAULT 0,
    sent_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 16. ANNOUNCEMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    posted_by   INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    body        TEXT         NOT NULL,
    target_role ENUM('all','student','faculty') DEFAULT 'all',
    is_active   TINYINT(1)   DEFAULT 1,
    posted_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 17. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    message     TEXT         NOT NULL,
    type        VARCHAR(50)  DEFAULT 'info',
    is_read     TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 18. STUDENT PROJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS projects (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT          NOT NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT         DEFAULT NULL,
    status       ENUM('Pending','In Progress','Completed') DEFAULT 'In Progress',
    github_url   VARCHAR(300) DEFAULT NULL,
    file_path    VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 19. ACHIEVEMENTS / CERTIFICATES
-- ============================================================
CREATE TABLE IF NOT EXISTS achievements (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT          NOT NULL,
    title        VARCHAR(200) NOT NULL,
    type         ENUM('Certificate','Award','Badge','Competition') DEFAULT 'Certificate',
    issued_by    VARCHAR(150) DEFAULT NULL,
    issued_date  DATE         DEFAULT NULL,
    file_path    VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 20. FEES / FINANCIAL
-- ============================================================
CREATE TABLE IF NOT EXISTS fees (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT          NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    fee_type      VARCHAR(100)  DEFAULT 'Tuition Fee',
    academic_year VARCHAR(9)    NOT NULL,
    due_date      DATE          NOT NULL,
    paid_date     DATE          DEFAULT NULL,
    status        ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
    transaction_id VARCHAR(100) DEFAULT NULL,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 21. PLACEMENT / CAREER
-- ============================================================
CREATE TABLE IF NOT EXISTS companies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    industry     VARCHAR(100) DEFAULT NULL,
    website      VARCHAR(300) DEFAULT NULL,
    contact_name VARCHAR(100) DEFAULT NULL,
    contact_email VARCHAR(150) DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS job_postings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_id      INT          NOT NULL,
    title           VARCHAR(200) NOT NULL,
    type            ENUM('Job','Internship') DEFAULT 'Job',
    description     TEXT         DEFAULT NULL,
    eligibility     TEXT         DEFAULT NULL,
    package         VARCHAR(100) DEFAULT NULL,
    apply_deadline  DATE         DEFAULT NULL,
    status          ENUM('Open','Closed') DEFAULT 'Open',
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS job_applications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    job_id      INT NOT NULL,
    applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    status      ENUM('Applied','Shortlisted','Selected','Rejected') DEFAULT 'Applied',
    UNIQUE KEY unique_app (student_id, job_id),
    FOREIGN KEY (student_id) REFERENCES students(id)     ON DELETE CASCADE,
    FOREIGN KEY (job_id)     REFERENCES job_postings(id) ON DELETE CASCADE
);

-- ============================================================
-- 22. STUDENT FEEDBACK (on faculty/courses)
-- ============================================================
CREATE TABLE IF NOT EXISTS feedback (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT          NOT NULL,
    faculty_id  INT          NOT NULL,
    course_id   INT          NOT NULL,
    rating      TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT         DEFAULT NULL,
    submitted_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fb (student_id, faculty_id, course_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)  ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE
);

-- ============================================================
-- 23. COMMUNITY / FORUM
-- ============================================================
CREATE TABLE IF NOT EXISTS forum_posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    body        TEXT         NOT NULL,
    category    VARCHAR(100) DEFAULT 'General',
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS forum_replies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT  NOT NULL,
    user_id     INT  NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)       ON DELETE CASCADE
);

-- ============================================================
-- 24. LEAVE MANAGEMENT (Faculty)
-- ============================================================
CREATE TABLE IF NOT EXISTS leave_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id   INT          NOT NULL,
    leave_type   VARCHAR(50)  DEFAULT 'Casual',
    from_date    DATE         NOT NULL,
    to_date      DATE         NOT NULL,
    reason       TEXT         DEFAULT NULL,
    status       ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    approved_by  INT          DEFAULT NULL,
    applied_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id)  REFERENCES faculty(id)  ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id)    ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA — Sample users (password = "root", properly bcrypt-hashed)
-- ============================================================

INSERT INTO users (name, email, password, role, phone) VALUES
('Admin User',         'Nipunsinhjadeja25@gmail.com', '$2b$10$58fRpVDtVPTPYxijdIFNMuveldR4RBNu2kKoqiJnxu5rrX7n9fhui', 'admin',   '9000000001'),
('Pratham Nichani',    'nichanipratham@gmail.com',    '$2b$10$58fRpVDtVPTPYxijdIFNMuveldR4RBNu2kKoqiJnxu5rrX7n9fhui', 'faculty', '9000000002'),
('Nency Ishani',       'nencyishani42@gmail.com',     '$2y$10$PZycmZSD56k353PizNYD6.OTDe6uKuz94yX8nLJgkRvbBcj/RZHkG', 'faculty', '9000000003'),
('Rahul Patel',        'rahul@student.com',           '$2y$10$PZycmZSD56k353PizNYD6.OTDe6uKuz94yX8nLJgkRvbBcj/RZHkG', 'student', '9000000004'),
('Neha Shah',          'neha@student.com',            '$2y$10$PZycmZSD56k353PizNYD6.OTDe6uKuz94yX8nLJgkRvbBcj/RZHkG', 'student', '9000000005'),
('Arjun Desai',        'arjun@student.com',           '$2y$10$PZycmZSD56k353PizNYD6.OTDe6uKuz94yX8nLJgkRvbBcj/RZHkG', 'student', '9000000006'),
('Vraj Patel',         'Vraj36@gmail.com',            '$2y$10$PZycmZSD56k353PizNYD6.OTDe6uKuz94yX8nLJgkRvbBcj/RZHkG', 'faculty', '9000000007'),
('Krish Viradiya',     'Krishviradiya26@gmail.com',   '$2y$10$PZycmZSD56k353PizNYD6.OTDe6uKuz94yX8nLJgkRvbBcj/RZHkG', 'faculty', '9000000008');

INSERT INTO departments (name, code) VALUES
('Computer Science',         'CS'),
('Information Technology',   'IT'),
('Electronics Engineering',  'EC'),
('Mechanical Engineering',   'ME');

INSERT INTO faculty (user_id, employee_id, department_id, designation, qualification, joining_date) VALUES
(2, 'FAC001', 1, 'Associate Professor',    'PhD Computer Science',  '2018-06-01'),
(3, 'FAC002', 2, 'Assistant Professor',    'M.Tech IT',             '2020-07-15'),
(7, 'FAC003', 3, 'Assistant Professor',    'M.Tech Electronics',    '2021-07-01'),
(8, 'FAC004', 4, 'Assistant Professor',    'M.Tech Mechanical',     '2022-07-01');

INSERT INTO students (user_id, enrollment_no, department_id, semester, batch_year, dob, gender, admission_date) VALUES
(4, 'STU2022001', 1, 5, 2022, '2003-04-12', 'Male',   '2022-07-20'),
(5, 'STU2022002', 1, 5, 2022, '2003-09-25', 'Female', '2022-07-20'),
(6, 'STU2022003', 2, 3, 2023, '2004-01-08', 'Male',   '2023-07-18');

INSERT INTO courses (name, code, department_id, semester, credits, type) VALUES
('Data Structures',          'CS301', 1, 3, 4, 'Theory'),
('Database Management',      'CS302', 1, 3, 4, 'Theory'),
('Web Technologies',         'CS501', 1, 5, 4, 'Theory'),
('Web Technologies Lab',     'CS502', 1, 5, 2, 'Practical'),
('Operating Systems',        'CS503', 1, 5, 4, 'Theory'),
('Networking Fundamentals',  'IT301', 2, 3, 4, 'Theory');

INSERT INTO class_assignments (faculty_id, course_id, academic_year) VALUES
(1, 3, '2024-2025'), (1, 4, '2024-2025'), (1, 5, '2024-2025'),
(2, 6, '2024-2025');

INSERT INTO enrollments (student_id, course_id, academic_year) VALUES
(1, 3, '2024-2025'), (1, 4, '2024-2025'), (1, 5, '2024-2025'),
(2, 3, '2024-2025'), (2, 4, '2024-2025'), (2, 5, '2024-2025'),
(3, 6, '2024-2025');

INSERT INTO timetable (course_id, faculty_id, day_of_week, start_time, end_time, room, academic_year) VALUES
(3, 1, 'Monday',    '09:00:00', '10:00:00', 'A-101', '2024-2025'),
(3, 1, 'Wednesday', '09:00:00', '10:00:00', 'A-101', '2024-2025'),
(5, 1, 'Tuesday',   '10:00:00', '11:00:00', 'A-102', '2024-2025'),
(5, 1, 'Thursday',  '10:00:00', '11:00:00', 'A-102', '2024-2025'),
(4, 1, 'Friday',    '11:00:00', '13:00:00', 'Lab-1', '2024-2025'),
(6, 2, 'Monday',    '11:00:00', '12:00:00', 'B-201', '2024-2025');

INSERT INTO announcements (posted_by, title, body, target_role) VALUES
(1, 'Welcome to University Portal', 'Welcome to the new University Portal! Please complete your profile.', 'all'),
(1, 'Mid-Term Exam Schedule Released', 'Mid-term exams will be held from 15th July 2025. Check timetable.', 'student'),
(2, 'Web Technologies Assignment Due', 'Assignment 1 for Web Technologies is due on 20th June 2025.', 'student');

INSERT INTO companies (name, industry, website, contact_name, contact_email) VALUES
('TechCorp Solutions', 'Software',    'https://techcorp.com', 'HR Manager', 'hr@techcorp.com'),
('InfoSys Ltd',        'IT Services', 'https://infosys.com',  'Recruiter',  'campus@infosys.com');

INSERT INTO job_postings (company_id, title, type, description, eligibility, package, apply_deadline) VALUES
(1, 'Junior PHP Developer', 'Job',        'Work on web applications using PHP & MySQL.', '6.5 CGPA, CS/IT branch', '4.5 LPA', '2025-08-01'),
(2, 'Summer Internship',    'Internship', '2-month paid internship in software development.', '7.0 CGPA', '15000/month', '2025-07-01');

-- ============================================================
-- END
-- ============================================================
